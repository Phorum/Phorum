<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks;

use Phorum\Mapper\AbstractPhorumMapper;

class WebhookMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Webhook::class;
    public const PRIMARY_KEY  = 'id';
    public const TABLE_BASE   = 'webhooks';

    public const MAPPING = [
        'id'               => ['read_only' => true],
        'url'              => [],
        'secret'           => [],
        'events'           => [],
        'active'           => [],
        'payload_template' => [],
        'content_type'     => [],
        'created_at'       => [],
    ];

    /** Every active webhook subscribed to the given event name. */
    public function findActiveForEvent(string $event): array
    {
        $rows = $this->find(['active' => 1]) ?? [];
        return array_values(array_filter(
            $rows,
            fn(Webhook $w) => in_array($event, self::decodeEvents($w->events), true)
        ));
    }

    /** @return string[] */
    public static function decodeEvents(string $events): array
    {
        $decoded = json_decode($events, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @param string[] $events */
    public static function encodeEvents(array $events): string
    {
        return json_encode(array_values($events));
    }
}
