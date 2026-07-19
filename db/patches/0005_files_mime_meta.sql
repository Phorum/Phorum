-- -------------------------------------------------------------------------
-- Adds mime_type and meta to {PREFIX}_files, a Phorum 10 addition enabling
-- image previews for attachments (see FileService::store(), MimeDetector,
-- FileMeta). mime_type is detected once at upload time for cheap per-render
-- template branching (FileController::serve() still re-sniffs the actual
-- bytes at serve time as a security check — the stored value is a display
-- hint, not trusted for that). meta is a JSON blob (image width/height
-- today; room for video metadata later), mirroring {PREFIX}_messages.meta /
-- MessageMeta. Applied by SchemaPatcher against databases that already have
-- the {PREFIX}_files table — a fresh install gets these columns directly
-- from db/mysql.sql instead.
-- -------------------------------------------------------------------------
ALTER TABLE {PREFIX}_files ADD COLUMN mime_type varchar(100) NOT NULL DEFAULT '';
ALTER TABLE {PREFIX}_files ADD COLUMN meta mediumtext NULL;
