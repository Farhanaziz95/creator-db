-- Round 14: message-angle categories, and a guided (human-in-the-loop)
-- follow-up system instead of an autonomous picker.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

CREATE TABLE IF NOT EXISTS niche_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    message_angle TEXT,   -- injected into AI prompts to shape tone/framing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO niche_categories (name, message_angle) VALUES
('Experts', 'Lean into credibility, mentorship, and expertise-elevation — they care about being seen as the authority in their space, not just growing numbers.'),
('Specialists', 'Lean into craft, skill, and concrete results — they care about the work itself and tangible outcomes, not hype or personality.'),
('Entrepreneurs with Personal Brand', 'Lean into scaling, brand growth, and influence/leadership — they care about growing their reach, revenue, and impact.');

ALTER TABLE niches
    ADD COLUMN category_id INT NULL,
    ADD FOREIGN KEY (category_id) REFERENCES niche_categories(id);

-- Every generated follow-up gets logged here — a system keeps records,
-- doesn't just fire messages into the void.
CREATE TABLE IF NOT EXISTS followup_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    followup_type VARCHAR(50) NOT NULL,   -- regular | validation_script | free_value | win_insight | custom_survey
    user_input TEXT NULL,                  -- what YOU said you're sharing this time (not needed for 'regular')
    generated_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);
