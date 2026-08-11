-- Run this in phpMyAdmin to add Flozy integration support.
-- In phpMyAdmin: select creator_db -> SQL tab -> paste this -> Go.

-- Maps our profiles to Flozy leads. Deleting a row here just means
-- "no longer pushed" — the actual profile/snapshot history is untouched.
CREATE TABLE IF NOT EXISTS flozy_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL UNIQUE,
    flozy_lead_id INT NOT NULL,
    pushed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);

-- Your editable default task list — managed from the Settings page, never
-- from code. Every active template gets attached as a Flozy task whenever
-- a profile is pushed.
CREATE TABLE IF NOT EXISTS flozy_task_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    status TINYINT DEFAULT 1,        -- 1 todo, 2 in progress, 3 completed, 4 in review
    priority TINYINT DEFAULT 2,      -- 1 low, 2 medium, 3 high
    due_offset_days INT NULL,        -- e.g. 3 = due 3 days after push. NULL = no due date
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Starter task list (edit/reorder/delete freely from Settings afterward)
INSERT INTO flozy_task_templates (title, description, status, priority, due_offset_days, sort_order) VALUES
('Send Outreach DM', 'Initial outreach message to the creator.', 1, 3, 0, 1),
('Create Gameplan', 'Draft the collaboration gameplan for this creator.', 1, 3, 1, 2),
('Record Loom Walkthrough', 'Record a Loom explaining the gameplan/offer.', 1, 2, 2, 3),
('Follow Up', 'Follow up if no response yet.', 1, 2, 3, 4);
