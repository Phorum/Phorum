<?php
declare(strict_types=1);

namespace Phorum\Twig;

use DealNews\SchemaOrg\JsonLdNode;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\MarkdownConverter;
use Phorum\Core\Config;
use Phorum\Core\CsrfGuard;
use Phorum\Core\Lang;
use Phorum\Hook\HookDispatcher;
use Phorum\Model\FileMeta;
use Phorum\Model\MessageMeta;
use Phorum\Service\Autolinker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class PhorumExtension extends AbstractExtension
{
    private MarkdownConverter $markdown;
    private ?Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config;

        $internalHosts = [];
        $host          = parse_url((string) ($config?->get('base_url', '') ?? ''), PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $internalHosts[] = $host;
        }

        $environment = new Environment([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
            'external_link'      => [
                'internal_hosts'     => $internalHosts,
                'open_in_new_window' => true,
                'nofollow'           => 'external',
                'noopener'           => 'external',
                'noreferrer'         => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new ExternalLinkExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->markdown = new MarkdownConverter($environment);

        // Register the core Markdown handler for the 'format' hook
        HookDispatcher::getInstance()->register(
            hook:     'format',
            callback: function (string $body, string $format): ?string {
                if ($format !== 'markdown') {
                    return null;
                }
                return (string) $this->markdown->convert($body);
            },
            priority: 10,
        );
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format',      [$this, 'formatBody'],    ['is_safe' => ['html']]),
            new TwigFilter('markdown',    [$this, 'renderMarkdown'], ['is_safe' => ['html']]),
            new TwigFilter('datestamp',   [$this, 'formatDatestamp']),
            new TwigFilter('relative_time', [$this, 'relativeTime']),
            new TwigFilter('decode_meta',     static fn($raw) => MessageMeta::decode(is_string($raw) ? $raw : null)),
            new TwigFilter('decode_file_meta', static fn($raw) => FileMeta::decode(is_string($raw) ? $raw : null)),
            new TwigFilter('filesizeformat',  static fn($bytes) => self::formatFilesize((int) $bytes)),
            new TwigFilter('url_encode',      static fn($s) => rawurlencode((string) $s)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pagination_url', [$this, 'paginationUrl']),
            new TwigFunction('pagination_range', [$this, 'paginationRange']),
            new TwigFunction('absolute_url', [$this, 'absoluteUrl']),
            new TwigFunction('csrf_field', [CsrfGuard::class, 'field'], ['is_safe' => ['html']]),
            new TwigFunction('trans', [Lang::class, 'get']),
            new TwigFunction('path', [$this, 'path']),
            new TwigFunction('hook', static function (string $name, mixed $data = ''): string {
                $result = phorum_api_hook($name, $data);
                return is_string($result) ? $result : '';
            }, ['is_safe' => ['html']]),
            new TwigFunction('json_ld', [$this, 'jsonLd'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Render a list of schema.org nodes as `<script type="application/ld+json">` tags.
     *
     * @param JsonLdNode[] $nodes
     */
    public function jsonLd(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            if ($node instanceof JsonLdNode) {
                $html .= $node->toJsonLdScriptTag();
            }
        }
        return $html;
    }

    /**
     * Dispatch through the 'format' hook to render a message body.
     * The format is read from the meta blob via MessageMeta::decode();
     * defaults to 'bbcode' for legacy Phorum 6.x messages with no meta.
     *
     * @param mixed $meta  The raw value of Message::$meta (?string)
     */
    public function formatBody(string $body, mixed $meta = null): string
    {
        $format     = MessageMeta::decode(is_string($meta) ? $meta : null)->format();
        $dispatcher = HookDispatcher::getInstance();
        $result     = $dispatcher->dispatch('format', $body, $format);

        // No hook claimed the format — fall back to HTML-escaped plain text
        if (!$dispatcher->lastDispatchWasClaimed()) {
            $escaped   = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $autolink  = new Autolinker();
            $escaped   = $autolink->linkifyEmails(
                $escaped,
                static fn(string $email): string => '<a href="mailto:' . $email . '" rel="nofollow">' . $email . '</a>'
            );
            $escaped   = $autolink->linkifyUrls(
                $escaped,
                static fn(string $href, string $label): string => '<a href="' . $href . '" rel="nofollow">' . $label . '</a>'
            );
            $html = nl2br($escaped);
        } else {
            $html = (string) $result;
        }

        $fixup = phorum_api_hook('format_fixup', $html);
        return is_string($fixup) ? $fixup : $html;
    }

    public function renderMarkdown(string $text): string
    {
        return (string) $this->markdown->convert($text);
    }

    public function formatDatestamp(int $timestamp, string $format = 'M j, Y g:i a'): string
    {
        if ($timestamp === 0) {
            return '&mdash;';
        }
        return date($format, $timestamp);
    }

    public function relativeTime(int $timestamp): string
    {
        if ($timestamp === 0) {
            return 'never';
        }
        $diff = time() - $timestamp;

        return match (true) {
            $diff < 60      => 'just now',
            $diff < 3600    => floor($diff / 60) . 'm ago',
            $diff < 86400   => floor($diff / 3600) . 'h ago',
            $diff < 604800  => floor($diff / 86400) . 'd ago',
            default         => date('M j, Y', $timestamp),
        };
    }

    public function path(string $url): string
    {
        if ($this->config !== null && str_starts_with($url, '/')) {
            return ((string) $this->config->get('base_path', '')) . $url;
        }
        return $url;
    }

    public function paginationUrl(string $base, int $page): string
    {
        if ($page === 1) {
            $url = $base;
        } elseif (str_contains($base, '?')) {
            $url = $base . '&page=' . $page;
        } else {
            $url = $base . '?page=' . $page;
        }
        return $this->path($url);
    }

    /**
     * Prefix a site-relative path (already run through path()/pagination_url())
     * with the configured scheme+host, for tags that require an absolute URL
     * — e.g. <link rel="canonical">/"prev"/"next">.
     */
    public function absoluteUrl(string $path): string
    {
        $base = rtrim((string) ($this->config?->get('base_url', '') ?? ''), '/');
        return $base . $path;
    }

    /**
     * Build the list of page numbers to render for a pagination bar,
     * collapsing long runs into a null "gap" marker (rendered as an
     * ellipsis in the template) so forums with tens of thousands of
     * pages don't print every page number in a row.
     *
     * Always includes page 1, the last page, and a window around the
     * current page. The window grows until at least $minVisible entries
     * (page numbers plus ellipses) are shown, or every page is already
     * included — so a small page count never gets truncated down to a
     * handful of links.
     *
     * @return array<int, int|null>
     */
    public function paginationRange(int $page, int $pages, int $minVisible = 10): array
    {
        if ($pages <= 1) {
            return [];
        }

        $range = [];
        for ($delta = 1; $delta <= $pages; $delta++) {
            $range = self::buildPaginationWindow($page, $pages, $delta);
            if (count($range) >= $minVisible || count($range) === $pages) {
                break;
            }
        }

        return $range;
    }

    /**
     * @return array<int, int|null>
     */
    private static function buildPaginationWindow(int $page, int $pages, int $delta): array
    {
        $keep = [];
        for ($p = 1; $p <= $pages; $p++) {
            if ($p === 1 || $p === $pages || ($p >= $page - $delta && $p <= $page + $delta)) {
                $keep[] = $p;
            }
        }

        $range = [];
        $prev  = null;
        foreach ($keep as $p) {
            if ($prev !== null) {
                $gap = $p - $prev;
                if ($gap === 2) {
                    // A single skipped page fits fine on its own — showing
                    // it takes no more room than an ellipsis would.
                    $range[] = $prev + 1;
                } elseif ($gap > 2) {
                    $range[] = null;
                }
            }
            $range[] = $p;
            $prev    = $p;
        }

        return $range;
    }

    private static function formatFilesize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
