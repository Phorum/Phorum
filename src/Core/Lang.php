<?php
declare(strict_types=1);

namespace Phorum\Core;

class Lang
{
    private static array  $strings = [];
    private static string $locale  = 'en';

    /**
     * Load translations for a locale using a fallback chain:
     *   lang/en.php → lang/<base>.php → lang/<locale>.php
     *
     * For example, loading 'es-MX' overlays:
     *   en.php → es.php → es-MX.php
     * A plain code like 'es' just overlays:
     *   en.php → es.php
     */
    public static function load(string $locale): void
    {
        self::$locale  = $locale;
        self::$strings = [];

        if (!defined('ROOT_PATH')) {
            return;
        }

        // Layer 1: English base — always loaded so any untranslated key falls back
        self::overlay(ROOT_PATH . '/lang/en.php');

        if ($locale === 'en') {
            return;
        }

        // Layer 2: Base language (e.g. 'es' from 'es-MX')
        $parts = explode('-', $locale, 2);
        $base  = $parts[0];

        if (isset($parts[1])) {
            // Regional variant: load base language first, then the variant delta
            self::overlay(ROOT_PATH . "/lang/{$base}.php");
        }

        // Layer 3: Full locale file (e.g. 'es-MX' or just 'es')
        self::overlay(ROOT_PATH . "/lang/{$locale}.php");
    }

    private static function overlay(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $loaded = require $path;
        if (is_array($loaded)) {
            self::$strings = array_merge(self::$strings, $loaded);
        }
    }

    /**
     * Translate a key, substituting named {placeholder} values.
     * Falls back to the key itself when no translation is defined.
     *
     * @param array<string, string> $replacements
     */
    public static function get(string $key, array $replacements = []): string
    {
        $str = self::$strings[$key] ?? $key;

        foreach ($replacements as $placeholder => $value) {
            $str = str_replace('{' . $placeholder . '}', (string) $value, $str);
        }

        return $str;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** Returns 'rtl' for right-to-left languages, 'ltr' for everything else. */
    public static function dir(): string
    {
        return self::$strings['_dir'] ?? 'ltr';
    }
}
