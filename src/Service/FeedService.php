<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Core\Config;
use Phorum\Core\Url;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\ForumMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Model\MessageMeta;

/**
 * Builds RSS 2.0, Atom 1.0, and JSON Feed 1.1 output for the site-wide,
 * forum, and thread feed scopes. Mirrors SchemaOrgService's shape:
 * stateless, constructed with just an optional Config, one public method
 * per feed scope, private helpers for URL/timestamp/body formatting.
 * Produces the response body directly (XML string or JSON string) rather
 * than through Twig, since Twig's autoescape strategy for .xml.twig
 * templates isn't compatible with the CDATA-wrapped message bodies the
 * RSS/Atom formats need.
 */
class FeedService
{
    public function __construct(private readonly ?Config $config = null)
    {
    }

    /** @param Message[] $messages as returned by MessageMapper::findRecentInForums() */
    public function siteWide(array $messages, ForumMapper $forums, string $format, string $siteName): string
    {
        $feedUrl        = $this->absoluteUrl('/feed.' . $format);
        $items          = [];
        $forumNameCache = [];

        foreach ($messages as $msg) {
            $forumNameCache[$msg->forum_id] ??= ($forums->load($msg->forum_id)?->name ?? '');
            $items[] = $this->buildItem(
                title:      $msg->subject,
                link:       $this->absoluteUrl(Url::thread($msg->forum_id, $msg->thread, $msg->message_id)),
                body:       $msg->body,
                meta:       $msg->meta,
                authorName: $msg->author,
                category:   $forumNameCache[$msg->forum_id],
                published:  $msg->datestamp,
                updated:    $msg->modifystamp ?: $msg->datestamp,
            );
        }

        return $this->render($format, $siteName, $feedUrl, $this->absoluteUrl('/'), $items);
    }

    /** @param Message[] $threads as returned by MessageMapper::findThreadsInForum() */
    public function forumThreads(Forum $forum, array $threads, string $format, string $siteName): string
    {
        $feedUrl  = $this->absoluteUrl(Url::forum($forum->forum_id) . '/feed.' . $format);
        $forumUrl = $this->absoluteUrl(Url::forum($forum->forum_id));
        $title    = $siteName . ' — ' . $forum->name;

        $items = [];
        foreach ($threads as $thread) {
            $replySuffix = $thread->thread_count > 1 ? ' (' . ($thread->thread_count - 1) . ' replies)' : '';
            $items[] = $this->buildItem(
                title:      $thread->subject . $replySuffix,
                link:       $this->absoluteUrl(Url::thread($forum->forum_id, $thread->message_id)),
                body:       $thread->body,
                meta:       $thread->meta,
                authorName: $thread->author,
                category:   $forum->name,
                published:  $thread->datestamp,
                updated:    $thread->modifystamp ?: $thread->datestamp,
            );
        }

        return $this->render($format, $title, $feedUrl, $forumUrl, $items);
    }

    /** @param Message[] $threadMessages as returned by MessageMapper::findByThread() */
    public function threadReplies(Forum $forum, Message $root, array $threadMessages, string $format, string $siteName): string
    {
        $threadUrl = $this->absoluteUrl(Url::thread($forum->forum_id, $root->message_id));
        $feedUrl   = $threadUrl . '/feed.' . $format;
        $title     = $siteName . ' — ' . $root->subject;

        $items = [];
        foreach ($threadMessages as $msg) {
            $items[] = $this->buildItem(
                title:      $root->subject,
                link:       $this->absoluteUrl(Url::thread($forum->forum_id, $root->message_id, $msg->message_id)),
                body:       $msg->body,
                meta:       $msg->meta,
                authorName: $msg->author,
                category:   $forum->name,
                published:  $msg->datestamp,
                updated:    $msg->modifystamp ?: $msg->datestamp,
            );
        }

        return $this->render($format, $title, $feedUrl, $threadUrl, $items);
    }

    /** @param array<int, array{title:string,link:string,body_html:string,author:string,category:string,published:int,updated:int}> $items */
    private function render(string $format, string $title, string $feedUrl, string $siteUrl, array $items): string
    {
        return match ($format) {
            'atom' => $this->renderAtom($title, $feedUrl, $siteUrl, $items),
            'json' => $this->renderJsonFeed($title, $feedUrl, $siteUrl, $items),
            default => $this->renderRss($title, $feedUrl, $siteUrl, $items),
        };
    }

    // -------------------------------------------------------------------------
    // Item assembly
    // -------------------------------------------------------------------------

    /** @return array{title:string,link:string,body_html:string,author:string,category:string,published:int,updated:int} */
    private function buildItem(
        string $title,
        string $link,
        string $body,
        ?string $meta,
        string $authorName,
        string $category,
        int $published,
        int $updated,
    ): array {
        return [
            'title'     => $title,
            'link'      => $link,
            'body_html' => $this->renderBody($body, $meta),
            'author'    => $authorName,
            'category'  => $category,
            'published' => $published,
            'updated'   => $updated,
        ];
    }

    /**
     * Render a message body through the same 'format' hook pipeline used by
     * PhorumExtension::formatBody() / SchemaOrgService::plainText() — reuses
     * the Markdown/BBCode -> HTML rendering rather than re-implementing it.
     */
    private function renderBody(string $body, ?string $meta): string
    {
        $format     = MessageMeta::decode($meta)->format();
        $dispatcher = HookDispatcher::getInstance();
        $result     = $dispatcher->dispatch('format', $body, $format);

        return $dispatcher->lastDispatchWasClaimed()
            ? (string) $result
            : nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    // -------------------------------------------------------------------------
    // RSS 2.0 rendering
    // -------------------------------------------------------------------------

    /** @param array<int, array{title:string,link:string,body_html:string,author:string,category:string,published:int,updated:int}> $items */
    private function renderRss(string $title, string $feedUrl, string $siteUrl, array $items): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "<channel>\n";
        $xml .= '<title>' . $this->esc($title) . '</title>' . "\n";
        $xml .= '<link>' . $this->esc($siteUrl) . '</link>' . "\n";
        $xml .= '<atom:link href="' . $this->esc($feedUrl) . '" rel="self" type="application/rss+xml" />' . "\n";
        $xml .= '<description>' . $this->esc($title) . '</description>' . "\n";

        foreach ($items as $item) {
            $xml .= "<item>\n";
            $xml .= '<title>' . $this->esc($item['title']) . '</title>' . "\n";
            $xml .= '<link>' . $this->esc($item['link']) . '</link>' . "\n";
            $xml .= '<guid isPermaLink="true">' . $this->esc($item['link']) . '</guid>' . "\n";
            $xml .= '<pubDate>' . date(DATE_RSS, $item['published']) . '</pubDate>' . "\n";
            $xml .= '<dc:creator>' . $this->esc($item['author']) . '</dc:creator>' . "\n";
            $xml .= '<category>' . $this->esc($item['category']) . '</category>' . "\n";
            $xml .= '<description>' . $this->cdata($item['body_html']) . '</description>' . "\n";
            $xml .= "</item>\n";
        }

        $xml .= "</channel>\n</rss>";
        return $xml;
    }

    // -------------------------------------------------------------------------
    // Atom 1.0 rendering
    // -------------------------------------------------------------------------

    /** @param array<int, array{title:string,link:string,body_html:string,author:string,category:string,published:int,updated:int}> $items */
    private function renderAtom(string $title, string $feedUrl, string $siteUrl, array $items): string
    {
        $updated = !empty($items) ? max(array_column($items, 'updated')) : time();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '<title>' . $this->esc($title) . '</title>' . "\n";
        $xml .= '<link href="' . $this->esc($feedUrl) . '" rel="self" type="application/atom+xml" />' . "\n";
        $xml .= '<link href="' . $this->esc($siteUrl) . '" rel="alternate" type="text/html" />' . "\n";
        $xml .= '<id>' . $this->esc($feedUrl) . '</id>' . "\n";
        $xml .= '<updated>' . date(DATE_ATOM, $updated) . '</updated>' . "\n";

        foreach ($items as $item) {
            $xml .= "<entry>\n";
            $xml .= '<title type="html">' . $this->esc($item['title']) . '</title>' . "\n";
            $xml .= '<link href="' . $this->esc($item['link']) . '" />' . "\n";
            $xml .= '<id>' . $this->esc($item['link']) . '</id>' . "\n";
            $xml .= '<published>' . date(DATE_ATOM, $item['published']) . '</published>' . "\n";
            $xml .= '<updated>' . date(DATE_ATOM, $item['updated']) . '</updated>' . "\n";
            $xml .= '<author><name>' . $this->esc($item['author']) . '</name></author>' . "\n";
            $xml .= '<category term="' . $this->escAttr($item['category']) . '" />' . "\n";
            $xml .= '<summary type="html">' . $this->cdata($item['body_html']) . '</summary>' . "\n";
            $xml .= "</entry>\n";
        }

        $xml .= '</feed>';
        return $xml;
    }

    // -------------------------------------------------------------------------
    // JSON Feed 1.1 rendering
    // -------------------------------------------------------------------------

    /**
     * No CDATA/entity-escaping workaround needed here (unlike RSS/Atom) —
     * json_encode() already escapes HTML content safely as a plain JSON
     * string value.
     *
     * @param array<int, array{title:string,link:string,body_html:string,author:string,category:string,published:int,updated:int}> $items
     */
    private function renderJsonFeed(string $title, string $feedUrl, string $siteUrl, array $items): string
    {
        $feed = [
            'version'       => 'https://jsonfeed.org/version/1.1',
            'title'         => $title,
            'home_page_url' => $siteUrl,
            'feed_url'      => $feedUrl,
            'items'         => [],
        ];

        foreach ($items as $item) {
            $entry = [
                'id'             => $item['link'],
                'url'            => $item['link'],
                'title'          => $item['title'],
                'content_html'   => $item['body_html'],
                'date_published' => date(DATE_ATOM, $item['published']),
                'date_modified'  => date(DATE_ATOM, $item['updated']),
                'authors'        => [['name' => $item['author']]],
            ];
            if ($item['category'] !== '') {
                $entry['tags'] = [$item['category']];
            }
            $feed['items'][] = $entry;
        }

        return json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function absoluteUrl(string $path): string
    {
        $base = rtrim((string) ($this->config?->get('base_url', '') ?? ''), '/');
        return $base . $path;
    }

    /** Escape a value for use as XML element text content. */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Escape a value for use inside a double-quoted XML attribute. */
    private function escAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * Wrap already-rendered HTML in a CDATA section so RSS/Atom readers get
     * the literal markup without double-escaping. Guards against the body
     * itself containing the "]]>" terminator (rare, but would truncate the
     * XML if unguarded) by splitting it into adjacent CDATA blocks.
     */
    private function cdata(string $html): string
    {
        $safe = str_replace(']]>', ']]]]><![CDATA[>', $html);
        return '<![CDATA[' . $safe . ']]>';
    }
}
