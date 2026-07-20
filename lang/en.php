<?php
declare(strict_types=1);

/**
 * English language strings for Phorum.
 *
 * Keys use dot-notation namespaces (e.g. 'nav.search').
 * Dynamic values use {placeholder} syntax in the string value;
 * pass an array of replacements as the second argument to trans().
 *
 * To create a translation, copy this file to lang/<locale>.php and
 * replace the values. Keys not present in a locale file fall back to
 * the English value defined here.
 */
return [

    // Metadata (not rendered as UI text)
    '_name' => 'English',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.forum_index'      => 'Forum Index',
    'nav.search'           => 'Search',
    'nav.messages'         => 'Messages',
    'nav.settings'         => 'Settings',
    'nav.log_out'          => 'Log Out',
    'nav.log_in'           => 'Log In',
    'nav.register'         => 'Register',
    'nav.powered_by'       => 'Powered by Phorum',
    'nav.skip_to_content'  => 'Skip to main content',
    'nav.breadcrumb'       => 'Breadcrumb',
    'nav.primary'          => 'Primary',
    'nav.menu'             => 'Menu',
    'pagination.nav_label' => 'Pagination',

    // -------------------------------------------------------------------------
    // Forum list (index page)
    // -------------------------------------------------------------------------
    'forum_list.no_forums'   => 'No forums have been created yet.',
    'forum_list.col_forum'   => 'Forum',
    'forum_list.col_posts'   => 'Posts',
    'forum_list.col_threads' => 'Threads',
    'forum_list.col_last'    => 'Last Post',

    // -------------------------------------------------------------------------
    // Forum (thread listing)
    // -------------------------------------------------------------------------
    'forum.new_thread'       => 'New Thread',
    'forum.no_threads'       => 'No threads yet.',
    'forum.start_one'        => 'Start one.',
    'forum.col_subject'      => 'Subject',
    'forum.col_author'       => 'Author',
    'forum.col_replies'      => 'Replies',
    'forum.col_posts'        => 'Posts',
    'forum.col_last_post'    => 'Last Post',
    'forum.sticky'           => 'Sticky',
    'forum.closed'           => 'Closed',
    'forum.by'               => 'by',
    'forum.new'              => 'new',
    'forum.mark_read'        => 'Mark All Read',
    'forum.feed_link'        => 'RSS',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Reply',
    'thread.follow'          => 'Follow',
    'thread.following'       => 'Following',
    'thread.reopen'          => 'Reopen',
    'thread.close'           => 'Close',
    'thread.sticky'          => 'Make Sticky',
    'thread.unsticky'        => 'Remove Sticky',
    'thread.move'            => 'Move',
    'thread.merge'           => 'Merge',
    'thread.delete'          => 'Delete Thread',

    // -------------------------------------------------------------------------
    // Individual message
    // -------------------------------------------------------------------------
    'message.awaiting_approval' => 'Awaiting Approval',
    'message.reply'             => 'Reply',
    'message.edit'              => 'Edit',
    'message.edit_title'        => 'Edit Message',
    'message.save_edit'         => 'Save Changes',
    'message.edited_note'       => 'Edited',
    'message.changes'           => 'Changes',
    'message.changes_title'     => 'Edit History',
    'message.changes_edit_n'    => 'Edit #{n}',
    'message.changes_by'        => 'by',
    'message.changes_subject'   => 'Subject',
    'message.changes_body'      => 'Message',
    'message.changes_back'      => 'Back to thread',
    'message.approve'           => 'Approve',
    'message.delete'            => 'Delete',
    'message.report'            => 'Report',
    'message.registered'        => 'Registered',
    'message.posts'             => 'Posts',

    // -------------------------------------------------------------------------
    // Post / reply form
    // -------------------------------------------------------------------------
    'post.new_thread'        => 'New Thread',
    'post.reply_to'          => 'Reply to {subject}',
    'post.reply'             => 'Reply',
    'post.subject'           => 'Subject',
    'post.body'              => 'Message',
    'post.submit_thread'     => 'Post Thread',
    'post.submit_reply'      => 'Post Reply',
    'post.cancel'            => 'Cancel',
    'post.preview'           => 'Preview',
    'post.error_subject_required' => 'Subject is required.',
    'post.error_subject_length'   => 'Subject must be 255 characters or fewer.',
    'post.error_body_required'    => 'Message body is required.',
    'post.error_flood_wait'       => 'Please wait {seconds} more second(s) before posting again.',
    'post.error_posting_blocked'  => 'Posting is not allowed from your account.',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    'auth.login_title'       => 'Log In',
    'auth.username'          => 'Username',
    'auth.password'          => 'Password',
    'auth.remember_me'       => 'Remember me',
    'auth.login_submit'      => 'Log In',
    'auth.create_account'    => 'Create an account',
    'auth.register_title'    => 'Create an Account',
    'auth.email'             => 'Email Address',
    'auth.password_hint'     => 'at least 6 characters',
    'auth.confirm_password'  => 'Confirm Password',
    'auth.register_submit'   => 'Create Account',
    'auth.have_account'       => 'Already have an account?',
    'auth.login_link'         => 'Log in.',
    'auth.forgot_password'    => 'Forgot your password?',
    'auth.forgot_title'       => 'Reset Your Password',
    'auth.forgot_email_label' => 'Email Address',
    'auth.forgot_submit'      => 'Send Reset Link',
    'auth.forgot_sent'        => 'If that email address is registered, a reset link has been sent. Check your inbox.',
    'auth.reset_title'        => 'Choose a New Password',
    'auth.reset_new_password' => 'New Password',
    'auth.reset_confirm'      => 'Confirm New Password',
    'auth.reset_submit'       => 'Set New Password',
    'auth.reset_invalid'      => 'This password reset link is invalid or has expired. Please request a new one.',
    'auth.reset_success'      => 'Your password has been updated. You are now logged in.',
    'auth.confirm_pending_title'  => 'Check Your Email',
    'auth.confirm_pending_body'   => 'We sent a confirmation link to {email}. Click it to activate your account.',
    'auth.confirm_pending_resend' => 'Resend confirmation email',
    'auth.confirm_invalid'        => 'This confirmation link is invalid or has expired.',
    'auth.resend_title'           => 'Resend Confirmation Email',
    'auth.resend_email_label'     => 'Email Address',
    'auth.resend_submit'          => 'Resend',
    'auth.resend_sent'            => 'If that address has a pending confirmation, a new link has been sent. Check your inbox.',
    'auth.pending_approval_title' => 'Awaiting Moderator Approval',
    'auth.pending_approval_body'  => 'Thanks! Your account is now waiting for a moderator to review and approve it. You will be able to log in once that happens.',
    'auth.error_missing_credentials'  => 'Please enter your username and password.',
    'auth.error_invalid_credentials'  => 'Invalid username or password.',
    'auth.error_registration_blocked' => 'Registration is not allowed from your account.',
    'auth.error_invalid_email'        => 'Please enter a valid email address.',
    'auth.error_password_min_length'  => 'Password must be at least 6 characters.',
    'auth.error_passwords_mismatch'   => 'Passwords do not match.',
    'auth.error_username_required'    => 'Username is required.',
    'auth.error_username_length'      => 'Username must be between 2 and 50 characters.',
    'auth.error_email_required'       => 'A valid email address is required.',
    'auth.error_username_taken'       => 'That username is already taken.',

    // -------------------------------------------------------------------------
    // OAuth login (mods/oauth)
    // -------------------------------------------------------------------------
    'oauth.button_google' => 'Continue with Google',
    'oauth.button_github' => 'Continue with GitHub',
    'oauth.error_provider_error'        => 'Login was cancelled or the provider returned an error. Please try again.',
    'oauth.error_state_mismatch'        => 'Your login session expired or is invalid. Please try again.',
    'oauth.error_token_exchange_failed' => 'We could not complete login with that provider. Please try again.',
    'oauth.error_email_not_verified'    => 'Your email address is not verified with that provider, so we cannot sign you in. Please verify your email with the provider and try again.',
    'oauth.error_login_failed'          => 'Something went wrong signing you in. Please try again.',
    'oauth.error_account_inactive'      => 'Your account is not active yet. Check your email for a confirmation link.',
    'oauth.error_not_configured'        => 'That login option is not currently available.',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'       => 'Username',
    'profile.name'           => 'Name',
    'profile.email'          => 'Email',
    'profile.joined'         => 'Joined',
    'profile.posts'          => 'Posts',
    'profile.last_active'    => 'Last Active',
    'profile.signature'      => 'Signature',
    'profile.recent_posts'   => 'Recent Posts',
    'profile.col_subject'    => 'Subject',
    'profile.col_date'       => 'Date',
    'profile.edit_settings'  => 'Edit Settings',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    'settings.title'             => 'Account Settings',
    'settings.saved'             => 'Your settings have been saved.',
    'settings.identity'          => 'Identity',
    'settings.display_name'      => 'Display Name',
    'settings.email'             => 'Email Address',
    'settings.hide_email'        => 'Hide my email address from my profile',
    'settings.password_section'  => 'Password',
    'settings.password_hint'     => 'Leave blank to keep your current password.',
    'settings.new_password'      => 'New Password',
    'settings.confirm_password'  => 'Confirm New Password',
    'settings.signature_section' => 'Signature',
    'settings.signature_text'    => 'Signature Text',
    'settings.show_signature'    => 'Show signature on my posts',
    'settings.preferences'       => 'Preferences',
    'settings.threaded_read'     => 'Use threaded view when reading threads',
    'settings.email_notify'          => 'When I post a new thread or reply',
    'settings.email_notify_none'     => "Don't follow automatically",
    'settings.email_notify_bookmark' => 'Follow silently (no emails)',
    'settings.email_notify_message'  => 'Follow and email me on replies',
    'settings.pm_email_notify'   => 'Email me when I receive a private message',
    'settings.tz_offset'         => 'Timezone Offset (hours, -12 to +14; -99 = server time)',
    'settings.save'              => 'Save Settings',
    'settings.cancel'            => 'Cancel',
    'settings.avatar_section'    => 'Avatar',
    'settings.avatar_current'    => 'Current avatar',
    'settings.avatar_upload'     => 'Upload new avatar',
    'settings.avatar_hint'       => 'JPG, PNG, GIF, or WebP. Maximum 100 KB.',
    'settings.avatar_delete'     => 'Remove current avatar',
    'settings.error_display_name_required' => 'Display name is required.',
    'settings.error_display_name_length'   => 'Display name must be 50 characters or fewer.',
    'settings.error_email_required'        => 'A valid email address is required.',
    'settings.error_email_taken'           => 'That email address is already in use by another account.',
    'settings.error_password_min_length'   => 'New password must be at least 6 characters.',
    'settings.error_passwords_mismatch'    => 'Passwords do not match.',
    'settings.error_tz_offset'             => 'Timezone offset must be between -12 and +14, or -99 for server time.',
    'settings.error_email_notify'          => 'Please choose a valid follow-on-post option.',

    // -------------------------------------------------------------------------
    // Forced password change
    // -------------------------------------------------------------------------
    'force_password_change.title'     => 'Change Your Password',
    'force_password_change.message'   => 'An administrator requires you to set a new password before continuing.',
    'force_password_change.new_password'     => 'New Password',
    'force_password_change.confirm_password' => 'Confirm New Password',
    'force_password_change.save'      => 'Set Password',
    'force_password_change.error_password_min_length' => 'New password must be at least 6 characters.',
    'force_password_change.error_passwords_mismatch'  => 'Passwords do not match.',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    'pm.private_messages'    => 'Private Messages',
    'pm.folders'             => 'Folders',
    'pm.inbox'               => 'Inbox',
    'pm.outbox'              => 'Outbox',
    'pm.compose'             => 'Compose',
    'pm.manage_folders'      => 'Manage Folders',
    'pm.no_messages'         => 'No messages.',
    'pm.col_subject'         => 'Subject',
    'pm.col_from'            => 'From',
    'pm.col_to'              => 'To',
    'pm.col_date'            => 'Date',
    'pm.delete'              => 'Delete',
    'pm.compose_title'       => 'Compose Message',
    'pm.to_label'            => 'To (username)',
    'pm.subject'             => 'Subject',
    'pm.body'                => 'Message',
    'pm.send'                => 'Send',
    'pm.cancel'              => 'Cancel',
    'pm.reply'               => 'Reply',
    'pm.back_to_inbox'       => 'Back to Inbox',
    'pm.move_to_folder'      => 'Move to folder…',
    'pm.move'                => 'Move',
    'pm.delete_title'        => 'Delete Private Message',
    'pm.delete_confirm'      => 'Delete "{subject}" from {author}?',
    'pm.create_folder_title' => 'Create Folder',
    'pm.folder_name'         => 'Folder Name',
    'pm.create'              => 'Create',
    'pm.buddy_list'          => 'Buddy List',
    'pm.no_buddies'          => 'You have no buddies yet.',
    'pm.add_buddy'           => 'Add Buddy',
    'pm.remove_buddy'        => 'Remove Buddy',
    'pm.mutual'              => 'Mutual',
    'pm.col_buddy'           => 'User',
    'pm.col_mutual'          => 'Mutual',
    'pm.col_last_active'     => 'Last Active',
    'pm.error_recipient_required'   => 'Recipient is required.',
    'pm.error_user_not_found'       => 'User "{username}" not found.',
    'pm.error_subject_required'     => 'Subject is required.',
    'pm.error_body_required'        => 'Message body is required.',
    'pm.error_folder_name_required' => 'Folder name is required.',
    'pm.error_folder_name_length'   => 'Folder name must be 60 characters or fewer.',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------
    'sub.title'              => 'Follow Thread',
    'sub.following_email'    => 'You are currently following this thread and will receive email notifications for new replies.',
    'sub.bookmarked'         => 'You have bookmarked this thread (no email notifications).',
    'sub.not_following'      => 'You are not following this thread.',
    'sub.follow_email'       => 'Follow & Email me on new replies',
    'sub.bookmark'           => 'Bookmark (no emails)',
    'sub.unfollow'           => 'Unfollow',
    'sub.back_to_thread'     => 'Back to thread',
    'sub.follow'             => 'Follow',
    'sub.confirm_title'      => 'Confirm Action',
    'sub.confirm_remove'     => 'Are you sure you want to unsubscribe from this thread?',
    'sub.confirm_bookmark'   => 'Switch your subscription to a bookmark (no email notifications)?',
    'sub.confirm_yes'        => 'Yes, confirm',
    'sub.confirm_cancel'     => 'Cancel',

    // -------------------------------------------------------------------------
    // Moderation
    // -------------------------------------------------------------------------
    'mod.delete_thread'            => 'Delete Thread',
    'mod.delete_message'           => 'Delete Message',
    'mod.approve_message'          => 'Approve Message',
    'mod.close_thread'             => 'Close Thread',
    'mod.reopen_thread'            => 'Reopen Thread',
    'mod.delete_thread_confirm'    => 'Are you sure you want to permanently delete the thread "{subject}" and all its replies? This cannot be undone.',
    'mod.delete_message_confirm'   => 'Are you sure you want to delete this message by {author}? Its replies will be re-parented to the previous message in the thread.',
    'mod.approve_confirm'          => 'Approve the following message by {author} so it becomes visible to all readers?',
    'mod.close_confirm'            => 'Close the thread "{subject}"? No new replies will be allowed once the thread is closed.',
    'mod.open_confirm'             => 'Reopen the thread "{subject}" so members can post new replies again?',
    'mod.sticky_thread'            => 'Make Thread Sticky',
    'mod.unsticky_thread'          => 'Remove Sticky Thread',
    'mod.sticky_confirm'           => 'Make "{subject}" a sticky thread? It will be pinned to the top of the forum listing.',
    'mod.unsticky_confirm'         => 'Remove the sticky pin from "{subject}"? It will return to normal sort order.',
    'mod.yes_delete'               => 'Yes, Delete',
    'mod.approve'                  => 'Approve',
    'mod.close'                    => 'Close Thread',
    'mod.reopen'                   => 'Reopen Thread',
    'mod.sticky'                   => 'Make Sticky',
    'mod.unsticky'                 => 'Remove Sticky',
    'mod.cancel'                   => 'Cancel',
    'mod.move_title'               => 'Move Thread',
    'mod.move_prompt'              => 'Move "{subject}" to a different forum:',
    'mod.destination'              => 'Destination forum',
    'mod.choose_forum'             => '— choose a forum —',
    'mod.move_submit'              => 'Move Thread',
    'mod.merge_title'               => 'Merge Thread',
    'mod.merge_prompt'              => 'Merge "{subject}" into another thread. The merged thread\'s posts will be appended to the target thread, and this thread\'s subscriptions will not be preserved.',
    'mod.merge_target'              => 'Target thread ID',
    'mod.merge_target_hint'         => 'The numeric ID of the thread to merge into (visible in its URL).',
    'mod.merge_submit'              => 'Merge Thread',
    'mod.merge_error_not_found'      => 'That thread ID was not found.',
    'mod.merge_error_same_thread'    => 'Choose a different thread to merge into.',
    'mod.merge_error_failed'         => 'Unable to merge into that thread.',
    'mod.moderate'                 => 'Moderate',
    'mod.queue'                    => 'Review Queue',
    'mod.queue_title'              => 'Pending Message Queue',
    'mod.queue_empty'              => 'No messages are awaiting approval.',
    'mod.queue_forum'              => 'Forum',
    'mod.queue_posted'             => 'Posted',
    'mod.reports_title'            => 'Reported Content',
    'mod.reports_empty'            => 'No open reports.',
    'mod.reports_message_missing'  => '(reported message no longer available)',
    'mod.reports_reported'         => 'reported',
    'mod.reports_resolve'          => 'Resolve',
    'mod.reports_dismiss'          => 'Dismiss',
    'mod.reports_view'             => 'View in thread',
    'mod.users_queue'              => 'Pending Users',
    'mod.users_queue_title'        => 'Pending User Approvals',
    'mod.users_queue_empty'        => 'No accounts are awaiting moderator approval.',
    'mod.users_queue_username'     => 'Username',
    'mod.users_queue_email'        => 'Email',
    'mod.users_queue_ip'           => 'IP Address',
    'mod.users_queue_registered'   => 'Registered',
    'mod.users_queue_approve'      => 'Approve',
    'mod.users_queue_reject'       => 'Reject',
    'report.title'                 => 'Report Message',
    'report.intro'                 => 'Report this message by {author} to the moderators?',
    'report.reason_label'          => 'Reason (optional)',
    'report.submit'                => 'Submit Report',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    'search.title'           => 'Search',
    'search.messages_label'  => 'Search messages',
    'search.author'          => 'Author',
    'search.match_type'      => 'Match type',
    'search.all_words'       => 'All words',
    'search.any_word'        => 'Any word',
    'search.exact_phrase'    => 'Exact phrase',
    'search.posted_within'   => 'Posted within',
    'search.last_30'         => 'Last 30 days',
    'search.last_90'         => 'Last 90 days',
    'search.last_year'       => 'Last year',
    'search.any_time'        => 'Any time',
    'search.threads_only'    => 'Thread starters only',
    'search.forums_label'    => 'Forums',
    'search.all_forums'      => 'All forums',
    'search.submit'          => 'Search',
    'search.no_results'      => 'No results found.',
    'search.showing'         => 'Showing',
    'search.of'              => 'of',
    'search.result'          => 'result',
    'search.results'         => 'results',
    'search.col_subject'     => 'Subject',
    'search.col_author'      => 'Author',
    'search.col_forum'       => 'Forum',
    'search.col_date'        => 'Date',

    // -------------------------------------------------------------------------
    // Install
    // -------------------------------------------------------------------------
    'install.page_title'               => 'Phorum Installer',
    'install.requirements_heading'     => 'Requirements',
    'install.requirement_failed'       => 'failed',
    'install.fix_requirements'         => 'Fix the requirements above before continuing.',
    'install.fix_requirements_hint_1'  => 'Make sure',
    'install.fix_requirements_hint_and' => 'and',
    'install.fix_requirements_hint_2'  => 'exist (copied from the .example files) and that the database credentials are correct.',
    'install.errors_heading'           => 'Please fix the following',
    'install.setup_heading'            => 'Site & Admin Setup',
    'install.site_name_label'          => 'Site Name',
    'install.admin_account_heading'    => 'Admin Account',
    'install.username_label'           => 'Username',
    'install.email_label'              => 'Email',
    'install.password_label'           => 'Password (min 8 chars)',
    'install.confirm_password_label'   => 'Confirm Password',
    'install.submit'                   => 'Install Phorum',
    'install.complete_page_title'      => 'Installation Complete — Phorum',
    'install.complete_heading'         => 'Installation Complete',
    'install.complete_message'         => 'The database schema has been created and your admin account is ready.',
    'install.go_to_forum'              => 'Go to Forum',
    'install.admin_panel'              => 'Admin Panel',
    'install.error_site_name_required'  => 'Site name is required.',
    'install.error_username_required'   => 'Admin username is required.',
    'install.error_username_format'     => 'Username must be 3–50 characters (letters, numbers, _ . - only).',
    'install.error_email_required'      => 'A valid admin email address is required.',
    'install.error_password_min_length' => 'Admin password must be at least 8 characters.',
    'install.error_passwords_mismatch'  => 'Passwords do not match.',
    'install.error_failed'              => 'Installation failed: {message}',

    // -------------------------------------------------------------------------
    // Upgrade (existing Phorum 6 database → Phorum 10)
    // -------------------------------------------------------------------------
    'upgrade.page_title'          => 'Phorum Upgrade',
    'upgrade.detected_heading'    => 'Existing Phorum 6 database detected',
    'upgrade.detected_message'    => 'This database was created by Phorum 6. Phorum 10 is schema-compatible with Phorum 6 — no existing data will be changed, deleted, or converted.',
    'upgrade.up_to_date'          => 'No schema changes are needed — this database is already up to date.',
    'upgrade.new_tables_heading'  => 'The following new tables will be added:',
    'upgrade.new_patches_heading' => 'The following schema updates will be applied:',
    'upgrade.submit'              => 'Continue',
    'upgrade.complete_page_title' => 'Upgrade Complete — Phorum',
    'upgrade.complete_heading'    => 'Upgrade Complete',
    'upgrade.complete_message'    => 'Your Phorum 6 database is now ready to run on Phorum 10.',
    'upgrade.go_to_forum'         => 'Go to Forum',
    'upgrade.admin_panel'         => 'Admin Panel',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'error.404_title'        => 'Page Not Found',
    'error.404_message'      => 'The page you requested does not exist.',
    'error.404_return'       => 'Return to the forum index.',
    'error.403_title'        => 'Access Denied',
    'error.403_message'      => 'You do not have permission to access this forum.',
    'error.403_login'        => 'Log in',
    'error.403_login_hint'   => 'to access forums that require registration.',
    'error.403_return'       => 'Return to Forum Index',
    'error.disabled_title'    => 'Site Unavailable',
    'error.disabled_message'  => 'This site is temporarily disabled. Please check back later.',
    'error.admin_only_title'   => 'Site Unavailable',
    'error.admin_only_message' => 'This site is temporarily closed for maintenance. Please check back later.',
    'error.read_only_title'    => 'Read Only',
    'error.read_only_message'  => 'This site is currently read only. Posting and logging in are temporarily disabled.',
    'banner.read_only'         => 'This site is currently read only — posting and logging in are temporarily disabled.',

    // -------------------------------------------------------------------------
    // Announcements
    // -------------------------------------------------------------------------
    'announcements.heading' => 'Announcements',

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------
    'attachment.label'      => 'Attachments',
    'attachment.add'        => 'Add files',
    'attachment.existing'   => 'Existing attachments',
    'attachment.remove'     => 'Remove',
    'attachment.hint_count' => 'Up to {n} file(s).',
    'attachment.hint_size'  => 'Max {size} per file.',
    'attachment.error_uploads_disabled' => 'File uploads are currently disabled.',
    'attachment.lightbox_close' => 'Close',
    'attachment.play_video' => 'Play video',
];
