# AUDIT REPORT: Frontend Slot Banner Layout Regression

Date: 2026-05-10 (UTC)

## Scope & Method

This audit compared the current `main` lineage at `ce4dc91` against the pre-logo/pre-offer-type frontend baseline (`13d838c`) and reviewed recent PR-era commits including PR #16 (`f779dc4`) and PR #17 (`6e30a55` merge lineage).

Commands used:

- `git log --oneline --decorate -n 30`
- `git log --oneline -- assets/css/slot-banner.css assets/js/slot-banner.js tmw-cr-slot-sidebar-banner.php`
- `git diff 13d838c..HEAD -- assets/css/slot-banner.css assets/js/slot-banner.js tmw-cr-slot-sidebar-banner.php`
- `git show f779dc4 -- tmw-cr-slot-sidebar-banner.php`
- `git show --name-only --stat f779dc4 6e30a55 8e7149f 243e5b5`
- `rg -n "slot-test|SPIN THE REELS|TRY YOUR FREE SPINS|Trusted Cam Models|Find More Sexy Girls|reel|cta|subtitle|title" tmw-cr-slot-sidebar-banner.php includes assets/js/slot-banner.js assets/css/slot-banner.css`

## Executive Finding

### Suspected regression commit/PR

**No frontend layout regression is introduced by PR #16 or PR #17 in plugin CSS/JS/markup source.**

- PR #16 (`f779dc4`) touches `tmw-cr-slot-sidebar-banner.php`, but only adds `allowed_offer_types` setting sanitization/defaults.
- PR #17 (`6e30a55` and merge `ce4dc91`) does not modify frontend renderer/CSS/JS paths.

### Most likely root cause category

1. **Content/config drift (headline/subheadline/cta text) rather than frontend code regression**, and/or
2. **External CSS collision in the live environment** due generic selectors/IDs (`#container`, `#spin`, `.col`, `.icon`) being non-namespaced and vulnerable to theme/page CSS.

## Required Confirmations

## 1) Compare current main vs previous stable layout

Compared baseline `13d838c` to `HEAD` (`ce4dc91`) for frontend banner files:

- `assets/css/slot-banner.css`: **no diff**
- `assets/js/slot-banner.js`: **no diff**
- `tmw-cr-slot-sidebar-banner.php`: only `allowed_offer_types` settings changes (no layout HTML/CSS class changes)

## 2) Recent commits/PRs touching frontend renderer paths

Reviewed commits touching audited files:

- `f779dc4` (PR #16 branch): touched `tmw-cr-slot-sidebar-banner.php` (settings only)
- No recent commits beyond initial commit touch `assets/css/slot-banner.css` or `assets/js/slot-banner.js`
- `8e7149f`/`243e5b5` logo-related work is admin/repository-only for mapping/data, not frontend layout renderer/CSS

## 3) Commit/PR introducing visual regression

**Not attributable to PR #16 or PR #17 via plugin frontend source changes.**

There is no commit in the audited PR window that changes frontend slot banner structure/styling/JS behavior in this repository.

## 4) Exact files/selectors/functions involved

### Files confirmed unchanged for layout during recent PRs

- `assets/css/slot-banner.css` (unchanged since initial commit)
- `assets/js/slot-banner.js` (unchanged since initial commit)

### Potentially fragile selectors contributing to environment-specific breakage

- CSS: `#container`, `#container .outer-col`, `#container .outer-col .col`, `#spin`-adjacent behavior through button id usage in JS, `.icon`
- JS queries: `banner.querySelector('#container')`, `banner.querySelector('#spin')`, and class-based column/icon markup assumptions

These are not newly introduced in PR #16/#17, but can be overridden/collide with theme/global styles and produce exactly the reported “collapsed/missing text or sizing” symptoms.

## 5) Did PR #16 or PR #17 change frontend layout?

**No.**

- PR #16 adds and sanitizes `allowed_offer_types`; no slot HTML/CSS/JS layout edits.
- PR #17 final merge retains that settings change and similarly does not alter frontend layout files.

## 6) Did “frontend logo display” prompt/PR change live layout?

**No direct evidence in this repo.**

Logo mapping/display changes were implemented in admin/repository paths and not in banner layout CSS/JS renderer output.

## Before vs After Behavior (code-level)

### Before (stable reference in code)

- Frontend banner renders headline/subheadline if settings are non-empty.
- CTA button text uses selected offer `cta_text` or fallback configured `cta_text`.
- 3 reels generated in same `.outer-col > .col` structure.

### After (current HEAD code)

- Identical frontend behavior in renderer/CSS/JS paths.
- Additional settings key `allowed_offer_types` can reduce candidate offers, which may change which fallback offer text appears, but **does not alter layout structure/styling**.

## Recommended Rollback/Fix Plan (without redesign)

1. **Do not rollback PR #16/#17 for layout reasons** (not root cause by code diff).
2. Capture live `/slot-test/` computed styles and compare against a clean environment; specifically inspect collisions on `#container`, `.col`, `.icon`, `#spin`, anchor/button typography, and inherited `font-size/line-height/display`.
3. Validate live option values for:
   - `headline` (expected: “Find More Sexy Girls”)
   - `subheadline` (expected: “Trusted Cam Models”)
   - `cta_text` (expected: “TRY YOUR FREE SPINS”)
4. If issue reproduces only with theme/global CSS, apply minimal namespacing hardening in a separate fix PR (not in this audit PR), e.g. replace generic IDs/classes with banner-prefixed selectors while preserving exact visual design.

## Verification Checklist

- [x] Confirmed no PHP/CSS/JS frontend code changed in this audit.
- [x] Audit identifies likely source category: not PR #16/#17 code regression; likely runtime config drift and/or CSS collision external to audited commit window.
- [x] `/slot-test/` frontend output remains unchanged by this audit PR (documentation-only change).

