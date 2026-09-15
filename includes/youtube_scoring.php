<?php
require_once __DIR__ . '/gemini_client.php';

/**
 * YouTube pipeline, Layer 2: the AI qualifying pass. One Gemini call per
 * channel does BOTH scoring and the qualify/reject/needs_review verdict
 * in one shot — deliberately NOT a separate keyword-based auto-reject
 * pre-filter before this. A hardcoded "corporate/news" keyword list
 * risks false-positive rejecting a genuinely small creator whose About
 * text happens to mention a tangential word, and it's another editable
 * settings list to build/maintain. The AI already sees the full channel
 * description, subscriber count, and video count — it can judge "is
 * this obviously a media company" with actual context, more reliably
 * than string matching. Flagging this as a deliberate simplification
 * from the original plan's "auto-reject red flags, config-editable
 * list" — worth revisiting if the AI's judgment on this turns out
 * unreliable once you see real verdicts.
 *
 * Same A/B/C/D grade cutoffs as Instagram's quality score (90/75/60),
 * for consistency across both pipelines' dashboards.
 */

const YOUTUBE_SCORE_BUCKETS = [
    'audience'     => ['field' => 'audience_score',     'max' => 20],
    'engagement'   => ['field' => 'engagement_score',   'max' => 20],
    'monetization' => ['field' => 'monetization_score', 'max' => 25],
    'content'      => ['field' => 'content_score',      'max' => 15],
    'opportunity'  => ['field' => 'opportunity_score',  'max' => 20],
];

function build_youtube_scoring_prompt(array $channel, array $videoTitles, array $videoDescriptions): string
{
    $subs = $channel['subscribers'] !== null ? number_format((int) $channel['subscribers']) : 'unknown';
    $totalVideos = $channel['total_videos'] ?? 'unknown';
    $totalViews = $channel['total_views'] !== null ? number_format((int) $channel['total_views']) : 'unknown';
    $verified = !empty($channel['is_verified']) ? 'yes' : 'no';
    $country = $channel['country'] ?: 'unknown';
    $description = $channel['channel_description'] ?: '(empty About page)';

    $titlesBlock = $videoTitles ? implode("\n", array_map(fn($t) => "- $t", $videoTitles)) : '(no recent videos found)';
    // Descriptions can be long — truncate per-video to keep the prompt's
    // token cost predictable regardless of how verbose a channel's video
    // descriptions happen to be.
    $descBlock = $videoDescriptions
        ? implode("\n---\n", array_map(fn($d) => mb_substr($d, 0, 300), $videoDescriptions))
        : '(no descriptions available)';

    return <<<PROMPT
You are qualifying a YouTube channel as a prospective client for a creator-monetization consulting business. The business helps under-monetized creators build products (courses, coaching, digital products, paid communities) around an existing audience.

CHANNEL DATA
Name: {$channel['channel_name']}
Subscribers: {$subs}
Total videos: {$totalVideos}
Total views: {$totalViews}
YouTube-verified: {$verified}
Country: {$country}
About page text: {$description}

RECENT VIDEO TITLES
{$titlesBlock}

RECENT VIDEO DESCRIPTIONS (truncated)
{$descBlock}

SCORING TASK
Score this channel out of 100 total, across 5 buckets. For each bucket give a whole-number score from 0 up to that bucket's max, plus one short sentence of reasoning.

1. Audience Fit (0-20 max): Is the subscriber count in a genuinely reachable range for a small consulting outreach effort — not so small it barely matters, not so large the creator already has a full team and is inundated with pitches? Bigger is NOT automatically better.
2. Engagement (0-20 max): Judge from views relative to subscriber count and any sign of an active, responsive audience — a live audience, not just a stale subscriber count.
3. Monetization Gap (0-25 max, the MOST important bucket): Does this creator appear to lack an obvious existing product, course, coaching offer, or paid community? No visible monetization scores HIGH here — that's the opportunity. A creator clearly already running a full monetized business (obvious storefront, "buy my course" links everywhere, a visible team) scores LOW — they're not a prospect.
4. Content Quality (0-15 max): Does this channel teach something repeatable? Do titles suggest real, consistent expertise rather than reactive/trend-chasing content?
5. Opportunity Clarity (0-20 max): Can you name ONE specific, concrete gap? Vague answers ("could probably monetize more") score low. Specific answers ("40K engaged subscribers asking budgeting questions in comments, no product or email list") score high.

Also decide a verdict:
- "reject" for an obvious corporate/news/media-company channel, a celebrity/public-figure channel with a visible existing team, or a channel that's clearly already a fully monetized business — regardless of score.
- "needs_review" when the data here is too thin to judge confidently (e.g. an empty About page AND no video titles).
- Otherwise "qualify" or "reject" based on whether the total score would reasonably exceed 50 out of 100.

Respond with ONLY this exact JSON shape — no markdown fences, no commentary before or after:
{
  "audience_score": <0-20>,
  "engagement_score": <0-20>,
  "monetization_score": <0-25>,
  "content_score": <0-15>,
  "opportunity_score": <0-20>,
  "reasoning": {
    "audience": "<one sentence>",
    "engagement": "<one sentence>",
    "monetization": "<one sentence>",
    "content": "<one sentence>",
    "opportunity": "<one sentence>"
  },
  "product_potential": "<1-2 sentences: what product/offer could this creator plausibly sell>",
  "pain_opportunity": "<1-2 sentences: the specific gap or pain point>",
  "verdict": "qualify" | "reject" | "needs_review"
}
PROMPT;
}

/**
 * Calls Gemini and parses its response into a clamped, safe result.
 * Returns ['parse_failed' => true, 'errors' => [...]] (never a partial/
 * garbage score record) if the response can't be parsed into the
 * expected shape at all — caller checks 'errors' to tell an ordinary
 * one-off parse hiccup from an actual quota/rate-limit message (worth
 * stopping the whole batch for) apart.
 */
function score_youtube_channel(array $channel, array $videoTitles, array $videoDescriptions): array
{
    $prompt = build_youtube_scoring_prompt($channel, $videoTitles, $videoDescriptions);
    $result = call_gemini($prompt, 800);

    if ($result['text'] === null) {
        return ['parse_failed' => true, 'errors' => $result['errors']];
    }

    // Defensive: strip markdown code fences if the model wraps its JSON
    // in them despite being told not to (seen occasionally from Gemini
    // on other prompts in this codebase).
    $text = trim($result['text']);
    $text = preg_replace('/^```(?:json)?\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $data = json_decode($text, true);
    if (!is_array($data)) {
        return ['parse_failed' => true, 'errors' => ['Could not parse JSON from response: ' . mb_substr($text, 0, 200)]];
    }

    $scores = [];
    $total = 0;
    foreach (YOUTUBE_SCORE_BUCKETS as $key => $bucket) {
        $val = (int) ($data[$bucket['field']] ?? 0);
        $val = max(0, min($bucket['max'], $val)); // clamp — never trust the model to stay in range
        $scores[$bucket['field']] = $val;
        $total += $val;
    }

    $grade = null;
    if ($total >= 90) $grade = 'A';
    elseif ($total >= 75) $grade = 'B';
    elseif ($total >= 60) $grade = 'C';
    else $grade = 'D';

    $verdict = $data['verdict'] ?? 'needs_review';
    if (!in_array($verdict, ['qualify', 'reject', 'needs_review'], true)) {
        $verdict = 'needs_review';
    }

    return [
        'parse_failed'        => false,
        'scores'              => $scores,
        'total_score'         => $total,
        'grade'               => $grade,
        'reasoning'           => is_array($data['reasoning'] ?? null) ? $data['reasoning'] : [],
        'product_potential'   => $data['product_potential'] ?? null,
        'pain_opportunity'    => $data['pain_opportunity'] ?? null,
        'verdict'             => $verdict,
    ];
}
