-- Logs every day the scheduled sweep runs (active or skipped) so you can
-- audit that the randomization is actually behaving as intended.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

CREATE TABLE IF NOT EXISTS sweep_schedule_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL,
    was_active_day TINYINT(1) DEFAULT 0,
    daily_percentage DECIMAL(5,2) NULL,     -- what % of eligible leads got targeted today
    keys_used TEXT NULL,                     -- comma-separated key labels used today
    leads_attempted INT DEFAULT 0,
    leads_refreshed INT DEFAULT 0,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
