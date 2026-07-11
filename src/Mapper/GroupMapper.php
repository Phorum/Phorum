<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\Group;

class GroupMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Group::class;
    public const PRIMARY_KEY  = 'group_id';
    public const TABLE_BASE   = 'groups';

    public const MAPPING = [
        'group_id' => ['read_only' => true],
        'name'     => [],
        'open'     => [],
    ];
}
