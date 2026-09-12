<?php
declare(strict_types=1);

return [
    'site_name'            => 'My Phorum', // fallback only — overridden once an admin sets one in Settings
    'template'             => 'emerald', // theme directory under public/assets/themes/
    'debug'                => false,
    'twig_cache'           => false,
    'db_name'              => 'phorum',  // must match the section key in etc/config.ini
    'db_prefix'            => 'phorum',  // table prefix: phorum_messages, phorum_users, etc.
    // base_url and base_path (URL prefix for subfolder installs, e.g.
    // '/community') must stay consistent with each other, and base_path is
    // needed before any DB connection exists — both live here only, not in
    // the admin settings panel.
    'base_url'             => 'https://example.com', // used in notification email links
    'base_path'            => '',
    'session_secure'       => false,  // set true in production (requires HTTPS)

    // HTTP Strict Transport Security. 0 disables it. Only sent when
    // session_secure is also true, because HSTS cannot be withdrawn within its
    // own lifetime — a site that sends it before TLS works properly locks
    // browsers out of itself until max-age expires. Start small (e.g. 300),
    // confirm nothing breaks, then raise it (31536000 is one year).
    'hsts_max_age'           => 0,
    'hsts_include_subdomains' => false,

    // Content-Security-Policy sent with every response. Leave blank to use the
    // built-in default (see Phorum\Core\SecurityHeaders), which restricts
    // base-uri, form-action, object-src and frame-ancestors. It deliberately
    // does not set script-src/style-src: the shipped templates use inline
    // scripts, inline event handlers and inline styles, so any script-src here
    // would need 'unsafe-inline' and would not actually stop XSS. Set your own
    // policy only if you have tested it against your theme and modules.
    'content_security_policy' => '',

    // Reverse proxies / CDNs whose X-Forwarded-For header may be believed
    // when resolving a visitor's IP for rate limiting. Leave empty unless
    // this site really is behind one: the header is client-supplied, and is
    // ignored entirely unless the request arrives from an address listed
    // here. Accepts bare addresses or CIDR blocks, IPv4 and IPv6.
    // Without it, every visitor behind a proxy shares one rate-limit bucket.
    'trusted_proxies'      => [],
    'require_confirmation' => false,  // set true to require email confirmation on register
    'track_edits'          => false,  // set true to record full edit history for messages

    // Admin session HMAC secret. Signs the admin-session and impersonation
    // cookies, which carry no server-side state — so anyone who knows this
    // value can forge an admin session. Deliberately blank: Phorum refuses to
    // log an admin in until it is set to a unique random string of at least 32
    // characters. Generate one with:
    //     php -r 'echo bin2hex(random_bytes(32));'
    'admin_secret'         => '',

    // Allow the webhooks module to POST to private, loopback, and otherwise
    // reserved addresses. Off by default: webhook URLs are admin-supplied and
    // fetched by the server, so an unrestricted target turns admin access into
    // a way to reach the internal network and cloud metadata services. Turn on
    // only if this site genuinely needs to deliver to an internal endpoint.
    'webhook_allow_private_targets' => false,

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
