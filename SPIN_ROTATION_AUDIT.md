# Frontend Spin Rotation Audit

## Scope and production inputs

This audit traced the rendered shortcode from the repository pool through the three-reel result. The supplied production Featured Offer Order is `8780, 153, 10022, 3778, 8266, 1659`; current eligibility leaves the exact expected frontend pool `8780, 153, 10022`. The audit did not change or reinterpret eligibility.

## 1. PHP pool at every stage

1. `get_frontend_slot_offers()` loads synced offers, overrides, selected IDs, priorities, and pool mode. In smart-fill modes it evaluates selected offers and then all remaining synced offers. In selected-only mode it evaluates only selected IDs.
2. `evaluate_synced_offer_for_frontend_pool()` is the eligibility gate. With the supplied production state, 8780, 153, and 10022 return effective offer records. Offers 3778 (`missing_valid_cta`), 8266 (`approval_blocked`), and 1659 (`missing_valid_cta`) return `null` and do not enter the pool.
3. `rank_offers_for_slot()` orders eligible records. Manual-priority smart-fill ranks selected and unselected groups separately and then concatenates and deduplicates them by ID. This does not suppress eligible featured records.
4. Thin-pool legacy top-up runs only while the pool has fewer than three entries and stops once it reaches three. It does not slice a pool that already contains the three eligible production offers.
5. The last normalization is `array_values(array_filter($offers))`; there is no final one-offer slice or limit.
6. `apply_featured_offer_order()` builds an ID map, lifts each configured ID that survived eligibility, skips missing/ineligible IDs, then appends every unused record in its previous relative order. For the production inputs its output is exactly `[8780, 153, 10022]`; it reorders and deduplicates malformed duplicate IDs but does not collapse the pool.
7. `build_slot_data()` retains the complete returned array in `offers`. It uses index zero only for initial CTA metadata.
8. `render_shortcode()` JSON-encodes that complete array into `data-slot-offers`. No slice occurs during serialization. Under `WP_DEBUG`, `[TMW-BANNER-POOL]` now records final IDs/count and `[TMW-SPIN-AUDIT]` records the exact raw JSON used for the attribute. Existing `[TMW-FEATURED-ORDER]` diagnostics record configured and surviving featured IDs.

Therefore the exact PHP stage sequence for the supplied state is: eligible effective records `[8780,153,10022]`; ranked pool containing those three (order before the final featured permutation is mode-dependent); final featured pool `[8780,153,10022]`; `build_slot_data()['offers']` `[8780,153,10022]`; JSON contains three objects in that order.

## 2. Exact frontend data

`parseOffers()` reads and parses `data-slot-offers`, requires an array, and filters only malformed records lacking `id` or `image`. The three production effective records have both fields, so JavaScript receives `[8780,153,10022]`. `state.offers` is assigned that entire parsed array and is never reduced or deduplicated in JavaScript.

The WP_DEBUG diagnostics expose the exact environment-specific JSON (including URLs and image paths) without leaking it in normal production logging. Candidate IDs are logged on every selection as `[TMW-SPIN-AUDIT] candidates=8780,153,10022`.

## 3. JavaScript state transitions

Before the fix, initialization parsed all three offers, populated every reel with repeated shuffled copies of all offers, and left the result/CTA hidden. On each click, `spin()` rebuilt decorative sequences, then called `setResult()`. `setResult()` unconditionally assigned `winner = state.offers[0]`. `renderFinalSelection()` copied that one winner to the top cell of all three reels so the required three-logo match occurred. `finishSpin()` correctly used `results[0]` for `currentWinningOffer`, result text, and CTA; it did not replace it later. Every click simply arrived with 8780 already selected again.

After the fix, the first selection uses index zero deterministically and sets `hasSelectedInitialOffer`. Each later selection maps `Math.random()` across the complete `state.offers` array, then passes that selected offer through the unchanged three-logo, result, and CTA flow. Debug output records candidates, chosen index, and final ID.

## 4. Root cause and explicit answers

The exact root cause was the deliberate hard lock in `setResult()`: it selected `state.offers[0]` on every spin. Randomness existed only in `buildReelOfferSequence()` and therefore changed passing decorative logos, never the actual winner object.

* **A. Does PHP send all three?** Yes: all three survive eligibility, ranking, featured reordering, slot-data construction, and serialization.
* **B. Does JavaScript receive all three?** Yes: all three valid records populate `state.offers`.
* **C. What was randomized?** Only decorative reel order; the winner was not randomized.
* **D. Was `state.offers[0]` reassigned after every animation?** The array was not reassigned, but its index-zero object was freshly chosen as winner before every animation.
* **E. Was final CTA/winner overwritten after completion?** No. Completion faithfully displayed the already locked winner.
* **F. What did position one mean?** Before the fix it meant every spin. The intended and now implemented meaning is initial result only; it still controls pool precedence and the visitor's deterministic first winner.

## 5. Smallest safe fix

Move only winner-index choice into a tiny pure helper: return index zero before an initial winner has been selected, and uniformly select an index from the full pool afterward. Keep the existing PHP order, eligibility, reel animation, forced three-logo rendering, CTA update, and tracking behavior intact.

## 6. Files and methods changed

* `assets/js/slot-selection.js`: pure `selectOffer()` helper.
* `assets/js/slot-banner.js`: `setResult()` uses the helper and tracks whether the deterministic initial selection has occurred; state initialization and debug diagnostics support that transition.
* `tmw-cr-slot-sidebar-banner.php`: registers the helper dependency and adds gated serialized-data diagnostics.
* `includes/class-offer-repository.php`: adds a gated final-pool ID/count diagnostic after featured ordering.
* `tests/slot-selection.test.js` and `tests/run-tests.php`: deterministic selection, pool, ordering, ineligible-skip, and serialization regressions.

## 7. Regression-test plan

PHP coverage constructs eligible 8780, 153, and 10022 plus an ineligible featured record, saves featured order with the ineligible record interleaved, asserts final IDs `[8780,153,10022]`, and asserts all three IDs occur in serialized frontend JSON. Node coverage injects deterministic random sources to prove initial index zero, later index one (153), later index two (10022), empty safety, and no pool mutation. Existing PHP tests continue to cover eligibility, ranking, featured stable ordering, three-reel matching, CTA, and tracking.

## 8. Untouched systems

No eligibility rule, country targeting, CTA validation, CrakRevenue sync, Featured Order admin UI, Offer Workbench, recommendation catalog, tracking URL, cache behavior, CSS/layout, or unrelated asset was changed. Jerkmate remains eligible and remains the deterministic position-one initial winner.
