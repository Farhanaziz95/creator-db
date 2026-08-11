-- Round 8: adjustable composite quality score.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

CREATE TABLE IF NOT EXISTS score_weights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_key VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    weight DECIMAL(4,2) DEFAULT 1.00,   -- how much this metric counts, relative to the others
    is_active TINYINT(1) DEFAULT 1,     -- turn a metric off entirely without losing your weight setting
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- All weights start equal (1.00) — adjust them once you have a feel for
-- which signals actually matter most for your vetting.
INSERT INTO score_weights (metric_key, label, weight) VALUES
('engagement_tier', 'Engagement Tier (High/Above/Average/Below/Low, benchmarked by follower size)', 1.00),
('posting_consistency', 'Posting Consistency (healthy vs inactive/spammy)', 1.00),
('growth_trend', 'Growth Trend (grew 15%+ since last import)', 1.00),
('niche_assigned', 'Niche Assigned (has a known niche vs unassigned)', 1.00);
