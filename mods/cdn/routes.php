<?php
declare(strict_types=1);

// Same route-array shape as etc/routes.php — App::initModules() require()s
// this file when the cdn module is enabled and merges the result in before
// the Router is built, so no core routes.php edit is needed.
//
// A single settings page (not a CRUD list) since this is one global
// configuration, mirroring core SettingsController's single-action index().
// Action is fully-qualified (leading backslash) since Phorum\Mod\Cdn\* isn't
// PSR-4 autoloaded under Phorum\Http\Controllers\ — see App::resolveAction().

return [
    [
        'type'    => 'exact',
        'pattern' => '/admin/cdn',
        'action'  => '\Phorum\Mod\Cdn\Admin\CdnController@index',
    ],
];
