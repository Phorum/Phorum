-- -------------------------------------------------------------------------
-- Outgoing webhook subscriptions (mods/webhooks) — a Phorum 10 addition,
-- picked up automatically by SchemaInstaller alongside db/mysql.sql (see
-- SchemaInstaller::allSchemaFiles()). No core db/*.sql edit is needed for
-- a module's own tables.
--
-- events is a JSON array of event names (e.g. ["message.created"]).
-- payload_template is an optional Twig template producing the raw request
-- body; blank means the module's standard JSON envelope is sent instead.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {PREFIX}_webhooks (
    id                       int unsigned       NOT NULL AUTO_INCREMENT,
    url                      varchar(500)       NOT NULL DEFAULT '',
    secret                   varchar(64)        NOT NULL DEFAULT '',
    events                   text               NOT NULL,
    active                   tinyint(1)         NOT NULL DEFAULT 1,
    payload_template         text                   NULL,
    content_type             varchar(100)       NOT NULL DEFAULT 'application/json',
    created_at               int unsigned       NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    KEY active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
