<?php
require_once __DIR__ . '/prompt_engine.php'; // reuses get_brand_voice() from round 26

/**
 * Assembles a final content-generation prompt from: Brand Voice (shared
 * with the lead-outreach side) + Color Rules + Master Prompt for the
 * content type + Campaign Rule for that type+stage + optional Structure
 * Rule + your brief (Theme/Core Idea/Angle). No AI call happens here —
 * this is pure assembly. The output gets pasted into Iman's tool.
 */
function build_content_prompt(PDO $pdo, string $contentType, string $campaignStage, string $theme, string $coreIdea, string $angle): array
{
    $colorRules = $pdo->query("SELECT color_rules FROM content_foundation WHERE id = 1")->fetchColumn() ?: '';
    $brandVoice = get_brand_voice($pdo);

    $stmt = $pdo->prepare("SELECT prompt_text FROM content_master_prompts WHERE content_type = ?");
    $stmt->execute([$contentType]);
    $master = $stmt->fetchColumn();
    if (!$master) {
        return ['success' => false, 'error' => "No Master Prompt found for content type '{$contentType}'."];
    }
    $master = str_replace('{color_rules}', $colorRules, $master);

    $stmt = $pdo->prepare("SELECT rule_text FROM content_campaign_rules WHERE content_type = ? AND campaign_stage = ?");
    $stmt->execute([$contentType, $campaignStage]);
    $campaignRule = $stmt->fetchColumn();
    if (!$campaignRule) {
        return ['success' => false, 'error' => "No Campaign Rule found for '{$contentType}' / '{$campaignStage}'."];
    }

    $stmt = $pdo->prepare("SELECT rule_text FROM content_structure_rules WHERE content_type = ?");
    $stmt->execute([$contentType]);
    $structureRule = $stmt->fetchColumn(); // optional — only Carousel has one so far

    $briefBlock = "Theme: {$theme}\n\nCore Idea: {$coreIdea}\n\nCampaign: " . ucfirst($campaignStage) . "\n\nAngle: {$angle}";

    $sections = [];
    if ($brandVoice !== '') {
        $sections[] = "BRAND VOICE:\n{$brandVoice}";
    }
    $sections[] = $briefBlock;
    $sections[] = $master;
    $sections[] = strtoupper($campaignStage) . " " . strtoupper($contentType) . " RULE\n======================================================\n\n" . $campaignRule;
    if ($structureRule) {
        $sections[] = "STRUCTURE RULE\n======================================================\n\n" . $structureRule;
    }

    $finalPrompt = implode("\n\n======================================================\n\n", $sections);

    return ['success' => true, 'final_prompt' => $finalPrompt];
}
