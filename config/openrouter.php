<?php
// Drop your OpenRouter API key below. Get one free at https://openrouter.ai/keys
return [
    'api_key'  => 'YOUR_OPENROUTER_API_KEY_HERE',
    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',

    // Tried top to bottom. If one fails (rate-limited, 500, empty response),
    // the next one is tried automatically. Kept to light/general models since
    // this is short classification, not a coding task.
    'models' => [
        'meta-llama/llama-3.3-70b-instruct:free',
        'google/gemma-3-27b-it:free',
        'deepseek/deepseek-r1-distill-llama-70b:free',
        'openai/gpt-oss-20b:free',
        'openrouter/free', // final catch-all auto-router
    ],

    // Free models are rate-limited (~20 req/min, ~200 req/day account-wide).
    // Keep this conservative so a single scheduled run doesn't burn the daily budget.
    'queue_batch_size' => 10,

    // After this many failed attempts, a profile is marked "Uncategorized"
    // and stops retrying — without this, a genuinely unclassifiable
    // profile (empty bio, every model fails every time) sits in the queue
    // forever, consuming a slot in every future batch indefinitely.
    'max_attempts' => 5,
];
