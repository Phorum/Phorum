<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mod\Webhooks\Webhook;
use Phorum\Mod\Webhooks\WebhookMapper;

class WebhookMapperTest extends MapperTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $base = dirname(__DIR__, 2) . '/mods/webhooks';
        require_once $base . '/Webhook.php';
        require_once $base . '/WebhookMapper.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::$pdo->exec('DELETE FROM phorum_webhooks');
        self::$pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'phorum_webhooks'");
    }

    private function makeMapper(): WebhookMapper
    {
        return new class extends WebhookMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedWebhook(array $override = []): int
    {
        return $this->insert('phorum_webhooks', array_merge([
            'url'              => 'https://example.com/hook',
            'secret'           => 'abc123',
            'events'           => '["message.created"]',
            'active'           => 1,
            'payload_template' => null,
            'content_type'     => 'application/json',
            'created_at'       => 1000,
        ], $override));
    }

    // -------------------------------------------------------------------------
    // Basic CRUD
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $w = new Webhook();
        $w->url    = 'https://example.com/a';
        $w->secret = 'sekrit';
        $w->events = WebhookMapper::encodeEvents(['message.created', 'user.registered']);
        $mapper->save($w);

        $this->assertGreaterThan(0, $w->id);
        $loaded = $mapper->load($w->id);
        $this->assertSame('https://example.com/a', $loaded->url);
        $this->assertSame(['message.created', 'user.registered'], WebhookMapper::decodeEvents($loaded->events));
    }

    public function testSaveUpdate(): void
    {
        $id = $this->seedWebhook(['url' => 'https://old.example.com']);
        $mapper = $this->makeMapper();
        $w = $mapper->load($id);
        $w->url = 'https://new.example.com';
        $mapper->save($w);
        $this->assertSame('https://new.example.com', $mapper->load($id)->url);
    }

    public function testDelete(): void
    {
        $id = $this->seedWebhook();
        $mapper = $this->makeMapper();
        $mapper->delete($id);
        $this->assertNull($mapper->load($id));
    }

    // -------------------------------------------------------------------------
    // findActiveForEvent
    // -------------------------------------------------------------------------

    public function testFindActiveForEventReturnsSubscribedActiveWebhooks(): void
    {
        $this->seedWebhook(['events' => '["message.created"]', 'active' => 1]);
        $this->seedWebhook(['events' => '["user.registered"]', 'active' => 1]);

        $mapper = $this->makeMapper();
        $found  = $mapper->findActiveForEvent('message.created');

        $this->assertCount(1, $found);
        $this->assertSame(['message.created'], WebhookMapper::decodeEvents($found[0]->events));
    }

    public function testFindActiveForEventExcludesInactiveWebhooks(): void
    {
        $this->seedWebhook(['events' => '["message.created"]', 'active' => 0]);

        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->findActiveForEvent('message.created'));
    }

    public function testFindActiveForEventMatchesWebhookSubscribedToMultipleEvents(): void
    {
        $this->seedWebhook(['events' => '["message.created","user.registered"]', 'active' => 1]);

        $mapper = $this->makeMapper();
        $this->assertCount(1, $mapper->findActiveForEvent('message.created'));
        $this->assertCount(1, $mapper->findActiveForEvent('user.registered'));
        $this->assertCount(0, $mapper->findActiveForEvent('user.banned'));
    }

    // -------------------------------------------------------------------------
    // decodeEvents / encodeEvents
    // -------------------------------------------------------------------------

    public function testDecodeEventsReturnsEmptyArrayForInvalidJson(): void
    {
        $this->assertSame([], WebhookMapper::decodeEvents('not json'));
    }

    public function testDecodeEventsReturnsEmptyArrayForNonArrayJson(): void
    {
        $this->assertSame([], WebhookMapper::decodeEvents('"a string"'));
    }

    public function testEncodeEventsRoundTrips(): void
    {
        $events  = ['message.created', 'user.banned'];
        $encoded = WebhookMapper::encodeEvents($events);
        $this->assertSame($events, WebhookMapper::decodeEvents($encoded));
    }
}
