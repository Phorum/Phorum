<?php
declare(strict_types=1);

return [
    // Theme assets (CSS, images, fonts) — served before everything else
    [
        'type'    => 'regex',
        'pattern' => '!^/theme/([\w-]+)/(.+)$!',
        'action'  => 'ThemeController@asset',
        'tokens'  => ['theme', 'file'],
    ],

    // Installer (checked before everything else)
    [
        'type'    => 'exact',
        'pattern' => '/install',
        'action'  => 'InstallController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/install/complete',
        'action'  => 'InstallController@complete',
    ],

    // Legacy Phorum 6 redirects (301 permanent)
    [
        'type'    => 'exact',
        'pattern' => '/index.php',
        'action'  => 'LegacyRedirectController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/list.php',
        'action'  => 'LegacyRedirectController@list',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/read.php',
        'action'  => 'LegacyRedirectController@read',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/profile.php',
        'action'  => 'LegacyRedirectController@profile',
    ],

    // Forum index
    [
        'type'    => 'exact',
        'pattern' => '/',
        'action'  => 'ForumController@index',
    ],

    // Forum view
    [
        'type'    => 'regex',
        'pattern' => '!^/forum/(\d+)$!',
        'action'  => 'ForumController@show',
        'tokens'  => ['forum_id'],
    ],

    // Mark forum as fully read
    [
        'type'    => 'regex',
        'pattern' => '!^/forum/(\d+)/mark-read$!',
        'action'  => 'ForumController@markForumRead',
        'tokens'  => ['forum_id'],
    ],

    // Thread / read
    [
        'type'    => 'regex',
        'pattern' => '!^/forum/(\d+)/thread/(\d+)$!',
        'action'  => 'MessageController@thread',
        'tokens'  => ['forum_id', 'thread_id'],
    ],

    // New thread or reply
    [
        'type'    => 'regex',
        'pattern' => '!^/forum/(\d+)/post$!',
        'action'  => 'MessageController@post',
        'tokens'  => ['forum_id'],
    ],

    // User avatar
    [
        'type'    => 'regex',
        'pattern' => '!^/avatar/(\d+)$!',
        'action'  => 'FileController@avatar',
        'tokens'  => ['user_id'],
    ],

    // File attachment download/serve
    [
        'type'    => 'regex',
        'pattern' => '!^/file/(\d+)/(.+)$!',
        'action'  => 'FileController@serve',
        'tokens'  => ['file_id', 'filename'],
    ],

    // Edit existing message
    [
        'type'    => 'regex',
        'pattern' => '!^/message/(\d+)/edit$!',
        'action'  => 'MessageController@editMessage',
        'tokens'  => ['message_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/message/(\d+)/changes$!',
        'action'  => 'MessageController@changes',
        'tokens'  => ['message_id'],
    ],

    // Report a message to the moderators
    [
        'type'    => 'regex',
        'pattern' => '!^/message/(\d+)/report$!',
        'action'  => 'ReportController@create',
        'tokens'  => ['message_id'],
    ],

    // Moderation — pending message review queue
    [
        'type'    => 'exact',
        'pattern' => '/moderate/queue',
        'action'  => 'ModerationController@queue',
    ],

    // Moderation — reported-content review queue
    [
        'type'    => 'exact',
        'pattern' => '/moderate/reports',
        'action'  => 'ModerationController@reports',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/moderate/report/(\d+)/(\w+)$!',
        'action'  => 'ModerationController@report',
        'tokens'  => ['report_id', 'action'],
    ],

    // Moderation — message-level: delete, approve
    [
        'type'    => 'regex',
        'pattern' => '!^/moderate/message/(\d+)/(\w+)$!',
        'action'  => 'ModerationController@message',
        'tokens'  => ['message_id', 'action'],
    ],

    // Moderation — thread-level: delete, close, open, move
    [
        'type'    => 'regex',
        'pattern' => '!^/moderate/thread/(\d+)/(\w+)$!',
        'action'  => 'ModerationController@thread',
        'tokens'  => ['thread_id', 'action'],
    ],

    // Follow / subscribe to a thread
    [
        'type'    => 'regex',
        'pattern' => '!^/follow/(\d+)$!',
        'action'  => 'SubscriptionController@follow',
        'tokens'  => ['thread_id'],
    ],

    // User settings (must come before the profile regex)
    [
        'type'    => 'exact',
        'pattern' => '/user/settings',
        'action'  => 'UserController@settings',
    ],

    // User profile
    [
        'type'    => 'regex',
        'pattern' => '!^/user/(\d+)$!',
        'action'  => 'UserController@profile',
        'tokens'  => ['user_id'],
    ],

    // Auth
    [
        'type'    => 'exact',
        'pattern' => '/login',
        'action'  => 'AuthController@login',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/logout',
        'action'  => 'AuthController@logout',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/register',
        'action'  => 'AuthController@register',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/forgot-password',
        'action'  => 'AuthController@forgotPassword',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/reset-password',
        'action'  => 'AuthController@resetPassword',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/confirm-email',
        'action'  => 'AuthController@confirmEmail',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/resend-confirmation',
        'action'  => 'AuthController@resendConfirmation',
    ],

    // Private messages — specific routes before the inbox catch-all
    [
        'type'    => 'exact',
        'pattern' => '/pm/buddies',
        'action'  => 'PmController@buddyList',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/buddies/add/(\d+)$!',
        'action'  => 'PmController@addBuddy',
        'tokens'  => ['buddy_user_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/buddies/remove/(\d+)$!',
        'action'  => 'PmController@removeBuddy',
        'tokens'  => ['buddy_user_id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/pm/outbox',
        'action'  => 'PmController@outbox',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/pm/folders',
        'action'  => 'PmController@folders',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/folders/(\d+)/delete$!',
        'action'  => 'PmController@deleteFolder',
        'tokens'  => ['pm_folder_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/folder/(\d+)$!',
        'action'  => 'PmController@folder',
        'tokens'  => ['pm_folder_id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/pm/compose',
        'action'  => 'PmController@compose',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/compose/(\d+)$!',
        'action'  => 'PmController@compose',
        'tokens'  => ['to_user_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/read/(\d+)$!',
        'action'  => 'PmController@read',
        'tokens'  => ['pm_xref_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/delete/(\d+)$!',
        'action'  => 'PmController@delete',
        'tokens'  => ['pm_xref_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/pm/move/(\d+)$!',
        'action'  => 'PmController@move',
        'tokens'  => ['pm_xref_id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/pm',
        'action'  => 'PmController@inbox',
    ],

    // Search
    [
        'type'    => 'exact',
        'pattern' => '/search',
        'action'  => 'SearchController@index',
    ],

    // Admin
    [
        'type'    => 'exact',
        'pattern' => '/admin/login',
        'action'  => 'Admin\LoginController@login',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/logout',
        'action'  => 'Admin\LoginController@logout',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/forums',
        'action'  => 'Admin\ForumController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/forums/create',
        'action'  => 'Admin\ForumController@create',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/forums/(\d+)/edit$!',
        'action'  => 'Admin\ForumController@edit',
        'tokens'  => ['forum_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/forums/(\d+)/delete$!',
        'action'  => 'Admin\ForumController@delete',
        'tokens'  => ['forum_id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/users',
        'action'  => 'Admin\UserController@index',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/users/(\d+)/edit$!',
        'action'  => 'Admin\UserController@edit',
        'tokens'  => ['user_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/users/(\d+)/impersonate$!',
        'action'  => 'Admin\UserController@impersonate',
        'tokens'  => ['user_id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/stop-impersonating',
        'action'  => 'Admin\UserController@stopImpersonate',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/settings',
        'action'  => 'Admin\SettingsController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/announcements',
        'action'  => 'Admin\AnnouncementsController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/bans',
        'action'  => 'Admin\BanController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/bans/create',
        'action'  => 'Admin\BanController@create',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/bans/(\d+)/edit$!',
        'action'  => 'Admin\BanController@edit',
        'tokens'  => ['ban_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/bans/(\d+)/delete$!',
        'action'  => 'Admin\BanController@delete',
        'tokens'  => ['ban_id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/audit-log',
        'action'  => 'Admin\AuditLogController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/groups',
        'action'  => 'Admin\GroupController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/groups/create',
        'action'  => 'Admin\GroupController@create',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/groups/(\d+)/edit$!',
        'action'  => 'Admin\GroupController@edit',
        'tokens'  => ['group_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/groups/(\d+)/delete$!',
        'action'  => 'Admin\GroupController@delete',
        'tokens'  => ['group_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/groups/(\d+)/members/add$!',
        'action'  => 'Admin\GroupController@addMember',
        'tokens'  => ['group_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/groups/(\d+)/members/(\d+)/remove$!',
        'action'  => 'Admin\GroupController@removeMember',
        'tokens'  => ['group_id', 'user_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/groups/(\d+)/members/(\d+)/status$!',
        'action'  => 'Admin\GroupController@setMemberStatus',
        'tokens'  => ['group_id', 'user_id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/groups/(\d+)/permissions$!',
        'action'  => 'Admin\GroupController@savePermissions',
        'tokens'  => ['group_id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/modules',
        'action'  => 'Admin\ModulesController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/custom-fields',
        'action'  => 'Admin\CustomFieldController@index',
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin/custom-fields/create',
        'action'  => 'Admin\CustomFieldController@create',
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/custom-fields/(\d+)/edit$!',
        'action'  => 'Admin\CustomFieldController@edit',
        'tokens'  => ['id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/custom-fields/(\d+)/delete$!',
        'action'  => 'Admin\CustomFieldController@delete',
        'tokens'  => ['id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/custom-fields/(\d+)/restore$!',
        'action'  => 'Admin\CustomFieldController@restore',
        'tokens'  => ['id'],
    ],
    [
        'type'    => 'regex',
        'pattern' => '!^/admin/custom-fields/(\d+)/purge$!',
        'action'  => 'Admin\CustomFieldController@purge',
        'tokens'  => ['id'],
    ],
    [
        'type'    => 'exact',
        'pattern' => '/admin',
        'action'  => 'Admin\DashboardController@index',
    ],
];
