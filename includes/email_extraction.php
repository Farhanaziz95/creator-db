<?php
/**
 * Round 35, item #1: pulls an email address out of an Instagram bio.
 * One shared function so the import-time extraction (api/import.php) and
 * the one-time backfill (api/backfill_emails.php) can't drift out of sync
 * with two copies of the same regex.
 *
 * Pattern is intentionally simple/standard (not the full RFC 5322 grammar)
 * — bios are short, informal text, and a stricter pattern risks missing
 * real addresses more than a looser one risks false positives. If a bio
 * has more than one address, the first match wins (most creators only
 * list one contact email; picking the first is the same "don't overthink
 * it" approach this project already takes elsewhere).
 */
function extract_email_from_bio(?string $bio): ?string
{
    if ($bio === null || trim($bio) === '') {
        return null;
    }

    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $bio, $matches)) {
        return $matches[0];
    }

    return null;
}
