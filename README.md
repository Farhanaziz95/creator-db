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

## ⚠️ IN PROGRESS — Round 28 (incomplete, handed off mid-build)

This chat hit its practical limit and is being continued in a new Claude
Project. Here's the exact state of what you asked for, so nothing gets
silently lost or redone:

### ✅ Done
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
- **Outreach flag — migration + endpoint only** —
  `sql/migration_023_outreach_flag.sql` (adds `flozy_leads.outreached_at`)
  and `api/toggle_outreach.php` are built and functional standalone, but
  **NOT wired into the frontend at all yet** — no toggle button, no badge,
  no filter dropdown.

### 🚧 Started, not finished
- **Outreach filter in `api/profiles.php`** — only this one line exists:
  `$outreachFilter = $_GET['outreach_status'] ?? '';` — the actual SQL
  filter logic, the `outreached_at` SELECT column, and everything on the
  frontend (filter dropdown, toggle button, visual badge) still needs
  building. This was the exact point of interruption.

### ❌ Not started
- **Direct link to Flozy lead** — needs you to check Flozy's real lead-detail
  URL pattern first (open a lead in Flozy, copy the URL) — can't guess this
  one, same policy as everything else API/URL-related this session.
- **Table-refresh bug investigation** — audited the functions I could find
  and they already had the correct `reload(null, false)` fix applied, so
  the bug (if still happening) needs a specific repro from you: which
  button, which tab, exactly what doesn't update — to actually pin it down
  rather than guess broadly again.

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
