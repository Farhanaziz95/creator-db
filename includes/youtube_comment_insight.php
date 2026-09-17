<?php
require_once __DIR__ . '/gemini_client.php';

/**
 * YouTube pipeline, Layer 4 (additive): Comment Insight. Deliberately a
 * SEPARATE prompt, separate parse, and separate storage
 * (youtube_comment_insights) from includes/youtube_scoring.php — this
 * never feeds into or overwrites the main 5-bucket score, total, grade,
 * or verdict. Confirmed: comments get their own scoring (Buying Intent,
 * Pain Point Clarity), appended as additional insight, not blended in.
 */

function build_comment_insight_prompt(array $channel, array $comments): string
{
    $commentsBlock = implode("\n---\n", array_map(fn($c) => mb_substr($c, 0, 300), $comments));

    return <<<PROMPT
You are analyzing YouTube comments for a creator being evaluated as a prospective client for a creator-monetization consulting business.

CHANNEL: {$channel['channel_name']}

COMMENTS (pulled from this channel's recently sampled videos)
{$commentsBlock}

TASK
Read through these comments and identify:
1. Buying Intent (0-100): how strongly do commenters signal they'd pay for something — asking "where can I buy this," "do you have a course," "I'd pay for more of this," "take my money," etc. 0 = no such signals at all, 100 = frequent, explicit buying signals.
2. Pain Point Clarity (0-100): how sharply do commenters articulate a specific, recurring problem or question the creator could plausibly solve with a paid offer. 0 = vague or no clear pain points, 100 = many comments repeating the exact same specific problem.
3. Recurring themes: the 2-4 most repeated topics, questions, or complaints across these comments.
4. A short summary (2-3 sentences) of what commenters are actually asking for or struggling with.

Respond with ONLY this exact JSON shape — no markdown fences, no commentary before or after:
{
  "buying_intent_score": <0-100>,
  "pain_point_clarity_score": <0-100>,
  "recurring_themes": ["<theme 1>", "<theme 2>", "..."],
  "summary": "<2-3 sentences>"
}
PROMPT;
}

/**
 * Same parse-safety contract as score_youtube_channel(): returns
 * ['parse_failed' => true, 'errors' => [...]] rather than a partial
 * record when the response can't be trusted — caller uses 'errors' to
 * tell an ordinary hiccup from a real quota/rate-limit signal apart.
 */
function analyze_comment_insight(array $channel, array $comments): array
{
    if (!$comments) {
        return ['parse_failed' => true, 'errors' => ['No comments to analyze.']];
    }

    $prompt = build_comment_insight_prompt($channel, $comments);
    $result = call_gemini($prompt, 500);

    if ($result['text'] === null) {
        return ['parse_failed' => true, 'errors' => $result['errors']];
    }

    $text = trim($result['text']);
    $text = preg_replace('/^```(?:json)?\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $data = json_decode($text, true);
    if (!is_array($data)) {
        return ['parse_failed' => true, 'errors' => ['Could not parse JSON from response: ' . mb_substr($text, 0, 200)]];
    }

    return [
        'parse_failed'             => false,
        'buying_intent_score'      => max(0, min(100, (int) ($data['buying_intent_score'] ?? 0))),
        'pain_point_clarity_score' => max(0, min(100, (int) ($data['pain_point_clarity_score'] ?? 0))),
        'recurring_themes'         => is_array($data['recurring_themes'] ?? null) ? $data['recurring_themes'] : [],
        'summary'                  => $data['summary'] ?? null,
    ];
}
