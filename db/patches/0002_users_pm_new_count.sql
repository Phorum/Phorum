-- -------------------------------------------------------------------------
-- Adds pm_new_count, a Phorum 10 addition tracking each user's unread PM
-- count. Missing from Phorum 6, and missed by patch 0001 when it added the
-- other new users/forums columns. Applied by SchemaPatcher against databases
-- that already have the {PREFIX}_users table — a fresh install gets this
-- column directly from db/mysql.sql instead.
--
-- Backfilled from {PREFIX}_pm_xref (read_flag = 0 rows) so upgraded users
-- see their true unread count immediately, rather than starting at 0 and
-- only becoming accurate as new PM activity increments/decrements it.
-- -------------------------------------------------------------------------
ALTER TABLE {PREFIX}_users ADD COLUMN pm_new_count int unsigned NOT NULL DEFAULT 0;

UPDATE {PREFIX}_users u
SET pm_new_count = (
    SELECT COUNT(*)
    FROM {PREFIX}_pm_xref x
    WHERE x.user_id = u.user_id AND x.read_flag = 0
);
