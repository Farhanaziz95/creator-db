-- Round: manual-review resolution + server-side sortable quality score.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

-- Lets a flagged reel be resolved: confirmed OK to use, or excluded from
-- future AI prompts entirely (pure b-roll/music, not usable content).
ALTER TABLE post_transcripts
    ADD COLUMN review_status ENUM('pending', 'confirmed', 'excluded') DEFAULT 'pending';

-- Quality score computed and stored server-side (was client-side-only,
-- which made it un-sortable). Recomputed at import, when AI assigns a
-- niche, and via a manual "Recompute All" button after weight changes.
ALTER TABLE profile_snapshots
    ADD COLUMN quality_score DECIMAL(5,2) NULL;

-- New scoring signal: does this profile have any UNRESOLVED manual-review
-- flags? A lead with unreviewed music-only reels shouldn't score the same
-- as one with fully verified content.
INSERT INTO score_weights (metric_key, label, weight) VALUES
('content_verified', 'Content Verified (no unresolved manual-review flags)', 1.00);
