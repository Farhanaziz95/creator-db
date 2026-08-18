# Creator Database — Setup

## 1. Install the project
Copy the whole `creator-db` folder into `C:\xampp\htdocs\`.

## 2. Create the database
- Start Apache + MySQL in the XAMPP control panel.
- Open `http://localhost/phpmyadmin`.
- Click **Import** → choose `sql/schema.sql` → Go.
  (This creates the `creator_db` database and all tables automatically.)

## 3. Add your OpenRouter key
- Get a free key at https://openrouter.ai/keys (no credit card needed).
- Open `config/openrouter.php` and replace `YOUR_OPENROUTER_API_KEY_HERE` with your real key.

## 4. Open the dashboard
Visit `http://localhost/creator-db/public/index.php`

From here you can:
- Upload an Apify JSON export directly (Import button)
- Adjust the Min Followers / Min Engagement filters live
- Watch stat cards update (added today/week/month, duplicates, pending AI niche)

## 5. Set up the background niche job (Windows Task Scheduler)
This clears the AI queue automatically every few minutes.

1. Open **Task Scheduler** → Create Basic Task
2. Trigger: **Daily**, recurring every **5 minutes** (set the trigger, then edit it afterward to repeat every 5 min for a duration of 1 day, "indefinitely")
3. Action: **Start a program**
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `C:\xampp\htdocs\creator-db\jobs\process_niche_queue.php`
4. Save. It'll now run quietly in the background, pulling ~10 profiles per run through the free OpenRouter models (keyword-matched profiles never touch this — only the leftovers with no business category and no keyword match).

## Round 34: Accordion, Overdue panel polish, outreach→Contacted, quick archive

Four changes this round, all from the same conversation.

### 1. Accordion — Tasks/History/Results moved from modals into the table
As discussed in Round 33's brainstorm: **Tasks & Reminders, History, and
Results** are no longer modals — they're now an expandable row (▶ button,
second column, left of Username). Clicking it reveals a tabbed panel
inline; **Follow-up stays a modal**, unchanged, since it's a multi-step
flow (pick type → conditionally describe what you're sharing → generate →
copy) that doesn't compress well into a row.

- **Single-open accordion** — expanding one row's panel collapses
  whichever other row was open. No two panels fight for space at once.
- **Lazy-loaded per tab** — opening a row loads only its first tab
  (Tasks on the Flozy tab, History on Active/Future); switching tabs
  fetches on first view, cached after that. Same cost as the old modals,
  just relocated — this was the actual point of the earlier "won't this
  be slower?" question: it isn't, because nothing preloads.
- **Active/Future tabs** get History + Results (no Tasks tab — that needs
  a pushed Flozy lead). **Flozy tab** gets all three. **Archived** gets no
  expand button — nothing to show there.
- `runVerification()` / `rerunAiOnly()` now populate the Results tab
  directly with the fresh response instead of a modal — if that lead's
  row happens to already be expanded, it updates in place; otherwise the
  result is just saved server-side as always, ready whenever you open it.
- **Care taken with reload interaction:** `table.ajax.reload()` recreates
  row DOM nodes, which would otherwise silently detach an open accordion
  mid-view. Actions that touch the SAME open row's data (marking a task
  done, adding a task) now refresh the accordion's own content in place
  instead of forcing a full table reload. A `draw` event listener also
  resets accordion tracking state cleanly if a reload does happen to tear
  down the currently-open row, so nothing is left pointing at a dead
  element.
- All the old modal HTML/JS (`#flozyTasksModal`, `#genHistoryModal`,
  `#resultsModal` and their functions) were fully removed, not just
  hidden — confirmed zero leftover references before shipping.

**Heads up on column indices:** inserting the new expand-toggle column
shifted every column index after it by one. Updated everywhere that
mattered — `api/profiles.php`'s server-side sort-column map, the default
sort order, and the `toggleableColumns` list for the "👁 Columns" menu.
Same caveat as previous column changes: cached DataTables state
(`stateSave`) may reset once on first load after this update.

### 2. Overdue panel — collapsible, count in the header, quick-done button
The panel from Round 33 could only grow — now:
- **Collapsible** — click the header to collapse/expand; state persists
  across reloads (`localStorage`).
- **Count in the heading** — "⚠️ Overdue Tasks (5)" even while collapsed,
  so you always know how many without expanding.
- **Max-height + scroll** (340px) on the list itself, so even fully
  expanded with many items it can't push the rest of the dashboard down
  indefinitely.
- **✅ Done button directly on each item** — the actual "quick button" ask.
  No more needing to open anything first; it hits the same `PUT
  /tasks/{id}` completion endpoint directly from the panel.
- "Open →" still exists alongside it for when you want more context —
  since a lead referenced here could be on any page of a paginated table,
  it switches to the Flozy tab and searches for that username (lands the
  row on page 1) rather than attempting a fragile cross-page auto-expand.

### 3. Outreach toggle now also moves the Opportunity to "Contacted"
You flagged that marking a lead outreached didn't move its stage in
Flozy. Fixed — with one thing still needing you:

- New shared `move_opportunity_to_named_stage()` in
  `includes/flozy_client.php`, reused by both this and the new archive
  action below.
- `api/toggle_outreach.php` now calls it when marking outreached (never
  on undo — same "undo is local-only" reasoning as the task-logging
  feature from Round 29).
- **`config/flozy.php` → `default_contacted_stage_name` is blank on
  purpose.** Unlike "New Lead" and "Not A Right Fit," I've never seen you
  use an exact "Contacted" stage name — guessing it risked shipping
  something that silently does nothing, or worse, fails against the wrong
  stage. Until this is filled in with your real stage name, marking
  outreached still works exactly as before (local flag + Flozy task log)
  — the stage move is simply skipped, not an error.

### 4. Quick Archive from Sent to Flozy → moves Opportunity to "Not A Right Fit"
New **🗄️ Archive (Not a Right Fit)** action (row-level and bulk) on the
Sent to Flozy tab.

- **Not the same as "Remove from Flozy."** This does NOT delete anything
  in Flozy — the Lead and Opportunity stay fully intact there, just moved
  to the "Not A Right Fit" stage (confirmed real stage name, used already
  since Round 22's sweep exclusions).
- Locally: unlinks the `flozy_leads` row (so it drops off the Sent to
  Flozy tab) and sets the profile to archived (so it reappears under
  Archived) — same local effect as "Remove from Flozy" already has,
  minus the destructive Flozy-side delete.
- New `api/archive_flozy_lead.php`, single (`action: 'one'`) and bulk
  (`action: 'selected'`) — bulk paced at ~3 req/sec like the other
  bulk Flozy-writing actions.
- If pushing this profile again later, a fresh Lead/Opportunity gets
  created — the old ones aren't reused, but they're not lost either,
  they're just sitting at "Not A Right Fit" in your real pipeline.

## Round 33: The actual "reminder" — an Overdue panel

Fair pushback on Round 32: a task view you have to remember to open isn't
a reminder, it's a filing cabinet. This closes that gap — plus some
brainstorming on where the app's UI is headed next.

### ⚠️ Overdue panel — new, top of the dashboard
Scans **every** task across **every** Flozy lead in one pass (not
per-lead lookups) and lists anything overdue: creator, task, how many
days overdue, priority — each with an "Open →" button straight into that
lead's Tasks & Reminders modal. Shows a calm "✅ Nothing overdue right
now" when there's nothing to flag, so it's not a permanent red alarm
block when everything's on track.

- `includes/flozy_client.php` — pulled the raw pagination fetch out of
  `api/flozy_lead_tasks.php` into a shared `fetch_all_flozy_tasks()`, so
  both that file (filters to one lead) and the new
  `api/flozy_overdue_tasks.php` (filters to overdue across every lead)
  scan Flozy's task list once each, from one place, instead of
  duplicating the same loop twice.
- `api/flozy_overdue_tasks.php` — new. Maps `lead_id → {profile_id,
  username}` locally so an overdue task can actually be attributed to a
  creator, then filters for `status != 3` (not done) and a `due_date` in
  the past.
- Refreshes automatically on page load, after marking a task done, and
  after adding a new task — plus a manual 🔄 button if you want to check
  without waiting.

### Brainstormed, not built yet: accordion/child-row layout
Discussed switching some of the modal-based views (Tasks & Reminders,
History, Results) to DataTables' native expandable child rows instead of
floating modals, for faster in-place access — Follow-up staying a modal
since it's a multi-step flow (pick type → conditionally describe what
you're sharing → generate → copy), which doesn't compress well into an
inline row. Worth noting: done right, this doesn't have to cost load
time — child rows can lazy-fetch on expand exactly like the modals
already do, so the tradeoff is really about UI shape (inline vs.
floating), not speed. No code changed yet — this is queued as a separate
pass once there's bandwidth for a UI restructuring, not bundled into this
round's task-visibility fix.

## Round 32: Live Tasks & Reminders (part 1 of the "syncing with Flozy" plan)

You asked about closing the gap between working in this app vs working
directly in Flozy, without having to manage two separate systems. Agreed
approach was 3 pieces, one at a time:
1. **Live task view (this round)** — see everything regardless of where
   it was created.
2. Import leads created directly in Flozy that this app doesn't know
   about yet.
3. Create the missing Opportunity for leads pushed before Round 28 added
   auto-creation (a real gap you caught — those leads never got one at
   all, so there's nothing for a sync to backfill; it has to actually
   create one).

### This round: 🔔 Tasks & Reminders
Confirmed against Flozy's real API docs first
(`docs.flozy.com/api-reference/tasks/*`), same discipline as every other
Flozy integration in this project:
- `GET /tasks` has **no `lead_id` filter** — confirmed from the docs, not
  assumed. Only `page`/`limit`/`order`/`search` exist as params. Same
  situation as Opportunities. So listing one lead's tasks means
  paginating through everything and filtering locally — new
  `fetch_tasks_for_lead()` in `api/flozy_lead_tasks.php` does this,
  mirroring the existing `build_lead_opportunity_lookup()` pattern in
  `api/sync_flozy_stage.php`.
- `id`, `title`, `status`, `priority`, `lead_id`, `created_at` all
  confirmed as real fields; status codes match what's already used
  throughout this project (1 todo / 2 in progress / 3 completed / 4 in
  review). `description`/`due_date` aren't shown in the docs' (trimmed)
  example responses even though Create accepts them — displayed
  defensively (`?? null`) rather than assumed present.

**New: 🔔 Tasks & Reminders**, in the ⋮ menu on the Sent to Flozy tab —
opens a modal showing every task for that lead, tagged **📱 App** or
**🏢 Flozy** depending on where it was actually created (origin is
determined by checking against the local `flozy_lead_tasks` table, not
guessed). Overdue tasks get a red border and an ⚠️ flag. You can:
- **Add a task** right there (title, optional due date/priority/notes) —
  goes straight to Flozy via the already-confirmed `POST /tasks`, and
  gets recorded locally too so it's correctly tagged "App" next time.
- **Mark a task done** via the already-confirmed `PUT /tasks/{id}`
  pattern (same one `api/gameplan_upload.php` already uses).

**Note on "reminders":** this is a local PHP/MySQL app with no email or
push-notification infrastructure, so "reminder" here means overdue tasks
are visually flagged whenever you open this view — not an active
notification. If you actually want a daily digest email or something
similar, that's a materially different (and bigger) build — say the word
and it can be scoped separately.

**Heads up on performance:** since there's no `lead_id` filter, opening
this view scans every task in your account, paginated 100 at a time. Fine
at current scale; if your total task count grows into the thousands,
this will get slower to open per-lead — not urgent now, just flagging it
so it's not a surprise later.

### Part 2 (import unmatched Flozy leads) — dropped
You confirmed you never add prospects directly in Flozy, so there's
nothing this would ever actually import. Skipped rather than building
something with no real use case.

### Part 3 — done: 🩹 Create Missing Opportunities
You caught something real: leads pushed **before** Round 28 added
automatic Opportunity creation never got one at all — there's nothing in
Flozy for a sync to find. `Sync All Pipeline Stages` can only backfill an
ID for an Opportunity that already exists; it can't conjure one into
existence. This needed its own action.

- Refactored `includes/flozy_client.php` first: pulled the
  Opportunity-creation logic that used to live only inline inside
  `push_profile_to_flozy()` out into a shared `create_opportunity_for_lead()`
  function, and moved `build_stage_lookup()` /
  `build_lead_opportunity_lookup()` out of `api/sync_flozy_stage.php` into
  the same shared file. All three Flozy-writing/reading code paths (push,
  sync, and this new bulk fixer) now call the same functions instead of
  three copies of similar logic drifting apart over time.
- **New: 🩹 Create Missing Opportunities**, filter panel, next to "Sync
  All Pipeline Stages." For every pushed lead:
  - Does a **fresh live check** against Flozy first (not just the local
    `flozy_opportunity_id` column) to confirm an Opportunity genuinely
    doesn't exist — a lead could have one that's simply never been synced
    locally, and this must never create a duplicate for that case. If one
    already exists, it's backfilled locally instead of recreated.
  - Otherwise, creates one using the same `config/flozy.php` defaults
    (`default_opportunity_stage_name`, `default_opportunity_close_days`,
    `default_opportunity_confidence`) used at push time, and stamps the
    new stage locally immediately — no follow-up sync needed just to see
    it reflected.
  - Paced at ~3 requests/sec (`usleep(300000)` between leads) since this
    can process many leads in one run and each one is a real write, not a
    cheap read.
  - Reports created / already-had-one / failed counts, with per-lead error
    detail for anything that failed (most likely cause: same as push-time
    — `default_opportunity_stage_name` not matching a real stage).

All 3 parts of the original ask are now resolved (part 2 intentionally
skipped as not needed for how you actually use Flozy).

## Round 31: Elaborated outreach filter (stage + date range)

The outreach filter from Round 29 could only sort by Contacted / Not
Contacted / Ghosted. Added two more filters that combine (AND) with it —
no more scrolling through a growing Sent to Flozy list to find who's
actually still worth a follow-up:

- **Pipeline Stage filter** — a dropdown of your real stage names,
  sourced from `flozy_leads.current_stage` (already kept fresh locally
  by the 🔄 sync buttons, so this loads instantly with no extra Flozy API
  call). New `api/flozy_stages_in_use.php` powers it.
- **Outreached In filter** — Any time / Last 7 days / Last 30 days / Last
  90 days, filtered against `flozy_leads.outreached_at`.
- `api/profiles.php` — `stage_filter` is bound as a real parameter (it's
  an arbitrary value, unlike the small fixed set of literal fragments
  `outreach_status` drives), `outreach_days` is whitelisted the same way
  `outreach_status` already was. All three filters AND together, e.g.
  "Contacted" + "Discovery Call Booked" + "Last 30 days" narrows to
  exactly that.
- All three reset automatically when you leave the Sent to Flozy tab,
  same as the original outreach filter — they have no meaning elsewhere.

### ✅ Finished — "Move Stage" (change a lead's Flozy Opportunity stage from this app)
Confirmed against Flozy's real published API docs
(`docs.flozy.com/api-reference/opportunities/*`) before building anything —
not guessed. Confirmed:
- `PUT /opportunities/{id}` — a partial update; Flozy's own docs example
  sends only `stage_id` + `confidence` together, so `value`, `lead_id`,
  and `expected_close_date` don't need to be resent just to move stages.
- `POST /opportunities` (create, already in use since Round 28) returns
  the new Opportunity's own ID as `data.id`.
- `GET /opportunities` (list, already in use for stage sync) returns each
  item's own ID as `items[].id` — same shape the existing sync code
  already expected, just wasn't capturing that one field yet.

Built:
- `sql/migration_024_opportunity_id.sql` — new
  `flozy_leads.flozy_opportunity_id` column. This is NOT the same as
  `flozy_lead_id` — a Lead and its Opportunity are different resources
  with different IDs, and updating requires the Opportunity's.
- `includes/flozy_client.php` — captures the Opportunity ID at the moment
  it's created during a push, going forward.
- `api/sync_flozy_stage.php` — backfills `flozy_opportunity_id` for leads
  pushed *before* this round (via `COALESCE`, so it only fills a missing
  value, never overwrites a known-good one) — runs automatically the next
  time you hit 🔄 or "Sync All Pipeline Stages" on an older lead.
- `api/flozy_pipeline_stages.php` — new endpoint, fresh `GET /pipelines`
  call, returns real stage `{id, name, tag}` triples for the picker.
- `api/move_opportunity_stage.php` — the actual move. If a lead doesn't
  have an Opportunity ID on record yet (i.e. it's an old lead that hasn't
  been synced once since this round), it fails with a clear message
  telling you to sync that row first, rather than a confusing Flozy error.
- **🔀 Move Stage**, in the ⋮ action menu on the Sent to Flozy tab — opens
  a small modal, pick a real stage from a live-loaded dropdown, done. The
  Pipeline Stage badge updates immediately using data already in hand
  (the stage name/tag the picker just showed you), no second Flozy
  round-trip needed just to redisplay what you already know you just set.

## Round 30: Flozy deep-link, bulk-tab-open bug fix, Opportunity re-confirmed

### Direct link to the Flozy lead
Added — no more hunting through the pipeline to find a creator. Confirmed
against a real lead URL (`https://dashboard.flozy.com/leads/detail/538145`)
rather than guessed.

- **No config edit needed, ever, for the normal case.** Flozy's dashboard
  domain is a fixed, non-secret constant, so it's baked directly into
  `public/index.php` as a default (`https://dashboard.flozy.com`) instead
  of living only in `config/flozy.php`. That file holds your real API
  key, so — correctly — it doesn't get overwritten by a fresh zip, which
  means anything that *only* lived there would need a manual add on every
  update. `config/flozy.php` still has a `dashboard_base_url` line
  (commented out) purely as an optional override, e.g. if you're ever on
  a white-labeled Flozy domain — nothing to do with it otherwise.
- `api/profiles.php` — `fl.flozy_lead_id` is now selected on the Flozy
  view so the frontend has it to build the link.
- **🔗 button** next to the Pipeline Stage badge (visible directly on the
  row, no dropdown needed — this was the actual point of "it's a
  hassle") plus a duplicate entry in the ⋮ action menu for anyone who
  prefers that path. Opens `{dashboard_base_url}/leads/detail/{flozy_lead_id}`
  in a new tab.

**Bug this caused, now fixed:** the first cut of this feature read
`dashboard_base_url` straight out of `config/flozy.php` with no fallback.
On a live install where your real config file (correctly) hadn't picked
up that new key yet, PHP threw a deprecation notice for passing `null`
into `rtrim()` — and that notice printed **directly into the middle of
the page's `<script>` tag**, corrupting the JavaScript and breaking the
entire script. That's why `switchView` looked "not defined" and the table
showed no data, even though none of the table code itself had changed.
Moving the default into the code (as described above) fixes this at the
root rather than just patching around it — there's no longer a "missing
config key" state for this value to be in. Worth remembering for future
rounds: any *non-secret* constant read into inline PHP-in-`<script>`
output belongs in code with a real default, not solely in a config file
that's expected to lag behind the code that reads it.

### Fixed: "Open Selected in New Tabs" opening unselected profiles
Root cause found — this was a real bug, not a misunderstanding. Two
compounding issues in `public/index.php`:

1. `doOpenTabs()` looped over the entire `selectedUsernames` Map instead
   of the current `selectedIds` Set, so it opened *every username ever
   selected*, not just the ones currently checked.
2. `switchView()` (running when you click Active/Archived/Future/Flozy)
   cleared `selectedIds` on tab switch but never cleared
   `selectedUsernames` — so leftover entries from a previous tab sat in
   that Map indefinitely and got opened the next time you used the
   button, even from a completely different tab.

Both fixed: `doOpenTabs()` now only opens usernames for ids present in
`selectedIds`, and `switchView()` now clears both. Selecting profiles on
one tab, switching tabs, and clicking "Open Selected" elsewhere no longer
drags old selections along.

### Opportunity auto-creation — already built (Round 28), re-confirmed here
This was raised again, so to be clear: **this already exists** and ships
in this zip — nothing new needed. `includes/flozy_client.php`'s
`push_profile_to_flozy()` creates a Lead *and* an Opportunity in the same
push. If you haven't seen it working: the push status message (shown
after clicking "Send to Flozy," single or bulk) will explicitly say
`Opportunity not created: ...` with the real reason if it failed — the
most common cause is `default_opportunity_stage_name` in
`config/flozy.php` (currently `'New Lead'`) not exactly matching a real
stage name in your Flozy pipeline. If you see that error, paste the exact
message and I can fix the config value with you.

## Round 29: Outreach tracking — finished (picks up where Round 28 left off)

Round 28 built the migration and the toggle endpoint for the outreach
flag but never wired any of it into the frontend. This round finishes it —
nothing new stored beyond what Round 28 already migrated.

- **Toggle + badge**, Sent to Flozy tab only — new **Outreach** column
  (between Pipeline Stage and Actions). Shows a green "✅ Contacted" badge
  with an ↩️ undo button once marked, or a "Mark Outreached" button if not.
- **Filter dropdown** — All / Contacted / Not Contacted / Ghosted (no
  reply), shown only on the Sent to Flozy tab (hidden and reset to "All"
  the moment you switch to any other tab, since the filter has no meaning
  there). "Ghosted" is not a separate status field — it means
  `outreached_at` is set AND the synced Flozy pipeline stage is literally
  named "Ghosted" (that's a real stage name in this pipeline, per the
  round 15/22 notes above, not a guessed enum value). Since stage data
  only updates when you click 🔄 or "Sync All Pipeline Stages", the
  Ghosted filter is only as fresh as your last sync.
- **`api/profiles.php`** — `outreach_status` is now whitelisted
  server-side (`contacted` / `not_contacted` / `ghosted` / empty) before
  being appended as a literal SQL fragment (it drives which fragment gets
  used, not a bound value, so it's validated with `in_array()` first
  rather than parameterized) — applied only on the `flozy` view.
  `fl.outreached_at` is now selected so the frontend has something to
  render.
- **`api/toggle_outreach.php`** — marking a lead outreached now also logs
  a completed task in Flozy (title "Outreach Sent", status `3`/completed)
  via `POST /tasks` — the same already-confirmed endpoint
  `includes/flozy_client.php` uses elsewhere in this project, not a
  guessed notes endpoint. **Undoing does NOT touch Flozy** — only the
  local flag changes, since there's nothing meaningful to retract on
  Flozy's side for an undo.
- If the Flozy task-logging call fails, the local toggle still succeeds —
  it's never rolled back over a Flozy-side hiccup. You just get a warning
  toast (`flozy_task_error` in the response) telling you the Flozy record
  didn't land, so you know to check.

**Heads up on table columns:** a new column was inserted between Pipeline
Stage and Actions. DataTables' `stateSave` remembers column
visibility/order by index across reloads — if your browser has state
cached from before this round, column visibility may reset once on first
load after updating. Nothing is lost; just re-toggle anything you'd
hidden via "👁 Columns."

### ❌ Still not started (carried over from Round 28)
- **Direct link to Flozy lead** — needs you to check Flozy's real
  lead-detail URL pattern first (open a lead in Flozy, copy the URL) —
  can't guess this one, same policy as everything else API/URL-related.
- **Table-refresh bug investigation** — audited the functions that could
  be found and they already had the correct `reload(null, false)` fix
  applied, so the bug (if still happening) needs a specific repro from
  you: which button, which tab, exactly what doesn't update — to actually
  pin it down rather than guess broadly again.

## Round 28: Opportunity auto-create, progress badge colors, bulk tab-open

- **Auto-create Opportunity on Flozy push** — `includes/flozy_client.php`
  now creates both the Lead AND an Opportunity (was Lead-only before,
  meaning nothing showed up in your Pipeline until a manual step in
  Flozy's UI). Config in `config/flozy.php`:
  `default_opportunity_stage_name` (must match a real stage name in your
  pipeline), `default_opportunity_close_days`, `default_opportunity_confidence`.
  Push feedback now shows if the Opportunity creation succeeded/failed.
- **Progress badge colors** — the 3 dots (Gameplan/Scraped/Message) now
  each have a distinct color (blue/purple/orange) instead of identical
  green, so they're actually distinguishable at a glance.
- **"Open Selected in New Tabs"** — new bulk-action button (works on every
  tab), opens each selected profile's Instagram URL in a new tab. Warns
  first if opening more than 10 (browser popup-blocker territory).
- **Outreach flag migration + endpoint** —
  `sql/migration_023_outreach_flag.sql` (adds `flozy_leads.outreached_at`)
  and `api/toggle_outreach.php` were built this round; wiring them into
  the frontend was finished in **Round 29** above.

## Round 27: Content Studio — separate feature, own tab

Run `sql/migration_022_content_studio.sql`.

A genuinely separate feature from lead outreach — for generating YOUR OWN
posting content (Reel/Story/Carousel), not messages to leads. New page:
`public/content_studio.php`, linked from the dashboard nav.

**Scope note:** the original ask referenced a much larger "Prompt OS"
concept (unlimited modules, versioning, drag-and-drop builder). Scaled
down to what you actually confirmed you need — same judgment call as
round 26's prompt templates, just applied to this new area.

### How it works
No AI call happens in this app for Content Studio — it's pure structured
**assembly**. The final prompt gets copied and pasted into Iman's tool,
which does the actual generation.

Final prompt = Brand Voice (shared with lead-outreach side) + Color Rules
+ Master Prompt for the content type + Campaign Rule for that type+stage
+ optional Structure Rule (only Carousel has one right now) + your brief
(Theme/Core Idea/Angle).

- **3 Master Prompts** (Reel/Story/Carousel) — your real content, seeded
  verbatim, with the repeated color block replaced by a `{color_rules}`
  placeholder so editing colors once propagates everywhere instead of
  needing 3 separate edits.
- **9 Campaign Rules** (3 types × Awareness/Consideration/Conversion) —
  also your real content.
- **1 Structure Rule** (Carousel only, your "rotate structures naturally"
  rule) — nullable per type, so Reel/Story just skip that section until
  you add one.
- **Wizard UX**: one task per screen, big buttons for type/stage
  selection, minimal typing (only Theme/Core Idea/Angle are freeform —
  everything else is a big-button pick). Auto-saves every generation to
  `content_briefs` — no manual save step.
- **Recent Briefs** — click any past brief to reload it as a starting
  point for a new one instead of retyping.
- **Edit Foundation & Templates** — same page, collapsible sections,
  every Master/Campaign/Structure text and Color Rules directly editable,
  no code changes ever needed.

## Round 26: Editable prompt templates + shared brand voice

Run `sql/migration_021_prompt_templates.sql`.

Every AI prompt was hardcoded in PHP — same problem already solved for
keyword rules, task templates, and score weights. This round applies the
same fix to prompts themselves.

**Note on scope:** the original ask referenced a much larger "modular
Prompt OS" concept (versioning, tagging, drag-and-drop module composition,
multi-platform expansion). That's disproportionate for what this actually
needs — built the real value instead: full prompt editability + one shared
voice layer, without the extra machinery.

**Note:** this round hit two session interruptions mid-build, which meant
the integration work (updating the actual prompt-generating files to use
the new template engine, plus the Settings UI) got lost twice before
landing correctly — worth knowing in case anything here looks like it
was rebuilt from scratch, because it was.

- **`prompt_templates` table** — all 8 prompts (verification, hook+followup,
  each of the 5 follow-up types, niche classification) now live here with
  `{placeholder}` tokens, not hardcoded PHP strings. `includes/prompt_engine.php`
  does the substitution.
- **Brand Voice** — one shared text block, auto-prepended to every single
  generated prompt. Change tone/style once instead of editing 8 templates.
  Empty by default, skipped entirely if blank.
- **Settings → Prompt Templates** — collapsible list, each with its
  available placeholders shown as reference, edit and save directly.
- Fails safe: if a template is somehow missing (e.g. migration not run),
  generation still runs — degrades to raw placeholder text instead of
  crashing.

## Round 25: Generation history timeline per lead

No migration — combines two tables that already existed (`content_analysis_runs`
and `followup_messages`) into one view, nothing new stored.

New "🕐 History" button (Active, Future, and Sent to Flozy tabs) shows a
single chronological timeline for a lead: every cold-outreach hook/follow-up
ever generated, plus every guided follow-up (with what you told it you were
sharing, for the types that need input) — newest first, so you can eyeball
whether you're about to repeat yourself before generating another message.

Also shows **data freshness** at the top — days since content was last
actually scraped for that lead, flagged if over 30 days old, so you know
whether it's worth a fresh Verify+Personalize before the next touchpoint
rather than working off stale content.

Deliberately no automated duplicate-detection — same "system, not machine"
principle as the guided follow-up picker: you look at the timeline and
judge repetition yourself, the system just makes that judgment easy to make
instead of trying to make it for you.

## Round 24: Human-pattern sweep scheduling (not built for now, but ready if it's needed)

Run `sql/migration_020_sweep_schedule_log.sql`.

A fixed 27th/28th sweep, or even a fixed 15-day window with even daily
amounts, is still a detectable pattern — regular timing is itself an
automation fingerprint separate from key concentration. Built as a daily
scheduled job (`jobs/scheduled_budget_sweep.php`, same Task Scheduler
pattern as the AI niche check) that decides everything fresh each day:

- **Window**: last N days of the month (`config/sweep_schedule.php` →
  `window_days`, default 15, raise to 20+ for an even more spread pattern)
- **Active day?**: random chance each day within the window — not every
  day does something
- **How much today?**: a random % of currently-eligible leads, recalculated
  fresh each day (not a fixed daily amount)
- **Which keys today?**: a random subset of active keys, not all of them —
  builds on the round-robin rotation from last round
- **Pacing within a day**: random pause (20-90s, configurable) between each
  lead's scrape, not back-to-back requests

Deliberately **no catch-up logic** forcing extra spend near the window's
end — some leftover budget going unused is the accepted tradeoff for not
having a detectable last-day burst.

The old manual "Run Sweep Now" button still exists for on-demand
testing/override. New "📜 Sweep History" button shows a day-by-day log of
what the scheduled job actually did, so the randomization can be audited
rather than trusted blindly.

Config is a plain editable PHP file, not a Settings CRUD panel — consistent
with how other tunable numbers in this project work (`content_analysis.php`,
`openrouter.php`'s batch size, etc.), rather than building a new DB-backed
system just for ~8 numbers.

## Round 23: Round-robin Apify key rotation

Run `sql/migration_019_key_rotation.sql`.

Previously `pick_apify_key()` always preferred the first key with enough
budget — meaning as long as key #1 had room, 100% of traffic sat on one
account indefinitely. That's exactly the pattern that looks automated
rather than a human spreading activity across several accounts, which
matters when a key belongs to a borrowed/friend's account.

Now keys rotate by **least-recently-used first** (new `last_used_at`
column, stamped every time a key actually gets used). Budget is still the
hard constraint and still wins — a key gets skipped if it's genuinely low
on room regardless of whose turn it is in rotation. Sort # is now just the
tiebreaker between equally-due keys, not the primary ordering.

Settings → Apify Key Rotation Pool now shows a "Last Used" column per key
so you can visually confirm traffic is actually spreading out.

## Round 22: Gap-fixing pass (7 issues from a full project review)

Run in order: `migration_016_sweep_exclusion_correction.sql`,
`migration_017_niche_queue_retry_cap.sql`,
`migration_018_review_resolution_and_score.sql`.

1. **Niche merge fixed** — was only updating `profiles`, leaving
   `keyword_rules` pointing at the deleted niche name. A future matching
   bio would silently recreate the niche you just merged away. Now
   `keyword_rules` gets repointed to the surviving niche too.
2. **Sweep exclusions corrected** — per updated business rules: Discovery
   Call Booked, Presentation Call Booked, Not A Right Fit excluded.
   **Ghosted explicitly un-excluded** (removed if previously added) — per
   the touchpoint algorithm study material, ghosted leads still get
   monthly touchpoints, so they still need fresh data.
3. **AI niche retry cap** — `max_attempts` in `config/openrouter.php`
   (default 5). A profile that fails every model every time now gets
   marked "Uncategorized" and stops consuming a slot in every future batch,
   instead of retrying forever.
4. **Apify budget check confirmed correct** — verified against Apify's real
   docs (`docs.apify.com/api/v2/users-me-limits-get`). The fallback path I'd
   already hedged with (`data.limits.maxMonthlyUsageUsd` /
   `data.current.monthlyUsageUsd`) turned out to be exactly right — cleaned
   up to use it directly instead of the uncertain dual-attempt.
5. **Manual-review resolution UI built** — the ⚠️N badge is now clickable,
   opening a list of flagged reels per lead with "✅ Confirm OK" / "🚫
   Exclude" buttons. Excluded transcripts are filtered out of every AI
   prompt (verification, follow-ups, everywhere) going forward.
6. **Quality Score is now server-side and sortable** — was client-side-only
   before, which meant it couldn't be sorted or trusted for bulk decisions.
   Computed at import, recomputed when AI assigns a niche or a manual-review
   flag gets resolved, and a "Recompute All Scores" button in Settings for
   after weight changes. New 5th signal: **Content Verified** — a lead with
   unresolved manual-review flags scores lower than one with fully verified
   content.
9. **"Regular" follow-up blocked cleanly with no stored content** — used to
   feed a placeholder string into the AI prompt as if it were real context,
   risking a hallucinated-sounding message. Now returns a clear error
   telling you to run Verify+Personalize first, or pick a type that doesn't
   depend on stored transcripts.

Points 7, 8, 10 from the review (orphaned `flozy_lead_tasks` rows on
remove, gameplan-task auto-complete not retroactive for pre-existing
pushes, sweep not covering pre-qualified Active leads) — confirmed
intentional, left as-is.

## Round 21: Stop re-scraping the same reels

Run `sql/migration_015_reel_cost_correction.sql`.

- **`onlyPostsNewerThan` wired in** — confirmed real Apify param from the
  actor's actual input schema page. On any re-scrape (sweep, manual
  re-run), only reels posted after the most recent one we already have get
  requested — no more re-paying for transcripts on content already fetched.
- **"No new content" is no longer treated as a failure** — if a profile
  hasn't posted anything since last scrape, the system falls back to
  re-running AI on existing stored data instead of erroring out or wasting
  a call to confirm nothing changed.
- **Dedup safety net** — since the filter is date-based (not exact
  timestamp), a boundary post could theoretically come back twice; now
  skipped if already stored.
- **Cost table corrected** — confirmed from Apify's own schema page: Reel
  Scraper transcripts are billed **per minute of audio**, not per-1000
  results like the rest of the cost table assumes. The $/1000 rate for
  this actor was always a rough blended guess — now explicitly flagged as
  such rather than presented as a reliable number.

## Round 20: Fixed reload-resets-page bug + row selection with bulk actions

Run nothing new — no migration, existing endpoints extended.

- **Table reset bug fixed** — `table.ajax.reload()` resets to page 1 by
  default; every action-triggered reload now passes `(null, false)` to
  preserve your current page/scroll position instead of bouncing you back
  to page 1 after every archive/note/verify click.
- **Row selection + bulk actions** — checkbox column (leftmost), a
  "select all visible" checkbox in the header, and a bulk-action bar that
  appears once anything's selected. Selection persists across pages within
  the same tab (tracked client-side), clears when switching tabs since
  cross-tab bulk actions don't make sense. Available actions match the
  current tab (Archive/Future/Push to Flozy on Active, Restore/Push on
  Future, Restore on Archived, Remove on Sent to Flozy).
- New backend actions: `archive_selected`, `future_selected`,
  `restore_selected` (in `archive.php`), `push_selected` (in `flozy_push.php`)
  — all take a `profile_ids` array instead of a threshold, for precise
  manual curation rather than range-based bulk actions.

## Round 19: Auto-complete the Flozy "Gameplan" task

Run `sql/migration_014_flozy_task_tracking.sql`.

Works both directions:
- **Gameplan uploaded before push** (pre-qualify workflow) — when the lead
  gets pushed to Flozy, any task template with "gameplan" in its title gets
  created as already-completed instead of an open todo.
- **Gameplan uploaded after push** — finds the matching task (tracked via
  new `flozy_lead_tasks` table, since task IDs weren't being saved before)
  and marks it complete via Flozy's API.

Matching is by title containing "gameplan" (case-insensitive), not exact
match — so renaming the task template in Settings still works.

## Round 18: Every column toggleable, not just three

No migration needed.

The "👁 Columns" menu now covers every real data column (Full Name, Niche,
Followers, Engagement %, Score, Avg Likes, Avg Comments, Posts/Week, Bio,
Link, Last Updated, Notes, Progress, Pipeline Stage) — generated from a
list rather than hardcoded, so show/hide whatever's relevant in the
moment. Username and Actions stay fixed (identity + functionality, not
optional data). Your choices persist across reloads via DataTables'
existing state-save.

## Round 17: Settings layout fix, hideable columns, Future tab parity

No migration needed.

- **Sweep checkbox staircase fixed** — labels used `display:flex` without
  `width:100%`, so each shrunk to its own text length and got positioned
  inconsistently. Forced full-width, left-aligned.
- **Full Name / Bio / Link hidden by default** — new "👁 Columns" toggle
  next to Settings link lets you show any of them back on demand instead
  of always taking up table space.
- **Future tab now has the same action set as Active** — Growth Chart,
  Upload Gameplan, Verify+Personalize, View Results, Send to Flozy,
  Archive — all available directly from Future, so pre-qualification work
  isn't lost/forgotten when something sits there before being restored.
  Archived tab intentionally kept simple (Restore only) since archived
  profiles didn't qualify in the first place.

## Round 16: Full-width layout fix + settings search boxes

No migration needed — CSS/JS only.

- **Full-width layout fixed** — DataTables locks in column widths once at
  initialization; if that happened before the layout fully settled, it can
  leave a narrow table with dead space on wide screens. Added
  `autoWidth: false`, forced `width: 100% !important` via CSS, wrapped the
  table in a horizontally-scrollable container, and added a window-resize
  handler that tells DataTables to recalculate.
- **Search boxes added** to Settings → Keyword Rules and → Niche→Category
  assignment, since both lists are growing and had no way to filter.
- **Duplicate button** — flagged but not yet identified from screenshots
  alone; point it out live next session and it's a fast fix.
- Budget sweep "Could not load" was confirmed to be a config/migration
  setup issue on your end, not a code bug — no fix needed there.

## Round 15: UI cleanup + sweep exclusion fix

Run `sql/migration_013_sweep_exclusions.sql`.

- **Actions column cleaned up** — was stacking 5-7 full buttons per row
  (the mess in your screenshot). Now: one primary button + a "⋮" dropdown
  for everything else. Same actions available, way less visual clutter.
- **Progress badges fixed** — were wrapping onto multiple lines due to
  missing `white-space: nowrap`; also swapped to smaller dot icons.
- **Pipeline Stage column** no longer wraps either.
- **Sweep exclusion bug fixed** — Flozy tags "Ghosted" and "Not A Right
  Fit" as ACTIVE, same as genuinely progressing stages, so filtering by
  tag alone would have wasted budget re-scraping dead leads. New Settings
  section pulls your real live stage names from Flozy and lets you check
  which ones should never be swept — not hardcoded, since your stage names
  are custom to your pipeline.

## Round 14: Guided follow-up system + message angles + budget sweep

Run `sql/migration_012_categories_followups.sql`.

Built as a **guided system, not an autonomous picker** — you choose the
follow-up type and provide the actual content being shared; the AI frames
it, it doesn't invent what to share.

### Message-Angle Categories (Settings)
A layer above your existing niches — e.g. "Experts" (coaches, nutritionists)
get a different message angle than "Specialists" (marketers, designers) or
"Entrepreneurs with Personal Brand." Seeded with your 3 examples + starter
angle text, fully editable. Assign niches to categories in the same Settings
panel. The angle gets injected into every AI-generated hook/follow-up for
creators in that category — both the cold-outreach system and follow-ups.

### Follow-up system (💬 button, Sent to Flozy tab)
Opens a modal: pick the type (Regular / Validation Script / Free Value /
Win-Insight Share / Free Custom Survey Offer). Everything except Regular
requires you to type what you're actually sharing — the AI frames it well,
you supply the substance. Every generated follow-up is logged to
`followup_messages` for history.

**Free Custom Survey** was today's genuinely clever addition — it's not new
data, just repackaging what the verification pipeline already generates
(audience-problem confirmation + engagement data) as a "here's a free
mini-audit of your account" offer.

### Budget Sweep (separate panel, NOT part of the messaging system)
Purely about not letting unused Apify budget expire. Shows remaining budget
across active keys + eligible active-lead count, flags the 27th/28th window,
and a manual "Run Sweep Now" button refreshes the oldest-data leads first
(reuses the existing verification pipeline internally, forced past the
21-day cache). This is a data-refresh action, not a message-sending one —
deliberately kept separate from the follow-up system per your note that
these are two different concerns.

### Also from today's study material — refines the earlier warm-up plan
The touchpoint algorithm from your slides is **stage-dependent**, not flat
monthly:
- No reply / replied-then-silent → every 1-2 **weeks**
- Rejected/Ghosted → **monthly** (this is what the 27th/28th sweep actually maps to)
- Won → stop

Worth keeping in mind for whenever the full automated warm-up scheduling
gets built — right now the sweep is manual/on-demand, not yet tied to a
per-lead cadence engine.

## Round 13: Pre-qualification before Flozy + manual-review flagging

Run `sql/migration_011_manual_review_flag.sql`.

- **Pre-qualify before pushing to Flozy** — Gameplan upload, Verify+Personalize,
  and View Results are now available on the **Active** tab, not just Sent to
  Flozy. Since the whole pipeline (Apify, Gemini) is third-party and doesn't
  depend on Flozy at all, vetting can happen before a lead is even pushed —
  push to Flozy only once it's confirmed worth pursuing.
- **Progress badges now show on Active too** — 📄🔍💬 at a glance before you decide to push.
- **Manual review flag** — a reel with no actual transcript (just music/b-roll,
  no OCR available) gets flagged. Shows as ⚠️ N next to the progress badges —
  worth a human glance before trusting AI personalization built from it.

## Round 12 (corrected): Pipeline markers + real Opportunities/Pipelines stage sync

Run `sql/migration_009_pipeline_stage.sql` then `sql/migration_010_opportunity_stage.sql`.

**Correction from the original round 12 build:** what I originally pulled
(`GET /leads/{id}` → `status_name`) is a *different* field from Flozy's
actual Pipeline (New Lead → Creator Gameplan Sent → Discovery Call Booked
→ Presentation Call Booked → Won/Lost/etc, shown in the screenshots) — that
real pipeline lives in **Opportunities + Pipelines**, two separate Flozy
resources. Rebuilt against those instead:

- `GET /pipelines` — all pipelines with their stages (id, name, tag_name: active/won/lost)
- `GET /opportunities` — each has a `stage_id` + `lead_id`, no confirmed
  lead-filter param, so the sync fetches all opportunities once (paginated)
  and matches locally — actually more efficient than the original per-lead
  design, not less
- **Progress badges** (Sent to Flozy tab only): 📄 Gameplan uploaded, 🔍 Scraping done, 💬 Message generated
- **Pipeline Stage column** — color-coded by tag (green=won, red=lost, blue=active), 🔄 to refresh one lead
- **Bulk sync** — "🔄 Sync All Pipeline Stages" fetches pipelines+opportunities once, applies to every lead — no per-lead API calls, so no rate-limit concern regardless of how many leads you have

## Round 11: Hook / Follow-up split

Run `sql/migration_008_hook_followup.sql` first.

The message generator used to produce one blended "chat," but Instagram DM
previews only show the first line before someone taps in — that first line
needed to be doing one job (earning the open), not blended with the rest.

- **Hook**: under 12 words, references one hyper-specific real detail from
  their content, written to create curiosity — this is what shows in the
  DM preview/notification
- **Follow-up**: the natural next 1-3 sentences, only relevant once
  they've already opened it — can gently move toward asking about their
  offer/coaching if verification confirmed real audience intent
- Both generated in one Gemini call (`includes/message_generation.php`,
  shared by both "Verify+Personalize" and "Retry AI Only" so they stay
  consistent), stored as separate fields, shown as two separate boxes with
  their own Copy buttons in the results modal
- If Gemini doesn't follow the expected format, the whole response falls
  back into the follow-up box rather than losing content

Note: this is for the **initial cold-outreach message only**. The monthly
warm-up message (27th/28th sweep, Flozy-pipeline-based priority) is a
related but separate feature, still just planned — different tone entirely
since it's for leads you've already reached, not a cold open.

## Round 10: Model fix, crash-proofing, SweetAlert2, cost optimization

- **Gemini models fixed** — `gemini-2.5-flash`/`gemini-2.5-flash-lite` were deprecated mid-build. Now uses your confirmed-working `gemini-3.5-flash` + `gemini-3.1-flash-lite`, plus `gemini-flash-latest` as a third fallback (an alias Google keeps pointed at whatever's current, so future renames hopefully don't repeat this).
- **UTF-8 sanitization** — PDF/scraped text with invalid byte sequences was silently making `json_encode()` fail, which sent an essentially empty request to Gemini (the "contents is not specified" error). Fixed at the source.
- **Crash-proofing** — `includes/error_handler.php`, wired into the 3 endpoints most likely to hit edge cases (`run_verification.php`, `rerun_ai_analysis.php`, `gameplan_upload.php`). Any PHP crash now returns clean JSON with the real error instead of breaking silently.
- **SweetAlert2** — replaces every `alert()`/`confirm()` on both pages:
  - Info → auto-dismissing toast (3s timer)
  - Warning/Error → toast that stays until you close it, **always** logged to console with full details too
  - Confirmations → proper modal instead of the native browser popup
  - Long AI/scrape operations → persistent "please wait" toast, auto-removed when done
- **Cost optimization** (`config/content_analysis.php`):
  - `comments_per_post`: 20 → 12 (still plenty for theme detection)
  - **Top-K comment concentration**: comments are now only pulled from the 4 highest-engagement posts of the N scraped, not all of them — that's where the real signal is anyway, and it's the single biggest lever without losing depth
  - **21-day cache**: if a lead was already scraped recently, "Verify+Personalize" automatically reuses that data instead of paying for Apify again (same underlying logic as "Retry AI Only," just automatic now)

## Round 9: Gameplan Verification & Personalized Outreach

Run `sql/migration_007_gameplan_verification.sql` first.

### Setup
1. **Gemini key**: free at https://aistudio.google.com/apikey — paste into `config/gemini.php`
2. **Apify keys**: Settings → "Apify Key Rotation Pool" — add your key (and friends' keys as you get them)
3. Check `config/content_analysis.php` — `posts_to_check` (default 8) and `comments_per_post` (default 20) are adjustable there

### How to use it (Sent to Flozy tab, per lead)
1. **📄 Gameplan** — upload the PDF from Iman's tool. Text is auto-extracted (via the `smalot/pdfparser` you just installed) and stored.
2. **🔍 Verify+Personalize** — runs the full pipeline: pulls N recent reels (transcripts), pulls comments on those same posts, then two Gemini passes — verifies the gameplan's claims against real comments, then drafts a personalized message using real transcript content. Takes a minute or two; don't close the tab.
3. **📋 Results** — reopens the last completed run without re-running it (re-running costs money again, viewing past results doesn't).
4. If the lead is already in Flozy, results also get attached as a task there automatically.

### Update: Apify input fields — now confirmed (you pulled these directly from the actors' own Input examples)
- **Reel Scraper**: `username` must be an **array** (`["natgeo"]`), not a plain string — this was the actual bug. Also `includeTranscript` **defaults to false**, so it was silently returning empty transcripts even on a "successful" call — now explicitly set to `true`. Also added `skipPinnedPosts: true` to match how pinned posts are excluded everywhere else in this project.
- **Comment Scraper**: `directUrls` + `resultsLimit` were already correct.

Still not 100% output-confirmed (lower-stakes than the input bug, since output field names for Apify actors are consistent across their whole Instagram lineup and we already verified several exact matches from the Profile Scraper's real JSON): the Reel Scraper's exact output field names (`url`, `shortCode`, `transcript`, `caption`, `likesCount`, `commentsCount`, `timestamp`) and the Comment Scraper's output fields (`text`, `postUrl`, `ownerUsername`, `likesCount`) — the code already has fallback field-name checks for the ones I was least sure of. If a run partially works (transcripts show up but some fields are blank), that's where to look next.

### Three things I could NOT fully verify — flagging honestly rather than guessing
This matters because it's real money and a live pipeline, not a "just try it" situation:

1. **Apify input field names** (`username`/`resultsLimit` for Reel Scraper, `directUrls`/`resultsLimit` for Comment Scraper) are my best inference from Apify's common conventions, **not confirmed** against those two actors' actual Input schemas. If a run comes back empty, check the actor's Input tab (or its API tab, which shows a ready example) in Apify Console for the real field names and tell me — quick fix once confirmed.
2. **Reel Scraper's cost rate** ($2.70/1000, seeded in Settings) is estimated from the pattern of every other official Apify Instagram actor, not a confirmed quote for that specific actor. Comment Scraper's rate ($2.30/1000) IS confirmed directly from Apify's store page.
3. **The budget-check response parsing** (`GET /users/me/limits`) is a best-effort guess at Apify's field names. It's written to fail safe — if it can't confidently read a number, it just tries the next key rather than blocking or guessing wrong. First real run, check what it actually returns.

None of these block the build from working end to end — they're the specific spots most likely to need a small adjustment once you see real data.

## Round 8: Composite Quality Score (#10)

Run `sql/migration_006_quality_score.sql` first.

- **Score column** on every profile — blends Engagement Tier, Posting
  Consistency, Growth Trend, and Niche Assigned into a single % + letter
  grade, computed live in the browser from your current weight settings
  (not stored — change a weight, every score updates instantly).
- **Adjustable weights** — Settings → "Composite Quality Score". All start
  equal (1.00); tune them once you have a feel for what actually matters,
  or switch a signal off entirely.
- **Full explanation** — dashboard → "❔ How Scoring Works" button, or the
  same section in Settings, walks through the exact formula and point
  scale.
- Note: since it's computed client-side (not a DB column), the Score column
  isn't server-sortable yet — visible and color-coded, but not click-to-sort.

## Round 7: Nine feature additions

Run `sql/migration_005_notes_growth_keywords.sql` first — adds notes,
growth tracking, and moves keyword rules into the database.

| # | Feature | Where |
|---|---|---|
| 1 | Growth chart per profile | 📈 button on every row (any tab) — Chart.js line chart of followers/engagement over every snapshot |
| 2 | Niche filter dropdown | Filter panel, shows profile count per niche |
| 3 | Bulk Send to Future (range-based) | Filter panel — uses the same Min/Max Followers/Engagement inputs, which now apply to ALL bulk actions and the Active tab's own filter |
| 4 | DB backup | Settings → "Download Backup Now" button, or schedule `jobs/backup_db.bat` in Task Scheduler for automatic nightly backups (30-day retention) |
| 5 | Keyword rules in Settings | Settings → full CRUD, no more editing `keyword_rules.php` directly |
| 6 | Growth alert on re-scrape | 🚀 badge + %growth shown next to Followers if a re-imported profile grew 15%+ since its last snapshot |
| 7 | Notes field per profile | Inline editable text box, own column, saves on blur |
| 8 | Import batch history | "📜 Import History" button in filter panel — modal listing every import (file, new/dup counts, when) |
| 9 | Niche merge tool | Settings → merge near-duplicate niches (e.g. "Parenting" into "Parenting & Family") |

Note: the Active tab's display filter, bulk Archive, bulk Future, and bulk
Flozy push all now share the same Min/Max Followers/Engagement inputs —
set them once, then choose which bulk action to run.

## Round 6 fixes
- **Flozy lead URL fixed** — now always the Instagram profile link
  (`instagram.com/username`), not the bio's external link.
- **Manual AI niche check button** — "Run AI Check Now" next to the Pending
  Niche stat card, so you're not stuck waiting for the 5-minute scheduled
  job before pushing a lead to Flozy. Same logic as the scheduled job, just
  triggerable on demand.
- **New "Future" tab** — a 4th tab alongside Active/Archived/Sent to Flozy,
  for accounts irrelevant right now but possibly useful later. Different
  from Archived (which means "didn't meet qualification criteria").
  Run `sql/migration_004_status_and_future.sql` to add this.

## Flozy Integration (Round 5)

### Setup
1. Run `sql/migration_003_flozy.sql` in phpMyAdmin (adds `flozy_leads` and
   `flozy_task_templates`, seeds a starter task list).
2. In Flozy: **Settings → API Keys** → create a key with scopes `write:leads`,
   `delete:leads`, `write:tasks`. Copy it immediately (shown once).
3. Open `config/flozy.php` and paste the key in. **Never paste API keys into
   a chat with Claude or anywhere else — only into this local file.**
4. Visit `public/settings.php` to review/edit the default task list before
   pushing anything for real.

### How it works
- **Push to Flozy** (single row button, or "Push Qualified to Flozy" bulk
  button in the filter panel) creates a real lead in your Flozy account
  under the `creator_prospect` status, plus every active default task
  linked to it.
- Pushed profiles move into their own **"Sent to Flozy"** tab here and
  disappear from Active/Archived (they're not deleted locally — just
  tracked separately via the `flozy_leads` table).
- **Remove from Flozy** (button on that tab) really deletes the lead in
  Flozy — including its tasks, which cascade automatically — and the
  profile becomes a normal Active/Archived entry again here.
- The lead title format is `@username | Full Name`. The lead description
  packs in followers, engagement %, niche, avg likes/comments, and
  posts/week (Flozy caps this field at 500 characters).
- **Custom Fields are NOT used** — Flozy's Custom Fields API is read-only
  (you can list definitions, but there's no way to set a value on a lead
  via API). The stat summary lives in the lead description instead, with
  full detail in the description field of the "Creator Profile"-style
  tasks if you want more room later.
- Bulk push paces itself at roughly 2.5 requests/second to stay well under
  Flozy's 100 requests/minute per-key limit even with several task calls
  stacked per lead.

### Editing the default task list
Go to `public/settings.php` — add, edit, reorder (Sort # column), toggle
active/inactive, or delete tasks. No code changes ever needed for this;
changes apply to the next profile you push, not retroactively to leads
already in Flozy.

## Round 4 fix
- **Search box crash fixed** — the query reused one named placeholder
  (`:search`) three times, which PDO rejects when emulated prepares are off
  (see `config/db.php`). That crash was producing broken output the moment
  you typed anything, which DataTables reported as "Invalid JSON response."
  Now uses three distinct placeholders. Also wrapped the whole endpoint in
  try/catch so any future backend error returns clean JSON with a readable
  message instead of crashing the table again.

## Round 3 fixes
- **"None" niche bug fixed** — Apify sometimes returns the literal string
  `"None"` for businessCategoryName, which used to get accepted as a real
  niche (skipping both keyword matching and the AI queue). Now treated as
  no category, same as blank. Run `jobs/requeue_invalid_niches.php` again
  to clean up profiles already stuck with a "None" niche.
- **Posts/Week logic corrected** — under 2/week now flags 🐌 (inactive),
  over 7/week flags 🔥 (spammy). Previously only flagged high-frequency,
  backwards from actual intent.
- **Benchmark reference modal** — click "📖 Open Full Benchmark Reference"
  in the Engagement Color Key panel for the full engagement-by-follower-size
  table and posting frequency bands, always available to reference.
- **Username collector script embedded** — no more digging through chat
  history; it's in a panel on the dashboard now with a Copy button.

## Updating an existing install (round 2)
Run `sql/migration_002_posting_consistency.sql` in phpMyAdmin to add the new
columns. Existing snapshot rows won't have Posts/Week data retroactively —
only new imports going forward. Re-import the same Apify file for a profile
if you want it recalculated cleanly with pinned posts excluded.

Also run `jobs/requeue_invalid_niches.php` once (visit it in your browser)
to clean up any profiles that got a garbage/reasoning-dump niche assigned
before the AI response validation fix — this clears those and re-queues them.

## What's new in this round
- **Pinned posts excluded** from every calculation (avg likes/comments,
  engagement rate, Posts/Week) — they're hand-picked best-performers, not
  representative of normal activity
- **Posts/Week column** — flags ⚠️ if a profile posts more than 7x/week
- **AI niche responses validated** — reasoning-dump garbage and non-answers
  ("none", "n/a") are now rejected and retried with the next model instead
  of being saved as a fake niche
- **Archive logic fixed** — bulk archive now requires failing BOTH
  thresholds (AND), not just one (OR)
- **Archived tab bug fixed** — it no longer re-applies the Min
  Followers/Engagement filter to itself, which was hiding archived profiles

## Updating an existing install
If you already ran the original `schema.sql` before this update, run
`sql/migration_001_archive.sql` in phpMyAdmin to add archive support without
losing any data. Fresh installs can just run `schema.sql` as normal — it
already includes the archive columns.

## What's new in this update
- **Active / Archived tabs** on the dashboard
- **Archive button** on the filter panel — archives every ACTIVE profile
  currently below your Min Followers / Min Engagement numbers (confirms
  before running, nothing is deleted, just flagged)
- **Per-row Archive / Restore button** in the Actions column
- **Sorting fixed** — clicking any column header now actually sorts by that
  column server-side (previously always sorted by engagement rate)
- **Table state persists** — page, sort, and page-length survive a refresh;
  your Min Followers / Min Engagement inputs are saved to the browser too

## Notes
- Nothing is ever deleted. Every import creates a new "snapshot" — re-importing the same
  username later just adds to its history instead of overwriting anything.
- The Min Followers / Min Engagement filters don't change any data — they just decide
  what's currently shown in the table. Change the numbers any time.
- Free OpenRouter models are rate-limited (~20 requests/min, ~200/day account-wide),
  which is why the background job only processes a small batch each run. If the queue is
  large, it'll clear gradually rather than all at once — check the "Pending Niche (AI)"
  stat card to watch it shrink over time.
- `ai_model_log` table records every OpenRouter attempt per profile — worth checking
  after a few days to see which free models are actually succeeding vs constantly failing.
