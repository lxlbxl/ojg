ALTER TABLE users
  ADD COLUMN condition_name VARCHAR(20) NULL,
  ADD COLUMN sub_brand VARCHAR(30) NULL,
  ADD COLUMN assessment_type VARCHAR(60) NULL,
  ADD COLUMN assessment_json JSON NULL,
  ADD COLUMN onboarded_at DATETIME NULL,
  ADD COLUMN streak_count INT DEFAULT 0,
  ADD COLUMN last_active_date DATE NULL;

CREATE TABLE IF NOT EXISTS member_milestones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  milestone_key VARCHAR(60),
  achieved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_milestone (user_id, milestone_key)
);
