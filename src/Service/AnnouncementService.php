<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\SettingMapper;

class AnnouncementService
{
    private readonly SettingMapper  $settings;
    private readonly MessageMapper  $messages;
    private readonly NewflagService $newflags;

    public function __construct(
        ?SettingMapper  $settings = null,
        ?MessageMapper  $messages = null,
        ?NewflagService $newflags = null,
    ) {
        $this->settings = $settings ?? new SettingMapper();
        $this->messages = $messages ?? new MessageMapper();
        $this->newflags = $newflags ?? new NewflagService(new NewflagMapper());
    }

    /**
     * Return the announcement threads to display on the given page for the
     * given user, or null if announcements are disabled or there's nothing
     * to show.
     *
     * @return array{threads: array, new_counts: array<int,int>}|null
     */
    public function getAnnouncementsFor(string $page, int $userId): ?array
    {
        $settings = $this->settings->getSetting('announcements');
        $settings = is_array($settings) ? $settings : [];

        $forumId = (int) ($settings['forum_id'] ?? 0);
        if ($forumId === 0 || empty($settings['pages'][$page])) {
            return null;
        }

        $limit   = (int) ($settings['number_to_show'] ?? 5);
        $days    = (int) ($settings['days_to_show'] ?? 0);
        $unread  = !empty($settings['only_show_unread']);

        $threads = $this->messages->findThreadsInForum($forumId, $limit, 0) ?? [];

        if ($days > 0) {
            $cutoff  = time() - ($days * 86400);
            $threads = array_values(array_filter($threads, fn($t) => $t->datestamp >= $cutoff));
        }

        $newCounts = $userId > 0 ? $this->newflags->getNewCountsForThreads($userId, $forumId) : [];

        if ($unread) {
            $threads = array_values(array_filter($threads, fn($t) => !empty($newCounts[$t->message_id])));
        }

        if (empty($threads)) {
            return null;
        }

        return ['threads' => $threads, 'new_counts' => $newCounts];
    }
}
