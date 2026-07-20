# Phorum 10 — Feature List

This is an inventory of Phorum's actual, implemented capabilities, organized by category. It exists so future contributors (human or AI) can quickly answer "does this already do X?" without re-reading the whole codebase.

It was built by surveying the code directly (controllers, services, templates, routes) rather than from a spec, so it reflects what's actually implemented — including a few flags/settings that are defined but not yet wired to real behavior (see [Known Gaps](#known-gaps--stubbed-features) at the end). Code moves faster than docs: treat this as a map for orientation, and confirm against the current source before relying on specifics like exact bit values or setting names.

---

## Content, Posting & Threads

### Posting & Editing
- **New thread / reply composer** — Start a new thread or reply to any post, gated by per-forum `ALLOW_NEW_TOPIC`/`ALLOW_REPLY` permissions and blocked on closed threads. `src/Http/Controllers/MessageController.php::post()`, `templates/message/post.html.twig`
- **Live Markdown editor (EasyMDE)** — The post/edit body textarea gets a client-side Markdown toolbar. `templates/message/post.html.twig`, `templates/message/edit.html.twig`
- **Post preview** — Renders the in-progress subject/body exactly as it will appear, without saving, for both new posts and edits.
- **Message editing with permission/time window** — Authors can edit their own posts if the edit permission bit is set, the thread isn't closed, and (if `edit_time_limit` is set) the post is still within that window; moderators can always edit. `MessageController::canEditMessage()`, `src/Service/MessageService.php::edit()`
- **Edit history / diff view** — When `track_edits` is enabled, every edit is snapshotted; a "Changes" page shows a reverse-chronological list with a line-level HTML diff of subject/body per revision. `MessageController::changes()`, `src/Service/DiffRenderer.php`
- **Reply auto-subject prefill** — Replying pre-fills the subject as "Re: {original subject}" if left blank.
- **Post moderation queue (pre-approval)** — Forums can require moderation of all new posts, or auto-flag based on account age or karma; flagged posts show an "Awaiting approval" badge. `src/Service/MessageService.php::post()`
- **Flood control** — Non-moderators must wait a configurable number of seconds between posts. `src/Service/FloodControlService.php`
- **Ban enforcement on posting** — New posts are checked against IP, email, username, and spam-word bans before being accepted.
- **Report a message** — Any logged-in user (other than the author) can report a post to moderators. `src/Http/Controllers/ReportController.php`

### Reading & Navigation
- **Threaded reply tree view** — Per-forum toggle (`threaded_read`) renders replies as a nested indented tree instead of a flat chronological list. `MessageController::thread()`/`buildTree()`
- **Flat paginated thread view** — Paginates replies (`read_length` per page), supports deep-linking to a specific message (`?msg=`) which resolves the correct page.
- **Thread list per forum** — Lists threads with subject, sticky/closed badges, reply count, unread-count badge, and last-post author/time. `src/Http/Controllers/ForumController.php::show()`
- **Sticky threads** — Threads can be pinned to the top of the list with a distinct badge/style.
- **Unread/"new" tracking** — Per-forum and per-thread unread counts, with highlighted "new" styling on individual unread messages and a "Mark forum read" action. `src/Service/NewflagService.php`
- **Thread subscribe/follow** — Follow a thread for reply notifications (see [Subscriptions & Notifications](#subscriptions--notifications)).
- **Forum folders / hierarchy** — Forums can be organized into folders showing a sub-tree of child forums/folders instead of a thread list. `ForumController::showFolder()`
- **View counts** — Each thread view increments a view counter.
- **Signatures on posts** — A poster's signature (if enabled) renders as Markdown below their message body.

### Content Formatting
- **Markdown message formatting** — The default post format, rendered via CommonMark with autolinking, safe external-link handling (`nofollow`/`noopener`/`target=_blank`), and images capped to container width (inline style + CSS, so it also applies in feed readers). `src/Twig/PhorumExtension.php`
- **BBCode formatting (legacy)** — Renders Phorum 6.x BBCode-formatted messages (`[b] [i] [u] [s] [sub] [sup] [center] [left] [right] [hr] [color] [size] [url] [img] [email] [quote] [code] [list]`), with auto-linking and disallowed-scheme stripping. `mods/bbcode/bbcode.php`
- **Plain-text fallback formatting** — Unrecognized/missing format falls back to HTML-escaped, auto-linked, `<br>`-converted text.
- **Autolinking** — Shared utility turning bare URLs/emails into clickable links, used by both BBCode rendering and the plain-text fallback. `src/Service/Autolinker.php`

---

## Attachments & Media

- **File attachments on posts** — Attach one or more files to a post/reply, subject to per-forum limits: max attachment count, max size per file, max total size, and an allowed-extension list (enforced client- and server-side). `MessageController::storeUploads()`, `src/Service/FileService.php`
- **Global upload toggle** — A site-wide `file_uploads` setting can disable attachments entirely for non-admins.
- **Image previews with stored dimensions** — Image attachments (jpeg/png/gif/webp) render as inline thumbnails, sized from metadata captured at upload time to avoid layout shift. `src/Model/FileMeta::fromImageData()`
- **Video attachment preview & lightbox playback** — Video attachments (mp4/webm) show a placeholder thumbnail; clicking opens a modal lightbox with native inline playback and HTTP Range-based seeking. `FileController::rangeResponse()`
- **Image lightbox viewer** — Clicking an image opens the same modal lightbox at full size (closable via button, backdrop click, or Escape). `templates/base.html.twig`
- **Generic file download** — Non-image/video attachments are listed as a plain download link with human-readable size.
- **Attachment download permission gating** — Viewing/downloading requires both forum read permission and the separate "view attachments" permission bit. `FileController::serve()`
- **Safe inline vs. forced-download serving** — Attachments are re-sniffed by actual byte content at serve time (not the stored MIME type); anything HTML/script/SVG-like is forced to download as `application/octet-stream`, preventing stored-content XSS. `src/Service/MimeDetector.php`
- **User avatars** — Users can upload/replace/delete a profile avatar, served at `/avatar/{user_id}`.
- **Attachment removal during edit** — Users editing their own message can remove previously-attached files.
- **S3-backed attachment storage (optional module)** — Redirects attachment/avatar storage to an S3 bucket, serving downloads via short-lived signed URLs instead of streaming through the app. `mods/s3storage/`
- **Per-forum attachment configuration (admin)** — Max attachments, max size per attachment, max total size, and allowed extensions, set per forum. `templates/admin/forums/edit.html.twig`

---

## Community & Social

### Private Messaging
- **Inbox / Outbox / custom folders** — View received and sent messages, and organize into user-created folders (deleted folders auto-move messages back to Inbox). `src/Http/Controllers/PmController.php`, `src/Service/PmService.php`
- **Compose / reply** — Send a PM to another user by username, with a Markdown preview step; replying pre-fills quoted text and subject.
- **Read/unread tracking** — Per-recipient read flag, with an unread-count badge in the site nav.
- **Delete a PM** — Removes the user's own copy (not the other party's).
- **Move a PM to a folder** — File a message into any custom folder from the read view.
- **Email notification for new PMs** — Opt-out setting (`pm_email_notify`, on by default).
- No attachment support in PMs — text/Markdown only, by design.

### Subscriptions & Notifications
- **Thread following/subscription** — Follow a thread with a choice of "email me on replies" or a silent bookmark, or unsubscribe. `src/Http/Controllers/SubscriptionController.php`, `src/Service/SubscriptionService.php`
- **Reply notification emails** — Subscribers (except the post's author) get an email on new approved posts, with unsubscribe and "keep as bookmark" one-click links.
- **Moderator notification emails** — Forum moderators can be emailed about new posts in forums they moderate (per-forum `email_moderators` toggle).
- **Unread/"new" post tracking** — Tracks which messages a user has/hasn't read per forum, with a "mark all read" action and an automatic 1000-flag-per-forum cap/pruning. `src/Service/NewflagService.php`
- **Default follow-on-post preference** — A per-user setting (`email_notify`: don't auto-follow / follow silently / follow and email me) that auto-subscribes the user to a thread the moment they start it or reply to it, unless they're already subscribed (an existing subscription is left untouched). `MessageController::applyDefaultSubscription()`, `templates/user/settings.html.twig`

### Profiles & Buddies
- **Public profile page** — Display name, real name (if set), email (unless hidden), join date, post count, last-active time (unless hidden), signature, avatar, and the user's 15 most recent posts. `src/Http/Controllers/UserController.php::profile()`
- **Buddy list ("friends")** — Add/remove other users as buddies, see mutual status, jump to composing a PM, and see last-active time. One-directional-by-default; there is **no** user-blocking/ignoring feature anywhere in the codebase. `src/Mapper/PmBuddyMapper.php`
- **Account settings** — See [Account Settings](#account-settings-1) under Authentication & Account.

---

## Moderation & Trust and Safety

### Moderation Actions
- **Pending-post review queue** — Moderators see all unapproved posts across every forum they moderate, with one-click approve/delete. `src/Http/Controllers/ModerationController.php::queue()`
- **Message approve/delete** — Approve a held post, or soft-delete any post (re-parenting replies, recalculating stats, removing from search, and deleting its attachments). `src/Service/ModerationService.php`
- **Thread delete/close/reopen** — Deleting a thread's root deletes the whole thread, including every message's attachments; threads can also be closed (blocking replies) and reopened.
- **Thread move** — Move an entire thread to a different forum (recalculates stats on both sides, updates search index and subscriptions).
- **Thread merge** — Fold one thread into another, reconciling closed-state, unread flags, stats, and search index.
- **Sticky/unsticky a thread** — Pin a thread to the top of the forum listing, or remove the pin, from the same moderation dropdown as close/move/merge. `ModerationService::stickyThread()`
- **Content reports queue** — Moderators see open user-submitted reports with the reported content and reason, and can resolve/dismiss each. `src/Http/Controllers/ReportController.php`, `ModerationController::reports()`
- **Moderator forum scoping** — Every moderation action is restricted to forums the acting user actually moderates (`ALLOW_MODERATE_MESSAGES`); merge/move targets are checked too. `ModerationController::moderatableForums()`

### Bans & Automated Enforcement
- **Pattern-based bans** — Ban by IP, username/display name, email, user ID, or spam-word phrase, scoped to all forums or one, as a plain substring or regex; these are permanent rules, not time-boxed suspensions. `src/Http/Controllers/Admin/BanController.php`, `src/Service/BanService.php`
- **Registration/login/posting ban enforcement** — Banned identities are blocked at signup, login, and post time.
- **Shadow banning** — A shadow-banned user keeps posting normally, but their content (past and future) is visible only to themselves; lifting it restores visibility and re-indexes their posts. `src/Http/Controllers/Admin/UserController.php::applyShadowBan()`
- **Karma-threshold auto-moderation** — Once a user's ratio of moderator-deleted-to-total posts crosses an admin-configured threshold (with a minimum sample size), future posts are auto-held for approval. Not a visible reputation score — purely an anti-abuse mechanic. `src/Service/MessageService.php`
- **New-account auto-moderation** — Posts from accounts younger than an admin-configured minimum age are held for approval regardless of karma.
- **Per-forum pre-moderation** — Forums can require moderator approval for every new post.

### Permissions
A single bitmask permission model underlies forum defaults, group grants, and per-user overrides, shared by every `canX()` check in `src/Service/PermissionService.php` (flags defined in `src/Service/PermissionFlags.php`):

| Bit | Flag | Controls |
|---|---|---|
| 1 | `ALLOW_READ` | View/read the forum's messages |
| 2 | `ALLOW_REPLY` | Reply to existing threads |
| 4 | `ALLOW_EDIT` | Edit their own previously-posted messages |
| 8 | `ALLOW_NEW_TOPIC` | Start new threads |
| 16 | `ALLOW_VIEW_ATTACHMENTS` | View/download message attachments |
| 32 | `ALLOW_ATTACH` | Attach files when posting |
| 64 | `ALLOW_MODERATE_MESSAGES` | Moderator rights over messages/threads in that forum |
| 128 | `ALLOW_MODERATE_USERS` | Site-wide pending-registration approval queue access, plus profile privacy bypass (see below) |

- **Resolution order**: site admin (unrestricted) → inactive user (none) → direct per-user override (`user_permissions`) → group grants (OR-combined across all the user's groups on that forum) → forum default (`reg_perms` for logged-in users, `pub_perms` for anonymous). `PermissionService::resolve()`
- **Groups with per-forum permission grants** — Named groups (with active/moderator membership status) get a permission bitmask on specific forums — the mechanism for forum-specific moderator roles without per-user overrides. `src/Http/Controllers/Admin/GroupController.php`
- **Direct per-user permission override** — An individual user can be granted a custom bitmask for a specific forum, overriding both group grants and forum defaults.

### Audit Log
- **Admin/moderator action log** — Read-only trail (last 200 shown) of moderation and admin actions — report resolve/dismiss, message approve/delete, thread delete/close/open/move/merge, ban CRUD, group CRUD/membership changes, shadow-ban toggles, and impersonation start/stop — with actor, action, object, forum, and timestamp. `src/Http/Controllers/Admin/AuditLogController.php`, `src/Mapper/ModLogMapper.php`
  - Not logged: individual per-user/per-group permission-grant edits.

### Pending-Registration Approval (front-end user moderation)
- **Pending-user approval queue** — A site-wide (not forum-scoped) queue of accounts awaiting moderator approval, reachable from the same "Moderate" dropdown as message moderation whenever a user holds `ALLOW_MODERATE_USERS` on at least one forum — approve or reject each with one click. Shows the IP address the account registered from (see below) to help spot abusive signups. `ModerationController::users()`/`userAction()`, `templates/moderation/users.html.twig`
- **Five-state account model, matching Phorum 6's schema exactly** — `users.active` now distinguishes `ACTIVE` (1), `INACTIVE` (0), `PENDING_MOD` (-1, awaiting moderator approval only), `PENDING_EMAIL` (-2, awaiting email confirmation only), and `PENDING_BOTH` (-3, awaiting both — email confirmation first, then moderator approval). No schema change — the column already supported these values. `src/Mapper/UserMapper.php`
- **Profile privacy bypass** — Site admins and `ALLOW_MODERATE_USERS` holders (on any forum) see a user's hidden email and hidden last-active time on their public profile, where other viewers see them hidden. `UserController::profile()`

---

## Authentication & Account

### Registration & Login
- **Username/password registration** — With uniqueness and format validation. `src/Http/Controllers/AuthController.php`, `src/Service/AuthService.php`
- **Registration IP capture** — The IP address a user registered from is recorded (`users.reg_ip`, a Phorum 10 addition — legacy Phorum 6 never captured this) for both password and OAuth registration, visible to admins on the user edit page and in the pending-approval queue. Not shown anywhere public-facing.
- **Registration abuse blocking** — Silently blocked if the submitter's IP, username, or email is banned.
- **Email confirmation on signup (optional)** — When enabled, new accounts start inactive until a 48-hour-expiry emailed link is clicked.
- **Moderator approval on signup (optional)** — Independent of email confirmation and can be combined with it; new accounts wait in the pending-registration queue (see [Pending-Registration Approval](#pending-registration-approval-front-end-user-moderation)) until a moderator approves or rejects them. When both are enabled, email confirmation must happen first.
- **Resend confirmation email** — Enumeration-safe (same response whether or not the address is registered).
- **Username/password login** — Supports legacy MD5 password hashes from old Phorum installs, transparently upgraded to bcrypt on successful login.
- **"Remember me" persistent login** — Long-lived (1-year) session cookie option, in addition to the normal short-term session. OAuth logins always set this.
- **Forgot / reset password** — Enumeration-safe reset-request email with a 1-hour-expiry token; successful reset auto-logs the user in.
- **Admin-forced password change** — An admin can flag an account to require a password change before the user can do anything else.
- **Pluggable authentication hook** — `user_authenticate` hook lets plugins (LDAP, SSO, etc.) override credential verification before the built-in check runs.

### OAuth / Social Login
- **"Continue with Google" / "Continue with GitHub"** — Standard OAuth2 authorization-code flow with CSRF-protecting `state` verification; buttons only appear when a provider is configured and enabled. `mods/oauth/`
- **Account linking / auto-registration** — Links to an existing account by verified email, or auto-registers (and immediately activates) a new one with a de-duplicated username derived from the provider profile.
- **OAuth error feedback** — Specific, user-readable messages for provider errors, CSRF mismatches, token failures, unverified email, or inactive accounts.
- **Admin OAuth configuration** — Enable/disable each provider independently and set client ID/secret. `/admin/oauth`

### Account Security
- **Separate admin login/session** — Independent from the front-end session: its own login form, a distinct signed cookie with a 30-minute sliding timeout, requiring the account to be active and admin-flagged. `src/Http/Controllers/Admin/LoginController.php`, `src/Core/AdminAuth.php`
- **CSRF protection** — Every state-changing form includes a per-session token validated on POST. `src/Core/CsrfGuard.php`
- **Admin impersonation** — Temporarily browse the front end as a specific non-admin user via a separate signed, time-limited cookie; start/stop are audit-logged. `src/Core/Impersonation.php`
- **Redirect-target sanitization** — Post-login/redirect URLs are sanitized to prevent open-redirect attacks. `src/Core/RedirectGuard.php`

### Account Settings
- **Profile & preferences** — Display name, email, password, signature (+ show/hide), hide-email flag, threaded-vs-flat reading mode, email-notify and PM-email-notify toggles, timezone offset. `src/Http/Controllers/UserController.php::settings()`
- **Avatar upload/removal** — Type/size-validated image upload or deletion, from the same settings page.

---

## Site Administration

### Dashboard & Site Status
- **Admin dashboard** — At-a-glance counts (active users, approved posts, active forums, pending posts) plus recent registrations and recent posts. `src/Http/Controllers/Admin/DashboardController.php`
- **Site-wide status control** — Normal / Read Only / Admin Only / Disabled, independent of per-forum permissions — a maintenance-mode kill switch. `src/Service/SiteStatusService.php`

### Forum & Folder Management
- **Forum/folder hierarchy** — Build a nested tree; folder-vs-forum is fixed at creation time. `src/Http/Controllers/Admin/ForumController.php`
- **Create/edit/reorder/soft-delete** — Move-up/move-down reordering; deletion deactivates rather than hard-deletes.
- **Per-forum discussion settings** — Moderation mode, moderator-email toggle, default threaded/flat mode, default page length.
- **Per-forum attachment settings** — See [Attachments & Media](#attachments--media).
- **Per-forum theme override** — A forum can use a different installed theme than the site default.
- **Per-forum permission defaults** — `pub_perms`/`reg_perms` baseline bitmasks (see [Permissions](#permissions)).

### User & Group Management
- **User directory & search** — Paginated admin list, searchable by username/display name/email. `src/Http/Controllers/Admin/UserController.php`
- **Edit user account** — Display name, email, account status (Active/Inactive/Pending Moderator Approval/Pending Email Confirmation/Pending Both), admin flag, forced-password-change flag, direct password reset.
- **Custom profile field editing (admin)** — Renders and saves any admin-visible custom fields for that user.
- **User groups** — Create/edit/delete named groups, each with an "open" (self-joinable) flag. `src/Http/Controllers/Admin/GroupController.php`
- **Group membership management** — Add/remove members by username, change membership status.
- **Group-based per-forum permission grants** — See [Permissions](#permissions).

### Site-Wide Settings
Configurable from `/admin/settings`: Site Name, minimum seconds between posts (flood control), edit time limit, minimum account age for auto-approval, karma threshold %, default theme, default language, Enable RSS toggle, Enable File Uploads toggle, Require Moderator Approval for New Registrations toggle (default off). `src/Http/Controllers/Admin/SettingsController.php`
- **Site Name is genuinely database-backed** — resolved once per request via `Phorum\Core\SiteSettings` (mirrors the `SiteStatus`/`FeedStatus` request-scoped cache pattern), read everywhere site name appears (page titles, email subjects/bodies, RSS/JSON-LD metadata). `etc/phorum.php`'s `site_name` is only the fallback default used until an admin sets one in the database.
- **Outbound mail (SMTP) is configured in `etc/phorum.php`, not the admin panel** — host, port, from address, username/password, and encryption (`''`/`tls`/`ssl`). Deliberately kept out of the database-backed settings: SMTP credentials are a secret on the same footing as the database password in `etc/config.ini`. Supports authenticated SMTP (Gmail, SendGrid, corporate relays, etc.) as well as unauthenticated local relays (leave `mail_username` empty). `src/Service/MailService.php`
- **Base URL is also configured in `etc/phorum.php`, not the admin panel** — kept alongside `base_path` (the URL prefix for subfolder installs), since the two must stay consistent with each other and `base_path` is resolved before any DB connection exists (at request-dispatch time in `App::run()`).

### Custom Profile Fields
- **Field schema management** — Define custom profile fields (name, max length, HTML-disabled flag, admin-visible flag), with soft-delete/restore and hard-delete (purge, including stored values). `src/Http/Controllers/Admin/CustomFieldController.php`

### Announcements
- **Site announcement banner** — Pulls from a designated source forum, with a configurable count, days-to-show window, "only unread" toggle, and which page types display it. `src/Http/Controllers/Admin/AnnouncementsController.php`

### Modules Management
- **Module enable/disable & discovery** — Scans `mods/`, shows each module's title/description from its `info.txt`, lets an admin enable/disable it and jump to its own config page; enabling a module runs its schema installer immediately. `src/Http/Controllers/Admin/ModulesController.php`

### Audit Log
See [Audit Log](#audit-log) under Moderation & Trust and Safety.

### Installation & Upgrades
- **First-run installer** — Checks server requirements (PHP 8.3+, PDO/PDO MySQL, JSON, mbstring, DB connectivity), then bootstraps the schema and first admin account; detects an existing Phorum 6 database and redirects to the upgrade path instead. `src/Http/Controllers/InstallController.php`
- **Upgrade wizard (schema self-heal)** — Adds any new tables/columns needed to bring an existing DB up to date, previews pending changes before running, and supports an admin-triggered manual re-run (`?force=1`) outside the normal auto-heal-on-version-bump flow. `src/Core/SchemaInstaller.php`, `src/Core/SchemaPatcher.php`, `src/Core/SchemaMigrator.php`

---

## Search

- **Full-text forum search** — Searches post content and thread-starter subjects across every forum the requester can read; only approved messages are indexed. `src/Http/Controllers/SearchController.php`, `src/Service/MysqlSearchService.php` (MySQL `FULLTEXT ... IN BOOLEAN MODE`)
- **Match-type control** — ALL (boolean AND), ANY (relevance-ranked OR), or exact PHRASE matching.
- **Author filter** — Narrow results to a specific author.
- **Forum scope filter** — Restrict to specific forums (intersected with the user's actual read access — can't be used to enumerate private forums).
- **Date-range filter** — Last 30/90/365 days, or any time.
- **Thread-starters-only filter** — Restrict to first posts of a thread.
- **Search-service abstraction** — `SearchServiceInterface` decouples the controller from the MySQL implementation, leaving room for a future non-MySQL backend.

---

## Syndication (Feeds) & SEO

- **Multi-format feeds** — RSS 2.0, Atom 1.0, or JSON Feed 1.1, selected by URL extension. `src/Http/Controllers/FeedController.php`, `src/Service/FeedService.php`
- **Site-wide "recent posts" feed** — Latest ~30 approved posts across every forum the viewer can read.
- **Per-forum feed** — Latest threads in one forum.
- **Per-thread feed** — Individual replies within one thread, for subscribing to a single conversation.
- **Site-wide feed toggle** — `enable_rss` setting; disabled feeds 404 rather than erroring.
- **Rendered post bodies in feeds** — Reuses the same Markdown/BBCode rendering pipeline as the web UI.
- **Structured data / SEO (schema.org JSON-LD)** — Forum index, folder, forum, and thread pages emit `CollectionPage`, `ItemList`, `DiscussionForumPosting`, `Comment`, `BreadcrumbList`, and view-count `InteractionCounter` markup for rich search-engine results (capped at 50 embedded comments per page). `src/Service/SchemaOrgService.php`

---

## Internationalization & Theming

- **Multi-locale UI translation** — 16 locales shipped (`ar, bn, de, en, es, fr, fr-CA, hi, id, nl, pt, pt-PT, ru, ur, zh-CN, zh-TW`); English is the canonical reference. `lang/*.php`
- **Three-layer locale fallback** — `en.php` → base-language file → exact-locale file; a missing key falls back through the chain, ultimately to the key itself. `src/Core/Lang.php`
- **RTL language support** — Locale files can declare `_dir => 'rtl'` (used by `ar`/`ur`).
- **Site-wide default locale setting** — Admin picks the default UI language; available locales are auto-discovered from `lang/`.
- **Six built-in themes** — `amethyst, diamond, emerald, ruby, sapphire, topaz`. `themes/`
- **Shared base stylesheet** — Only `emerald/phorum.css` is a full stylesheet; the other five `@import` it and override a handful of CSS custom properties for palette — so structural/layout fixes to Emerald apply to all themes automatically.
- **Site-wide and per-forum theme selection** — Admin sets a site default theme, with an optional per-forum override.

---

## Extensibility / Plugin System

- **Backward-compatible hook dispatcher** — A pipeline-style hook system (`HookDispatcher`): callbacks register against a named hook with a priority, and `dispatch()` pipes data through each in order; a non-null return "claims" the dispatch. Preserves the classic Phorum 6 `phorum_api_hook('hook_name', $data, ...)` calling convention via a procedural wrapper, so legacy-style module code keeps working against the PHP 8 core. `src/Hook/HookDispatcher.php`, `src/Hook/functions.php`
- **Settings-driven module enable/disable** — Active modules are controlled by an admin-editable `mods` setting, not filesystem presence; `App::initModules()` loads each enabled module's boot file and merges in its own `routes.php` if present, so a module can add pages/admin screens with zero changes to core `etc/routes.php`.
- **Module self-description convention** — Every module ships an `info.txt` with `title`, `desc`, and optional `configure` (admin URL) keys, giving the admin UI a uniform way to list installed modules.
- **Bundled modules**:
  - **bbcode** — Legacy BBCode message rendering (see [Content Formatting](#content-formatting)).
  - **oauth** — Google/GitHub social login (see [OAuth / Social Login](#oauth--social-login)).
  - **s3storage** — S3-backed attachment/avatar storage (see [Attachments & Media](#attachments--media)).
  - **webhooks** — Outgoing HTTP webhooks: HMAC-SHA256-signed deliveries, optional custom Twig payload template, custom content-type, firing on `message.created`, `message.approved`, `message.deleted`, `user.registered`, `user.banned`, `user.shadow_ban_changed`, and `pm.sent` (payload deliberately excludes the PM body). Delivery is synchronous/best-effort with a short timeout and no retry queue — failures are logged, never thrown back into the triggering request. `mods/webhooks/`

---

## Known Gaps / Stubbed Features

Flags, settings, or service methods that exist in the code but aren't yet wired to real, reachable behavior. Worth checking here before assuming something works end-to-end:

- **`SUB_DIGEST` subscription type** — Defined as a constant but explicitly unused; no digest-email sending code exists.
