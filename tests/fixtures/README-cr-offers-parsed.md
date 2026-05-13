# cr_offers_parsed.csv — fixture provenance and usage notes

## Source

Parsed from the CrakRevenue affiliate dashboard "All Offers" view, exported as two
PDFs (`Offers - CrakRevenue.pdf` and `Offers 2 - CrakRevenue.pdf`) captured on
2026-05-13. Extraction used `pdftotext -layout` plus a deterministic ID/payout-type
resolver anchored on each card's `ID NNNN` token.

## Row counts

The CR UI reported 274 offers (page footers: "1 - 200 of 274" on PDF1 and
"201 - 274 of 274" on PDF2). The parser captured **273 unique offer IDs**. The
1-offer gap is a known PDF/OCR limitation: one card had its `ID NNNN` rendered
across a column boundary that `pdftotext -layout` could not recover into a
single contiguous token. This row is not present in the fixture and is not
synthesized.

## Header (canonical)

```
cr_id,name,approval,payout_type,page,pdf
```

Six columns, exact order, no trailing whitespace. UTF-8, CRLF line endings as
produced by the source parser.

## Distributions

Approval status:

```
Approved = 223
Required = 50
```

Visible CR dashboard "Payout Type" label:

```
PPS               = 89
Revshare Lifetime = 77
Revshare          = 39
SOI               = 27
DOI               = 23
Multi-CPA         = 18
CPC / CPI / CPM   = 0
```

These distributions are asserted by `cr_offers_parsed_fixture_integrity` in
`tests/run-tests.php` and any drift causes the test suite to fail.

## Anchor row

```
cr_id       = 235
name        = Exposed Webcams / Live Free Fun - Revshare Lifetime
approval    = Required
payout_type = Revshare Lifetime
page        = 21
pdf         = pdf1.txt
```

This row exists by construction and is verified by the integrity test.

## Scope of the fixture

This file is for tests and future ID-level reconciliation work only. It is
plain UTF-8 text, not binary or packaged data, and is not consumed by any
runtime code path in the plugin (offer repository, sync service, admin pages,
frontend banner). Modifying this fixture has no effect on production behavior.

The CrakRevenue Affiliate API's `payout_type` field is a payout-calculation
method enum (`cpa_flat`, `cpa_percentage`, `cpa_both`, `cpc`, `cpm`) and is
**not** the same taxonomy as the dashboard's visible "Payout Type" label
column captured here. Comparisons between this fixture and local
`raw payout_type` counters must account for that semantic gap.

## Known parser-fallback name rows (do not assert exact names)

For these 10 offer IDs the `name` column captured PDF chrome (badge text,
carousel headers, or `New` / `close` glyphs) instead of the canonical offer
name because the corresponding cards used a non-standard column layout
(typically the "Recommended Offers" carousel on page 2 of both PDFs):

```
779, 2994, 3785, 6224, 8780, 8865, 9927, 10375, 10385, 10389
```

For these rows the **`cr_id`, `approval`, and `payout_type` columns remain
reliable** and are cross-verified against the source PDF cards. Only the
`name` column is cosmetically degraded. The integrity test deliberately
does not assert exact name strings on these IDs.

## Do not regenerate from scratch

Do not regenerate this CSV with synthetic placeholder rows (for example
`1000,CR Parsed Offer 1000 - PPS,Approved,PPS,1,pdf1.txt`). The integrity
test rejects any fixture whose IDs form a sequential `1000..1272` block or
whose names match `/^CR Parsed Offer\s+\d+/i`, and the suite will fail loudly
if either pattern reappears.
