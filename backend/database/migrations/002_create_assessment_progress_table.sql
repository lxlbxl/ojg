CREATE TABLE IF NOT EXISTS assessment_progress (
    session_id VARCHAR(100) PRIMARY KEY,
    funnel_name VARCHAR(50) NOT NULL,
    current_step INT DEFAULT 1,
    progress_data JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_funnel_updated (funnel_name, updated_at)
);
