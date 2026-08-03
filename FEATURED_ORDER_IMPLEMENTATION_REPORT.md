# Featured Offer Order — Implementation Report

**Plugin:** TMW CR Offer Sidebar Banner
**Version:** 1.9.14 → **1.9.15**
**Source design doc:** `docs/FEATURED_OFFER_ORDER_PROPOSAL.md`
**Status:** Implemented, tested, packaged. No unrelated files or behavior changed.

---

## Files changed (8 total)

See `FEATURED_ORDER_CHANGED_FILES.txt` for the plain list. Summary of what changed in each:

| File | What changed |
|---|---|
| `tmw-cr-slot-sidebar-banner.php` | Added `FEATURED_OFFER_IDS_OPTION_KEY` constant (`tmw_cr_slot_banner_featured_offer_ids`); passed it into the existing `TMW_CR_Slot_Offer_Repository` constructor call; bumped plugin header `Version:` and the `TMW_CR_SLOT_BANNER_VERSION` constant to `1.9.15`. |
| `includes/class-offer-repository.php` | Added a constructor parameter (`$featured_offer_ids_option_key`, with a default value, so no existing call site anywhere in the codebase needed to change). Added `sanitize_featured_offer_ids()`, `get_featured_offer_ids()`, `save_featured_offer_ids()`, and the `apply_featured_offer_order()` final-reorder step, called once at the very end of `get_frontend_slot_offers()` immediately before the existing `return apply_filters(...)` line. |
| `admin/admin-page.php` | Registered a new `admin_post_tmw_cr_slot_banner_save_featured_order` action. Added `handle_save_featured_order()` (its own nonce/capability check via the existing `assert_admin_action()` helper, writes only the new option). Added `render_featured_offer_order_panel()`, called once at the top of `render_slot_setup_tab()`, before the existing Offer Setup table. |
| `assets/js/admin-dashboard.js` | Appended one new self-contained IIFE implementing search-as-you-type, add, remove, and native HTML5 drag-and-drop reordering for the new panel. The existing filter-panel code above it is untouched. |
| `assets/css/admin-dashboard.css` | Appended a small block of rules scoped entirely to `.tmw-cr-featured-order` and its descendants, reusing the existing `.tmw-cr-badge--*` classes already in the stylesheet. No existing selector was touched. |
| `tests/run-tests.php` | Updated two pre-existing version-locked tests (`plugin_version_bumped_to_1914` → `...1915`, `readme_stable_tag_bumped_to_1914` → `...1915`) to match the version bump. Added 17 new tests for the Featured Offer Order feature (see below). |
| `readme.txt` | Bumped `Stable tag` to `1.9.15`; added a `= 1.9.15 =` changelog entry describing the feature in plain terms. |
| `README.md` | Added a "Featured Offer Order (manual)" section documenting the option name, admin action, ranking contract, and its relationship to `slot_offer_ids` / `slot_offer_priority`. |

No other file was touched. `assets/js/slot-banner.js` is byte-identical to the original (verified with `diff`).

---

## Behavior added

1. A **"Featured Offer Order"** panel at the top of the Offer Setup tab: search by offer name or numeric ID, add to a compact ordered list, drag rows to reorder, remove with a button, one **Save Featured Order** button.
2. Each row shows position, offer name, ID, detected offer type, active/approval status, and a frontend eligibility summary (reusing existing repository helpers — no offer-classification or eligibility logic was duplicated).
3. An offer ID saved in the list but not currently present in the synced catalog renders as **"Unknown / not in current sync"** with a Remove button, and is preserved in storage rather than silently dropped.
4. In `selected_only` pool mode, a featured offer that is not also in `slot_offer_ids` shows an inline warning in the panel; it is **not** silently added to `slot_offer_ids`, and pool-mode candidate rules are unchanged.
5. **Frontend ranking contract**, applied as the last step of `get_frontend_slot_offers()`, after assembly, eligibility filtering, and existing ranking are complete:
   - Featured offers that survived into the eligible pool are moved to the front, in the exact saved order.
   - Featured offers not present in the eligible pool (ineligible, unsynced, or unknown) are skipped — never added, never substituted.
   - Every remaining offer keeps the relative order the existing ranking logic already produced.
   - An empty featured list is a no-op: the pool is identical to pre-1.9.15 output.
6. Saving the featured order never reads or writes `slot_offer_ids`, `slot_offer_priority`, `offer_overrides`, `offer_image_overrides`, or any other settings key, and is immune to saves on the Performance or Settings tabs (which post through the shared `options.php` settings group; the featured order intentionally does not).

## Option, action, and nonce names

| Item | Value |
|---|---|
| Option name | `tmw_cr_slot_banner_featured_offer_ids` |
| Constant | `TMW_CR_Slot_Sidebar_Banner::FEATURED_OFFER_IDS_OPTION_KEY` |
| Admin-post action | `tmw_cr_slot_banner_save_featured_order` |
| Nonce action name | `tmw_cr_slot_banner_save_featured_order` (via `wp_nonce_field()` / `check_admin_referer()`, same name used for both) |
| Capability required | `manage_options` (enforced by the shared `assert_admin_action()` helper used by every other admin-post handler in this plugin) |

## Frontend insertion point

`TMW_CR_Slot_Offer_Repository::apply_featured_offer_order()`, called from `get_frontend_slot_offers()` immediately before:

```php
return apply_filters( 'tmw_cr_slot_banner_offers', $offers, '', $banner_data );
```

This is strictly after: pool assembly, the eligibility evaluation (`evaluate_synced_offer_for_frontend_pool()`), the thin-pool legacy top-up, and `rank_offers_for_slot()`. It only permutes the already-final `$offers` array; it never adds, removes, or re-evaluates an offer.

## Debug logging

Under `WP_DEBUG` only, one line per frontend pool build when at least one featured ID survives into the eligible pool:

```
[TMW-FEATURED-ORDER] configured=8780,10335 present=8780,10335 first=8780
```

No affiliate URLs, API keys, credentials, tracking parameters, or personal data are logged — only the configured featured IDs, which of them made it into the eligible pool, and the resulting offer ID at position 1.

The admin save handler also logs (also gated by the existing `admin_debug_logging_enabled()` helper, which requires `WP_DEBUG` or the explicit full-audit flag):

```
[TMW-FEATURED-ORDER] admin_save_featured_order saved_count=2 first=8780
```

## Test totals

- Full suite: **501 passed, 4 failed** (505 tests total).
- **17 new tests added**, covering every item on the requested test list: empty-list no-op, position-1 promotion, exact order preservation, reordering changes the winner, ineligible-featured-offer skip, next-eligible-offer promotion, non-featured relative order preservation, duplicate-ID removal, non-numeric-ID rejection, unknown-numeric-ID preservation-but-frontend-ignore, isolation from `slot_offer_ids` on removal, isolation from `slot_offer_ids` on save, isolation from `slot_offer_priority` on save, survival of a simulated Performance/Settings tab save, `selected_only` non-addition, final serialized pool ordering, and CTA/country/eligibility non-interference.
- All 17 new tests pass.

## Pre-existing test failures (not introduced by this change)

The same 4 tests fail identically on the **unmodified original repository** (verified by running `tests/run-tests.php` against the untouched ZIP before making any change):

- `mobile_css_preserves_compact_three_card_row`
- `slot_setup_counts_distinguish_synced_type_allowed_from_displayed_rows`
- `slot_setup_show_all_matching_allowed_type_offers_link_exists`
- `slot_setup_missing_logo_examples_include_manifest_expected_filename`

These are unrelated to the Featured Offer Order feature and were left untouched, per the "do not fix unrelated bugs" instruction.

## Validation performed

- `php -l` — clean on all changed PHP files (`tmw-cr-slot-sidebar-banner.php`, `includes/class-offer-repository.php`, `admin/admin-page.php`, `tests/run-tests.php`).
- `php tests/run-tests.php` — 501/505 passing, matching baseline exactly plus the 17 new tests.
- Full-repository `diff -rq` against the original, untouched ZIP confirms **exactly these 8 files** changed and nothing else.
- `assets/js/slot-banner.js` confirmed byte-identical via `diff`.
- Confirmed no `wp_cache_flush()` was added anywhere.
- Confirmed no new hardcoded affiliate URL, API key, or credential was introduced (every URL/key-shaped string in the diff traces back to pre-existing lines, e.g. the plugin's own author URI and the `cr_api_key` field that already existed).
- `git diff --check` was not run: the supplied repository was provided as a plain ZIP with no `.git` directory, so there is no git history to diff against. The `diff -rq` / per-file `diff -u` comparisons above against the original ZIP serve the same verification purpose.

## Confirmation: prohibited systems untouched

Byte-for-byte unchanged (verified via diff): `assets/js/slot-banner.js`, `includes/class-offer-sync-service.php`, `includes/class-cr-api-client.php`, `includes/class-cr-api-inspector.php`, `includes/class-stats-sync-service.php`, `includes/geo-helper.php`, `includes/recommended-offer-priorities.php`, `assets/css/slot-banner.css`, all logo assets, and every other file not listed in `FEATURED_ORDER_CHANGED_FILES.txt`.

Within the files that did change, the following were deliberately left alone:

- Eligibility evaluation (`evaluate_synced_offer_for_frontend_pool()`, `get_offer_frontend_eligibility_summary()`, `is_offer_blocked_for_banner()`, `is_unavailable_account_pps_offer()`).
- CTA/URL generation and validation (`get_effective_cta_url()`, `is_valid_frontend_winner_cta_url()`, `is_valid_manual_final_url_override()`).
- Country targeting (`is_offer_allowed_for_country()`, country override import handlers).
- Offer-type detection (`get_offer_type_keys()`, `get_manual_offer_type()`).
- Logo/image resolution (`get_effective_image()`, `get_logo_status_for_offer_any()`, the manifest reader).
- The existing `rank_offers_for_slot()` ranking logic and the `recommended-offer-priorities.php` catalog — both still run exactly as before; the new step only reorders their combined output.
- The pre-existing `slot_offer_ids` / `slot_offer_priority` behavior and the known cross-tab settings-rebuild characteristic of `sanitize_settings()` — explicitly out of scope, not modified, not fixed.
