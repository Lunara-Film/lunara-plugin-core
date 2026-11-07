# Working agreement — Lunara Film

One owner, Dalton. Several AI agents: Claude, Codex, Gemini, Grok. No other
humans touch these repos. This working agreement is shared across the seven
active website repos; Theme-specific CI routing is described under Tests.
Read it, then the top entry of `docs/SESSION-LOG.md` in
`lunara-theme-blocks` (the shared log for all repos), then work.

## The loop

1. Branch from `main` as `<agent>/<topic>` (`claude/…`, `codex/…`, `gemini/…`,
   `grok/…`). Never commit to `main`.
2. Open a PR. Its description is the changelog: what changed, why, and what you
   measured. CI runs the tests on the PR; red cannot merge.
3. Dalton's confirmation authorizes completion of the requested implementation,
   including task-branch commits, PRs, verified merge, manual deployment and
   live verification. Do not ask him to confirm the same release twice or to
   perform routine merge/deploy clicks. Honor an explicit hold or no-deploy
   instruction. Follow the checks below; auto-deploy stays off. A merge is
   not evidence that deployment happened.
4. Rollback is GitHub's **Revert** button on the merged PR, then merge and
   explicitly deploy the reviewed revert. A revert merge alone is not live.
5. Close the session with one short entry at the top of `docs/SESSION-LOG.md`
   (theme repo), newest first:
   - **Shipped:** repo, version, one line each.
   - **Live:** what is live now, if it changed.
   - **Holding:** what you are mid-way through, so another agent does not take it.
   - **Next:** the next item from the plan.
   - **Found, not fixed:** anything wrong you saw and left.
   Never rewrite a past entry; add a correction line inside it.

## What Dalton does

Confirms the goal once and reviews substantive editorial/design decisions.
That confirmation authorizes the requested implementation through release.
Agents own tests, PRs, merges, deployment, live verification and release
records. Dalton does not need to run scripts, clear caches or click Merge.
Ask only for a missing substantive decision or access that actually blocks
the task, not permission already granted here or in the conversation.

## Dalton's confirmation — effective 2026-10-23

Dalton's confirmation of a website implementation task (for example "yes",
"go ahead", "do it" or "begin/create it") authorizes the requested work through
task-branch commits, PRs, passing checks, agent merge, manual deployment and
live verification. Do not ask for a second deployment confirmation or require
a new approval naming the final version. Reuse the confirmation in the current
conversation; instructions to hold, prepare only, leave a draft, or not deploy
take precedence. Ask only about a real missing decision, access, changed
destination or work beyond that confirmed scope.

Keep branch protections, required CI and exact-head PR merges. Manual
deployment means the agent uses the existing host action; it does not mean
Dalton must click it. Auto-deploy stays off unless he specifically changes it.
A confirmation never makes a merge evidence of deployment: verify and report
both states separately.

## Agent merge authority — effective 2026-10-17

This replaces the former "Dalton merges" rule for Claude, Codex, Gemini, Grok
and other authorized agents. Follow the shared
[merge policy](https://github.com/Lunara-Film/lunara-theme-blocks/blob/main/docs/AGENT-MERGE-POLICY.md).

- Confirm the repository, intended base, PR number and exact remote head SHA.
  Review the final diff against current `main`, including any conflict fixes.
- Complete relevant verification and wait for all applicable CI and required
  checks to pass for the final candidate. Resolve review threads. Preserve
  protections; never use an administrator bypass, force push or direct-main
  commit to evade the PR process.
- Merge the verified PR using its exact head SHA as a guard. Verify GitHub's
  merged commit and resulting main checks, then report merged and deployed
  states separately. Do not merge branches back and forth to fix an
  ahead/behind count; compare their actual changes.
- Preserve unrelated drafts and unfinished work. Dalton's confirmation covers
  the requested scope through deployment; it does not approve unrelated work.
  An explicit instruction to hold a merge or deployment takes precedence.
- When resuming an older branch, fetch and read the current agreement on
  `origin/main`; old runbooks, handoffs and logs do not restore a manual-merge
  requirement.

## Tests

- `tests/*.php` and `tests/*.js` are behavior tests. Full CI runs every one of
  them (`*-cases.php` and `*-fixture.php` are helpers, not tests).
- Theme CI validates routing on every PR and main push. Only the narrow
  [documentation-only scope](docs/CI.md) skips runtime/browser jobs. Code,
  tests, dependencies, workflow changes and uncertain comparisons require
  the full suite and every browser shard. Other repositories retain their
  existing CI until their own workflows explicitly adopt a scoped exception.
- In every repo, a push to `main` skips the full jobs only when that exact
  tree already passed them on a same-repository PR
  ([main push reuse](docs/CI.md#main-push-after-a-tested-pull-request)).
  Any other push runs the full workflow.
- Change behavior → add or change a test. Remove behavior → delete its test.
- No string-pin tests (a test that only checks a file contains some text).
  The PowerShell suite was retired on 2026-10-10 for that reason.
- Tests never pin a version number. Bumping a version touches one file per repo.
- `tests/ci-skip.txt` lists tests CI does not run, with the reason. Fix or
  delete them; do not let the list grow.

## Rules that do not expire (each one is a scar)

- Never clear a cache to make a release look right. If a release needs a flush
  to be correct, the release is wrong (3.2.48). A purge as the last step of a
  deploy is part of the deploy, not a fix.
- Adding a key to a cached payload means bumping the cache version and clearing
  the retired key in the flush routine (3.2.53, twice).
- Licensed Klim Tiempos font files are never committed. They live only in
  `/wp-content/uploads/lunara-fonts/v1/`.
- When a plugin and the theme both change, the plugin ships first.
- In the theme, `inc/` is live; `functions.php` carries dead
  `function_exists()` duplicates. Confirm which definition runs before editing.
- Two surfaces that render the same data get checked together.
- Theme is the single authority for public design. Read `DESIGN.md`,
  `docs/PUBLIC-GEOMETRY.md` and `docs/REPOSITORY-ALIGNMENT.md` in
  `lunara-theme-blocks` (sibling checkout or the Theme repository on GitHub).
  Square structural corners apply to all public pages, blocks and future
  components. Update owning rules and relevant geometry checks.
- Generated public markup, including AI Assistant Classic suggestions, must
  use semantic content and existing Theme classes/tokens. Do not bundle style
  tags, inline layout/corner rules or a separate public design system. Private
  editor assets must stay scoped to administration.
- Private Review Workspace, Image Studio, Debrief Studio, Journal Desk,
  Control Tower and administrative IMDb Guard/AI Assistant UI are exempt from
  public square geometry. Visitor output and public-content previews are not.
- Read-only probes against production. No staging writes.

## Unison checklist (plugins and theme move together)

Every plugin that prints public markup follows the theme's measured rules.
Check each line before a plugin release, and check the plugin side again
after a theme release that changes one of them (2026-11-07, theme 3.2.155,
Oscars Ledger 2.8.28, Core 0.8.18).

1. **No CDN upscale.** Every `srcset` candidate stops at the source picture's
   measured size. A `resize=` crop asks the image CDN to scale up past the
   original, so a candidate list is capped by the attachment's metadata
   (theme `lunara_image_cdn_crop_srcset()`, Ledger
   `get_attachment_crop_srcset()`). A URL with no attachment behind it is
   never trusted for a size.
2. **No inline backgrounds.** A `style="background-image: url()"` downloads
   before the stylesheet can hide or shrink it. Plates are typographic; a
   backdrop is an `<img>` with candidates or a 320px CDN source for a blur.
3. **Sizes match the measured frame.** Each image slot carries a `sizes`
   value measured at the breakpoints (theme `lunara_image_sizes_for()`,
   Ledger `poster_sizes_for()`), never a viewport width unless the frame is
   one. An eager image never carries a leading `auto, ` (Chromium then
   resolves it to 100vw); only a lazy image keeps WordPress's `auto`.
4. **Cache keys move with the markup.** A change to cached rendered markup
   bumps its key or generation (theme showcase `_v5`, Ledger page-store
   generation); an import that changes what a page shows retires the stored
   page. Every public route sends the same edge lifetime,
   `max-age=300`.
5. **13px floor, 44px targets.** No functional text below 13px
   (`0.8125rem`); controls are 44px tall. Inline text links inside cards
   take 4px of vertical padding, which moves no line; a flex-item link needs
   a decision with pictures before it grows.
6. **CSS diet.** A route loads the stylesheet it uses. Dead motion rules
   (hover zooms, sheens, transform/filter transitions) are deleted, not
   neutralised; motion is transform/opacity only and honours
   `prefers-reduced-motion`.
7. **Contracts both ways.** When a plugin hands the theme a picture, it hands
   the attachment id and the measured size, not only a URL (Core
   `resolve_slot()`); when the theme sizes a plugin frame (the Oscars profile
   hero), the plugin's `sizes` follows the theme's breakpoints and the
   plugin's fixture carries the theme rule that sets the frame.
8. **Tests measure, never pin.** A rendered route's images are checked in the
   browser for eager/no-auto, candidates and sizes against the painted frame
   (Ledger `tests/ledger-routes-browser.js`); a stylesheet change is proven
   pixel-neutral with before/after screenshots.

## Frozen

The site's feature set is frozen as of 2026-10-10. Defect fixes ship any time.
A new feature needs three lines in `docs/PARKING.md` (theme repo) — what a
visitor gains, what it costs to build, what it costs to keep — and Dalton's
yes; features batch into at most one release a quarter. The plan is Dalton's
doc "What Done Looks Like".

## Status (keep these lines true)

- Theme and plugin auto-deploy: **off**. Agents manually deploy verified work
  under Dalton's task confirmation; do not require a second release approval
  or turn auto-deploy back on as part of a code fix.
- CI: theme `.github/workflows/ci.yml`; every plugin `.github/workflows/lint.yml`.
- The watch: a scheduled check of the PHP log, `/wp-json/lunara-ledger/v1/status`
  and Lighthouse that messages Dalton only on a regression. Not built yet.

## Manual release stages

Prepare a branch and PR; run the repository's required checks on the final
candidate. The integrating agent merges and deploys within Dalton's confirmed
task without another release approval, unless he explicitly held that step.
Record the confirmation and merged commit. Verify the existing WordPress.com
repository connection, destination and manual mode; deploy changed runtime
plugins before the Theme. Confirm deployment completion, exact live version and
affected visitor behavior before calling it live. Documentation/test-only
changes need a merge but no runtime version bump or standalone deployment.
Do not enable auto-deploy, create a staging environment or alter production
settings as part of this process. Historical runbooks do not override this
agreement. See the Theme's repository alignment document for ownership.

## Repos

`lunara-theme-blocks` (the hub: shared SESSION-LOG, PARKING, CHANGELOG),
`lunara-plugin-oscars-ledger`, `lunara-plugin-core`,
`lunara-plugin-journal-foundation`, `lunara-plugin-dispatch`,
`lunara-plugin-imdb-guard`, `lunara-plugin-ai-assistant-classic`.
The secondary `academy-awards-sql`, `lunara-engine` and `lunara-world-site`
repositories inherit the same merge authority through their scoped agreements;
they are not added to the active WordPress deployment set.
`ARCHITECTURE.md` and `README.md` in the theme are historical; do not take
instructions from them.
