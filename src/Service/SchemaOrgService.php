<?php
declare(strict_types=1);

namespace Phorum\Service;

use DealNews\SchemaOrg\Type\BreadcrumbList;
use DealNews\SchemaOrg\Type\Comment;
use DealNews\SchemaOrg\Type\CollectionPage;
use DealNews\SchemaOrg\Type\DiscussionForumPosting;
use DealNews\SchemaOrg\Type\InteractionCounter;
use DealNews\SchemaOrg\Type\ItemList;
use DealNews\SchemaOrg\Type\ListItem;
use DealNews\SchemaOrg\Type\Person;
use DealNews\SchemaOrg\Type\ViewAction;
use DealNews\SchemaOrg\Type\WebPage;
use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Model\MessageMeta;

/**
 * Builds schema.org JSON-LD graphs for the forum list, message list, and
 * thread read pages.
 */
class SchemaOrgService
{
    /** Maximum number of replies embedded as `comment` on a thread posting. */
    private const MAX_COMMENTS = 50;

    public function __construct(private readonly ?Config $config = null)
    {
    }

    /**
     * @param  Forum[] $forums flattened forum tree (folders included; skipped internally)
     * @return array{0: CollectionPage}
     */
    public function forumIndex(array $forums, string $siteName): array
    {
        $page       = new CollectionPage();
        $page->id   = $this->absoluteUrl('/');
        $page->url  = $page->id;
        $page->name = $siteName;

        $items    = [];
        $position = 0;
        foreach ($forums as $forum) {
            if ($forum->folder_flag) {
                continue;
            }

            $position++;

            $entry       = new WebPage();
            $entry->id   = $this->absoluteUrl('/forum/' . $forum->forum_id);
            $entry->url  = $entry->id;
            $entry->name = $forum->name;
            if ($forum->description !== '') {
                $entry->description = $forum->description;
            }

            $listItem           = new ListItem();
            $listItem->position = $position;
            $listItem->item     = $entry;
            $items[]             = $listItem;
        }

        $list                   = new ItemList();
        $list->itemListElement  = $items;
        $list->numberOfItems    = count($items);
        $page->mainEntity       = $list;

        return [$page];
    }

    /**
     * @param  Message[] $threads root messages, as returned by findThreadsInForum()
     * @return array{0: CollectionPage}
     */
    public function forumShow(Forum $forum, array $threads, string $siteName): array
    {
        $forumUrl = $this->absoluteUrl('/forum/' . $forum->forum_id);

        $page       = new CollectionPage();
        $page->id   = $forumUrl;
        $page->url  = $forumUrl;
        $page->name = $forum->name;
        if ($forum->description !== '') {
            $page->description = $forum->description;
        }
        $page->breadcrumb = $this->breadcrumb([
            [$siteName, $this->absoluteUrl('/')],
            [$forum->name, $forumUrl],
        ]);

        $items    = [];
        $position = 0;
        foreach ($threads as $thread) {
            $position++;

            $threadUrl = $this->absoluteUrl('/forum/' . $forum->forum_id . '/thread/' . $thread->message_id);

            $posting             = new DiscussionForumPosting();
            $posting->id         = $threadUrl;
            $posting->url        = $threadUrl;
            $posting->headline   = $thread->subject;
            $posting->name       = $thread->subject;
            $posting->author     = $this->authorFor($thread->user_id, $thread->author);
            $posting->datePublished = $this->iso8601($thread->datestamp);
            if ($thread->modifystamp > 0 && $thread->modifystamp !== $thread->datestamp) {
                $posting->dateModified = $this->iso8601($thread->modifystamp);
            }
            $posting->commentCount = max(0, $thread->thread_count - 1);

            $listItem           = new ListItem();
            $listItem->position = $position;
            $listItem->item     = $posting;
            $items[]             = $listItem;
        }

        $list                  = new ItemList();
        $list->itemListElement = $items;
        $list->numberOfItems   = count($items);
        $page->mainEntity      = $list;

        return [$page];
    }

    /**
     * @param  Message[] $threadMessages full flat message list for the thread
     *                                   (as loaded by findByThread(), before any
     *                                   threaded-tree reshaping)
     * @return array{0: DiscussionForumPosting, 1: BreadcrumbList}
     */
    public function thread(Forum $forum, Message $root, array $threadMessages, string $siteName): array
    {
        $threadUrl = $this->absoluteUrl('/forum/' . $forum->forum_id . '/thread/' . $root->message_id);

        $posting                  = new DiscussionForumPosting();
        $posting->id              = $threadUrl;
        $posting->url             = $threadUrl;
        $posting->mainEntityOfPage = $threadUrl;
        $posting->headline        = $root->subject;
        $posting->name            = $root->subject;
        $posting->text            = $this->plainText($root->body, $root->meta);
        $posting->author          = $this->authorFor($root->user_id, $root->author);
        $posting->datePublished   = $this->iso8601($root->datestamp);
        if ($root->modifystamp > 0 && $root->modifystamp !== $root->datestamp) {
            $posting->dateModified = $this->iso8601($root->modifystamp);
        }
        $posting->commentCount = max(0, $root->thread_count - 1);

        if ($root->viewcount > 0) {
            $views                    = new InteractionCounter();
            $views->interactionType   = new ViewAction();
            $views->userInteractionCount = $root->viewcount;
            $posting->interactionStatistic = $views;
        }

        $replies = array_values(array_filter(
            $threadMessages,
            static fn(Message $m) => $m->message_id !== $root->message_id && $m->status === 2,
        ));
        usort($replies, static fn(Message $a, Message $b) => $a->datestamp <=> $b->datestamp);

        $comments = [];
        foreach (array_slice($replies, 0, self::MAX_COMMENTS) as $msg) {
            $commentUrl = $threadUrl . '#msg-' . $msg->message_id;

            $comment           = new Comment();
            $comment->id       = $commentUrl;
            $comment->url      = $commentUrl;
            $comment->text     = $this->plainText($msg->body, $msg->meta);
            $comment->author   = $this->authorFor($msg->user_id, $msg->author);
            $comment->datePublished = $this->iso8601($msg->datestamp);
            if ($msg->modifystamp > 0 && $msg->modifystamp !== $msg->datestamp) {
                $comment->dateModified = $this->iso8601($msg->modifystamp);
            }
            $comments[] = $comment;
        }
        $posting->comment = $comments;

        $breadcrumb = $this->breadcrumb([
            [$siteName, $this->absoluteUrl('/')],
            [$forum->name, $this->absoluteUrl('/forum/' . $forum->forum_id)],
            [$root->subject, $threadUrl],
        ]);

        return [$posting, $breadcrumb];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function absoluteUrl(string $path): string
    {
        $base = rtrim((string) ($this->config?->get('base_url', '') ?? ''), '/');
        return $base . $path;
    }

    private function iso8601(int $timestamp): string
    {
        return $timestamp > 0 ? date(DATE_ATOM, $timestamp) : '';
    }

    private function authorFor(int $userId, string $authorName): Person
    {
        $person       = new Person();
        $person->name = $authorName;
        if ($userId > 0) {
            $url        = $this->absoluteUrl('/user/' . $userId);
            $person->id = $url;
            $person->url = $url;
        }
        return $person;
    }

    /**
     * Render a message body through the same 'format' hook pipeline used by
     * PhorumExtension::formatBody(), then strip it down to plain text.
     */
    private function plainText(string $body, ?string $meta): string
    {
        $format     = MessageMeta::decode($meta)->format();
        $dispatcher = HookDispatcher::getInstance();
        $result     = $dispatcher->dispatch('format', $body, $format);
        $html       = $dispatcher->lastDispatchWasClaimed() ? (string) $result : $body;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $items list of [name, url] pairs, in order
     */
    private function breadcrumb(array $items): BreadcrumbList
    {
        $elements = [];
        foreach ($items as $position => [$name, $url]) {
            $entry       = new WebPage();
            $entry->id   = $url;
            $entry->url  = $url;
            $entry->name = $name;

            $listItem           = new ListItem();
            $listItem->position = $position + 1;
            $listItem->item     = $entry;
            $elements[]          = $listItem;
        }

        $list                  = new BreadcrumbList();
        $list->itemListElement = $elements;
        return $list;
    }
}
