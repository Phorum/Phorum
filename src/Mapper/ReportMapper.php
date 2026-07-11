<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\Report;

class ReportMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Report::class;
    public const PRIMARY_KEY  = 'report_id';
    public const TABLE_BASE   = 'reports';

    public const STATUS_OPEN      = 0;
    public const STATUS_RESOLVED  = 1;
    public const STATUS_DISMISSED = 2;

    public const MAPPING = [
        'report_id'        => ['read_only' => true],
        'message_id'       => [],
        'forum_id'         => [],
        'reporter_user_id' => [],
        'reason'           => [],
        'status'           => [],
        'created'          => [],
        'resolved_user_id' => [],
        'resolved_time'    => [],
    ];

    /** File a new report against a message. */
    public function create(int $messageId, int $forumId, int $reporterUserId, string $reason): void
    {
        $report                   = new Report();
        $report->message_id       = $messageId;
        $report->forum_id         = $forumId;
        $report->reporter_user_id = $reporterUserId;
        $report->reason           = $reason;
        $report->status           = self::STATUS_OPEN;
        $report->created          = time();
        $this->save($report);
    }

    /**
     * Return open reports across a set of forums, oldest first.
     *
     * @param int[] $forumIds
     */
    public function findOpenInForums(array $forumIds): ?array
    {
        if (empty($forumIds)) {
            return null;
        }

        $params = [':status' => self::STATUS_OPEN];
        $ids    = [];
        foreach (array_values($forumIds) as $i => $forumId) {
            $key         = ":fid{$i}";
            $params[$key] = $forumId;
            $ids[]        = $key;
        }

        $sql = 'SELECT * FROM ' . $this->table()
             . ' WHERE status = :status'
             . '   AND forum_id IN (' . implode(', ', $ids) . ')'
             . ' ORDER BY created ASC';

        $rows = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /** Mark a report resolved or dismissed. */
    public function resolve(int $reportId, int $resolvedUserId, int $status): void
    {
        $report = $this->load($reportId);
        if ($report === null) {
            return;
        }
        $report->status        = $status;
        $report->resolved_user_id = $resolvedUserId;
        $report->resolved_time = time();
        $this->save($report);
    }
}
