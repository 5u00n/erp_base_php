CREATE TABLE IF NOT EXISTS payments (
    id                       TEXT PRIMARY KEY,
    project_id               TEXT NOT NULL,
    stripe_payment_intent_id TEXT,
    amount_cents             INTEGER NOT NULL,
    currency                 TEXT NOT NULL DEFAULT 'usd',
    status                   TEXT NOT NULL DEFAULT 'requires_payment_method',
    meta                     TEXT NOT NULL DEFAULT '{}',
    created_at               TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
