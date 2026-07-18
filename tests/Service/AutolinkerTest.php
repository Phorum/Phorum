<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Service\Autolinker;
use PHPUnit\Framework\TestCase;

class AutolinkerTest extends TestCase
{
    private Autolinker $autolinker;

    protected function setUp(): void
    {
        $this->autolinker = new Autolinker();
    }

    private function wrapUrl(string $href, string $label): string
    {
        return $href === $label ? "[{$href}]" : "[{$href}|{$label}]";
    }

    // -------------------------------------------------------------------------
    // linkifyUrls
    // -------------------------------------------------------------------------

    public function testLinkifiesBareHttpsUrl(): void
    {
        $result = $this->autolinker->linkifyUrls(
            'See https://example.com/page for info.',
            $this->wrapUrl(...)
        );
        $this->assertSame('See [https://example.com/page] for info.', $result);
    }

    public function testLinkifiesWwwPrefixedDomainWithImplicitHttpHref(): void
    {
        $result = $this->autolinker->linkifyUrls('Visit www.example.com today.', $this->wrapUrl(...));
        $this->assertSame('Visit [http://www.example.com|www.example.com] today.', $result);
    }

    public function testStripsTrailingSentencePunctuationFromUrl(): void
    {
        $result = $this->autolinker->linkifyUrls('See https://example.com/page.', $this->wrapUrl(...));
        $this->assertSame('See [https://example.com/page].', $result);
    }

    public function testLeavesTextWithNoUrlsUnchanged(): void
    {
        $result = $this->autolinker->linkifyUrls('No links here.', $this->wrapUrl(...));
        $this->assertSame('No links here.', $result);
    }

    public function testLinkifiesMultipleUrlsInOneString(): void
    {
        $result = $this->autolinker->linkifyUrls(
            'First https://one.example.com then https://two.example.com.',
            $this->wrapUrl(...)
        );
        $this->assertSame('First [https://one.example.com] then [https://two.example.com].', $result);
    }

    // -------------------------------------------------------------------------
    // linkifyEmails
    // -------------------------------------------------------------------------

    public function testLinkifiesBareEmailAddress(): void
    {
        $result = $this->autolinker->linkifyEmails(
            'Contact someone@example.com for help.',
            static fn(string $email): string => "<{$email}>"
        );
        $this->assertSame('Contact <someone@example.com> for help.', $result);
    }

    public function testLeavesTextWithNoEmailUnchanged(): void
    {
        $result = $this->autolinker->linkifyEmails(
            'No email here.',
            static fn(string $email): string => "<{$email}>"
        );
        $this->assertSame('No email here.', $result);
    }
}
