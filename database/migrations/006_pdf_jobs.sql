CREATE TABLE IF NOT EXISTS pdf_jobs (
    id          TEXT PRIMARY KEY,
    project_id  TEXT NOT NULL,
    template_id TEXT,
    status      TEXT NOT NULL DEFAULT 'pending',
    output_url  TEXT,
    input_meta  TEXT NOT NULL DEFAULT '{}',
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
