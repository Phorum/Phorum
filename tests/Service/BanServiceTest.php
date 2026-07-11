<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\BanMapper;
use Phorum\Service\BanService;
use PHPUnit\Framework\TestCase;

class BanServiceTest extends TestCase
{
    private function makeService(array $bans): BanService
    {
        $mapper = $this->createMock(BanMapper::class);
        $mapper->method('getBans')->willReturn($bans);
        return new BanService($mapper);
    }

    // -------------------------------------------------------------------------
    // Empty-value guards
    // -------------------------------------------------------------------------

    public function testCheckEmailReturnsFalseForEmptyEmail(): void
    {
        $svc = $this->makeService([]);
        $this->assertFalse($svc->checkEmail('', 1));
    }

    public function testCheckUsernameReturnsFalseForEmptyName(): void
    {
        $svc = $this->makeService([]);
        $this->assertFalse($svc->checkUsername('', 1));
    }

    public function testCheckSpamWordsReturnsFalseForEmptyBody(): void
    {
        $svc = $this->makeService([]);
        $this->assertFalse($svc->checkSpamWords('', 1));
    }

    // -------------------------------------------------------------------------
    // No bans
    // -------------------------------------------------------------------------

    public function testNoBansReturnsFalse(): void
    {
        $svc = $this->makeService([]);
        $this->assertFalse($svc->checkEmail('user@example.com', 0));
        $this->assertFalse($svc->checkUsername('bob', 0));
        $this->assertFalse($svc->checkSpamWords('hello world', 0));
    }

    // -------------------------------------------------------------------------
    // pcre=0 substring match (case-insensitive)
    // -------------------------------------------------------------------------

    public function testSubstringMatchHits(): void
    {
        $svc = $this->makeService([['string' => 'spam.com', 'pcre' => 0]]);
        $this->assertTrue($svc->checkEmail('user@spam.com', 0));
    }

    public function testSubstringMatchCaseInsensitive(): void
    {
        $svc = $this->makeService([['string' => 'SPAM', 'pcre' => 0]]);
        $this->assertTrue($svc->checkEmail('user@spam.com', 0));
    }

    public function testSubstringMissReturnsFalse(): void
    {
        $svc = $this->makeService([['string' => 'evil.com', 'pcre' => 0]]);
        $this->assertFalse($svc->checkEmail('user@good.com', 0));
    }

    // -------------------------------------------------------------------------
    // pcre=1 — word-boundary for non-spam types
    // -------------------------------------------------------------------------

    public function testPcreWordBoundaryHitsWholeWord(): void
    {
        $svc = $this->makeService([['string' => 'baduser', 'pcre' => 1]]);
        $this->assertTrue($svc->checkUsername('baduser', 0));
    }

    public function testPcreWordBoundaryDoesNotMatchPartialWord(): void
    {
        $svc = $this->makeService([['string' => 'bad', 'pcre' => 1]]);
        $this->assertFalse($svc->checkUsername('baduser', 0));
    }

    // -------------------------------------------------------------------------
    // pcre=1 — spam-words type uses pattern directly (no word boundary)
    // -------------------------------------------------------------------------

    public function testPcreSpamWordsMatchesSubstring(): void
    {
        $svc = $this->makeService([['string' => 'buy.*cheap', 'pcre' => 1]]);
        $this->assertTrue($svc->checkSpamWords('please buy very cheap pills', 0));
    }

    public function testPcreSpamWordsNoMatchReturnsFalse(): void
    {
        $svc = $this->makeService([['string' => 'casino', 'pcre' => 1]]);
        $this->assertFalse($svc->checkSpamWords('hello world', 0));
    }

    // -------------------------------------------------------------------------
    // Tilde in pattern is escaped (delimiter safety)
    // -------------------------------------------------------------------------

    public function testTildeInPatternDoesNotBreakRegex(): void
    {
        $svc = $this->makeService([['string' => 'spam~evil', 'pcre' => 1]]);
        $this->assertTrue($svc->checkSpamWords('spam~evil', 0));
    }

    // -------------------------------------------------------------------------
    // Invalid regex silently returns false
    // -------------------------------------------------------------------------

    public function testInvalidRegexReturnsFalse(): void
    {
        $svc = $this->makeService([['string' => '[unclosed', 'pcre' => 1]]);
        $this->assertFalse($svc->checkEmail('any@test.com', 0));
    }

    // -------------------------------------------------------------------------
    // Empty pattern is skipped
    // -------------------------------------------------------------------------

    public function testEmptyPatternIsSkipped(): void
    {
        $svc = $this->makeService([
            ['string' => '', 'pcre' => 0],
            ['string' => 'spam.com', 'pcre' => 0],
        ]);
        $this->assertTrue($svc->checkEmail('user@spam.com', 0));
    }

    // -------------------------------------------------------------------------
    // Multiple bans — both misses and hits work correctly
    // -------------------------------------------------------------------------

    public function testNonMatchingValueReturnsFalseWithMultipleBans(): void
    {
        $svc = $this->makeService([
            ['string' => 'evil.com', 'pcre' => 0],
            ['string' => 'spam.com', 'pcre' => 0],
        ]);
        $this->assertFalse($svc->checkEmail('user@safe.org', 0));
    }

    public function testSecondBanMatchesWhenFirstDoesNot(): void
    {
        $svc = $this->makeService([
            ['string' => 'evil.com', 'pcre' => 0],
            ['string' => 'spam.com', 'pcre' => 0],
        ]);
        $this->assertTrue($svc->checkEmail('user@spam.com', 0));
    }
}
