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

## CR recommended priority

Version 1.10.0 adds a built-in, runtime-only recommendation layer for synced
CrakRevenue offers. In `manual_priority_smart_fill`, explicit operator selections
and stored manual priorities remain first, recommended eligible offers follow,
and all other eligible synced offers retain the existing smart-fill optimization
order. `smart_auto` applies the recommendation layer without a manual-selection
boost, while `selected_only` remains limited to explicitly selected offers and
keeps its existing ordering behavior.

Recommendations only affect final ordering. They never enable an offer or bypass
country targeting, disabled/inactive status, allowed offer types, URL/logo
requirements, or any other eligibility check. The catalog contains CrakRevenue
offer IDs only; it does not contain affiliate destinations, credentials, API
keys, or tracking URLs.

Site owners can replace, remove, or reorder the catalog with the
`tmw_cr_slot_banner_recommended_offer_priorities` filter. The filtered value is
an associative array of string offer IDs to positive integer ranks (lower ranks
sort first):

```php
add_filter(
    'tmw_cr_slot_banner_recommended_offer_priorities',
    static function ( $priorities ) {
        unset( $priorities['10335'] );
        $priorities['8780'] = 1;
        return $priorities;
    }
);
```
