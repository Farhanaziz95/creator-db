<?php
/**
 * Tries to guess a niche from bio + full name using keyword rules stored
 * in the `keyword_rules` table (editable from Settings — no code edits
 * needed anymore). Returns null if nothing matches — caller should then
 * queue for AI classification.
 */
function guess_niche_from_keywords(string $bio, string $fullName, PDO $pdo): ?string
{
    $text = strtolower($bio . ' ' . $fullName);

    static $rules = null;
    if ($rules === null) {
        $rules = $pdo->query("SELECT niche_name, keyword FROM keyword_rules ORDER BY id ASC")->fetchAll();
    }

    foreach ($rules as $rule) {
        if (strpos($text, strtolower($rule['keyword'])) !== false) {
            return $rule['niche_name'];
        }
    }

    return null;
}
