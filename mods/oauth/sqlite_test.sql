CREATE TABLE IF NOT EXISTS phorum_oauth_identities (
    oauth_identity_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id           INTEGER NOT NULL,
    provider          TEXT    NOT NULL DEFAULT '',
    provider_user_id  TEXT    NOT NULL DEFAULT '',
    email             TEXT    NOT NULL DEFAULT '',
    date_added        INTEGER NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS phorum_oauth_identities_provider_identity ON phorum_oauth_identities (provider, provider_user_id);
CREATE INDEX IF NOT EXISTS phorum_oauth_identities_user_id ON phorum_oauth_identities (user_id);
