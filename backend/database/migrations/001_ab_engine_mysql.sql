-- OJG A/B Engine — Migration 001 (MySQL)
-- Adds experiments, variants, assignments, daily metrics, AI insights tables
-- and extends funnel_tracking with experiment_id, variant_id, revenue columns.

-- 1. New experiments table
CREATE TABLE IF NOT EXISTS experiments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funnel_name VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    hypothesis TEXT,
    stage VARCHAR(50) NOT NULL,
    primary_metric VARCHAR(60) NOT NULL,
    reward_type ENUM('binary','revenue') DEFAULT 'binary',
    status ENUM('draft','burn_in','active','concluded','archived') DEFAULT 'draft',
    burn_in_hours INT DEFAULT 48,
    min_exposure_floor DECIMAL(4,3) DEFAULT 0.100,
    min_samples_per_variant INT DEFAULT 1000,
    decision_p_best DECIMAL(4,3) DEFAULT 0.950,
    decision_expected_loss DECIMAL(5,4) DEFAULT 0.0050,
    winner_variant_id INT NULL,
    started_at DATETIME NULL,
    concluded_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exp_funnel (funnel_name),
    INDEX idx_exp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Variants table (per-arm posterior state)
CREATE TABLE IF NOT EXISTS variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('control','structural','element') NOT NULL,
    directory VARCHAR(120) NULL,
    overrides JSON NULL,
    alpha DECIMAL(12,4) DEFAULT 1.0,
    beta  DECIMAL(12,4) DEFAULT 1.0,
    exposures INT DEFAULT 0,
    conversions INT DEFAULT 0,
    revenue_total DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending_approval','active','killed','winner') DEFAULT 'active',
    source ENUM('human','ai_challenger') DEFAULT 'human',
    ai_rationale TEXT NULL,
    is_essential TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_var_exp (experiment_id),
    INDEX idx_var_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Sticky assignment table (authoritative replay)
CREATE TABLE IF NOT EXISTS assignments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    experiment_id INT NOT NULL,
    variant_id INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_experiment (session_id, experiment_id),
    KEY idx_assign_variant (variant_id),
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Daily rolled-up metrics
CREATE TABLE IF NOT EXISTS variant_metrics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT NOT NULL,
    metric_date DATE NOT NULL,
    exposures INT DEFAULT 0,
    assessment_starts INT DEFAULT 0,
    assessment_completes INT DEFAULT 0,
    results_views INT DEFAULT 0,
    plan_selects INT DEFAULT 0,
    checkout_inits INT DEFAULT 0,
    purchases INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0,
    UNIQUE KEY uq_variant_date (variant_id, metric_date),
    FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. AI insight log
CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NULL,
    funnel_name VARCHAR(50),
    insight_type ENUM('diagnostic','suggestion','challenger_rationale','compliance'),
    content JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_insight_exp (experiment_id),
    INDEX idx_insight_funnel (funnel_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Extend existing event store
ALTER TABLE funnel_tracking
    ADD COLUMN experiment_id INT NULL,
    ADD COLUMN variant_id INT NULL,
    ADD COLUMN revenue DECIMAL(10,2) NULL,
    ADD INDEX idx_ft_variant (variant_id, event_type, created_at);

-- 7. Assignment failure log (for monitoring MySQL outages)
CREATE TABLE IF NOT EXISTS assignment_failures (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    experiment_id INT,
    error TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Cron run log (skip-if-active gate)
CREATE TABLE IF NOT EXISTS cron_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    script_name VARCHAR(100) NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    status ENUM('running','completed','failed') DEFAULT 'running',
    rows_affected INT DEFAULT 0,
    notes TEXT,
    UNIQUE KEY uq_active_run (script_name, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Experiment events (server-side authoritative log; spec §4-5)
-- This is the authoritative event store; funnel_tracking.experiment_id links back.
-- Keeping it separate avoids lock contention on the hot funnel_tracking table.
CREATE TABLE IF NOT EXISTS experiment_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    experiment_id INT NULL,
    variant_id INT NULL,
    funnel_name VARCHAR(50) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    revenue DECIMAL(10,2) DEFAULT 0,
    metadata TEXT,
    user_agent VARCHAR(500),
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ee_session (session_id),
    INDEX idx_ee_exp_var (experiment_id, variant_id, event_type),
    INDEX idx_ee_funnel_time (funnel_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
