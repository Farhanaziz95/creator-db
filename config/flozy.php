<?php
// Drop your Flozy API key below (Settings → API Keys in Flozy, agency owners only).
// Required scopes for this integration: write:leads, delete:leads, write:tasks
return [
    'api_key'  => 'YOUR_FLOZY_API_KEY_HERE',
    'base_url' => 'https://flozy-backend-1042419926531.us-central1.run.app/api/v1/external',

    // The status slug pushed leads get in Flozy. Flozy auto-creates this
    // status/tab if it doesn't already exist — no setup needed on their side.
    'default_status_name' => 'creator_prospect',

    // Auto-creates an Opportunity (not just a Lead) on push, so it shows
    // up in your Pipeline immediately instead of needing a manual step in
    // Flozy's UI. Opportunities require a stage_id, value, and
    // expected_close_date — none of which we actually know for a fresh
    // lead, so these are reasonable placeholder defaults, editable here.
    'default_opportunity_stage_name' => 'New Lead', // must match a real stage name in your Flozy pipeline
    'default_opportunity_close_days' => 30,          // placeholder expected close date, N days out
    'default_opportunity_confidence' => 50,           // 0-100
];
