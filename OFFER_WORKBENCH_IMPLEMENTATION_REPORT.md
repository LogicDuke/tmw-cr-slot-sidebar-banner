# Offer Workbench — Phase 1 Implementation Report

Plugin: TMW CR Offer Sidebar Banner (`tmw-cr-slot-sidebar-banner`)
Specification: `docs/OFFER_SETUP_UX_PROPOSAL.md`
Baseline version: 1.9.15
Delivered version: **1.9.16**
Deliverable archive: `tmw-cr-slot-sidebar-banner-offer-workbench.zip` (built outside the repository tree; no archive is committed inside the branch)

---

## 1. Exact files changed

Six repository files were modified. One documentation file was added.

| File | Change |
|---|---|
| `admin/admin-page.php` | +690 lines. One `add_action` in `__construct()`, one call at the top of `render_slot_setup_tab()`, one replaced empty-state cell, one new truncation notice, and five new methods. |
| `includes/class-offer-repository.php` | +175 lines. Two new read-only methods. No existing method touched. |
| `assets/css/admin-dashboard.css` | +73 lines appended. New scoped selectors only; no existing selector edited. |
| `tests/run-tests.php` | +627 lines. 36 new tests, two new fixture helpers, one test-harness accessor, version-assertion strings updated to 1.9.16. |
| `readme.txt` | Stable tag 1.9.15 → 1.9.16; new 1.9.16 changelog entry. |
| `tmw-cr-slot-sidebar-banner.php` | Header `Version:` and `TMW_CR_SLOT_BANNER_VERSION` 1.9.15 → 1.9.16. Nothing else. |
| `docs/OFFER_SETUP_UX_PROPOSAL.md` | Added — the approved specification this PR implements. |

`git diff --stat` against the supplied baseline:

```
 admin/admin-page.php                | 691 +++++++++++++++++++++++++++++++++++-
 assets/css/admin-dashboard.css      |  73 ++++
 includes/class-offer-repository.php | 175 +++++++++
 readme.txt                          |  10 +-
 tests/run-tests.php                 | 627 +++++++++++++++++++++++++++++++-
 tmw-cr-slot-sidebar-banner.php      |   4 +-
 6 files changed, 1571 insertions(+), 9 deletions(-)
```

### New methods

`TMW_CR_Slot_Admin_Page` (`admin/admin-page.php`)

| Method | Purpose |
|---|---|
| `handle_save_offer_config()` | Isolated single-offer save handler |
| `read_workbench_request()` | Reads `offer_q`, `offer_edit`, `offer_blocked_only` |
| `render_offer_workbench_panel( $settings )` | Search form + result cards; renders the editor when `offer_edit` is set |
| `render_offer_config_editor( $offer_id, $settings, $state, $query, $blocked_only )` | Compact single-offer editor |
| `render_offer_validation_row( $label, $is_ok, $message )` | One labelled validation line |
| `render_setup_empty_state( $result, $include_all, $type_allowed_displayed, $synced_total )` | Accurate bulk-table empty states |

`TMW_CR_Slot_Offer_Repository` (`includes/class-offer-repository.php`) — both read-only, both pure compositions of existing helpers

| Method | Composes |
|---|---|
| `search_offers_for_setup( $query, $limit = 20 )` | `get_synced_offers()` |
| `get_offer_setup_state( $offer_id, $settings, $country, $legacy_catalog )` | `get_synced_offers()`, `get_offer_override()`, `get_offer_status_approval_audit()`, `get_offer_frontend_eligibility_summary()`, `get_logo_status_for_offer_any()`, `get_effective_cta_url()`, `is_valid_manual_final_url_override()`, `is_valid_frontend_winner_cta_url()`, `is_offer_allowed_for_country()`, `is_offer_type_allowed()`, `get_offer_type_keys()`, `get_effective_offer_type()`, `get_selected_offer_ids()`, `get_featured_offer_ids()`, `get_frontend_pool_mode()` |

Neither repository method contains an eligibility rule of its own.

---

## 2. New action hook

```
admin_post_tmw_cr_slot_banner_save_offer_config  →  TMW_CR_Slot_Admin_Page::handle_save_offer_config()
```

Registered in `TMW_CR_Slot_Admin_Page::__construct()`, immediately after the existing `admin_post_tmw_cr_slot_banner_save_featured_order` registration.

## 3. Nonce

```
tmw_cr_slot_banner_save_offer_config
```

Emitted by `wp_nonce_field( 'tmw_cr_slot_banner_save_offer_config' )` in the editor form and verified through the existing `assert_admin_action()` helper. `current_user_can( 'manage_options' )` is checked immediately after, matching `handle_save_pool_mode()`.

## 4. POST field namespace

```
tmw_offer_config[...]
```

Full field list: `offer_id`, `offer_q`, `offer_blocked_only`, `enabled`, `final_url_override`, `image_url_override`, `allowed_countries`, `blocked_countries`, `custom_cta_text`, `custom_slogan`, `label_override`, `manual_offer_type`, `notes`, `slot_selected`, `priority`, `add_to_featured`.

The editor emits no field inside the `tmw_cr_slot_banner_settings[...]` namespace and posts to `admin-post.php`, never to `options.php`. This is asserted by the test `workbench_editor_renders_isolated_form_fields`.

## 5. Option keys written

| Option key | What is written |
|---|---|
| `tmw_cr_slot_banner_offer_overrides` | Read in full, only the target offer's row merged and replaced, written back via the existing `save_offer_overrides()`. |
| `tmw_cr_slot_banner_settings` | Read in full, only `slot_offer_ids` membership for the target offer and `slot_offer_priority[<offer_id>]` adjusted, written back with `update_option()`. |
| `tmw_cr_slot_banner_featured_offer_ids` | Only when "Add to Featured Order" is ticked, appended via the existing `save_featured_offer_ids()` (which enforces the 25 limit and returns `false` when exceeded). |

**No new persistent option was introduced.**

## 6. Option keys and values explicitly preserved

Within `tmw_cr_slot_banner_settings`, every key other than `slot_offer_ids` and `slot_offer_priority` is carried through untouched, specifically including:

- `frontend_pool_mode` — test `save_offer_config_preserves_frontend_pool_mode`
- `allowed_offer_types` — test `save_offer_config_preserves_allowed_offer_types`
- `offer_image_overrides` — test `save_offer_config_preserves_offer_image_override_map` (the legacy map is read for display only and never written)
- `rotation_mode`, `cr_api_key`, `country_overrides_raw`, `enforce_skipped_offers_exclusion`, all optimisation thresholds and all banner copy — untouched by construction, since the handler mutates only two array keys.

Within `slot_offer_ids` and `slot_offer_priority`, entries for every other offer are preserved — tests `save_offer_config_preserves_unrelated_slot_offer_ids` and `save_offer_config_preserves_unrelated_slot_offer_priority`.

Within `tmw_cr_slot_banner_offer_overrides`, every non-target offer row is preserved byte for byte — test `save_offer_config_preserves_other_offer_overrides`.

`tmw_cr_slot_banner_featured_offer_ids` order is preserved on append — test `save_offer_config_appends_to_featured_order`.

---

## 7. Feature summary

**Search.** `offer_q` searches every synced offer by exact numeric ID (leading zeros normalised) or case-insensitive partial name. It reads the offer store directly, so it is unaffected by the bulk table's `selected_only` filter, its allowed-type skip, and its `page=1 / per_page=400` slice. Exact ID matches are returned first. Results are capped at 20. The form is a plain `method="get"` form and needs no JavaScript. `offer_blocked_only` narrows results to offers that are not currently eligible.

**Result cards** show: name, ID, active/inactive, approved/unapproved, effective payout type, eligible or the exact block reason, featured position (or `#-`), selected/not selected, and a Configure action linking to `offer_edit=<id>`.

**Editor** exposes `enabled`, `final_url_override`, `image_url_override`, `allowed_countries`, `blocked_countries`, `custom_cta_text`, `custom_slogan`, `label_override`, `manual_offer_type`, `notes`, `slot_offer_ids` membership, manual priority, and an optional "Add to Featured Order" (hidden and replaced by the current position when the offer is already featured).

**Validation** is printed beside the field that clears it, covering `missing_valid_cta`, `country_not_allowed`, `missing_logo`, `not_allowed_type`, `approval_blocked`, `status_blocked`, `business_rule_blocked`, `unavailable_account_offer` and `skipped_offer`. The final URL is checked against **both** `is_valid_manual_final_url_override()` and `is_valid_frontend_winner_cta_url()`, reported separately. When a URL passes the first and fails the second, an explicit warning states that the URL saves but the banner still cannot use it, and names the rejected substrings. Neither validator was modified.

**Post-save** the handler re-runs `get_offer_setup_state()` against freshly stored data and redirects to `slot-setup` with `offer_edit` and `offer_q` preserved, carrying a notice that reports `Eligible` or `Not eligible - <reason>`, plus the winner-rule warning and the featured-order result where applicable.

**Empty states.** The string `"No offers available for slot setup yet. Sync offers first."` is gone. The bulk table now distinguishes: no synced offers; synced offers exist but the table is in selected-only mode with nothing selected; matched offers all excluded by Allowed offer types; and rows hidden behind the 400-row bulk slice. A separate notice above the table reports truncation when more than 400 offers match. The bulk table itself was not redesigned.

---

## 8. Validation results

**PHP lint** — every changed PHP file:

```
No syntax errors detected in admin/admin-page.php
No syntax errors detected in includes/class-offer-repository.php
No syntax errors detected in tmw-cr-slot-sidebar-banner.php
No syntax errors detected in tests/run-tests.php
```

**`git diff --check`** against the supplied baseline: exit 0, clean. No whitespace errors introduced.

**Test totals** (`php tests/run-tests.php`, PHP 8.3.6):

| Run | Passed | Failed |
|---|---|---|
| Baseline (supplied ZIP, before any change) | 505 | 4 |
| After this PR | **541** | **4** |
| Delta | **+36** | **0** |

All 36 new tests pass. **No new failures were introduced.**

### Pre-existing failures (present in the supplied ZIP before any change, unchanged by this PR)

1. `mobile_css_preserves_compact_three_card_row` — expects `#container {` in the slot reel CSS. That file was not touched by this PR.
2. `slot_setup_counts_distinguish_synced_type_allowed_from_displayed_rows` — expects a counter string that does not exist in the shipped code.
3. `slot_setup_show_all_matching_allowed_type_offers_link_exists` — expects a "Show all matching allowed-type offers" link that does not exist in the shipped code.
4. `slot_setup_missing_logo_examples_include_manifest_expected_filename` — expects a "Missing logo examples:" block that does not render under the test's fixture.

These four were verified failing on the pristine upload before any edit. Repairing them is out of scope for this PR.

### New tests added (36)

*Search (5)* — `workbench_exact_id_finds_offer_hidden_by_selected_only`, `workbench_exact_id_bypasses_bulk_pagination`, `workbench_partial_name_search_is_case_insensitive`, `workbench_search_result_cap_is_respected`, `workbench_unknown_id_returns_empty_result_set`

*State (5)* — `workbench_state_reports_missing_valid_cta`, `workbench_state_reports_eligible_with_valid_cta`, `workbench_state_reports_featured_position`, `workbench_state_reports_selected_membership`, `workbench_state_flags_url_accepted_by_import_but_rejected_by_winner`

*Save isolation (14)* — `save_offer_config_writes_only_target_override`, `save_offer_config_preserves_other_offer_overrides`, `save_offer_config_preserves_frontend_pool_mode`, `save_offer_config_preserves_allowed_offer_types`, `save_offer_config_preserves_unrelated_slot_offer_ids`, `save_offer_config_preserves_unrelated_slot_offer_priority`, `save_offer_config_preserves_offer_image_override_map`, `save_offer_config_rejects_invalid_nonce`, `save_offer_config_rejects_insufficient_capability`, `save_offer_config_rejects_non_numeric_offer_id`, `save_offer_config_rejects_unknown_offer_without_override`, `save_offer_config_accepts_unknown_offer_that_already_has_override`, `save_offer_config_appends_to_featured_order`, `save_offer_config_reports_featured_limit_clearly`

*Behaviour and notices (4)* — `save_offer_config_redirects_back_to_the_same_offer`, `save_offer_config_notice_reports_remaining_block_reason`, `save_offer_config_warns_when_url_is_rejected_by_winner_rule`, `save_offer_config_does_not_change_frontend_pool_when_only_notes_change`

*Empty states (5)* — `empty_state_reports_no_synced_offers`, `empty_state_reports_synced_but_none_selected`, `empty_state_reports_allowed_type_exclusion`, `empty_state_reports_bulk_row_truncation`, `slot_setup_no_longer_claims_offers_need_syncing_when_offers_exist`

*Panel rendering (3)* — `workbench_panel_renders_at_top_of_offer_setup_tab`, `workbench_panel_renders_search_results_and_configure_link`, `workbench_editor_renders_isolated_form_fields`

The two version-assertion tests were renamed to `plugin_version_bumped_to_1916` and `readme_stable_tag_bumped_to_1916` and their expected strings updated to 1.9.16.

---

## 9. Confirmation: frontend files and selection logic untouched

Verified by MD5 against the supplied baseline:

| File | Baseline MD5 | Delivered MD5 | Status |
|---|---|---|---|
| `assets/js/slot-banner.js` | `bc166717c9dbf49396b64c2ea2db8569` | `bc166717c9dbf49396b64c2ea2db8569` | **unchanged** |
| `assets/css/slot-banner.css` | `f7c76e7d3b83b3482dc06f58622e01ed` | `f7c76e7d3b83b3482dc06f58622e01ed` | **unchanged** |
| `assets/js/admin-dashboard.js` | `a911dd84ef6843ae3e599e13dc65706d` | `a911dd84ef6843ae3e599e13dc65706d` | **unchanged** |

Verified byte-identical by method-body extraction and diff:

- `get_frontend_slot_offers()` — identical
- `evaluate_synced_offer_for_frontend_pool()` — identical
- `rank_offers_for_slot()` — identical
- `apply_featured_offer_order()` — identical
- `is_valid_frontend_winner_cta_url()` — identical
- `is_valid_manual_final_url_override()` — identical
- `is_offer_allowed_for_country()` — identical
- `handle_save_featured_order()` — identical
- `save_featured_offer_ids()` — identical
- `sanitize_settings()` — identical

Additionally unchanged (no diff against baseline): `includes/class-cr-api-client.php`, `includes/class-cr-api-inspector.php`, `includes/class-offer-sync-service.php`, `includes/class-stats-sync-service.php`, `includes/geo-helper.php`, `includes/recommended-offer-priorities.php`, `tests/bootstrap.php`, all logo assets and manifests.

Also confirmed:

- `grep -rn "wp_cache_flush"` across the delivered tree returns **no matches**. No cache flushing was added; no transient was added or cleared.
- No `*.zip`, `*.tar`, `*.gz`, `*.rar`, `*.7z`, `*.jar`, `*.exe`, `*.dll`, `*.so` or `*.dylib` exists anywhere inside the repository tree. The deliverable archive is built outside the repo.
- The existing bulk `options.php` form, the include-all checkbox, the pool-mode form, the allowed-types form, the CSV importers and the Featured Order panel all behave exactly as before.

---

## 10. Deliberately deferred to separate future PRs

None of the following were touched, per the implementation constraints:

1. `frontend_pool_mode` loss inside `sanitize_settings()`
2. Bulk-form truncation of `slot_offer_ids` / `slot_offer_priority` / `offer_image_overrides`
3. The three `<form>` elements nested inside the `options.php` form
4. The admin/frontend disagreement on approval and logo checks
5. Heavy-audit restructuring and the `include_all_offers` coupling

The workbench is structurally immune to items 1–3 because it never submits the shared settings form. Item 4 is unaffected because `get_offer_setup_state()` calls the same admin-side helpers the dashboard already used, so no eligibility verdict changed.

---

## 11. Migration

None required. No option key was added, renamed or reshaped. Rollback is removing one `add_action`, one call in `render_slot_setup_tab()`, five admin methods, two repository methods, the appended CSS block, and reverting the empty-state cell. No stored data is left inconsistent, because every write goes through an existing sanitiser.
