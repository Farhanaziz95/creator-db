<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$roundId = (int) ($_GET['round_id'] ?? 0);
if (!$roundId) {
    http_response_code(400);
    echo json_encode(['error' => 'round_id is required.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*,
        s.audience_score, s.engagement_score, s.monetization_score, s.content_score, s.opportunity_score,
        s.total_score, s.grade, s.ai_reasoning, s.ai_product_potential, s.ai_pain_opportunity, s.ai_verdict,
        yfl.flozy_lead_id, yfl.flozy_opportunity_id, yfl.flozy_contact_id, yfl.current_stage,
        ci.buying_intent_score, ci.pain_point_clarity_score, ci.recurring_themes AS comment_recurring_themes,
        ci.summary AS comment_summary, ci.comments_analyzed
    FROM youtube_channels c
    LEFT JOIN youtube_scores s ON s.channel_id = c.id
    LEFT JOIN youtube_flozy_leads yfl ON yfl.channel_id = c.id
    LEFT JOIN youtube_comment_insights ci ON ci.channel_id = c.id
    WHERE c.round_id = ?
    ORDER BY s.total_score DESC, c.imported_at DESC
");
$stmt->execute([$roundId]);
$channels = $stmt->fetchAll();

foreach ($channels as &$c) {
    $c['ai_reasoning'] = $c['ai_reasoning'] ? json_decode($c['ai_reasoning'], true) : null;
    $c['sample_video_titles'] = $c['sample_video_titles'] ? json_decode($c['sample_video_titles'], true) : [];
    $c['social_links'] = $c['social_links'] ? json_decode($c['social_links'], true) : [];
    $c['comment_recurring_themes'] = $c['comment_recurring_themes'] ? json_decode($c['comment_recurring_themes'], true) : [];
}

echo json_encode(['channels' => $channels]);
