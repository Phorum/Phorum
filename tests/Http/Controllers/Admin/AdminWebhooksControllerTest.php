<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Request;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mod\Webhooks\Admin\WebhooksController;
use Phorum\Mod\Webhooks\Webhook;
use Phorum\Mod\Webhooks\WebhookMapper;
use Phorum\Tests\Http\ControllerTestCase;

class AdminWebhooksControllerTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        $base = dirname(__DIR__, 4) . '/mods/webhooks';
        require_once $base . '/Webhook.php';
        require_once $base . '/WebhookMapper.php';
        require_once $base . '/WebhookDispatcher.php';
        require_once $base . '/Admin/WebhooksController.php';
    }

    private function makeController(array $deps = []): WebhooksController
    {
        return new WebhooksController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            webhooks: $deps['webhooks'] ?? $this->createMock(WebhookMapper::class),
            modLog:   $deps['modLog']   ?? $this->createMock(ModLogMapper::class),
        );
    }

    private function makeWebhook(int $id = 1, array $override = []): Webhook
    {
        $w         = new Webhook();
        $w->id     = $id;
        $w->url    = 'https://example.test/hook';
        $w->secret = 'sekrit';
        $w->events = WebhookMapper::encodeEvents(['message.created']);
        foreach ($override as $k => $v) {
            $w->$k = $v;
        }
        return $w;
    }

    // -------------------------------------------------------------------------
    // Auth guards
    // -------------------------------------------------------------------------

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testCreateRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->create(new Request());
        $this->assertSame(302, $response->status);
    }

    public function testEditRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->edit(new Request(tokens: ['webhook_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testDeleteRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->delete(new Request(tokens: ['webhook_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function testIndexReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('find')->willReturn([$this->makeWebhook()]);

        $ctrl     = $this->makeController(['webhooks' => $webhooks]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function testCreateGetReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->create($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostValidationErrorForInvalidUrl(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->create($this->makePostRequest(['url' => 'not-a-url']));
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostValidationErrorForNonHttpScheme(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->create($this->makePostRequest(['url' => 'ftp://example.com/hook']));
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostSuccessRedirectsAndLogsAction(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->expects($this->once())->method('save');
        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'create', 'webhook', 0, 0, 'https://example.test/hook');

        $ctrl     = $this->makeController(['webhooks' => $webhooks, 'modLog' => $modLog]);
        $response = $ctrl->create($this->makePostRequest([
            'url'    => 'https://example.test/hook',
            'events' => ['message.created', 'user.registered'],
        ]));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/webhooks', $response->headers['Location']);
    }

    public function testCreatePostGeneratesASecret(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved = null;
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('save')->willReturnCallback(function ($w) use (&$saved) {
            $saved = $w;
            return $w;
        });

        $ctrl = $this->makeController(['webhooks' => $webhooks]);
        $ctrl->create($this->makePostRequest(['url' => 'https://example.test/hook']));

        $this->assertNotEmpty($saved->secret);
    }

    public function testCreatePostIgnoresUnknownEventNames(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved = null;
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('save')->willReturnCallback(function ($w) use (&$saved) {
            $saved = $w;
            return $w;
        });

        $ctrl = $this->makeController(['webhooks' => $webhooks]);
        $ctrl->create($this->makePostRequest([
            'url'    => 'https://example.test/hook',
            'events' => ['message.created', 'not.a.real.event'],
        ]));

        $this->assertSame(['message.created'], WebhookMapper::decodeEvents($saved->events));
    }

    // -------------------------------------------------------------------------
    // edit
    // -------------------------------------------------------------------------

    public function testEditReturns404WhenWebhookNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['webhooks' => $webhooks]);
        $response = $ctrl->edit(new Request(tokens: ['webhook_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testEditGetReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($this->makeWebhook());

        $ctrl     = $this->makeController(['webhooks' => $webhooks]);
        $response = $ctrl->edit($this->makeGetRequest(tokens: ['webhook_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostSuccessRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($this->makeWebhook());
        $webhooks->expects($this->once())->method('save');
        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'update', 'webhook', 1, 0, 'https://example.test/hook');

        $ctrl     = $this->makeController(['webhooks' => $webhooks, 'modLog' => $modLog]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['url' => 'https://example.test/hook', 'events' => ['message.created']],
            tokens: ['webhook_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/webhooks', $response->headers['Location']);
    }

    public function testEditPostKeepsExistingSecretByDefault(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $existing = $this->makeWebhook(1, ['secret' => 'original-secret']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($existing);

        $saved = null;
        $webhooks->method('save')->willReturnCallback(function ($w) use (&$saved) {
            $saved = $w;
            return $w;
        });

        $ctrl = $this->makeController(['webhooks' => $webhooks]);
        $ctrl->edit($this->makePostRequest(
            post:   ['url' => 'https://example.test/hook'],
            tokens: ['webhook_id' => '1'],
        ));

        $this->assertSame('original-secret', $saved->secret);
    }

    public function testEditPostRegeneratesSecretWhenRequested(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $existing = $this->makeWebhook(1, ['secret' => 'original-secret']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($existing);

        $saved = null;
        $webhooks->method('save')->willReturnCallback(function ($w) use (&$saved) {
            $saved = $w;
            return $w;
        });

        $ctrl = $this->makeController(['webhooks' => $webhooks]);
        $ctrl->edit($this->makePostRequest(
            post:   ['url' => 'https://example.test/hook', 'regenerate_secret' => '1'],
            tokens: ['webhook_id' => '1'],
        ));

        $this->assertNotSame('original-secret', $saved->secret);
        $this->assertNotEmpty($saved->secret);
    }

    public function testEditPostSavesCustomPayloadTemplate(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($this->makeWebhook());

        $saved = null;
        $webhooks->method('save')->willReturnCallback(function ($w) use (&$saved) {
            $saved = $w;
            return $w;
        });

        $ctrl = $this->makeController(['webhooks' => $webhooks]);
        $ctrl->edit($this->makePostRequest(
            post:   [
                'url'              => 'https://example.test/hook',
                'payload_template' => '{"text": "{{ data.subject }}"}',
            ],
            tokens: ['webhook_id' => '1'],
        ));

        $this->assertSame('{"text": "{{ data.subject }}"}', $saved->payload_template);
    }

    public function testEditPostBlankPayloadTemplateClearsIt(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $existing = $this->makeWebhook(1, ['payload_template' => '{"old": true}']);
        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($existing);

        $saved = null;
        $webhooks->method('save')->willReturnCallback(function ($w) use (&$saved) {
            $saved = $w;
            return $w;
        });

        $ctrl = $this->makeController(['webhooks' => $webhooks]);
        $ctrl->edit($this->makePostRequest(
            post:   ['url' => 'https://example.test/hook', 'payload_template' => '   '],
            tokens: ['webhook_id' => '1'],
        ));

        $this->assertNull($saved->payload_template);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function testDeleteReturns404WhenWebhookNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['webhooks' => $webhooks]);
        $response = $ctrl->delete(new Request(tokens: ['webhook_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testDeleteGetReturnsConfirmForm(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($this->makeWebhook());

        $ctrl     = $this->makeController(['webhooks' => $webhooks]);
        $response = $ctrl->delete($this->makeGetRequest(tokens: ['webhook_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testDeletePostDeletesAndRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $webhooks = $this->createMock(WebhookMapper::class);
        $webhooks->method('load')->willReturn($this->makeWebhook());
        $webhooks->expects($this->once())->method('delete')->with(1);
        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'delete', 'webhook', 1, 0, 'https://example.test/hook');

        $ctrl     = $this->makeController(['webhooks' => $webhooks, 'modLog' => $modLog]);
        $response = $ctrl->delete($this->makePostRequest(tokens: ['webhook_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/webhooks', $response->headers['Location']);
    }
}
