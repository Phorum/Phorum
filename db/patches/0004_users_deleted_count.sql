-- -------------------------------------------------------------------------
-- Adds deleted_count, a Phorum 10 addition tracking how many of a user's
-- messages have been removed by a moderator — the "bad" side of the karma
-- threshold check (see MessageService::post()), mirroring how `posts`
-- already tracks the "good"/approved side. Applied by SchemaPatcher against
-- databases that already have the {PREFIX}_users table — a fresh install
-- gets this column directly from db/mysql.sql instead.
-- -------------------------------------------------------------------------
ALTER TABLE {PREFIX}_users ADD COLUMN deleted_count int unsigned NOT NULL DEFAULT 0;
