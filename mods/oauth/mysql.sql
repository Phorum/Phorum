-- -------------------------------------------------------------------------
-- OAuth login identities (mods/oauth) — a Phorum 10 addition, picked up
-- automatically by SchemaInstaller alongside db/mysql.sql (see
-- SchemaInstaller::allSchemaFiles()). No core db/*.sql edit is needed for
-- a module's own tables.
--
-- Links a third-party provider identity (Google/GitHub) to a local user
-- row. The users table itself is frozen for Phorum 6 schema compatibility,
-- so this is an additive join table rather than new columns on users.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_oauth_identities (
    oauth_identity_id        int unsigned       NOT NULL AUTO_INCREMENT,
    user_id                  int unsigned       NOT NULL,
    provider                 varchar(20)        NOT NULL DEFAULT '',
    provider_user_id         varchar(191)       NOT NULL DEFAULT '',
    email                    varchar(100)       NOT NULL DEFAULT '',
    date_added               int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (oauth_identity_id),
    UNIQUE KEY provider_identity (provider, provider_user_id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
