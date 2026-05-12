# TMW Banner Phase 1 Audit — CrakRevenue Country Availability

## Scope
Audit-only analysis of sync, storage, admin filtering, and frontend eligibility behavior for country metadata.

## 1) API country fields found

| Field name | Requested by API fields list | Normalized mapping target | Populated in code path? | Example format after normalization | Reliability recommendation |
|---|---|---|---|---|---|
| `accepted_countries` | Yes | `accepted_countries` | Yes (preferred) | `['US','CA','GB']` (uppercase array) | **Primary** field for PPS country eligibility |
| `accepted_country` | Yes | `accepted_countries` fallback alias | Yes | `['US']` or parsed from CSV | Valid fallback alias |
| `countries` | No (not explicitly requested) | `accepted_countries` fallback alias | Only if API includes it anyway | `['US','DE']` | Keep as compatibility fallback |
| `country_codes` | No (not explicitly requested) | `accepted_countries` fallback alias | Only if API includes it anyway | `['US','FR']` | Keep as compatibility fallback |
| `performs_in` | Yes | `performs_in` | Yes | `['US','GB']` uppercase array | Secondary/fallback signal only |
| `top_countries` | No (not explicitly requested) | `performs_in` fallback alias | Only if API includes it anyway | `['US','CA']` | Compatibility fallback |
| `performing_countries` | No (not explicitly requested) | `performs_in` fallback alias | Only if API includes it anyway | `['AU','NZ']` | Compatibility fallback |
| `optimized_for` | Yes | `optimized_for` | Yes | `['mobile','desktop']` list | Not country eligibility; supporting metadata |

### Live payload audit status
Live diagnostics are now emitted via safe log lines tagged `[TMW-BANNER-GEO-AUDIT]` during **Test Connection** (raw rows) and **Sync Offers** (post-sync stored rows), with only non-secret metadata.

## 2) PPS offer sample table
Populate this from `debug.log` after running Test Connection + Sync Offers and filtering for target PPS names.

| offer_id | offer_name | payout_type | status | accepted_countries | performs_in | optimized_for |
|---|---|---|---|---|---|---|
| 10358 | Joi - PPS - T1 (Premium) | PPS | (from log) | (from log sample/count) | (from log sample/count) | (from log sample) |
| 10280 | Joi - PPS - Tier 2 | PPS | (from log) | (from log sample/count) | (from log sample/count) | (from log sample) |
| ... | Instabang - PPS - Premium / Joi tiers | PPS | (from log) | (from log sample/count) | (from log sample/count) | (from log sample) |

## 3) Current behavior conclusion
- Country fields are requested and synced into normalized offer payload (`accepted_countries`, `performs_in`, `optimized_for`).
- Admin dashboard model exposes/filter-supports `accepted_country` and `performs_in` families.
- Frontend country eligibility currently does **not** enforce synced `accepted_countries`; it uses manual overrides, and only falls back to legacy catalog countries when no overrides and no synced offer are present.
- Phase 1 gap: enforce PPS eligibility against synced country availability data.

## 4) Phase 1 implementation recommendation
1. Use `accepted_countries` as the **primary** country-eligibility field.
2. If `accepted_countries` is empty, allow optional fallback to `performs_in` (feature-flagged / explicit behavior).
3. For empty country data in both fields, prefer **manual-review-safe default** (reject for PPS until approved), unless business explicitly wants global-by-default behavior.

## Safe diagnostics added
- Tag: `[TMW-BANNER-GEO-AUDIT]`
- Logged fields only: offer id/name/payout_type/status, present country fields, accepted/performs counts + first 10 samples, optimized_for first 10.
- No API key, no tokenized URL, no full response body, no tracking link logged.
