CREATE TABLE IF NOT EXISTS email_configs (
    id                    TEXT PRIMARY KEY,
    project_id            TEXT NOT NULL UNIQUE,
    host                  TEXT NOT NULL DEFAULT '',
    port                  INTEGER NOT NULL DEFAULT 587,
    secure                INTEGER NOT NULL DEFAULT 0,
    from_address          TEXT NOT NULL DEFAULT '',
    from_name             TEXT NOT NULL DEFAULT '',
    encrypted_credentials TEXT NOT NULL DEFAULT '',
    created_at            TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at            TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
