-- Confirmed via the actor's real input schema page: Reel Scraper's
-- transcript feature is billed per MINUTE OF AUDIO, not per-1000-results
-- like the rest of the cost table assumes. The $/1000 estimate for this
-- actor is therefore a rough blended guess, not a reliable unit — treat
-- Apify's live remaining-budget check as the authoritative signal for this
-- actor specifically, not the cost_per_1000 estimate.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

UPDATE apify_actor_costs
SET notes = 'CAUTION: transcript is billed per MINUTE OF AUDIO, not per-1000 results. This rate is a rough blended estimate only — trust the live remaining-budget check over this number for this actor.'
WHERE actor_key = 'instagram_reel_scraper';
