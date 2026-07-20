-- -------------------------------------------------------------------------
-- Backfills the new ALLOW_VIEW_ATTACHMENTS permission bit (16) onto every
-- existing forum's pub_perms/reg_perms, wherever ALLOW_READ (1) is already
-- set — this is a data migration, not a schema change (both columns
-- already exist and are wide enough for the new bit; see
-- PermissionFlags::FLAGS / PermissionService::ALLOW_VIEW_ATTACHMENTS).
--
-- Without this, every existing forum would silently start hiding
-- attachments from everyone the moment this bit is introduced, since a
-- brand-new bit is simply absent from any previously-stored bitmask.
-- Preserves today's actual behavior (anyone who can read a forum can
-- already see its attachments) on upgrade; admins can turn it off per
-- forum/group afterward via the "View attachments" checkbox. New forums
-- created after this ships are unaffected — they start at pub_perms =
-- reg_perms = 0 like every other permission, same as today.
-- -------------------------------------------------------------------------
UPDATE {PREFIX}_forums SET pub_perms = pub_perms | 16 WHERE pub_perms & 1 = 1;
UPDATE {PREFIX}_forums SET reg_perms = reg_perms | 16 WHERE reg_perms & 1 = 1;
