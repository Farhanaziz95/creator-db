<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/content_prompt_engine.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$contentType    = $input['content_type'] ?? '';
$campaignStage  = $input['campaign_stage'] ?? '';
$theme          = trim($input['theme'] ?? '');
$coreIdea       = trim($input['core_idea'] ?? '');
$angle          = trim($input['angle'] ?? '');

if (!in_array($contentType, ['reel', 'story', 'carousel'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid content type.']);
    exit;
}
if (!in_array($campaignStage, ['awareness', 'consideration', 'conversion'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid campaign stage.']);
    exit;
}

$result = build_content_prompt($pdo, $contentType, $campaignStage, $theme, $coreIdea, $angle);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['error' => $result['error']]);
    exit;
}

// Auto-save — every generation gets logged, no manual "save" step needed.
$stmt = $pdo->prepare("
    INSERT INTO content_briefs (content_type, campaign_stage, theme, core_idea, angle, final_prompt)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$contentType, $campaignStage, $theme, $coreIdea, $angle, $result['final_prompt']]);

echo json_encode(['success' => true, 'final_prompt' => $result['final_prompt'], 'brief_id' => $pdo->lastInsertId()]);
