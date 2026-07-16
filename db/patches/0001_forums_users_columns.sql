-- -------------------------------------------------------------------------
-- Adds columns that exist in Phorum 6 but were missing from the Phorum 10
-- rewrite's {PREFIX}_forums / {PREFIX}_users tables. Applied by SchemaPatcher
-- against databases that already have these tables (an existing Phorum 6
-- database, or an already-running Phorum 10 site upgrading its code) — a
-- fresh install gets these columns directly from db/mysql.sql instead.
-- -------------------------------------------------------------------------
ALTER TABLE {PREFIX}_forums ADD COLUMN edit_post tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE {PREFIX}_forums ADD COLUMN template_settings text NOT NULL;
ALTER TABLE {PREFIX}_users ADD COLUMN moderator_data text NOT NULL;
ALTER TABLE {PREFIX}_users ADD COLUMN force_password_change tinyint(1) NOT NULL DEFAULT 0;
