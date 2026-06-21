-- Migration 014: Add missing body/health columns to member_profiles (PostgreSQL)
-- The original schema only had subscription + meta columns. These columns are
-- written by member_actions.php (update_body_data) and AutomationOrchestrator.

ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS age INTEGER;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS weight DECIMAL(6,2);
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS height DECIMAL(6,2);
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS bmi DECIMAL(5,2);
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS allergies TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS dietary_preferences TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS cycle_length INTEGER DEFAULT 28;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS last_period_date DATE;
