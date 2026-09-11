<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$minFollowers  = isset($_GET['min_followers']) ? (int) $_GET['min_followers'] : 0;
$maxFollowers  = ($_GET['max_followers'] ?? '') !== '' ? (int) $_GET['max_followers'] : PHP_INT_MAX;
$minEngagement = isset($_GET['min_engagement']) ? (float) $_GET['min_engagement'] : 0;
$maxEngagement = ($_GET['max_engagement'] ?? '') !== '' ? (float) $_GET['max_engagement'] : 999999;
$nicheId       = ($_GET['niche_id'] ?? '') !== '' ? (int) $_GET['niche_id'] : null;
// 'contacted' | 'not_contacted' | 'ghosted' | '' (all) — only meaningful on flozy view.
// Whitelisted rather than bound as a param since it drives which literal SQL
// fragment gets appended, not a value substituted into the query.
$outreachFilterRaw = $_GET['outreach_status'] ?? '';
$outreachFilter = in_array($outreachFilterRaw, ['contacted', 'not_contacted', 'ghosted'], true) ? $outreachFilterRaw : '';
// Exact pipeline stage name to filter to, or '' for all — only meaningful
// on flozy view. This one IS bound as a param (unlike outreachFilter
// above) since it's an arbitrary real value, not one of a small fixed set
// of literal SQL fragments.
$stageFilter = trim($_GET['stage_filter'] ?? '');
// Whitelisted the same way as outreachFilter — only these 3 windows are offered.
$outreachDaysRaw = $_GET['outreach_days'] ?? '';
$outreachDays = in_array($outreachDaysRaw, ['7', '30', '90'], true) ? (int) $outreachDaysRaw : null;
// 'yes' | 'no' | '' (all) — whitelisted the same way outreachFilter is,
// since it drives which literal SQL fragment gets appended.
$hasEmailRaw = $_GET['has_email'] ?? '';
$hasEmailFilter = in_array($hasEmailRaw, ['yes', 'no'], true) ? $hasEmailRaw : '';
// Round 35, item #8 — 'uploaded' | 'not_uploaded' | '' (all), and
// 'not_verified' | 'verified_only' | 'verified_personalized' | '' (all).
// Both whitelisted for the same reason as hasEmailFilter above. Confirmed
// to apply on Active/Future/Flozy only, not Archived — guarded below by
// $view rather than filtered out here, so the whitelist stays simple.
$gameplanFilterRaw = $_GET['gameplan_filter'] ?? '';
$gameplanFilter = in_array($gameplanFilterRaw, ['uploaded', 'not_uploaded'], true) ? $gameplanFilterRaw : '';
$verifyFilterRaw = $_GET['verify_filter'] ?? '';
$verifyFilter = in_array($verifyFilterRaw, ['not_verified', 'verified_only', 'verified_personalized'], true) ? $verifyFilterRaw : '';
// Round 35, item #12 — 'low' | 'mid' | '' (all, the default "All" sub-tab)
// — only meaningful on the Flozy view, guarded below by $view same as
// gameplan/verify filters above.
$tierFilterRaw = $_GET['priority_tier'] ?? '';
$tierFilter = in_array($tierFilterRaw, ['low', 'mid'], true) ? $tierFilterRaw : '';
$search        = $_GET['search']['value'] ?? '';
$start         = (int) ($_GET['start'] ?? 0);
$length        = (int) ($_GET['length'] ?? 25);
$view          = $_GET['view'] ?? 'active'; // 'active' | 'archived' | 'future' | 'flozy'

$applyThresholds = ($view === 'active');

$sortableColumns = [
    2  => 'p.username',
    3  => 'p.full_name',
    4  => 'n.name',
    5  => 's.followers_count',
    6  => 's.engagement_rate',
    7  => 's.quality_score',
    8  => 's.avg_likes',
    9  => 's.avg_comments',
    10 => 's.posts_per_week',
    11 => 's.biography',
    12 => 'p.external_url',
    13 => 's.imported_at',
];

$orderSql = 's.engagement_rate DESC';
if (isset($_GET['order'][0]['column'])) {
    $colIndex = (int) $_GET['order'][0]['column'];
    $dir      = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    if (isset($sortableColumns[$colIndex])) {
        $orderSql = $sortableColumns[$colIndex] . ' ' . $dir;
    }
}

$latestSnapshotJoin = "
    JOIN (
        SELECT ps1.*
        FROM profile_snapshots ps1
        INNER JOIN (
            SELECT profile_id, MAX(imported_at) AS max_date
            FROM profile_snapshots
            GROUP BY profile_id
        ) latest ON ps1.profile_id = latest.profile_id AND ps1.imported_at = latest.max_date
    ) s ON s.profile_id = p.id
";

if ($view === 'flozy') {
    $baseQuery = "
        FROM profiles p
        $latestSnapshotJoin
        LEFT JOIN niches n ON n.id = p.niche_id
        JOIN flozy_leads fl ON fl.profile_id = p.id
        WHERE p.status != 'archived'
    ";
    // Round 35: archiving no longer deletes the flozy_leads row (see
    // api/archive_flozy_lead.php), so this exclusion is now needed —
    // otherwise an archived-but-still-linked lead would show up here too.
    // Outreach filter — 'ghosted' means contacted but the Flozy pipeline
    // stage is literally named 'Ghosted' (the real stage name in this
    // pipeline, confirmed in round 15/22 notes) — not a guess at a status
    // enum, an actual stage-name match.
    if ($outreachFilter === 'contacted') {
        $baseQuery .= " AND fl.outreached_at IS NOT NULL ";
    } elseif ($outreachFilter === 'not_contacted') {
        $baseQuery .= " AND fl.outreached_at IS NULL ";
    } elseif ($outreachFilter === 'ghosted') {
        $baseQuery .= " AND fl.outreached_at IS NOT NULL AND fl.current_stage = 'Ghosted' ";
    }
    // Pipeline stage filter — combines (AND) with the outreach status
    // filter above, e.g. "Contacted" + "Discovery Call Booked" together.
    if ($stageFilter !== '') {
        $baseQuery .= " AND fl.current_stage = :stageFilter ";
    }
    // Outreach date-range filter — a NULL outreached_at never satisfies
    // this comparison, so pairing this with "Not Contacted" naturally
    // yields zero rows rather than needing a separate guard.
    if ($outreachDays !== null) {
        $baseQuery .= " AND fl.outreached_at >= DATE_SUB(NOW(), INTERVAL :outreachDays DAY) ";
    }
    // Round 35, item #12 — Low/Mid sub-tabs. "All" (tierFilter === '')
    // shows every lead regardless of tier, confirmed — so this only adds
    // a clause for the two specific tiers, never excludes NULL-tier leads
    // by default.
    if ($tierFilter !== '') {
        $baseQuery .= " AND fl.priority_tier = :tierFilter ";
    }
} elseif ($view === 'archived') {
    // Round 35 fix: previously excluded anyone with a flozy_leads row
    // (same NOT IN pattern as active/future below) — but since archiving
    // no longer deletes that row, this exclusion was wrongly hiding
    // Progress/Pipeline Stage/Outreach data for leads archived FROM the
    // Flozy tab. LEFT JOIN instead of excluding: leads archived directly
    // from Active/Future (never pushed) simply get NULLs for fl.* here,
    // which the frontend renders as "never pushed" rather than blank.
    $baseQuery = "
        FROM profiles p
        $latestSnapshotJoin
        LEFT JOIN niches n ON n.id = p.niche_id
        LEFT JOIN flozy_leads fl ON fl.profile_id = p.id
        WHERE p.status = 'archived'
    ";
} else {
    $statusValue = ($view === 'future') ? 'future' : 'active';
    $baseQuery = "
        FROM profiles p
        $latestSnapshotJoin
        LEFT JOIN niches n ON n.id = p.niche_id
        WHERE p.status = :statusValue
          AND p.id NOT IN (SELECT profile_id FROM flozy_leads)
    ";
    if ($applyThresholds) {
        $baseQuery .= " AND s.followers_count >= :minFollowers AND s.followers_count <= :maxFollowers
                         AND s.engagement_rate >= :minEngagement AND s.engagement_rate <= :maxEngagement ";
    }
}

if ($nicheId !== null) {
    $baseQuery .= " AND p.niche_id = :nicheId ";
}

if ($hasEmailFilter === 'yes') {
    $baseQuery .= " AND p.email IS NOT NULL AND p.email != '' ";
} elseif ($hasEmailFilter === 'no') {
    $baseQuery .= " AND (p.email IS NULL OR p.email = '') ";
}

// Round 35, item #8 — confirmed: Active, Future, AND Flozy tabs, not
// Archived (that tab is for reviewing why something got archived, not
// funnel status, and the frontend hides these dropdowns there too).
if (in_array($view, ['active', 'future', 'flozy'], true)) {
    if ($gameplanFilter === 'uploaded') {
        $baseQuery .= " AND EXISTS(SELECT 1 FROM gameplans g WHERE g.profile_id = p.id) ";
    } elseif ($gameplanFilter === 'not_uploaded') {
        $baseQuery .= " AND NOT EXISTS(SELECT 1 FROM gameplans g WHERE g.profile_id = p.id) ";
    }

    // Same three states as the has_gameplan/has_scraped_data/has_message
    // flags already computed below for the progress badges — Not Verified
    // has no post_transcripts at all; Verified Only has transcripts but no
    // 'done' content_analysis_runs (exactly what a Round 35 "Verify Only"
    // run leaves behind); Verified + Personalized has both.
    if ($verifyFilter === 'not_verified') {
        $baseQuery .= " AND NOT EXISTS(SELECT 1 FROM post_transcripts pt WHERE pt.profile_id = p.id) ";
    } elseif ($verifyFilter === 'verified_only') {
        $baseQuery .= "
            AND EXISTS(SELECT 1 FROM post_transcripts pt WHERE pt.profile_id = p.id)
            AND NOT EXISTS(SELECT 1 FROM content_analysis_runs car WHERE car.profile_id = p.id AND car.status = 'done')
        ";
    } elseif ($verifyFilter === 'verified_personalized') {
        $baseQuery .= " AND EXISTS(SELECT 1 FROM content_analysis_runs car WHERE car.profile_id = p.id AND car.status = 'done') ";
    }
}

if ($search !== '') {
    $baseQuery .= " AND (p.username LIKE :search1 OR p.full_name LIKE :search2 OR n.name LIKE :search3) ";
}

function bind_common(PDOStatement $stmt, string $view, bool $applyThresholds, int $minFollowers, int $maxFollowers, float $minEngagement, float $maxEngagement, ?int $nicheId, string $search, string $stageFilter = '', ?int $outreachDays = null, string $tierFilter = ''): void
{
    if ($view === 'active' || $view === 'future') {
        $statusValue = ($view === 'future') ? 'future' : 'active';
        $stmt->bindValue(':statusValue', $statusValue);
        if ($applyThresholds) {
            $stmt->bindValue(':minFollowers', $minFollowers);
            $stmt->bindValue(':maxFollowers', $maxFollowers);
            $stmt->bindValue(':minEngagement', $minEngagement);
            $stmt->bindValue(':maxEngagement', $maxEngagement);
        }
    } elseif ($view === 'flozy') {
        if ($stageFilter !== '') {
            $stmt->bindValue(':stageFilter', $stageFilter);
        }
        if ($outreachDays !== null) {
            $stmt->bindValue(':outreachDays', $outreachDays, PDO::PARAM_INT);
        }
        if ($tierFilter !== '') {
            $stmt->bindValue(':tierFilter', $tierFilter);
        }
    }
    // 'archived' view's status condition is a hardcoded literal in the
    // query now (no leftover flozy_leads exclusion to parameterize
    // either) — nothing view-specific to bind for it.
    if ($nicheId !== null) {
        $stmt->bindValue(':nicheId', $nicheId, PDO::PARAM_INT);
    }
    if ($search !== '') {
        $like = "%$search%";
        $stmt->bindValue(':search1', $like);
        $stmt->bindValue(':search2', $like);
        $stmt->bindValue(':search3', $like);
    }
}

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) $baseQuery");
    bind_common($countStmt, $view, $applyThresholds, $minFollowers, $maxFollowers, $minEngagement, $maxEngagement, $nicheId, $search, $stageFilter, $outreachDays, $tierFilter);
    $countStmt->execute();
    $totalFiltered = (int) $countStmt->fetchColumn();

    $extraSelect = '';
    if (in_array($view, ['active', 'flozy', 'future', 'archived'], true)) {
        $extraSelect .= "
            , EXISTS(SELECT 1 FROM gameplans g WHERE g.profile_id = p.id) AS has_gameplan
            , EXISTS(SELECT 1 FROM post_transcripts pt WHERE pt.profile_id = p.id) AS has_scraped_data
            , EXISTS(SELECT 1 FROM content_analysis_runs car WHERE car.profile_id = p.id AND car.status = 'done') AS has_message
            , (SELECT COUNT(*) FROM post_transcripts pt2 WHERE pt2.profile_id = p.id AND pt2.needs_manual_review = 1) AS manual_review_count
        ";
    }
    if ($view === 'flozy' || $view === 'archived') {
        $extraSelect .= "
            , fl.flozy_lead_id
            , fl.flozy_opportunity_id
            , fl.pushed_at AS flozy_pushed_at
            , fl.current_stage
            , fl.current_stage_tag
            , fl.stage_synced_at
            , fl.outreached_at
            , fl.flozy_contact_id
            , fl.priority_tier
        ";
    }

    $dataStmt = $pdo->prepare("
        SELECT p.id, p.username, p.full_name, p.external_url, p.email, n.name AS niche, n.id AS niche_id, p.niche_source, p.status, p.notes,
               s.followers_count, s.engagement_rate, s.quality_score, s.avg_likes, s.avg_comments,
               s.biography, s.imported_at, s.posts_per_week, s.is_inconsistent, s.pinned_posts_excluded,
               s.follower_growth_pct, s.is_trending
               $extraSelect
        $baseQuery
        ORDER BY $orderSql
        LIMIT :start, :length
    ");
    bind_common($dataStmt, $view, $applyThresholds, $minFollowers, $maxFollowers, $minEngagement, $maxEngagement, $nicheId, $search, $stageFilter, $outreachDays, $tierFilter);
    $dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
    $dataStmt->bindValue(':length', $length, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    if ($view === 'flozy') {
        $totalRecords = (int) $pdo->query("SELECT COUNT(*) FROM flozy_leads fl JOIN profiles p ON p.id = fl.profile_id WHERE p.status != 'archived'")->fetchColumn();
    } elseif ($view === 'archived') {
        $totalRecords = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'archived'")->fetchColumn();
    } else {
        $statusValue = ($view === 'future') ? 'future' : 'active';
        $totalRecordsStmt = $pdo->prepare("
            SELECT COUNT(*) FROM profiles p
            WHERE p.status = ? AND p.id NOT IN (SELECT profile_id FROM flozy_leads)
        ");
        $totalRecordsStmt->execute([$statusValue]);
        $totalRecords = (int) $totalRecordsStmt->fetchColumn();
    }

    echo json_encode([
        'draw'            => (int) ($_GET['draw'] ?? 1),
        'recordsTotal'    => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data'            => $rows,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Query failed: ' . $e->getMessage(),
        'draw'  => (int) ($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
}
