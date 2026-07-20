<?php
declare(strict_types=1);

return [
    'site_name'            => 'My Phorum',
    'template'             => 'emerald', // theme directory under public/assets/themes/
    'debug'                => false,
    'twig_cache'           => false,
    'db_name'              => 'phorum',  // must match the section key in etc/config.ini
    'db_prefix'            => 'phorum',  // table prefix: phorum_messages, phorum_users, etc.
    'base_url'             => 'https://example.com', // used in notification email links
    'base_path'            => '', // URL prefix for subfolder installs, e.g. '/community'
    'session_secure'       => false,  // set true in production (requires HTTPS)
    'require_confirmation' => false,  // set true to require email confirmation on register
    'track_edits'          => false,  // set true to record full edit history for messages

    // Admin session HMAC secret — change this to a long random string in production
    'admin_secret'         => 'change-me-to-a-long-random-string',

    // Avatar uploads — maximum file size in bytes (default 100 KB)
    'avatar_max_size' => 102400,

    // Outbound mail (leave mail_host empty to disable all email). Not
    // exposed in the admin settings panel — SMTP credentials are a secret,
    // same footing as the database password in etc/config.ini.
    'mail_host'       => '',        // SMTP hostname, e.g. 'smtp.example.com'
    'mail_port'       => 25,
    'mail_from'       => '',        // envelope From address
    'mail_username'   => '',        // leave empty for an unauthenticated relay
    'mail_password'   => '',
    'mail_encryption' => '',        // '', 'tls' (STARTTLS, typically port 587), or 'ssl' (implicit TLS, typically port 465)
];
