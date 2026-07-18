<?php
declare(strict_types=1);

// Same route-array shape as etc/routes.php — App::initModules() require()s
// this file when the webhooks module is enabled and merges the result in
// before the Router is built, so no core routes.php edit is needed.
//
// Actions are fully-qualified (leading backslash) since Phorum\Mod\Webhooks\*
// isn't PSR-4 autoloaded under Phorum\Http\Controllers\ — see
// App::resolveAction().

return [
    [
        'type'    => 'exact',
        'pattern' => '/admin/webhooks',
        'action'  => '\Phorum\Mod\Webhooks\Admin\WebhooksController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/webhooks/create',
        'action'  => '\Phorum\Mod\Webhooks\Admin\WebhooksController@create',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/webhooks/(\d+)/edit$!',
        'action'  => '\Phorum\Mod\Webhooks\Admin\WebhooksController@edit',
        'tokens'  => ['webhook_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/webhooks/(\d+)/delete$!',
        'action'  => '\Phorum\Mod\Webhooks\Admin\WebhooksController@delete',
        'tokens'  => ['webhook_id'],
    ],
];
