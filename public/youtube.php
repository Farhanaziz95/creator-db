<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>YouTube Pipeline</title>
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
    h1 { font-size: 20px; font-weight: 600; margin: 0 0 4px; }
    a { color: var(--accent2); }
    .stats-row { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 20px; }
    .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; }
    .stat-card .label { font-size: 11px; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .03em; }
    .stat-card .value { font-size: 20px; font-weight: 700; }
    .panel { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 18px; margin-bottom: 20px; }
    .panel h2 { font-size: 14px; margin: 0 0 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    label { font-size: 13px; color: var(--muted); }
    input[type="text"], input[type="number"], select, textarea {
        background: #0f1115; border: 1px solid var(--border); color: var(--text);
        padding: 8px 10px; border-radius: 6px; font-size: 13px;
    }
    button {
        background: var(--accent2); border: none; color: #0f1115; font-weight: 600;
        padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px;
    }
    button:hover { opacity: .9; }
    button.ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
    button.small { padding: 4px 10px; font-size: 12px; }
    .tabs { display: flex; gap: 8px; margin-bottom: 16px; }
    .tab-btn { background: transparent; border: 1px solid var(--border); color: var(--muted); padding: 8px 16px; }
    .tab-btn.active { background: var(--accent2); color: #0f1115; border-color: var(--accent2); }
    table.dataTable { color: var(--text) !important; width: 100% !important; }
    .dataTables_wrapper { width: 100%; }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select { background: #0f1115; border: 1px solid var(--border); color: var(--text); border-radius: 4px; }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: var(--muted) !important; }
    table.dataTable thead th { border-bottom: 1px solid var(--border) !important; color: var(--muted); }
    table.dataTable tbody td { border-bottom: 1px solid var(--border) !important; vertical-align: top; }
    .grade { font-weight: 700; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
    .grade-A { background: rgba(110,231,183,.15); color: var(--accent); }
    .grade-B { background: rgba(96,165,250,.15); color: var(--accent2); }
    .grade-C { background: rgba(250,204,21,.15); color: #facc15; }
    .grade-D { background: rgba(248,113,113,.15); color: var(--danger); }
    .verdict-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; white-space: nowrap; }
    .verdict-qualify { background: rgba(110,231,183,.15); color: var(--accent); }
    .verdict-reject { background: rgba(248,113,113,.15); color: var(--danger); }
    .verdict-needs_review { background: rgba(250,204,21,.15); color: #facc15; }
    .channel-detail { padding: 12px 4px; }
    .reasoning-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 10px 0; }
    .reasoning-item { background: #0f1115; border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; font-size: 12px; }
    .reasoning-item b { color: var(--muted); display: block; margin-bottom: 4px; }
    .expand-btn { background: transparent; border: none; color: var(--text); cursor: pointer; font-size: 14px; }
    #toast { position: fixed; top: 20px; right: 20px; background: var(--card); border: 1px solid var(--border); padding: 12px 18px; border-radius: 8px; display: none; z-index: 1000; max-width: 400px; }
</style>
</head>
<body>

<h1>🎬 YouTube Pipeline</h1>
<p style="color: var(--muted); font-size: 13px; margin: 0 0 8px;">
    <a href="index.php">← Instagram dashboard</a> — separate pipeline, shared destination: Flozy.
</p>
<p style="color: var(--muted); font-size: 13px; margin: 0 0 20px;">
    💰 Apify budget remaining (shared with Instagram — both pipelines draw from the same key pool): <span id="apifyBudgetDisplay">checking…</span>
</p>

<div id="toast"></div>

<div class="panel">
    <h2>Round</h2>
    <div class="controls">
        <label>Active round</label>
        <select id="roundSelect" style="min-width:280px;" onchange="loadRound()"></select>
        <button class="ghost" onclick="toggleNewRoundForm()">+ New Round</button>
        <button class="ghost" onclick="toggleSettingsForm()">⚙️ Settings</button>
        <button class="ghost" onclick="openCompareRounds()">📊 Compare Rounds</button>
        <button class="ghost" onclick="openRejectedAudit()">📋 Rejection Audit</button>
    </div>

    <div id="newRoundForm" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
        <div class="controls" style="margin-bottom:10px;">
            <label>Niche</label>
            <input type="text" id="newRoundNiche" placeholder="e.g. personal finance" style="width:200px;">
            <label>Sub-niche keywords (comma-separated)</label>
            <input type="text" id="newRoundSubNiches" placeholder="budgeting, dividend investing" style="width:320px;">
        </div>
        <div class="controls" style="margin-bottom:10px;">
            <label>Hashtags (optional, comma-separated, secondary discovery path)</label>
            <input type="text" id="newRoundHashtags" placeholder="e.g. budgetingtips, fire" style="width:320px;">
        </div>
        <div class="controls">
            <label>Min subscribers</label>
            <input type="number" id="newRoundSubMin" value="1000" style="width:100px;">
            <label>Max channels per keyword/hashtag</label>
            <input type="number" id="newRoundMaxPerKeyword" value="50" style="width:100px;">
            <button onclick="createRound()">Create Round</button>
        </div>
    </div>

    <div id="settingsForm" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
        <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
            gemini_call_delay_seconds and scoring_batch_limit exist to keep a scoring run from burning through the
            Gemini free-tier quota shared with the Instagram dashboard — raise the delay if you still see rate-limit stops.
        </p>
        <div class="controls">
            <label>Videos sampled per channel</label>
            <input type="number" id="settingVideoSampleCount" style="width:80px;">
            <label>Comments per video (Comment Insight)</label>
            <input type="number" id="settingCommentsPerVideo" style="width:80px;">
            <label>Gemini call delay (seconds)</label>
            <input type="number" id="settingDelaySeconds" style="width:80px;">
            <label>Channels scored per run</label>
            <input type="number" id="settingBatchLimit" style="width:80px;">
            <button onclick="saveSettings()">Save</button>
        </div>
    </div>
</div>

<!-- Compare Rounds modal — the actual payoff of the round/cohort idea:
     see raw→qualified→pushed side by side across every niche tested,
     instead of tallying it by hand from the dropdown one at a time. -->
<div id="compareRoundsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:1000px; width:92%; max-height:85vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px;">📊 Compare Rounds</h2>
            <button class="ghost small" onclick="closeCompareRounds()">✕ Close</button>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="text-align:left; color:var(--muted); border-bottom:1px solid var(--border);">
                    <th style="padding:8px;">Niche</th>
                    <th style="padding:8px;">Sub-niches</th>
                    <th style="padding:8px;">Total</th>
                    <th style="padding:8px;">Raw</th>
                    <th style="padding:8px;">Qualified</th>
                    <th style="padding:8px;">Rejected</th>
                    <th style="padding:8px;">Pushed</th>
                    <th style="padding:8px;">With Email</th>
                    <th style="padding:8px;">Qualify Rate</th>
                </tr>
            </thead>
            <tbody id="compareRoundsBody"></tbody>
        </table>
        <p style="font-size:12px; color:var(--muted); margin-top:14px;">
            Qualify Rate = Qualified ÷ Total scored so far (Raw channels not yet scored aren't counted against a round — a round with a lot left in Raw isn't necessarily worse, just earlier in review).
        </p>
    </div>
</div>

<!-- Rejection Audit modal — cross-round on purpose, so you can spot
     whether the AI's reject calls hold up consistently across niches,
     not just within whichever round happens to be selected. -->
<div id="rejectedAuditModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:1000px; width:92%; max-height:85vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:16px;">📋 Rejection Audit — every rejected channel, every round</h2>
            <button class="ghost small" onclick="closeRejectedAudit()">✕ Close</button>
        </div>
        <div class="controls" style="margin-bottom:12px;">
            <label>Filter</label>
            <select id="rejectedAuditFilter" onchange="renderRejectedAudit()">
                <option value="all">All</option>
                <option value="AI">AI-rejected only</option>
                <option value="Manual">Manually rejected only</option>
            </select>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="text-align:left; color:var(--muted); border-bottom:1px solid var(--border);">
                    <th style="padding:8px;">Channel</th>
                    <th style="padding:8px;">Niche</th>
                    <th style="padding:8px;">Subs</th>
                    <th style="padding:8px;">Score</th>
                    <th style="padding:8px;">Rejected By</th>
                    <th style="padding:8px;">Reason</th>
                </tr>
            </thead>
            <tbody id="rejectedAuditBody"></tbody>
        </table>
        <p id="rejectedAuditEmpty" style="color:var(--muted); font-size:13px; display:none;">No rejections match this filter.</p>
    </div>
</div>

<div class="stats-row" id="statsRow"></div>

<div class="panel">
    <div class="controls">
        <button onclick="runDiscovery()">🔎 Run Discovery</button>
        <button onclick="runScoring()">🤖 Run Scoring (next batch)</button>
        <span id="jobStatus" style="font-size:12px; color:var(--muted);"></span>
    </div>
</div>

<div class="tabs">
    <button class="tab-btn active" data-tab="all" onclick="switchTab('all')">All</button>
    <button class="tab-btn" data-tab="raw" onclick="switchTab('raw')">Raw</button>
    <button class="tab-btn" data-tab="qualified" onclick="switchTab('qualified')">Qualified</button>
    <button class="tab-btn" data-tab="rejected" onclick="switchTab('rejected')">Rejected</button>
    <button class="tab-btn" data-tab="pushed" onclick="switchTab('pushed')">Pushed to Flozy</button>
</div>
<p style="color:var(--muted); font-size:12px; margin:-10px 0 16px;">
    💡 Tip: sort a column (e.g. Subs), check one row, then shift-click another row's checkbox to select everything between them.
</p>

<div class="panel" id="bulkBar" style="display:none;">
    <div class="controls">
        <span id="selectedCount" style="font-size:13px;"></span>
        <span id="bulkActionButtons"></span>
        <button class="ghost" onclick="clearSelection()">Clear</button>
    </div>
</div>

<div class="panel">
    <table id="channelsTable" class="display" style="width:100%;">
        <thead>
            <tr>
                <th></th>
                <th><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"></th>
                <th>Channel</th>
                <th>Subs</th>
                <th>Videos</th>
                <th>Country</th>
                <th>Sub-niche</th>
                <th>Email</th>
                <th>Business Email</th>
                <th>Score</th>
                <th>Verdict</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
/**
 * ============================================================
 * DATA LOADING — everything that fetches from the backend
 * ============================================================
 */

let rounds = [];               // every round, as returned by youtube_rounds_list.php
let currentRoundId = null;     // which round's channels are currently loaded
let allChannels = [];          // every channel in the current round (unfiltered)
let currentTab = 'all';        // which status tab is active: all/raw/qualified/rejected/pushed
let selectedIds = new Set();   // channel IDs checked for bulk-push
let table = null;              // the DataTable instance itself

function showToast(msg, isError) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.style.borderColor = isError ? 'var(--danger)' : 'var(--border)';
    el.style.display = 'block';
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => { el.style.display = 'none'; }, 6000);
}

// Fetches every round (with its summary counts) and fills the round
// dropdown. Called on page load, and again after anything that could
// change a round's counts (create round, discovery, scoring, push, a
// manual qualify/reject).
function loadRounds(selectRoundId) {
    fetch('../api/youtube_rounds_list.php')
        .then(r => r.json())
        .then(res => {
            rounds = res.rounds || [];
            const select = document.getElementById('roundSelect');
            select.innerHTML = rounds.map(r =>
                `<option value="${r.id}">${r.niche} — ${r.sub_niches.join(', ')}${r.hashtags && r.hashtags.length ? ' + #' + r.hashtags.join(', #') : ''} (${r.total_channels} channels)</option>`
            ).join('');
            if (rounds.length) {
                select.value = selectRoundId || currentRoundId || rounds[0].id;
                loadRound();
            }
        });
}

// Switches which round is active: updates the stat cards from the
// already-fetched `rounds` array, then fetches that round's channels.
function loadRound() {
    currentRoundId = parseInt(document.getElementById('roundSelect').value, 10);
    selectedIds.clear();
    renderStats();
    loadChannels();
}

function renderStats() {
    const round = rounds.find(r => r.id === currentRoundId);
    const statsRow = document.getElementById('statsRow');
    if (!round) { statsRow.innerHTML = ''; return; }
    const cards = [
        ['Total', round.total_channels], ['Raw', round.raw_count], ['Qualified', round.qualified_count],
        ['Rejected', round.rejected_count], ['Pushed', round.pushed_count], ['With Email', round.with_email_count],
    ];
    statsRow.innerHTML = cards.map(([label, value]) =>
        `<div class="stat-card"><div class="label">${label}</div><div class="value">${value}</div></div>`
    ).join('');
}

// Fetches every channel for the current round (scores already joined in
// by the backend) and hands them to the DataTable via applyTabFilter().
function loadChannels() {
    if (!currentRoundId) return;
    fetch(`../api/youtube_channels.php?round_id=${currentRoundId}`)
        .then(r => r.json())
        .then(res => {
            allChannels = res.channels || [];
            applyTabFilter();
        });
}

/**
 * ============================================================
 * THE DATATABLE ITSELF
 * ============================================================
 * One DataTable, client-side (all of a round's channels are fetched in
 * one go — rounds are at most a few hundred rows, small enough that we
 * don't need server-side paging like the Instagram dashboard's
 * thousands of profiles do). Sorting/search/pagination all come from
 * DataTables itself once the data's handed to it — none of that is
 * hand-built.
 */
function initTable() {
    table = $('#channelsTable').DataTable({
        data: [],
        pageLength: 25,
        columns: [
            {
                // The expand/collapse arrow — same "child row" mechanism
                // the Instagram dashboard's accordion uses
                // (table.row(tr).child(html).show()), just simplified to
                // one panel instead of tabs since there's only one thing
                // to show here (the AI's reasoning).
                data: null, orderable: false, className: 'expand-btn-cell',
                render: () => '<button class="expand-btn" onclick="toggleChannelDetail(this)">▶</button>'
            },
            {
                data: 'id', orderable: false,
                render: (id) => `<input type="checkbox" ${selectedIds.has(id) ? 'checked' : ''} onchange="toggleSelect(${id}, this.checked, event)">`
            },
            {
                data: 'channel_name',
                render: (name, type, row) => type === 'display'
                    ? `<a href="${row.channel_url}" target="_blank">${name || row.channel_username || '(unnamed)'}</a>${row.needs_manual_review ? ' ⚠️' : ''}`
                    : (name || row.channel_username || '')
            },
            {
                // type-aware render: DataTables asks for 'sort'/'type' as
                // well as 'display' — returning the raw number for those
                // is what makes the column sort numerically instead of
                // alphabetically on the formatted "12,345" string.
                data: 'subscribers',
                render: (v, type) => type === 'display' ? (v !== null ? Number(v).toLocaleString() : '—') : (v || 0)
            },
            { data: 'total_videos', render: v => v ?? '—' },
            { data: 'country', render: v => v || '—' },
            { data: 'sub_niche', render: v => v || '—' },
            {
                // Editable in case the regex missed or picked up a wrong
                // address — same pattern as Instagram's editable email.
                data: 'email', orderable: false,
                render: (v, type, row) => type === 'display'
                    ? `<input type="email" value="${(v || '').replace(/"/g, '&quot;')}" placeholder="none found" style="width:150px; background:#0f1115; border:1px solid var(--border); color:var(--text); padding:4px 6px; border-radius:4px; font-size:12px;" onchange="saveEmailField(${row.id}, 'email', this.value)">`
                    : (v || '')
            },
            {
                // ALWAYS manually entered — YouTube's protected "business
                // inquiries" email isn't scrapeable at all (click-through
                // + captcha on YouTube's own side). Treated as PRIMARY
                // over the regex `email` wherever only one can be used
                // (the Flozy Contact push).
                data: 'business_email', orderable: false,
                render: (v, type, row) => type === 'display'
                    ? `<input type="email" value="${(v || '').replace(/"/g, '&quot;')}" placeholder="manually enter" style="width:150px; background:#0f1115; border:1px solid var(--border); color:var(--text); padding:4px 6px; border-radius:4px; font-size:12px;" onchange="saveEmailField(${row.id}, 'business_email', this.value)">`
                    : (v || '')
            },
            {
                data: 'total_score',
                render: (score, type, row) => {
                    if (type !== 'display') return score || 0;
                    if (score === null) return '<span style="color:var(--muted);">not scored</span>';
                    const gradeHtml = row.grade ? `<span class="grade grade-${row.grade}">${row.grade}</span>` : '';
                    return `${score}/100 ${gradeHtml}`;
                }
            },
            {
                data: 'ai_verdict', orderable: false,
                render: v => v ? `<span class="verdict-badge verdict-${v}">${v.replace('_', ' ')}</span>` : ''
            },
            {
                data: null, orderable: false,
                render: function (row) {
                    let actions = '';
                    if (row.status !== 'pushed') {
                        actions += `<button class="small ghost" onclick="setStatus(${row.id}, 'qualified')" title="Qualify">✅</button> `;
                        actions += `<button class="small ghost" onclick="setStatus(${row.id}, 'rejected')" title="Reject">❌</button> `;
                        actions += `<button class="small ghost" onclick="setStatus(${row.id}, 'raw')" title="Reset to Raw">↩️</button>`;
                    }
                    if (row.status === 'qualified') {
                        actions += ` <button class="small" style="background:#5B7BFF;" onclick="pushOneToFlozy(${row.id})">🚀 Push</button>`;
                    }
                    if (row.status === 'pushed') {
                        actions = '<span style="color:var(--accent); font-size:12px;">✅ Pushed</span> ' + actions;
                    }
                    return actions;
                }
            },
        ],
        order: [[9, 'desc']], // default sort: highest score first (index shifted +1 for the new Business Email column)
    });
}

// Re-filters `allChannels` down to the active tab and hands the result
// to the DataTable. Called on tab switch and whenever the underlying
// data changes (round switch, after an action completes).
function applyTabFilter() {
    const filtered = currentTab === 'all' ? allChannels : allChannels.filter(c => c.status === currentTab);
    table.clear();
    table.rows.add(filtered);
    table.draw();
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active');
    // Selections don't carry meaning across tabs (a Raw-tab selection of
    // IDs would just silently vanish from view on the Qualified tab
    // without this) — clear on every switch, and rebuild the bulk bar's
    // buttons for whatever action actually makes sense on this tab.
    selectedIds.clear();
    lastCheckedId = null;
    updateBulkBar();
    applyTabFilter();
}

function saveEmailField(channelId, field, value) {
    fetch('../api/youtube_update_email.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel_id: channelId, field, value })
    })
        .then(r => r.json())
        .then(res => { if (!res.success) showToast(res.error || 'Could not save email.', true); })
        .catch(err => showToast('Could not save email: ' + err, true));
}

/**
 * ============================================================
 * ROW EXPAND — shows the AI's full reasoning for one channel
 * ============================================================
 */
function toggleChannelDetail(btnEl) {
    const tr = $(btnEl).closest('tr');
    const row = table.row(tr);

    if (row.child.isShown()) {
        row.child.hide();
        $(btnEl).text('▶');
        return;
    }

    const c = row.data();
    const reasoning = c.ai_reasoning || {};
    const titlesHtml = (c.sample_video_titles && c.sample_video_titles.length)
        ? `<ul style="font-size:12px; color:var(--muted); margin:0 0 10px; padding-left:18px;">${c.sample_video_titles.map((t, i) => {
            const desc = (c.sample_video_descriptions && c.sample_video_descriptions[i]) ? c.sample_video_descriptions[i] : '';
            return `<li style="margin-bottom:8px;">
                <span style="color:var(--text);">${t.replace(/</g, '&lt;')}</span>
                ${desc ? `<div style="margin-top:2px; color:var(--muted);">${desc.replace(/</g, '&lt;')}</div>` : ''}
            </li>`;
        }).join('')}</ul>`
        : `<p style="font-size:12px; color:var(--muted); margin:0 0 10px;">No recent video titles on file — run scoring for this channel to pull them.</p>`;
    const scoredHtml = c.ai_verdict ? `
        <div class="reasoning-grid">
            <div class="reasoning-item"><b>Audience (${c.audience_score}/20)</b>${reasoning.audience || ''}</div>
            <div class="reasoning-item"><b>Engagement (${c.engagement_score}/20)</b>${reasoning.engagement || ''}</div>
            <div class="reasoning-item"><b>Monetization Gap (${c.monetization_score}/25)</b>${reasoning.monetization || ''}</div>
            <div class="reasoning-item"><b>Content (${c.content_score}/15)</b>${reasoning.content || ''}</div>
            <div class="reasoning-item"><b>Opportunity (${c.opportunity_score}/20)</b>${reasoning.opportunity || ''}</div>
        </div>
        <p style="font-size:12px; margin:8px 0 4px;"><b>Product potential:</b> ${c.ai_product_potential || '—'}</p>
        <p style="font-size:12px; margin:0 0 8px;"><b>Pain / opportunity:</b> ${c.ai_pain_opportunity || '—'}</p>
    ` : '<p style="font-size:12px; color:var(--muted);">Not scored yet — run scoring for this round.</p>';

    // Comment Insight — deliberately styled/positioned as its own
    // section, separate from the reasoning grid above, since it's a
    // separate score that never feeds into the main total/grade.
    const themes = c.comment_recurring_themes || [];
    const commentHtml = c.buying_intent_score !== null ? `
        <div style="margin-top:14px; padding-top:12px; border-top:1px solid var(--border);">
            <p style="font-size:12px; margin:0 0 8px; color:var(--muted); text-transform:uppercase; letter-spacing:.03em;">💬 Comment Insight (separate from the score above, ${c.comments_analyzed} comment(s) analyzed)</p>
            <div class="reasoning-grid">
                <div class="reasoning-item"><b>Buying Intent (${c.buying_intent_score}/100)</b></div>
                <div class="reasoning-item"><b>Pain Point Clarity (${c.pain_point_clarity_score}/100)</b></div>
            </div>
            ${themes.length ? `<p style="font-size:12px; margin:8px 0 4px;"><b>Recurring themes:</b> ${themes.join(', ')}</p>` : ''}
            <p style="font-size:12px; margin:0 0 8px;"><b>Summary:</b> ${c.comment_summary || '—'}</p>
        </div>
    ` : '';

    const html = `
        <div class="channel-detail">
            <p style="font-size:12px; color:var(--muted); margin:0 0 8px;"><b>About:</b> ${(c.channel_description || '').replace(/</g, '&lt;')}</p>
            <p style="font-size:12px; margin:0 0 4px;"><b>Recent videos sampled (title + description):</b></p>
            ${titlesHtml}
            ${scoredHtml}
            ${commentHtml}
            <p style="font-size:12px; color:var(--muted); margin:0;">
                <a href="${c.channel_url}" target="_blank">Open channel ↗</a>
                ${c.website ? ` · <a href="${c.website}" target="_blank">Website ↗</a>` : ''}
            </p>
        </div>`;

    row.child(html).show();
    $(btnEl).text('▼');
}

/**
 * ============================================================
 * ROUND / SETTINGS FORMS
 * ============================================================
 */
function toggleNewRoundForm() {
    const f = document.getElementById('newRoundForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function toggleSettingsForm() {
    const f = document.getElementById('settingsForm');
    const opening = f.style.display === 'none';
    f.style.display = opening ? 'block' : 'none';
    if (opening) loadSettings();
}

/**
 * The actual point of the round/cohort idea: see raw→qualified→pushed
 * side by side across every niche tested, so which one to go deep on is
 * a read of a table, not a mental tally kept while flipping through the
 * round dropdown one at a time.
 */
function openCompareRounds() {
    // Refetch fresh rather than reuse the in-memory `rounds` array — the
    // dropdown's copy could be stale if this is opened right after an
    // action elsewhere finished without a round switch to trigger a reload.
    fetch('../api/youtube_rounds_list.php')
        .then(r => r.json())
        .then(res => {
            const body = document.getElementById('compareRoundsBody');
            body.innerHTML = (res.rounds || []).map(r => {
                // "Scored so far" = qualified + rejected — Raw hasn't
                // been judged yet, so it shouldn't drag down a round's
                // rate just for having a backlog left to review.
                const scoredSoFar = r.qualified_count + r.rejected_count;
                const rate = scoredSoFar > 0 ? Math.round((r.qualified_count / scoredSoFar) * 100) : null;
                return `
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px; font-weight:600;">${r.niche}</td>
                        <td style="padding:8px; color:var(--muted);">${r.sub_niches.join(', ')}</td>
                        <td style="padding:8px;">${r.total_channels}</td>
                        <td style="padding:8px;">${r.raw_count}</td>
                        <td style="padding:8px; color:var(--accent);">${r.qualified_count}</td>
                        <td style="padding:8px;">${r.rejected_count}</td>
                        <td style="padding:8px;">${r.pushed_count}</td>
                        <td style="padding:8px;">${r.with_email_count}</td>
                        <td style="padding:8px; font-weight:600;">${rate !== null ? rate + '%' : '—'}</td>
                    </tr>`;
            }).join('');
            document.getElementById('compareRoundsModal').style.display = 'flex';
        })
        .catch(err => showToast('Could not load round comparison: ' + err, true));
}

function closeCompareRounds() {
    document.getElementById('compareRoundsModal').style.display = 'none';
}

let allRejections = []; // held in memory so the filter dropdown re-renders without refetching

/**
 * Cross-round on purpose (see the modal's own comment) — fetches every
 * rejected channel regardless of which round the main dashboard is
 * currently showing.
 */
function openRejectedAudit() {
    fetch('../api/youtube_rejected_audit.php')
        .then(r => r.json())
        .then(res => {
            allRejections = res.rejections || [];
            renderRejectedAudit();
            document.getElementById('rejectedAuditModal').style.display = 'flex';
        })
        .catch(err => showToast('Could not load rejection audit: ' + err, true));
}

function renderRejectedAudit() {
    const filter = document.getElementById('rejectedAuditFilter').value;
    const filtered = filter === 'all' ? allRejections : allRejections.filter(r => r.rejected_by === filter);

    const body = document.getElementById('rejectedAuditBody');
    document.getElementById('rejectedAuditEmpty').style.display = filtered.length ? 'none' : 'block';

    body.innerHTML = filtered.map(r => {
        const scoreLabel = r.total_score !== null ? `${r.total_score}/100${r.grade ? ' ' + r.grade : ''}` : '—';
        const byColor = r.rejected_by === 'AI' ? 'var(--danger)' : 'var(--muted)';
        return `
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:8px;"><a href="${r.channel_url}" target="_blank">${r.channel_name || r.channel_username || '(unnamed)'}</a></td>
                <td style="padding:8px; color:var(--muted);">${r.niche}</td>
                <td style="padding:8px;">${r.subscribers !== null ? Number(r.subscribers).toLocaleString() : '—'}</td>
                <td style="padding:8px;">${scoreLabel}</td>
                <td style="padding:8px; color:${byColor}; font-weight:600;">${r.rejected_by}</td>
                <td style="padding:8px; color:var(--muted);">${(r.reject_reason || '').replace(/</g, '&lt;')}</td>
            </tr>`;
    }).join('');
}

function closeRejectedAudit() {
    document.getElementById('rejectedAuditModal').style.display = 'none';
}

function loadSettings() {
    fetch('../api/youtube_settings.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('settingVideoSampleCount').value = res.video_sample_count;
            document.getElementById('settingCommentsPerVideo').value = res.comments_per_video;
            document.getElementById('settingDelaySeconds').value = res.gemini_call_delay_seconds;
            document.getElementById('settingBatchLimit').value = res.scoring_batch_limit;
        });
}

function saveSettings() {
    const body = {
        video_sample_count: parseInt(document.getElementById('settingVideoSampleCount').value, 10),
        comments_per_video: parseInt(document.getElementById('settingCommentsPerVideo').value, 10),
        gemini_call_delay_seconds: parseInt(document.getElementById('settingDelaySeconds').value, 10),
        scoring_batch_limit: parseInt(document.getElementById('settingBatchLimit').value, 10),
    };
    fetch('../api/youtube_settings.php', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(r => r.json())
        .then(() => showToast('Settings saved.'));
}

function createRound() {
    const niche = document.getElementById('newRoundNiche').value.trim();
    const subNiches = document.getElementById('newRoundSubNiches').value.split(',').map(s => s.trim()).filter(Boolean);
    const hashtags = document.getElementById('newRoundHashtags').value.split(',').map(s => s.trim()).filter(Boolean);
    const subscriberMin = parseInt(document.getElementById('newRoundSubMin').value, 10) || 1000;
    const maxPerKeyword = parseInt(document.getElementById('newRoundMaxPerKeyword').value, 10) || 50;

    if (!niche || !subNiches.length) {
        showToast('Niche and at least one sub-niche keyword are required.', true);
        return;
    }

    fetch('../api/youtube_create_round.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ niche, sub_niches: subNiches, hashtags, subscriber_min: subscriberMin, max_channels_per_keyword: maxPerKeyword })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.error || 'Could not create round.', true); return; }
            showToast(`Round created (#${res.round_id}). Click "Run Discovery" to start scraping.`);
            document.getElementById('newRoundForm').style.display = 'none';
            loadRounds(res.round_id);
        })
        .catch(err => showToast('Could not create round: ' + err, true));
}

/**
 * ============================================================
 * ROW ACTIONS — qualify/reject/reset, push to Flozy, bulk select
 * ============================================================
 */
function setStatus(channelId, status) {
    fetch('../api/youtube_set_channel_status.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel_id: channelId, status })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.error || 'Could not update status.', true); return; }
            loadRounds(currentRoundId); // refetches rounds (updates stat cards) AND this round's channels
        })
        .catch(err => showToast('Could not update status: ' + err, true));
}

function pushOneToFlozy(channelId) {
    fetch('../api/youtube_push_flozy.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'one', channel_id: channelId })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.error || 'Push failed.', true); return; }
            let msg = 'Pushed to Flozy.';
            if (res.contact_error) msg += ` Contact issue: ${res.contact_error}`;
            if (res.opportunity_error) msg += ` Opportunity issue: ${res.opportunity_error}`;
            if (res.cross_platform_warning) msg += ` ⚠️ ${res.cross_platform_warning}`;
            showToast(msg);
            loadRounds(currentRoundId);
        })
        .catch(err => showToast('Push failed: ' + err, true));
}

let lastCheckedId = null; // for shift-click range selection, below

/**
 * Shift-click range select — sort by whatever column matters (e.g.
 * Subs), click one row's checkbox, shift-click another, and everything
 * BETWEEN them (in the table's current sorted/searched order) gets
 * selected too. Uses DataTables' own current row order
 * (`{ search: 'applied' }`) so the range respects sorting and any active
 * search filter, not just the original fetch order.
 */
function toggleSelect(id, checked, event) {
    if (event && event.shiftKey && lastCheckedId !== null) {
        const orderedIds = table.rows({ search: 'applied' }).data().toArray().map(r => r.id);
        const fromIdx = orderedIds.indexOf(lastCheckedId);
        const toIdx = orderedIds.indexOf(id);
        if (fromIdx !== -1 && toIdx !== -1) {
            const [start, end] = fromIdx < toIdx ? [fromIdx, toIdx] : [toIdx, fromIdx];
            for (let i = start; i <= end; i++) {
                if (checked) selectedIds.add(orderedIds[i]); else selectedIds.delete(orderedIds[i]);
            }
            table.rows().invalidate().draw(false);
        }
    } else {
        if (checked) selectedIds.add(id); else selectedIds.delete(id);
    }
    lastCheckedId = id;
    updateBulkBar();
}

function toggleSelectAll(checkbox) {
    const filtered = currentTab === 'all' ? allChannels : allChannels.filter(c => c.status === currentTab);
    filtered.forEach(c => {
        if (checkbox.checked) selectedIds.add(c.id); else selectedIds.delete(c.id);
    });
    table.rows().invalidate().draw(false); // re-render checkboxes to reflect the new selection, without losing sort/page position
    updateBulkBar();
}

function clearSelection() {
    selectedIds.clear();
    table.rows().invalidate().draw(false);
    updateBulkBar();
}

function updateBulkBar() {
    const bar = document.getElementById('bulkBar');
    bar.style.display = selectedIds.size ? 'block' : 'none';
    document.getElementById('selectedCount').textContent = `${selectedIds.size} selected`;

    // Which bulk action makes sense depends entirely on which tab you're
    // looking at — Raw rows haven't been judged yet (qualify/reject),
    // Qualified rows are ready to push, Rejected rows might deserve a
    // second look (reset to raw). No point offering "Push to Flozy" on
    // a tab full of unqualified rows, or "Qualify" on already-pushed ones.
    const buttons = document.getElementById('bulkActionButtons');
    if (currentTab === 'raw') {
        buttons.innerHTML = `
            <button onclick="bulkRunScoring()">🤖 Score Selected</button>
            <button class="ghost" onclick="bulkGetCommentInsight()">💬 Get Comment Insight</button>
            <button onclick="bulkSetStatus('qualified')">✅ Qualify Selected</button>
            <button class="ghost" onclick="bulkSetStatus('rejected')">❌ Reject Selected</button>`;
    } else if (currentTab === 'qualified') {
        buttons.innerHTML = `
            <button style="background:#5B7BFF;" onclick="bulkPushToFlozy()">🚀 Push Selected to Flozy</button>
            <button class="ghost" onclick="bulkRunScoring()">🤖 Score Selected</button>
            <button class="ghost" onclick="bulkGetCommentInsight()">💬 Get Comment Insight</button>
            <button class="ghost" onclick="bulkSetStatus('raw')">↩️ Reset Selected to Raw</button>`;
    } else if (currentTab === 'rejected') {
        buttons.innerHTML = `<button class="ghost" onclick="bulkSetStatus('raw')">↩️ Reset Selected to Raw</button>`;
    } else if (currentTab === 'all') {
        // Mixed statuses on this tab — Score Selected and Get Comment
        // Insight are both still safe to offer regardless of status
        // (re-running either just overwrites that channel's own record,
        // no harm), so those are the only bulk buttons shown here.
        buttons.innerHTML = `
            <button onclick="bulkRunScoring()">🤖 Score Selected</button>
            <button class="ghost" onclick="bulkGetCommentInsight()">💬 Get Comment Insight</button>`;
    } else {
        buttons.innerHTML = ''; // 'pushed' — nothing left to do
    }
}

/**
 * The whole point of this button: sort by whatever matters (subscribers,
 * score, whatever), shift-click a range you actually want the AI to
 * look at, and score ONLY that — instead of an automatic sweep of every
 * 'raw' channel burning a Gemini call on ones you'd have skipped anyway.
 */
function bulkRunScoring() {
    const ids = [...selectedIds];
    if (!ids.length) return;
    document.getElementById('jobStatus').textContent = `Scoring ${ids.length} selected channel(s) — paced to avoid the shared Gemini rate limit…`;
    fetch('../api/youtube_run_scoring.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel_ids: ids })
    })
        .then(r => r.json())
        .then(res => {
            document.getElementById('jobStatus').textContent = '';
            if (!res.success) { showToast(res.error || 'Scoring failed.', true); return; }
            let msg = `Scored ${res.scored} of ${ids.length} selected (${res.qualified} qualified, ${res.rejected} rejected, ${res.needs_review} needs review).`;
            if (res.stopped_early) msg += ' ' + res.stop_reason;
            showToast(msg);
            clearSelection();
            loadRounds(currentRoundId);
            loadApifyBudget(); // this action spends Apify credits
        })
        .catch(err => { document.getElementById('jobStatus').textContent = ''; showToast('Scoring failed: ' + err, true); });
}

/**
 * Comment Insight — additive only, never touches the main score. Reuses
 * whichever videos scoring already sampled (channel.sample_video_urls),
 * so a channel needs to have been scored at least once first; channels
 * without a video sample yet are silently skipped and counted in
 * skipped_no_videos rather than erroring.
 */
function bulkGetCommentInsight() {
    const ids = [...selectedIds];
    if (!ids.length) return;
    document.getElementById('jobStatus').textContent = `Analyzing comments for ${ids.length} selected channel(s) — paced to avoid the shared Gemini rate limit…`;
    fetch('../api/youtube_run_comment_insight.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel_ids: ids })
    })
        .then(r => r.json())
        .then(res => {
            document.getElementById('jobStatus').textContent = '';
            if (!res.success) { showToast(res.error || 'Comment Insight failed.', true); return; }
            let msg = `Analyzed ${res.analyzed} of ${ids.length} selected.`;
            if (res.skipped_no_videos) msg += ` ${res.skipped_no_videos} skipped (not scored yet — run scoring first).`;
            if (res.skipped_no_comments) msg += ` ${res.skipped_no_comments} had no comments returned.`;
            if (res.stopped_early) msg += ' ' + res.stop_reason;
            showToast(msg);
            clearSelection();
            loadRounds(currentRoundId);
            loadApifyBudget(); // this action spends Apify credits
        })
        .catch(err => { document.getElementById('jobStatus').textContent = ''; showToast('Comment Insight failed: ' + err, true); });
}

function bulkSetStatus(status) {
    const ids = [...selectedIds];
    fetch('../api/youtube_set_channel_status.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel_ids: ids, status })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.error || 'Bulk update failed.', true); return; }
            showToast(`Updated ${res.updated} channel(s).`);
            clearSelection();
            loadRounds(currentRoundId);
        })
        .catch(err => showToast('Bulk update failed: ' + err, true));
}

function bulkPushToFlozy() {
    const ids = [...selectedIds];
    fetch('../api/youtube_push_flozy.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'selected', channel_ids: ids })
    })
        .then(r => r.json())
        .then(res => {
            showToast(`Pushed ${res.pushed} of ${res.total_attempted}.${res.failed.length ? ' Some failed — see console.' : ''}`);
            if (res.failed.length) console.warn(res.failed);
            if (res.cross_platform_warnings && res.cross_platform_warnings.length) {
                showToast(`⚠️ ${res.cross_platform_warnings.length} email(s) already linked to a pushed Instagram lead — see console.`, true);
                console.warn(res.cross_platform_warnings);
            }
            clearSelection();
            loadRounds(currentRoundId);
        })
        .catch(err => showToast('Bulk push failed: ' + err, true));
}

/**
 * ============================================================
 * THE TWO BACKGROUND JOBS — discovery (Apify) and scoring (Gemini)
 * ============================================================
 */
function runDiscovery() {
    if (!currentRoundId) { showToast('Select or create a round first.', true); return; }
    document.getElementById('jobStatus').textContent = 'Running discovery — this can take a while depending on keyword count…';
    fetch('../api/youtube_run_discovery.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ round_id: currentRoundId })
    })
        .then(r => r.json())
        .then(res => {
            document.getElementById('jobStatus').textContent = '';
            if (!res.success) { showToast(res.error || 'Discovery failed.', true); return; }
            showToast(`Discovery done — ${res.saved} channel(s) saved (${res.below_subscriber_floor} below subscriber floor, ${res.detail_failed} failed).`);
            loadRounds(currentRoundId);
            loadApifyBudget(); // this action spends Apify credits
        })
        .catch(err => { document.getElementById('jobStatus').textContent = ''; showToast('Discovery failed: ' + err, true); });
}

function runScoring() {
    if (!currentRoundId) { showToast('Select or create a round first.', true); return; }
    document.getElementById('jobStatus').textContent = 'Scoring in progress — paced to avoid the shared Gemini rate limit, this takes a bit…';
    fetch('../api/youtube_run_scoring.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ round_id: currentRoundId })
    })
        .then(r => r.json())
        .then(res => {
            document.getElementById('jobStatus').textContent = '';
            if (!res.success) { showToast(res.error || 'Scoring failed.', true); return; }
            let msg = `Scored ${res.scored} (${res.qualified} qualified, ${res.rejected} rejected, ${res.needs_review} needs review).`;
            if (res.stopped_early) msg += ' ' + res.stop_reason;
            else if (res.note) msg += ' ' + res.note;
            showToast(msg);
            loadRounds(currentRoundId);
            loadApifyBudget(); // this action spends Apify credits
        })
        .catch(err => { document.getElementById('jobStatus').textContent = ''; showToast('Scoring failed: ' + err, true); });
}

/**
 * Shared Apify key pool with Instagram — checking it here means never
 * having to tab over just to see the number. Read-only, no sweep logic
 * (that's Instagram-specific and lives on its own dashboard).
 */
function loadApifyBudget() {
    fetch('../api/apify_budget.php')
        .then(r => r.json())
        .then(res => {
            const el = document.getElementById('apifyBudgetDisplay');
            el.textContent = res.remaining_budget !== null ? `$${res.remaining_budget.toFixed(2)}` : 'unknown (check Apify console)';
        })
        .catch(() => { document.getElementById('apifyBudgetDisplay').textContent = 'unavailable'; });
}

/**
 * ============================================================
 * STARTUP
 * ============================================================
 */
initTable();
loadRounds();
loadApifyBudget();
</script>
</body>
</html>
