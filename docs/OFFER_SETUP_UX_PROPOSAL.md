# Offer Setup UX Proposal

Plugin: TMW CR Offer Sidebar Banner (`tmw-cr-slot-sidebar-banner`)
Audited version: 1.9.15
Scope: analysis and proposal only. No code, tests, assets, or version numbers were modified by this document.

---

## 1. Executive summary

The plugin's data layer is not the problem. Synchronisation works, the override store is durable, and the frontend pool builder is centralised and correct. The problem is that **Offer Setup has no way to look up a single offer**, and its default view is a filter that hides almost everything.

Four findings explain the entire reported experience:

1. **The Offer Setup table defaults to "selected offers only."** `render_slot_setup_tab()` passes `'selected_only' => ! $include_all`. With no `?include_all_offers=1` in the URL, the table queries only offers already present in `slot_offer_ids`. When that array is empty, the table is empty — regardless of 451 synced offers.
2. **The empty-state string is unconditional.** `"No offers available for slot setup yet. Sync offers first."` is printed whenever `$offers` is empty, with no test for whether offers exist. It cannot distinguish "nothing synced" from "everything filtered out."
3. **There is no search in Offer Setup.** The tab builds its query args as a hard-coded literal — no `search`, no `payout_type`, no `paged`. The only lever is a single checkbox. The two "Show Revshare matching offers" links add `payout_type` to the URL, but that parameter is never read on this tab, so the links do nothing except set `include_all_offers=1`.
4. **Featured Offer Order and Offer Setup are different systems with no bridge.** Featured Order writes one dedicated option and shows eligibility. Offer Setup writes the shared settings blob and holds the fields that fix eligibility. Neither can reach the other, so the operator has to find the same offer twice, in two different ways, with no search on the second one.

Offer 153 ("Slut Roulette - PPS", `Not eligible — missing_valid_cta`) is a clean instance: Featured Order correctly names the blocker; the field that clears the blocker (`final_url_override`) lives in a table the operator cannot search.

Separately, the audit found three **data-integrity risks** that are not the cause of the confusion but would be made worse by any workflow that saves more often (see §8): `sanitize_settings()` drops `frontend_pool_mode` on every settings save; the Offer Setup form rebuilds `slot_offer_ids`, `slot_offer_priority`, and `offer_image_overrides` from posted rows only; and three `<form>` elements are nested inside the settings form.

**Recommendation:** add one read-only-elsewhere "Offer Workbench" panel at the top of the Offer Setup tab — exact-ID/name search, one Configure action, a compact editor, and a single isolated `admin_post` save that touches only the selected offer's keys. Everything below it stays exactly as it is. This is additive, reversible by removing one panel and one handler, and it sidesteps all three data-integrity risks because it never submits the shared settings form.

---

## 2. Current architecture

| Layer | File | Class | Role |
|---|---|---|---|
| Bootstrap | `tmw-cr-slot-sidebar-banner.php` | `TMW_CR_Slot_Sidebar_Banner` | Option keys, `get_settings()`, shortcode render, `build_slot_data()` |
| Data + rules | `includes/class-offer-repository.php` | `TMW_CR_Slot_Offer_Repository` | Offer store, overrides, eligibility, frontend pool, ranking, logo resolution, all audits |
| Admin UI | `admin/admin-page.php` | `TMW_CR_Slot_Admin_Page` | 5 tabs, all forms, all `admin_post` handlers, `sanitize_settings()` |
| Sync | `includes/class-offer-sync-service.php`, `class-cr-api-client.php`, `class-cr-api-inspector.php` | — | CrakRevenue fetch and normalisation |
| Stats | `includes/class-stats-sync-service.php` | — | Performance stats + cron |
| Geo | `includes/geo-helper.php` | `TMW_CR_Slot_Geo_Helper` | `get_country_code()` |
| Admin assets | `assets/js/admin-dashboard.js`, `assets/css/admin-dashboard.css` | — | Filter panels IIFE, Featured Order IIFE |

Admin tabs, from `TMW_CR_Slot_Admin_Page::get_tabs()`: `overview`, `offers`, `performance`, `slot-setup` (labelled "Offer Setup"), `settings`.

`render_slot_setup_tab()` renders, in order: Featured Offer Order panel → include-all GET form → pool-mode form → allowed-types form → **one very long `options.php` form** containing ~20 diagnostic paragraphs, four audit tables, and finally the Offer Setup table → summary lists.

---

## 3. Current data sources and option keys

| Option key | Constant | Written by | Contains |
|---|---|---|---|
| `tmw_cr_slot_banner_settings` | `TMW_CR_Slot_Sidebar_Banner::OPTION_KEY` | `sanitize_settings()`, `handle_save_allowed_types()`, `handle_save_pool_mode()`, `handle_select_offer()` | Banner copy, `slot_offer_ids`, `slot_offer_priority`, `offer_image_overrides`, `allowed_offer_types`, `frontend_pool_mode`, `rotation_mode`, optimisation thresholds, `cr_api_key` |
| `tmw_cr_slot_banner_synced_offers` | `OFFERS_OPTION_KEY` | `save_synced_offers()` | Offer map keyed by offer ID string |
| `tmw_cr_slot_banner_sync_meta` | `SYNC_META_OPTION_KEY` | `save_sync_meta()` | Last sync timestamp, error, count |
| `tmw_cr_slot_banner_offer_overrides` | `OFFER_OVERRIDES_OPTION_KEY` | `save_offer_overrides()` | Per-offer: `enabled`, `final_url_override`, `image_url_override`, `allowed_countries`, `blocked_countries`, `custom_cta_text`, `custom_slogan`, `label_override`, `manual_offer_type`, `notes`, `dashboard_*` |
| `tmw_cr_slot_banner_featured_offer_ids` | `FEATURED_OFFER_IDS_OPTION_KEY` | `save_featured_offer_ids()` | Ordered array of digit-only ID strings, max 25 |
| `tmw_cr_slot_banner_offer_stats` / `_meta` | `OFFER_STATS_OPTION_KEY` / `OFFER_STATS_META_OPTION_KEY` | `save_offer_stats()` / `save_stats_meta()` | Performance data |
| `tmw_cr_slot_banner_offer_dashboard_meta` | constructor arg | `sync_dashboard_metadata_layer()` | Derived filter facets |
| `tmw_cr_slot_banner_skipped_offers` | constructor arg | `save_skipped_offers()` | Skip/reject decisions |

**Three competing meanings of "this offer matters":**

- `slot_offer_ids` — manual selection (priority boost, or hard gate in `selected_only` mode)
- `slot_offer_priority[id]` — numeric sort weight, default 100 in the UI, 9999 in ranking
- `tmw_cr_slot_banner_featured_offer_ids` — manual display order, applied last

They are stored separately, edited in separate forms, and displayed in separate panels. Nothing in the UI explains their interaction.

---

## 4. Current save handlers and sanitizers

Registered in `TMW_CR_Slot_Admin_Page::__construct()`:

| Hook | Method | Nonce action | Writes |
|---|---|---|---|
| `admin_post_tmw_cr_slot_banner_save_featured_order` | `handle_save_featured_order()` | `tmw_cr_slot_banner_save_featured_order` | Featured IDs option only |
| `admin_post_tmw_cr_slot_banner_select_offer` | `handle_select_offer()` | `tmw_cr_slot_banner_select_offer` | Appends to `settings['slot_offer_ids']` (add-only) |
| `admin_post_tmw_cr_slot_banner_save_pool_mode` | `handle_save_pool_mode()` | `tmw_cr_slot_banner_save_pool_mode` | `settings['frontend_pool_mode']` |
| `admin_post_tmw_cr_slot_banner_save_allowed_types` | `handle_save_allowed_types()` | `tmw_cr_slot_banner_save_allowed_types` | `settings['allowed_offer_types']` |
| `admin_post_tmw_cr_slot_banner_import_final_url_overrides` | `handle_import_final_url_overrides()` → `import_final_url_override_rows()` | `tmw_cr_slot_banner_import_final_url_overrides` | Overrides option |
| `admin_post_tmw_cr_slot_banner_import_allowed_country_overrides` | `handle_import_allowed_country_overrides()` → `import_allowed_country_override_rows()` | `tmw_cr_slot_banner_import_allowed_country_overrides` | Overrides option |
| `admin_post_tmw_cr_slot_banner_import_both_overrides` | `handle_import_both_overrides()` | `tmw_cr_slot_banner_import_both_overrides` | Overrides option |
| `admin_post_tmw_cr_slot_import_skipped_offers` | `handle_import_skipped_offers()` | `tmw_cr_slot_import_skipped_offers` | Skipped offers option |
| `admin_post_tmw_cr_slot_banner_sync_offers` / `_sync_stats` / `_test_connection` / `_audit_api` | respective handlers | matching actions | Sync + meta |
| `options.php` (Settings API) | `register_settings()` → `sanitize_settings()` | WP settings-group nonce | **Whole settings option, rebuilt** |

Nonce verification funnels through `assert_admin_action( $nonce_action, $custom_nonce_field )`.

**`sanitize_settings()` semantics — the important part.** It builds a fresh `$output` array and returns it. `$existing` is read via `TMW_CR_Slot_Sidebar_Banner::get_settings()` but consulted for exactly one key (`cr_api_key`). Consequences:

- Any settings key not explicitly re-assigned is **absent from the saved option**. `frontend_pool_mode` is never assigned. `get_settings()` re-applies the default via `wp_parse_args()`, so the operator's saved pool mode silently reverts to `manual_priority_smart_fill`.
- `slot_offer_ids`, `slot_offer_priority`, and `offer_image_overrides` are each initialised to `array()` and repopulated **only from posted rows**.
- `offer_overrides` is the exception and is handled correctly: the handler loads the full stored map via `get_offer_overrides()`, merges per posted row, and writes back with `save_offer_overrides()`. Un-rendered offers keep their overrides.
- Within a posted override row, `final_url_override` falls back to the stored value when the key is absent, but `image_url_override` falls back to `''`. Inconsistent; harmless today only because both inputs are always rendered together.

`TMW_CR_Slot_Offer_Repository::sanitize_offer_override()` normalises on read: `allowed_countries` / `blocked_countries` pass through `sanitize_country_names()` / `sanitize_country_codes()`, which accept both a comma/pipe string (settings form) and an array (CSV importer). The two writers disagree on type; the reader reconciles them. This works, but it means the stored option contains mixed shapes.

---

## 5. Current search / filter / pagination flow

**Offers tab** (`render_offers_tab()`): full featured. `read_offers_tab_filters_from_request()` reads `search`, `status`, `tag`, `vertical`, `featured`, `approval_required`, `payout_type`, `performs_in`, `optimized_for`, `accepted_country`, `niche`, `promotion_method`, `image_status`, `logo_status`; plus `sort_by`, `sort_order`, `paged`, `per_page = 25`. Columns include **Frontend eligible** and **Block reason**. It has no configure action — it can diagnose but not fix.

**Offer Setup tab** (`render_slot_setup_tab()`): the args array is a literal.

```
'selected_only' => ! $include_all,
'include_all'   => $include_all,
'sort_by'       => 'name',
'sort_order'    => 'asc',
'page'          => 1,
'per_page'      => 400,
```

No `search`. No `payout_type`. `page` is pinned to 1.

**`get_filtered_synced_offers_for_admin()` behaviour with those args:**

- `selected_only` is honoured (`if ( ! empty( $query['selected_only'] ) && ! $is_selected ) { continue; }`).
- **`include_all` is declared in `$defaults` and never read anywhere in the method body.** It is inert. The checkbox works only because it flips `selected_only`.
- All other filters are empty strings, so no further narrowing happens.
- Result is sorted by name ascending and sliced to `array_slice( $filtered, 0, 400 )`.

Then `render_slot_setup_tab()` applies a second, local filter to the 400 returned items:

```
if ( $include_all && ! $is_selected && ! $is_allowed_type ) { continue; }
```

and assigns `$offers = $filtered_offers` before rendering. Finally it re-sorts by `slot_offer_priority` (default 9999) then name.

So the displayed set is: **(selected offers only) OR (first 400 by name, minus non-selected offers of disallowed type).**

**Pagination inside the tab** is a separate system entirely: `paginate_rows()` + `render_audit_pagination()` drive `manual_audit_page`, `manual_not_live_page`, and `pps_audit_page` at 25 rows each. These paginate the *audit* tables, not the setup table. The setup table has no pagination at all — it renders up to 400 rows in one page.

**Heavy audits** are gated by `is_heavy_audit_requested()`: true for `tmw_run_full_audit`, `tmw_run_live_pool_audit`, `tmw_run_logo_audit`, `include_all_offers`, or any audit page param; forced false by `tmw_light_admin=1`. Note the coupling: **the checkbox that reveals offers also switches on every heavy audit table**, because `include_all_offers` is one of the triggers. Making the table useful and making the page enormous are the same click.

---

## 6. Current frontend eligibility flow

Entry: `TMW_CR_Slot_Sidebar_Banner::render_shortcode()` → `build_slot_data()` → `TMW_CR_Slot_Offer_Repository::get_frontend_slot_offers()`.

Per-candidate checks live in `evaluate_synced_offer_for_frontend_pool()`, in this order:

1. `get_offer_type_keys()` ∩ `get_allowed_offer_types()` — else `not_allowed_type`
2. `should_exclude_skipped_frontend_offer()` (only when `enforce_skipped_offers_exclusion`)
3. `is_offer_allowed_for_country()`
4. `is_offer_blocked_for_banner()`
5. `is_unavailable_account_pps_offer()` (hard-coded IDs `9647`, `9781`)
6. `evaluate_offer_eligibility()` → `get_effective_offer_record()`
7. `is_valid_frontend_winner_cta_url( $effective['cta_url'] )`

Pool assembly by `frontend_pool_mode`:

- `selected_only` — candidates are `slot_offer_ids` only; empty selection ⇒ empty pool
- `manual_priority_smart_fill` (default) — selected first in saved order, then every other synced offer, ranked in two groups and concatenated
- `smart_auto` — all synced, single ranking, no manual boost

Then: override-only top-up when the pool is under 3, `rank_offers_for_slot()`, legacy catalog top-up when still under 3, and finally `apply_featured_offer_order()` — a pure permutation that lifts featured IDs already present, never adds one.

**CTA is the dominant gate.** `get_effective_cta_url()` returns `final_url_override` or `''`. There is no other source. Every offer without a manual URL fails `is_valid_frontend_winner_cta_url()`. That is the mechanical reason 448 type-allowed offers collapse to 34 eligible.

**`is_valid_frontend_winner_cta_url()` is stricter than the importer's `is_valid_manual_final_url_override()`.** The winner check rejects any URL whose decoded lowercase form contains `affiliate_id` anywhere; the import check only rejects `affiliate_id=affiliate_id`, `aid=affiliate_id`, and bracketed placeholders. A real CrakRevenue tracking URL of the form `…?affiliate_id=123456&…` therefore **saves successfully, displays in the admin field, and is still rejected at render time** with `missing_valid_cta` — and no message anywhere says why. The winner check also rejects any URL containing `preview`, `template`, `help`, `docs`, or `documentation` as substrings, including inside a hostname or path segment.

---

## 7. Root causes of the confusing behaviour

**7.1 — Why the setup table shows zero rows while hundreds of offers exist.**
`'selected_only' => ! $include_all` in `render_slot_setup_tab()`. Default URL ⇒ `selected_only = true` ⇒ `get_filtered_synced_offers_for_admin()` skips every offer not in `slot_offer_ids`. Empty selection ⇒ zero rows.

**7.2 — Why "Sync offers first" appears when offers are synchronised.**
The message is inside `<?php if ( empty( $offers ) ) : ?>` in the setup table `<tbody>`. `$offers` at that point is the *post-filter* set. Nothing in the branch consults `count( get_synced_offers() )`, `$result['total']`, or `$result['source_total']` — all of which are available and would have told the truth.

**7.3 — Which controls determine display.**
In order of effect: (a) `?include_all_offers=1` → `selected_only`; (b) membership in `slot_offer_ids`; (c) `allowed_offer_types` via the local `$include_all && ! $is_selected && ! $is_allowed_type` skip; (d) the hard `per_page = 400, page = 1` slice. Notably *not* in effect: `payout_type`, `search`, `paged`, and the `include_all` argument itself.

**7.4 — Whether tabs or forms can overwrite each other's settings.**
Yes, in two ways.
*Whole-key loss:* `sanitize_settings()` omits `frontend_pool_mode`, so saving Offer Setup or the Settings tab reverts a pool mode chosen via `handle_save_pool_mode()`.
*Partial-key loss:* `slot_offer_ids`, `slot_offer_priority`, and `offer_image_overrides` are rebuilt from posted rows. In the default (`selected_only`) view the table renders only selected offers, so saving discards every `slot_offer_priority` and `offer_image_overrides` entry belonging to a non-selected offer. In `include_all` mode with more than 400 matching offers, a selected offer that sorts past position 400 by name is not rendered, not posted, and therefore **removed from `slot_offer_ids`** on save.

**7.5 — Whether hidden or filtered offers lose saved configuration.**
`offer_overrides` (URLs, countries, CTA, slogan, notes, type) are safe — merged against the stored map. `slot_offer_ids`, `slot_offer_priority`, `offer_image_overrides` are not, per 7.4.

**7.6 — Whether Featured Order and Offer Setup share a source of truth.**
No. Featured Order reads/writes `tmw_cr_slot_banner_featured_offer_ids` exclusively and never touches `slot_offer_ids`. Offer Setup reads/writes `slot_offer_ids` and never touches featured IDs. The two are joined only at render time, inside `get_frontend_slot_offers()`, where `apply_featured_offer_order()` permutes whatever survived eligibility. That separation is deliberate and correct as a storage decision — the gap is that no screen shows both for one offer with an action that changes either.

**7.7 — Which eligibility checks are duplicated.**
Four independent implementations of "is this offer usable":

| Path | Method |
|---|---|
| Frontend | `evaluate_synced_offer_for_frontend_pool()` |
| Admin badge | `get_offer_frontend_eligibility_summary()` |
| Drop-reason audit | `get_selected_offer_frontend_drop_reason()` |
| PPS audit table | `get_pps_expansion_readiness_audit_rows()` |

They run the same predicates in different orders and disagree on two points:
- **Approval.** `get_offer_frontend_eligibility_summary()` blocks on `get_offer_status_approval_audit()['approval_blocked']`. `get_effective_offer_record()` checks only `status !== 'active'`. An approval-blocked offer can be reported ineligible in the admin and still enter the frontend pool.
- **Logo.** The admin summary blocks when `get_logo_status_for_offer_any()` returns `missing` or `placeholder_only`. On the frontend, the logo check inside `evaluate_offer_eligibility()` is only reachable when `get_effective_offer_record()` returned empty; on the normal success path **no logo check runs at all**. So a logo-less offer can be reported `missing_logo` in the admin and still render.

Also: the admin summary calls `get_effective_cta_url()` with `array( 'cta_url' => … )` while the frontend passes full `$banner_data`. Harmless today because the method ignores both arguments, but the signatures have already drifted.

**7.8 — Whether admin status matches the live frontend.**
Approval and logo: no (7.7). CTA: yes as a boolean, but the admin never tells the operator that a *saved, valid-looking* URL was rejected by the stricter winner rule (§6). Country: yes — both go through `is_offer_allowed_for_country()`. Type: yes. Live pool audit: `get_live_frontend_pool_audit()` is accurate because it calls the real `get_frontend_slot_offers()`, but every row it emits is hard-coded `'frontend_ready' => 'yes'`, `'first_blocker' => ''`, so it can only ever show survivors — it cannot explain an exclusion.

**7.9 — Which diagnostics belong elsewhere.**
Between `settings_fields( 'tmw_cr_slot_banner' )` and the setup table there are roughly twenty `<p class="description">` counters, a PPS logo coverage list that prints every missing offer name inline, a manual-winner eligibility table, a live-pool audit table, a "manual-ready but not in live pool" table, a PPS expansion audit table, and a CR URL field audit. All of it is developer telemetry. All of it sits above the only editable controls on the page.

**7.10 — Structural HTML defect.**
The `options.php` form opens before the diagnostics and closes after the setup table's submit button. Three `<form>` elements are nested inside it: two `admin-post.php` bulk "Select for banner" forms and one `method="get"` PPS audit filter form. HTML forbids nested forms; browsers discard the inner elements and re-parent their inputs to the outer form. In practice the nested submit buttons post the **outer settings form to `options.php`**, carrying `action=tmw_cr_slot_banner_select_offer` and `pps_audit_filter` as unrecognised input — and, more importantly, triggering a full `sanitize_settings()` rebuild with all the truncation semantics of 7.4.

---

## 8. Risks in the current implementation

| # | Risk | Trigger | Impact |
|---|---|---|---|
| R1 | `frontend_pool_mode` silently reset to default | Any `options.php` save from Offer Setup or Settings | Operator's pool mode is lost without notice |
| R2 | `slot_offer_ids` truncated to displayed rows | Saving Offer Setup with >400 matching offers | Selections silently deleted |
| R3 | `slot_offer_priority` / `offer_image_overrides` wiped for non-displayed offers | Any Offer Setup save in the default `selected_only` view | Priorities and image overrides silently deleted |
| R4 | Nested forms | Clicking any nested submit inside the settings form | Unintended full settings save; intended action never runs |
| R5 | Valid-looking CTA rejected at render | Tracking URL containing `affiliate_id`, `preview`, `template`, `help`, or `docs` | Offer stays ineligible with no explanation |
| R6 | Admin/frontend disagreement on approval and logo | Approval-required or logo-less offers | Admin badge does not predict live behaviour |
| R7 | `image_url_override` reset to `''` on partial post | Any future partial override submission | Image override loss |
| R8 | `include_all` argument inert | — | Any future code trusting it gets no filtering |
| R9 | Heavy audits coupled to `include_all_offers` | Revealing offers | Page becomes very large exactly when the operator needs it usable |
| R10 | `get_featured_offer_ids()` returns `array()` when stored count exceeds 25 | External/corrupt write | Entire featured order appears empty rather than flagged |

R1–R4 are pre-existing and independent of this proposal. **The proposed workbench does not inherit them**, because it never posts to `options.php`. They are listed so the phasing in §21 can address them deliberately rather than by accident, and they should not be bundled into the first PR.

---

## 9. Proposed operator workflow

Single loop, one offer at a time, one screen:

1. **Find** — type `153` or `Slut Roulette` into one search box at the top of Offer Setup.
2. **Read** — one result card: name, ID, active/approved, payout type, eligible/not eligible with the exact block reason, featured position if any, manual-selection state.
3. **Configure** — press *Configure*. A compact editor opens in place with only the fields that matter, each showing which blocker it clears.
4. **Save** — one *Save this offer* button. Writes only this offer's keys.
5. **Verify** — the page reloads on the same offer and re-evaluates: **Eligible**, or **Not eligible** with the remaining reasons.
6. **Feature** — *Add to Featured Order* (server-side) from the same card; the Featured Order panel above updates on the reload, where drag/Move up/Move down and *Save Featured Order* continue to work exactly as today.

No tab switching. No scrolling past diagnostics. No dependence on `include_all_offers`.

---

## 10. Proposed screen layout

Offer Setup tab, top to bottom:

```
┌─ A. Offer Workbench ─────────────────────────────────────────────┐
│  [ Search by offer ID or name        ]  ( Find )   ☐ show only   │
│                                                     blocked      │
│  ── Results (max 20) ────────────────────────────────────────────│
│  153 · Slut Roulette - PPS                                       │
│  Active · Approved · PPS · Featured #— · Not selected            │
│  ✕ Not eligible — missing_valid_cta          [ Configure ]       │
│                                                                  │
│  8780 · Jerkmate - PPS                                           │
│  Active · Approved · PPS · Featured #1 · Selected                │
│  ✓ Eligible                                  [ Configure ]       │
└──────────────────────────────────────────────────────────────────┘

┌─ B. Configure offer 153 (only when ?offer_edit=153) ─────────────┐
│  Status  Active ✓   Approved ✓   Type PPS ✓   Allowed type ✓     │
│                                                                  │
│  Final affiliate URL      [___________________]  ✕ required      │
│  Image / logo override    [___________________]  ✓ mapped_local  │
│  Allowed countries        [US,CA,GB__________]   ✓ BE allowed    │
│  Blocked countries        [_________________ ]                   │
│  CTA text                 [_________________ ]  fallback: …      │
│  Slogan / label           [_________________ ]                   │
│  Offer-type override      [ Auto ▾ ]            effective: pps   │
│  Internal notes           [_________________ ]                   │
│                                                                  │
│  ☑ Enable for frontend pool (manual selection)                   │
│  Manual priority [100]                                           │
│  ☐ Add to Featured Order (appends at end)                        │
│                                                                  │
│  ( Save this offer )      ( Cancel )                             │
└──────────────────────────────────────────────────────────────────┘

── Featured Offer Order ──  (unchanged)
── Frontend pool mode ──    (unchanged)
── Allowed offer types ──   (unchanged)
── ▸ Diagnostics & audits ──(collapsed <details>, phase 2)
── Offer Setup bulk table ──(unchanged, better empty state)
```

The workbench sits **above** Featured Offer Order so the operator's first sight of the page is a search box, not a list.

---

## 11. Proposed data model

**No new option keys. No schema change. No migration.**

The workbench is a view and an editor over existing storage:

| Field in editor | Storage |
|---|---|
| Final affiliate URL | `tmw_cr_slot_banner_offer_overrides[id]['final_url_override']` |
| Image/logo override | `…offer_overrides[id]['image_url_override']` |
| Allowed / blocked countries | `…offer_overrides[id]['allowed_countries'] / ['blocked_countries']` |
| CTA text / slogan / label | `…offer_overrides[id]['custom_cta_text'] / ['custom_slogan'] / ['label_override']` |
| Offer-type override | `…offer_overrides[id]['manual_offer_type']` |
| Notes | `…offer_overrides[id]['notes']` |
| Enabled | `…offer_overrides[id]['enabled']` |
| Enable for frontend pool | membership in `tmw_cr_slot_banner_settings['slot_offer_ids']` |
| Manual priority | `tmw_cr_slot_banner_settings['slot_offer_priority'][id]` |
| Featured position | position in `tmw_cr_slot_banner_featured_offer_ids` |

The legacy `settings['offer_image_overrides'][id]` map is **read for display only** and never written by the workbench, so the older mechanism keeps working and the newer per-offer `image_url_override` remains the field the operator edits.

One new **read-only** aggregate is proposed, purely as a composition of existing helpers:

`TMW_CR_Slot_Offer_Repository::get_offer_setup_state( $offer_id, $settings, $country, $legacy_catalog )`
returns `array( 'offer', 'override', 'status_audit', 'eligibility', 'logo_status', 'effective_cta_url', 'country_allowed', 'is_selected', 'priority', 'featured_position', 'type_keys', 'effective_type' )` by calling, in order:
`get_synced_offers()`, `get_offer_override()`, `get_offer_status_approval_audit()`, `get_offer_frontend_eligibility_summary()`, `get_logo_status_for_offer_any()`, `get_effective_cta_url()`, `is_offer_allowed_for_country()`, `get_selected_offer_ids()`, `get_featured_offer_ids()`, `get_offer_type_keys()`, `get_effective_offer_type()`.

It contains **no new business logic** — every value comes from an existing helper. This satisfies the "reuse, don't duplicate" constraint and gives one place to fix the admin/frontend divergences in R6 later.

---

## 12. Proposed save architecture

**A dedicated `admin_post` action. Not AJAX. Not the settings API.**

Rationale: `admin_post` matches the four handlers already in the file, is testable with the existing harness (which stubs `$_POST`, nonces, and `wp_redirect`), degrades without JavaScript, and produces a full page reload — which is exactly what step 5 of the workflow needs, since eligibility must be recomputed from freshly stored data rather than from a JS guess. AJAX would require a new REST/ajax surface, a second nonce lifecycle, and duplicate rendering of the eligibility badge in JavaScript. Not worth it for a one-offer-at-a-time editor.

- Hook: `admin_post_tmw_cr_slot_banner_save_offer_config`
- Method: `TMW_CR_Slot_Admin_Page::handle_save_offer_config()`
- Nonce action: `tmw_cr_slot_banner_save_offer_config`, verified through the existing `assert_admin_action()`
- Capability: `current_user_can( 'manage_options' )`, matching `handle_save_pool_mode()`
- Field name space: `tmw_offer_config[…]`, deliberately **not** `tmw_cr_slot_banner_settings[…]`, so a stray post can never enter `sanitize_settings()`

Write sequence, strictly scoped:

1. `$offer_id = sanitize_text_field( $_POST['offer_id'] )`; reject non-digit; reject unknown-to-`get_synced_offers()` unless an override already exists.
2. `$overrides = $this->offer_repository->get_offer_overrides();` → replace **only** `$overrides[ $offer_id ]` (merging with its existing row, so unposted keys survive) → `save_offer_overrides( $overrides )`.
3. `$settings = get_option( $this->option_key, array() );` → adjust **only** `$settings['slot_offer_ids']` (add or remove this one ID) and `$settings['slot_offer_priority'][ $offer_id ]` → `update_option( $this->option_key, $settings, false )`. Every other key of the array is carried through untouched, exactly as `handle_save_pool_mode()` already does.
4. If "Add to Featured Order" was ticked: `$ids = get_featured_offer_ids(); $ids[] = $offer_id; save_featured_offer_ids( $ids );` — honouring the existing max-25 `false` return by surfacing the existing error notice.
5. `redirect_with_notice_to_tab( 'success', …, 'slot-setup', array( 'offer_edit' => $offer_id, 'offer_q' => $q ) )` so the operator lands back on the same offer with fresh state.
6. Debug tag: `[TMW-OFFER-CONFIG] saved offer_id=… selected=… priority=… featured=… eligible=… block_reason=…` via the existing `admin_debug_log()`.

Post-save the page re-runs `get_offer_setup_state()` and prints **Eligible** or **Not eligible — `<reason>`** plus the per-field validation list of §14. Because the reload reads from storage, the badge and the frontend cannot disagree about what was saved.

---

## 13. Featured Order integration

Featured Offer Order keeps its option, its handler, its nonce, its 25-item cap, its drag/move/remove behaviour, and its JS module. Nothing about it is rewritten.

Two additive bridges:

**Phase 1 — server side, no JS coupling.** The workbench editor carries a checkbox `tmw_offer_config[add_to_featured]`. `handle_save_offer_config()` appends the ID through `save_featured_offer_ids()`. After redirect, the Featured Order panel — which renders from the option — shows the new row at the end, ready to drag. This is one `if` block and no changes to `assets/js/admin-dashboard.js`.

**Phase 2 — client side, optional.** Add *Add to Featured Order* directly on a workbench result card. It dispatches `document.dispatchEvent( new CustomEvent( 'tmw-cr:featured-add', { detail: { id, name } } ) )`. The existing Featured Order IIFE gains one listener that calls its already-private `addOffer()`. No new persistence path; the operator still presses *Save Featured Order*. Deferred because it duplicates a working server path for pure convenience.

The workbench card always shows the current featured position (or `—`) from `get_featured_offer_ids()`, so the operator can see the two systems agree without leaving the panel.

---

## 14. Eligibility and validation presentation

Every block reason emitted by `get_offer_frontend_eligibility_summary()` is mapped to the field that clears it, and the message is printed **next to that field**, not as one badge at the top:

| `block_reason` | Field | Message | Repairable here? |
|---|---|---|---|
| `missing_valid_cta` | Final affiliate URL | Add a live affiliate tracking URL. This is required for the banner. | yes |
| `country_not_allowed` | Allowed countries | Visitor country `XX` is not in the allowed list. | yes |
| `missing_logo` | Image/logo override | No local logo mapped for this brand. Add an override URL or add the file to `assets/logos/80x80/`. | partly |
| `not_allowed_type` | Offer-type override | Detected type `revshare` is not in Allowed offer types. Change the type override, or enable the type below. | partly |
| `business_rule_blocked` | Status strip | Blocked by banner business rules. | no |
| `status_blocked` | Status strip | Offer status is `paused` in the last sync. | no |
| `approval_blocked` | Status strip | Approval required and not granted. | no |
| `unavailable_account_offer` | Status strip | Offer is unavailable on this account. | no |
| `skipped_offer` | Status strip | In the Skipped/Rejected list while enforcement is on. | via skip list |

Two additions worth their space:

- **URL pre-flight.** Run both `is_valid_manual_final_url_override()` and `is_valid_frontend_winner_cta_url()` on the entered URL and report them separately. This is the only surface in the plugin that would tell an operator "saved, but the banner will still reject it because the URL contains `affiliate_id`" — directly addressing R5 without changing either validator.
- **Pool-mode context.** When `frontend_pool_mode` is `selected_only` and the offer is not in `slot_offer_ids`, say so beside the *Enable for frontend pool* checkbox. Featured Order already shows an equivalent warning; the editor should not be quieter than the panel above it.

Rendering reuses `render_badge( $label, $variant )` with the existing `.tmw-cr-badge--featured` / `--warning` / `--muted` variants.

---

## 15. Diagnostics restructuring

Target state: nothing between the top of the tab and the first editable control except the workbench.

- **Phase 2, low risk:** wrap the counter paragraphs and the PPS logo coverage list in `<details class="tmw-cr-diagnostics"><summary>Diagnostics</summary>…</details>`, closed by default, inside `render_slot_setup_tab()`. Pure markup; no data path touched.
- **Phase 3:** decouple heavy audits from `include_all_offers` (R9). Keep `tmw_run_full_audit` as the canonical trigger and stop treating `include_all_offers` as a heavy-audit signal in `is_heavy_audit_requested()`, so revealing offers and running audits become separate decisions.
- **Phase 4, optional:** move the four audit tables (`get_manual_winner_eligibility_audit_rows()`, `get_live_frontend_pool_audit()`, "manual-ready but not in live pool", `get_pps_expansion_readiness_audit_rows()`) plus the CR URL field audit to a sixth `diagnostics` tab in `get_tabs()`. This also resolves R4, because the nested forms live inside that block and would no longer sit within the `options.php` form.

Order matters: the collapse (phase 2) is cosmetic and safe; the tab move (phase 4) relocates the nested forms and must be verified against the "Select for banner" workflow.

---

## 16. Empty-state and error-message improvements

Replace the single unconditional string with a small resolver — proposed as `TMW_CR_Slot_Admin_Page::render_setup_empty_state( $result, $args, $include_all )` — using values already returned by `get_filtered_synced_offers_for_admin()`:

| Condition | Message |
|---|---|
| `$result['source_total'] === 0` | No offers have been synchronised yet. Run **Sync Offers** on the Overview tab. |
| `source_total > 0` and `selected_only` and `total === 0` | 451 offers are synchronised. This table is showing **selected offers only**, and none are selected yet. Use the search box above to find and configure any offer, or tick "Include more offers from synced pool". |
| `total > 0` but the local allowed-type skip removed everything | 448 offers match the current filters, but none match your Allowed offer types. Adjust Allowed offer types below. |
| `total > per_page * page` | Showing the first 400 of 451 matching offers. Use the search box above to reach a specific offer. |

Two related message fixes:

- `"Type-allowed synced offers: %d of %d"` and `"Setup rows currently displayed: %d"` sit adjacent and describe different populations, which is what makes "448 of 451" next to "0 rows" read as a contradiction. Label them explicitly: *"Matching your type filter (whole synced pool): 448 of 451"* and *"Rows visible in this table right now: 0"*.
- The unconditional red paragraph *"CrackRevenue API does not provide usable final CTA URLs…"* prints on every load. It should render only when `get_cr_url_field_audit_summary()['offers_with_tracking_url']` is actually low, so a permanent warning does not train the operator to ignore red text.

---

## 17. Exact files and methods that would need changes

**`admin/admin-page.php` — class `TMW_CR_Slot_Admin_Page`**

| Change | Location |
|---|---|
| Register `add_action( 'admin_post_tmw_cr_slot_banner_save_offer_config', array( $this, 'handle_save_offer_config' ) );` | `__construct()`, after the `save_featured_order` registration |
| New `public function handle_save_offer_config()` | after `handle_save_featured_order()` |
| New `protected function render_offer_workbench_panel( $settings )` | before `render_featured_offer_order_panel()` |
| New `protected function render_offer_config_editor( $offer_id, $settings, $state )` | after the workbench renderer |
| New `protected function render_offer_validation_row( $label, $ok, $message )` | beside `render_badge()` |
| New `protected function read_workbench_request()` — reads `offer_q`, `offer_edit`, `offer_blocked_only` | beside `read_offers_tab_filters_from_request()` |
| One call: `$this->render_offer_workbench_panel( $settings );` | first line of `render_slot_setup_tab()`, above `render_featured_offer_order_panel()` |
| New `protected function render_setup_empty_state( $result, $args, $include_all )` + its single call | replaces the literal in the setup table `<tbody>` `if ( empty( $offers ) )` branch |
| Phase 2: `<details class="tmw-cr-diagnostics">` wrapper | inside `render_slot_setup_tab()`, around the counter paragraphs after `settings_fields()` |

**`includes/class-offer-repository.php` — class `TMW_CR_Slot_Offer_Repository`**

| Change | Location |
|---|---|
| New read-only `public function get_offer_setup_state( $offer_id, $settings, $country, $legacy_catalog = array() )` | after `get_offer_frontend_eligibility_summary()` |
| New read-only `public function search_offers_for_setup( $query, $settings, $limit = 20 )` — exact-ID key lookup first, then delegate to `get_filtered_synced_offers_for_admin()` with `search`, `selected_only => false`, `per_page => $limit` | after `get_filtered_synced_offers_for_admin()` |

No existing repository method is modified. No eligibility predicate is rewritten, reordered, or bypassed.

**`assets/js/admin-dashboard.js`** — phase 1: none. Phase 2 optional: a third IIFE bound to `[data-tmw-offer-workbench="1"]` for type-ahead result filtering, plus a `tmw-cr:featured-add` listener inside the existing Featured Order IIFE. The panel must work with JavaScript disabled; the *Find* button submits a normal `method="get"` form.

**`assets/css/admin-dashboard.css`** — new scopes only, appended, no existing selector edited:
`.tmw-cr-workbench`, `.tmw-cr-workbench__search`, `.tmw-cr-workbench__results`, `.tmw-cr-workbench__result`, `.tmw-cr-workbench__result-meta`, `.tmw-cr-workbench__editor`, `.tmw-cr-workbench__field`, `.tmw-cr-workbench__validation`, `.tmw-cr-workbench__validation--ok`, `.tmw-cr-workbench__validation--error`, `.tmw-cr-diagnostics`. Badges reuse `.tmw-cr-badge` and its existing modifiers.

**`tests/run-tests.php`** — new `$tests[…]` closures only; no existing test modified.

**Debug log tags** — `[TMW-OFFER-CONFIG]` for saves, `[TMW-OFFER-SEARCH]` for search resolution, both routed through `admin_debug_log()` so they stay silent in production.

---

## 18. Files and systems that must remain untouched

- `includes/class-cr-api-client.php`, `includes/class-offer-sync-service.php`, `includes/class-cr-api-inspector.php` — CrakRevenue synchronisation
- `includes/class-stats-sync-service.php` and the `STATS_SYNC_CRON_HOOK` schedule
- `includes/geo-helper.php`, `includes/recommended-offer-priorities.php`
- `TMW_CR_Slot_Offer_Repository::get_frontend_slot_offers()`, `evaluate_synced_offer_for_frontend_pool()`, `rank_offers_for_slot()`, `apply_featured_offer_order()` — winner selection and ordering
- `is_valid_frontend_winner_cta_url()`, `is_valid_manual_final_url_override()`, `is_offer_allowed_for_country()`, `is_offer_type_allowed()`, `is_offer_blocked_for_banner()` — eligibility predicates: **called, never edited**
- `handle_save_featured_order()`, `save_featured_offer_ids()`, `sanitize_featured_offer_ids()` and the Featured Order IIFE
- `sanitize_settings()` and the `options.php` form — untouched by the workbench PR (its known defects are handled separately in §21)
- `assets/css/slot-banner.css`, `assets/js/slot-banner.js` — frontend animation and CTA tracking
- `assets/logos/80x80/*`, `manifest.csv`
- No cache flush of any kind is introduced. No transient is added or cleared.

---

## 19. Migration and backward compatibility

Nothing to migrate.

- No new option key, no key rename, no value reshaping. Existing `tmw_cr_slot_banner_settings`, `…offer_overrides`, and `…featured_offer_ids` are read and written in their current shapes by their current helpers.
- Mixed `allowed_countries` shapes (string from the settings form, array from the CSV importer) continue to be reconciled on read by `sanitize_offer_override()`. The workbench posts a comma string, matching the settings form, so no new shape appears.
- The bulk Offer Setup table, the include-all checkbox, the CSV importers, the pool-mode form, the allowed-types form, and Featured Order all keep working unchanged. An operator who ignores the workbench sees the tab behave exactly as it does today, plus a clearer empty state.
- Rollback is deleting one panel call, one renderer, one handler, one `add_action`, and one CSS block. No stored data is left in an inconsistent state, because every write goes through an existing sanitiser.
- `assets/js/admin-dashboard.js` is unchanged in phase 1, so no cache-busting concern; the enqueue already versions on `TMW_CR_SLOT_BANNER_VERSION`.

---

## 20. Testing strategy

The existing harness (`tests/bootstrap.php` + `tests/run-tests.php`, `$tests['name'] = function() {…}`, `tmw_assert_same()`, stubbed `$_POST` / `$_GET` / options / nonce / redirect) covers everything needed. Proposed additions, all new closures:

*Search*
- `workbench_exact_id_returns_offer_hidden_by_selected_only`
- `workbench_exact_id_ignores_allowed_type_filter`
- `workbench_name_search_is_case_insensitive_and_partial`
- `workbench_search_limit_is_capped`
- `workbench_unknown_id_returns_empty_without_error`

*State*
- `setup_state_reports_missing_valid_cta_for_offer_without_final_url`
- `setup_state_reports_eligible_after_valid_final_url`
- `setup_state_reports_featured_position_when_offer_is_featured`
- `setup_state_flags_url_accepted_by_import_but_rejected_by_winner_rule` (guards R5)

*Save isolation — the critical set*
- `save_offer_config_writes_only_target_offer_override`
- `save_offer_config_preserves_other_offers_overrides`
- `save_offer_config_preserves_frontend_pool_mode` (guards R1)
- `save_offer_config_preserves_unrelated_slot_offer_ids` (guards R2)
- `save_offer_config_preserves_unrelated_slot_offer_priority` (guards R3)
- `save_offer_config_preserves_offer_image_overrides_map`
- `save_offer_config_rejects_invalid_nonce`
- `save_offer_config_rejects_non_manage_options_user`
- `save_offer_config_add_to_featured_appends_without_reordering`
- `save_offer_config_add_to_featured_respects_25_limit`
- `save_offer_config_does_not_change_frontend_pool_output` — snapshot `get_frontend_slot_offers()` before and after a save that only edits `notes`

*Empty state*
- `empty_state_says_sync_when_no_offers_synced`
- `empty_state_says_hidden_when_offers_exist_but_none_selected`
- `empty_state_says_no_type_match_when_filter_excludes_all`

Manual verification before merge: with 451 synced offers, search `153` → configure → paste a valid tracking URL → save → confirm the badge flips to Eligible → add to Featured Order → confirm position 1 on the frontend → confirm `frontend_pool_mode`, allowed types, and all other offers' priorities are unchanged in the stored option.

---

## 21. Phased implementation plan

| Phase | Content | Risk | Reversible by |
|---|---|---|---|
| **1** | Offer Workbench: search, result cards, editor, `handle_save_offer_config()`, `get_offer_setup_state()`, `search_offers_for_setup()`, CSS scopes, tests | Low — additive, isolated writes | Removing one panel call + one handler |
| **2** | Empty-state resolver; relabel the contradictory counters; conditional CR-URL warning | Low — messaging only | Restoring the literal string |
| **3** | Collapse diagnostics into `<details>` | Low — markup only | Removing the wrapper |
| **4** | Fix R1: preserve `frontend_pool_mode` in `sanitize_settings()` (one assignment) | Low, but touches the shared sanitizer | One-line revert |
| **5** | Fix R2/R3: post the full `slot_offer_ids` / `slot_offer_priority` / `offer_image_overrides` state as hidden inputs for non-displayed offers, or merge instead of rebuild in `sanitize_settings()` | Medium — changes shared save semantics; needs its own test set | Revert |
| **6** | Fix R4: unnest the three forms, most cleanly by moving audit blocks out of the `options.php` form (§15 phase 4) | Medium — relocates working controls | Revert |
| **7** | Decouple heavy audits from `include_all_offers` (R9); optional Diagnostics tab; optional workbench JS enhancements | Medium | Revert |
| **8** | Reconcile admin/frontend approval and logo checks (R6) behind `get_offer_setup_state()` | **High — changes what is eligible.** Must be its own PR with a full before/after pool snapshot | Revert |

Phases 4–8 are deliberately outside the first PR. Phase 8 in particular changes live winner selection and is explicitly out of scope for this proposal.

---

## 22. Recommended smallest first PR

**Title:** Offer Setup: add Offer Workbench (search + single-offer configure + isolated save)

**Scope — additive only:**

*`admin/admin-page.php`*
- `__construct()`: one `add_action( 'admin_post_tmw_cr_slot_banner_save_offer_config', … )`
- new `handle_save_offer_config()` — nonce `tmw_cr_slot_banner_save_offer_config`, cap `manage_options`, field namespace `tmw_offer_config[…]`, writes only the target offer's override row, its `slot_offer_ids` membership, its `slot_offer_priority` entry, and optionally appends to featured IDs
- new `render_offer_workbench_panel()`, `render_offer_config_editor()`, `render_offer_validation_row()`, `read_workbench_request()`
- one call to `render_offer_workbench_panel( $settings )` at the top of `render_slot_setup_tab()`
- new `render_setup_empty_state()` replacing the `"Sync offers first."` literal

*`includes/class-offer-repository.php`*
- new read-only `get_offer_setup_state()` and `search_offers_for_setup()`; no existing method touched

*`assets/css/admin-dashboard.css`* — appended `.tmw-cr-workbench*` scopes

*`tests/run-tests.php`* — the search, state, save-isolation, and empty-state closures from §20

**Explicitly not in this PR:** `sanitize_settings()`, the `options.php` form, the nested forms, the bulk setup table, Featured Order storage or JS, `is_heavy_audit_requested()`, any eligibility predicate, any frontend file, any sync or stats code, any version bump beyond the normal release step, any archive or packaged file.

**Query parameters introduced:** `offer_q`, `offer_edit`, `offer_blocked_only` — read-only, additive, safe to append to any existing `slot-setup` URL.

---

## 23. Open product decisions

1. **Should the workbench editor be able to change Allowed offer types?** Today it is a global setting. Offering a per-offer `manual_offer_type` override plus a link to the global control is safer than exposing the global toggle inside a single-offer editor — but it means `not_allowed_type` is sometimes only half-repairable in the editor. Confirm the preference.
2. **Should "Enable for frontend pool" default to ticked when the operator saves an offer for the first time?** Ticking it is what most operators intend; not ticking it keeps the smart-fill pool honest. Recommend leaving it unticked and labelling it clearly, since in the default `manual_priority_smart_fill` mode an eligible offer already enters the pool without being selected.
3. **Should the workbench show performance data (EPC, clicks) on the result card?** Useful for deciding what to feature; adds a `get_performance_rows()` call per search. Recommend deferring.
4. **Should `include_all_offers` be retired once search exists?** It becomes largely redundant, but retiring it also removes a heavy-audit trigger some workflows rely on. Recommend keeping it in phase 1 and revisiting at phase 7.
5. **Should the URL pre-flight block saving a URL that fails `is_valid_frontend_winner_cta_url()`, or save it with a warning?** Recommend saving with a prominent warning — blocking would prevent an operator from storing a URL that a future validator change might accept.
6. **Should logo repair include an upload?** The `missing_logo` reason is only partly repairable without shipping a file to `assets/logos/80x80/`. A media-library picker writing `image_url_override` would close the loop; it is also the largest single piece of new UI. Recommend deferring past phase 1.
7. **How should featured-but-ineligible offers be surfaced?** Featured Order already flags them. Should the workbench also list them unprompted on page load ("3 featured offers are not currently eligible — repair"), or only on search? Recommend a small unprompted list, since it is the highest-value thing to show an operator who arrives with no query.

---

## Final recommendation

> **What is the smallest safe change that lets an administrator search for any synced offer, configure everything needed for eligibility in one place, add it to Featured Order, and verify it works — without touching unrelated plugin systems?**

Add a single **Offer Workbench** panel at the top of the Offer Setup tab, backed by one new `admin_post` handler.

The panel does four things: it searches all synced offers by exact ID or name, **bypassing `selected_only`, the allowed-type filter, and the 400-row slice entirely**; it shows one card per match with active/approved state, payout type, eligibility, the exact block reason, featured position, and selection state; it opens a compact editor exposing only the fields that clear those blockers, with validation printed beside each field; and it saves through `admin_post_tmw_cr_slot_banner_save_offer_config` — which writes only that offer's override row, its `slot_offer_ids` membership, its `slot_offer_priority` entry, and optionally appends it to the existing featured order.

That is roughly 350 lines of new PHP in `admin/admin-page.php`, two new read-only composition methods in `includes/class-offer-repository.php`, an appended CSS block, and a set of new tests. No existing method is modified except one added line in the constructor and one added line at the top of `render_slot_setup_tab()`.

Because the save never posts to `options.php`, it structurally cannot trigger the `frontend_pool_mode` reset, the `slot_offer_ids` truncation, or the priority/image-override wipe that the current bulk form causes. Because it calls the existing eligibility helpers rather than reimplementing them, it cannot drift from the frontend. Because it is one panel and one handler, removing it restores the current behaviour exactly.

The operator's loop becomes: **search → configure → save → verify → feature**, on one screen, for offer 153 or any of the other 450.
