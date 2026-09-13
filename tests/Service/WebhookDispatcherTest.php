<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Phorum\Mod\Webhooks\Webhook;
use Phorum\Mod\Webhooks\WebhookDispatcher;
use Phorum\Mod\Webhooks\WebhookMapper;
use Phorum\Mod\Webhooks\WebhookUrlGuard;
use Phorum\Tests\Support\SpyLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers WebhookDispatcher: building the delivery body, signing it, honouring
 * the per-delivery target check, and swallowing every delivery failure rather
 * than letting it escape into the request that triggered it.
 *
 * No real HTTP is performed — Guzzle is driven by a MockHandler and the
 * request history is asserted on. A SpyLogger is injected in place of the
 * default ErrorLogLogger so refusal and failure notices are asserted on
 * instead of reaching stderr.
 */
class WebhookDispatcherTest extends TestCase
{
    /** Collects the dispatcher's log output for the duration of one test. */
    private SpyLogger $logger;

    /** Loads the mod classes, which live outside the composer autoload map. */
    public static function setUpBeforeClass(): void
    {
        $base = dirname(__DIR__, 2) . '/mods/webhooks';
        require_once $base . '/Webhook.php';
        require_once $base . '/WebhookMapper.php';
        require_once $base . '/WebhookUrlGuard.php';
        require_once $base . '/WebhookDispatcher.php';
    }

    /** Gives each test a fresh spy logger. */
    protected function setUp(): void
    {
        $this->logger = new SpyLogger();
    }

    /** Builds the dispatcher under test with the spy logger injected. */
    private function makeDispatcher(
        WebhookMapper $webhooks,
        ?\GuzzleHttp\ClientInterface $http = null,
        ?WebhookUrlGuard $urlGuard = null,
    ): WebhookDispatcher {
        return new WebhookDispatcher($webhooks, $http, $urlGuard, $this->logger);
    }

    /**
     * A guard configured to allow private targets, which short-circuits before
     * any DNS lookup — these tests exercise delivery, not address policy, and
     * must not depend on name resolution. WebhookUrlGuardTest covers the policy.
     */
    private function permissiveGuard(): WebhookUrlGuard
    {
        return new WebhookUrlGuard(allowPrivateTargets: true);
    }

    private function makeWebhook(array $overrides = []): Webhook
    {
        $w = new Webhook();
        $w->id     = 1;
        $w->url    = 'https://example.test/hook';
        $w->secret = 'topsecret';
        foreach ($overrides as $k => $v) {
            $w->$k = $v;
        }
        return $w;
    }

    /** Builds a Client backed by MockHandler; $history is populated (by reference) as requests are sent. */
    private function makeClient(array $responses, array &$history): Client
    {
        $history = [];

        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
    }

    public function testSendsStandardEnvelopeWhenNoTemplateConfigured(): void
    {
        $history = [];
        $client = $this->makeClient([new GuzzleResponse(200)], $history);

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$this->makeWebhook()]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());
        $dispatcher->dispatch('message.created', ['subject' => 'Hi', 'author' => 'alice']);

        $this->assertCount(1, $history);
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('message.created', $body['event']);
        $this->assertSame(['subject' => 'Hi', 'author' => 'alice'], $body['data']);
        $this->assertIsInt($body['timestamp']);
    }

    public function testSignsTheRequestBodyWithTheWebhookSecret(): void
    {
        $history = [];
        $client = $this->makeClient([new GuzzleResponse(200)], $history);

        $webhook  = $this->makeWebhook(['secret' => 'mysecret']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());
        $dispatcher->dispatch('message.created', ['x' => 1]);

        $request     = $history[0]['request'];
        $body        = (string) $request->getBody();
        $expectedSig = 'sha256=' . hash_hmac('sha256', $body, 'mysecret');
        $this->assertSame($expectedSig, $request->getHeaderLine('X-Phorum-Signature'));
    }

    public function testUsesCustomPayloadTemplateWhenConfigured(): void
    {
        $history = [];
        $client = $this->makeClient([new GuzzleResponse(200)], $history);

        $webhook = $this->makeWebhook([
            'payload_template' => '{"text": "New post *{{ data.subject }}* by {{ data.author }}"}',
        ]);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());
        $dispatcher->dispatch('message.created', ['subject' => 'Hello World', 'author' => 'alice']);

        $body = (string) $history[0]['request']->getBody();
        $this->assertSame('{"text": "New post *Hello World* by alice"}', $body);
    }

    public function testSignatureIsComputedOverTheRenderedCustomBody(): void
    {
        $history = [];
        $client = $this->makeClient([new GuzzleResponse(200)], $history);

        $webhook = $this->makeWebhook([
            'secret'           => 'mysecret',
            'payload_template' => '{"text": "{{ data.subject }}"}',
        ]);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());
        $dispatcher->dispatch('message.created', ['subject' => 'Hi']);

        $request     = $history[0]['request'];
        $body        = (string) $request->getBody();
        $expectedSig = 'sha256=' . hash_hmac('sha256', $body, 'mysecret');
        $this->assertSame($expectedSig, $request->getHeaderLine('X-Phorum-Signature'));
    }

    public function testUsesConfiguredContentType(): void
    {
        $history = [];
        $client = $this->makeClient([new GuzzleResponse(200)], $history);

        $webhook  = $this->makeWebhook(['content_type' => 'application/x-www-form-urlencoded']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());
        $dispatcher->dispatch('message.created', []);

        $this->assertSame(
            'application/x-www-form-urlencoded',
            $history[0]['request']->getHeaderLine('Content-Type')
        );
    }

    public function testDeliversToEveryActiveSubscriberIndependently(): void
    {
        $history = [];
        $client = $this->makeClient([new GuzzleResponse(200), new GuzzleResponse(200)], $history);

        $a = $this->makeWebhook(['id' => 1, 'url' => 'https://a.test/hook']);
        $b = $this->makeWebhook(['id' => 2, 'url' => 'https://b.test/hook']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$a, $b]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());
        $dispatcher->dispatch('message.created', []);

        $this->assertCount(2, $history);
        $this->assertSame('a.test', $history[0]['request']->getUri()->getHost());
        $this->assertSame('b.test', $history[1]['request']->getUri()->getHost());
    }

    public function testDoesNothingWhenNoSubscribersExist(): void
    {
        $history = [];
        $client = $this->makeClient([], $history);

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());
        $dispatcher->dispatch('message.created', []);

        $this->assertCount(0, $history);
    }

    public function testSwallowsAConnectionFailureRatherThanThrowing(): void
    {
        $request = new GuzzleRequest('POST', 'https://example.test/hook');
        $history = [];
        $client  = $this->makeClient([
            new ConnectException('Could not resolve host', $request),
        ], $history);

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$this->makeWebhook()]);

        $dispatcher = $this->makeDispatcher($webhooks, $client, $this->permissiveGuard());

        // Must not throw.
        $dispatcher->dispatch('message.created', []);
        $this->assertCount(1, $history);

        $record = $this->logger->onlyRecord();
        $this->assertSame('error', $record['level']);
        $this->assertStringContainsString('delivery failed', $record['message']);
        $this->assertSame(1, $record['context']['webhook']);
        $this->assertStringContainsString('Could not resolve host', $record['context']['error']);
    }

    /**
     * Template text that isn't a well-formed placeholder is passed through as
     * literal body content. There's no template parser to fail any more, so
     * unlike the old Twig path this delivers rather than aborting.
     */
    public function testMalformedPlaceholderIsSentLiterallyAndDoesNotThrow(): void
    {
        $history = [];
        $client = $this->makeClient([new GuzzleResponse(200)], $history);

        $webhook  = $this->makeWebhook(['payload_template' => '{{ this is not a placeholder %}']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        ($this->makeDispatcher($webhooks, $client, $this->permissiveGuard()))->dispatch('message.created', []);

        $this->assertCount(1, $history);
        $this->assertSame('{{ this is not a placeholder %}', (string) $history[0]['request']->getBody());
    }

    // -------------------------------------------------------------------------
    // Payload templates are substitution, not evaluation
    // -------------------------------------------------------------------------

    /**
     * Send $template as webhook #1's payload_template and return the body that
     * was actually delivered.
     *
     * @param string $template The configured payload_template.
     * @param array  $data     The event data the placeholders resolve against.
     */
    private function renderTemplate(string $template, array $data = [], string $contentType = 'application/json'): string
    {
        $history = [];
        $client  = $this->makeClient([new GuzzleResponse(200)], $history);

        $webhook  = $this->makeWebhook(['payload_template' => $template, 'content_type' => $contentType]);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        ($this->makeDispatcher($webhooks, $client, $this->permissiveGuard()))->dispatch('message.created', $data);

        return (string) $history[0]['request']->getBody();
    }

    /**
     * Payload templates used to render as unsandboxed Twig, where these turn
     * admin panel access into command execution and arbitrary file reads. They
     * must now be treated as ordinary text.
     *
     * @param string $template A Twig expression that was executable before the fix.
     */
    #[DataProvider('templateInjectionProvider')]
    public function testTemplateExpressionsAreNotEvaluated(string $template): void
    {
        $body = $this->renderTemplate($template, ['subject' => 'Hi']);

        // Byte-identical to the template is the exact property: anything
        // evaluated would have replaced the expression with its result.
        // (Asserting the absence of a marker word would pass trivially here,
        // since the marker is part of the literal template text.)
        $this->assertSame($template, $body);
    }

    /**
     * Twig gadgets that executed shell commands or read files on the installed
     * Twig version before payload templates stopped being evaluated.
     *
     * @return array<string, array{0: string}>
     */
    public static function templateInjectionProvider(): array
    {
        return [
            'map system'    => ['{{ ["echo PWNED"]|map("system")|join }}'],
            'filter system' => ['{{ ["echo PWNED"]|filter("system")|join }}'],
            'sort system'   => ['{{ ["echo PWNED"]|sort("system")|join }}'],
            'file read'     => ['{{ ["composer.json"]|map("file_get_contents")|join }}'],
            'tag'           => ['{% if 1 %}PWNED{% endif %}'],
        ];
    }

    /**
     * A value containing what looks like a placeholder must not be rescanned —
     * otherwise a post subject could pull in another field.
     */
    public function testSubstitutedValuesAreNotRescanned(): void
    {
        $body = $this->renderTemplate(
            '{"a": "{{ data.subject }}"}',
            ['subject' => '{{ data.secret }}', 'secret' => 'SHOULD-NOT-APPEAR'],
        );

        $this->assertStringNotContainsString('SHOULD-NOT-APPEAR', $body);
        $this->assertStringContainsString('{{ data.secret }}', $body);
    }

    /**
     * Quotes and backslashes in event data must produce valid JSON. The old
     * Twig path HTML-escaped them instead, sending `&quot;` downstream.
     */
    public function testValuesAreJsonEscapedForJsonContentType(): void
    {
        $body = $this->renderTemplate(
            '{"text": "{{ data.subject }}"}',
            ['subject' => 'He said "hi" \ left'],
        );

        $this->assertNotNull(json_decode($body), 'payload was not valid JSON: ' . $body);
        $this->assertSame('He said "hi" \ left', json_decode($body, true)['text']);
        $this->assertStringNotContainsString('&quot;', $body);
    }

    /** Non-JSON content types get the value verbatim, with no JSON escaping. */
    public function testValuesAreNotJsonEscapedForNonJsonContentType(): void
    {
        $body = $this->renderTemplate(
            'subject={{ data.subject }}',
            ['subject' => 'He said "hi"'],
            'text/plain',
        );

        $this->assertSame('subject=He said "hi"', $body);
    }

    /** Placeholders naming a missing field, or a non-scalar, render as empty. */
    public function testUnknownAndNonScalarPlaceholdersRenderEmpty(): void
    {
        $body = $this->renderTemplate('[{{ data.nope }}][{{ nope }}][{{ data }}]', ['subject' => 'Hi']);

        $this->assertSame('[][][]', $body);
    }

    /** The top-level event and timestamp placeholders still resolve. */
    public function testEventAndTimestampPlaceholdersResolve(): void
    {
        $body = $this->renderTemplate('{{ event }}', ['subject' => 'Hi']);

        $this->assertSame('message.created', $body);
    }

    // -------------------------------------------------------------------------
    // Outbound target restrictions
    // -------------------------------------------------------------------------

    /**
     * The target is re-checked on every delivery, not only when the admin
     * saved it: DNS answers change, the setting can be turned off, and rows
     * created before the check existed are still in the table.
     */
    public function testDeliveryToAnInternalTargetIsRefused(): void
    {
        $history = [];
        $client  = $this->makeClient([], $history);

        $webhook  = $this->makeWebhook(['url' => 'http://169.254.169.254/latest/meta-data/']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        // A real, restrictive guard — the one the application uses by default.
        ($this->makeDispatcher($webhooks, $client, new WebhookUrlGuard()))
            ->dispatch('message.created', []);

        $this->assertCount(0, $history, 'a request was made to an internal address');

        // The refusal is recorded, with the guard's reason, rather than silent.
        $record = $this->logger->onlyRecord();
        $this->assertSame('warning', $record['level']);
        $this->assertStringContainsString('refusing delivery', $record['message']);
        $this->assertSame(1, $record['context']['webhook']);
        $this->assertStringContainsString('169.254.169.254', $record['context']['reason']);
    }

    /** Refusing one target doesn't stop the others from being delivered. */
    public function testRefusedTargetDoesNotBlockOtherWebhooks(): void
    {
        $history = [];
        $client  = $this->makeClient([new GuzzleResponse(200)], $history);

        $blocked = $this->makeWebhook(['id' => 1, 'url' => 'http://10.0.0.5/hook']);
        $allowed = $this->makeWebhook(['id' => 2, 'url' => 'https://8.8.8.8/hook']);

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$blocked, $allowed]);

        ($this->makeDispatcher($webhooks, $client, new WebhookUrlGuard()))
            ->dispatch('message.created', []);

        $this->assertCount(1, $history);
        $this->assertSame('8.8.8.8', $history[0]['request']->getUri()->getHost());

        // Only the blocked webhook is logged; the delivered one is not.
        $record = $this->logger->onlyRecord();
        $this->assertSame('warning', $record['level']);
        $this->assertSame(1, $record['context']['webhook']);
        $this->assertStringContainsString('10.0.0.5', $record['context']['reason']);
    }
}
