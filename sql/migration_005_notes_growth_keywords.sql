-- Round 7: notes field, growth-alert tracking, and moving keyword rules
-- into the database so they're editable from Settings.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE profiles
    ADD COLUMN notes TEXT NULL;

ALTER TABLE profile_snapshots
    ADD COLUMN follower_growth_pct DECIMAL(6,2) NULL,
    ADD COLUMN is_trending TINYINT(1) DEFAULT 0;

CREATE TABLE IF NOT EXISTS keyword_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    niche_name VARCHAR(150) NOT NULL,
    keyword VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Carries over the rules that used to be hardcoded in keyword_rules.php
INSERT INTO keyword_rules (niche_name, keyword) VALUES
('Health & Nutrition', 'nutrition'), ('Health & Nutrition', 'gut health'), ('Health & Nutrition', 'diet'),
('Health & Nutrition', 'wellness'), ('Health & Nutrition', 'holistic'), ('Health & Nutrition', 'nutritionist'), ('Health & Nutrition', 'digestive'),
('Fitness', 'fitness'), ('Fitness', 'workout'), ('Fitness', 'gym'), ('Fitness', 'personal trainer'), ('Fitness', 'strength coach'), ('Fitness', 'bodybuilding'),
('Parenting & Family', 'mom'), ('Parenting & Family', 'mother'), ('Parenting & Family', 'motherhood'), ('Parenting & Family', 'parenting'), ('Parenting & Family', 'baby'), ('Parenting & Family', 'toddler'), ('Parenting & Family', 'homeschool'),
('Beauty & Skincare', 'skincare'), ('Beauty & Skincare', 'makeup'), ('Beauty & Skincare', 'beauty'), ('Beauty & Skincare', 'cosmetics'), ('Beauty & Skincare', 'esthetician'),
('Fashion', 'fashion'), ('Fashion', 'stylist'), ('Fashion', 'wardrobe'), ('Fashion', 'outfit inspo'),
('Food & Cooking', 'recipe'), ('Food & Cooking', 'cooking'), ('Food & Cooking', 'chef'), ('Food & Cooking', 'food blogger'), ('Food & Cooking', 'baking'),
('Business & Entrepreneurship', 'entrepreneur'), ('Business & Entrepreneurship', 'founder'), ('Business & Entrepreneurship', 'ceo'), ('Business & Entrepreneurship', 'business coach'), ('Business & Entrepreneurship', 'startup'),
('Travel', 'travel'), ('Travel', 'wanderlust'), ('Travel', 'digital nomad'), ('Travel', 'explorer'),
('Finance', 'finance'), ('Finance', 'investing'), ('Finance', 'money coach'), ('Finance', 'financial advisor'), ('Finance', 'crypto'),
('Art & Design', 'artist'), ('Art & Design', 'illustrator'), ('Art & Design', 'designer'), ('Art & Design', 'painter'), ('Art & Design', 'creative director'),
('Spirituality & Faith', 'faith'), ('Spirituality & Faith', 'christian'), ('Spirituality & Faith', 'islamic'), ('Spirituality & Faith', 'spiritual'), ('Spirituality & Faith', 'quran'), ('Spirituality & Faith', 'muslim'),
('Education & Coaching', 'coach'), ('Education & Coaching', 'mentor'), ('Education & Coaching', 'educator'), ('Education & Coaching', 'course creator'),
('Tech', 'developer'), ('Tech', 'programmer'), ('Tech', 'software engineer'), ('Tech', 'saas founder');
