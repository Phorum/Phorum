-- Phorum MySQL / MariaDB schema
-- Table prefix placeholder: {PREFIX} (replaced by the installer with your db_prefix)
--
-- Differences from Phorum 5.x schema (new installs only):
--   * users.password / users.password_temp: varchar(255) instead of varchar(50) — bcrypt requires 60 chars
--   * All tables use InnoDB (MySQL 5.6+ / MariaDB 10.0+ support FULLTEXT on InnoDB)
--   * utf8mb4 throughout

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
-- Forums / folders
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_forums (
    forum_id                 int unsigned       NOT NULL AUTO_INCREMENT,
    name                     varchar(50)        NOT NULL DEFAULT '',
    active                   tinyint(1)         NOT NULL DEFAULT 0,
    description              text               NOT NULL,
    template                 varchar(50)        NOT NULL DEFAULT '',
    folder_flag              tinyint(1)         NOT NULL DEFAULT 0,
    parent_id                int unsigned       NOT NULL DEFAULT 0,
    list_length_flat         int unsigned       NOT NULL DEFAULT 0,
    list_length_threaded     int unsigned       NOT NULL DEFAULT 0,
    moderation               int unsigned       NOT NULL DEFAULT 0,
    threaded_list            tinyint(1)         NOT NULL DEFAULT 0,
    threaded_read            tinyint(1)         NOT NULL DEFAULT 0,
    float_to_top             tinyint(1)         NOT NULL DEFAULT 0,
    check_duplicate          tinyint(1)         NOT NULL DEFAULT 0,
    allow_attachment_types   varchar(100)       NOT NULL DEFAULT '',
    max_attachment_size      int unsigned       NOT NULL DEFAULT 0,
    max_totalattachment_size int unsigned       NOT NULL DEFAULT 0,
    max_attachments          int unsigned       NOT NULL DEFAULT 0,
    pub_perms                int unsigned       NOT NULL DEFAULT 0,
    reg_perms                int unsigned       NOT NULL DEFAULT 0,
    display_ip_address       tinyint(1)         NOT NULL DEFAULT 1,
    allow_email_notify       tinyint(1)         NOT NULL DEFAULT 1,
    language                 varchar(100)       NOT NULL DEFAULT 'english',
    email_moderators         tinyint(1)         NOT NULL DEFAULT 0,
    message_count            int unsigned       NOT NULL DEFAULT 0,
    sticky_count             int unsigned       NOT NULL DEFAULT 0,
    thread_count             int unsigned       NOT NULL DEFAULT 0,
    last_post_time           int unsigned       NOT NULL DEFAULT 0,
    display_order            int unsigned       NOT NULL DEFAULT 0,
    read_length              int unsigned       NOT NULL DEFAULT 0,
    vroot                    int unsigned       NOT NULL DEFAULT 0,
    forum_path               text               NOT NULL,
    count_views              tinyint(1)         NOT NULL DEFAULT 0,
    count_views_per_thread   tinyint(1)         NOT NULL DEFAULT 0,
    display_fixed            tinyint(1)         NOT NULL DEFAULT 0,
    reverse_threading        tinyint(1)         NOT NULL DEFAULT 0,
    inherit_id               int unsigned           NULL DEFAULT NULL,
    cache_version            int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (forum_id),
    KEY name (name),
    KEY folder_index (parent_id, vroot, active, folder_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Messages
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_messages (
    message_id               int unsigned       NOT NULL AUTO_INCREMENT,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    thread                   int unsigned       NOT NULL DEFAULT 0,
    parent_id                int unsigned       NOT NULL DEFAULT 0,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    author                   varchar(255)       NOT NULL DEFAULT '',
    subject                  varchar(255)       NOT NULL DEFAULT '',
    body                     text               NOT NULL,
    email                    varchar(100)       NOT NULL DEFAULT '',
    ip                       varchar(255)       NOT NULL DEFAULT '',
    status                   tinyint(4)         NOT NULL DEFAULT 2,
    msgid                    varchar(100)       NOT NULL DEFAULT '',
    modifystamp              int unsigned       NOT NULL DEFAULT 0,
    thread_count             int unsigned       NOT NULL DEFAULT 0,
    moderator_post           tinyint(1)         NOT NULL DEFAULT 0,
    sort                     tinyint(4)         NOT NULL DEFAULT 2,
    datestamp                int unsigned       NOT NULL DEFAULT 0,
    meta                     mediumtext             NULL,
    viewcount                int unsigned       NOT NULL DEFAULT 0,
    threadviewcount          int unsigned       NOT NULL DEFAULT 0,
    closed                   tinyint(1)         NOT NULL DEFAULT 0,
    recent_message_id        int unsigned       NOT NULL DEFAULT 0,
    recent_user_id           int unsigned       NOT NULL DEFAULT 0,
    recent_author            varchar(255)       NOT NULL DEFAULT '',
    moved                    tinyint(1)         NOT NULL DEFAULT 0,
    hide_period              int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (message_id),
    KEY special_threads (sort, forum_id),
    KEY last_post_time (forum_id, status, modifystamp),
    KEY dup_check (forum_id, author(50), subject(100), datestamp),
    KEY recent_user_id (recent_user_id),
    KEY user_messages (user_id, message_id),
    KEY updated_threads (status, parent_id, modifystamp),
    KEY list_page_flat (forum_id, status, parent_id, datestamp),
    KEY thread_date (thread, datestamp),
    KEY list_page_float (forum_id, status, parent_id, modifystamp),
    KEY forum_recent_messages (forum_id, status, datestamp),
    KEY recent_threads (status, parent_id, datestamp),
    KEY recent_messages (status, datestamp),
    KEY forum_thread_count (forum_id, parent_id, status, moved, message_id),
    KEY forum_message_count (forum_id, status, moved, message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Settings key/value store
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_settings (
    name                     varchar(255)       NOT NULL DEFAULT '',
    type                     enum('V','S')      NOT NULL DEFAULT 'V',
    data                     text               NOT NULL,

    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Subscriptions
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_subscribers (
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    sub_type                 tinyint(4)         NOT NULL DEFAULT 0,
    thread                   int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id, thread),
    KEY forum_id (forum_id, thread, sub_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Per-user per-forum permission overrides
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_permissions (
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    permission               int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id),
    KEY forum_id (forum_id, permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Users
-- Note: password / password_temp are varchar(255) here (Phorum 5.x used varchar(50)).
--       Upgrade migrations must ALTER these columns to store bcrypt hashes.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_users (
    user_id                  int unsigned       NOT NULL AUTO_INCREMENT,
    username                 varchar(50)        NOT NULL DEFAULT '',
    real_name                varchar(255)       NOT NULL DEFAULT '',
    display_name             varchar(255)       NOT NULL DEFAULT '',
    password                 varchar(255)       NOT NULL DEFAULT '',
    password_temp            varchar(255)       NOT NULL DEFAULT '',
    sessid_lt                varchar(64)        NOT NULL DEFAULT '',
    sessid_st                varchar(64)        NOT NULL DEFAULT '',
    sessid_st_timeout        int unsigned       NOT NULL DEFAULT 0,
    email                    varchar(100)       NOT NULL DEFAULT '',
    email_temp               varchar(110)       NOT NULL DEFAULT '',
    hide_email               tinyint(1)         NOT NULL DEFAULT 1,
    active                   tinyint(1)         NOT NULL DEFAULT 0,
    signature                text               NOT NULL,
    threaded_list            tinyint(1)         NOT NULL DEFAULT 0,
    posts                    int                NOT NULL DEFAULT 0,
    admin                    tinyint(1)         NOT NULL DEFAULT 0,
    threaded_read            tinyint(1)         NOT NULL DEFAULT 0,
    date_added               int unsigned       NOT NULL DEFAULT 0,
    date_last_active         int unsigned       NOT NULL DEFAULT 0,
    last_active_forum        int unsigned       NOT NULL DEFAULT 0,
    hide_activity            tinyint(1)         NOT NULL DEFAULT 0,
    show_signature           tinyint(1)         NOT NULL DEFAULT 0,
    email_notify             tinyint(1)         NOT NULL DEFAULT 0,
    pm_email_notify          tinyint(1)         NOT NULL DEFAULT 1,
    pm_new_count             int unsigned       NOT NULL DEFAULT 0,
    tz_offset                float(4,2)         NOT NULL DEFAULT -99.00,
    is_dst                   tinyint(1)         NOT NULL DEFAULT 0,
    user_language            varchar(100)       NOT NULL DEFAULT '',
    user_template            varchar(100)       NOT NULL DEFAULT '',
    moderation_email         tinyint(1)         NOT NULL DEFAULT 1,
    settings_data            mediumtext         NOT NULL,

    PRIMARY KEY (user_id),
    UNIQUE KEY username (username),
    KEY active (active),
    KEY sessid_st (sessid_st),
    KEY sessid_lt (sessid_lt),
    KEY activity (date_last_active, hide_activity, last_active_forum),
    KEY date_added (date_added),
    KEY email_temp (email_temp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Read/unread tracking per user
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_newflags (
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    message_id               int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id, message_id),
    KEY move (message_id, forum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Minimum unread message id (optimization table for newflags)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_min_id (
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    min_id                   int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Groups
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_groups (
    group_id                 int unsigned       NOT NULL AUTO_INCREMENT,
    name                     varchar(255)       NOT NULL DEFAULT '',
    open                     tinyint(1)         NOT NULL DEFAULT 0,

    PRIMARY KEY (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Forums <-> groups permission cross-reference
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_forum_group_xref (
    forum_group_xref_id      int unsigned       NOT NULL AUTO_INCREMENT,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    group_id                 int unsigned       NOT NULL DEFAULT 0,
    permission               int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (forum_group_xref_id),
    UNIQUE KEY forum_group (forum_id, group_id),
    KEY group_id (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Users <-> groups cross-reference
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_group_xref (
    user_group_xref_id       int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    group_id                 int unsigned       NOT NULL DEFAULT 0,
    status                   tinyint(4)         NOT NULL DEFAULT 1,

    PRIMARY KEY (user_group_xref_id),
    UNIQUE KEY user_group (user_id, group_id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- File attachments
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_files (
    file_id                  int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    filename                 varchar(255)       NOT NULL DEFAULT '',
    filesize                 int unsigned       NOT NULL DEFAULT 0,
    file_data                mediumtext         NOT NULL,
    add_datetime             int unsigned       NOT NULL DEFAULT 0,
    message_id               int unsigned       NOT NULL DEFAULT 0,
    link                     varchar(10)        NOT NULL DEFAULT '',

    PRIMARY KEY (file_id),
    KEY add_datetime (add_datetime),
    KEY message_id_link (message_id, link),
    KEY user_id_link (user_id, link)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Ban list (IP, email, username, spam words)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_banlists (
    id                       int unsigned       NOT NULL AUTO_INCREMENT,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    type                     tinyint(4)         NOT NULL DEFAULT 0,
    pcre                     tinyint(1)         NOT NULL DEFAULT 0,
    string                   varchar(255)       NOT NULL DEFAULT '',
    comments                 text               NOT NULL,

    PRIMARY KEY (id),
    KEY forum_id (forum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Moderator action audit log
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_mod_log (
    mod_log_id               int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    action                   varchar(32)        NOT NULL DEFAULT '',
    object_type              varchar(16)        NOT NULL DEFAULT '',
    object_id                int unsigned       NOT NULL DEFAULT 0,
    details                  varchar(255)       NOT NULL DEFAULT '',
    time                     int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (mod_log_id),
    KEY user_id (user_id),
    KEY time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- User-submitted content reports
-- status: 0=open, 1=resolved, 2=dismissed
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_reports (
    report_id                int unsigned       NOT NULL AUTO_INCREMENT,
    message_id               int unsigned       NOT NULL DEFAULT 0,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    reporter_user_id         int unsigned       NOT NULL DEFAULT 0,
    reason                   varchar(255)       NOT NULL DEFAULT '',
    status                   tinyint(4)         NOT NULL DEFAULT 0,
    created                  int unsigned       NOT NULL DEFAULT 0,
    resolved_user_id         int unsigned       NOT NULL DEFAULT 0,
    resolved_time            int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (report_id),
    KEY message_id (message_id),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Full-text search index
-- InnoDB FULLTEXT requires MySQL 5.6+ / MariaDB 10.0+
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_search (
    message_id               int unsigned       NOT NULL DEFAULT 0,
    forum_id                 int unsigned       NOT NULL DEFAULT 0,
    search_text              mediumtext         NOT NULL,

    PRIMARY KEY (message_id),
    KEY forum_id (forum_id),
    FULLTEXT KEY search_text (search_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Custom field definitions (user profile fields, post fields)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_custom_fields_config (
    id                       int unsigned       NOT NULL AUTO_INCREMENT,
    field_type               tinyint(1)         NOT NULL DEFAULT 1,
    name                     varchar(50)        NOT NULL DEFAULT '',
    length                   mediumint          NOT NULL DEFAULT 255,
    html_disabled            tinyint(1)         NOT NULL DEFAULT 1,
    show_in_admin            tinyint(1)         NOT NULL DEFAULT 0,
    deleted                  tinyint(1)         NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    UNIQUE KEY field_type_name (field_type, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Custom field values
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_custom_fields (
    relation_id              int unsigned       NOT NULL DEFAULT 0,
    field_type               tinyint(1)         NOT NULL DEFAULT 1,
    type                     int unsigned       NOT NULL DEFAULT 0,
    data                     text               NOT NULL,

    PRIMARY KEY (relation_id, field_type, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Private message bodies
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_messages (
    pm_message_id            int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    author                   varchar(255)       NOT NULL DEFAULT '',
    subject                  varchar(100)       NOT NULL DEFAULT '',
    message                  text               NOT NULL,
    datestamp                int unsigned       NOT NULL DEFAULT 0,
    meta                     mediumtext         NOT NULL,

    PRIMARY KEY (pm_message_id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- PM folder definitions per user
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_folders (
    pm_folder_id             int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    foldername               varchar(20)        NOT NULL DEFAULT '',

    PRIMARY KEY (pm_folder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- PM <-> user/folder cross-reference
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_xref (
    pm_xref_id               int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    pm_folder_id             int unsigned       NOT NULL DEFAULT 0,
    special_folder           varchar(10)            NULL DEFAULT NULL,
    pm_message_id            int unsigned       NOT NULL DEFAULT 0,
    read_flag                tinyint(1)         NOT NULL DEFAULT 0,
    reply_flag               tinyint(1)         NOT NULL DEFAULT 0,

    PRIMARY KEY (pm_xref_id),
    KEY xref (user_id, pm_folder_id, pm_message_id),
    KEY read_flag (read_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- PM buddy list
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_buddies (
    pm_buddy_id              int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    buddy_user_id            int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (pm_buddy_id),
    UNIQUE KEY userids (user_id, buddy_user_id),
    KEY buddy_user_id (buddy_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Message edit / moderation audit trail
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_messages_edittrack (
    track_id                 int unsigned       NOT NULL AUTO_INCREMENT,
    message_id               int unsigned       NOT NULL DEFAULT 0,
    user_id                  int unsigned       NOT NULL DEFAULT 0,
    time                     int unsigned       NOT NULL DEFAULT 0,
    diff_body                text                   NULL,
    diff_subject             text                   NULL,

    PRIMARY KEY (track_id),
    KEY message_id (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
