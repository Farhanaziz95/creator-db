<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Content Studio</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    :root {
        --bg: #0f1115; --card: #171a21; --border: #262a33; --text: #e8e9ec;
        --muted: #8b8f9a; --accent: #6ee7b7; --accent2: #60a5fa; --danger: #f87171;
    }
    * { box-sizing: border-box; }
    html, body { width: 100%; margin: 0; }
    body { background: var(--bg); color: var(--text); font-family: -apple-system, Segoe UI, Roboto, sans-serif; padding: 24px; }
    h1 { font-size: 20px; margin: 0 0 6px; }
    a.back { color: var(--muted); font-size: 13px; text-decoration: none; }
    .panel { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 24px; margin: 20px 0; }
    .big-btn {
        background: #0f1115; border: 2px solid var(--border); color: var(--text);
        padding: 24px; border-radius: 12px; font-size: 16px; font-weight: 600;
        cursor: pointer; width: 100%; text-align: center; transition: all 0.15s;
    }
    .big-btn:hover { border-color: var(--accent2); background: #1a1e27; }
    .big-btn.selected { border-color: var(--accent); background: rgba(110,231,183,0.1); }
    .big-btn-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .step-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
    input, textarea, select {
        width: 100%; background: #0f1115; border: 1px solid var(--border); color: var(--text);
        padding: 10px 12px; border-radius: 6px; font-size: 14px; margin-top: 6px;
    }
    textarea { min-height: 90px; }
    label { font-size: 13px; color: var(--muted); display: block; margin-top: 16px; }
    label:first-child { margin-top: 0; }
    button.primary {
        background: var(--accent2); border: none; color: #0f1115; font-weight: 600;
        padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 15px; margin-top: 20px;
    }
    button.ghost { background: transparent; border: 1px solid var(--border); color: var(--text); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; }
    button.small { padding: 5px 12px; font-size: 12px; }
    #finalPromptBox { width: 100%; height: 400px; font-family: monospace; font-size: 12px; white-space: pre-wrap; }
    .hidden { display: none; }
    .history-item { background: #0f1115; border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; cursor: pointer; font-size: 13px; }
    .history-item:hover { border-color: var(--accent2); }
    .history-meta { font-size: 11px; color: var(--muted); }
    details { background: #0f1115; border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; }
    summary { cursor: pointer; font-size: 13px; font-weight: 600; }
    details textarea { font-family: monospace; font-size: 12px; height: 260px; margin-top: 10px; }
</style>
</head>
<body>

<a class="back" href="index.php">← Back to Dashboard</a>
<h1>🎬 Content Studio <span style="font-weight:400; color:var(--muted); font-size:13px;">(separate from lead outreach — for your own posting content)</span></h1>
<p style="font-size:13px; color:var(--muted); margin:6px 0 0;">
    Assembles a structured prompt from your Brand Foundation + Master Prompt + Campaign Rule + today's brief.
    No AI runs here — copy the final prompt into Iman's tool for actual generation.
</p>

<!-- WIZARD -->
<div class="panel">

    <div id="step1">
        <div class="step-label">Step 1 — Content Type</div>
        <div class="big-btn-row">
            <button class="big-btn" data-type="reel" onclick="selectContentType('reel')">🎥<br>Reel</button>
            <button class="big-btn" data-type="story" onclick="selectContentType('story')">📖<br>Story</button>
            <button class="big-btn" data-type="carousel" onclick="selectContentType('carousel')">🎠<br>Carousel</button>
        </div>
    </div>

    <div id="step2" class="hidden" style="margin-top:28px;">
        <div class="step-label">Step 2 — Campaign Stage</div>
        <div class="big-btn-row">
            <button class="big-btn" data-stage="awareness" onclick="selectStage('awareness')">👁<br>Awareness</button>
            <button class="big-btn" data-stage="consideration" onclick="selectStage('consideration')">🤔<br>Consideration</button>
            <button class="big-btn" data-stage="conversion" onclick="selectStage('conversion')">🎯<br>Conversion</button>
        </div>
    </div>

    <div id="step3" class="hidden" style="margin-top:28px;">
        <div class="step-label">Step 3 — Today's Brief</div>
        <label>Theme</label>
        <input type="text" id="briefTheme" placeholder="e.g. The Creator's Hidden Problem">
        <label>Core Idea</label>
        <textarea id="briefCoreIdea" placeholder="What's the actual idea/angle you want to get across?"></textarea>
        <label>Angle</label>
        <textarea id="briefAngle" placeholder="The specific reframe, tension, or hook direction"></textarea>
        <button class="primary" onclick="generatePrompt()">Generate Final Prompt</button>
    </div>

    <div id="step4" class="hidden" style="margin-top:28px;">
        <div class="step-label">Final Prompt — ready to paste into Iman's tool</div>
        <textarea id="finalPromptBox" readonly></textarea>
        <button class="primary" onclick="copyFinalPrompt()">Copy Prompt</button>
        <button class="ghost" onclick="resetWizard()" style="margin-top:20px; margin-left:10px;">Start New</button>
        <span id="copyStatus" style="margin-left:10px; font-size:12px; color:var(--muted);"></span>
    </div>

</div>

<!-- RECENT BRIEFS -->
<div class="panel">
    <div class="step-label">Recent Briefs</div>
    <div id="recentBriefsList"></div>
</div>

<!-- EDIT FOUNDATION & TEMPLATES -->
<div class="panel">
    <h2 style="font-size:14px; color:var(--muted); text-transform:uppercase; margin:0 0 12px;">⚙️ Edit Foundation &amp; Templates</h2>
    <p style="font-size:12px; color:var(--muted); margin:0 0 14px;">
        Keep <code>{color_rules}</code> where you see it in a Master Prompt — that's filled in automatically
        from Color Rules below. Everything else is fair game to edit.
    </p>

    <details>
        <summary>🎨 Color Rules (shared across all 3 Master Prompts)</summary>
        <textarea id="colorRulesText"></textarea>
        <button class="small" onclick="saveColorRules()" style="margin-top:8px;">Save</button>
    </details>

    <div id="mastersEditList"></div>
    <div id="campaignsEditList"></div>
    <div id="structuresEditList"></div>
</div>

<script>
function notifyInfo(message) {
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: message, showConfirmButton: false, timer: 3000, timerProgressBar: true });
}
function notifyError(message, details) {
    console.error('[ERROR]', message, details || '');
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: message, showConfirmButton: true, confirmButtonText: 'OK' });
}

let selectedType = null;
let selectedStage = null;

function selectContentType(type) {
    selectedType = type;
    document.querySelectorAll('#step1 .big-btn').forEach(b => b.classList.toggle('selected', b.dataset.type === type));
    document.getElementById('step2').classList.remove('hidden');
}
function selectStage(stage) {
    selectedStage = stage;
    document.querySelectorAll('#step2 .big-btn').forEach(b => b.classList.toggle('selected', b.dataset.stage === stage));
    document.getElementById('step3').classList.remove('hidden');
}

function generatePrompt() {
    const theme = document.getElementById('briefTheme').value.trim();
    const coreIdea = document.getElementById('briefCoreIdea').value.trim();
    const angle = document.getElementById('briefAngle').value.trim();

    if (!theme || !coreIdea) {
        notifyError('Theme and Core Idea are required.');
        return;
    }

    fetch('../api/content_studio.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content_type: selectedType, campaign_stage: selectedStage, theme, core_idea: coreIdea, angle })
    })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { notifyError('Generation failed.', res.error); return; }
            document.getElementById('finalPromptBox').value = res.final_prompt;
            document.getElementById('step4').classList.remove('hidden');
            document.getElementById('step4').scrollIntoView({ behavior: 'smooth' });
            notifyInfo('Prompt generated and saved to history.');
            loadRecentBriefs();
        })
        .catch(err => notifyError('Generation failed.', err));
}

function copyFinalPrompt() {
    const box = document.getElementById('finalPromptBox');
    box.select();
    navigator.clipboard.writeText(box.value).then(() => {
        document.getElementById('copyStatus').textContent = 'Copied!';
        setTimeout(() => document.getElementById('copyStatus').textContent = '', 2000);
    });
}

function resetWizard() {
    selectedType = null; selectedStage = null;
    document.querySelectorAll('.big-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('briefTheme').value = '';
    document.getElementById('briefCoreIdea').value = '';
    document.getElementById('briefAngle').value = '';
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step3').classList.add('hidden');
    document.getElementById('step4').classList.add('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function loadRecentBriefs() {
    fetch('../api/content_briefs_history.php')
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('recentBriefsList');
            if (!res.data.length) {
                list.innerHTML = '<p style="color:var(--muted); font-size:13px;">Nothing generated yet.</p>';
                return;
            }
            list.innerHTML = res.data.map(b => `
                <div class="history-item" onclick="reuseBrief(${b.id}, '${b.content_type}', '${b.campaign_stage}', '${(b.theme||'').replace(/'/g,"\\'")}', '${(b.core_idea||'').replace(/'/g,"\\'").replace(/\n/g,' ')}', '${(b.angle||'').replace(/'/g,"\\'").replace(/\n/g,' ')}')">
                    <div><b>${b.theme}</b></div>
                    <div class="history-meta">${b.content_type} · ${b.campaign_stage} · ${b.created_at}</div>
                </div>
            `).join('');
        });
}
function reuseBrief(id, type, stage, theme, coreIdea, angle) {
    resetWizard();
    selectContentType(type);
    selectStage(stage);
    document.getElementById('briefTheme').value = theme;
    document.getElementById('briefCoreIdea').value = coreIdea;
    document.getElementById('briefAngle').value = angle;
    document.getElementById('step3').scrollIntoView({ behavior: 'smooth' });
    notifyInfo('Loaded as a starting point — edit and generate.');
}

function loadTemplatesForEditing() {
    fetch('../api/content_templates.php')
        .then(r => r.json())
        .then(res => {
            document.getElementById('colorRulesText').value = res.color_rules;

            document.getElementById('mastersEditList').innerHTML = res.masters.map(m => `
                <details>
                    <summary>📄 ${m.content_type.charAt(0).toUpperCase() + m.content_type.slice(1)} Master Prompt</summary>
                    <textarea id="master_${m.id}">${m.prompt_text}</textarea>
                    <button class="small" onclick="saveTemplate('master', ${m.id}, 'master_${m.id}')" style="margin-top:8px;">Save</button>
                </details>
            `).join('');

            document.getElementById('campaignsEditList').innerHTML = res.campaigns.map(c => `
                <details>
                    <summary>🎯 ${c.content_type} — ${c.campaign_stage}</summary>
                    <textarea id="campaign_${c.id}">${c.rule_text}</textarea>
                    <button class="small" onclick="saveTemplate('campaign', ${c.id}, 'campaign_${c.id}')" style="margin-top:8px;">Save</button>
                </details>
            `).join('');

            document.getElementById('structuresEditList').innerHTML = ['reel', 'story', 'carousel'].map(type => {
                const existing = res.structures.find(s => s.content_type === type);
                const text = existing ? existing.rule_text : '';
                return `
                    <details>
                        <summary>🧩 ${type.charAt(0).toUpperCase() + type.slice(1)} Structure Rule (optional)</summary>
                        <textarea id="structure_${type}" placeholder="Leave blank to skip this section for ${type}s">${text}</textarea>
                        <button class="small" onclick="saveStructure('${type}')" style="margin-top:8px;">Save</button>
                    </details>
                `;
            }).join('');
        });
}
function saveColorRules() {
    fetch('../api/content_templates.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target: 'color_rules', text: document.getElementById('colorRulesText').value })
    }).then(() => notifyInfo('Color Rules saved.'));
}
function saveTemplate(target, id, textareaId) {
    fetch('../api/content_templates.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target, id, text: document.getElementById(textareaId).value })
    }).then(() => notifyInfo('Saved.'));
}
function saveStructure(contentType) {
    fetch('../api/content_templates.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target: 'structure', content_type: contentType, text: document.getElementById(`structure_${contentType}`).value })
    }).then(() => notifyInfo('Saved.'));
}

loadRecentBriefs();
loadTemplatesForEditing();
</script>
</body>
</html>
