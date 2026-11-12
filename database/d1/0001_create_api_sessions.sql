CREATE TABLE IF NOT EXISTS api_sessions (
    token_hash TEXT PRIMARY KEY NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS api_sessions_user_id_expires_at_index
    ON api_sessions(user_id, expires_at);

CREATE INDEX IF NOT EXISTS api_sessions_expires_at_index
    ON api_sessions(expires_at);
