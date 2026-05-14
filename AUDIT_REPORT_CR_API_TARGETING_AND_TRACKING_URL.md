# CrakRevenue API Targeting + Tracking URL Audit

Purpose: audit/discovery only for API response shapes; no frontend behavior changes.

## Enable
- `WP_DEBUG` true **or** `TMW_CR_API_AUDIT` true
- `WP_DEBUG_LOG` true

## Safe Trigger
From wp-admin, submit `admin-post.php?action=tmw_cr_slot_banner_audit_api` with valid nonce and `manage_options`.

## Probes
- Offer fields: id, name, description, preview_url, status, payout_type, default_payout, percent_payout, require_approval, use_target_rules, terms_and_conditions
- Targeting candidate fields: targeting, target_rules, targeting_rules, rules, offer_targeting, geo_targeting, countries, allowed_countries
- Tracking methods: Affiliate_Offer.getTrackingUrl, generateTrackingLink, findOneTrackingLink, getTrackingLink

## Logs
Find `debug.log` lines tagged `[TMW-CR-AUDIT]`.

Examples:
- `grep "\[TMW-CR-AUDIT\]" wp-content/debug.log`

## Safety
- No API keys logged (redacted).
- No tracking URLs persisted or used on frontend.
- No frontend banner/CTA/layout/filtering behavior changes.

## Next Step
Implement field-aware production logic only after confirming real live response shapes from logs.
