-- Content Studio: a SEPARATE feature from lead outreach — assembles
-- Reel/Story/Carousel generation prompts (Foundation + Master + Campaign
-- Rule + your brief) for pasting into Iman's tool. No AI call happens
-- here — this is pure structured assembly, same underlying pattern as
-- the lead-outreach prompt_templates system, applied to a new use case.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

-- Foundation: just Color Rules (Brand Voice already exists from round 26
-- and is reused here too — no need to duplicate it).
CREATE TABLE IF NOT EXISTS content_foundation (
    id INT PRIMARY KEY DEFAULT 1,
    color_rules TEXT
);
INSERT IGNORE INTO content_foundation (id, color_rules) VALUES
(1, 'Primary Colors: Purple, White, Black. Accent colors: minimal, used only when necessary. Overall aesthetic: premium, minimal, modern, high contrast, clean.');

CREATE TABLE IF NOT EXISTS content_master_prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('reel', 'story', 'carousel') NOT NULL UNIQUE,
    prompt_text LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS content_campaign_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('reel', 'story', 'carousel') NOT NULL,
    campaign_stage ENUM('awareness', 'consideration', 'conversion') NOT NULL,
    rule_text LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY content_stage (content_type, campaign_stage)
);

-- Optional per-content-type structure guidance. Only Carousel has one so
-- far (your "rotate structures naturally" rule) — nullable so Reel/Story
-- just skip this section until/unless you add one later.
CREATE TABLE IF NOT EXISTS content_structure_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('reel', 'story', 'carousel') NOT NULL UNIQUE,
    rule_text LONGTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Every generated brief gets logged — auto-save, and a history to revisit
-- or duplicate as a starting point for a new one.
CREATE TABLE IF NOT EXISTS content_briefs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('reel', 'story', 'carousel') NOT NULL,
    campaign_stage ENUM('awareness', 'consideration', 'conversion') NOT NULL,
    theme VARCHAR(255),
    core_idea TEXT,
    angle TEXT,
    final_prompt LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===================== STORY =====================
-- Color block replaced with {color_rules} so editing Foundation once
-- propagates everywhere, instead of retyping colors in 3 separate places.

INSERT INTO content_master_prompts (content_type, prompt_text) VALUES
('story', '======================================================
ROLE
======================================================

Generate Instagram Stories that complement the Instagram content strategy.

Stories are NOT mini carousel slides.

Stories are quick interactions designed to increase engagement, curiosity, profile visits, trust and conversions depending on the campaign.

Every story should feel native to Instagram Stories.

======================================================
VISUAL STYLE
======================================================

When B-roll is not suitable, suggest a clean branded graphic.

Avoid quote backgrounds.

Avoid text-only stories.

Maintain a premium minimalist aesthetic.

{color_rules}

======================================================
CONTENT RULES
======================================================

Keep text short.

Maximum:
One headline
One supporting sentence.

Stories should be readable in under 3 seconds.

Do NOT overload the story.

Generate ONE story only.

======================================================
META BUSINESS SUITE
======================================================

Stories must work when scheduled through Meta Business Suite.

Do NOT use:

- Poll Sticker
- Question Sticker
- Emoji Slider
- Quiz Sticker

Instead convert interactions into text.

Examples

Instead of Poll:
"React ❤️ for YES / 🔥 for NO"

Instead of Question Box:
"DM me your answer."

Instead of Emoji Slider:
"Drop a 🔥 if you agree."

======================================================
OUTPUT FORMAT
======================================================

Visual Suggestion
Headline
Supporting Text
Interaction
CTA (Optional if needed)

Every story should have ONE primary objective only:
React, Follow, DM, Think, or Build Curiosity.

Never try to achieve multiple objectives in the same story.');

INSERT INTO content_campaign_rules (content_type, campaign_stage, rule_text) VALUES
('story', 'awareness', 'Campaign Goal

Create curiosity.
Make viewers stop.
Encourage reactions.
Encourage profile visits.
Occasionally ask people to support the account by following.

Preferred interactions

- React ❤️
- React 🔥
- True / False
- Guess
- Did You Know?
- Follow Reminder
- Tomorrow I''ll explain...
- Curiosity Gap

Do NOT teach everything. Create curiosity instead.'),

('story', 'consideration', 'Campaign Goal

Challenge beliefs.
Encourage thinking.
Increase engagement.

Preferred interactions

- This or That
- Which One?
- Agree / Disagree
- Finish the Sentence
- Would You Rather
- Myth vs Reality
- Prediction
- Common Mistake
- Perspective Shift

Do not become educational. The goal is discussion.'),

('story', 'conversion', 'Campaign Goal

Build trust.
Generate conversations.
Encourage DMs.
Move followers closer to becoming clients.

Preferred interactions

- DM me...
- Ask me...
- Want the checklist?
- Reply with...
- FAQ
- Mini Framework
- Quick Tip
- Objection Handling
- Soft CTA

Never sound salesy. Keep the tone helpful and professional.');

-- ===================== CAROUSEL =====================

INSERT INTO content_master_prompts (content_type, prompt_text) VALUES
('carousel', '======================================================
ROLE
======================================================

Generate an Instagram Carousel that aligns with the selected campaign objective.

The carousel should communicate ONE powerful idea.

It should encourage users to stop scrolling, swipe through the slides, save the post and naturally progress through the content funnel.

Do NOT try to explain everything.

Focus on clarity, curiosity and memorable insights.

======================================================
DESIGN RULES
======================================================

The carousel will be placed into pre-designed branded templates.

Do NOT describe detailed illustrations, people, environments or complex scene compositions.

Instead provide only:

- Slide Headline
- Supporting Copy
- Copy Hierarchy
- Highlight Words
- Suggested Icon (Optional)
- Suggested Simple Graph or Diagram (Optional)
- Layout Emphasis (Optional)

{color_rules}

Assume the visual system already exists. Never suggest visual styles outside this branding.

======================================================
CONTENT RULES
======================================================

Use the minimum number of slides required.

Target: 4-6 slides.

Each slide should communicate ONE idea.

Keep text concise. Avoid paragraphs.

Prefer:
Headline → One supporting sentence → Optional icon suggestion

Every slide should be readable in under 3 seconds.

Assume each slide has room for:
- One short headline
- One supporting sentence (Maximum 15 words)

Automatically shorten copy if necessary. Never overflow the layout.

======================================================
WRITING STYLE
======================================================

Writing should be: Clear, Confident, Intelligent, Analytical, Easy to scan.

Avoid: Fluff, Long explanations, Generic motivation, Clickbait, Fake urgency.

======================================================
OUTPUT FORMAT
======================================================

For each slide provide:
Slide Number
Headline
Supporting Copy
Highlight Words
Suggested Icon (Optional)
Suggested Simple Graphic (Optional)
Layout Emphasis (Optional)');

INSERT INTO content_campaign_rules (content_type, campaign_stage, rule_text) VALUES
('carousel', 'awareness', 'Campaign Goal

Increase awareness.
Help creators recognize a hidden problem.
Create an "Aha!" moment.
Teach ONE concept only.
Do not overwhelm with information.

Focus on:
- Reframes
- Hidden problems
- False assumptions
- Mindset shifts
- Educational insights

The final slide should end with a memorable realization rather than a CTA.'),

('carousel', 'consideration', 'Campaign Goal

Challenge existing beliefs.
Create curiosity.
Increase engagement.
Encourage discussion.

Use techniques like:
- Myth vs Reality
- Before vs After
- This or That
- Common Mistake
- Unpopular Opinion
- Perspective Shift
- Contrarian Thinking

Do not fully explain the solution. Encourage the audience to think differently.

The final slide should leave the reader wanting to learn more.'),

('carousel', 'conversion', 'Campaign Goal

Build trust.
Demonstrate expertise.
Move the audience closer to taking action.

Focus on:
- Frameworks
- Processes
- Mini Case Studies
- FAQs
- Common Objections
- Mistakes
- Actionable Tips
- Quick Wins

Keep everything educational. Never become overly promotional.

The final slide should naturally encourage the next action such as sending a DM, visiting the profile or learning more.');

INSERT INTO content_structure_rules (content_type, rule_text) VALUES
('carousel', 'Generate the carousel using the most appropriate storytelling structure for the selected campaign.

Do NOT force the same structure every time. Choose the structure that best communicates the idea.

Examples include:
- Problem → Reframe → Insight
- Myth → Reality
- Before → After
- Mistake → Better Approach
- Question → Answer
- Comparison
- Framework
- Timeline
- List
- Process
- Perspective Shift

Rotate structures naturally to avoid repetitive content.');

-- ===================== REEL =====================

INSERT INTO content_master_prompts (content_type, prompt_text) VALUES
('reel', '======================================================
ROLE
======================================================

Generate a short Instagram B-roll Reel.

The reel exists to stop scrolling and drive viewers to either:
- Read the caption
- Visit the profile
- Follow the account
- Start a conversation

The reel is NOT intended to teach everything. The caption contains the detailed value.

======================================================
VISUAL STYLE
======================================================

Prioritize cinematic vertical B-roll.

Examples:
- Laptop
- Typing
- Coffee
- Workspace
- Analytics Dashboard
- Phone
- White Desk
- Keyboard
- Browser Tabs
- Digital Product
- AI Interface
- Planning
- CRM
- Modern Office
- Minimal Motion Graphics

Use clean transitions. Keep visuals premium and minimalist.

{color_rules}

======================================================
VIDEO RULES
======================================================

Length: 8-15 seconds
Aspect Ratio: 9:16

Use one powerful hook. Avoid multiple ideas.

Keep on-screen text minimal.

Maximum: One Hook, One Closing Line. No long explanations.

======================================================
WRITING STYLE
======================================================

Hooks should be: Curiosity-driven, Contrarian, Analytical, Clear.

Avoid: Clickbait, Fake urgency, Generic motivation.

======================================================
CALL TO ACTION
======================================================

Rotate naturally between:
- Read the caption ↓
- Full breakdown below ↓
- Keep reading ↓
- The explanation is below ↓
- Here''s why ↓
- Most creators miss this ↓

Avoid repeating the same CTA every reel.

======================================================
OUTPUT FORMAT
======================================================

Visual Sequence
Hook
Supporting Text (Optional)
Closing CTA
Caption Idea');

INSERT INTO content_campaign_rules (content_type, campaign_stage, rule_text) VALUES
('reel', 'awareness', 'Campaign Goal

Create curiosity.
Introduce a problem.
Generate profile visits.

The hook should create an information gap. Do not explain the solution.

Encourage users to read the caption for the full explanation.'),

('reel', 'consideration', 'Campaign Goal

Challenge assumptions.
Present a surprising perspective.
Create discussion.

Use:
- Hot Takes
- Myths
- Comparisons
- Questions
- Contrarian Opinions

Leave enough curiosity for the caption to complete the story.'),

('reel', 'conversion', 'Campaign Goal

Build trust.
Demonstrate expertise.
Show quick wins.
Introduce simple frameworks.

Encourage profile visits, DMs or further learning without sounding sales-oriented.

The reel should spark interest. The caption should deliver the value.');
