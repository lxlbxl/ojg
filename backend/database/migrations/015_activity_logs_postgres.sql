-- Migration 015: Create activity_logs table for PostgreSQL
-- Tracks meal, movement, and herbal tea activity completion with time windows

CREATE TABLE IF NOT EXISTS activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plan_date DATE NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_name VARCHAR(255),
    scheduled_start TIME NOT NULL DEFAULT '00:00',
    scheduled_end TIME NOT NULL DEFAULT '23:59',
    completed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_date ON activity_logs(user_id, plan_date);
CREATE INDEX IF NOT EXISTS idx_activity_logs_status ON activity_logs(status);
CREATE INDEX IF NOT EXISTS idx_activity_logs_type ON activity_logs(activity_type);
