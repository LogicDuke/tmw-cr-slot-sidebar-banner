# Audit: Disappearing live slot rotation offers after PR #98

## Scope
- No code changes; audit-only.
- Files audited:
  - `includes/class-offer-repository.php`
  - `admin/admin-page.php`
  - `tmw-cr-slot-sidebar-banner.php`
  - `assets/js/slot-banner.js`
  - `tests/run-tests.php`

## Key finding
Primary root-cause candidate is **configuration drift of `allowed_offer_types` back to default `['pps']`**, not JS rotation logic.

Why:
- Defaults force `allowed_offer_types` to `pps` when empty/unset.
- Type normalization in PR #98 correctly maps Fanvue-like payout enums to `revshare` / `revshare_lifetime`.
- `get_frontend_slot_offers()` hard-filters selected offers by intersection with allowed types; revshare offers are dropped with `reason=not_allowed_type` when only `pps` is allowed.
- JS chooses winners from full `state.offers` and does not cap to first 3.

## Pipeline trace (A)
1. Shortcode render path calls repository frontend pool builder and embeds JSON into `data-slot-offers`.
2. `get_frontend_slot_offers()`:
   - starts with selected IDs
   - applies type allowlist
   - applies status/approval, skipped exclusion, CTA validity, country, logo checks
   - only if selected pool is empty, falls back to full synced pool
   - ranks pool
   - top-up from override-only/legacy only when count < 3
3. Frontend JS parses `data-slot-offers` into `state.offers`, builds reel icons, and picks winner randomly from **all** `state.offers`.

## Backend inclusion/drop matrix (B)
Selected/manual-ready offer can be dropped by:
- `not_allowed_type`
- `inactive_or_unapproved`
- `invalid_cta`
- `country_blocked`
- `missing_logo`
- `unknown_frontend_drop`

Drop logging is emitted via `[TMW-BANNER-POOL] selected_dropped ...`.

## Frontend payload (`data-slot-offers`) (C)
- Payload comes from `$slot_data['offers']` JSON on render.
- If Fanvue/MYM/SextPanther/Camera Prive IDs are absent there, backend pool already excluded them.
- If present there, JS should be able to rotate them (subject to randomness and image-load fallback text rendering).

## JS behavior confirmation (D)
- JS uses all offers from `state.offers`.
- No "first 3 only" winner lock: winner is `state.offers[Math.floor(Math.random()*state.offers.length)]`.
- Reel visuals are deterministic sequences built from full pool, but winner selection is independent.
- Image load failure does not remove offer; it falls back to text badge.

## Exact reason offers "stop appearing" (E/F)
Most likely:
1. `allowed_offer_types` reverted/saved to `pps` only (default), while Fanvue-style offers are `revshare` post-normalization.
2. Those selected offers are then dropped as `not_allowed_type` and do not enter frontend JSON.
3. Live frontend appears to have "stopped rotating" those brands, but root is backend filtering due to settings/admin state (not spin animation/CTA styling).

Secondary contributing factor:
- The admin "manual-ready" table checks only manual URL/country readiness and can diverge from true frontend eligibility, which also requires allowed type/status/logo/CTA validity.

## Cache/staleness audit (F)
- Plugin does not add cache-busting to shortcode HTML payload itself; `data-slot-offers` is server-rendered markup.
- Script/style versions are filemtime-based (good for assets), but that does not invalidate cached page HTML.
- So stale page cache can preserve old `data-slot-offers` until cache purge.

## Safest fix recommendation (G)
1. Operational first (no code): ensure `allowed_offer_types` includes `revshare` and/or `revshare_lifetime` in production settings.
2. Verify with logs:
   - `[TMW-BANNER-POOL] live_pool ... allowed_types=...`
   - `[TMW-BANNER-POOL] selected_dropped ... reason=not_allowed_type`
   - `[TMW-BANNER-TYPE-NORM] ... normalized="revshare"`
3. Purge page/object cache for `/slot-test/` and any production cache layers so fresh `data-slot-offers` is served.

Optional hardening PR (later):
- Add explicit admin warning when selected IDs have types outside `allowed_offer_types`.
- Add cached-page troubleshooting note in slot setup/live-pool audit UI.

## Files/functions likely needing change if fix PR is requested (H)
- `admin/admin-page.php`
  - slot setup diagnostics / warning UX around selected type mismatch.
- `includes/class-offer-repository.php`
  - optional enhanced logging/diagnostic summarization for type-blocked selected rows.
- `tests/run-tests.php`
  - add tests that assert warning visibility and mismatch diagnostics.

## Tests to catch/regress this issue (I)
Add/extend tests to assert:
- Selected `revshare` offer is excluded when allowed types = `pps` and logs `not_allowed_type`.
- Same offer included when allowed types includes `revshare`.
- Admin audit explicitly flags "manual-ready but type-blocked" with corrective action.
- Frontend payload (`data-slot-offers`) contains selected IDs when all checks pass.

## Notes on specific requested brands
This repo-local audit cannot query live WP DB selections for:
- Fanvue - Mai / Mila LeRue / Sofía Storme / Talia Rose
- MYM.fans - All Models - Revshare
- SextPanther - Revshare
- Camera Prive - Revshare

The code path to validate each is:
- selected in `slot_offer_ids`
- payout type normalized and intersects `allowed_offer_types`
- active/approved
- valid final URL override (or valid effective CTA)
- country allowlist includes visitor country (e.g., BE)
- logo resolved to mapped/local/remote (not missing/placeholder-only)
- not skipped/excluded

Use `[TMW-BANNER-POOL] selected_dropped` and live `data-slot-offers` HTML to confirm per-offer outcome in production.
