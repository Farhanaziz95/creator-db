<?php
return [
    // The sweep "season" — last N days of the month. Default 15, can go
    // higher (e.g. 20) for an even more spread-out, harder-to-fingerprint
    // pattern at the cost of less certainty all leftover budget gets used.
    'window_days' => 15,

    // Chance ANY given day within the window is actually active. A human
    // wouldn't touch this every single day — some days nothing happens.
    'daily_activity_chance' => 0.55,

    // On an active day, a RANDOM percentage of currently-eligible leads
    // gets targeted (not a fixed daily amount) — recalculated fresh each
    // day using whatever's still eligible.
    'daily_percent_min' => 0.05,
    'daily_percent_max' => 0.25,

    // On an active day, only a random SUBSET of active keys is usable —
    // not every key touched on the same day.
    'key_subset_min_pct' => 0.5,
    'key_subset_max_pct' => 0.8,

    // Random pause between each lead's scrape within an active day, in
    // seconds — avoids rapid back-to-back requests even within one run.
    'min_delay_seconds' => 20,
    'max_delay_seconds' => 90,

    // Some budget going unused by month-end is fine — deliberately no
    // "catch-up" logic that forces extra spend near the window's end,
    // since a burst on the final day is exactly the pattern being avoided.
];
