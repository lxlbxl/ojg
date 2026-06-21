-- Migration 012: Add missing member-area tables (PostgreSQL)
-- Run this on the live ojg database to fix "relation does not exist" errors

-- Auth tokens (auto-login after purchase)
CREATE TABLE IF NOT EXISTS auth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_user ON auth_tokens(user_id);

-- Daily plans (AI-generated)
CREATE TABLE IF NOT EXISTS daily_plans (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plan_date DATE NOT NULL,
    plan_data TEXT,
    trigger_type VARCHAR(20) DEFAULT 'auto',
    is_completed BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, plan_date)
);
CREATE INDEX IF NOT EXISTS idx_daily_plans_user_date ON daily_plans(user_id, plan_date);

-- Meal swaps
CREATE TABLE IF NOT EXISTS meal_swaps (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    original_meal TEXT,
    new_meal TEXT,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Symptom logs
CREATE TABLE IF NOT EXISTS symptom_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    log_date DATE NOT NULL,
    symptoms TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, log_date)
);

-- Weight logs
CREATE TABLE IF NOT EXISTS weight_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    log_date DATE NOT NULL,
    weight DECIMAL(5,2),
    unit VARCHAR(10) DEFAULT 'kg',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_weight_logs_date ON weight_logs(user_id, log_date);

-- System prompts (AI personality/rules)
CREATE TABLE IF NOT EXISTS system_prompts (
    id SERIAL PRIMARY KEY,
    prompt_key VARCHAR(50) UNIQUE NOT NULL,
    prompt_text TEXT NOT NULL,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO system_prompts (prompt_key, prompt_text, description)
VALUES ('pcos_meal_planner', 'You are an expert PCOS Nutritionist. Create a meal plan for a user with [PCOS_TYPE].', 'Base prompt for meal planning')
ON CONFLICT (prompt_key) DO NOTHING;

-- User tracking (weight, water, steps, sleep)
CREATE TABLE IF NOT EXISTS user_tracking (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    metric_type TEXT NOT NULL,
    metric_value REAL NOT NULL,
    unit TEXT,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_user_tracking ON user_tracking(user_id, metric_type, logged_at);

-- User preferences (AI personalization, UI settings)
CREATE TABLE IF NOT EXISTS user_preferences (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pref_key TEXT NOT NULL,
    pref_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, pref_key)
);

-- Add missing columns to ai_generation_logs if they don't exist yet
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='ai_generation_logs' AND column_name='action') THEN
        ALTER TABLE ai_generation_logs ADD COLUMN action VARCHAR(50);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='ai_generation_logs' AND column_name='target_date') THEN
        ALTER TABLE ai_generation_logs ADD COLUMN target_date DATE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='ai_generation_logs' AND column_name='duration_ms') THEN
        ALTER TABLE ai_generation_logs ADD COLUMN duration_ms INTEGER;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='ai_generation_logs' AND column_name='error_message') THEN
        ALTER TABLE ai_generation_logs ADD COLUMN error_message TEXT;
    END IF;
END$$;
