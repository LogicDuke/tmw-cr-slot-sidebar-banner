# Manual Image Override Rendering Audit

## Scope and reproduction

The production case is synced offer **153**, `Slut Roulette - PPS`, with this saved Workbench value:

`https://top-models.webcam/wp-content/plugins/tmw-cr-slot-sidebar-banner/assets/logos/80x80/Slut-Roulette.png`

The URL itself is valid. The defect was an internal field mismatch, not URL availability, eligibility, or browser loading.

## End-to-end data flow

### 1. Workbench save

`handle_save_offer_config()` reads `tmw_offer_config[image_url_override]`, trims it, sanitizes it with `esc_url_raw()`, merges only the target offer row, and passes the complete map to `save_offer_overrides()`. The repository sanitizes that row again and persists it under the repository's offer-overrides option. The canonical per-offer key is **`image_url_override`**. The legacy settings map `offer_image_overrides[offer_id]` remains supported and is deliberately not rewritten by the Workbench save.

### 2. Repository state and resolution

`get_offer_setup_state()` loads the canonical value at **`state['override']['image_url_override']`**. `evaluate_synced_offer_for_frontend_pool()` independently performs type, skipped-offer, country, status/approval, account, general eligibility, and CTA checks, then returns the effective record. This explains why image presentation and eligibility can diverge: a usable effective `image` satisfies the existing image path (including placeholder fallback), while reel presentation separately depends on `logo_url`.

`resolve_synced_offer_image()` already had the correct image precedence:

1. `offer_overrides[offer_id].image_url_override`;
2. legacy `settings['offer_image_overrides'][offer_id]`;
3. local catalog image;
4. explicit remote thumbnail;
5. generated placeholder.

Bundled reel logos are separately resolved by manifest offer ID first and then the brand map. There are two frontend-record construction paths:

* The normal synced path uses `get_effective_offer_record()`. Before this fix, it placed the correctly resolved manual URL only in **`image`**, but populated **`logo_url`** directly from the manifest/brand resolver. For offer 153, where that resolver returned no bundled logo, `logo_url` was empty.
* The thin-pool override-only fallback uses `get_override_only_effective_offer_record()` for a saved override whose offer is absent from the synced map. It also called the manifest/brand resolver directly. Its immediately following empty-`logo_url` guard could therefore discard an otherwise valid override-only offer even when the override supplied a canonical or legacy manual logo.

The smallest safe correction is confined to PHP record construction: both the synced and override-only paths call the shared `get_frontend_offer_logo_url()` helper. It gives the canonical per-offer `image_url_override` highest precedence, then `settings['offer_image_overrides'][offer_id]`, and only then the unchanged manifest/brand-map resolver. The override-only empty-logo guard now evaluates this shared result, so a supplied manual logo survives while a record with no manual or bundled logo remains excluded. The helper does not promote remote thumbnails or placeholders into reel logos, so the existing text and exclusion behavior remains intact.

### 3. Frontend serialization

`build_slot_data()` obtains the repository pool without remapping its records. `render_shortcode()` JSON-encodes that pool and writes it to the `data-slot-offers` attribute. Each effective synced record includes both **`image`** (the general image-resolution field) and **`logo_url`** (the reel-consumed field). After the fix, offer 153's `logo_url` contains the exact saved URL.

### 4. Frontend JavaScript

`parseOffers()` parses `data-slot-offers` and retains records having `id` and `image`. Reel rendering does not read `image`; `renderReelFace()` reads **`offer.logo_url`**. It creates an `<img>` when that property is non-empty, hides the already-created text fallback on `load`, and removes the image and restores text on `error`. No competing JavaScript image property was added.

## Explicit answers

**A. Which option key stores the manual override?**  The per-offer overrides option stores it as `offer_overrides[153]['image_url_override']` (the repository option is `tmw_cr_slot_banner_offer_overrides`). A legacy `offer_image_overrides[153]` settings entry is also read, at lower precedence.

**B. Which PHP field contains it after loading?**  `get_offer_setup_state()` exposes it as `state['override']['image_url_override']`; effective record image resolution also puts it in `image`.

**C. Which field is serialized into `data-slot-offers`?**  Both `image` and `logo_url` are serialized. The fix ensures the override is normalized into `logo_url` as well as remaining in `image`.

**D. Which field does `slot-banner.js` read?**  `renderReelFace()` reads `offer.logo_url` for the `<img>` source. `parseOffers()` separately requires `offer.image` when admitting an offer.

**E. At what exact step was the override lost or ignored?**  It was ignored during frontend record construction. The synced path's `image` used `get_effective_image()` and received the override, while its `logo_url` bypassed that manual value and called `get_offer_logo_url()` (manifest/brand map only). The override-only path likewise called `get_offer_logo_url()` and could then reject the record at its empty-logo check. Serialization and JavaScript faithfully carried/read whatever `logo_url` those constructors produced.

**F. Why can the offer be eligible while still rendering text?**  Eligibility and `parseOffers()` can succeed because the effective `image` is non-empty (manual, catalog, remote, or placeholder) and all type/country/status/CTA gates pass. Reel rendering is a later, independent decision based on `logo_url`; an empty value produces text, as does an image load error.

## Regression boundary

The change does not touch eligibility, CTA validation, country targeting, Featured Order, ranking/spin selection, tracking, sync, caching, remote image resolution, placeholders, or production JavaScript. Tests cover persistence, the synced path, canonical and legacy override-only paths, manual precedence over manifest/brand resolution, bundled and no-logo fallback behavior, the exact offer 153 URL in serialized JSON, unrelated-offer behavior, `<img>` creation, successful-load text hiding, error fallback, and empty-logo text rendering.
