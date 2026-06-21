-- Migration 013: Add trigger_type column to existing daily_plans table (PostgreSQL)
-- Run this on the live ojg database if daily_plans already exists without this column

ALTER TABLE daily_plans ADD COLUMN IF NOT EXISTS trigger_type VARCHAR(20) DEFAULT 'auto';
