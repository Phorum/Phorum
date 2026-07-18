-- -------------------------------------------------------------------------
-- Outgoing webhook subscriptions (mods/webhooks) — a Phorum 10 addition.
-- Mirrors mods/webhooks/mysql.sql; not currently wired to any runtime path
-- (db/postgresql.sql itself isn't loaded by SchemaInstaller yet), kept in
-- sync per the project's convention of maintaining all schema dialects.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_webhooks (
    id                       SERIAL          NOT NULL,
    url                      VARCHAR(500)    NOT NULL DEFAULT '',
    secret                   VARCHAR(64)     NOT NULL DEFAULT '',
    events                   TEXT            NOT NULL DEFAULT '',
    active                   SMALLINT        NOT NULL DEFAULT 1,
    payload_template         TEXT                         NULL,
    content_type             VARCHAR(100)    NOT NULL DEFAULT 'application/json',
    created_at               INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (id)
);

CREATE INDEX IF NOT EXISTS {PREFIX}_webhooks_active ON {PREFIX}_webhooks (active);
