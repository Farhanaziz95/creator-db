<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $colorRules = $pdo->query("SELECT color_rules FROM content_foundation WHERE id = 1")->fetchColumn();
    $masters    = $pdo->query("SELECT * FROM content_master_prompts ORDER BY content_type ASC")->fetchAll();
    $campaigns  = $pdo->query("SELECT * FROM content_campaign_rules ORDER BY content_type ASC, campaign_stage ASC")->fetchAll();
    $structures = $pdo->query("SELECT * FROM content_structure_rules ORDER BY content_type ASC")->fetchAll();

    echo json_encode([
        'color_rules' => $colorRules ?: '',
        'masters'     => $masters,
        'campaigns'   => $campaigns,
        'structures'  => $structures,
    ]);
    exit;
}

if ($method === 'PUT') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $target = $input['target'] ?? '';

    if ($target === 'color_rules') {
        $stmt = $pdo->prepare("UPDATE content_foundation SET color_rules = ? WHERE id = 1");
        $stmt->execute([$input['text'] ?? '']);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($target === 'master') {
        $stmt = $pdo->prepare("UPDATE content_master_prompts SET prompt_text = ? WHERE id = ?");
        $stmt->execute([$input['text'] ?? '', (int) ($input['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($target === 'campaign') {
        $stmt = $pdo->prepare("UPDATE content_campaign_rules SET rule_text = ? WHERE id = ?");
        $stmt->execute([$input['text'] ?? '', (int) ($input['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($target === 'structure') {
        // Upsert — a content type might not have a structure rule row yet.
        $contentType = $input['content_type'] ?? '';
        $stmt = $pdo->prepare("SELECT id FROM content_structure_rules WHERE content_type = ?");
        $stmt->execute([$contentType]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $stmt = $pdo->prepare("UPDATE content_structure_rules SET rule_text = ? WHERE id = ?");
            $stmt->execute([$input['text'] ?? '', $existingId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO content_structure_rules (content_type, rule_text) VALUES (?, ?)");
            $stmt->execute([$contentType, $input['text'] ?? '']);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown target.']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
