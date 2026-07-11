-- Phorum PostgreSQL schema
-- Table prefix placeholder: {PREFIX} (replaced by the installer with your db_prefix)
--
-- Key differences from mysql.sql:
--   * SERIAL for auto-increment primary keys
--   * integer for int unsigned (PostgreSQL has no unsigned integer types)
--   * smallint for tinyint
--   * text for mediumtext
--   * numeric(4,2) for float(4,2)
--   * VARCHAR(1) + CHECK for the settings.type enum
--   * Secondary indexes are separate CREATE INDEX statements (must include {PREFIX} in name)
--   * No FULLTEXT index on {PREFIX}_search — add a GIN index and tsvector column
--     when implementing a PostgreSQL search service

-- -------------------------------------------------------------------------
-- Forums / folders
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_forums (
    forum_id                 SERIAL          NOT NULL,
    name                     VARCHAR(50)     NOT NULL DEFAULT '',
    active                   SMALLINT        NOT NULL DEFAULT 0,
    description              TEXT            NOT NULL DEFAULT '',
    template                 VARCHAR(50)     NOT NULL DEFAULT '',
    folder_flag              SMALLINT        NOT NULL DEFAULT 0,
    parent_id                INTEGER         NOT NULL DEFAULT 0,
    list_length_flat         INTEGER         NOT NULL DEFAULT 0,
    list_length_threaded     INTEGER         NOT NULL DEFAULT 0,
    moderation               INTEGER         NOT NULL DEFAULT 0,
    threaded_list            SMALLINT        NOT NULL DEFAULT 0,
    threaded_read            SMALLINT        NOT NULL DEFAULT 0,
    float_to_top             SMALLINT        NOT NULL DEFAULT 0,
    check_duplicate          SMALLINT        NOT NULL DEFAULT 0,
    allow_attachment_types   VARCHAR(100)    NOT NULL DEFAULT '',
    max_attachment_size      INTEGER         NOT NULL DEFAULT 0,
    max_totalattachment_size INTEGER         NOT NULL DEFAULT 0,
    max_attachments          INTEGER         NOT NULL DEFAULT 0,
    pub_perms                INTEGER         NOT NULL DEFAULT 0,
    reg_perms                INTEGER         NOT NULL DEFAULT 0,
    display_ip_address       SMALLINT        NOT NULL DEFAULT 1,
    allow_email_notify       SMALLINT        NOT NULL DEFAULT 1,
    language                 VARCHAR(100)    NOT NULL DEFAULT 'english',
    email_moderators         SMALLINT        NOT NULL DEFAULT 0,
    message_count            INTEGER         NOT NULL DEFAULT 0,
    sticky_count             INTEGER         NOT NULL DEFAULT 0,
    thread_count             INTEGER         NOT NULL DEFAULT 0,
    last_post_time           INTEGER         NOT NULL DEFAULT 0,
    display_order            INTEGER         NOT NULL DEFAULT 0,
    read_length              INTEGER         NOT NULL DEFAULT 0,
    vroot                    INTEGER         NOT NULL DEFAULT 0,
    forum_path               TEXT            NOT NULL DEFAULT '',
    count_views              SMALLINT        NOT NULL DEFAULT 0,
    count_views_per_thread   SMALLINT        NOT NULL DEFAULT 0,
    display_fixed            SMALLINT        NOT NULL DEFAULT 0,
    reverse_threading        SMALLINT        NOT NULL DEFAULT 0,
    inherit_id               INTEGER                      NULL DEFAULT NULL,
    cache_version            INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (forum_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_forums_name         ON {PREFIX}_forums (name);
CREATE INDEX IF NOT EXISTS {PREFIX}_forums_folder_index ON {PREFIX}_forums (parent_id, vroot, active, folder_flag);

-- -------------------------------------------------------------------------
-- Messages
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_messages (
    message_id               SERIAL          NOT NULL,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    thread                   INTEGER         NOT NULL DEFAULT 0,
    parent_id                INTEGER         NOT NULL DEFAULT 0,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    author                   VARCHAR(255)    NOT NULL DEFAULT '',
    subject                  VARCHAR(255)    NOT NULL DEFAULT '',
    body                     TEXT            NOT NULL DEFAULT '',
    email                    VARCHAR(100)    NOT NULL DEFAULT '',
    ip                       VARCHAR(255)    NOT NULL DEFAULT '',
    status                   SMALLINT        NOT NULL DEFAULT 2,
    msgid                    VARCHAR(100)    NOT NULL DEFAULT '',
    modifystamp              INTEGER         NOT NULL DEFAULT 0,
    thread_count             INTEGER         NOT NULL DEFAULT 0,
    moderator_post           SMALLINT        NOT NULL DEFAULT 0,
    sort                     SMALLINT        NOT NULL DEFAULT 2,
    datestamp                INTEGER         NOT NULL DEFAULT 0,
    meta                     TEXT                         NULL,
    viewcount                INTEGER         NOT NULL DEFAULT 0,
    threadviewcount          INTEGER         NOT NULL DEFAULT 0,
    closed                   SMALLINT        NOT NULL DEFAULT 0,
    recent_message_id        INTEGER         NOT NULL DEFAULT 0,
    recent_user_id           INTEGER         NOT NULL DEFAULT 0,
    recent_author            VARCHAR(255)    NOT NULL DEFAULT '',
    moved                    SMALLINT        NOT NULL DEFAULT 0,
    hide_period              INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (message_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_messages_special_threads      ON {PREFIX}_messages (sort, forum_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_last_post_time       ON {PREFIX}_messages (forum_id, status, modifystamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_dup_check            ON {PREFIX}_messages (forum_id, author, subject, datestamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_recent_user_id       ON {PREFIX}_messages (recent_user_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_user_messages        ON {PREFIX}_messages (user_id, message_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_updated_threads      ON {PREFIX}_messages (status, parent_id, modifystamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_list_page_flat       ON {PREFIX}_messages (forum_id, status, parent_id, datestamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_thread_date          ON {PREFIX}_messages (thread, datestamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_list_page_float      ON {PREFIX}_messages (forum_id, status, parent_id, modifystamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_forum_recent         ON {PREFIX}_messages (forum_id, status, datestamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_recent_threads       ON {PREFIX}_messages (status, parent_id, datestamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_recent_messages      ON {PREFIX}_messages (status, datestamp);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_forum_thread_count   ON {PREFIX}_messages (forum_id, parent_id, status, moved, message_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_messages_forum_message_count  ON {PREFIX}_messages (forum_id, status, moved, message_id);

-- -------------------------------------------------------------------------
-- Settings key/value store
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_settings (
    name                     VARCHAR(255)    NOT NULL DEFAULT '',
    type                     VARCHAR(1)      NOT NULL DEFAULT 'V' CHECK (type IN ('V', 'S')),
    data                     TEXT            NOT NULL DEFAULT '',

    PRIMARY KEY (name)
);

-- -------------------------------------------------------------------------
-- Subscriptions
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_subscribers (
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    sub_type                 SMALLINT        NOT NULL DEFAULT 0,
    thread                   INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id, thread)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_subscribers_forum_id ON {PREFIX}_subscribers (forum_id, thread, sub_type);

-- -------------------------------------------------------------------------
-- Per-user per-forum permission overrides
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_permissions (
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    permission               INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_user_permissions_forum_id ON {PREFIX}_user_permissions (forum_id, permission);

-- -------------------------------------------------------------------------
-- Users
-- Note: password / password_temp are VARCHAR(255) (Phorum 5.x used VARCHAR(50)).
--       Upgrade migrations must ALTER these columns to store bcrypt hashes.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_users (
    user_id                  SERIAL          NOT NULL,
    username                 VARCHAR(50)     NOT NULL DEFAULT '',
    real_name                VARCHAR(255)    NOT NULL DEFAULT '',
    display_name             VARCHAR(255)    NOT NULL DEFAULT '',
    password                 VARCHAR(255)    NOT NULL DEFAULT '',
    password_temp            VARCHAR(255)    NOT NULL DEFAULT '',
    sessid_lt                VARCHAR(64)     NOT NULL DEFAULT '',
    sessid_st                VARCHAR(64)     NOT NULL DEFAULT '',
    sessid_st_timeout        INTEGER         NOT NULL DEFAULT 0,
    email                    VARCHAR(100)    NOT NULL DEFAULT '',
    email_temp               VARCHAR(110)    NOT NULL DEFAULT '',
    hide_email               SMALLINT        NOT NULL DEFAULT 1,
    active                   SMALLINT        NOT NULL DEFAULT 0,
    signature                TEXT            NOT NULL DEFAULT '',
    threaded_list            SMALLINT        NOT NULL DEFAULT 0,
    posts                    INTEGER         NOT NULL DEFAULT 0,
    admin                    SMALLINT        NOT NULL DEFAULT 0,
    threaded_read            SMALLINT        NOT NULL DEFAULT 0,
    date_added               INTEGER         NOT NULL DEFAULT 0,
    date_last_active         INTEGER         NOT NULL DEFAULT 0,
    last_active_forum        INTEGER         NOT NULL DEFAULT 0,
    hide_activity            SMALLINT        NOT NULL DEFAULT 0,
    show_signature           SMALLINT        NOT NULL DEFAULT 0,
    email_notify             SMALLINT        NOT NULL DEFAULT 0,
    pm_email_notify          SMALLINT        NOT NULL DEFAULT 1,
    pm_new_count             INTEGER         NOT NULL DEFAULT 0,
    tz_offset                NUMERIC(4,2)    NOT NULL DEFAULT -99.00,
    is_dst                   SMALLINT        NOT NULL DEFAULT 0,
    user_language            VARCHAR(100)    NOT NULL DEFAULT '',
    user_template            VARCHAR(100)    NOT NULL DEFAULT '',
    moderation_email         SMALLINT        NOT NULL DEFAULT 1,
    settings_data            TEXT            NOT NULL DEFAULT '',

    PRIMARY KEY (user_id),
    UNIQUE (username)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_users_active        ON {PREFIX}_users (active);
CREATE INDEX IF NOT EXISTS {PREFIX}_users_sessid_st     ON {PREFIX}_users (sessid_st);
CREATE INDEX IF NOT EXISTS {PREFIX}_users_sessid_lt     ON {PREFIX}_users (sessid_lt);
CREATE INDEX IF NOT EXISTS {PREFIX}_users_activity      ON {PREFIX}_users (date_last_active, hide_activity, last_active_forum);
CREATE INDEX IF NOT EXISTS {PREFIX}_users_date_added    ON {PREFIX}_users (date_added);
CREATE INDEX IF NOT EXISTS {PREFIX}_users_email_temp    ON {PREFIX}_users (email_temp);

-- -------------------------------------------------------------------------
-- Read/unread tracking per user
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_newflags (
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    message_id               INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id, message_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_user_newflags_move ON {PREFIX}_user_newflags (message_id, forum_id);

-- -------------------------------------------------------------------------
-- Minimum unread message id (optimization table for newflags)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_min_id (
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    min_id                   INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id, forum_id)
);

-- -------------------------------------------------------------------------
-- Groups
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_groups (
    group_id                 SERIAL          NOT NULL,
    name                     VARCHAR(255)    NOT NULL DEFAULT '',
    open                     SMALLINT        NOT NULL DEFAULT 0,

    PRIMARY KEY (group_id)
);

-- -------------------------------------------------------------------------
-- Forums <-> groups permission cross-reference
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_forum_group_xref (
    forum_group_xref_id      SERIAL          NOT NULL,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    group_id                 INTEGER         NOT NULL DEFAULT 0,
    permission               INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (forum_group_xref_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS {PREFIX}_forum_group_xref_forum_group ON {PREFIX}_forum_group_xref (forum_id, group_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_forum_group_xref_group_id ON {PREFIX}_forum_group_xref (group_id);

-- -------------------------------------------------------------------------
-- Users <-> groups cross-reference
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_user_group_xref (
    user_group_xref_id       SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    group_id                 INTEGER         NOT NULL DEFAULT 0,
    status                   SMALLINT        NOT NULL DEFAULT 1,

    PRIMARY KEY (user_group_xref_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS {PREFIX}_user_group_xref_user_group ON {PREFIX}_user_group_xref (user_id, group_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_user_group_xref_user_id ON {PREFIX}_user_group_xref (user_id);

-- -------------------------------------------------------------------------
-- File attachments
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_files (
    file_id                  SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    filename                 VARCHAR(255)    NOT NULL DEFAULT '',
    filesize                 INTEGER         NOT NULL DEFAULT 0,
    file_data                TEXT            NOT NULL DEFAULT '',
    add_datetime             INTEGER         NOT NULL DEFAULT 0,
    message_id               INTEGER         NOT NULL DEFAULT 0,
    link                     VARCHAR(10)     NOT NULL DEFAULT '',

    PRIMARY KEY (file_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_files_add_datetime   ON {PREFIX}_files (add_datetime);
CREATE INDEX IF NOT EXISTS {PREFIX}_files_message_id     ON {PREFIX}_files (message_id, link);
CREATE INDEX IF NOT EXISTS {PREFIX}_files_user_id        ON {PREFIX}_files (user_id, link);

-- -------------------------------------------------------------------------
-- Ban list (IP, email, username, spam words)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_banlists (
    id                       SERIAL          NOT NULL,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    type                     SMALLINT        NOT NULL DEFAULT 0,
    pcre                     SMALLINT        NOT NULL DEFAULT 0,
    string                   VARCHAR(255)    NOT NULL DEFAULT '',
    comments                 TEXT            NOT NULL DEFAULT '',

    PRIMARY KEY (id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_banlists_forum_id ON {PREFIX}_banlists (forum_id);

-- -------------------------------------------------------------------------
-- Moderator action audit log
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_mod_log (
    mod_log_id               SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    action                   VARCHAR(32)     NOT NULL DEFAULT '',
    object_type              VARCHAR(16)     NOT NULL DEFAULT '',
    object_id                INTEGER         NOT NULL DEFAULT 0,
    details                  VARCHAR(255)    NOT NULL DEFAULT '',
    time                     INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (mod_log_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_mod_log_user_id ON {PREFIX}_mod_log (user_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_mod_log_time ON {PREFIX}_mod_log (time);

-- -------------------------------------------------------------------------
-- User-submitted content reports
-- status: 0=open, 1=resolved, 2=dismissed
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_reports (
    report_id                SERIAL          NOT NULL,
    message_id               INTEGER         NOT NULL DEFAULT 0,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    reporter_user_id         INTEGER         NOT NULL DEFAULT 0,
    reason                   VARCHAR(255)    NOT NULL DEFAULT '',
    status                   SMALLINT        NOT NULL DEFAULT 0,
    created                  INTEGER         NOT NULL DEFAULT 0,
    resolved_user_id         INTEGER         NOT NULL DEFAULT 0,
    resolved_time            INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (report_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_reports_message_id ON {PREFIX}_reports (message_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_reports_status ON {PREFIX}_reports (status);

-- -------------------------------------------------------------------------
-- Full-text search index
-- The search_text column stores "author | subject | body" (mirrors Phorum 5.x).
-- To enable full-text search on PostgreSQL, implement a PostgreSQL search
-- service and add a GIN index, for example:
--   ALTER TABLE {PREFIX}_search ADD COLUMN search_vector tsvector
--       GENERATED ALWAYS AS (to_tsvector('english', search_text)) STORED;
--   CREATE INDEX {PREFIX}_search_vector_idx ON {PREFIX}_search USING GIN (search_vector);
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_search (
    message_id               INTEGER         NOT NULL DEFAULT 0,
    forum_id                 INTEGER         NOT NULL DEFAULT 0,
    search_text              TEXT            NOT NULL DEFAULT '',

    PRIMARY KEY (message_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_search_forum_id ON {PREFIX}_search (forum_id);

-- -------------------------------------------------------------------------
-- Custom field definitions (user profile fields, post fields)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_custom_fields_config (
    id                       SERIAL          NOT NULL,
    field_type               SMALLINT        NOT NULL DEFAULT 1,
    name                     VARCHAR(50)     NOT NULL DEFAULT '',
    length                   INTEGER         NOT NULL DEFAULT 255,
    html_disabled            SMALLINT        NOT NULL DEFAULT 1,
    show_in_admin            SMALLINT        NOT NULL DEFAULT 0,
    deleted                  SMALLINT        NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    UNIQUE (field_type, name)
);

-- -------------------------------------------------------------------------
-- Custom field values
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_custom_fields (
    relation_id              INTEGER         NOT NULL DEFAULT 0,
    field_type               SMALLINT        NOT NULL DEFAULT 1,
    type                     INTEGER         NOT NULL DEFAULT 0,
    data                     TEXT            NOT NULL DEFAULT '',

    PRIMARY KEY (relation_id, field_type, type)
);

-- -------------------------------------------------------------------------
-- Private message bodies
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_messages (
    pm_message_id            SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    author                   VARCHAR(255)    NOT NULL DEFAULT '',
    subject                  VARCHAR(100)    NOT NULL DEFAULT '',
    message                  TEXT            NOT NULL DEFAULT '',
    datestamp                INTEGER         NOT NULL DEFAULT 0,
    meta                     TEXT            NOT NULL DEFAULT '',

    PRIMARY KEY (pm_message_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_pm_messages_user_id ON {PREFIX}_pm_messages (user_id);

-- -------------------------------------------------------------------------
-- PM folder definitions per user
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_folders (
    pm_folder_id             SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    foldername               VARCHAR(20)     NOT NULL DEFAULT '',

    PRIMARY KEY (pm_folder_id)
);

-- -------------------------------------------------------------------------
-- PM <-> user/folder cross-reference
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_xref (
    pm_xref_id               SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    pm_folder_id             INTEGER         NOT NULL DEFAULT 0,
    special_folder           VARCHAR(10)                  NULL DEFAULT NULL,
    pm_message_id            INTEGER         NOT NULL DEFAULT 0,
    read_flag                SMALLINT        NOT NULL DEFAULT 0,
    reply_flag               SMALLINT        NOT NULL DEFAULT 0,

    PRIMARY KEY (pm_xref_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_pm_xref_xref      ON {PREFIX}_pm_xref (user_id, pm_folder_id, pm_message_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_pm_xref_read_flag ON {PREFIX}_pm_xref (read_flag);

-- -------------------------------------------------------------------------
-- PM buddy list
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_pm_buddies (
    pm_buddy_id              SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    buddy_user_id            INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (pm_buddy_id),
    UNIQUE (user_id, buddy_user_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_pm_buddies_buddy_user_id ON {PREFIX}_pm_buddies (buddy_user_id);

-- -------------------------------------------------------------------------
-- Message edit / moderation audit trail
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_messages_edittrack (
    track_id                 SERIAL          NOT NULL,
    message_id               INTEGER         NOT NULL DEFAULT 0,
    user_id                  INTEGER         NOT NULL DEFAULT 0,
    time                     INTEGER         NOT NULL DEFAULT 0,
    diff_body                TEXT                         NULL,
    diff_subject             TEXT                         NULL,

    PRIMARY KEY (track_id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_messages_edittrack_message_id ON {PREFIX}_messages_edittrack (message_id);
