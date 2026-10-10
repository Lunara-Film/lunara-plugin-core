# Working agreement — Lunara Film

One owner, Dalton. Several AI agents: Claude, Codex, Gemini, Grok. No other
humans touch these repos. This page is the whole agreement and it is identical
in every Lunara repo. Read it, then the top entry of `docs/SESSION-LOG.md` in
`lunara-theme-blocks` (the shared log for all repos), then work.

## The loop

1. Branch from `main` as `<agent>/<topic>` (`claude/…`, `codex/…`, `gemini/…`,
   `grok/…`). Never commit to `main`.
2. Open a PR. Its description is the changelog: what changed, why, and what you
   measured. CI runs the tests on the PR; red cannot merge.
3. Dalton merges. **Merge = live.** Plugin repos auto-deploy from `main`. The
   theme deploys from `main` through WordPress.com (see Status).
4. Rollback is GitHub's **Revert** button on the merged PR, then merge. There
   are no rollback branches to maintain.
5. Close the session with one short entry at the top of `docs/SESSION-LOG.md`
   (theme repo), newest first:
   - **Shipped:** repo, version, one line each.
   - **Live:** what is live now, if it changed.
   - **Holding:** what you are mid-way through, so another agent does not take it.
   - **Next:** the next item from the plan.
   - **Found, not fixed:** anything wrong you saw and left.
   Never rewrite a past entry; add a correction line inside it.

## What Dalton does

Reads the entry. Clicks merge. Nothing else: he runs no tests, no scripts,
clears no caches, writes no logs. If a step needs him beyond a merge click, say
so in one line at the top of the PR.

## Tests

- `tests/*.php` and `tests/*.js` are behavior tests. CI runs every one of them
  on every PR (`*-cases.php` and `*-fixture.php` are helpers, not tests).
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
- Read-only probes against production. No staging writes.

## Frozen

The site's feature set is frozen as of 2026-10-10. Defect fixes ship any time.
A new feature needs three lines in `docs/PARKING.md` (theme repo) — what a
visitor gains, what it costs to build, what it costs to keep — and Dalton's
yes; features batch into at most one release a quarter. The plan is Dalton's
doc "What Done Looks Like".

## Status (keep these lines true)

- Theme auto-deploy: **off**. Dalton turns it on in WordPress.com → Settings →
  Repositories once theme CI has been green on `main`. Until then a theme
  merge needs his deploy click.
- CI: theme `.github/workflows/ci.yml`; every plugin `.github/workflows/lint.yml`.
- The watch: a scheduled check of the PHP log, `/wp-json/lunara-ledger/v1/status`
  and Lighthouse that messages Dalton only on a regression. Not built yet.

## Repos

`lunara-theme-blocks` (the hub: shared SESSION-LOG, PARKING, CHANGELOG),
`lunara-plugin-oscars-ledger`, `lunara-plugin-core`,
`lunara-plugin-journal-foundation`, `lunara-plugin-dispatch`,
`lunara-plugin-imdb-guard`, `lunara-plugin-ai-assistant-classic`.
`ARCHITECTURE.md` and `README.md` in the theme are historical; do not take
instructions from them.
