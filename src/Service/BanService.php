<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\BanMapper;

/**
 * Checks values against the banlists table.
 *
 * Matches the old Phorum ban-check behavior:
 * - pcre=0: case-insensitive substring match (stripos)
 * - pcre=1 on non-spam types: pattern wrapped in /\b…\b/i (word boundary)
 * - pcre=1 on spam words: pattern used as-is in /…/i
 */
class BanService
{
    public const TYPE_IP         = 1;
    public const TYPE_NAME       = 2;
    public const TYPE_EMAIL      = 3;
    public const TYPE_USERID     = 5;
    public const TYPE_SPAM_WORDS = 6;

    public function __construct(private readonly BanMapper $bans) {}

    /**
     * True if the current request IP is banned in the given forum context.
     * Pass forumId = 0 to check only global bans (e.g. on registration).
     */
    public function checkIp(int $forumId): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return $ip !== '' && $this->match($ip, self::TYPE_IP, $forumId);
    }

    /** True if the email address is banned. */
    public function checkEmail(string $email, int $forumId): bool
    {
        return $email !== '' && $this->match($email, self::TYPE_EMAIL, $forumId);
    }

    /** True if the display name / username is banned. */
    public function checkUsername(string $name, int $forumId): bool
    {
        return $name !== '' && $this->match($name, self::TYPE_NAME, $forumId);
    }

    /** True if the message body contains a spam-listed phrase. */
    public function checkSpamWords(string $body, int $forumId): bool
    {
        return $body !== '' && $this->match($body, self::TYPE_SPAM_WORDS, $forumId);
    }

    /** True if any ban entry of $type matches $value. */
    private function match(string $value, int $type, int $forumId): bool
    {
        foreach ($this->bans->getBans($type, $forumId) as $ban) {
            if ($this->matchesBan($value, $ban, $type)) {
                return true;
            }
        }
        return false;
    }

    private function matchesBan(string $value, array $ban, int $type): bool
    {
        $pattern = $ban['string'];
        if ($pattern === '') return false;

        if ((int) $ban['pcre']) {
            $safe  = str_replace('~', '\~', $pattern);
            $regex = $type === self::TYPE_SPAM_WORDS
                ? '~' . $safe . '~i'
                : '~\b' . $safe . '\b~i';
            return @preg_match($regex, $value) === 1;
        }

        return stripos($value, $pattern) !== false;
    }
}
