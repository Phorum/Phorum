<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\SettingMapper;

/**
 * Minimum-time-between-posts flood control. Interval is admin-configurable
 * via the 'flood_interval' setting (seconds; 0 or unset disables the check).
 */
class FloodControlService
{
    public function __construct(
        private readonly MessageMapper $messages,
        private readonly SettingMapper $settings,
    ) {}

    /** Seconds the user must still wait before posting again; 0 if clear to post. */
    public function secondsRemaining(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $interval = (int) ($this->settings->getSetting('flood_interval') ?? 0);
        if ($interval <= 0) {
            return 0;
        }

        $last = $this->messages->findLastByUser($userId);
        if ($last === null) {
            return 0;
        }

        return max(0, $interval - (time() - $last->datestamp));
    }
}
