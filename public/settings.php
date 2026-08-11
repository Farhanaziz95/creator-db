<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings — Creator Database</title>
<style>
    :root {
        --bg: #0f1115; --card: #171a21; --border: #262a33; --text: #e8e9ec;
        --muted: #8b8f9a; --accent2: #60a5fa; --danger: #f87171;
    }
    * { box-sizing: border-box; }
    body { background: var(--bg); color: var(--text); font-family: -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; padding: 24px; }
    h1 { font-size: 20px; margin: 0 0 6px; }
    a.back { color: var(--muted); font-size: 13px; text-decoration: none; }
    .panel { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 18px; margin: 20px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid var(--border); font-size: 13px; }
    th { color: var(--muted); font-weight: 600; }
    input, select, textarea {
        background: #0f1115; border: 1px solid var(--border); color: var(--text);
        padding: 6px 8px; border-radius: 6px; font-size: 13px; width: 100%;
    }
    button { background: var(--accent2); border: none; color: #0f1115; font-weight: 600; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; }
    button.danger { background: var(--danger); }
    button.small { padding: 4px 10px; font-size: 12px; }
    .add-row { display: grid; grid-template-columns: 2fr 3fr 1fr 1fr 1fr auto; gap: 8px; margin-top: 14px; align-items: end; }
    .add-row label { font-size: 11px; color: var(--muted); display:block; margin-bottom: 3px; }
    .toggle { cursor: pointer; }
</style>
</head>
<body>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function notifyInfo(message) {
    console.log('[INFO]', message);
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: message, showConfirmButton: false, timer: 3000, timerProgressBar: true });
}
function notifyWarning(message, details) {
    console.warn('[WARNING]', message, details || '');
    Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: message, showConfirmButton: true, confirmButtonText: 'OK' });
}
function notifyError(message, details) {
    console.error('[ERROR]', message, details || '');
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: message, showConfirmButton: true, confirmButtonText: 'OK' });
}
function confirmAction(title, text, confirmButtonText) {
    return Swal.fire({
        title: title, text: text, icon: 'warning',
        showCancelButton: true, confirmButtonText: confirmButtonText || 'Yes, do it',
        confirmButtonColor: '#f87171', cancelButtonText: 'Cancel',
        background: '#171a21', color: '#e8e9ec',
    }).then(result => result.isConfirmed);
}
</script>

<script>
function loadBrandVoice() {
    fetch('../api/brand_voice.php')
        .then(r => r.json())
        .then(res => { document.getElementById('brandVoiceText').value = res.voice_text; });
}
function saveBrandVoice() {
    const text = document.getElementById('brandVoiceText').value;
    fetch('../api/brand_voice.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ voice_text: text })
    }).then(() => {
        document.getElementById('brandVoiceStatus').textContent = 'Saved — applies to every future generation.';
        notifyInfo('Brand voice saved.');
    });
}

function loadPromptTemplates() {
    fetch('../api/prompt_templates.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('promptTemplatesList').innerHTML = res.data.map(t => `
                <details style="margin-bottom:10px; background:#0f1115; border:1px solid var(--border); border-radius:8px; padding:10px 14px;">
                    <summary style="cursor:pointer; font-size:13px; font-weight:600;">${t.label}
                        <span style="font-weight:400; color:var(--muted); font-size:11px;"> — updated ${t.updated_at}</span>
                    </summary>
                    <p style="font-size:11px; color:var(--muted); margin:10px 0 6px;">${t.description || ''}</p>
                    <p style="font-size:11px; color:var(--accent); margin:0 0 8px;">Placeholders: ${t.available_placeholders || 'none'}</p>
                    <textarea id="promptText_${t.id}" style="width:100%; height:180px; background:#171a21; border:1px solid var(--border); color:var(--text); padding:10px; border-radius:6px; font-size:12px; font-family:monospace;">${t.template_text}</textarea>
                    <button onclick="savePromptTemplate(${t.id})" style="margin-top:8px;">Save</button>
                    <span id="promptStatus_${t.id}" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>
                </details>
            `).join('');
        });
}
function savePromptTemplate(id) {
    const text = document.getElementById(`promptText_${id}`).value;
    fetch('../api/prompt_templates.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, template_text: text })
    }).then(() => {
        document.getElementById(`promptStatus_${id}`).textContent = 'Saved.';
        notifyInfo('Prompt template saved.');
        loadPromptTemplates();
    });
}

loadBrandVoice();
loadPromptTemplates();
</script>

<a class="back" href="index.php">← Back to Dashboard</a>
<h1>⚙️ Settings</h1>

<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Brand Voice / Style</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        One shared block, automatically prepended to EVERY AI-generated prompt (hooks, follow-ups,
        verification). Change tone/voice once here instead of editing 8 separate prompt templates.
        Leave blank to skip — nothing gets added if empty.
    </p>
    <textarea id="brandVoiceText" placeholder="e.g. Casual, warm, no corporate-speak, short sentences, never use exclamation points..." style="width:100%; height:80px; background:#0f1115; border:1px solid var(--border); color:var(--text); padding:10px; border-radius:6px; font-size:13px;"></textarea>
    <button onclick="saveBrandVoice()" style="margin-top:8px;">Save Brand Voice</button>
    <span id="brandVoiceStatus" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>
</div>

<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Prompt Templates</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Every AI prompt this app sends — wording is fully editable here, no code changes needed.
        Keep the <code>{placeholder}</code> tokens shown under each one — those get filled in
        automatically with the real data (transcripts, gameplan text, etc.) at generation time.
        Changing everything else (tone, instructions, structure) is fair game.
    </p>
    <div id="promptTemplatesList"></div>
</div>

<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Default Task List (Flozy Gameplan)</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 12px;">
        Every ACTIVE task below gets automatically attached to a lead whenever you push a profile to Flozy.
        Reorder with the Sort # column, toggle Active on/off, or edit/delete — no code changes ever needed.
    </p>
    <table id="templatesTable">
        <thead>
            <tr>
                <th>Sort</th><th>Title</th><th>Description</th><th>Priority</th><th>Due (days after push)</th><th>Active</th><th>Actions</th>
            </tr>
        </thead>
        <tbody id="templatesBody"></tbody>
    </table>

    <div class="add-row">
        <div><label>Title</label><input type="text" id="newTitle" placeholder="e.g. Send Outreach DM"></div>
        <div><label>Description</label><input type="text" id="newDesc" placeholder="optional"></div>
        <div><label>Priority</label>
            <select id="newPriority"><option value="1">Low</option><option value="2" selected>Medium</option><option value="3">High</option></select>
        </div>
        <div><label>Due (days)</label><input type="number" id="newDue" placeholder="blank = none"></div>
        <div><label>Sort #</label><input type="number" id="newSort" value="99"></div>
        <div><button onclick="addTemplate()">Add Task</button></div>
    </div>
</div>

<script>
function loadTemplates() {
    fetch('../api/task_templates.php')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('templatesBody');
            tbody.innerHTML = '';
            res.data.forEach(t => {
                const priorityLabel = { 1: 'Low', 2: 'Medium', 3: 'High' }[t.priority] || 'Medium';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="width:60px;"><input type="number" value="${t.sort_order}" style="width:60px;" onchange="updateTemplate(${t.id}, {sort_order: this.value})"></td>
                    <td><input type="text" value="${t.title.replace(/"/g,'&quot;')}" onchange="updateTemplate(${t.id}, {title: this.value})"></td>
                    <td><input type="text" value="${(t.description||'').replace(/"/g,'&quot;')}" onchange="updateTemplate(${t.id}, {description: this.value})"></td>
                    <td style="width:100px;">
                        <select onchange="updateTemplate(${t.id}, {priority: this.value})">
                            <option value="1" ${t.priority==1?'selected':''}>Low</option>
                            <option value="2" ${t.priority==2?'selected':''}>Medium</option>
                            <option value="3" ${t.priority==3?'selected':''}>High</option>
                        </select>
                    </td>
                    <td style="width:120px;"><input type="number" value="${t.due_offset_days ?? ''}" placeholder="none" onchange="updateTemplate(${t.id}, {due_offset_days: this.value})"></td>
                    <td style="width:60px; text-align:center;"><input type="checkbox" class="toggle" ${t.is_active==1?'checked':''} onchange="updateTemplate(${t.id}, {is_active: this.checked ? 1 : 0})"></td>
                    <td style="width:80px;"><button class="small danger" onclick="deleteTemplate(${t.id})">Delete</button></td>
                `;
                tbody.appendChild(tr);
            });
        });
}

function updateTemplate(id, changes) {
    // Merge changes with current row values pulled fresh so partial updates don't clobber other fields
    fetch('../api/task_templates.php')
        .then(r => r.json())
        .then(res => {
            const current = res.data.find(t => t.id === id);
            const merged = Object.assign({}, current, changes, { id });
            fetch('../api/task_templates.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(merged)
            }).then(() => loadTemplates());
        });
}

function addTemplate() {
    const title = document.getElementById('newTitle').value.trim();
    if (!title) { notifyWarning('Title is required.'); return; }

    fetch('../api/task_templates.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            title: title,
            description: document.getElementById('newDesc').value,
            priority: document.getElementById('newPriority').value,
            due_offset_days: document.getElementById('newDue').value,
            sort_order: document.getElementById('newSort').value,
        })
    }).then(() => {
        document.getElementById('newTitle').value = '';
        document.getElementById('newDesc').value = '';
        document.getElementById('newDue').value = '';
        loadTemplates();
    });
}

async function deleteTemplate(id) {
    const ok = await confirmAction('Delete this task?', 'Only affects future pushes to Flozy, not leads already created.', 'Delete it');
    if (!ok) return;
    fetch('../api/task_templates.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(() => { notifyInfo('Task deleted.'); loadTemplates(); });
}

loadTemplates();
</script><div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Database Backup</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Downloads a full .sql backup of everything right now. For automatic nightly
        backups instead, point Windows Task Scheduler at <code>jobs/backup_db.bat</code>
        (keeps the last 30 days, deletes older ones automatically).
    </p>
    <button onclick="window.location.href='../api/backup.php'">Download Backup Now</button>
</div>

<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Keyword Rules (Niche Auto-Detection)</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Any bio/name containing a keyword below gets auto-assigned that niche at import
        time — before AI classification even runs. Edit freely, no code changes needed.
    </p>
    <input type="text" id="keywordSearch" placeholder="Search niche or keyword…" oninput="filterKeywordTable()" style="width:100%; margin-bottom:10px;">
    <table id="keywordTable" style="margin-bottom:14px;">
        <thead><tr><th>Niche</th><th>Keyword</th><th>Actions</th></tr></thead>
        <tbody id="keywordBody"></tbody>
    </table>
    <div class="add-row" style="grid-template-columns: 2fr 2fr auto;">
        <div><label>Niche Name</label><input type="text" id="kwNiche" placeholder="e.g. Fitness"></div>
        <div><label>Keyword</label><input type="text" id="kwKeyword" placeholder="e.g. personal trainer"></div>
        <div><button onclick="addKeywordRule()">Add Rule</button></div>
    </div>
</div>

<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Niche Merge Tool</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Useful once the AI creates near-duplicates (e.g. "Parenting" vs "Parenting & Family").
        Merges all profiles from one niche into another, then deletes the leftover one.
    </p>
    <div class="add-row" style="grid-template-columns: 2fr 2fr auto;">
        <div><label>Merge FROM (deleted after)</label><select id="mergeFrom"></select></div>
        <div><label>Merge INTO (kept)</label><select id="mergeInto"></select></div>
        <div><button class="danger" onclick="mergeNiches()">Merge</button></div>
    </div>
    <div id="mergeStatus" style="margin-top:10px; font-size:12px; color:var(--muted);"></div>
</div>

<script>
function filterKeywordTable() {
    const q = document.getElementById('keywordSearch').value.trim().toLowerCase();
    document.querySelectorAll('#keywordBody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function loadKeywordRules() {
    fetch('../api/keyword_rules_api.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('keywordBody').innerHTML = res.data.map(k => `
                <tr>
                    <td>${k.niche_name}</td>
                    <td>${k.keyword}</td>
                    <td><button class="small danger" onclick="deleteKeywordRule(${k.id})">Delete</button></td>
                </tr>
            `).join('');
            filterKeywordTable();
        });
}
function addKeywordRule() {
    const niche = document.getElementById('kwNiche').value.trim();
    const keyword = document.getElementById('kwKeyword').value.trim();
    if (!niche || !keyword) { notifyWarning('Both fields are required.'); return; }
    fetch('../api/keyword_rules_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ niche_name: niche, keyword: keyword })
    }).then(() => {
        document.getElementById('kwNiche').value = '';
        document.getElementById('kwKeyword').value = '';
        loadKeywordRules();
    });
}
function deleteKeywordRule(id) {
    fetch('../api/keyword_rules_api.php', {
        method: 'DELETE', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(() => loadKeywordRules());
}

function loadNicheSelects() {
    fetch('../api/niches.php')
        .then(r => r.json())
        .then(res => {
            const options = res.data.map(n => `<option value="${n.id}">${n.name} (${n.profile_count})</option>`).join('');
            document.getElementById('mergeFrom').innerHTML = options;
            document.getElementById('mergeInto').innerHTML = options;
        });
}
async function mergeNiches() {
    const fromId = document.getElementById('mergeFrom').value;
    const intoId = document.getElementById('mergeInto').value;
    const status = document.getElementById('mergeStatus');
    if (fromId === intoId) { notifyWarning('Pick two different niches.'); return; }

    const ok = await confirmAction('Merge these niches?', 'The FROM niche will be deleted after all its profiles move to the INTO niche.', 'Merge them');
    if (!ok) return;

    fetch('../api/niches.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'merge', from_niche_id: fromId, into_niche_id: intoId })
    })
        .then(r => r.json())
        .then(res => {
            if (res.error) { notifyError('Merge failed.', res.error); return; }
            status.textContent = `Moved ${res.profiles_moved} profile(s). Merge complete.`;
            notifyInfo(`Merged — ${res.profiles_moved} profile(s) moved.`);
            loadNicheSelects();
        })
        .catch(err => notifyError('Merge failed.', err));
}

loadKeywordRules();
loadNicheSelects();
</script>
<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Composite Quality Score</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Controls how much each signal counts toward the Score column on the dashboard.
        All start equal (1.00) — raise a weight to make that signal matter more, lower it
        to matter less, or switch it off to remove it from the formula entirely. Changes
        apply instantly, no re-import needed. Full explanation: dashboard → "❔ How Scoring Works".
    </p>
    <table>
        <thead><tr><th>Signal</th><th style="width:120px;">Weight</th><th style="width:80px;">Active</th></tr></thead>
        <tbody id="scoreWeightsBody"></tbody>
    </table>
    <button onclick="recomputeAllScores()" style="margin-top:12px;">Recompute All Scores</button>
    <span id="recomputeStatus" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>
</div>

<script>
function recomputeAllScores() {
    document.getElementById('recomputeStatus').textContent = 'Recomputing…';
    fetch('../api/recompute_scores.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('recomputeStatus').textContent = `Done — recomputed ${res.recomputed} profile(s).`;
            notifyInfo(`Recomputed ${res.recomputed} score(s).`);
        });
}
function loadScoreWeightsSettings() {
    fetch('../api/score_weights.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('scoreWeightsBody').innerHTML = res.data.map(w => `
                <tr>
                    <td>${w.label}</td>
                    <td><input type="number" value="${w.weight}" min="0" max="5" step="0.1" style="width:80px;" onchange="updateScoreWeight(${w.id}, this.value, ${w.is_active})"></td>
                    <td style="text-align:center;"><input type="checkbox" class="toggle" ${w.is_active == 1 ? 'checked' : ''} onchange="updateScoreWeight(${w.id}, null, this.checked ? 1 : 0)"></td>
                </tr>
            `).join('');
        });
}
function updateScoreWeight(id, weight, isActive) {
    fetch('../api/score_weights.php')
        .then(r => r.json())
        .then(res => {
            const current = res.data.find(w => w.id === id);
            fetch('../api/score_weights.php', {
                method: 'PUT', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: id,
                    weight: weight !== null ? weight : current.weight,
                    is_active: isActive,
                })
            }).then(() => loadScoreWeightsSettings());
        });
}
loadScoreWeightsSettings();
</script>
<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Apify Key Rotation Pool</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Keys rotate round-robin by least-recently-used — spreads traffic across every
        active key instead of hammering one, which looks more like a human juggling
        several accounts than one app grinding on a single key. Budget is still checked
        first and always wins: a key gets skipped if it's genuinely low, regardless of
        whose turn it is. Sort # only breaks ties between equally-due keys.
    </p>
    <table>
        <thead><tr><th>Label</th><th>Key (masked)</th><th>Sort #</th><th>Active</th><th>Last Used</th><th>Actions</th></tr></thead>
        <tbody id="apifyKeysBody"></tbody>
    </table>
    <div class="add-row" style="grid-template-columns: 1fr 2fr 1fr auto;">
        <div><label>Label</label><input type="text" id="newKeyLabel" placeholder="e.g. Ali's account"></div>
        <div><label>API Key</label><input type="text" id="newKeyValue" placeholder="apify_api_..."></div>
        <div><label>Sort #</label><input type="number" id="newKeySort" value="99"></div>
        <div><button onclick="addApifyKey()">Add Key</button></div>
    </div>
</div>

<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Apify Actor Cost Rates</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Used to estimate cost before each run. Verify these against each actor's Pricing
        tab in Apify Console occasionally — rates do change.
    </p>
    <table>
        <thead><tr><th>Actor</th><th>$ per 1000</th><th>Notes</th></tr></thead>
        <tbody id="actorCostsBody"></tbody>
    </table>
</div>

<script>
function loadApifyKeys() {
    fetch('../api/apify_keys_api.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('apifyKeysBody').innerHTML = res.data.map(k => `
                <tr>
                    <td>${k.label}</td>
                    <td style="font-family:monospace;">${k.masked_key}</td>
                    <td><input type="number" value="${k.sort_order}" style="width:60px;" onchange="updateApifyKey(${k.id}, this.value, ${k.is_active})"></td>
                    <td style="text-align:center;"><input type="checkbox" ${k.is_active == 1 ? 'checked' : ''} onchange="updateApifyKey(${k.id}, ${k.sort_order}, this.checked ? 1 : 0)"></td>
                    <td style="font-size:12px; color:var(--muted);">${k.last_used_at || 'never'}</td>
                    <td><button class="small danger" onclick="deleteApifyKey(${k.id})">Delete</button></td>
                </tr>
            `).join('');
        });
}
function addApifyKey() {
    const label = document.getElementById('newKeyLabel').value.trim();
    const key = document.getElementById('newKeyValue').value.trim();
    if (!label || !key) { notifyWarning('Both fields required.'); return; }
    fetch('../api/apify_keys_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ label, api_key: key, sort_order: document.getElementById('newKeySort').value })
    }).then(() => {
        document.getElementById('newKeyLabel').value = '';
        document.getElementById('newKeyValue').value = '';
        loadApifyKeys();
    });
}
function updateApifyKey(id, sortOrder, isActive) {
    fetch('../api/apify_keys_api.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, sort_order: sortOrder, is_active: isActive })
    }).then(() => loadApifyKeys());
}
async function deleteApifyKey(id) {
    const ok = await confirmAction('Remove this key?', 'It will be removed from the rotation pool.', 'Remove it');
    if (!ok) return;
    fetch('../api/apify_keys_api.php', {
        method: 'DELETE', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(() => { notifyInfo('Key removed.'); loadApifyKeys(); });
}

function loadActorCosts() {
    fetch('../api/actor_costs_api.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('actorCostsBody').innerHTML = res.data.map(c => `
                <tr>
                    <td>${c.label}</td>
                    <td><input type="number" step="0.01" value="${c.cost_per_1000}" style="width:90px;" onchange="updateActorCost(${c.id}, this.value, '${(c.notes||'').replace(/'/g,"&apos;")}')"></td>
                    <td style="font-size:11px; color:var(--muted);">${c.notes || ''}</td>
                </tr>
            `).join('');
        });
}
function updateActorCost(id, cost, notes) {
    fetch('../api/actor_costs_api.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, cost_per_1000: cost, notes })
    }).then(() => loadActorCosts());
}

loadApifyKeys();
loadActorCosts();
</script>
<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Message-Angle Categories</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        A layer above your niches — each category has a "message angle" that gets
        injected into every AI-generated hook/follow-up for creators in that category.
        Assign niches to categories in the table below.
    </p>
    <table style="margin-bottom:14px;">
        <thead><tr><th>Category</th><th>Message Angle</th><th>Actions</th></tr></thead>
        <tbody id="categoriesBody"></tbody>
    </table>
    <div class="add-row" style="grid-template-columns: 1fr 2fr auto;">
        <div><label>Category Name</label><input type="text" id="newCategoryName" placeholder="e.g. Experts"></div>
        <div><label>Message Angle</label><input type="text" id="newCategoryAngle" placeholder="What this group actually cares about..."></div>
        <div><button onclick="addCategory()">Add Category</button></div>
    </div>

    <h3 style="font-size:12px; color:var(--muted); text-transform:uppercase; margin:20px 0 10px;">Assign Niches to Categories</h3>
    <input type="text" id="nicheAssignSearch" placeholder="Search niches…" oninput="filterNicheAssignTable()" style="width:100%; margin-bottom:10px;">
    <table>
        <thead><tr><th>Niche</th><th># Profiles</th><th>Category</th></tr></thead>
        <tbody id="nicheCategoryAssignBody"></tbody>
    </table>
</div>

<script>
function loadCategories() {
    fetch('../api/niche_categories_api.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('categoriesBody').innerHTML = res.data.map(c => `
                <tr>
                    <td><input type="text" value="${c.name.replace(/"/g,'&quot;')}" onchange="updateCategory(${c.id}, this.value, null)"></td>
                    <td><input type="text" value="${(c.message_angle||'').replace(/"/g,'&quot;')}" onchange="updateCategory(${c.id}, null, this.value)"></td>
                    <td><button class="small danger" onclick="deleteCategory(${c.id})">Delete</button></td>
                </tr>
            `).join('');
            loadNicheCategoryAssignments(res.data);
        });
}
function addCategory() {
    const name = document.getElementById('newCategoryName').value.trim();
    const angle = document.getElementById('newCategoryAngle').value.trim();
    if (!name) { notifyWarning('Category name is required.'); return; }
    fetch('../api/niche_categories_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, message_angle: angle })
    }).then(() => {
        document.getElementById('newCategoryName').value = '';
        document.getElementById('newCategoryAngle').value = '';
        loadCategories();
    });
}
function updateCategory(id, name, angle) {
    fetch('../api/niche_categories_api.php')
        .then(r => r.json())
        .then(res => {
            const current = res.data.find(c => c.id === id);
            fetch('../api/niche_categories_api.php', {
                method: 'PUT', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, name: name !== null ? name : current.name, message_angle: angle !== null ? angle : current.message_angle })
            }).then(() => loadCategories());
        });
}
async function deleteCategory(id) {
    const ok = await confirmAction('Delete this category?', 'Niches assigned to it will become uncategorized.', 'Delete it');
    if (!ok) return;
    fetch('../api/niche_categories_api.php', {
        method: 'DELETE', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(() => { notifyInfo('Category deleted.'); loadCategories(); });
}

function filterNicheAssignTable() {
    const q = document.getElementById('nicheAssignSearch').value.trim().toLowerCase();
    document.querySelectorAll('#nicheCategoryAssignBody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function loadNicheCategoryAssignments(categories) {
    fetch('../api/niches.php')
        .then(r => r.json())
        .then(res => {
            const options = categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            document.getElementById('nicheCategoryAssignBody').innerHTML = res.data.map(n => `
                <tr>
                    <td>${n.name}</td>
                    <td>${n.profile_count}</td>
                    <td>
                        <select onchange="assignNicheCategory(${n.id}, this.value)">
                            <option value="">— none —</option>
                            ${options}
                        </select>
                    </td>
                </tr>
            `).join('');
            // Set current selections
            res.data.forEach(n => {
                if (n.category_id) {
                    const row = [...document.querySelectorAll('#nicheCategoryAssignBody tr')].find(tr => tr.children[0].textContent === n.name);
                    if (row) row.querySelector('select').value = n.category_id;
                }
            });
            filterNicheAssignTable();
        });
}
function assignNicheCategory(nicheId, categoryId) {
    fetch('../api/niches.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'set_category', niche_id: nicheId, category_id: categoryId })
    }).then(() => notifyInfo('Category assigned.'));
}

loadCategories();
</script>
<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">Budget Sweep — Excluded Stages</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 10px;">
        Flozy tags stages like "Ghosted" and "Not A Right Fit" as ACTIVE — same as
        genuinely progressing leads — so the sweep needs an explicit exclude list
        rather than relying on the tag alone. Check any stage that should never get
        swept. Pulled live from your real Flozy pipeline.
    </p>
    <div id="sweepStagesBody"></div>
</div>

<script>
function loadSweepStages() {
    fetch('../api/sweep_excluded_stages.php')
        .then(r => r.json())
        .then(res => {
            const wrap = document.getElementById('sweepStagesBody');
            if (!res.flozy_reachable) {
                wrap.innerHTML = '<p style="color:var(--danger); font-size:12px;">Could not reach Flozy — check config/flozy.php has a valid key.</p>';
                return;
            }
            wrap.innerHTML = res.data.map(s => `
                <label style="display:flex; width:100%; align-items:center; gap:8px; padding:6px 0; font-size:13px; text-align:left;">
                    <input type="checkbox" ${s.excluded ? 'checked' : ''} onchange="toggleSweepStage('${s.name.replace(/'/g,"\\'")}', this.checked)">
                    ${s.name} <span style="color:var(--muted); font-size:11px;">(${s.tag || 'no tag'})</span>
                </label>
            `).join('');
        });
}
function toggleSweepStage(stageName, excluded) {
    fetch('../api/sweep_excluded_stages.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stage_name: stageName, excluded })
    }).then(() => notifyInfo(excluded ? `"${stageName}" excluded from sweep.` : `"${stageName}" included in sweep.`));
}
loadSweepStages();
</script>

</body>
</html>
