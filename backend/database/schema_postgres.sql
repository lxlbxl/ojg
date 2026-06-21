-- OJG Herbal Health Assessment System Database Schema
-- PostgreSQL Database Structure

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    age INTEGER,
    gender VARCHAR(20),
    type VARCHAR(20) DEFAULT 'lead',
    name VARCHAR(255),
    username VARCHAR(50),
    password_hash VARCHAR(255),
    condition_type VARCHAR(50),
    marketing_consent INTEGER DEFAULT 0,
    data_consent INTEGER DEFAULT 1,
    plan_duration INTEGER DEFAULT 0,
    plan_start_date TIMESTAMP,
    plan_end_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role TEXT DEFAULT 'admin',
    permissions TEXT DEFAULT '["all"]',
    last_login TIMESTAMP,
    login_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT 'active'
);

-- PCOS Assessments
CREATE TABLE IF NOT EXISTS pcos_assessments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    age INTEGER NOT NULL,
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(4,2),
    menstrual_cycle TEXT,
    symptoms TEXT,
    lifestyle_factors TEXT,
    medical_history TEXT,
    current_medications TEXT,
    assessment_score INTEGER,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Acne Assessments
CREATE TABLE IF NOT EXISTS acne_assessments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    age INTEGER NOT NULL,
    skin_type TEXT,
    acne_severity TEXT,
    acne_type TEXT,
    triggers TEXT,
    current_treatment TEXT,
    skincare_routine TEXT,
    lifestyle_factors TEXT,
    assessment_score INTEGER,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Weight Management Assessments
CREATE TABLE IF NOT EXISTS weight_assessments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    age INTEGER NOT NULL,
    current_weight DECIMAL(5,2) NOT NULL,
    target_weight DECIMAL(5,2),
    height DECIMAL(5,2) NOT NULL,
    current_bmi DECIMAL(4,2),
    target_bmi DECIMAL(4,2),
    activity_level TEXT,
    diet_preferences TEXT,
    health_conditions TEXT,
    weight_history TEXT,
    goals TEXT,
    assessment_score INTEGER,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales/Orders table
CREATE TABLE IF NOT EXISTS sales (
    id VARCHAR(50) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    assessment_id VARCHAR(50),
    transaction_id VARCHAR(100),
    tx_ref VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    product_type VARCHAR(50),
    product_name VARCHAR(255),
    amount DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'NGN',
    payment_status VARCHAR(20) DEFAULT 'pending',
    payment_method VARCHAR(50),
    customer_data TEXT,
    product_data TEXT,
    tracking_data TEXT,
    ip_address TEXT,
    user_agent TEXT,
    referrer TEXT,
    notes TEXT,
    plan_duration INTEGER DEFAULT 0,
    plan_start_date TIMESTAMP,
    plan_end_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contact/Lead forms
CREATE TABLE IF NOT EXISTS contacts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    category TEXT,
    subject VARCHAR(255),
    message TEXT,
    source VARCHAR(50),
    status TEXT DEFAULT 'new',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System logs for admin actions
CREATE TABLE IF NOT EXISTS admin_logs (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL REFERENCES admin_users(id),
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INTEGER,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type TEXT DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Generic Assessments Table
CREATE TABLE IF NOT EXISTS assessments (
    id VARCHAR(50) PRIMARY KEY,
    user_id INTEGER,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    assessment_type VARCHAR(50),
    assessment_data TEXT,
    score DECIMAL(5,2),
    recommendations TEXT,
    tracking_data TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    status VARCHAR(20) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Webhook Queue Table
CREATE TABLE IF NOT EXISTS webhook_queue (
    id SERIAL PRIMARY KEY,
    webhook_id VARCHAR(50) NOT NULL,
    event VARCHAR(50) NOT NULL,
    payload TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    last_attempt TIMESTAMP,
    next_attempt TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Funnel Tracking Table
CREATE TABLE IF NOT EXISTS funnel_tracking (
    id SERIAL PRIMARY KEY,
    session_id VARCHAR(100),
    user_id INTEGER,
    email VARCHAR(255),
    funnel_name VARCHAR(50),
    step_name VARCHAR(100),
    event_type VARCHAR(50),
    metadata TEXT,
    url TEXT,
    experiment_id INTEGER,
    variant_id INTEGER,
    revenue DECIMAL(10,2),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Nurture Queue Table
CREATE TABLE IF NOT EXISTS nurture_queue (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    pcos_type VARCHAR(50),
    confidence VARCHAR(20),
    funnel VARCHAR(50) DEFAULT 'pcos',
    session_id VARCHAR(100),
    assessment_completed_at TIMESTAMP,
    sales_page_viewed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending',
    nurture_step INTEGER DEFAULT 0,
    next_contact_at TIMESTAMP,
    last_contacted_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- AI Generation Logs
CREATE TABLE IF NOT EXISTS ai_generation_logs (
    id SERIAL PRIMARY KEY,
    request_type VARCHAR(50),
    input_data TEXT,
    output_data TEXT,
    metadata TEXT,
    tokens_used INTEGER,
    cost DECIMAL(10,4),
    status VARCHAR(20) DEFAULT 'completed',
    user_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Consumed tokens (rate limiting)
CREATE TABLE IF NOT EXISTS consumed_tokens (
    token VARCHAR(64) PRIMARY KEY,
    consumed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Login attempts
CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rate limits bucket
CREATE TABLE IF NOT EXISTS rate_limits_bucket (
    ip_address VARCHAR(45) PRIMARY KEY,
    tokens DECIMAL(10,4) NOT NULL,
    last_updated INTEGER NOT NULL
);

-- Member Schema tables
CREATE TABLE IF NOT EXISTS member_profiles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    condition_type VARCHAR(50),
    pcos_type VARCHAR(50),
    age INTEGER,
    weight DECIMAL(6,2),
    height DECIMAL(6,2),
    bmi DECIMAL(5,2),
    allergies TEXT,
    dietary_preferences TEXT,
    cycle_length INTEGER DEFAULT 28,
    last_period_date DATE,
    treatment_plan TEXT,
    daily_log TEXT,
    progress_data TEXT,
    preferences TEXT,
    subscription_status VARCHAR(20) DEFAULT 'inactive',
    subscription_tier VARCHAR(50),
    subscription_expiry TIMESTAMP,
    payment_status VARCHAR(20) DEFAULT 'not_paid',
    start_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- A/B Engine Tables
-- =======================
CREATE TABLE IF NOT EXISTS experiments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'draft',
    traffic_allocation DECIMAL(5,2) DEFAULT 1.00,
    min_sample_size INTEGER DEFAULT 100,
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    winner_criteria VARCHAR(50) DEFAULT 'conversion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS experiment_variants (
    id SERIAL PRIMARY KEY,
    experiment_id INTEGER NOT NULL REFERENCES experiments(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    config_data TEXT,
    is_control BOOLEAN DEFAULT false,
    weight DECIMAL(5,2) DEFAULT 1.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS experiment_assignments (
    id SERIAL PRIMARY KEY,
    experiment_id INTEGER NOT NULL REFERENCES experiments(id) ON DELETE CASCADE,
    variant_id INTEGER NOT NULL REFERENCES experiment_variants(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(100),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS experiment_events (
    id SERIAL PRIMARY KEY,
    experiment_id INTEGER NOT NULL REFERENCES experiments(id) ON DELETE CASCADE,
    variant_id INTEGER NOT NULL REFERENCES experiment_variants(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(100),
    event_type VARCHAR(50) NOT NULL,
    event_data TEXT,
    revenue DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =======================
-- Indexes
-- =======================
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
CREATE INDEX IF NOT EXISTS idx_pcos_user_id ON pcos_assessments(user_id);
CREATE INDEX IF NOT EXISTS idx_acne_user_id ON acne_assessments(user_id);
CREATE INDEX IF NOT EXISTS idx_weight_user_id ON weight_assessments(user_id);
CREATE INDEX IF NOT EXISTS idx_sales_user_id ON sales(user_id);
CREATE INDEX IF NOT EXISTS idx_contacts_status ON contacts(status);
CREATE INDEX IF NOT EXISTS idx_contacts_created_at ON contacts(created_at);
CREATE INDEX IF NOT EXISTS idx_assessments_email ON assessments(email);
CREATE INDEX IF NOT EXISTS idx_assessments_type ON assessments(assessment_type);
CREATE INDEX IF NOT EXISTS idx_webhook_queue_status ON webhook_queue(status);
CREATE INDEX IF NOT EXISTS idx_webhook_queue_next_attempt ON webhook_queue(next_attempt);
CREATE INDEX IF NOT EXISTS idx_tracking_funnel ON funnel_tracking(funnel_name);
CREATE INDEX IF NOT EXISTS idx_tracking_session ON funnel_tracking(session_id);
CREATE INDEX IF NOT EXISTS idx_tracking_created ON funnel_tracking(created_at);
CREATE INDEX IF NOT EXISTS idx_nurture_email ON nurture_queue(email);
CREATE INDEX IF NOT EXISTS idx_nurture_status ON nurture_queue(status);
CREATE INDEX IF NOT EXISTS idx_nurture_next_contact ON nurture_queue(next_contact_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ident ON login_attempts(identifier);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address);

-- =======================
-- Member Area Tables
-- =======================

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

-- Activity logs (meal, movement, herbal tea completion tracking)
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

-- =======================
-- Seed Data
-- =======================
INSERT INTO admin_users (username, email, password_hash, full_name, role)
VALUES ('admin', 'admin@ojgherbal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin')
ON CONFLICT (username) DO NOTHING;

INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'OJG Herbal Health Assessment', 'string', 'Website name'),
('site_email', 'info@ojgherbal.com', 'string', 'Contact email'),
('site_phone', '+1-234-567-8900', 'string', 'Contact phone'),
('assessment_scoring_enabled', '1', 'boolean', 'Enable assessment scoring system'),
('email_notifications', '1', 'boolean', 'Send email notifications for new submissions'),
('max_assessments_per_user', '5', 'integer', 'Maximum assessments per user per day')
ON CONFLICT (setting_key) DO NOTHING;

-- =======================
-- Triggers for updated_at
-- =======================
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
DECLARE
    tbl TEXT;
BEGIN
    FOR tbl IN
        SELECT table_name FROM information_schema.columns
        WHERE table_schema = 'public' AND column_name = 'updated_at'
    LOOP
        EXECUTE format(
            'CREATE OR REPLACE TRIGGER trg_%I_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION update_timestamp()',
            tbl, tbl
        );
    END LOOP;
END;
$$ LANGUAGE plpgsql;
