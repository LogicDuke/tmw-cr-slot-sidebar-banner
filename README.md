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
## CrakRevenue recommended-offer priority

Version 1.9.15 includes a built-in runtime-only recommendation catalog in
`includes/recommended-offer-priorities.php`. In `manual_priority_smart_fill`,
offers with an explicitly saved manual priority remain first, followed by eligible
recommended offers and then other eligible synced offers (including saved pool
members without an explicit priority). In `smart_auto`,
eligible recommendations precede the existing smart-auto order without a saved
manual-priority membership boost. `selected_only` retains its existing behavior.
The ranked order is preserved through legacy top-up, the public offer filter,
and final HTML serialization. Cached output is invalidated when the plugin
version, settings, synced offers, or offer overrides change.

Customize the ordered ID list with the
`tmw_cr_slot_banner_recommended_offer_priorities` filter. The helper
`tmw_cr_get_recommended_offer_priority( $offer_id )` returns its one-based rank,
or `null` for an ordinary offer. Recommendations do not override eligibility,
disabled status, country targeting, allowed offer types, logo requirements, or
CTA URL validation, and do not override explicit manual priority in
`manual_priority_smart_fill`. The catalog is not saved to WordPress options.
