-- Tracks the Flozy task IDs created when a lead is pushed, so we can
-- update them later (e.g. mark "Create Gameplan" complete when the
-- gameplan actually gets uploaded).
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

CREATE TABLE IF NOT EXISTS flozy_lead_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    flozy_task_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);
