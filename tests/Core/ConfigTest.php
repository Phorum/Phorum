<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'phorum_cfg_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function writeConfig(array $data): void
    {
        file_put_contents(
            $this->tmpFile,
            '<?php return ' . var_export($data, true) . ';'
        );
    }

    // -------------------------------------------------------------------------
    // constructor
    // -------------------------------------------------------------------------

    public function testConstructorThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        new Config('/tmp/does_not_exist_phorum_' . uniqid() . '.php');
    }

    public function testConstructorLoadsFile(): void
    {
        $this->writeConfig(['key' => 'value']);
        $config = new Config($this->tmpFile);
        $this->assertSame('value', $config->get('key'));
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function testGetReturnsValueForExistingKey(): void
    {
        $this->writeConfig(['db_host' => 'localhost', 'debug' => true]);
        $config = new Config($this->tmpFile);
        $this->assertSame('localhost', $config->get('db_host'));
        $this->assertTrue($config->get('debug'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->writeConfig([]);
        $config = new Config($this->tmpFile);
        $this->assertNull($config->get('missing'));
        $this->assertSame('fallback', $config->get('missing', 'fallback'));
    }

    public function testGetReturnsFalseDefault(): void
    {
        $this->writeConfig([]);
        $config = new Config($this->tmpFile);
        $this->assertFalse($config->get('missing', false));
    }

    // -------------------------------------------------------------------------
    // all()
    // -------------------------------------------------------------------------

    public function testAllReturnsFullArray(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 'three'];
        $this->writeConfig($data);
        $config = new Config($this->tmpFile);
        $this->assertSame($data, $config->all());
    }
}
