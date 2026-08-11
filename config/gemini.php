<?php
// Get a free key at https://aistudio.google.com/apikey — no credit card needed.
// Model names get deprecated periodically (2.5-flash/2.5-flash-lite died
// mid-build). Confirmed working via live test on your AI Studio account:
// Gemini 3.5 Flash and Gemini 3.1 Flash Lite. Added "gemini-flash-latest"
// as a third fallback — it's an alias Google keeps pointed at whatever
// their current flash model is, so it should survive future renames
// without needing another manual fix like this one.
return [
    'api_key'  => 'YOUR_GEMINI_API_KEY_HERE',
    'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
    'models' => [
        'gemini-3.5-flash',
        'gemini-3.1-flash-lite',
        'gemini-flash-latest',
    ],
];
