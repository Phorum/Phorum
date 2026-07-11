<?php
declare(strict_types=1);

namespace Phorum\Service;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Renders a line-level HTML diff between two strings.
 * Unchanged lines are plain text; removed lines are wrapped in <del>,
 * added lines in <ins>.
 */
class DiffRenderer
{
    private Differ $differ;

    public function __construct()
    {
        $this->differ = new Differ(new UnifiedDiffOutputBuilder(''));
    }

    /**
     * Return an HTML string highlighting what changed from $old to $new.
     * All text is HTML-escaped; only <del> and <ins> tags are emitted.
     */
    public function renderHtml(string $old, string $new): string
    {
        if ($old === $new) {
            return htmlspecialchars($old, ENT_QUOTES, 'UTF-8');
        }

        $lines = $this->differ->diffToArray($old, $new);
        $html  = '';
        foreach ($lines as [$text, $type]) {
            $safe = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
            $html .= match ($type) {
                Differ::ADDED   => "<ins>{$safe}</ins>",
                Differ::REMOVED => "<del>{$safe}</del>",
                default         => $safe,
            };
        }
        return $html;
    }
}
