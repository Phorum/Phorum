<?php
declare(strict_types=1);

// Same route-array shape as etc/routes.php — App::initModules() require()s
// this file when the oauth module is enabled and merges the result in
// before the Router is built, so no core routes.php edit is needed.
//
// Provider name is constrained directly in the regex rather than validated
// only in PHP, mirroring how mods/webhooks/routes.php constrains its own
// numeric tokens. Actions are fully-qualified (leading backslash) since
// Phorum\Mod\Oauth\* isn't PSR-4 autoloaded under Phorum\Http\Controllers\ —
// see App::resolveAction().

return [
    [
        'type'    => 'regex',
        'pattern' => '!^/auth/oauth/(google|github)$!',
        'action'  => '\Phorum\Mod\Oauth\Controllers\OauthController@start',
        'tokens'  => ['provider'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/auth/oauth/(google|github)/callback$!',
        'action'  => '\Phorum\Mod\Oauth\Controllers\OauthController@callback',
        'tokens'  => ['provider'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/oauth',
        'action'  => '\Phorum\Mod\Oauth\Admin\OauthController@index',
    ],
];
