<?php
declare(strict_types=1);

namespace Phorum\Tests\Model;

use PHPUnit\Framework\TestCase;
use Phorum\Model\UserSettings;

class UserSettingsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // decode — edge cases
    // -------------------------------------------------------------------------

    public function testDecodeNullReturnsEmpty(): void
    {
        $s = UserSettings::decode(null);
        $this->assertSame([], $s->toArray());
    }

    public function testDecodeEmptyStringReturnsEmpty(): void
    {
        $s = UserSettings::decode('');
        $this->assertSame([], $s->toArray());
    }

    public function testDecodeGarbageReturnsEmpty(): void
    {
        $s = UserSettings::decode('not-valid-at-all');
        $this->assertSame([], $s->toArray());
    }

    // -------------------------------------------------------------------------
    // decode — JSON format
    // -------------------------------------------------------------------------

    public function testDecodeJson(): void
    {
        $raw = '{"locale":"en","theme":"default"}';
        $s   = UserSettings::decode($raw);
        $this->assertSame('en', $s->get('locale'));
        $this->assertSame('default', $s->get('theme'));
    }

    public function testDecodeJsonMissingKeyReturnsNull(): void
    {
        $s = UserSettings::decode('{"locale":"en"}');
        $this->assertNull($s->get('missing'));
    }

    // -------------------------------------------------------------------------
    // decode — PHP-serialized format (Phorum 6 upgrade path)
    // -------------------------------------------------------------------------

    public function testDecodePhpSerialized(): void
    {
        $raw = serialize(['locale' => 'de', 'timezone' => 'Europe/Berlin']);
        $s   = UserSettings::decode($raw);
        $this->assertSame('de', $s->get('locale'));
        $this->assertSame('Europe/Berlin', $s->get('timezone'));
    }

    public function testDecodePhpSerializedAdminToken(): void
    {
        // Old Phorum 6 stored admin_token + admin_token_time in settings_data.
        $raw = serialize(['admin_token' => 'abc123', 'admin_token_time' => 1700000000]);
        $s   = UserSettings::decode($raw);
        $this->assertSame('abc123', $s->get('admin_token'));
        $this->assertSame(1700000000, $s->get('admin_token_time'));
    }

    public function testDecodePhpSerializedDoesNotInstantiateObjects(): void
    {
        // A serialized object payload must not result in an object being created.
        $raw = 'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}';
        $s   = UserSettings::decode($raw);
        // unserialize with allowed_classes:false returns false for object payloads,
        // so we fall through to an empty instance.
        $this->assertSame([], $s->toArray());
    }

    // -------------------------------------------------------------------------
    // fromArray
    // -------------------------------------------------------------------------

    public function testFromArray(): void
    {
        $s = UserSettings::fromArray(['key' => 'value']);
        $this->assertSame('value', $s->get('key'));
    }

    // -------------------------------------------------------------------------
    // with / without / immutability
    // -------------------------------------------------------------------------

    public function testWithReturnsNewInstance(): void
    {
        $a = UserSettings::fromArray(['x' => 1]);
        $b = $a->with('y', 2);
        $this->assertNotSame($a, $b);
        $this->assertNull($a->get('y'));
        $this->assertSame(2, $b->get('y'));
        $this->assertSame(1, $b->get('x'));
    }

    public function testWithoutRemovesKey(): void
    {
        $a = UserSettings::fromArray(['x' => 1, 'y' => 2]);
        $b = $a->without('x');
        $this->assertNull($b->get('x'));
        $this->assertSame(2, $b->get('y'));
    }

    public function testWithoutNonExistentKeyIsNoop(): void
    {
        $a = UserSettings::fromArray(['x' => 1]);
        $b = $a->without('missing');
        $this->assertSame($a->toArray(), $b->toArray());
    }

    // -------------------------------------------------------------------------
    // encode
    // -------------------------------------------------------------------------

    public function testEncodeEmptyReturnsEmptyString(): void
    {
        $s = UserSettings::decode(null);
        $this->assertSame('', $s->encode());
    }

    public function testEncodeProducesJson(): void
    {
        $s = UserSettings::fromArray(['locale' => 'fr', 'tz' => 'Europe/Paris']);
        $decoded = json_decode($s->encode(), true);
        $this->assertSame('fr', $decoded['locale']);
        $this->assertSame('Europe/Paris', $decoded['tz']);
    }

    // -------------------------------------------------------------------------
    // Round-trip: PHP-serialize in → JSON out
    // -------------------------------------------------------------------------

    public function testRoundTripFromPhpSerialized(): void
    {
        $original = ['locale' => 'ja', 'custom_flag' => true];
        $raw      = serialize($original);

        $s       = UserSettings::decode($raw);
        $encoded = $s->encode();

        // encoded value must be valid JSON
        $this->assertNotEmpty($encoded);
        $redecoded = UserSettings::decode($encoded);
        $this->assertSame('ja', $redecoded->get('locale'));
        $this->assertTrue($redecoded->get('custom_flag'));
    }
}
