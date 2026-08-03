# TMW CR Offer Sidebar Banner

This plugin renders a geo-aware CrackRevenue offer recommendation banner that includes an animated offer selector, built-in partner creatives, and automatic SubID tracking. Drop the
`[tmw_cr_slot_banner]` shortcode into any sidebar or narrow column to display
the animated offer recommendation experience with a pulsing call to action.

Key capabilities:

* 🇨🇦🇺🇸🇪🇺 Geo detection powered by Cloudflare/GeoIP with optional manual
  overrides.
* Animated offer selector that highlights bundled CrackRevenue offers.
* 🧩 Automatic SubID appending for each CTA click.
* 🧠 Filters that let developers override the offer catalog or targeting logic.
## Featured Offer Order (manual)

Version 1.9.15 adds a compact **Featured Offer Order** panel at the top of the
**Offer Setup** tab, for operators who want to manually pin specific offers to
the front of the banner without touching numeric priorities.

* Search synced offers by name or ID, add them to the list, drag to reorder,
  and remove — no numeric priority field is required for normal use.
* Position 1 is the offer visitors land on. Offers left out of the list
  continue to behave exactly as ordinary smart-fill offers do today.
* The list is stored as a single ordered array of offer IDs in its own option,
  `tmw_cr_slot_banner_featured_offer_ids`, saved through a dedicated
  admin-post action (`tmw_cr_slot_banner_save_featured_order`) with its own
  nonce. It is completely separate from `slot_offer_ids` and
  `slot_offer_priority`, so saving the Performance or Settings tab can never
  wipe it, and saving the featured order can never touch those other keys.
* Frontend ranking: after the existing pool has been assembled, filtered for
  eligibility, and ranked, eligible featured offers are lifted to the front in
  the exact saved order; a featured offer that fails eligibility (country,
  logo, CTA, offer type, status, etc.) is skipped, never promoted; every other
  offer keeps the relative order the existing ranking already produced. An
  empty featured list leaves current frontend behavior completely unchanged.
* This is a manual ordering control, not a new automatic ranking system — it
  does not replace or duplicate the existing offer-selection checkboxes,
  country/URL/image overrides, or the `slot_offer_priority` field, which
  remain available for advanced use.

## CrakRevenue recommended-offer priority

Version 1.9.14 includes a built-in runtime-only recommendation catalog in
`includes/recommended-offer-priorities.php`. In `manual_priority_smart_fill`,
explicitly selected/manual-priority offers remain first, followed by eligible
recommended offers and then other eligible synced offers. In `smart_auto`,
eligible recommendations precede the existing smart-auto order without a saved
manual-priority membership boost. `selected_only` retains its existing behavior.

Customize the ordered ID list with the
`tmw_cr_slot_banner_recommended_offer_priorities` filter. The helper
`tmw_cr_get_recommended_offer_priority( $offer_id )` returns its one-based rank,
or `null` for an ordinary offer. Recommendations do not override eligibility,
disabled status, country targeting, allowed offer types, logo requirements, or
CTA URL validation, and do not override explicit manual priority in
`manual_priority_smart_fill`. The catalog is not saved to WordPress options.
