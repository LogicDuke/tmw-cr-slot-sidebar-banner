# [TMW-AUDIT] PR #60 — Reconcile CR payout counts vs local detected payout types

## Scope and constraints
- Audit-only. No runtime logic changed.
- Findings below are based on repository code paths and test suite behavior.
- Live 431-row bucket math cannot be proven from this repo alone because no persisted live `tmw_cr_slot_banner_synced_offers` option payload is present in-repo.

## 1) Payout normalization and detection rules

### Canonical payout alias mapping (`normalize_filter_family_value('payout_type', ...)`)
- `cpa_percentage` -> `revshare`
- `revshare` / `revenue_share` -> `revshare`
- `cpa` / `cpa_both` / `multi_cpa` / `multi-cpa` / `multicpa` / `hybrid` -> `multi_cpa`
- `cpa_flat` -> `revshare_lifetime`
- `revshare_lifetime` -> `revshare_lifetime`
- `pps` -> `pps`
- `soi` -> `soi`
- `doi` -> `doi`
- `cpi` -> `cpi`
- `cpm` -> `cpm`
- `cpc` / `ppc` -> `cpc`
- unknown payout strings log `[TMW-BANNER-PAYOUT-NORM]` and fall through lowercased raw string.

### Name/raw multi-signal detection (`get_offer_type_keys`)
- Detects from both `name` first and `payout_type` second via regex keys:
  `fallback`, `smartlink`, `revshare`, `soi`, `doi`, `cpa`, `cpl`, `cpc`, `cpi`, `cpm`, `pps`.
- Important: this means `pps` / `soi` can be detected from **name** even when raw `payout_type` is `cpa_flat`.

### Admin payout filter values (`get_admin_offer_filter_values` + `extract_admin_raw_filter_values`)
- Merges metadata + raw normalized values.
- For `payout_type`, it explicitly appends:
  - normalized raw `payout_type` value(s)
  - normalized `get_offer_type_keys(...)` values
  - `multi_cpa` if name contains `cpa`
  - `multi_cpa` if raw payout_type is exactly `cpa_flat`
- Therefore one offer can contribute to multiple payout families simultaneously.

## Answers to explicit questions
- Does `cpa_flat` currently normalize to `revshare_lifetime`? **Yes.**
- Does `cpa_flat` also contribute `multi_cpa`? **Yes** (explicit add in `extract_admin_raw_filter_values`).
- Does `get_offer_type_keys()` detect PPS/SOI from offer name even when raw `payout_type` is `cpa_flat`? **Yes**.
- Can one offer contribute multiple payout families? **Yes**, by design.

## 2) Local count bucket computability (requested A/B/C/D)
- A/B/C/D exact counts for the live 431-row dataset are **not reproducible from repo-only state** because no live option snapshot is committed.
- What can be proven:
  - Local dashboard matching uses detected/normalized families, not raw-only families.
  - `all payout filters selected = 431` is consistent with union-style match against combined families across all synced offers.
  - A single raw `cpa_flat` row can increment Revshare Lifetime and Multi-CPA families, and can also increment PPS/SOI via name detection.

## 3) Reconcile why local shows PPS=94, SOI=28, Revshare Lifetime=181, all=431
Most likely code-backed decomposition:
1. **PPS / SOI inflation vs CR raw payout_type**: rows whose name includes PPS/SOI are matched even if raw payout_type is `cpa_flat`.
2. **Revshare Lifetime inflation**: all raw `cpa_flat` rows normalize to `revshare_lifetime`.
3. **Additional overlap**: same row may also contribute `multi_cpa` and/or `fallback`.
4. **All selected = 431**: because each row has at least one matched family in the merged payout-family set.

These are expected from current logic and covered by tests.

## 4) CR dashboard semantics comparison (what can/cannot be proven)
From this repo alone, cannot prove CR UI-side semantics such as:
- whether CR applies implicit approval/account visibility filters,
- whether CR excludes fallback/synthetic group rows in UI,
- whether CR uses different payout derivation rules,
- whether API/UI pagination or endpoint differences apply.

This repo only proves local behavior after API ingestion and local normalization.

## 5) Sync import/source audit
- Sync uses client `get_offers()` then `extract_offer_rows(...)`; supports multiple response envelope shapes.
- It stores sync diagnostics meta:
  `last_raw_row_count`, `last_imported_count`, `last_skipped_count`, soft-failure flags/timestamps.
- Normalized offer rows are saved; full raw API payload is not persisted as a complete archive.
- Failed/invalid rows are counted as skipped; previous offers are preserved on soft/transport failure.

## 6) cpa_flat mapping correctness (business question)
- Why it maps today: explicit alias map ties CR `cpa_flat` to local `revshare_lifetime` canonical filter key.
- Tests enforcing behavior include:
  - revshare_lifetime filter must return `cpa_flat` rows,
  - payout type dropdown/data model includes revshare_lifetime for cpa_flat,
  - raw cpa_flat can also match `multi_cpa`,
  - PPS/SOI-by-name matching works even when raw cpa_flat.
- Historical rationale comments: no explanatory business-comment found near the alias declaration; behavior is test-enforced contract.
- Changing `cpa_flat` mapping now would alter dashboard filter totals and may alter frontend type eligibility when allowed types rely on detected keys.
- Yes, remapping likely reduces local Revshare Lifetime count toward CR’s lower number, but exact delta needs live row-by-row recalculation.

## 7) Safe next options

### Option A (lowest risk)
Keep logic; relabel local metrics as **Detected Local Types** and avoid direct CR parity claims.

### Option B (observability)
Add side-by-side counters in dashboard:
- Raw CR payout_type counts,
- Detected local type counts,
- Frontend-eligible counts.

### Option C (behavior change, guarded)
After business confirmation, remap `cpa_flat` (e.g., to `multi_cpa` or dedicated `cpa_flat` family) with full regression updates and rollout note.

## Root cause summary
Primary mismatch root cause: local dashboard counts are based on **normalized + name-detected + metadata-merged payout families**, not raw CR payout_type alone, and `cpa_flat` is currently canonically treated as `revshare_lifetime` while also able to contribute `multi_cpa` and name-derived families.

## Minimal safe PR plan (no frontend behavior change)
1. Add audit/telemetry counters only (no eligibility/filter behavior changes).
2. Expose raw-vs-detected-vs-eligible in admin summary.
3. Add regression tests asserting counts are reported separately.
4. Keep frontend selection and slot behavior unchanged unless explicit business sign-off.

## Explicit confirmation
- Do not change frontend behavior in this audit PR.
