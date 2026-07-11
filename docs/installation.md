# Phorum Installation Guide

## Prerequisites

- **PHP 8.3 or later** with the following extensions enabled:
  - `pdo`, `pdo_mysql`
  - `mbstring`
  - `json`
  - `fileinfo` (avatar uploads)
- **MySQL 8.0+ or MariaDB 10.6+**
- **Composer** (v2 recommended)
- A web server: **Apache 2.4+** with `mod_rewrite`, or **Nginx** with PHP-FPM

The web installer checks all PHP requirements on first load and will report any missing extensions.

---

## 1. Download Phorum

Clone the repository or extract a release archive to a directory on your server.
The directory does **not** need to be inside your web root — only the `public/`
subdirectory is exposed to the web.

```
/var/www/phorum/          ← project root
├── public/               ← document root (expose this to the web)
├── etc/
├── src/
├── templates/
├── themes/
└── ...
```

---

## 2. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

---

## 3. Configure the Application

### 3a. Database credentials — `etc/config.ini`

```bash
cp etc/config.ini.example etc/config.ini
```

Edit `etc/config.ini`:

```ini
[db.phorum]
db.phorum.type   = mysql
db.phorum.server = 127.0.0.1
db.phorum.user   = phorum_user
db.phorum.pass   = your_password
db.phorum.db     = phorum
```

The section key (`phorum` in `[db.phorum]`) must match the `db_name` value in
`etc/phorum.php` (see below).

### 3b. Application settings — `etc/phorum.php`

```bash
cp etc/phorum.example.php etc/phorum.php
```

Edit `etc/phorum.php`:

```php
return [
    'site_name'      => 'My Forum',
    'db_name'        => 'phorum',   // must match the [db.X] key in etc/config.ini
    'db_prefix'      => 'phorum',   // table prefix: phorum_messages, phorum_users, …
    'base_url'       => 'https://example.com',   // used in notification emails
    'session_secure' => true,       // set true on HTTPS sites
    'admin_secret'   => 'replace-with-a-long-random-string',
    // ... other settings
];
```

Both files are excluded from version control by `.gitignore`.

---

## 4. Web Server Configuration

Phorum's document root is the `public/` subdirectory. All other directories
(`etc/`, `src/`, `templates/`, `themes/`, `vendor/`, etc.) must **not** be
directly accessible from the web.

Theme assets (CSS, images) are served through PHP at `/theme/{name}/{file}`, so
the `themes/` directory at the project root never needs to be web-accessible.

### Apache

Enable `mod_rewrite` and point the virtual host's document root at `public/`.
The `public/.htaccess` file handles the URL rewriting; `AllowOverride All` (or
at minimum `AllowOverride FileInfo Options`) is required for it to take effect.

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/phorum/public

    <Directory /var/www/phorum/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

If you prefer to disable `.htaccess` files entirely (`AllowOverride None`), put
the rewrite rules directly in the virtual host:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/phorum/public

    <Directory /var/www/phorum/public>
        AllowOverride None
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>
</VirtualHost>
```

**Upgrading from Phorum 6:** If you are replacing an existing installation in
the same virtual host, make sure the document root now points to `public/`
rather than the old project root.

### Nginx

```nginx
server {
    listen 80;
    server_name example.com;

    root /var/www/phorum/public;
    index index.php;

    # Route all non-file requests through index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;  # adjust to your PHP-FPM socket
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive directories
    # (these directories live at the project root, not under public/,
    #  so this is defense-in-depth against server misconfiguration)
    location ~ ^/(etc|src|templates|themes|vendor)/ {
        deny all;
    }
}
```

> **PHP-FPM socket path** varies by distribution. Common values:
> - Debian/Ubuntu: `/run/php/php8.3-fpm.sock`
> - RHEL/Rocky: `unix:/run/php-fpm/www.sock`
> - TCP (any): `127.0.0.1:9000`

### Subfolder installs

To host Phorum under a URL prefix (e.g. `https://example.com/community/`):

1. Set `'base_path' => '/community'` in `etc/phorum.php`.
2. In Apache, use an `Alias` directive:
   ```apache
   Alias /community /var/www/phorum/public
   <Directory /var/www/phorum/public>
       AllowOverride All
       Require all granted
   </Directory>
   ```
3. In Nginx, wrap everything in a `location /community` block and pass
   `PATH_INFO` so PHP sees the full URI:
   ```nginx
   location /community {
       alias /var/www/phorum/public;
       try_files $uri $uri/ @phorum_community;
   }
   location @phorum_community {
       rewrite ^/community/(.*)$ /community/index.php?$query_string last;
   }
   location ~ ^/community/index\.php$ {
       fastcgi_pass unix:/run/php/php8.3-fpm.sock;
       fastcgi_param SCRIPT_FILENAME /var/www/phorum/public/index.php;
       fastcgi_param REQUEST_URI $request_uri;
       include fastcgi_params;
   }
   ```

---

## 5. Create the Database

Create an empty database and a dedicated user before running the installer:

```sql
CREATE DATABASE phorum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'phorum_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON phorum.* TO 'phorum_user'@'localhost';
FLUSH PRIVILEGES;
```

The credentials here must match what you put in `etc/config.ini`.

---

## 6. Run the Web Installer

Visit `https://example.com/install` in your browser. The installer will:

1. Check all PHP requirements and report any failures.
2. Prompt for a site name and initial administrator credentials.
3. Create all database tables.
4. Write an `installed` flag to the database and redirect to the forum.

The installer is automatically blocked for all subsequent requests once the
`installed` flag exists in the database.

---

## 7. Post-Install Checklist

| Task | Why |
|------|-----|
| Set `'session_secure' => true` in `etc/phorum.php` | Prevents session cookies being sent over HTTP |
| Set a unique, long `admin_secret` | Signs admin session tokens — the default placeholder is public |
| Set `'twig_cache' => true` (or a path string) | Enables compiled template caching for better performance |
| Set `'require_confirmation' => true` | Enables email confirmation on new registrations |
| Configure `mail_host`, `mail_port`, `mail_from` | Required for password resets and subscription emails |
| Point `base_url` at your real domain | Used in links inside notification emails |
| Enable HTTPS and configure your certificate | Required for `session_secure` and good practice |
| Restrict file permissions on `etc/` | `chmod 750` so the web process can read but others cannot |
