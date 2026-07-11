<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\PmMessage;

class PmMessageMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = PmMessage::class;
    public const PRIMARY_KEY  = 'pm_message_id';
    public const TABLE_BASE   = 'pm_messages';

    public const MAPPING = [
        'pm_message_id' => ['read_only' => true],
        'user_id'       => [],
        'author'        => [],
        'subject'       => [],
        'message'       => [],
        'datestamp'     => [],
        'meta'          => [],
    ];
}
