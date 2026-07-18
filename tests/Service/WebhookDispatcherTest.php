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
use PHPUnit\Framework\TestCase;

class WebhookDispatcherTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $base = dirname(__DIR__, 2) . '/mods/webhooks';
        require_once $base . '/Webhook.php';
        require_once $base . '/WebhookMapper.php';
        require_once $base . '/WebhookDispatcher.php';
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);
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

        $dispatcher = new WebhookDispatcher($webhooks, $client);

        // Must not throw.
        $dispatcher->dispatch('message.created', []);
        $this->assertCount(1, $history);
    }

    public function testMalformedCustomTemplateIsCaughtAndDoesNotThrow(): void
    {
        $history = [];
        $client = $this->makeClient([], $history);

        $webhook  = $this->makeWebhook(['payload_template' => '{{ this is not valid twig %}']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('findActiveForEvent')->willReturn([$webhook]);

        $dispatcher = new WebhookDispatcher($webhooks, $client);

        // Must not throw, and must not attempt delivery with a broken body.
        $dispatcher->dispatch('message.created', []);
        $this->assertCount(0, $history);
    }
}
