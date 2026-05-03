CREATE TABLE IF NOT EXISTS data_store_configs (
    id               TEXT PRIMARY KEY,
    project_id       TEXT NOT NULL UNIQUE,
    store_type       TEXT NOT NULL DEFAULT 'sql_json',
    encrypted_config TEXT NOT NULL DEFAULT '',
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at       TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
