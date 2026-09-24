# Hand-off: Creator Vetting & Outreach Platform

This document is written for whoever (or whichever AI session) picks this
project up next, with zero prior context. Read this first; it points you
to the deeper technical reference (`README.md`) for anything you need to
go further into.

## Who this is for and what it does

Built for Farhan, a digital-product and creator-monetization
entrepreneur running multiple ventures. This platform finds Instagram
and YouTube creators who look under-monetized, scores them with AI, and
pushes the promising ones into Flozy (a CRM) for outreach. Everyone —
regardless of which platform they came from — ends up in the same Flozy
pipeline; outreach sequencing itself is handled entirely inside Flozy,
not in this app.

Stack: local XAMPP (PHP/MySQL), no framework, plain vanilla JS + jQuery
DataTables on the frontend. Two Apify accounts' worth of scrapers feed
the data in; Gemini (free tier) does the AI scoring; OpenRouter (free
tier) handles a smaller Instagram niche-classification job.

## Architecture: two parallel pipelines, one shared destination

```
Instagram pipeline                    YouTube pipeline
(public/index.php)                    (public/youtube.php)
       |                                     |
  Apify scraping                       Apify scraping
       |                                     |
  Gemini verification                  Gemini scoring +
  + personalization                    Comment Insight
       |                                     |
       |------------------> Flozy <----------|
         (flozy_leads /              (youtube_flozy_leads,
          Instagram tables)           its own bridge table)
```

**Deliberately NOT a shared/unified schema.** Instagram and YouTube
creators don't fit the same shape (followers vs. subscribers,
engagement % vs. views/sub ratio, different scoring inputs entirely).
Retrofitting the mature, working Instagram pipeline to handle a second
platform badly was judged riskier than a clean parallel build. The
**only** shared code path is the very last step — pushing to Flozy — and
even that uses YouTube's own bridge table, never touching Instagram's
`flozy_leads`.

## Setup from scratch

1. Copy the `creator-db` folder into `C:\xampp\htdocs\`.
2. Import `sql/schema.sql` in phpMyAdmin (creates the base `creator_db` database).
3. Run every numbered migration in `sql/` **in order**, `migration_001` through the highest number present — each is additive, none should be skipped.
4. Fill in API keys:
   - `config/openrouter.php` — free key from openrouter.ai (Instagram niche classification)
   - `config/gemini.php` — free key from Google AI Studio (used by BOTH pipelines' AI scoring — see the shared-quota warning below)
   - Apify keys are added through the Instagram dashboard's Settings page (`apify_keys` table), not a config file — same key pool serves both pipelines
   - `config/flozy.php` — your Flozy API key, plus default pipeline stage names (confirm these match your actual Flozy account's stage names)
5. Instagram dashboard: `public/index.php`. YouTube dashboard: `public/youtube.php` (linked from the Instagram one).
6. Optional: set up the Windows Task Scheduler background job for Instagram's niche-classification queue — see `README.md` step 5 for the exact command.

## File map

```
config/          - API keys, actor slugs, tunable-but-not-live-editable defaults
sql/             - every migration, numbered, run in order; schema.sql is the base
includes/        - shared PHP logic (scraping, scoring, Flozy push helpers)
api/             - one file per endpoint, called via fetch() from the dashboards
public/          - the actual pages: index.php (Instagram), youtube.php (YouTube),
                   settings.php, content_studio.php
```

Instagram-specific `includes/`: `flozy_client.php`, `quality_score.php`,
`verification_scrape.php`, `gameplan_match.php`, `gameplan_storage.php`,
`email_extraction.php`, `keyword_rules.php`, `niche_queue_processor.php`,
`openrouter_client.php`, `gemini_client.php` (shared with YouTube),
`message_generation.php`, `prompt_engine.php`.

YouTube-specific `includes/`: `youtube_scrape.php` (all 3 Apify stages +
hashtag discovery), `youtube_scoring.php` (the 5-bucket AI score),
`youtube_comment_insight.php` (separate additive scoring),
`youtube_flozy.php` (its own Flozy push, not sharing Instagram's).

## Current status — what's actually confirmed working vs. what's untested

**Confirmed working with real usage:** Instagram's full pipeline
(scraping to verification to personalization to Flozy push) has been in
production use across 35+ build rounds — see `README.md`'s round-by-round
history for the full story. YouTube's scoring has been described by the
user as "insightful" after real test runs; the Flozy push has been
confirmed running; dual email (regex + manual business email) is working.

**Recently fixed:** Follow-up generation was fixating on the same
angle/topic on every attempt for a given lead — the AI had no memory of
what it had already sent, since the prompt fed it identical stored
content every call. Fixed via `migration_034` — see README's "Bug fix"
section near the top for the full root-cause writeup. Worth confirming
with real usage that follow-ups now vary meaningfully across attempts.

**Not yet proven at real scale:**
1. **Hashtag discovery** (YouTube) — built against the actor's confirmed
   input schema, but one input value (`socials: ["youtube"]`) is an
   inference from the output's own field naming, not confirmed from the
   schema docs directly. Test with a small hashtag-only round before
   trusting it.
2. **Comment sort order** (YouTube Comment Insight) — the comment
   scraper's own docs don't document a sort parameter, so it's unknown
   whether it returns "Top" comments or just default order. Worth
   checking real output.
3. **A full-scale round** — the original plan's "Round 1" (6 niches,
   30-50 creators each) hasn't been run yet. Everything YouTube-side has
   been validated on smaller test batches so far.

## Settings reference (all live-editable in-app, never hardcoded)

**Instagram** (`public/settings.php`): brand voice, prompt templates,
gameplan match prefix, and more — see that page directly, it's
self-documenting.

**YouTube** (`public/youtube.php` -> Settings):
- `video_sample_count` (default 5) - recent videos sampled per channel for scoring
- `comments_per_video` (default 15) - comments pulled per video for Comment Insight
- `gemini_call_delay_seconds` (default 4) - **shared quota warning**: this paces calls to the SAME Gemini key Instagram uses. A careless batch can block Instagram's AI features for the rest of the day, not just YouTube's.
- `scoring_batch_limit` (default 10) - cap per automatic round-wide scoring run (explicit selections via shift-click "Score Selected" deliberately bypass this cap)

## The YouTube pipeline in one glance

```
Create a Round (niche + keywords, optional hashtags)
    -> Run Discovery (Apify search -> channel detail -> subscriber filter -> email regex)
    -> Run Scoring (sample videos -> Gemini 5-bucket score + verdict, paced/batched)
    -> manual Qualify / Reject / Reset
    -> optional: Get Comment Insight (separate score, never touches the main total)
    -> Push to Flozy (Lead + Contact + Opportunity + tasks, one call)
```

A **round** is the niche experiment container — no separate niche
taxonomy table exists; the round's own search keywords define its niche.
Compare rounds side by side via the "Compare Rounds" button to see
which niche actually yields reachable, engaged, under-monetized
creators — that comparison is the actual point of the whole round
concept, straight from the original planning conversation.

**Rejection Audit** ("📋 Rejection Audit" button) is deliberately
cross-round, not scoped to the currently-selected round — the point is
spotting whether the AI's reject calls hold up consistently across
niches. Filterable AI-rejected vs. manually-rejected.

**Cross-platform contact dedup**: before either pipeline's Flozy push
completes, it checks the OTHER pipeline's already-pushed leads for a
matching email. Warns (toast/console), never blocks — you may genuinely
want the same person tracked as a lead from both platforms.

Full technical detail — exact schema, the 5-bucket scoring rubric, every
Apify actor used and its confirmed cost/maintenance status, the
Monetization Gap bucket's known soft-signal limitation — lives in
**`README.md`'s "YouTube Pipeline" section** (near the top, before the
Instagram round history begins). Read that before making any schema or
scoring changes.

## Where to find deeper history

`README.md` contains a full round-by-round build history for the
Instagram pipeline (35 rounds), each with what shipped, what was a
judgment call vs. an explicit confirmed decision, and why. If you're
about to change something Instagram-side, search that file for the
relevant feature name first — there's likely already documented
reasoning for why it works the way it does.

## Working style this project has established (worth continuing)

- **Never edit an already-applied migration** — always add a new
  numbered one, even for a one-line fix.
- **Flag judgment calls explicitly**, in code comments and in chat —
  don't silently decide something ambiguous and move on.
- **Verify third-party field names/schemas against real sources**
  (official docs, actual API responses) before building against them —
  this project has been burned twice by guessed field names
  (`isMonetized` that never existed; actor input params assumed rather
  than confirmed).
- **Only Apify actors "Maintained by Apify"** (a real badge on the
  actor's own store page — check for it, don't assume from publisher
  name alone) are allowed, per an explicit rule set mid-project.
- **Pace and cap anything hitting the shared Gemini key** — Instagram
  and YouTube's AI features can starve each other on the free tier if a
  batch job doesn't self-limit.
- Ship in small, testable batches with a zip after each — this project
  has consistently preferred "confirm, build, verify, ship" over big
  unverified drops.
