<?php
declare(strict_types=1);

namespace Phorum\Service;

/**
 * Detects bare URLs and email addresses in already HTML-escaped text and
 * hands each match to a caller-supplied callback to be wrapped as a link.
 *
 * Used to autolink plain-text and BBCode message bodies, which have no
 * markdown-style link syntax of their own. Markdown bodies get the same
 * behavior for free from CommonMark's own AutolinkExtension instead.
 */
class Autolinker
{
    private const URL_REGEX = '/\b(?:https?|ftp):\/\/[^\s<>"\']+|\bwww\.[^\s<>"\']+/i';

    private const EMAIL_REGEX = '/\b[A-Za-z0-9][A-Za-z0-9_.+-]*@[A-Za-z0-9-]+\.[A-Za-z0-9.-]*[A-Za-z0-9]\b/';

    /**
     * Replace bare URLs (and bare "www." domains) with the result of
     * $wrap($href, $label). $href always has a scheme (www.* URLs are
     * given an implicit http:// href); $label preserves what was typed.
     *
     * @param callable(string, string): string $wrap
     */
    public function linkifyUrls(string $text, callable $wrap): string
    {
        return (string) preg_replace_callback(
            self::URL_REGEX,
            function (array $m) use ($wrap): string {
                [$url, $trailing] = $this->splitTrailingPunctuation($m[0]);
                $href = stripos($url, 'www.') === 0 ? 'http://' . $url : $url;
                return $wrap($href, $url) . $trailing;
            },
            $text
        );
    }

    /**
     * Replace bare email addresses with the result of $wrap($email).
     *
     * @param callable(string): string $wrap
     */
    public function linkifyEmails(string $text, callable $wrap): string
    {
        return (string) preg_replace_callback(
            self::EMAIL_REGEX,
            static fn(array $m): string => $wrap($m[0]),
            $text
        );
    }

    /**
     * Split off trailing sentence punctuation (e.g. "https://x.com." at the
     * end of a sentence) so it isn't swallowed into the link.
     *
     * @return array{0: string, 1: string}
     */
    private function splitTrailingPunctuation(string $url): array
    {
        if (preg_match('/^(.*?)([.,!?;:]+)$/', $url, $m)) {
            return [$m[1], $m[2]];
        }
        return [$url, ''];
    }
}
