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
$search        = $_GET['search']['value'] ?? '';
$start         = (int) ($_GET['start'] ?? 0);
$length        = (int) ($_GET['length'] ?? 25);
$view          = $_GET['view'] ?? 'active'; // 'active' | 'archived' | 'future' | 'flozy'

$applyThresholds = ($view === 'active');

$sortableColumns = [
    1  => 'p.username',
    2  => 'p.full_name',
    3  => 'n.name',
    4  => 's.followers_count',
    5  => 's.engagement_rate',
    6  => 's.quality_score',
    7  => 's.avg_likes',
    8  => 's.avg_comments',
    9  => 's.posts_per_week',
    10 => 's.biography',
    11 => 'p.external_url',
    12 => 's.imported_at',
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
        WHERE 1=1
    ";
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
} else {
    $statusValue = in_array($view, ['archived', 'future'], true) ? $view : 'active';
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

if ($search !== '') {
    $baseQuery .= " AND (p.username LIKE :search1 OR p.full_name LIKE :search2 OR n.name LIKE :search3) ";
}

function bind_common(PDOStatement $stmt, string $view, bool $applyThresholds, int $minFollowers, int $maxFollowers, float $minEngagement, float $maxEngagement, ?int $nicheId, string $search): void
{
    if ($view !== 'flozy') {
        $statusValue = in_array($view, ['archived', 'future'], true) ? $view : 'active';
        $stmt->bindValue(':statusValue', $statusValue);
        if ($applyThresholds) {
            $stmt->bindValue(':minFollowers', $minFollowers);
            $stmt->bindValue(':maxFollowers', $maxFollowers);
            $stmt->bindValue(':minEngagement', $minEngagement);
            $stmt->bindValue(':maxEngagement', $maxEngagement);
        }
    }
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
    bind_common($countStmt, $view, $applyThresholds, $minFollowers, $maxFollowers, $minEngagement, $maxEngagement, $nicheId, $search);
    $countStmt->execute();
    $totalFiltered = (int) $countStmt->fetchColumn();

    $extraSelect = '';
    if ($view === 'active' || $view === 'flozy' || $view === 'future') {
        $extraSelect .= "
            , EXISTS(SELECT 1 FROM gameplans g WHERE g.profile_id = p.id) AS has_gameplan
            , EXISTS(SELECT 1 FROM post_transcripts pt WHERE pt.profile_id = p.id) AS has_scraped_data
            , EXISTS(SELECT 1 FROM content_analysis_runs car WHERE car.profile_id = p.id AND car.status = 'done') AS has_message
            , (SELECT COUNT(*) FROM post_transcripts pt2 WHERE pt2.profile_id = p.id AND pt2.needs_manual_review = 1) AS manual_review_count
        ";
    }
    if ($view === 'flozy') {
        $extraSelect .= "
            , fl.flozy_lead_id
            , fl.pushed_at AS flozy_pushed_at
            , fl.current_stage
            , fl.current_stage_tag
            , fl.stage_synced_at
            , fl.outreached_at
        ";
    }

    $dataStmt = $pdo->prepare("
        SELECT p.id, p.username, p.full_name, p.external_url, n.name AS niche, n.id AS niche_id, p.niche_source, p.status, p.notes,
               s.followers_count, s.engagement_rate, s.quality_score, s.avg_likes, s.avg_comments,
               s.biography, s.imported_at, s.posts_per_week, s.is_inconsistent, s.pinned_posts_excluded,
               s.follower_growth_pct, s.is_trending
               $extraSelect
        $baseQuery
        ORDER BY $orderSql
        LIMIT :start, :length
    ");
    bind_common($dataStmt, $view, $applyThresholds, $minFollowers, $maxFollowers, $minEngagement, $maxEngagement, $nicheId, $search);
    $dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
    $dataStmt->bindValue(':length', $length, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    if ($view === 'flozy') {
        $totalRecords = (int) $pdo->query("SELECT COUNT(*) FROM flozy_leads")->fetchColumn();
    } else {
        $statusValue = in_array($view, ['archived', 'future'], true) ? $view : 'active';
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
