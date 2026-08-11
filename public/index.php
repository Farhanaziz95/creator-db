<?php
// Only needed here to build a direct "open in Flozy" link client-side —
// nothing else on this page touches Flozy's API directly.
$flozyDashboardConfig = require __DIR__ . '/../config/flozy.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Creator Database</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
    :root {
        --bg: #0f1115;
        --card: #171a21;
        --border: #262a33;
        --text: #e8e9ec;
        --muted: #8b8f9a;
        --accent: #6ee7b7;
        --accent2: #60a5fa;
        --danger: #f87171;
    }
    * { box-sizing: border-box; }
    html, body { width: 100%; margin: 0; }
    body {
        background: var(--bg);
        color: var(--text);
        font-family: -apple-system, Segoe UI, Roboto, sans-serif;
        margin: 0;
        padding: 24px;
    }
    h1 { font-size: 20px; font-weight: 600; margin: 0 0 20px; }
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 14px 16px;
    }
    .stat-card .label { font-size: 12px; color: var(--muted); margin-bottom: 6px; }
    .stat-card .value { font-size: 22px; font-weight: 700; }
    .panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 18px;
        margin-bottom: 20px;
    }
    .panel h2 { font-size: 14px; margin: 0 0 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .controls { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .controls label { font-size: 13px; color: var(--muted); }
    input[type="number"], input[type="file"], input[type="text"] {
        background: #0f1115;
        border: 1px solid var(--border);
        color: var(--text);
        padding: 8px 10px;
        border-radius: 6px;
        font-size: 13px;
    }
    button {
        background: var(--accent2);
        border: none;
        color: #0f1115;
        font-weight: 600;
        padding: 9px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
    }
    button:hover { opacity: 0.9; }
    button.danger { background: var(--danger); }
    button.ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
    button.small { padding: 4px 10px; font-size: 12px; }
    #uploadStatus, #archiveStatus { margin-top: 10px; font-size: 13px; color: var(--muted); }
    table.dataTable { color: var(--text) !important; width: 100% !important; }
    .dataTables_wrapper { width: 100%; }
    .table-scroll { overflow-x: auto; }
    table.dataTable thead th { color: var(--muted) !important; border-bottom: 1px solid var(--border) !important; }
    table.dataTable tbody td { border-top: 1px solid var(--border) !important; }
    a.ext-link { color: var(--accent2); text-decoration: none; }
    .badge { background: #262a33; padding: 2px 8px; border-radius: 20px; font-size: 12px; color: var(--accent); }
    .tabs { display: flex; gap: 8px; margin-bottom: 16px; }
    .tab-btn {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--muted);
        padding: 8px 16px;
    }
    .tab-btn.active { background: var(--accent2); color: #0f1115; border-color: var(--accent2); }
    .legend-swatch { display:inline-block; width:14px; height:14px; border-radius:3px; margin-right:6px; vertical-align:middle; }
    .progress-badges { white-space: nowrap; font-size: 13px; }
    .action-menu { position: relative; display: inline-block; white-space: nowrap; }
    .action-menu-btn { background: transparent; border: 1px solid var(--border); color: var(--text); padding: 4px 9px; border-radius: 4px; cursor: pointer; font-size: 13px; margin-left: 4px; }
    .action-menu-content { display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: #1c2029; border: 1px solid var(--border); border-radius: 6px; min-width: 190px; z-index: 100; box-shadow: 0 6px 16px rgba(0,0,0,0.5); overflow: hidden; }
    .action-menu-content.open { display: block; }
    .action-menu-content button { display: block; width: 100%; text-align: left; background: transparent; border: none; color: var(--text); padding: 9px 12px; font-size: 12px; cursor: pointer; }
    .action-menu-content button:hover { background: #262a33; }
</style>
</head>
<body>

<!-- Benchmark Reference Modal -->
<div id="benchmarkModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:900px; width:90%; max-height:85vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">📖 Benchmark Reference</h2>
            <button class="ghost small" onclick="closeBenchmarkModal()">✕ Close</button>
        </div>

        <h3 style="font-size:13px; color:var(--muted); margin:0 0 8px;">Engagement Rate by Follower Size</h3>
        <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:20px;">
            <thead>
                <tr style="text-align:left; color:var(--muted); border-bottom:1px solid var(--border);">
                    <th style="padding:6px;">Tier</th>
                    <th style="padding:6px;">1k-5k</th>
                    <th style="padding:6px;">5k-10k</th>
                    <th style="padding:6px;">10k-50k</th>
                    <th style="padding:6px;">50k-100k</th>
                    <th style="padding:6px;">100k-500k</th>
                    <th style="padding:6px;">500k-1M</th>
                    <th style="padding:6px;">1M+</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background:rgba(34,197,94,0.15)">
                    <td style="padding:6px;"><b>High</b></td>
                    <td style="padding:6px;">&gt;6.16%</td><td style="padding:6px;">&gt;2.09%</td><td style="padding:6px;">&gt;1.27%</td>
                    <td style="padding:6px;">&gt;0.91%</td><td style="padding:6px;">&gt;0.93%</td><td style="padding:6px;">&gt;1.00%</td><td style="padding:6px;">&gt;1.08%</td>
                </tr>
                <tr style="background:rgba(163,230,53,0.12)">
                    <td style="padding:6px;"><b>Above Average</b></td>
                    <td style="padding:6px;">3.85-6.16%</td><td style="padding:6px;">1.13-2.09%</td><td style="padding:6px;">0.65-1.27%</td>
                    <td style="padding:6px;">0.43-0.91%</td><td style="padding:6px;">0.46-0.93%</td><td style="padding:6px;">0.51-1.00%</td><td style="padding:6px;">0.57-1.08%</td>
                </tr>
                <tr style="background:rgba(168,85,247,0.15)">
                    <td style="padding:6px;"><b>Average</b></td>
                    <td style="padding:6px;">3.16-3.85%</td><td style="padding:6px;">0.88-1.13%</td><td style="padding:6px;">0.49-0.65%</td>
                    <td style="padding:6px;">0.32-0.43%</td><td style="padding:6px;">0.35-0.46%</td><td style="padding:6px;">0.39-0.51%</td><td style="padding:6px;">0.45-0.57%</td>
                </tr>
                <tr style="background:rgba(251,146,60,0.12)">
                    <td style="padding:6px;"><b>Below Average</b></td>
                    <td style="padding:6px;">1.85-3.16%</td><td style="padding:6px;">0.46-0.88%</td><td style="padding:6px;">0.24-0.49%</td>
                    <td style="padding:6px;">0.15-0.32%</td><td style="padding:6px;">0.16-0.35%</td><td style="padding:6px;">0.19-0.39%</td><td style="padding:6px;">0.22-0.45%</td>
                </tr>
                <tr style="background:rgba(248,113,113,0.15)">
                    <td style="padding:6px;"><b>Low</b></td>
                    <td style="padding:6px;">&lt;1.85%</td><td style="padding:6px;">&lt;0.46%</td><td style="padding:6px;">&lt;0.24%</td>
                    <td style="padding:6px;">&lt;0.15%</td><td style="padding:6px;">&lt;0.16%</td><td style="padding:6px;">&lt;0.19%</td><td style="padding:6px;">&lt;0.22%</td>
                </tr>
            </tbody>
        </table>

        <h3 style="font-size:13px; color:var(--muted); margin:0 0 8px;">Posting Frequency Bands</h3>
        <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:8px;">
            <thead>
                <tr style="text-align:left; color:var(--muted); border-bottom:1px solid var(--border);">
                    <th style="padding:6px;">Band</th><th style="padding:6px;">Posts/Week</th><th style="padding:6px;">Meaning</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background:rgba(248,113,113,0.15)"><td style="padding:6px;">🐌 Inactive</td><td style="padding:6px;">&lt; 2/wk</td><td style="padding:6px;">Stale/under-active account</td></tr>
                <tr style="background:rgba(34,197,94,0.15)"><td style="padding:6px;">Healthy</td><td style="padding:6px;">2 - 7/wk</td><td style="padding:6px;">Steady, normal cadence</td></tr>
                <tr style="background:rgba(248,113,113,0.15)"><td style="padding:6px;">🔥 Spammy</td><td style="padding:6px;">&gt; 7/wk</td><td style="padding:6px;">Erratic/overposting</td></tr>
            </tbody>
        </table>
        <p style="font-size:11px; color:var(--muted);">Pinned posts are excluded from all engagement and posting-frequency calculations.</p>
    </div>
</div>

<!-- Username Collector Script -->
<div class="panel">
    <h2>Collect Suggested Usernames (Console Script)</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        1. On Instagram, open a profile → "Suggested for you" → "See All" → scroll fully to load everyone.<br>
        2. Open DevTools console (F12).<br>
        3. Paste the script below, hit Enter.<br>
        4. Copy the printed usernames (one per line) → paste into Apify's <code>usernames</code> field.
    </p>
    <textarea id="scriptBox" readonly style="width:100%; height:180px; background:#0f1115; border:1px solid var(--border); color:var(--text); font-family:monospace; font-size:12px; padding:10px; border-radius:6px;">(() => {
  const dialog = document.querySelector('div[role="dialog"]');
  if (!dialog) {
    console.log("No open dialog found. Make sure the 'See All' suggestions modal is open.");
    return;
  }
  const links = dialog.querySelectorAll('a[href^="/"]');
  const excluded = new Set(["explore", "reels", "direct", "accounts", "stories", "p", "tv", "about", "developer", "legal", ""]);
  const usernames = new Set();
  links.forEach(link => {
    const href = link.getAttribute("href");
    if (!href) return;
    const match = href.match(/^\/([^\/?]+)\/?$/);
    if (!match) return;
    const username = match[1];
    if (excluded.has(username)) return;
    usernames.add(username);
  });
  const result = [...usernames];
  console.log(`Found ${result.length} usernames:\n`);
  console.log(result.join("\n"));
})();</textarea>
    <button onclick="copyScript()" style="margin-top:10px;">Copy Script</button>
    <span id="copyStatus" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>
</div>

<!-- Growth Chart Modal -->
<div id="growthModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:700px; width:90%;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 id="growthModalTitle" style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">📈 Growth History</h2>
            <button class="ghost small" onclick="closeGrowthModal()">✕ Close</button>
        </div>
        <canvas id="growthChartCanvas" height="120"></canvas>
        <p id="growthEmptyNote" style="display:none; font-size:12px; color:var(--muted); margin-top:12px;">
            Only one snapshot on record so far — the chart fills in as this profile gets re-imported over time.
        </p>
    </div>
</div>

<!-- Import History Modal -->
<div id="importHistoryModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:800px; width:90%; max-height:80vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">📜 Import History</h2>
            <button class="ghost small" onclick="closeImportHistory()">✕ Close</button>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="text-align:left; color:var(--muted); border-bottom:1px solid var(--border);">
                    <th style="padding:6px;">File</th><th style="padding:6px;">Total</th><th style="padding:6px;">New</th><th style="padding:6px;">Duplicates</th><th style="padding:6px;">When</th>
                </tr>
            </thead>
            <tbody id="importHistoryBody"></tbody>
        </table>
    </div>
</div>

<!-- Score Guide Modal -->
<div id="scoreGuideModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:700px; width:90%; max-height:85vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">❔ How the Quality Score Works</h2>
            <button class="ghost small" onclick="closeScoreGuide()">✕ Close</button>
        </div>
        <p style="font-size:13px; color:var(--muted); line-height:1.6;">
            The score blends 4 signals, each scored 1-5, then combined using the weights
            you set in <b>Settings → Composite Quality Score</b>. Nothing is hardcoded —
            adjust weights any time and every score updates instantly (it's calculated
            live from your current settings, not stored).
        </p>
        <table style="width:100%; border-collapse:collapse; font-size:12px; margin:14px 0;">
            <thead>
                <tr style="text-align:left; color:var(--muted); border-bottom:1px solid var(--border);">
                    <th style="padding:6px;">Signal</th><th style="padding:6px;">5 pts</th><th style="padding:6px;">3 pts</th><th style="padding:6px;">1-2 pts</th>
                </tr>
            </thead>
            <tbody style="font-size:12px;">
                <tr><td style="padding:6px;">Engagement Tier</td><td style="padding:6px;">High</td><td style="padding:6px;">Average</td><td style="padding:6px;">Below Average / Low</td></tr>
                <tr><td style="padding:6px;">Posting Consistency</td><td style="padding:6px;">Healthy (2-7/wk)</td><td style="padding:6px;">No data yet</td><td style="padding:6px;">Inactive or Spammy</td></tr>
                <tr><td style="padding:6px;">Growth Trend</td><td style="padding:6px;">Trending (+15%+)</td><td style="padding:6px;">No growth data yet</td><td style="padding:6px;">—</td></tr>
                <tr><td style="padding:6px;">Niche Assigned</td><td style="padding:6px;">Has a niche</td><td style="padding:6px;">Unassigned</td><td style="padding:6px;">—</td></tr>
                <tr><td style="padding:6px;">Content Verified</td><td style="padding:6px;">No unresolved review flags</td><td style="padding:6px;">—</td><td style="padding:6px;">Has unresolved music-only/b-roll flags</td></tr>
            </tbody>
        </table>
        <p style="font-size:13px; color:var(--muted); line-height:1.6;">
            <b>Formula:</b> for each ACTIVE signal, its 1-5 score is multiplied by its weight.
            Those are summed and divided by the maximum possible (5 × sum of active weights),
            giving a percentage. Turning a signal off in Settings removes it from the formula
            entirely rather than counting it as zero.
            <br><br>
            <b>Grades:</b> 90%+ = A, 75-89% = B, 60-74% = C, 45-59% = D, under 45% = F.
            <br><br>
            "No data yet" signals (posting/growth) score a neutral 3 rather than being
            penalized — a profile you just imported for the first time shouldn't be marked
            down just because it hasn't been re-scraped yet to show a trend.
            <br><br>
            This is a starting point, not gospel — tune the weights in Settings as you get
            a feel for which signals actually predict a good partnership for you.
        </p>
    </div>
</div>

<input type="file" id="gameplanFileInput" accept="application/pdf" style="display:none;" onchange="handleGameplanFileSelected()">

<!-- Verification Results Modal -->
<div id="resultsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:650px; width:90%; max-height:85vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">🔍 Verification & Personalized Message</h2>
            <button class="ghost small" onclick="closeResultsModal()">✕ Close</button>
        </div>
        <h3 style="font-size:12px; color:var(--muted); text-transform:uppercase; margin:0 0 6px;">Verification (does the gameplan hold up against real comments?)</h3>
        <p id="resultsVerification" style="font-size:13px; line-height:1.6; background:#0f1115; padding:12px; border-radius:6px; margin:0 0 18px;"></p>

        <h3 style="font-size:12px; color:var(--accent); text-transform:uppercase; margin:0 0 6px;">🎣 Hook (the opener — this is what shows in their DM preview/notification, decides if they even tap in)</h3>
        <textarea id="resultsHook" style="width:100%; height:50px; background:#0f1115; border:1px solid var(--accent); color:var(--text); padding:10px; border-radius:6px; font-size:14px; font-weight:600;"></textarea>
        <button onclick="copyText('resultsHook', 'copyHookStatus')" style="margin-top:8px;">Copy Hook</button>
        <span id="copyHookStatus" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>

        <h3 style="font-size:12px; color:var(--muted); text-transform:uppercase; margin:18px 0 6px;">Follow-up (only matters once they've opened it — edit freely before sending)</h3>
        <textarea id="resultsFollowup" style="width:100%; height:100px; background:#0f1115; border:1px solid var(--border); color:var(--text); padding:10px; border-radius:6px; font-size:13px;"></textarea>
        <button onclick="copyText('resultsFollowup', 'copyFollowupStatus')" style="margin-top:8px;">Copy Follow-up</button>
        <span id="copyFollowupStatus" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>
    </div>
</div>

<!-- Follow-up Modal (guided — you choose the type, system prompts for input) -->
<div id="followupModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:550px; width:90%;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">💬 Send Follow-up</h2>
            <button class="ghost small" onclick="closeFollowupModal()">✕ Close</button>
        </div>

        <label style="font-size:12px; color:var(--muted);">What are we going for?</label>
        <select id="followupType" onchange="onFollowupTypeChange()" style="width:100%; margin:6px 0 14px;">
            <option value="regular">Regular personalized follow-up</option>
            <option value="validation_script">Validation script</option>
            <option value="free_value">Free value</option>
            <option value="win_insight">Win / insight share</option>
            <option value="custom_survey">Free custom survey / audit offer</option>
        </select>

        <div id="followupInputWrap" style="display:none;">
            <label style="font-size:12px; color:var(--muted);">What are you sharing this time?</label>
            <textarea id="followupUserInput" style="width:100%; height:90px; background:#0f1115; border:1px solid var(--border); color:var(--text); padding:10px; border-radius:6px; font-size:13px; margin:6px 0 14px;" placeholder="Paste the validation result, the win, the insight, whatever's relevant..."></textarea>
        </div>

        <button onclick="generateFollowup()">Generate</button>

        <div id="followupResultWrap" style="display:none; margin-top:16px;">
            <label style="font-size:12px; color:var(--muted);">Generated message</label>
            <textarea id="followupResult" style="width:100%; height:100px; background:#0f1115; border:1px solid var(--border); color:var(--text); padding:10px; border-radius:6px; font-size:13px; margin-top:6px;"></textarea>
            <button onclick="copyText('followupResult', 'copyFollowupResultStatus')" style="margin-top:8px;">Copy</button>
            <span id="copyFollowupResultStatus" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>
        </div>
    </div>
</div>

<!-- Manual Review Modal -->
<div id="manualReviewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:600px; width:90%; max-height:80vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 id="manualReviewTitle" style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">⚠️ Reels Needing Manual Review</h2>
            <button class="ghost small" onclick="closeManualReviewModal()">✕ Close</button>
        </div>
        <p style="font-size:12px; color:var(--muted); margin:0 0 14px;">
            These reels came back with no transcript (no OCR available, so we can't tell if it's just music/b-roll
            or actual speech that failed to transcribe). Confirm OK to keep using it in AI personalization, or
            Exclude to remove it from future AI prompts for this lead.
        </p>
        <div id="manualReviewList"></div>
    </div>
</div>

<h1>📊 Creator Database</h1>

<div class="stats-row" id="statsRow"></div>
<div id="nicheCheckStatus" style="font-size:12px; color:var(--muted); margin:-14px 0 20px;"></div>

<div class="panel">
    <h2>Import new Apify data</h2>
    <div class="controls">
        <input type="file" id="jsonFile" accept="application/json">
        <button onclick="uploadFile()">Import</button>
    </div>
    <div id="uploadStatus"></div>
</div>

<div class="panel">
    <h2>Filters &amp; Archiving</h2>
    <div class="controls">
        <label>Min Followers</label>
        <input type="number" id="minFollowers" value="0" min="0" style="width:110px">
        <label>Max Followers</label>
        <input type="number" id="maxFollowers" placeholder="no limit" min="0" style="width:110px">
        <label>Min Engagement %</label>
        <input type="number" id="minEngagement" value="0" min="0" step="0.1" style="width:90px">
        <label>Max Engagement %</label>
        <input type="number" id="maxEngagement" placeholder="no limit" min="0" step="0.1" style="width:90px">
        <label>Niche</label>
        <select id="nicheFilter" style="width:160px;"><option value="">All niches</option></select>
        <button onclick="reloadTable()">Apply Filter</button>
    </div>
    <div class="controls" style="margin-top:12px;">
        <button class="danger" onclick="archiveBelowThreshold()">Archive Everyone Below Min Numbers</button>
        <button class="ghost" onclick="sendToFutureInRange()">Send Range to Future</button>
        <button style="background:#5B7BFF;" onclick="pushQualifiedToFlozy()">Push Qualified to Flozy</button>
        <button class="ghost" onclick="syncAllFlozyStages()">🔄 Sync All Pipeline Stages</button>
        <button class="ghost" onclick="openImportHistory()">📜 Import History</button>
    </div>
    <div id="archiveStatus"></div>
    <div id="flozyStatus" style="margin-top:6px; font-size:13px; color:var(--muted);"></div>
</div>

<div class="panel">
    <h2>Engagement Color Key</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Row color shows how a profile's engagement rate compares to typical Instagram
        benchmarks <b>for its follower size</b> (a 3k-follower account and a 500k-follower
        account need very different engagement rates to count as "good"). This is just a
        visual read to help you scan faster — it's not a hard qualification rule, the
        Min Followers / Min Engagement filter above is still what actually decides what's active vs archived.
        <br><br>
        <b>Pinned posts are excluded</b> from all averages, engagement rate, and the
        Posts/Week number — a creator's pinned posts are hand-picked best-performers, not
        representative of their normal engagement or posting rhythm.
        <b>Posts/Week</b>: 🐌 under 2x/week = inactive/stale account, 🔥 over 7x/week =
        spammy/erratic. Anything in between is a healthy cadence, no icon shown.
        <br><br>
        <b>Future tab</b>: a holding pen for accounts that don't fit right now but could
        be useful later — separate from Archived (which means "didn't qualify").
    </p>
    <button class="ghost" onclick="openBenchmarkModal()" style="margin-top:8px;">📖 Open Full Benchmark Reference</button>
    <div class="controls" style="gap:16px;">
        <span><span class="legend-swatch" style="background:rgba(34,197,94,0.35)"></span>High</span>
        <span><span class="legend-swatch" style="background:rgba(163,230,53,0.30)"></span>Above Average</span>
        <span><span class="legend-swatch" style="background:rgba(168,85,247,0.28)"></span>Average</span>
        <span><span class="legend-swatch" style="background:rgba(251,146,60,0.30)"></span>Below Average</span>
        <span><span class="legend-swatch" style="background:rgba(248,113,113,0.32)"></span>Low</span>
    </div>
</div>

<div class="panel">
    <div class="controls" style="margin-top:12px;">
        <button class="ghost" onclick="openScoreGuide()">❔ How Scoring Works</button>
    </div>
</div>

<div class="panel" id="budgetSweepPanel">
    <h2>💰 Month-End Budget Sweep <span style="font-weight:400; text-transform:none; letter-spacing:0; color:var(--muted); font-size:11px;">(separate from follow-ups — just spends leftover Apify budget refreshing lead data)</span></h2>
    <div id="budgetSweepInfo" style="font-size:13px; color:var(--muted); margin-bottom:10px;">Loading budget info…</div>
    <p style="font-size:11px; color:var(--muted); margin:0 0 10px;">
        This button is manual/on-demand. For actual production use, set up
        <code>jobs/scheduled_budget_sweep.php</code> in Task Scheduler (daily) instead —
        it spreads activity randomly across the last <code>window_days</code> of the
        month (config/sweep_schedule.php), varying the day, the % of leads touched, and
        which keys are used, so it doesn't look like a single automated burst.
    </p>
    <button onclick="runBudgetSweep()">Run Sweep Now (Manual)</button>
    <button class="ghost" onclick="openSweepHistory()">📜 Sweep History</button>
    <div id="budgetSweepStatus" style="margin-top:8px; font-size:12px; color:var(--muted);"></div>
</div>

<!-- Generation History Modal -->
<div id="genHistoryModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:650px; width:90%; max-height:80vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 id="genHistoryTitle" style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">🕐 Generation History</h2>
            <button class="ghost small" onclick="closeGenerationHistory()">✕ Close</button>
        </div>
        <div id="genHistoryFreshness" style="font-size:13px; padding:10px 12px; border-radius:6px; margin-bottom:14px;"></div>
        <div id="genHistoryTimeline"></div>
    </div>
</div>

<!-- Sweep History Modal -->
<div id="sweepHistoryModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:750px; width:90%; max-height:80vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px; text-transform:none; letter-spacing:0;">📜 Sweep Schedule History</h2>
            <button class="ghost small" onclick="closeSweepHistory()">✕ Close</button>
        </div>
        <p style="font-size:12px; color:var(--muted); margin:0 0 12px;">
            One row per day the scheduled job ran (or skipped) — use this to confirm the
            randomization is actually varying, not falling into a predictable pattern.
        </p>
        <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="text-align:left; color:var(--muted); border-bottom:1px solid var(--border);">
                    <th style="padding:6px;">Date</th><th style="padding:6px;">Active?</th><th style="padding:6px;">%</th>
                    <th style="padding:6px;">Keys Used</th><th style="padding:6px;">Refreshed</th><th style="padding:6px;">Note</th>
                </tr>
            </thead>
            <tbody id="sweepHistoryBody"></tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="tabs">
        <button class="tab-btn active" id="tabActive" onclick="switchView('active')">Active</button>
        <button class="tab-btn" id="tabArchived" onclick="switchView('archived')">Archived</button>
        <button class="tab-btn" id="tabFuture" onclick="switchView('future')">Future</button>
        <button class="tab-btn" id="tabFlozy" onclick="switchView('flozy')">Sent to Flozy</button>
        <span id="outreachFilterWrap" style="display:none; align-self:center;">
            <select id="outreachFilterSelect" onchange="table.ajax.reload(null, false)" style="width:180px;">
                <option value="">All (outreach status)</option>
                <option value="contacted">Contacted</option>
                <option value="not_contacted">Not Contacted</option>
                <option value="ghosted">Ghosted (no reply)</option>
            </select>
        </span>
        <div class="action-menu" style="margin-left:auto; align-self:center;">
            <button class="action-menu-btn" onclick="toggleColumnMenu()">👁 Columns</button>
            <div class="action-menu-content" id="columnToggleMenu" style="min-width:180px; max-height:340px; overflow-y:auto;"></div>
        </div>
        <a href="content_studio.php" style="align-self:center; color:var(--muted); font-size:13px;">🎬 Content Studio</a>
        <a href="settings.php" style="align-self:center; color:var(--muted); font-size:13px;">⚙️ Settings (Default Task List)</a>
    </div>
    <div id="bulkActionBar" style="display:none; background:#1c2029; border:1px solid var(--accent2); border-radius:8px; padding:10px 14px; margin-bottom:12px; align-items:center; gap:10px; flex-wrap:wrap;">
        <span id="bulkSelectionCount" style="font-size:13px; font-weight:600;"></span>
        <div id="bulkActionButtons" style="display:flex; gap:8px; flex-wrap:wrap;"></div>
        <button class="ghost small" onclick="clearSelection()" style="margin-left:auto;">Clear Selection</button>
    </div>
    <div class="table-scroll">
    <table id="profilesTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAllVisible" onchange="toggleSelectAllVisible(this.checked)" title="Select all visible rows"></th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Niche</th>
                <th>Followers</th>
                <th>Engagement %</th>
                <th>Score</th>
                <th>Avg Likes</th>
                <th>Avg Comments</th>
                <th>Posts/Week</th>
                <th>Bio</th>
                <th>Link</th>
                <th>Last Updated</th>
                <th>Notes</th>
                <th>Progress</th>
                <th>Pipeline Stage</th>
                <th>Outreach</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
// ===== Notification system (SweetAlert2) =====
// Info: auto-dismissing timer toast. Warning/Error: toast that STAYS until
// you close it, always with full details logged to console so debugging
// never requires reproducing the error. Loading: persistent "in progress"
// toast for anything that takes a while, removed automatically when done.

function notifyInfo(message) {
    console.log('[INFO]', message);
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success', title: message,
        showConfirmButton: false, timer: 3000, timerProgressBar: true,
    });
}

function notifyWarning(message, details) {
    console.warn('[WARNING]', message, details || '');
    Swal.fire({
        toast: true, position: 'top-end', icon: 'warning', title: message,
        showConfirmButton: true, confirmButtonText: 'OK',
    });
}

function notifyError(message, details) {
    console.error('[ERROR]', message, details || '');
    Swal.fire({
        toast: true, position: 'top-end', icon: 'error', title: message,
        showConfirmButton: true, confirmButtonText: 'OK',
    });
}

function confirmAction(title, text, confirmButtonText) {
    return Swal.fire({
        title: title, text: text, icon: 'warning',
        showCancelButton: true, confirmButtonText: confirmButtonText || 'Yes, do it',
        confirmButtonColor: '#f87171', cancelButtonText: 'Cancel',
        background: '#171a21', color: '#e8e9ec',
    }).then(result => result.isConfirmed);
}

let loadingToastEl = null;
function showLoadingToast(message) {
    loadingToastEl = Swal.fire({
        toast: true, position: 'top-end', title: message,
        showConfirmButton: false, allowOutsideClick: false, allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
    });
}
function hideLoadingToast() {
    if (loadingToastEl) Swal.close();
    loadingToastEl = null;
}

let selectedIds = new Set();
let selectedUsernames = new Map(); // id -> username, so "Open Selected" works across pages without a re-fetch

function toggleRowSelect(id, checked, username) {
    if (checked) { selectedIds.add(id); selectedUsernames.set(id, username); }
    else { selectedIds.delete(id); selectedUsernames.delete(id); }
    updateBulkActionBar();
}

function toggleSelectAllVisible(checked) {
    document.querySelectorAll('.row-select').forEach(cb => {
        cb.checked = checked;
        const id = parseInt(cb.getAttribute('data-id'), 10);
        const username = cb.getAttribute('data-username');
        if (checked) { selectedIds.add(id); selectedUsernames.set(id, username); }
        else { selectedIds.delete(id); selectedUsernames.delete(id); }
    });
    updateBulkActionBar();
}

function clearSelection() {
    selectedIds.clear();
    selectedUsernames.clear();
    document.getElementById('selectAllVisible').checked = false;
    table.ajax.reload(null, false); // redraw so checkboxes reflect cleared state
    updateBulkActionBar();
}

function openSelectedInNewTabs() {
    if (selectedIds.size > 10) {
        confirmAction('Open ' + selectedIds.size + ' tabs?', 'That\'s a lot of tabs at once — your browser may block some of them as a popup-spam precaution.', 'Open them anyway').then(ok => {
            if (ok) doOpenTabs();
        });
    } else {
        doOpenTabs();
    }
}
function doOpenTabs() {
    // Iterate selectedIds (the source of truth for "currently selected"),
    // not selectedUsernames directly — selectedUsernames can carry stale
    // entries left over from a previous tab, and opening straight off that
    // Map was what caused unselected/leftover profiles from other tabs to
    // pop open alongside the ones actually checked right now.
    selectedIds.forEach(id => {
        const username = selectedUsernames.get(id);
        if (username) {
            window.open(`https://instagram.com/${username}`, '_blank');
        }
    });
}

function updateBulkActionBar() {
    const bar = document.getElementById('bulkActionBar');
    const count = selectedIds.size;

    if (count === 0) {
        bar.style.display = 'none';
        return;
    }

    bar.style.display = 'flex';
    document.getElementById('bulkSelectionCount').textContent = `${count} selected`;

    const buttonsWrap = document.getElementById('bulkActionButtons');
    let viewButtons = '';
    if (currentView === 'active') {
        viewButtons = `
            <button class="small danger" onclick="bulkArchiveSelected()">Archive Selected</button>
            <button class="small ghost" onclick="bulkFutureSelected()">Send to Future</button>
            <button class="small" style="background:#5B7BFF;" onclick="bulkPushSelected()">Push to Flozy</button>`;
    } else if (currentView === 'future') {
        viewButtons = `
            <button class="small ghost" onclick="bulkRestoreSelected()">Restore to Active</button>
            <button class="small" style="background:#5B7BFF;" onclick="bulkPushSelected()">Push to Flozy</button>
            <button class="small danger" onclick="bulkArchiveSelected()">Archive</button>`;
    } else if (currentView === 'archived') {
        viewButtons = `<button class="small ghost" onclick="bulkRestoreSelected()">Restore to Active</button>`;
    } else if (currentView === 'flozy') {
        viewButtons = `<button class="small danger" onclick="bulkRemoveFromFlozySelected()">Remove from Flozy</button>`;
    }
    buttonsWrap.innerHTML = viewButtons + ` <button class="small ghost" onclick="openSelectedInNewTabs()">🔗 Open Selected in New Tabs</button>`;
}

async function bulkArchiveSelected() {
    const ids = [...selectedIds];
    const ok = await confirmAction('Archive selected?', `Archives ${ids.length} selected profile(s). Not deleted, just moved to Archived.`, 'Archive them');
    if (!ok) return;
    showLoadingToast('Archiving selected…');
    fetch('../api/archive.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'archive_selected', profile_ids: ids })
    }).then(r => r.json()).then(res => {
        hideLoadingToast();
        notifyInfo(`Archived ${res.archived_count} profile(s).`);
        clearSelection(); loadStats();
    }).catch(err => { hideLoadingToast(); notifyError('Bulk archive failed.', err); });
}

async function bulkFutureSelected() {
    const ids = [...selectedIds];
    const ok = await confirmAction('Send selected to Future?', `Moves ${ids.length} selected profile(s) to the Future tab.`, 'Move them');
    if (!ok) return;
    showLoadingToast('Moving selected to Future…');
    fetch('../api/archive.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'future_selected', profile_ids: ids })
    }).then(r => r.json()).then(res => {
        hideLoadingToast();
        notifyInfo(`Moved ${res.moved_count} profile(s) to Future.`);
        clearSelection(); loadStats();
    }).catch(err => { hideLoadingToast(); notifyError('Bulk move failed.', err); });
}

async function bulkRestoreSelected() {
    const ids = [...selectedIds];
    const ok = await confirmAction('Restore selected to Active?', `Restores ${ids.length} selected profile(s).`, 'Restore them');
    if (!ok) return;
    showLoadingToast('Restoring selected…');
    fetch('../api/archive.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'restore_selected', profile_ids: ids })
    }).then(r => r.json()).then(res => {
        hideLoadingToast();
        notifyInfo(`Restored ${res.restored_count} profile(s).`);
        clearSelection(); loadStats();
    }).catch(err => { hideLoadingToast(); notifyError('Bulk restore failed.', err); });
}

async function bulkPushSelected() {
    const ids = [...selectedIds];
    const ok = await confirmAction('Push selected to Flozy?', `Creates real leads (and default tasks) in Flozy for ${ids.length} selected profile(s).`, 'Push them');
    if (!ok) return;
    showLoadingToast('Pushing selected to Flozy…');
    fetch('../api/flozy_push.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'push_selected', profile_ids: ids })
    }).then(r => r.json()).then(res => {
        hideLoadingToast();
        notifyInfo(`Pushed ${res.pushed} of ${res.total_attempted} to Flozy.`);
        if (res.failed && res.failed.length) notifyWarning(`${res.failed.length} failed — details in console.`, res.failed);
        clearSelection(); loadStats();
    }).catch(err => { hideLoadingToast(); notifyError('Bulk push failed.', err); });
}

async function bulkRemoveFromFlozySelected() {
    const ids = [...selectedIds];
    const ok = await confirmAction('Remove selected from Flozy?', `Permanently deletes ${ids.length} lead(s) (and their tasks) in your actual Flozy account.`, 'Remove them');
    if (!ok) return;
    showLoadingToast('Removing selected from Flozy…');
    let removed = 0;
    for (const id of ids) {
        const res = await fetch('../api/flozy_remove.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ profile_id: id })
        }).then(r => r.json());
        if (res.success) removed++;
    }
    hideLoadingToast();
    notifyInfo(`Removed ${removed} of ${ids.length} from Flozy.`);
    clearSelection(); loadStats();
}

let table;
let currentView = 'active';

// Built server-side from config/flozy.php so this stays in sync with the
// backend's config instead of being a second hardcoded copy.
const FLOZY_DASHBOARD_BASE_URL = '<?= addslashes(rtrim($flozyDashboardConfig['dashboard_base_url'], '/')) ?>';

function openInFlozy(flozyLeadId) {
    if (!flozyLeadId) {
        notifyWarning('No Flozy lead ID on record for this profile.');
        return;
    }
    window.open(`${FLOZY_DASHBOARD_BASE_URL}/leads/detail/${flozyLeadId}`, '_blank');
}

// Industry benchmark: what counts as "good" engagement depends heavily on
// follower count. Ordered by upper bound of each follower bracket.
const engagementBenchmarks = [
    { maxFollowers: 5000,      high: 6.16, aboveMin: 3.85, avgMin: 3.16, belowMin: 1.85 },
    { maxFollowers: 10000,     high: 2.09, aboveMin: 1.13, avgMin: 0.88, belowMin: 0.46 },
    { maxFollowers: 50000,     high: 1.27, aboveMin: 0.65, avgMin: 0.49, belowMin: 0.24 },
    { maxFollowers: 100000,    high: 0.91, aboveMin: 0.43, avgMin: 0.32, belowMin: 0.15 },
    { maxFollowers: 500000,    high: 0.93, aboveMin: 0.46, avgMin: 0.35, belowMin: 0.16 },
    { maxFollowers: 1000000,   high: 1.00, aboveMin: 0.51, avgMin: 0.39, belowMin: 0.19 },
    { maxFollowers: Infinity,  high: 1.08, aboveMin: 0.57, avgMin: 0.45, belowMin: 0.22 },
];

const tierColors = {
    high:    'rgba(34,197,94,0.20)',
    above:   'rgba(163,230,53,0.16)',
    average: 'rgba(168,85,247,0.16)',
    below:   'rgba(251,146,60,0.16)',
    low:     'rgba(248,113,113,0.18)',
};

function getEngagementTier(followers, engagementRate) {
    const bracket = engagementBenchmarks.find(b => followers <= b.maxFollowers) || engagementBenchmarks[engagementBenchmarks.length - 1];
    if (engagementRate > bracket.high) return 'high';
    if (engagementRate >= bracket.aboveMin) return 'above';
    if (engagementRate >= bracket.avgMin) return 'average';
    if (engagementRate >= bracket.belowMin) return 'below';
    return 'low';
}

// --- Persisted filter values (survive page refresh) ---
function loadSavedFilters() {
    const savedFollowers = localStorage.getItem('cdb_min_followers');
    const savedEngagement = localStorage.getItem('cdb_min_engagement');
    if (savedFollowers !== null) document.getElementById('minFollowers').value = savedFollowers;
    if (savedEngagement !== null) document.getElementById('minEngagement').value = savedEngagement;
}
function saveFilters() {
    localStorage.setItem('cdb_min_followers', document.getElementById('minFollowers').value || 0);
    localStorage.setItem('cdb_min_engagement', document.getElementById('minEngagement').value || 0);
}

function runNicheCheck() {
    const status = document.getElementById('nicheCheckStatus');
    status.textContent = 'Running AI niche check…';
    showLoadingToast('Running AI niche check…');
    fetch('../api/run_niche_check.php')
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            if (!res.success) {
                status.textContent = 'Failed to run niche check.';
                notifyError('Niche check failed.', res);
                return;
            }
            if (res.attempted === 0) {
                status.textContent = 'Queue was already empty — nothing to check.';
            } else {
                status.textContent = `Checked ${res.attempted} profile(s): ${res.classified} classified, ${res.still_pending} still pending (will retry).`;
                notifyInfo(`Checked ${res.attempted} profile(s): ${res.classified} classified.`);
            }
            loadStats();
            table.ajax.reload(null, false);
        })
        .catch(err => { hideLoadingToast(); status.textContent = 'Failed: ' + err; notifyError('Niche check failed.', err); });
}

function loadStats() {
    fetch('../api/stats.php')
        .then(r => r.json())
        .then(s => {
            document.getElementById('statsRow').innerHTML = `
                <div class="stat-card"><div class="label">Active Profiles</div><div class="value">${s.active_count}</div></div>
                <div class="stat-card"><div class="label">Archived</div><div class="value">${s.archived_count}</div></div>
                <div class="stat-card"><div class="label">Sent to Flozy</div><div class="value">${s.flozy_count}</div></div>
                <div class="stat-card"><div class="label">Future (Saved)</div><div class="value">${s.future_count}</div></div>
                <div class="stat-card"><div class="label">Added Today</div><div class="value">${s.added_today}</div></div>
                <div class="stat-card"><div class="label">Added This Week</div><div class="value">${s.added_week}</div></div>
                <div class="stat-card"><div class="label">Added This Month</div><div class="value">${s.added_month}</div></div>
                <div class="stat-card"><div class="label">Duplicates Found</div><div class="value">${s.duplicates_found}</div></div>
                <div class="stat-card">
                    <div class="label">Pending Niche (AI)</div>
                    <div class="value">${s.pending_niche}</div>
                    <button class="small" style="margin-top:6px;" onclick="runNicheCheck()">Run AI Check Now</button>
                </div>
            `;
            document.getElementById('nicheCheckStatus').textContent = '';
        });
}

function initTable() {
    table = $('#profilesTable').DataTable({
        serverSide: true,
        processing: true,
        autoWidth: false,          // let CSS control width instead of DataTables' one-time calculation at init
        stateSave: true,          // remembers page/length/sort across reloads
        stateDuration: 60 * 60 * 24 * 30, // 30 days
        ajax: {
            url: '../api/profiles.php',
            data: function (d) {
                d.min_followers = document.getElementById('minFollowers').value || 0;
                d.max_followers = document.getElementById('maxFollowers').value || '';
                d.min_engagement = document.getElementById('minEngagement').value || 0;
                d.max_engagement = document.getElementById('maxEngagement').value || '';
                d.niche_id = document.getElementById('nicheFilter').value || '';
                d.outreach_status = document.getElementById('outreachFilterSelect').value || '';
                d.view = currentView;
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function (row) {
                    const checked = selectedIds.has(row.id) ? 'checked' : '';
                    return `<input type="checkbox" class="row-select" data-id="${row.id}" data-username="${row.username}" ${checked} onchange="toggleRowSelect(${row.id}, this.checked, '${row.username}')">`;
                }
            },
            { data: 'username', render: u => `<a class="ext-link" href="https://instagram.com/${u}" target="_blank">@${u}</a>` },
            { data: 'full_name', visible: false },
            { data: 'niche', render: n => n ? `<span class="badge">${n}</span>` : '<span style="color:#666">unassigned</span>' },
            { data: 'followers_count', render: (f, type, row) => Number(f).toLocaleString() + (row.is_trending ? ` 🚀<span style="font-size:10px; color:var(--accent);" title="Grew ${row.follower_growth_pct}% since last import">+${row.follower_growth_pct}%</span>` : '') },
            { data: 'engagement_rate', render: e => Number(e).toFixed(2) + '%' },
            {
                data: 'quality_score',
                orderable: true,
                render: function (val) {
                    if (val === null) return '<span style="color:#666">n/a</span>';
                    const pct = parseFloat(val);
                    let grade = 'F';
                    if (pct >= 90) grade = 'A'; else if (pct >= 75) grade = 'B'; else if (pct >= 60) grade = 'C'; else if (pct >= 45) grade = 'D';
                    const color = pct >= 75 ? 'var(--accent)' : (pct >= 50 ? '#facc15' : 'var(--danger)');
                    return `<span style="color:${color}; font-weight:600;">${pct}% (${grade})</span>`;
                }
            },
            { data: 'avg_likes' },
            { data: 'avg_comments' },
            {
                data: 'posts_per_week',
                render: function (val, type, row) {
                    if (val === null) return '<span style="color:#666">n/a</span>';
                    if (val < 2) {
                        return `<span style="color:var(--danger)" title="Inactive — posting less than 2x/week">${val}/wk 🐌</span>`;
                    }
                    if (val > 7) {
                        return `<span style="color:var(--danger)" title="Spammy — posting more than 7x/week">${val}/wk 🔥</span>`;
                    }
                    return `<span title="Healthy posting cadence">${val}/wk</span>`;
                }
            },
            { data: 'biography', visible: false, render: b => b ? (b.length > 60 ? b.substring(0, 60) + '…' : b) : '' },
            { data: 'external_url', visible: false, render: u => u ? `<a class="ext-link" href="${u}" target="_blank">link</a>` : '' },
            { data: 'imported_at' },
            {
                data: 'notes',
                render: function (val, type, row) {
                    const safe = (val || '').replace(/"/g, '&quot;');
                    return `<input type="text" value="${safe}" placeholder="add note…" style="width:150px; background:#0f1115; border:1px solid var(--border); color:var(--text); padding:4px 6px; border-radius:4px; font-size:12px;" onchange="saveNote(${row.id}, this.value)">`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function (row) {
                    if (currentView !== 'flozy' && currentView !== 'active' && currentView !== 'future') return '';
                    const dot = (done, color) => `<span style="display:inline-block; width:9px; height:9px; border-radius:50%; background:${done ? color : '#333844'}; margin-right:3px; vertical-align:middle;"></span>`;
                    const g = dot(row.has_gameplan, '#60a5fa');   // blue = gameplan
                    const s = dot(row.has_scraped_data, '#a78bfa'); // purple = scraped
                    const m = dot(row.has_message, '#fb923c');    // orange = message
                    const reviewWarning = row.manual_review_count > 0
                        ? ` <span style="color:#facc15; cursor:pointer; text-decoration:underline;" onclick="openManualReviewModal(${row.id}, '${row.username}')" title="${row.manual_review_count} reel(s) had no transcript (likely music/b-roll) — click to review">⚠️${row.manual_review_count}</span>`
                        : '';
                    return `<span class="progress-badges"><span title="Gameplan uploaded">${g}📄</span> <span title="Scraping done">${s}🔍</span> <span title="Message generated">${m}💬</span>${reviewWarning}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function (row) {
                    if (currentView !== 'flozy') return '';
                    const openBtn = ` <button class="small ghost" onclick="openInFlozy(${row.flozy_lead_id})" title="Open this lead directly in Flozy">🔗</button>`;
                    if (!row.current_stage) {
                        return '<span style="color:#666; font-size:12px; white-space:nowrap;">not synced</span> <button class="small ghost" onclick="syncFlozyStage(' + row.id + ')" title="Pull latest stage from Flozy">🔄</button>' + openBtn;
                    }
                    const tagColors = { won: 'rgba(34,197,94,0.25)', lost: 'rgba(248,113,113,0.25)', active: 'rgba(96,165,250,0.2)' };
                    const bg = tagColors[row.current_stage_tag] || 'rgba(96,165,250,0.2)';
                    return `<span class="badge" style="background:${bg}; white-space:nowrap;">${row.current_stage}</span> <button class="small ghost" onclick="syncFlozyStage(${row.id})" title="Pull latest stage from Flozy">🔄</button>${openBtn}`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function (row) {
                    if (currentView !== 'flozy') return '';
                    if (row.outreached_at) {
                        return `<span class="badge" style="background:rgba(34,197,94,0.22); white-space:nowrap;" title="Outreached at ${row.outreached_at}">✅ Contacted</span> <button class="small ghost" onclick="toggleOutreach(${row.id}, false)" title="Undo — marks as not yet contacted again">↩️</button>`;
                    }
                    return `<span style="color:#666; font-size:12px; white-space:nowrap;">not contacted</span> <button class="small" style="background:#5B7BFF;" onclick="toggleOutreach(${row.id}, true)">Mark Outreached</button>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function (row) {
                    if (currentView === 'active') {
                        return `
                            <div class="action-menu">
                                <button class="small" style="background:#5B7BFF;" onclick="pushOneToFlozy(${row.id})">Send to Flozy</button>
                                <button class="action-menu-btn" onclick="toggleActionMenu(${row.id})">⋮</button>
                                <div class="action-menu-content" id="menu-${row.id}">
                                    <button onclick="openGrowthChart(${row.id}, '${row.username}')">📈 Growth Chart</button>
                                    <button onclick="triggerGameplanUpload(${row.id})">📄 Upload Gameplan</button>
                                    <button onclick="runVerification(${row.id})">🔍 Verify + Personalize</button>
                                    <button onclick="rerunAiOnly(${row.id})">🔁 Retry AI Only</button>
                                    <button onclick="viewResults(${row.id})">📋 View Results</button>
                                    <button onclick="openGenerationHistory(${row.id}, '${row.username}')">🕐 History</button>
                                    <button onclick="sendToFuture(${row.id})">⏭️ Send to Future</button>
                                    <button onclick="archiveOne(${row.id})" style="color:var(--danger);">🗄️ Archive</button>
                                </div>
                            </div>`;
                    }
                    if (currentView === 'future') {
                        return `
                            <div class="action-menu">
                                <button class="small ghost" onclick="restoreOne(${row.id})">Restore to Active</button>
                                <button class="action-menu-btn" onclick="toggleActionMenu(${row.id})">⋮</button>
                                <div class="action-menu-content" id="menu-${row.id}">
                                    <button onclick="openGrowthChart(${row.id}, '${row.username}')">📈 Growth Chart</button>
                                    <button onclick="triggerGameplanUpload(${row.id})">📄 Upload Gameplan</button>
                                    <button onclick="runVerification(${row.id})">🔍 Verify + Personalize</button>
                                    <button onclick="rerunAiOnly(${row.id})">🔁 Retry AI Only</button>
                                    <button onclick="viewResults(${row.id})">📋 View Results</button>
                                    <button onclick="openGenerationHistory(${row.id}, '${row.username}')">🕐 History</button>
                                    <button onclick="pushOneToFlozy(${row.id})">🚀 Send to Flozy</button>
                                    <button onclick="archiveOne(${row.id})" style="color:var(--danger);">🗄️ Archive</button>
                                </div>
                            </div>`;
                    }
                    if (currentView === 'archived') {
                        return `<button class="small ghost" onclick="openGrowthChart(${row.id}, '${row.username}')">📈</button> <button class="small ghost" onclick="restoreOne(${row.id})">Restore to Active</button>`;
                    }
                    // flozy view
                    return `
                        <div class="action-menu">
                            <button class="small" style="background:#5B7BFF;" onclick="runVerification(${row.id})">🔍 Verify+Personalize</button>
                            <button class="action-menu-btn" onclick="toggleActionMenu(${row.id})">⋮</button>
                            <div class="action-menu-content" id="menu-${row.id}">
                                <button onclick="openInFlozy(${row.flozy_lead_id})">🔗 Open in Flozy</button>
                                <button onclick="openGrowthChart(${row.id}, '${row.username}')">📈 Growth Chart</button>
                                <button onclick="triggerGameplanUpload(${row.id})">📄 Upload Gameplan</button>
                                <button onclick="rerunAiOnly(${row.id})">🔁 Retry AI Only</button>
                                <button onclick="viewResults(${row.id})">📋 View Results</button>
                                <button onclick="openFollowupModal(${row.id})">💬 Follow-up</button>
                                <button onclick="openGenerationHistory(${row.id}, '${row.username}')">🕐 History</button>
                                <button onclick="removeFromFlozy(${row.id})" style="color:var(--danger);">❌ Remove from Flozy</button>
                            </div>
                        </div>`;
                }
            },
        ],
        order: [[4, 'desc']],
        pageLength: 25,
        createdRow: function (row, data) {
            const tier = getEngagementTier(data.followers_count, data.engagement_rate);
            row.style.backgroundColor = tierColors[tier];
            row.title = 'Engagement tier: ' + tier.charAt(0).toUpperCase() + tier.slice(1) + ' (relative to follower count)';
        },
    });
}

function switchView(view) {
    currentView = view;
    document.getElementById('tabActive').classList.toggle('active', view === 'active');
    document.getElementById('tabArchived').classList.toggle('active', view === 'archived');
    document.getElementById('tabFuture').classList.toggle('active', view === 'future');
    document.getElementById('tabFlozy').classList.toggle('active', view === 'flozy');
    document.getElementById('outreachFilterWrap').style.display = (view === 'flozy') ? 'inline-block' : 'none';
    if (view !== 'flozy') {
        document.getElementById('outreachFilterSelect').value = ''; // reset — filter is meaningless outside Flozy tab
    }
    selectedIds.clear();
    selectedUsernames.clear(); // was missing — left stale username entries behind on every tab switch, which is what let "Open Selected in New Tabs" open leftover profiles from a previous tab
    document.getElementById('selectAllVisible').checked = false;
    updateBulkActionBar();
    table.ajax.reload(); // switching tabs is a real context change, page 1 makes sense here
}

function syncFlozyStage(profileId) {
    fetch('../api/sync_flozy_stage.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'sync_one', profile_id: profileId })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { notifyError('Stage sync failed.', res.error); return; }
            notifyInfo(res.stage ? `Stage: ${res.stage}` : (res.note || 'No opportunity found for this lead yet.'));
            table.ajax.reload(null, false);
        })
        .catch(err => notifyError('Stage sync failed.', err));
}

function toggleOutreach(profileId, outreached) {
    fetch('../api/toggle_outreach.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ profile_id: profileId, outreached: outreached })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { notifyError('Could not update outreach status.', res); return; }
            if (res.flozy_task_error) {
                notifyWarning('Marked as outreached, but logging it in Flozy failed.', res.flozy_task_error);
            } else {
                notifyInfo(outreached ? 'Marked as outreached.' : 'Outreach undone.');
            }
            table.ajax.reload(null, false);
        })
        .catch(err => notifyError('Could not update outreach status.', err));
}

function syncAllFlozyStages() {
    showLoadingToast('Syncing pipeline stages…');
    fetch('../api/sync_flozy_stage.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'sync_all' })
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            if (!res.success) { notifyError('Bulk stage sync failed.', res.error); return; }
            notifyInfo(`Synced ${res.synced} of ${res.total} lead(s).`);
            table.ajax.reload(null, false);
        })
        .catch(err => { hideLoadingToast(); notifyError('Bulk stage sync failed.', err); });
}

function pushOneToFlozy(profileId) {
    const status = document.getElementById('flozyStatus');
    status.textContent = 'Pushing…';
    fetch('../api/flozy_push.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'push_one', profile_id: profileId })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                status.textContent = 'Failed: ' + (res.error || 'unknown error');
                return;
            }
            const taskNote = res.task_errors && res.task_errors.length ? ` (${res.task_errors.length} task(s) failed, lead itself is fine)` : '';
            const oppNote = res.opportunity_error ? ` ⚠️ Opportunity not created: ${res.opportunity_error}` : ' Opportunity created too.';
            status.textContent = `Pushed to Flozy.${taskNote}${oppNote}`;
            loadStats();
            table.ajax.reload(null, false);
        })
        .catch(err => status.textContent = 'Failed: ' + err);
}

async function pushQualifiedToFlozy() {
    const minFollowers = document.getElementById('minFollowers').value || 0;
    const maxFollowers = document.getElementById('maxFollowers').value || '';
    const minEngagement = document.getElementById('minEngagement').value || 0;
    const maxEngagement = document.getElementById('maxEngagement').value || '';

    const ok = await confirmAction('Push to Flozy?', 'Pushes every ACTIVE profile within the current Min/Max Followers and Engagement range. Creates real leads (and their default tasks) in your Flozy account.', 'Push them');
    if (!ok) return;

    showLoadingToast('Pushing qualified profiles to Flozy…');
    fetch('../api/flozy_push.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'push_qualified', min_followers: minFollowers, max_followers: maxFollowers, min_engagement: minEngagement, max_engagement: maxEngagement })
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            notifyInfo(`Pushed ${res.pushed} of ${res.total_attempted} qualified profile(s) to Flozy.`);
            if (res.failed.length) notifyWarning(`${res.failed.length} profile(s) failed to push — details in console.`, res.failed);
            loadStats();
            table.ajax.reload(null, false);
        })
        .catch(err => { hideLoadingToast(); notifyError('Push to Flozy failed.', err); });
}

async function removeFromFlozy(profileId) {
    const ok = await confirmAction('Remove from Flozy?', 'This permanently deletes the lead (and its tasks) in your actual Flozy account, not just here.', 'Remove it');
    if (!ok) return;

    fetch('../api/flozy_remove.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ profile_id: profileId })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { notifyError('Remove from Flozy failed.', res.error); return; }
            notifyInfo('Removed from Flozy.');
            loadStats();
            table.ajax.reload(null, false);
        })
        .catch(err => notifyError('Remove from Flozy failed.', err));
}

function reloadTable() {
    saveFilters();
    table.ajax.reload(null, false);
}

async function archiveBelowThreshold() {
    const minFollowers = document.getElementById('minFollowers').value || 0;
    const minEngagement = document.getElementById('minEngagement').value || 0;

    const ok = await confirmAction('Archive underperformers?', `Archives every ACTIVE profile with fewer than ${minFollowers} followers AND below ${minEngagement}% engagement (must fail both). Not deleted — just moved to Archived.`, 'Archive them');
    if (!ok) return;

    showLoadingToast('Archiving…');
    fetch('../api/archive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'bulk_archive_below_threshold',
            min_followers: minFollowers,
            min_engagement: minEngagement,
        })
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            notifyInfo(`Archived ${res.archived_count} profile(s).`);
            loadStats();
            table.ajax.reload(null, false);
        })
        .catch(err => { hideLoadingToast(); notifyError('Archive failed.', err); });
}

async function sendToFutureInRange() {
    const minFollowers = document.getElementById('minFollowers').value || 0;
    const maxFollowers = document.getElementById('maxFollowers').value || '';
    const minEngagement = document.getElementById('minEngagement').value || 0;
    const maxEngagement = document.getElementById('maxEngagement').value || '';

    const ok = await confirmAction('Send range to Future?', 'Moves every ACTIVE profile WITHIN the current Min/Max Followers and Engagement range to the Future tab.', 'Move them');
    if (!ok) return;

    showLoadingToast('Moving to Future…');
    fetch('../api/archive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'bulk_future_in_range',
            min_followers: minFollowers, max_followers: maxFollowers,
            min_engagement: minEngagement, max_engagement: maxEngagement,
        })
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            notifyInfo(`Moved ${res.moved_count} profile(s) to Future.`);
            loadStats();
            table.ajax.reload(null, false);
        })
        .catch(err => { hideLoadingToast(); notifyError('Send to Future failed.', err); });
}

function saveNote(profileId, notes) {
    fetch('../api/notes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ profile_id: profileId, notes: notes })
    });
}

function loadNicheOptions() {
    fetch('../api/niches.php')
        .then(r => r.json())
        .then(res => {
            const select = document.getElementById('nicheFilter');
            const current = select.value;
            select.innerHTML = '<option value="">All niches</option>';
            res.data.forEach(n => {
                const opt = document.createElement('option');
                opt.value = n.id;
                opt.textContent = `${n.name} (${n.profile_count})`;
                select.appendChild(opt);
            });
            select.value = current;
        });
}

let growthChartInstance = null;
function openGrowthChart(profileId, username) {
    document.getElementById('growthModal').style.display = 'flex';
    document.getElementById('growthModalTitle').textContent = `📈 Growth History — @${username}`;
    document.getElementById('growthEmptyNote').style.display = 'none';

    fetch(`../api/profile_history.php?profile_id=${profileId}`)
        .then(r => r.json())
        .then(res => {
            const history = res.history || [];
            const ctx = document.getElementById('growthChartCanvas').getContext('2d');
            if (growthChartInstance) growthChartInstance.destroy();

            if (history.length < 2) {
                document.getElementById('growthEmptyNote').style.display = 'block';
            }

            growthChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: history.map(h => h.imported_at.split(' ')[0]),
                    datasets: [
                        { label: 'Followers', data: history.map(h => h.followers_count), borderColor: '#60a5fa', yAxisID: 'y', tension: 0.2 },
                        { label: 'Engagement %', data: history.map(h => h.engagement_rate), borderColor: '#6ee7b7', yAxisID: 'y1', tension: 0.2 },
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { type: 'linear', position: 'left', ticks: { color: '#8b8f9a' } },
                        y1: { type: 'linear', position: 'right', ticks: { color: '#8b8f9a' }, grid: { drawOnChartArea: false } },
                        x: { ticks: { color: '#8b8f9a' } },
                    },
                    plugins: { legend: { labels: { color: '#e8e9ec' } } },
                }
            });
        });
}
function closeGrowthModal() {
    document.getElementById('growthModal').style.display = 'none';
}

function openImportHistory() {
    document.getElementById('importHistoryModal').style.display = 'flex';
    fetch('../api/import_history.php')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('importHistoryBody');
            tbody.innerHTML = res.data.map(b => `
                <tr>
                    <td style="padding:6px;">${b.filename}</td>
                    <td style="padding:6px;">${b.total_in_file}</td>
                    <td style="padding:6px;">${b.new_count}</td>
                    <td style="padding:6px;">${b.duplicate_count}</td>
                    <td style="padding:6px;">${b.imported_at}</td>
                </tr>
            `).join('');
        });
}
function closeImportHistory() {
    document.getElementById('importHistoryModal').style.display = 'none';
}

function sendToFuture(profileId) {
    fetch('../api/archive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_to_future', profile_id: profileId })
    }).then(() => { loadStats(); table.ajax.reload(null, false); });
}

function archiveOne(profileId) {
    fetch('../api/archive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'archive_one', profile_id: profileId })
    }).then(() => { loadStats(); table.ajax.reload(null, false); });
}

function restoreOne(profileId) {
    fetch('../api/archive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'restore_one', profile_id: profileId })
    }).then(() => { loadStats(); table.ajax.reload(null, false); });
}

function uploadFile() {
    const fileInput = document.getElementById('jsonFile');
    const status = document.getElementById('uploadStatus');
    if (!fileInput.files.length) {
        status.textContent = 'Choose a JSON file first.';
        return;
    }

    const formData = new FormData();
    formData.append('jsonFile', fileInput.files[0]);
    status.textContent = 'Importing…';

    fetch('../api/import.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.error) {
                status.textContent = 'Error: ' + res.error;
                return;
            }
            status.textContent = `Done. ${res.total} profiles in file — ${res.new} new, ${res.duplicates} already known (updated).`;
            loadStats();
            table.ajax.reload(null, false);
        })
        .catch(err => status.textContent = 'Upload failed: ' + err);
}

let scoreWeights = null;
function loadScoreWeights() {
    fetch('../api/score_weights.php')
        .then(r => r.json())
        .then(res => {
            scoreWeights = {};
            res.data.forEach(w => scoreWeights[w.metric_key] = { weight: parseFloat(w.weight), active: w.is_active == 1 });
            if (table) table.ajax.reload(null, false); // refresh scores once weights are known
        });
}

function computeQualityScore(row) {
    if (!scoreWeights) return null;

    const engTier = getEngagementTier(row.followers_count, row.engagement_rate);
    const engPoints = { high: 5, above: 4, average: 3, below: 2, low: 1 }[engTier];

    let postPoints;
    if (row.posts_per_week === null) postPoints = 3;
    else if (row.posts_per_week < 2 || row.posts_per_week > 7) postPoints = 2;
    else postPoints = 5;

    const growthPoints = row.is_trending ? 5 : 3;
    const nichePoints = (row.niche && row.niche !== '') ? 5 : 3;

    const components = [
        { key: 'engagement_tier', label: 'Engagement', points: engPoints },
        { key: 'posting_consistency', label: 'Posting', points: postPoints },
        { key: 'growth_trend', label: 'Growth', points: growthPoints },
        { key: 'niche_assigned', label: 'Niche', points: nichePoints },
    ];

    let weightedSum = 0, maxPossible = 0;
    const breakdownParts = [];

    components.forEach(c => {
        const w = scoreWeights[c.key];
        if (!w || !w.active) return;
        weightedSum += c.points * w.weight;
        maxPossible += 5 * w.weight;
        breakdownParts.push(`${c.label}: ${c.points}/5`);
    });

    if (maxPossible === 0) return null;

    const pct = Math.round((weightedSum / maxPossible) * 100);
    let grade = 'F';
    if (pct >= 90) grade = 'A';
    else if (pct >= 75) grade = 'B';
    else if (pct >= 60) grade = 'C';
    else if (pct >= 45) grade = 'D';

    return { pct, grade, breakdown: breakdownParts.join(' | ') };
}

let pendingFollowupProfileId = null;

function openFollowupModal(profileId) {
    pendingFollowupProfileId = profileId;
    document.getElementById('followupType').value = 'regular';
    document.getElementById('followupUserInput').value = '';
    document.getElementById('followupInputWrap').style.display = 'none';
    document.getElementById('followupResultWrap').style.display = 'none';
    document.getElementById('followupModal').style.display = 'flex';
}
function closeFollowupModal() {
    document.getElementById('followupModal').style.display = 'none';
}
function onFollowupTypeChange() {
    const type = document.getElementById('followupType').value;
    document.getElementById('followupInputWrap').style.display = (type === 'regular') ? 'none' : 'block';
}
function generateFollowup() {
    const type = document.getElementById('followupType').value;
    const userInput = document.getElementById('followupUserInput').value.trim();

    if (type !== 'regular' && !userInput) {
        notifyWarning('This follow-up type needs you to say what you\'re sharing first.');
        return;
    }

    showLoadingToast('Generating follow-up…');
    fetch('../api/generate_followup.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ profile_id: pendingFollowupProfileId, followup_type: type, user_input: userInput })
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            if (!res.success) { notifyError('Follow-up generation failed.', res.error); return; }
            document.getElementById('followupResult').value = res.message;
            document.getElementById('followupResultWrap').style.display = 'block';
        })
        .catch(err => { hideLoadingToast(); notifyError('Follow-up generation failed.', err); });
}

const followupTypeLabels = {
    cold_outreach: '🎣 Cold Outreach (Hook + Follow-up)',
    regular: '💬 Regular Follow-up',
    validation_script: '📋 Validation Script',
    free_value: '🎁 Free Value',
    win_insight: '💡 Win/Insight Share',
    custom_survey: '📊 Free Custom Survey Offer',
};

function openGenerationHistory(profileId, username) {
    document.getElementById('genHistoryTitle').textContent = `🕐 Generation History — @${username}`;
    document.getElementById('genHistoryModal').style.display = 'flex';
    document.getElementById('genHistoryTimeline').innerHTML = '<p style="color:var(--muted); font-size:13px;">Loading…</p>';

    fetch(`../api/generation_history.php?profile_id=${profileId}`)
        .then(r => r.json())
        .then(res => {
            const freshnessEl = document.getElementById('genHistoryFreshness');
            if (res.days_since_scrape === null) {
                freshnessEl.style.background = 'rgba(96,165,250,0.15)';
                freshnessEl.innerHTML = 'No content scraped for this lead yet.';
            } else {
                const stale = res.days_since_scrape > 30;
                freshnessEl.style.background = stale ? 'rgba(248,113,113,0.15)' : 'rgba(34,197,94,0.15)';
                freshnessEl.innerHTML = `Last scraped: <b>${res.days_since_scrape} day(s) ago</b> (${res.last_scraped_at})` +
                    (stale ? ' — content may be stale, worth a fresh Verify+Personalize before the next touchpoint.' : '');
            }

            const timeline = document.getElementById('genHistoryTimeline');
            if (!res.timeline.length) {
                timeline.innerHTML = '<p style="color:var(--muted); font-size:13px;">Nothing generated for this lead yet.</p>';
                return;
            }

            timeline.innerHTML = res.timeline.map(entry => {
                const label = followupTypeLabels[entry.type] || entry.type;
                const hookLine = entry.hook ? `<div style="font-weight:600; margin-bottom:4px;">🎣 ${entry.hook}</div>` : '';
                const inputLine = entry.user_input ? `<div style="font-size:11px; color:var(--muted); margin-bottom:4px;">You shared: ${entry.user_input}</div>` : '';
                return `
                    <div style="background:#0f1115; border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:10px;">
                        <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--muted); margin-bottom:8px;">
                            <span>${label}</span><span>${entry.generated_at}</span>
                        </div>
                        ${hookLine}${inputLine}
                        <div style="font-size:13px;">${entry.message}</div>
                    </div>
                `;
            }).join('');
        })
        .catch(err => notifyError('Could not load generation history.', err));
}
function closeGenerationHistory() {
    document.getElementById('genHistoryModal').style.display = 'none';
}

function openSweepHistory() {
    document.getElementById('sweepHistoryModal').style.display = 'flex';
    fetch('../api/sweep_schedule_log.php')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('sweepHistoryBody');
            if (!res.data.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="padding:10px; color:var(--muted);">No scheduled runs logged yet — set up jobs/scheduled_budget_sweep.php in Task Scheduler.</td></tr>';
                return;
            }
            tbody.innerHTML = res.data.map(r => `
                <tr>
                    <td style="padding:6px;">${r.run_date}</td>
                    <td style="padding:6px;">${r.was_active_day == 1 ? '✅' : '⬜'}</td>
                    <td style="padding:6px;">${r.daily_percentage !== null ? r.daily_percentage + '%' : '—'}</td>
                    <td style="padding:6px; font-size:11px;">${r.keys_used || '—'}</td>
                    <td style="padding:6px;">${r.leads_refreshed}/${r.leads_attempted}</td>
                    <td style="padding:6px; font-size:11px; color:var(--muted);">${r.note || ''}</td>
                </tr>
            `).join('');
        });
}
function closeSweepHistory() {
    document.getElementById('sweepHistoryModal').style.display = 'none';
}

function loadBudgetSweepInfo() {
    fetch('../api/budget_sweep.php')
        .then(r => r.json())
        .then(res => {
            const budgetText = res.remaining_budget !== null ? `$${res.remaining_budget.toFixed(2)}` : 'unknown (budget check inconclusive)';
            const windowNote = res.is_sweep_window ? ' — <b style="color:var(--accent);">this is the 27th/28th sweep window</b>' : ` (sweep window is the 27th/28th, today is the ${res.day_of_month}${res.day_of_month === 1 ? 'st' : ''})`;
            document.getElementById('budgetSweepInfo').innerHTML = `Remaining across active keys: <b>${budgetText}</b> · ${res.eligible_leads} eligible active lead(s)${windowNote}`;
        })
        .catch(() => { document.getElementById('budgetSweepInfo').textContent = 'Could not load budget info.'; });
}

async function runBudgetSweep() {
    const ok = await confirmAction('Run budget sweep?', 'Refreshes data for active leads (oldest-data-first), spending Apify budget in the process. Not tied to messaging — purely a data refresh.', 'Run it');
    if (!ok) return;

    const status = document.getElementById('budgetSweepStatus');
    showLoadingToast('Running budget sweep… this can take a while for several leads.');
    status.textContent = '';

    fetch('../api/budget_sweep.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            if (!res.success) { notifyError('Budget sweep failed.', res.error); return; }
            status.textContent = `Refreshed ${res.refreshed} of ${res.attempted} lead(s).`;
            notifyInfo(`Sweep done — refreshed ${res.refreshed} lead(s).`);
            if (res.failed.length) notifyWarning(`${res.failed.length} failed — details in console.`, res.failed);
            loadBudgetSweepInfo();
        })
        .catch(err => { hideLoadingToast(); notifyError('Budget sweep failed.', err); });
}

// Every real data column, in the same order as the DataTable's columns
// array below — used to build the "👁 Columns" toggle menu dynamically
// instead of hardcoding a handful. Username/Actions excluded (identity +
// functional columns, not optional). Matches the `visible` defaults set on
// the actual column definitions further down.
const toggleableColumns = [
    { idx: 2,  label: 'Full Name' },
    { idx: 3,  label: 'Niche' },
    { idx: 4,  label: 'Followers' },
    { idx: 5,  label: 'Engagement %' },
    { idx: 6,  label: 'Score' },
    { idx: 7,  label: 'Avg Likes' },
    { idx: 8,  label: 'Avg Comments' },
    { idx: 9,  label: 'Posts/Week' },
    { idx: 10, label: 'Bio' },
    { idx: 11, label: 'Link' },
    { idx: 12, label: 'Last Updated' },
    { idx: 13, label: 'Notes' },
    { idx: 14, label: 'Progress' },
    { idx: 15, label: 'Pipeline Stage' },
    { idx: 16, label: 'Outreach' },
];

function buildColumnToggleMenu() {
    document.getElementById('columnToggleMenu').innerHTML = toggleableColumns.map(c => `
        <label style="display:flex; width:100%; align-items:center; gap:8px; padding:9px 12px; font-size:12px; cursor:pointer;">
            <input type="checkbox" ${table.column(c.idx).visible() ? 'checked' : ''} onchange="table.column(${c.idx}).visible(this.checked)">
            ${c.label}
        </label>
    `).join('');
}

function toggleActionMenu(id) {
    document.querySelectorAll('.action-menu-content').forEach(el => {
        if (el.id !== 'menu-' + id) el.classList.remove('open');
    });
    document.getElementById('menu-' + id).classList.toggle('open');
}
function toggleColumnMenu() {
    document.querySelectorAll('.action-menu-content').forEach(el => {
        if (el.id !== 'columnToggleMenu') el.classList.remove('open');
    });
    document.getElementById('columnToggleMenu').classList.toggle('open');
}
document.addEventListener('click', function (e) {
    if (!e.target.closest('.action-menu')) {
        document.querySelectorAll('.action-menu-content').forEach(el => el.classList.remove('open'));
    }
});

let pendingGameplanProfileId = null;
function triggerGameplanUpload(profileId) {
    pendingGameplanProfileId = profileId;
    document.getElementById('gameplanFileInput').click();
}
function handleGameplanFileSelected() {
    const fileInput = document.getElementById('gameplanFileInput');
    const file = fileInput.files[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('gameplan', file);
    fd.append('profile_id', pendingGameplanProfileId);

    showLoadingToast('Uploading gameplan…');
    fetch('../api/gameplan_upload.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            if (!res.success) { notifyError('Gameplan upload failed.', res.error); return; }
            if (res.text_extracted) {
                const taskNote = res.flozy_tasks_completed > 0 ? ` Marked "Gameplan" task complete in Flozy.` : '';
                notifyInfo('Gameplan uploaded and text extracted.' + taskNote);
            } else {
                notifyWarning('Gameplan uploaded, but text extraction failed — PDF saved anyway. Verification won\'t work until this is fixed.', res);
            }
        })
        .catch(err => { hideLoadingToast(); notifyError('Gameplan upload failed.', err); });

    fileInput.value = '';
}

async function runVerification(profileId) {
    const ok = await confirmAction('Run Verify + Personalize?', 'Calls Apify (Reel + Comment scraping — real cost) and Gemini. Can take a minute or two.', 'Run it');
    if (!ok) return;

    showLoadingToast('AI is verifying + personalizing… please don\'t close this tab.');
    fetch('../api/run_verification.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ profile_id: profileId })
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            if (!res.success) { notifyError('Verification run failed.', res.error); return; }
            notifyInfo('Verification & personalization ready.');
            showResultsModal(res.verification_summary, res.draft_hook, res.draft_message);
        })
        .catch(err => { hideLoadingToast(); notifyError('Verification run failed.', err); });
}

function rerunAiOnly(profileId) {
    showLoadingToast('Re-running AI on already-fetched data…');
    fetch('../api/rerun_ai_analysis.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ profile_id: profileId })
    })
        .then(r => r.json())
        .then(res => {
            hideLoadingToast();
            if (!res.success) { notifyError('AI retry failed.', res.error); return; }
            notifyInfo('AI analysis ready.');
            showResultsModal(res.verification_summary, res.draft_hook, res.draft_message);
        })
        .catch(err => { hideLoadingToast(); notifyError('AI retry failed.', err); });
}

function viewResults(profileId) {
    fetch(`../api/content_analysis_result.php?profile_id=${profileId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.latest_run || res.latest_run.status !== 'done') {
                notifyWarning('No completed verification results yet for this lead.');
                return;
            }
            showResultsModal(res.latest_run.verification_summary, res.latest_run.draft_hook, res.latest_run.draft_message);
        })
        .catch(err => notifyError('Could not load results.', err));
}

function showResultsModal(verification, hook, followup) {
    document.getElementById('resultsVerification').textContent = verification;
    document.getElementById('resultsHook').value = hook || '(hook parsing failed — check the follow-up box, the content is probably all in there)';
    document.getElementById('resultsFollowup').value = followup;
    document.getElementById('resultsModal').style.display = 'flex';
}
function closeResultsModal() {
    document.getElementById('resultsModal').style.display = 'none';
}
function copyText(boxId, statusId) {
    const box = document.getElementById(boxId);
    box.select();
    navigator.clipboard.writeText(box.value).then(() => {
        document.getElementById(statusId).textContent = 'Copied!';
        setTimeout(() => document.getElementById(statusId).textContent = '', 2000);
    });
}

let pendingReviewProfileId = null;
function openManualReviewModal(profileId, username) {
    pendingReviewProfileId = profileId;
    document.getElementById('manualReviewTitle').textContent = `⚠️ Reels Needing Manual Review — @${username}`;
    document.getElementById('manualReviewModal').style.display = 'flex';
    loadManualReviewList();
}
function closeManualReviewModal() {
    document.getElementById('manualReviewModal').style.display = 'none';
}
function loadManualReviewList() {
    fetch(`../api/manual_review.php?profile_id=${pendingReviewProfileId}`)
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('manualReviewList');
            if (!res.data.length) {
                list.innerHTML = '<p style="color:var(--muted); font-size:13px;">Nothing left to review.</p>';
                return;
            }
            list.innerHTML = res.data.map(t => `
                <div style="background:#0f1115; border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:10px;">
                    <div style="font-size:12px; color:var(--muted); margin-bottom:6px;">${t.posted_at || 'unknown date'}</div>
                    <div style="font-size:13px; margin-bottom:8px;">${t.caption || '(no caption)'}</div>
                    <a href="${t.post_url}" target="_blank" class="ext-link" style="font-size:12px;">Open reel ↗</a>
                    <div style="margin-top:10px; display:flex; gap:8px;">
                        <button class="small" style="background:var(--accent2);" onclick="resolveManualReview(${t.id}, 'confirmed')">✅ Confirm OK</button>
                        <button class="small danger" onclick="resolveManualReview(${t.id}, 'excluded')">🚫 Exclude</button>
                    </div>
                </div>
            `).join('');
        });
}
function resolveManualReview(transcriptId, status) {
    fetch('../api/manual_review.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ transcript_id: transcriptId, status, profile_id: pendingReviewProfileId })
    }).then(() => {
        notifyInfo(status === 'confirmed' ? 'Marked OK to use.' : 'Excluded from future AI prompts.');
        loadManualReviewList();
        table.ajax.reload(null, false);
    });
}

function openScoreGuide() { document.getElementById('scoreGuideModal').style.display = 'flex'; }
function closeScoreGuide() { document.getElementById('scoreGuideModal').style.display = 'none'; }

function openBenchmarkModal() {
    document.getElementById('benchmarkModal').style.display = 'flex';
}
function closeBenchmarkModal() {
    document.getElementById('benchmarkModal').style.display = 'none';
}
function copyScript() {
    const box = document.getElementById('scriptBox');
    box.select();
    navigator.clipboard.writeText(box.value).then(() => {
        document.getElementById('copyStatus').textContent = 'Copied!';
        setTimeout(() => document.getElementById('copyStatus').textContent = '', 2000);
    });
}

loadSavedFilters();
loadNicheOptions();
loadScoreWeights();
loadBudgetSweepInfo();
loadStats();
initTable();
buildColumnToggleMenu();

window.addEventListener('resize', function () {
    if (table) table.columns.adjust();
});
</script>
</body>
</html>
