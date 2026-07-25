<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * Discovers installed themes — shared by the admin theme pickers (site
 * default, per-forum override) and the front-end per-user theme preference.
 */
final class Themes
{
    /**
     * Return [directory_name => display_name] for every theme that has a
     * config.php and is not marked hidden.
     *
     * @return array<string,string>
     */
    public static function available(bool $withDefault = false): array
    {
        $themes = [];
        $dir    = (defined('ROOT_PATH') ? ROOT_PATH : '') . '/themes';

        if (is_dir($dir)) {
            foreach (new \DirectoryIterator($dir) as $entry) {
                if (!$entry->isDir() || $entry->isDot()) {
                    continue;
                }
                $configFile = $entry->getPathname() . '/config.php';
                if (!file_exists($configFile)) {
                    continue;
                }
                $config = require $configFile;
                if (!is_array($config) || !empty($config['hidden'])) {
                    continue;
                }
                $themes[$entry->getFilename()] = $config['name'] ?? $entry->getFilename();
            }
            asort($themes);
        }

        if ($withDefault) {
            $themes = ['' => '— Site default —'] + $themes;
        }

        return $themes;
    }
}
