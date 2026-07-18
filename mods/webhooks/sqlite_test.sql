CREATE TABLE IF NOT EXISTS phorum_webhooks (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    url              TEXT    NOT NULL DEFAULT '',
    secret           TEXT    NOT NULL DEFAULT '',
    events           TEXT    NOT NULL DEFAULT '',
    active           INTEGER NOT NULL DEFAULT 1,
    payload_template TEXT            DEFAULT NULL,
    content_type     TEXT    NOT NULL DEFAULT 'application/json',
    created_at       INTEGER NOT NULL DEFAULT 0
);
