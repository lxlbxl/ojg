-- OJG A/B Engine — Migration 001 (SQLite)
-- Same shape as the MySQL migration, adapted for SQLite.
-- All DECIMAL → REAL, ENUM → TEXT, AUTO_INCREMENT → AUTOINCREMENT.

-- 1. Experiments
CREATE TABLE IF NOT EXISTS experiments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funnel_name TEXT NOT NULL,
    name TEXT NOT NULL,
    hypothesis TEXT,
    stage TEXT NOT NULL,
    primary_metric TEXT NOT NULL,
    reward_type TEXT DEFAULT 'binary',
    status TEXT DEFAULT 'draft',
    burn_in_hours INTEGER DEFAULT 48,
    min_exposure_floor REAL DEFAULT 0.100,
    min_samples_per_variant INTEGER DEFAULT 1000,
    decision_p_best REAL DEFAULT 0.950,
    decision_expected_loss REAL DEFAULT 0.0050,
    winner_variant_id INTEGER,
    started_at TEXT,
    concluded_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_exp_funnel ON experiments(funnel_name);
CREATE INDEX IF NOT EXISTS idx_exp_status ON experiments(status);

-- 2. Variants
CREATE TABLE IF NOT EXISTS variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    experiment_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    directory TEXT,
    overrides TEXT,
    alpha REAL DEFAULT 1.0,
    beta REAL DEFAULT 1.0,
    exposures INTEGER DEFAULT 0,
    conversions INTEGER DEFAULT 0,
    revenue_total REAL DEFAULT 0,
    status TEXT DEFAULT 'active',
    source TEXT DEFAULT 'human',
    ai_rationale TEXT,
    is_essential INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_var_exp ON variants(experiment_id);
CREATE INDEX IF NOT EXISTS idx_var_status ON variants(status);

-- 3. Assignments
CREATE TABLE IF NOT EXISTS assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    experiment_id INTEGER NOT NULL,
    variant_id INTEGER NOT NULL,
    assigned_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (session_id, experiment_id)
);
CREATE INDEX IF NOT EXISTS idx_assign_variant ON assignments(variant_id);

-- 4. Daily metrics
CREATE TABLE IF NOT EXISTS variant_metrics_daily (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    variant_id INTEGER NOT NULL,
    metric_date TEXT NOT NULL,
    exposures INTEGER DEFAULT 0,
    assessment_starts INTEGER DEFAULT 0,
    assessment_completes INTEGER DEFAULT 0,
    results_views INTEGER DEFAULT 0,
    plan_selects INTEGER DEFAULT 0,
    checkout_inits INTEGER DEFAULT 0,
    purchases INTEGER DEFAULT 0,
    revenue REAL DEFAULT 0,
    UNIQUE (variant_id, metric_date)
);

-- 5. AI insights
CREATE TABLE IF NOT EXISTS ai_insights (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    experiment_id INTEGER,
    funnel_name TEXT,
    insight_type TEXT,
    content TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_insight_exp ON ai_insights(experiment_id);
CREATE INDEX IF NOT EXISTS idx_insight_funnel ON ai_insights(funnel_name);

-- 6. Funnel tracking extensions (idempotent via try/catch in app code)
-- SQLite doesn't support IF NOT EXISTS on ADD COLUMN; the app code handles
-- this with PRAGMA table_info() before issuing the ALTER.

-- 7. Assignment failures
CREATE TABLE IF NOT EXISTS assignment_failures (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT,
    experiment_id INTEGER,
    error TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 8. Cron runs
CREATE TABLE IF NOT EXISTS cron_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    script_name TEXT NOT NULL,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT DEFAULT 'running',
    rows_affected INTEGER DEFAULT 0,
    notes TEXT
);
CREATE INDEX IF NOT EXISTS idx_cron_script ON cron_runs(script_name);

-- 9. Experiment events
CREATE TABLE IF NOT EXISTS experiment_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    experiment_id INTEGER,
    variant_id INTEGER,
    funnel_name TEXT NOT NULL,
    event_type TEXT NOT NULL,
    revenue REAL DEFAULT 0,
    metadata TEXT,
    user_agent TEXT,
    ip_address TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ee_session ON experiment_events(session_id);
CREATE INDEX IF NOT EXISTS idx_ee_exp_var ON experiment_events(experiment_id, variant_id, event_type);
CREATE INDEX IF NOT EXISTS idx_ee_funnel_time ON experiment_events(funnel_name, created_at);
