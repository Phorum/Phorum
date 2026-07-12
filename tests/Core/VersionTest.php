<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    /**
     * Guards the shape release.sh regex-replaces (`public const CURRENT = '...';`)
     * so a manual edit can't silently break the release script's substitution.
     */
    public function testCurrentIsANonEmptyVersionString(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', Version::CURRENT);
    }
}
