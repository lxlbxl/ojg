-- Migration 011: Add location/geo columns to users table
-- For: OJG Herbal geo-adaptive plan engine (v2)
-- Run: php backend/database/migrate.php

-- ── Users table: location columns ───────────────────────────────────────────
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS country_code       CHAR(2)       NULL COMMENT 'ISO 3166-1 alpha-2: NG, KE, RS, PH, DE...',
  ADD COLUMN IF NOT EXISTS country_name       VARCHAR(80)   NULL,
  ADD COLUMN IF NOT EXISTS region_city        VARCHAR(120)  NULL,
  ADD COLUMN IF NOT EXISTS locale             VARCHAR(10)   NULL COMMENT 'IETF locale: en-NG, sr-RS, en-KE...',
  ADD COLUMN IF NOT EXISTS measurement_system ENUM('metric','imperial') NOT NULL DEFAULT 'metric',
  ADD COLUMN IF NOT EXISTS cuisine_pref       VARCHAR(160)  NULL COMMENT 'Free text: vegetarian, halal, Mediterranean...',
  ADD COLUMN IF NOT EXISTS climate_zone       VARCHAR(40)   NULL COMMENT 'tropical, temperate, arid, continental...',
  ADD COLUMN IF NOT EXISTS geo_source         ENUM('user','ip','default') NOT NULL DEFAULT 'default'
                                              COMMENT 'How location was determined';

-- Index for fast per-country analytics
CREATE INDEX IF NOT EXISTS idx_users_country_code ON users(country_code);

-- ── Region profiles cache table ──────────────────────────────────────────────
-- Stores AI-generated region profiles awaiting human curation review
CREATE TABLE IF NOT EXISTS region_profiles (
  id           INTEGER      PRIMARY KEY AUTOINCREMENT,
  country_code CHAR(2)      NOT NULL UNIQUE,
  country_name VARCHAR(80)  NOT NULL,
  profile_data TEXT         NOT NULL COMMENT 'JSON: staples, herbs, sourcing, units, etc.',
  reviewed     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0=AI-generated unreviewed, 1=curated',
  reviewer     VARCHAR(80)  NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- SQLite does not support IF NOT EXISTS for columns — the PHP migrator wraps in try/catch.
-- For MySQL, remove AUTOINCREMENT → AUTO_INCREMENT, and IF NOT EXISTS is supported in MySQL 8.
