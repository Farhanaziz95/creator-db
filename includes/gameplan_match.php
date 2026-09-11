<?php
/**
 * Round 35, item #2: bulk gameplan upload with pattern matching. Shared
 * by api/gameplan_bulk_preview.php so the "which line, which pattern"
 * logic lives in exactly one place.
 */

/**
 * First non-blank line of the PDF's extracted text — matching happens
 * against the PDF's own text, not the filename (confirmed in chat).
 */
function extract_first_nonempty_line(string $text): string
{
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            return $trimmed;
        }
    }
    return '';
}

/**
 * Matches a username out of the gameplan's first line, against a
 * Settings-stored prefix (api/gameplan_match_settings.php — editable in
 * Settings, never hardcoded). Handles both confirmed real examples:
 *   "Monetisation Audit: Full Name (@username)"
 *   "Monetisation Audit: @username"
 *
 * A blank configured prefix matches any first line (no prefix
 * requirement) — lets the pattern degrade gracefully to "just find an
 * @username anywhere on the first line" if the setting is ever cleared.
 */
function match_gameplan_first_line(string $firstLine, string $prefix): ?string
{
    $line = trim($firstLine);
    $prefix = trim($prefix);

    if ($prefix !== '') {
        if (stripos($line, $prefix) !== 0) {
            return null; // first line must start with the configured prefix
        }
        $line = trim(substr($line, strlen($prefix)));
    }

    // "Full Name (@username)" — checked first since it's the more
    // specific of the two confirmed patterns.
    if (preg_match('/\(@([A-Za-z0-9._]+)\)/', $line, $m)) {
        return $m[1];
    }
    // "@username" on its own.
    if (preg_match('/@([A-Za-z0-9._]+)/', $line, $m)) {
        return $m[1];
    }

    return null;
}
