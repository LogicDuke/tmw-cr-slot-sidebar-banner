=== TMW CR Slot Sidebar Banner ===
Contributors: themilisofia
Tags: affiliate marketing, crackrevenue, sidebar, banner, shortcode
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
TMW CR Slot Sidebar Banner renders an interactive CrackRevenue slot promotion in any sidebar or widget area. It detects the visitor country, rotates through a curated catalog of partner offers, and appends a configurable SubID tracking parameter to the call-to-action URL.

Use the `[tmw_cr_slot_banner]` shortcode inside any widget, sidebar, or post content to output the banner. The plugin bundles high-resolution offer assets and a 3-reel slot interface so everything renders instantly without external ad tags.

== Features ==
* Geo-targeted banner logic with per-country overrides and filters.
* Three animated slot reels driven by bundled CrackRevenue creatives.
* Customizable headline, subheadline, CTA text, and base tracking URL.
* Automatically appends a configurable SubID parameter for affiliate tracking.
* No external ad scripts — assets load directly from the plugin directory.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/tmw-cr-slot-sidebar-banner` directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Settings → TMW Slot Banner** to configure your CrackRevenue offer URLs and creatives.
4. Add the `[tmw_cr_slot_banner]` shortcode to any widget or template.

== Frequently Asked Questions ==

= How do I add country specific creatives? =
Enter a line for each country on the settings page using the format `CC|Image URL|CTA URL|CTA Text|Headline`. For example: `CA|https://example.com/ca.png|https://offer.com/ca|Play Now|Canadian Exclusive Spins`.

= Can I use the banner outside of a sidebar? =
Yes. The shortcode outputs responsive markup that adapts well to any narrow column or block area.

= Can I override the bundled offer list? =
Yes. Developers can hook into the `tmw_cr_slot_banner_offers` filter to provide their own offer array or integrate directly with the CrackRevenue API.

= Do you include graphics? =
Yes. Version 1.3.0 ships with optimized PNG assets inside `assets/img/offers/` so the slot banner works out of the box. You can still override or extend the catalog with your own creatives via filters.

== Changelog ==

= 1.6.0 =
* [TMW-CR-CTRL] Added a persistent per-offer control layer via `tmw_cr_slot_banner_offer_overrides` (enabled, final URL, image URL, countries, CTA text, label, notes).
* [TMW-CR-CTRL] Added effective synced-offer resolution with layered URL/image/CTA fallback rules and country eligibility filtering.
* [TMW-CR-DASH] Extended Offers and Slot Setup admin views with per-offer enablement, destination/image controls, country controls, and effective status indicators.
* [TMW-CR-CTRL] Added automated coverage for override resolution, country filtering, legacy fallback behavior, admin override save/render path, and API-key safety.

= 1.7.0 =
* [TMW-CR-IMG] Added synced-offer automatic image resolver chain (manual override → legacy override → local alias match → explicit remote map → placeholder fallback).
* [TMW-CR-IMG] Added local catalog alias strategy for normalized brand-name matching without frontend API/image discovery calls.
* [TMW-CR-DASH] Added admin image-source badges (manual, auto-local, auto-remote, placeholder) and wired slot setup preview to the effective image source.
* [TMW-CR-IMG] Expanded tests to cover resolver order, alias normalization, remote-map fallback, placeholder fallback, and frontend slot normalization compatibility.

= 1.5.0 =
* [TMW-CR-DASH] Rebuilt admin into a WordPress-native dashboard with Overview, Offers, Slot Setup, and Settings tabs.
* [TMW-CR-ADMIN] Added synced-offer explorer filtering, sorting, server-side pagination, and selected-for-slot indicators.
* [TMW-CR-ADMIN] Added operations summary cards, slot setup workflow improvements, and dedicated admin dashboard styling.

= 1.4.2 =
* [TMW-CR-FIX] Fixed CrakRevenue envelope parsing so `response/status/httpStatus/data/errors/errorMessage` wrappers are not misclassified as offer rows.
* [TMW-CR-SYNC] Added explicit nested wrapper unwrapping (`response`, `data`, `results`) and improved response shape diagnostics for envelope payloads.
* [TMW-CR-API] Added tests covering live envelope payload shapes (`response.data`, `response.data.data`) to ensure rows import and local storage updates correctly.

= 1.4.1 =
* [TMW-CR-FIX] Hardened CrakRevenue offer sync extraction/normalization for response shape variants.
* [TMW-CR-SYNC] Added sync diagnostics, soft-failure preservation, and richer admin notices.

= 1.3.0 =
* Replaced the static banner with a three-reel slot interface and heartbeat CTA.
* Added an offer catalog with bundled PNG assets and geo filtering.
* Ensured CSS/JS load using `plugins_url()` for consistent paths.
* Prevented empty shortcode output when no geo offers are available.

= 1.2.0 =
* Initial code-only release for CrackRevenue slot banner integration.
