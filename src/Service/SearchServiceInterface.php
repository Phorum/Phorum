<?php
declare(strict_types=1);

namespace Phorum\Service;

interface SearchServiceInterface
{
    /**
     * Run a search and return matching messages with pagination info.
     *
     * @param  string   $query        Search text (empty to skip text search)
     * @param  string   $author       Author name substring (empty to skip)
     * @param  string   $matchType    'ALL' | 'ANY' | 'PHRASE'
     * @param  int[]    $forumIds     Restrict to these forums (empty = all)
     * @param  int      $dateRange    Number of days back; 0 = no limit
     * @param  bool     $matchThreads Only return thread-starter messages
     * @param  int      $limit
     * @param  int      $offset
     * @return array{messages: array[], total: int}
     */
    public function search(
        string $query,
        string $author,
        string $matchType,
        array  $forumIds,
        int    $dateRange,
        bool   $matchThreads,
        int    $limit,
        int    $offset,
    ): array;
}
