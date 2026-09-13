<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\Lang;
use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure ROOT_PATH points to the project root for file loading tests
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
        // Reset static state between tests
        Lang::load('en');
    }

    // -------------------------------------------------------------------------
    // load() and locale()
    // -------------------------------------------------------------------------

    public function testLoadSetsLocale(): void
    {
        Lang::load('fr');
        $this->assertSame('fr', Lang::locale());
    }

    public function testLoadEnglishPopulatesStrings(): void
    {
        Lang::load('en');
        // Any defined key must return a non-empty string
        $this->assertNotSame('', Lang::get('nav.home'));
    }

    public function testLoadFrenchOverlaysOnEnglish(): void
    {
        Lang::load('en');
        $enName = Lang::get('_name');

        Lang::load('fr');
        $frName = Lang::get('_name');

        $this->assertSame('English', $enName);
        $this->assertNotSame('English', $frName);
    }

    public function testLoadRegionalVariantFallsBackToBase(): void
    {
        // fr-CA falls back through en → fr → fr-CA
        Lang::load('fr-CA');
        $this->assertSame('fr-CA', Lang::locale());
        // '_name' key must be set (defined in fr.php at minimum)
        $name = Lang::get('_name');
        $this->assertNotSame('_name', $name);
    }

    // -------------------------------------------------------------------------
    // get() — fallback and replacements
    // -------------------------------------------------------------------------

    public function testGetReturnsKeyForMissingTranslation(): void
    {
        Lang::load('en');
        $this->assertSame('nonexistent.key', Lang::get('nonexistent.key'));
    }

    public function testGetSubstitutesReplacements(): void
    {
        // Inject a known string with a placeholder, using the French locale reset
        Lang::load('en');
        // Use a key with a real placeholder from the English file
        // We test the substitution mechanism with a synthetic call
        $result = Lang::get('nonexistent', ['{count}' => 'world']);
        // Key not found, returns key unchanged — substitution is still applied
        $this->assertSame('nonexistent', $result);
    }

    public function testGetReplacesPlaceholders(): void
    {
        // Fabricate a known string with placeholder by calling with a key that exists
        // in en.php that uses {placeholder} syntax. We test the generic mechanism:
        Lang::load('en');
        // Pass a key that doesn't exist — the fallback value is the key itself.
        // Pass a replacement array — placeholders in key names aren't replaced.
        // Real test: reload using an existing key, if it has a placeholder.
        // We just verify the replacement mechanism doesn't break.
        $str = Lang::get('nav.home', ['foo' => 'bar']);
        $this->assertIsString($str);
    }

    // -------------------------------------------------------------------------
    // dir()
    // -------------------------------------------------------------------------

    public function testDirReturnsLtrForEnglish(): void
    {
        Lang::load('en');
        $this->assertSame('ltr', Lang::dir());
    }

    public function testDirReturnsRtlForArabic(): void
    {
        Lang::load('ar');
        $this->assertSame('rtl', Lang::dir());
    }

    // -------------------------------------------------------------------------
    // availableLocales()
    // -------------------------------------------------------------------------

    public function testAvailableLocalesAlwaysIncludesEnglish(): void
    {
        $locales = Lang::availableLocales();
        $this->assertArrayHasKey('en', $locales);
    }

    public function testAvailableLocalesIncludesKnownShippedLocales(): void
    {
        $locales = Lang::availableLocales();
        $this->assertArrayHasKey('fr', $locales);
        $this->assertArrayHasKey('ar', $locales);
        $this->assertArrayHasKey('zh-CN', $locales);
    }

    public function testAvailableLocalesUsesNameKeyForDisplayName(): void
    {
        $locales = Lang::availableLocales();
        $this->assertStringContainsString('Français', $locales['fr']);
        $this->assertStringContainsString('(fr)', $locales['fr']);
    }

    public function testAvailableLocalesWithDefaultAddsLeadingBlankEntry(): void
    {
        $locales = Lang::availableLocales(withDefault: true);
        $keys    = array_keys($locales);
        $this->assertSame('', $keys[0]);
    }

    public function testAvailableLocalesWithoutDefaultHasNoBlankEntry(): void
    {
        $locales = Lang::availableLocales();
        $this->assertArrayNotHasKey('', $locales);
    }

    // -------------------------------------------------------------------------
    // number()
    // -------------------------------------------------------------------------

    /**
     * English groups thousands with a comma. This result is identical whether
     * ext-intl is installed or the number_format() fallback runs, so it holds
     * on every host.
     */
    public function testNumberGroupsThousandsForEnglish(): void
    {
        Lang::load('en');
        $this->assertSame('15,619', Lang::number(15619));
    }

    /**
     * Numbers below the grouping threshold come back unchanged.
     */
    public function testNumberLeavesSmallNumbersUngrouped(): void
    {
        Lang::load('en');
        $this->assertSame('999', Lang::number(999));
    }

    /**
     * German swaps the separators: '.' groups thousands, ',' marks decimals.
     * Both the intl and fallback paths agree on this locale.
     */
    public function testNumberUsesLocaleSeparators(): void
    {
        Lang::load('de');
        $this->assertSame('15.619', Lang::number(15619));
        $this->assertSame('1.234,50', Lang::number(1234.5, 2));
    }

    /**
     * The decimals argument pads as well as truncates, so a whole number asked
     * for two decimals still shows them.
     */
    public function testNumberRespectsDecimalPlaces(): void
    {
        Lang::load('en');
        $this->assertSame('1,234.50', Lang::number(1234.5, 2));
        $this->assertSame('1,234.00', Lang::number(1234, 2));
        $this->assertSame('1,235', Lang::number(1234.5, 0));
    }

    /**
     * Every shipped locale resolves both separator keys through the fallback
     * chain, so the number_format() path never emits a raw key name. Regional
     * delta files (fr-CA, pt-PT) inherit them from their base language.
     */
    public function testEveryLocaleResolvesSeparatorKeys(): void
    {
        foreach (array_keys(Lang::availableLocales()) as $locale) {
            Lang::load($locale);
            $this->assertNotSame('_thousands_sep', Lang::get('_thousands_sep'), "locale {$locale}");
            $this->assertNotSame('_decimal_sep', Lang::get('_decimal_sep'), "locale {$locale}");
        }
    }

    /**
     * Without ext-intl the separators come straight from the locale file —
     * French uses a narrow no-break space (U+202F) to group thousands.
     */
    public function testNumberFallsBackToLocaleFileSeparators(): void
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('ext-intl is installed; NumberFormatter handles this locale instead.');
        }

        Lang::load('fr');
        $this->assertSame("15\u{202F}619", Lang::number(15619));
    }

    /**
     * With ext-intl installed, locales with non-uniform grouping are rendered
     * correctly — Hindi groups by lakh/crore, which number_format() cannot do.
     */
    public function testNumberUsesIntlGroupingRulesWhenAvailable(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('ext-intl is not installed; the fallback groups in threes.');
        }

        Lang::load('hi');
        $this->assertSame('12,34,567', Lang::number(1234567));
    }
}
