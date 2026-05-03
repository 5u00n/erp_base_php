CREATE TABLE IF NOT EXISTS push_configs (
    id              TEXT PRIMARY KEY,
    project_id      TEXT NOT NULL UNIQUE,
    provider        TEXT NOT NULL DEFAULT 'fcm',
    encrypted_blob  TEXT NOT NULL DEFAULT '',
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
