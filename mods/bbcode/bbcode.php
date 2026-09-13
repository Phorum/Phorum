<?php
declare(strict_types=1);

namespace Phorum\Mod\Bbcode;

use Phorum\Hook\HookDispatcher;
use Phorum\Service\Autolinker;

/**
 * BBCode formatter for Phorum.
 *
 * Handles messages stored with format=bbcode (all Phorum 6.x content).
 * Self-registers on the 'format' hook when this file is require'd.
 *
 * Tags supported: b, i, u, s, sub, sup, center, left, right, hr,
 *                 color, size, url, img, email, quote, code, list/[*]
 *
 * Bare URLs and email addresses (not wrapped in [url]/[email]) are
 * autolinked automatically via Autolinker.
 */
class BbcodeFormatter
{
    /**
     * URL schemes allowed in [url]/[img] hrefs. A URL with no scheme at all
     * (a relative or protocol-relative link) is allowed too, but that case is
     * an explicit branch in safeUrl() rather than an '' entry here — folding
     * it into the list is what let an unparseable scheme through as "empty".
     */
    private const SAFE_SCHEMES = ['http', 'https', 'ftp'];

    /**
     * How many levels of nested [quote] to resolve.
     *
     * Real quote chains are a handful deep; this only bites on markup built to
     * be expensive. Tags past the limit are left as the literal text they came
     * in as — already HTML-escaped by the first pass of render() — so they show
     * up as visible [quote] markers rather than disappearing.
     */
    private const MAX_QUOTE_DEPTH = 20;

    public function render(string $body): string
    {
        // HTML-escape the full body first. The BBCode delimiters [ ] = are not
        // HTML special chars, so every tag is processed correctly after this.
        $text = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Extract [code] blocks before nl2br and other processing; restore last.
        $blocks = [];
        $n      = 0;
        $text = preg_replace_callback(
            '/\[code\](.*?)\[\/code\]/si',
            function (array $m) use (&$blocks, &$n): string {
                $key = "\x02{$n}\x03";
                $blocks[$key] = '<pre class="bbcode"><code>' . $m[1] . '</code></pre>';
                $n++;
                return $key;
            },
            $text
        );

        // Convert bare URLs and email addresses into [url]/[email] tags so
        // they render identically to explicit ones below. Existing explicit
        // [url]/[img]/[email] tags are protected first so their raw href
        // text isn't re-wrapped.
        $protected = [];
        $p         = 0;
        $text = preg_replace_callback(
            '/\[(url|img|email)(?:=[^\]]*)?\].*?\[\/\1\]/si',
            function (array $m) use (&$protected, &$p): string {
                $key = "\x04{$p}\x05";
                $protected[$key] = $m[0];
                $p++;
                return $key;
            },
            $text
        );

        $autolink = new Autolinker();
        $text     = $autolink->linkifyEmails(
            $text,
            static fn(string $email): string => '[email]' . $email . '[/email]'
        );
        $text     = $autolink->linkifyUrls(
            $text,
            static fn(string $href, string $label): string => $href === $label
                ? '[url]' . $href . '[/url]'
                : '[url=' . $href . ']' . $label . '[/url]'
        );

        $text = strtr($text, $protected);

        // [quote] — iterate to resolve nesting (inner-most first each pass).
        // Each pass resolves exactly one level, because the lazy `.*?` only
        // ever matches the innermost pair, so the pass count is the nesting
        // depth — and each pass rescans a string that has grown about fivefold
        // per level. That made cost quadratic in depth: 4,369 levels, which is
        // what fits in the 64 KB TEXT body column, took ~185 ms of CPU on
        // every render, with no output cache and the feed path rendering
        // bodies too. Capping the depth caps the pass count directly and puts
        // the same input under a millisecond.
        $prev  = null;
        $depth = 0;
        while ($prev !== $text && $depth < self::MAX_QUOTE_DEPTH) {
            $prev = $text;
            $depth++;
            $text = preg_replace_callback(
                '/\[quote([^\]]*)\](.*?)\[\/quote\]/si',
                function (array $m): string {
                    // Arg may be: nothing, =author, ="author", or = &quot;author&quot;
                    $raw    = html_entity_decode(ltrim(trim($m[1]), '= '), ENT_QUOTES, 'UTF-8');
                    $author = trim($raw, '"\'');
                    $header = $author !== ''
                        ? '<div class="bbcode-quote-by">'
                          . htmlspecialchars($author, ENT_QUOTES, 'UTF-8')
                          . ' wrote:</div>'
                        : '';
                    return '<blockquote class="bbcode">'
                         . $header
                         . '<div class="bbcode-quote-body">' . $m[2] . '</div>'
                         . '</blockquote>';
                },
                $text
            );
        }

        // [list=type] / [*] items / [/list]
        $text = preg_replace_callback(
            '/\[list(?:=([a-zA-Z0-9]+))?\](.*?)\[\/list\]/si',
            function (array $m): string {
                $type    = $m[1] ?? '';
                // Replace [*]item up to the next [*] or end with <li>item</li>
                $content = preg_replace(
                    '/\[\*\](.*?)(?=\[\*\]|$)/si',
                    '<li>$1</li>',
                    $m[2]
                );
                if ($type !== '' && preg_match('/^[ia1]$/i', $type)) {
                    return '<ol type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">'
                         . trim($content) . '</ol>';
                }
                return '<ul>' . trim($content) . '</ul>';
            },
            $text
        );

        // Simple paired tags processed in separate passes so they nest correctly
        $simple = [
            'b'      => ['<strong>', '</strong>'],
            'i'      => ['<em>', '</em>'],
            'u'      => ['<u>', '</u>'],
            's'      => ['<s>', '</s>'],
            'sub'    => ['<sub>', '</sub>'],
            'sup'    => ['<sup>', '</sup>'],
            'center' => ['<div style="text-align:center">', '</div>'],
            'left'   => ['<div style="text-align:left">', '</div>'],
            'right'  => ['<div style="text-align:right">', '</div>'],
        ];
        foreach ($simple as $tag => [$open, $close]) {
            $text = preg_replace(
                '/\[' . $tag . '\](.*?)\[\/' . $tag . '\]/si',
                $open . '$1' . $close,
                $text
            );
        }

        // [color=name_or_hex] — strict allowlist prevents CSS injection
        $text = preg_replace_callback(
            '/\[color=([A-Za-z0-9#]+)\](.*?)\[\/color\]/si',
            fn(array $m) => '<span style="color:' . $m[1] . '">' . $m[2] . '</span>',
            $text
        );

        // [size=value] — strict allowlist prevents CSS injection
        $text = preg_replace_callback(
            '/\[size=([A-Za-z0-9.%-]+)\](.*?)\[\/size\]/si',
            fn(array $m) => '<span style="font-size:' . $m[1] . '">' . $m[2] . '</span>',
            $text
        );

        // [url=http://...]link text[/url]  or  [url]http://...[/url]
        $text = preg_replace_callback(
            '/\[url(?:=([^\]]*))?\](.*?)\[\/url\]/si',
            function (array $m): string {
                $href  = trim($m[1] !== '' ? $m[1] : $m[2]);
                $label = $m[2] !== '' ? $m[2] : $href;
                $safe  = $this->safeUrl($href);
                if ($safe === null) {
                    return $label; // already HTML-escaped
                }
                // Emit the normalized URL, not the raw one — the scheme check
                // only means anything if what reaches the browser is what was
                // checked.
                return '<a href="' . $this->escapeAttr($safe) . '" rel="nofollow">' . $label . '</a>';
            },
            $text
        );

        // [img]url[/img]
        $text = preg_replace_callback(
            '/\[img\](.*?)\[\/img\]/si',
            function (array $m): string {
                $safe = $this->safeUrl(trim($m[1]));
                if ($safe === null) {
                    return '';
                }
                return '<img src="' . $this->escapeAttr($safe) . '" class="bbcode" alt=""/>';
            },
            $text
        );

        // [email]addr[/email]
        $text = preg_replace_callback(
            '/\[email\](.*?)\[\/email\]/si',
            fn(array $m) => '<a href="mailto:' . $m[1] . '">' . $m[1] . '</a>',
            $text
        );

        // [hr] (open-only)
        $text = preg_replace('/\[hr\]/i', '<hr class="bbcode"/>', $text);

        // Newlines → <br> (outside the protected [code] blocks)
        $text = nl2br($text);

        // Restore protected [code] blocks
        return strtr($text, $blocks);
    }

    /**
     * Vet a [url]/[img] target and return the exact string to put in the
     * href/src attribute, or null if it must not be linked at all.
     *
     * Returns the normalized URL rather than a bool so the caller emits the
     * same string that was vetted. parse_url() is deliberately not used: it
     * returns null for a scheme containing a control character (and false for
     * other malformed input), which the previous `?? ''` collapsed into the
     * "no scheme, therefore relative, therefore safe" case — so
     * "java<TAB>script:alert(1)" passed the check and browsers, which strip
     * tabs and newlines out of URLs before parsing them, then ran it.
     */
    private function safeUrl(string $url): ?string
    {
        $normalized = $this->normalizeUrl($url);
        $safe       = null;

        if ($normalized !== null) {
            $scheme = preg_match('~^([A-Za-z][A-Za-z0-9+.\-]*):~', $normalized, $m) === 1
                ? strtolower($m[1])
                : '';
            // No scheme at all is a relative/protocol-relative link, which
            // can't carry script the way a bare "javascript:" can.
            if ($scheme === '' || in_array($scheme, self::SAFE_SCHEMES, strict: true)) {
                $safe = $normalized;
            }
        }

        return $safe;
    }

    /**
     * Put a URL into the form a browser will actually parse, so the scheme
     * check below can't disagree with it: decode the HTML entities the
     * pre-escape pass added, drop the ASCII tab/CR/LF that browsers strip
     * from anywhere in a URL, and trim the leading/trailing C0 controls and
     * spaces they ignore. Returns null when any other control character
     * survives — that isn't a URL worth emitting, and rejecting it beats
     * reasoning about how each browser normalizes it.
     */
    private function normalizeUrl(string $url): ?string
    {
        $decoded    = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = trim(str_replace(["\t", "\r", "\n"], '', $decoded), "\x00..\x20");

        return preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1 ? null : $normalized;
    }

    /** Escape a value for use inside a double-quoted HTML attribute. */
    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
// Self-register the 'format' hook handler so the app only needs to require
// this file; no additional registration code needed in App.php.

HookDispatcher::getInstance()->register(
    hook:     'format',
    callback: static function (string $body, string $format): ?string {
        if ($format !== 'bbcode') {
            return null; // let other handlers try
        }
        return (new BbcodeFormatter())->render($body);
    },
    priority: 10,
);
