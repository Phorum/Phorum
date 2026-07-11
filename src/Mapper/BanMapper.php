<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\Ban;

class BanMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Ban::class;
    public const PRIMARY_KEY  = 'id';
    public const TABLE_BASE   = 'banlists';

    public const MAPPING = [
        'id'       => ['read_only' => true],
        'forum_id' => [],
        'type'     => [],
        'pcre'     => [],
        'string'   => [],
        'comments' => [],
    ];

    /**
     * Return all ban entries of a given type that apply to a forum.
     * Always includes global bans (forum_id = 0) plus forum-specific ones.
     *
     * @return array<int, array{pcre: int, string: string}>
     */
    public function getBans(int $type, int $forumId): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT pcre, string FROM ' . $this->table()
            . ' WHERE type = :type'
            . '   AND (forum_id = 0 OR forum_id = :forum_id)'
            . '   AND string != \'\'',
            [':type' => $type, ':forum_id' => $forumId]
        );
        return $rows ?: [];
    }
}
