-- -------------------------------------------------------------------------
-- Adds shadow_banned, a Phorum 10 addition marking a user account as
-- shadow-banned: the user keeps logging in and posting normally, but their
-- posts are hidden from every other viewer (see MessageMapper::STATUS_SHADOW).
-- Applied by SchemaPatcher against databases that already have the
-- {PREFIX}_users table — a fresh install gets this column directly from
-- db/mysql.sql instead.
-- -------------------------------------------------------------------------
ALTER TABLE {PREFIX}_users ADD COLUMN shadow_banned tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE {PREFIX}_users ADD KEY shadow_banned (shadow_banned);
