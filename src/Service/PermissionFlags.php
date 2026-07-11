<?php
declare(strict_types=1);

namespace Phorum\Service;

/**
 * Shared permission-bit checkbox flags, used anywhere a forum's pub_perms/
 * reg_perms or a group's per-forum grant is edited (values match
 * PermissionService's ALLOW_* constants).
 */
final class PermissionFlags
{
    public const FLAGS = [
        1   => 'Read',
        2   => 'Reply',
        4   => 'Edit own posts',
        8   => 'Start new threads',
        32  => 'Attach files',
        64  => 'Moderate messages',
        128 => 'Moderate users',
    ];

    /** Combine posted checkbox values into a single bitmask. */
    public static function combine(array $posted): int
    {
        $result = 0;
        foreach ($posted as $bit) {
            $bit = (int) $bit;
            if (isset(self::FLAGS[$bit])) {
                $result |= $bit;
            }
        }
        return $result;
    }

    /** Return the list of bit values present in a bitmask, for pre-checking checkboxes. */
    public static function decode(int $perms): array
    {
        $bits = [];
        foreach (array_keys(self::FLAGS) as $bit) {
            if ($perms & $bit) {
                $bits[] = $bit;
            }
        }
        return $bits;
    }
}
