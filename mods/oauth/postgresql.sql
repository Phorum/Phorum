-- -------------------------------------------------------------------------
-- OAuth login identities (mods/oauth) — a Phorum 10 addition.
-- Mirrors mods/oauth/mysql.sql; not currently wired to any runtime path
-- (db/postgresql.sql itself isn't loaded by SchemaInstaller yet), kept in
-- sync per the project's convention of maintaining all schema dialects.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_oauth_identities (
    oauth_identity_id        SERIAL          NOT NULL,
    user_id                  INTEGER         NOT NULL,
    provider                 VARCHAR(20)     NOT NULL DEFAULT '',
    provider_user_id         VARCHAR(191)    NOT NULL DEFAULT '',
    email                    VARCHAR(100)    NOT NULL DEFAULT '',
    date_added               INTEGER         NOT NULL DEFAULT 0,

    PRIMARY KEY (oauth_identity_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS {PREFIX}_oauth_identities_provider_identity ON {PREFIX}_oauth_identities (provider, provider_user_id);
CREATE INDEX IF NOT EXISTS {PREFIX}_oauth_identities_user_id ON {PREFIX}_oauth_identities (user_id);
