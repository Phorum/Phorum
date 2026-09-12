# Phorum 10 — Feature List

This is an inventory of Phorum's actual, implemented capabilities, organized by category. It exists so future contributors (human or AI) can quickly answer "does this already do X?" without re-reading the whole codebase.

It was built by surveying the code directly (controllers, services, templates, routes) rather than from a spec, so it reflects what's actually implemented — including a few flags/settings that are defined but not yet wired to real behavior (see [Known Gaps](#known-gaps--stubbed-features) at the end). Code moves faster than docs: treat this as a map for orientation, and confirm against the current source before relying on specifics like exact bit values or setting names.

---

## Content, Posting & Threads

### Posting & Editing
- **New thread / reply composer** — Start a new thread or reply to any post, gated by per-forum `ALLOW_NEW_TOPIC`/`ALLOW_REPLY` permissions and blocked on closed threads. `src/Http/Controllers/MessageController.php::post()`, `templates/message/post.html.twig`
- **Live Markdown editor (EasyMDE)** — The post/edit body textarea gets a client-side Markdown toolbar. `templates/message/post.html.twig`, `templates/message/edit.html.twig`
- **Post preview** — Renders the in-progress subject/body exactly as it will appear, without saving, for both new posts and edits.
- **Message editing with permission/time window** — Authors can edit their own posts if editing isn't forum-wide disabled (`edit_post`), the edit permission bit is set, the thread isn't closed, and (if `edit_time_limit` is set) the post is still within that window; moderators can always edit regardless of the forum-wide switch. `MessageController::canEditMessage()`, `src/Service/MessageService.php::edit()`
- **Edit history / diff view** — When `track_edits` is enabled, every edit is snapshotted; a "Changes" page shows a reverse-chronological list with a line-level HTML diff of subject/body per revision. `MessageController::changes()`, `src/Service/DiffRenderer.php`
- **Reply auto-subject prefill** — Replying pre-fills the subject as "Re: {original subject}" if left blank.
- **Post moderation queue (pre-approval)** — Forums can require moderation of all new posts, or auto-flag based on account age or karma; flagged posts show an "Awaiting approval" badge. `src/Service/MessageService.php::post()`
- **Flood control** — Non-moderators must wait a configurable number of seconds between posts. `src/Service/FloodControlService.php`
- **Ban enforcement on posting** — New posts are checked against IP, email, username, and spam-word bans before being accepted.
- **Duplicate-post detection (optional, per forum)** — When `check_duplicate` is on, posting the exact same subject+body to the same forum again within an hour is blocked. `MessageMapper::isDuplicate()`
- **Report a message** — Any logged-in user (other than the author) can report a post to moderators. `src/Http/Controllers/ReportController.php`

### Reading & Navigation
- **Threaded reply tree view** — Per-forum toggle (`threaded_read`) renders replies as a nested indented tree instead of a flat chronological list. `MessageController::thread()`/`buildTree()`
- **Flat paginated thread view** — Paginates replies (`read_length` per page), supports deep-linking to a specific message (`?msg=`) which resolves the correct page.
- **Thread list per forum** — Lists threads with subject, sticky/closed badges, reply count, unread-count badge, and last-post author/time. Sort order is per-forum (`float_to_top`): on (default) bumps a thread back to the top on every new reply (sorted by last-activity time); off keeps threads in fixed creation-time order regardless of reply activity. Stickies/announcements always rank first either way. `src/Http/Controllers/ForumController.php::show()`, `MessageMapper::findThreadsInForum()`
- **Sticky threads** — Threads can be pinned to the top of the list with a distinct badge/style.
- **Unread/"new" tracking** — Per-forum and per-thread unread counts, with highlighted "new" styling on individual unread messages and a "Mark forum read" action. `src/Service/NewflagService.php`
- **Thread subscribe/follow** — Follow a thread for reply notifications (see [Subscriptions & Notifications](#subscriptions--notifications)).
- **Forum folders / hierarchy** — Forums can be organized into folders showing a sub-tree of child forums/folders instead of a thread list. `ForumController::showFolder()`
- **View counts (per forum, off by default)** — `count_views` gates whether a thread view increments `viewcount` at all; when on, `count_views_per_thread` additionally decides whether `threadviewcount` tracks alongside it. `MessageMapper::incrementViewCounts()`
- **Signatures on posts** — A poster's signature (if enabled) renders as Markdown below their message body.

### Content Formatting
- **Markdown message formatting** — The default post format, rendered via CommonMark with autolinking, safe external-link handling (`nofollow`/`noopener`/`target=_blank`), and images capped to container width (inline style + CSS, so it also applies in feed readers). `src/Twig/PhorumExtension.php`
- **BBCode formatting (legacy)** — Renders Phorum 6.x BBCode-formatted messages (`[b] [i] [u] [s] [sub] [sup] [center] [left] [right] [hr] [color] [size] [url] [img] [email] [quote] [code] [list]`), with auto-linking and disallowed-scheme stripping. Nested `[quote]` resolves to 20 levels; deeper tags render as literal text, since resolution cost grows with the square of the nesting depth and message bodies aren't length-limited. `mods/bbcode/bbcode.php`
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
- **User avatars** — Users can upload/replace/delete a profile avatar, served at `/avatar/{user_id}`. Uploads must be a real JPG/PNG/GIF/WebP image whose contents match its extension (the image header is parsed, not trusted from the filename), and the serve route only renders recognized image types inline — anything else is returned as a download.
- **Attachment removal during edit** — Users editing their own message can remove previously-attached files.
- **S3-backed attachment storage (optional module)** — Redirects attachment/avatar storage to an S3 bucket, serving downloads via short-lived signed URLs instead of streaming through the app. `mods/s3storage/`
- **CDN base URL for attachments/avatars (optional module)** — Rewrites `/file/{id}/{filename}` and `/avatar/{user_id}` links to a configurable CDN domain instead of this site's own base path, via the `attachment_url`/`avatar_url` hooks dispatched from `PhorumExtension`. URLs are plain/unsigned with no expiry, so a cached copy stays reachable independent of the requester's forum permissions. `mods/cdn/`
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
- **Thread following/subscription** — Follow a thread with a choice of "email me on replies" or a silent bookmark, or unsubscribe. The email option is itself gated per forum (`allow_email_notify`, on by default) — when a forum has it off, only the silent bookmark is offered, and a user's own default "email me" preference silently downgrades to a bookmark for that forum. `src/Http/Controllers/SubscriptionController.php`, `src/Service/SubscriptionService.php`
- **Follow an entire forum** — Same email/bookmark/unsubscribe choice as thread-following, but scoped to every new thread and reply in a forum (`/forum/{id}/follow`, `thread = 0` under the hood). Notification emails' one-click unsubscribe/bookmark-downgrade links correctly target whichever subscription (forum-wide or the specific thread) actually matched. `SubscriptionController::followForum()`
- **Reply notification emails** — Subscribers (except the post's author) get an email on new approved posts, with unsubscribe and "keep as bookmark" one-click links.
- **Moderator notification emails** — Forum moderators can be emailed about new posts in forums they moderate (per-forum `email_moderators` toggle).
- **Unread/"new" post tracking** — Tracks which messages a user has/hasn't read per forum, with a "mark all read" action and an automatic 1000-flag-per-forum cap/pruning. `src/Service/NewflagService.php`
- **Default follow-on-post preference** — A per-user setting (`email_notify`: don't auto-follow / follow silently / follow and email me) that auto-subscribes the user to a thread the moment they start it or reply to it, unless they're already subscribed (an existing subscription is left untouched). `MessageController::applyDefaultSubscription()`, `templates/user/settings.html.twig`

### Profiles & Buddies
- **Public profile page** — Display name, real name (if set), email (unless hidden), join date, post count, last-active time (unless hidden), signature, avatar, and the user's 15 most recent posts. On your own profile, also a "Continue Browsing: {forum}" shortcut back to whichever forum you most recently viewed (tracked automatically on every forum/thread view). `src/Http/Controllers/UserController.php::profile()`
- **Buddy list ("friends")** — Add/remove other users as buddies, see mutual status, jump to composing a PM, and see last-active time. One-directional-by-default; there is **no** user-blocking/ignoring feature anywhere in the codebase. `src/Mapper/PmBuddyMapper.php`
- **Account settings** — See [Account Settings](#account-settings-1) under Authentication & Account.

### Groups
- **Browse & self-service join** — `/groups` lists your own memberships and any `open` group you're not already in; requesting to join files a Pending-Approval membership (never instant — a group moderator must approve it). Closed groups aren't offered and can't be requested into; they remain admin-invite-only. `src/Http/Controllers/GroupController.php::index()`/`join()`
- **Leave a group** — Self-service from the same page, for any status except Suspended (a suspension is a moderator decision, not something you can route around by leaving and rejoining — the existing row blocks a fresh join request). `GroupController::leave()`
- **Group moderator review panel** — A member with Moderator status on a specific group gets `/groups/{id}/moderate`: approve or reject pending requests, and suspend/reinstate/remove existing members. A group moderator can never grant or touch Moderator status itself — appointing moderators stays an admin-only action via `/admin/groups`. `GroupController::moderate()`/`setMemberStatus()`/`removeMember()`
- **Moderator actions are audit-logged** — Approve/reject/suspend/remove all write to the same admin audit log as message moderation and admin group changes.

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
- **Poster IP address shown to moderators** — Moderators (and admins) see the IP a post was made from directly on the message, gated by a per-forum toggle (`forums.display_ip_address`, on by default); no one else ever sees it. `templates/message/_post.html.twig`

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
- **Direct per-user permission override (admin)** — An individual user can be granted a custom bitmask for a specific forum, from a "Forum Permission Overrides" table on their admin edit page, overriding both group grants and forum defaults; unchecking every box for a forum removes the override entirely, falling back to group/forum permissions. `src/Http/Controllers/Admin/UserController.php::savePermissions()`, `src/Mapper/UserPermissionMapper.php`

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
- **Email-verification gating on OAuth account linking** — A provider identity is linked to an *existing* local account only when that account has itself proved control of the address (by confirming it, or by completing a password reset). Otherwise the login is refused with an explanation, because the provider only vouches for its own side: with `require_confirmation` off, anyone could register on someone else's address and be handed their account when they later signed in with Google or GitHub. Accounts created *from* a provider are verified on creation. Changing an address — by the user or an admin — clears the flag. `users.email_verified`, `mods/oauth/OauthService.php::resolveUser()`
- **OAuth error feedback** — Specific, user-readable messages for provider errors, CSRF mismatches, token failures, unverified email, or inactive accounts.
- **Admin OAuth configuration** — Enable/disable each provider independently and set client ID/secret. `/admin/oauth`

### Account Security
- **Separate admin login/session** — Independent from the front-end session: its own login form, a distinct signed cookie with a 30-minute sliding timeout, requiring the account to be active and admin-flagged. `src/Http/Controllers/Admin/LoginController.php`, `src/Core/AdminAuth.php`
- **Login & password-reset rate limiting** — Failed logins are counted per source address and per targeted account, and password-reset/resend-confirmation emails per source address, all within a rolling window (defaults 15 min / 15 per IP / 8 per account / 5 resets, all admin-configurable). Exceeding a limit refuses the attempt for the rest of the window and never sets a lasting account lock, so it can't be used to lock someone out; a successful login clears that account's counter. Both the front-end and admin login forms share the limits. Behind a proxy, set `trusted_proxies` so visitors aren't counted as one. `src/Service/LoginThrottleService.php`, `src/Core/ClientIp.php`
- **Logout requires a POST** — Logging out is a CSRF-protected form submission, so another site can't force it by navigating the visitor to `/logout` (which would also destroy their remember-me cookie). A GET to `/logout` shows a confirmation form instead of acting, so existing bookmarks keep working. The admin panel's logout works the same way. `src/Http/Controllers/AuthController.php::logout()`
- **Security response headers** — Every response carries `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` (which keeps password-reset tokens out of the Referer sent to other sites), and a Content-Security-Policy restricting `base-uri`, `form-action`, `object-src` and `frame-ancestors`. The default policy deliberately sets no `script-src`/`style-src`, because the shipped templates use inline scripts, inline event handlers and inline styles — a policy covering them would need `'unsafe-inline'` and would not stop XSS. Operators can supply their own via `content_security_policy`, and enable HSTS with `hsts_max_age` (HTTPS only). `src/Core/SecurityHeaders.php`
- **CSRF protection** — Every state-changing form includes a per-session token validated on POST. The PHP session runs with `session.use_strict_mode` (a client-invented session id is never adopted) and an HttpOnly/SameSite=Lax cookie that is also Secure when `session_secure` is set; both the session id and the token are rotated on login, logout, and admin elevation, so a fixed session can't be used to learn a victim's token. `src/Core/CsrfGuard.php`
- **Admin impersonation** — Temporarily browse the front end as a specific non-admin user via a separate signed, time-limited cookie; start/stop are audit-logged. `src/Core/Impersonation.php`
- **Redirect-target sanitization** — Post-login/redirect URLs are sanitized to prevent open-redirect attacks. `src/Core/RedirectGuard.php`

### Account Settings
- **Profile & preferences** — Display name, email, password, signature (+ show/hide), hide-email flag, threaded-vs-flat reading mode, email-notify and PM-email-notify toggles, timezone offset (+ DST checkbox), personal language and theme override (below). `src/Http/Controllers/UserController.php::settings()`
- **Timezone-aware date/time display** — Every displayed timestamp sitewide shifts by the viewing user's `tz_offset` (+1 hour when `is_dst` is checked); anonymous visitors and anyone who's left the offset at its default "use server time" sentinel see unshifted server-local time, unchanged from before. `src/Twig/PhorumExtension.php::formatDatestamp()`
- **Per-user language & theme override** — Either can be left as "site default" or pinned to any installed locale/theme; resolution order is (per page) forum-specific theme override → this personal preference → site-wide default. `App::initLang()`, `Controller::resolveTheme()`, `Phorum\Core\Lang::availableLocales()`, `Phorum\Core\Themes::available()`
- **Avatar upload/removal** — Content- and size-validated image upload or deletion, from the same settings page.
- **One-time link handling** — Password-reset and email-confirmation tokens are stored as a sha256 digest, so the database never holds a value that works as-is. A reset link is exchanged for a session-held token and the browser redirected to a clean URL, keeping the token out of browser history and out of the access log entry for the form submission. Confirming an email address activates the account but no longer creates a session, so the 48-hour link is an activation link rather than a login credential. `src/Service/AuthService.php`, `src/Http/Controllers/AuthController.php`
- **Re-authentication for account-critical changes** — Changing the password or the email address requires the current password; every other field on the settings page saves without it. Any password change (settings, forced change, or reset link) also clears the account's stored session tokens — including the year-long remember-me token — and issues a fresh session, so a stolen cookie stops working. `src/Service/AuthService.php::applyNewPassword()`

---

## Site Administration

### Dashboard & Site Status
- **Admin dashboard** — At-a-glance counts (active users, approved posts, active forums, pending posts) plus recent registrations and recent posts. `src/Http/Controllers/Admin/DashboardController.php`
- **Site-wide status control** — Normal / Read Only / Admin Only / Disabled, independent of per-forum permissions — a maintenance-mode kill switch. `src/Service/SiteStatusService.php`

### Forum & Folder Management
- **Forum/folder hierarchy** — Build a nested tree; folder-vs-forum is fixed at creation time. `src/Http/Controllers/Admin/ForumController.php`
- **Create/edit/reorder/soft-delete** — Move-up/move-down reordering; deletion deactivates rather than hard-deletes.
- **Per-forum discussion settings** — Moderation mode, moderator-email toggle, default threaded/flat mode, default page length, whether poster IPs are shown to moderators, thread-list sort behavior (`float_to_top`), view counting (`count_views`/`count_views_per_thread`), duplicate-post blocking (`check_duplicate`), whether editing is allowed at all (`edit_post`), and whether email-subscription is offered (`allow_email_notify`).
- **Per-forum attachment settings** — See [Attachments & Media](#attachments--media).
- **Per-forum theme override** — A forum can use a different installed theme than the site default.
- **Per-forum permission defaults** — `pub_perms`/`reg_perms` baseline bitmasks (see [Permissions](#permissions)).

### User & Group Management
- **User directory & search** — Paginated admin list, searchable by username/display name/email. `src/Http/Controllers/Admin/UserController.php`
- **Edit user account** — Display name, email, account status (Active/Inactive/Pending Moderator Approval/Pending Email Confirmation/Pending Both), admin flag, forced-password-change flag, direct password reset.
- **Custom profile field editing (admin)** — Renders and saves any admin-visible custom fields for that user.
- **User groups** — Create/edit/delete named groups. The `open` flag gates front-end self-service joining — see [Groups](#groups) under Community & Social. `src/Http/Controllers/Admin/GroupController.php`
- **Group membership management** — Add/remove members by username; set a membership status (Suspended/Unapproved/Approved/Moderator). Moderator status now confers real capability (the front-end group review panel — see [Groups](#groups)), but is still equivalent to Approved for *forum permission bitmask* purposes: `PermissionService`/`UserPermissionMapper::getGroupPermission()` only check `status >= 1`, so a group moderator gets no extra forum-permission bits beyond what the group's own grant gives every approved member. Appointing/demoting a Moderator remains admin-only, from this same page.
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
  - **cdn** — CDN base URL override for attachment/avatar links (see [Attachments & Media](#attachments--media)).
  - **webhooks** — Outgoing HTTP webhooks: HMAC-SHA256-signed deliveries, optional custom payload template (plain `{{ event }}` / `{{ timestamp }}` / `{{ data.<field> }}` placeholder substitution, JSON-escaped when the content type is JSON — not a template language), custom content-type, firing on `message.created`, `message.approved`, `message.deleted`, `user.registered`, `user.banned`, `user.shadow_ban_changed`, and `pm.sent` (payload deliberately excludes the PM body). Delivery is synchronous/best-effort with a short timeout and no retry queue — failures are logged, never thrown back into the triggering request. Targets must be public addresses: private, loopback, and reserved ranges (including cloud metadata endpoints) are refused both when saved and on every delivery, and redirects are not followed, so a webhook can't be used to reach the internal network. Set `webhook_allow_private_targets` in `etc/phorum.php` if a site genuinely delivers to an internal endpoint. `mods/webhooks/`

---

## Known Gaps / Stubbed Features

Flags, settings, or service methods that exist in the code but aren't yet wired to real, reachable behavior. Worth checking here before assuming something works end-to-end. This list was built from a full schema-vs-codebase field audit (every column in `db/mysql.sql` checked against actual read/write consumers) — items below have confirmed no functional consumer beyond the mapper/model declaration, distinct from fields that are deliberately dead legacy carryovers (page-cache counters, Phorum-6-era presentational relics, etc.) which aren't listed here since they need no decision.

**Reviewed 2026-07-25 and declined** — every item below was considered and deliberately not implemented (not an oversight, not a backlog). Don't re-flag these without a specific new reason to revisit.

- **`SUB_DIGEST` subscription type** — Defined as a constant but explicitly unused; no digest-email sending code exists.
- **Report resolution identity isn't displayed** — `reports.resolved_user_id`/`resolved_time` are written correctly when a report is resolved or dismissed, but aren't shown anywhere (the reports queue only ever lists open reports, so they never appear there); the same actor+timestamp is visible in the admin audit log instead, so this is low-priority duplication rather than lost data.
- **Several per-forum presentation/behavior toggles are still mapped but not wired**: `list_length_threaded` (no threaded-view-specific page length — threaded mode always renders the whole thread in one page), `threaded_list` (thread-list-level threading mode, distinct from `threaded_read`'s in-thread view), `reverse_threading` (no reverse-order threaded replies), `language` (no per-forum locale override — awkward to wire since the site locale is resolved before routing/forum-lookup happens), `inherit_id` (no settings-inheritance-from-another-forum — a materially bigger feature than the others, not a quick toggle). `float_to_top`, `check_duplicate`, `edit_post`, `allow_email_notify`, `count_views`, and `count_views_per_thread` are now wired (see Posting & Editing, Reading & Navigation, Subscriptions & Notifications, and Forum & Folder Management above) — note that `float_to_top` and `count_views` both default to *off* per the (frozen, Phorum-6-inherited) schema default, which is a real behavior change from this codebase's prior hardcoded-always-on handling of thread-list sorting and view counting; flip them on per forum (or change the default in `src/Model/Forum.php`) if the old always-on behavior is preferred going forward.
- **`messages.moderator_post`** — mapped but never set or read. Phorum 6 used it to substitute the word "Moderator" for a masked IP shown to regular users; Phorum 10's IP-to-moderator display (see Moderation Actions) only ever shows the IP to moderators/admins, so there's no masked-view case left for this flag to serve. Intentionally left inert rather than building the moderator-IP-masking-from-other-users behavior.
- **`pm_xref.reply_flag`** — always written as `0`, never read or toggled. Mirrors Phorum 6's own unfinished state (its source has a literal `PMTODO implement pm_reply_flag functionality` comment) rather than a Phorum 10 regression.
- **Thread move doesn't leave a redirect stub** — Phorum 6 left behind a `moved`-flagged stub message pointing readers to the new forum when a thread was moved; Phorum 10 relocates the same message rows in place (IDs unchanged) with no stub, so old bookmarked links should still resolve correctly — but this is a behavioral difference from Phorum 6, not merely an unused column, and hasn't been explicitly signed off as intentional.
