-- SQLite in-memory schema for PHPUnit mapper tests.
-- Prefix placeholder phorum_ is used directly (PHORUM_DB_PREFIX not defined in tests).
-- MySQL-specific syntax stripped, types simplified. SQLite ignores column types anyway.

CREATE TABLE IF NOT EXISTS phorum_forums (
    forum_id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    name                     TEXT    NOT NULL DEFAULT '',
    active                   INTEGER NOT NULL DEFAULT 0,
    description              TEXT    NOT NULL DEFAULT '',
    template                 TEXT    NOT NULL DEFAULT '',
    folder_flag              INTEGER NOT NULL DEFAULT 0,
    parent_id                INTEGER NOT NULL DEFAULT 0,
    list_length_flat         INTEGER NOT NULL DEFAULT 0,
    list_length_threaded     INTEGER NOT NULL DEFAULT 0,
    moderation               INTEGER NOT NULL DEFAULT 0,
    threaded_list            INTEGER NOT NULL DEFAULT 0,
    threaded_read            INTEGER NOT NULL DEFAULT 0,
    float_to_top             INTEGER NOT NULL DEFAULT 0,
    check_duplicate          INTEGER NOT NULL DEFAULT 0,
    allow_attachment_types   TEXT    NOT NULL DEFAULT '',
    max_attachment_size      INTEGER NOT NULL DEFAULT 0,
    max_totalattachment_size INTEGER NOT NULL DEFAULT 0,
    max_attachments          INTEGER NOT NULL DEFAULT 0,
    pub_perms                INTEGER NOT NULL DEFAULT 0,
    reg_perms                INTEGER NOT NULL DEFAULT 0,
    display_ip_address       INTEGER NOT NULL DEFAULT 1,
    allow_email_notify       INTEGER NOT NULL DEFAULT 1,
    language                 TEXT    NOT NULL DEFAULT 'english',
    email_moderators         INTEGER NOT NULL DEFAULT 0,
    message_count            INTEGER NOT NULL DEFAULT 0,
    sticky_count             INTEGER NOT NULL DEFAULT 0,
    thread_count             INTEGER NOT NULL DEFAULT 0,
    last_post_time           INTEGER NOT NULL DEFAULT 0,
    display_order            INTEGER NOT NULL DEFAULT 0,
    read_length              INTEGER NOT NULL DEFAULT 0,
    vroot                    INTEGER NOT NULL DEFAULT 0,
    edit_post                INTEGER NOT NULL DEFAULT 1,
    template_settings        TEXT    NOT NULL DEFAULT '',
    forum_path               TEXT    NOT NULL DEFAULT '',
    count_views              INTEGER NOT NULL DEFAULT 0,
    count_views_per_thread   INTEGER NOT NULL DEFAULT 0,
    display_fixed            INTEGER NOT NULL DEFAULT 0,
    reverse_threading        INTEGER NOT NULL DEFAULT 0,
    inherit_id               INTEGER         DEFAULT NULL,
    cache_version            INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS phorum_messages (
    message_id               INTEGER PRIMARY KEY AUTOINCREMENT,
    forum_id                 INTEGER NOT NULL DEFAULT 0,
    thread                   INTEGER NOT NULL DEFAULT 0,
    parent_id                INTEGER NOT NULL DEFAULT 0,
    user_id                  INTEGER NOT NULL DEFAULT 0,
    author                   TEXT    NOT NULL DEFAULT '',
    subject                  TEXT    NOT NULL DEFAULT '',
    body                     TEXT    NOT NULL DEFAULT '',
    email                    TEXT    NOT NULL DEFAULT '',
    ip                       TEXT    NOT NULL DEFAULT '',
    status                   INTEGER NOT NULL DEFAULT 2,
    msgid                    TEXT    NOT NULL DEFAULT '',
    modifystamp              INTEGER NOT NULL DEFAULT 0,
    thread_count             INTEGER NOT NULL DEFAULT 0,
    moderator_post           INTEGER NOT NULL DEFAULT 0,
    sort                     INTEGER NOT NULL DEFAULT 2,
    datestamp                INTEGER NOT NULL DEFAULT 0,
    meta                     TEXT            DEFAULT NULL,
    viewcount                INTEGER NOT NULL DEFAULT 0,
    threadviewcount          INTEGER NOT NULL DEFAULT 0,
    closed                   INTEGER NOT NULL DEFAULT 0,
    recent_message_id        INTEGER NOT NULL DEFAULT 0,
    recent_user_id           INTEGER NOT NULL DEFAULT 0,
    recent_author            TEXT    NOT NULL DEFAULT '',
    moved                    INTEGER NOT NULL DEFAULT 0,
    hide_period              INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS phorum_settings (
    name TEXT    NOT NULL DEFAULT '' PRIMARY KEY,
    type TEXT    NOT NULL DEFAULT 'V',
    data TEXT    NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS phorum_subscribers (
    user_id  INTEGER NOT NULL DEFAULT 0,
    forum_id INTEGER NOT NULL DEFAULT 0,
    sub_type INTEGER NOT NULL DEFAULT 0,
    thread   INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, forum_id, thread)
);

CREATE TABLE IF NOT EXISTS phorum_user_permissions (
    user_id    INTEGER NOT NULL DEFAULT 0,
    forum_id   INTEGER NOT NULL DEFAULT 0,
    permission INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, forum_id)
);

CREATE TABLE IF NOT EXISTS phorum_users (
    user_id           INTEGER PRIMARY KEY AUTOINCREMENT,
    username          TEXT    NOT NULL DEFAULT '',
    real_name         TEXT    NOT NULL DEFAULT '',
    display_name      TEXT    NOT NULL DEFAULT '',
    password          TEXT    NOT NULL DEFAULT '',
    password_temp     TEXT    NOT NULL DEFAULT '',
    sessid_lt         TEXT    NOT NULL DEFAULT '',
    sessid_st         TEXT    NOT NULL DEFAULT '',
    sessid_st_timeout INTEGER NOT NULL DEFAULT 0,
    email             TEXT    NOT NULL DEFAULT '',
    email_temp        TEXT    NOT NULL DEFAULT '',
    hide_email        INTEGER NOT NULL DEFAULT 1,
    active            INTEGER NOT NULL DEFAULT 0,
    signature         TEXT    NOT NULL DEFAULT '',
    threaded_list     INTEGER NOT NULL DEFAULT 0,
    posts             INTEGER NOT NULL DEFAULT 0,
    admin             INTEGER NOT NULL DEFAULT 0,
    threaded_read     INTEGER NOT NULL DEFAULT 0,
    date_added        INTEGER NOT NULL DEFAULT 0,
    date_last_active  INTEGER NOT NULL DEFAULT 0,
    last_active_forum INTEGER NOT NULL DEFAULT 0,
    hide_activity     INTEGER NOT NULL DEFAULT 0,
    show_signature    INTEGER NOT NULL DEFAULT 0,
    email_notify      INTEGER NOT NULL DEFAULT 0,
    pm_email_notify   INTEGER NOT NULL DEFAULT 1,
    pm_new_count      INTEGER NOT NULL DEFAULT 0,
    tz_offset         REAL    NOT NULL DEFAULT -99.00,
    is_dst            INTEGER NOT NULL DEFAULT 0,
    user_language     TEXT    NOT NULL DEFAULT '',
    user_template     TEXT    NOT NULL DEFAULT '',
    moderation_email  INTEGER NOT NULL DEFAULT 1,
    settings_data     TEXT    NOT NULL DEFAULT '',
    moderator_data    TEXT    NOT NULL DEFAULT '',
    force_password_change INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS phorum_user_newflags (
    user_id    INTEGER NOT NULL DEFAULT 0,
    forum_id   INTEGER NOT NULL DEFAULT 0,
    message_id INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, forum_id, message_id)
);

CREATE TABLE IF NOT EXISTS phorum_groups (
    group_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name     TEXT    NOT NULL DEFAULT '',
    open     INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS phorum_forum_group_xref (
    forum_group_xref_id INTEGER PRIMARY KEY AUTOINCREMENT,
    forum_id            INTEGER NOT NULL DEFAULT 0,
    group_id            INTEGER NOT NULL DEFAULT 0,
    permission          INTEGER NOT NULL DEFAULT 0,
    UNIQUE (forum_id, group_id)
);

CREATE TABLE IF NOT EXISTS phorum_user_group_xref (
    user_group_xref_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id            INTEGER NOT NULL DEFAULT 0,
    group_id           INTEGER NOT NULL DEFAULT 0,
    status             INTEGER NOT NULL DEFAULT 1,
    UNIQUE (user_id, group_id)
);

CREATE TABLE IF NOT EXISTS phorum_files (
    file_id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL DEFAULT 0,
    filename     TEXT    NOT NULL DEFAULT '',
    filesize     INTEGER NOT NULL DEFAULT 0,
    file_data    TEXT    NOT NULL DEFAULT '',
    add_datetime INTEGER NOT NULL DEFAULT 0,
    message_id   INTEGER NOT NULL DEFAULT 0,
    link         TEXT    NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS phorum_banlists (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    forum_id INTEGER NOT NULL DEFAULT 0,
    type     INTEGER NOT NULL DEFAULT 0,
    pcre     INTEGER NOT NULL DEFAULT 0,
    string   TEXT    NOT NULL DEFAULT '',
    comments TEXT    NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS phorum_search (
    message_id  INTEGER NOT NULL DEFAULT 0 PRIMARY KEY,
    forum_id    INTEGER NOT NULL DEFAULT 0,
    search_text TEXT    NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS phorum_mod_log (
    mod_log_id  INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL DEFAULT 0,
    forum_id    INTEGER NOT NULL DEFAULT 0,
    action      TEXT    NOT NULL DEFAULT '',
    object_type TEXT    NOT NULL DEFAULT '',
    object_id   INTEGER NOT NULL DEFAULT 0,
    details     TEXT    NOT NULL DEFAULT '',
    time        INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS phorum_reports (
    report_id        INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id       INTEGER NOT NULL DEFAULT 0,
    forum_id         INTEGER NOT NULL DEFAULT 0,
    reporter_user_id INTEGER NOT NULL DEFAULT 0,
    reason           TEXT    NOT NULL DEFAULT '',
    status           INTEGER NOT NULL DEFAULT 0,
    created          INTEGER NOT NULL DEFAULT 0,
    resolved_user_id INTEGER NOT NULL DEFAULT 0,
    resolved_time    INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS phorum_user_custom_fields (
    user_id     INTEGER NOT NULL DEFAULT 0,
    type        INTEGER NOT NULL DEFAULT 0,
    data        TEXT    NOT NULL DEFAULT '',
    PRIMARY KEY (user_id, type)
);

CREATE TABLE IF NOT EXISTS phorum_pm_messages (
    pm_message_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NOT NULL DEFAULT 0,
    author        TEXT    NOT NULL DEFAULT '',
    subject       TEXT    NOT NULL DEFAULT '',
    message       TEXT    NOT NULL DEFAULT '',
    datestamp     INTEGER NOT NULL DEFAULT 0,
    meta          TEXT    NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS phorum_pm_folders (
    pm_folder_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL DEFAULT 0,
    foldername   TEXT    NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS phorum_pm_xref (
    pm_xref_id     INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL DEFAULT 0,
    pm_folder_id   INTEGER NOT NULL DEFAULT 0,
    special_folder TEXT            DEFAULT NULL,
    pm_message_id  INTEGER NOT NULL DEFAULT 0,
    read_flag      INTEGER NOT NULL DEFAULT 0,
    reply_flag     INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS phorum_messages_edittrack (
    track_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id  INTEGER NOT NULL DEFAULT 0,
    user_id     INTEGER NOT NULL DEFAULT 0,
    time        INTEGER NOT NULL DEFAULT 0,
    diff_body   TEXT            DEFAULT NULL,
    diff_subject TEXT           DEFAULT NULL
);
