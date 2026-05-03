CREATE TABLE IF NOT EXISTS contacts (
    id         TEXT PRIMARY KEY,
    project_id TEXT NOT NULL,
    email      TEXT NOT NULL,
    name       TEXT,
    meta       TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE (project_id, email)
);
