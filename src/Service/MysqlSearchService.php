<?php
declare(strict_types=1);

namespace Phorum\Service;

use DealNews\DB\CRUD;

class MysqlSearchService implements SearchServiceInterface
{
    private ?CRUD $crud = null;

    public function __construct(private readonly string $dbName, private readonly string $prefix)
    {
    }

    public function search(
        string $query,
        string $author,
        string $matchType,
        array  $forumIds,
        int    $dateRange,
        bool   $matchThreads,
        int    $limit,
        int    $offset,
    ): array {
        if ($query === '' && $author === '') {
            return ['messages' => [], 'total' => 0];
        }

        $where  = ['m.status = 2'];
        $params = [];

        // FULLTEXT filter
        $matchStr = $query !== '' ? $this->buildMatchString($query, $matchType) : '';
        if ($matchStr !== '') {
            $where[]            = 'MATCH(s.search_text) AGAINST (:match IN BOOLEAN MODE)';
            $params[':match']   = $matchStr;
        }

        // Author filter
        if ($author !== '') {
            $where[]            = 'm.author LIKE :author';
            $escaped            = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $author);
            $params[':author']  = '%' . $escaped . '%';
        }

        // Forum filter
        if (!empty($forumIds)) {
            $placeholders = [];
            foreach ($forumIds as $i => $fid) {
                $key                = ':fid' . $i;
                $placeholders[]     = $key;
                $params[$key]       = $fid;
            }
            $where[] = 's.forum_id IN (' . implode(', ', $placeholders) . ')';
        }

        // Date range filter
        if ($dateRange > 0) {
            $since              = time() - ($dateRange * 86400);
            $where[]            = 'm.datestamp >= :since';
            $params[':since']   = $since;
        }

        // Thread starters only
        if ($matchThreads) {
            $where[] = 'm.parent_id = 0';
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);
        $joins       = ' INNER JOIN ' . $this->prefix . '_search s ON s.message_id = m.message_id'
                     . ' INNER JOIN ' . $this->prefix . '_forums f ON f.forum_id = m.forum_id';
        $from        = ' FROM ' . $this->prefix . '_messages m' . $joins;

        $total = (int) ($this->crud()->runFetch(
            'SELECT COUNT(*) AS cnt' . $from . $whereClause,
            $params
        )[0]['cnt'] ?? 0);

        if ($total === 0) {
            return ['messages' => [], 'total' => 0];
        }

        $rows = $this->crud()->runFetch(
            'SELECT m.*, f.name AS forum_name' . $from . $whereClause
            . ' ORDER BY m.datestamp DESC'
            . " LIMIT {$limit} OFFSET {$offset}",
            $params
        ) ?: [];

        return ['messages' => $rows, 'total' => $total];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildMatchString(string $query, string $matchType): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        // Strip chars that have special meaning in FULLTEXT BOOLEAN MODE
        $clean = preg_replace('/[+\-<>~*"()@]/', ' ', $query) ?? $query;
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean));

        return match ($matchType) {
            'PHRASE' => '"' . $clean . '"',
            'ALL'    => implode(' ', array_map(
                fn(string $w) => '+' . $w,
                array_filter(explode(' ', $clean))
            )),
            default  => $clean, // ANY — words without operators; FULLTEXT ranks by relevance
        };
    }

    private function crud(): CRUD
    {
        if ($this->crud === null) {
            $this->crud = CRUD::factory($this->dbName);
        }
        return $this->crud;
    }
}
