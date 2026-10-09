# ShareSphere - Smart Matching Engine

> **Phase 8 Design Document** - `docs/matching.md`
>
> _These weights, thresholds and formulas are **proposed design choices made to satisfy the spec requirements** (spec 8.2/8.3/8.4/8.5/Appendix C). They are **not** trained, empirically proven, or claimed to be optimal. Reviewers should treat every numeric parameter as a starting point subject to tuning._

---

## 1. Purpose

Match NGO requirements with available donor donations using a four-component weighted score, returning ranked results with human-readable explanations. The system operates entirely on snapped public coordinates (D-9) -- exact donor locations are never exposed via matching queries or distance reporting.

---

## 2. Eligibility Filters (M8.1 - spec 8.3)

Before any ranking, both the requirement and the donation must pass **all** of the following gates. A pair that fails any gate is excluded entirely -- no score is computed.

| # | Rule | SQL predicate |
|---|------|---------------|
| 1 | Donation is available | `d.status IN ('active','partially_allocated')` |
| 2 | Donation has stock | `d.available_quantity > 0` |
| 3 | Donation is not expired | `d.expires_at IS NULL OR d.expires_at > now()` |
| 4 | Requirement is open | `r.status IN ('active','partially_fulfilled')` |
| 5 | Requirement has outstanding quantity | `r.quantity_needed - r.quantity_allocated > 0` |
| 6 | NGO is verified | `n.verification_status = 'verified'` |
| 7 | NGO user is active | `nu.account_status = 'active'` |
| 8 | Donor user is active | `du.account_status = 'active'` |
| 9 | Category is eligible | `d.category_id = r.category_id` OR `category_compatibility` row exists (both directions) |
| 10 | Condition meets minimum | donation condition rank >= requirement `min_condition` rank (`new>like_new>good>fair`) |
| 11 | Donation is within radius | `ST_DWithin(d.location_public, r.location, r.radius_km * 1000)` -- uses snapped `location_public` to preserve privacy |
| 12 | No duplicate open request | `NOT EXISTS (donation_requests where ngo+donation+requirement, status in pending/accepted)` |

> **Privacy note (D-9):** Rule 11 explicitly uses `d.location_public` (snapped 0.005 deg ~550 m). Distance values returned via API are computed from snapped coordinates, so even exact-distance probing cannot recover the donor precise address.

### Database index relied on

```sql
CREATE INDEX IF NOT EXISTS idx_donations_location_public_gist
    ON donations USING GIST (location_public);
```

---

## 3. Scoring Formula (M8.2 - spec 8.2/8.5)

### 3.1 Weights

| Weight constant | Value | Component |
|-----------------|-------|-----------|
| `WEIGHT_ITEM`   | 0.35  | Item/category compatibility (S_item) |
| `WEIGHT_DIST`   | 0.30  | Proximity within radius (S_dist) |
| `WEIGHT_URG`    | 0.20  | Urgency + deadline pressure (S_urg) |
| `WEIGHT_QTY`    | 0.15  | Quantity fit (S_qty) |
| **Sum**         | **1.00** | |

> Weights were chosen to prioritise category relevance and proximity. They are design choices, not trained values.

### 3.2 Component definitions

**S_item** -- item compatibility (0-100)
- Exact category match: 100
- Compatible via `category_compatibility.score_factor`: value of score_factor (default 60 per D-12)
- Clamped to [0, 100]

**S_dist** -- spatial proximity (0-100)

    S_dist = 100 * (1 - d / R)    clamped to [0, 100]

where `d` = distance in metres (ST_Distance on public snapped points), `R` = radius_km * 1000.

**S_urg** -- urgency + deadline boost (0-100)

    base = {low: 25, medium: 50, high: 75, critical: 100}
    boost = 25 * (1 - days_left / 14)   when 0 <= days_left <= 14
    S_urg = min(100, base + boost)

**S_qty** -- quantity fit (0-100)

    fit = min(donation.available_quantity, requirement.outstanding)
    S_qty = 100 * fit / outstanding

### 3.3 Composite score

    MatchScore = 0.35*S_item + 0.30*S_dist + 0.20*S_urg + 0.15*S_qty

All values rounded to 2 decimal places.

### 3.4 Score bands

| Band   | Range       |
|--------|-------------|
| High   | >= 75       |
| Medium | 50 - 74.99  |
| Low    | < 50        |

### 3.5 Tie-break (deterministic)

`score DESC, distance_meters ASC, donation_id ASC`

---

## 4. Golden Fixture Set (M8.2 - spec Appendix C)

Ten pre-computed pairs, hand-calculated by the developer, used as evaluation baseline.

| # | Category | Distance | Radius | Urgency | days_left | avail | outstanding | S_item | S_dist | S_urg | S_qty | Score | Band |
|---|----------|---------|--------|---------|-----------|-------|-------------|--------|--------|-------|-------|-------|------|
| 1 | Exact | 0 m | 25 km | critical | 0 | 20 | 12 | 100 | 100 | 100 | 100 | **100.00** | High |
| 2 | Exact | 5 km | 25 km | high | none | 12 | 12 | 100 | 80 | 75 | 100 | **87.00** | High |
| 4 | Compatible(60) | 5 km | 25 km | high | none | 20 | 10 | 60 | 80 | 75 | 100 | **76.00** | High |
| 3 | Exact | 10 km | 25 km | medium | none | 6 | 12 | 100 | 60 | 50 | 50 | **66.50** | Medium |
| 9 | Exact | 2 km | 5 km | medium | 14 | 1 | 100 | 100 | 60 | 50 | 1 | **56.65** | Medium |
| 5 | Exact | 20 km | 25 km | low | none | 8 | 12 | 100 | 20 | 25 | 66.67 | **57.50** | Medium |
| 6 | Compatible(60) | 15 km | 25 km | medium | 7 | 5 | 12 | 60 | 40 | 59.64 | 41.67 | **50.46** | Medium |
| 10 | Compatible(60) | 4 km | 5 km | critical | 3 | 50 | 50 | 60 | 20 | 100 | 100 | **72.00** | Medium |
| 7 | Exact | 24 km | 25 km | low | none | 100 | 1 | 100 | 4 | 25 | 100 | **49.20** | Low |
| 8 | Compatible(60) | 20 km | 25 km | low | none | 3 | 12 | 60 | 20 | 25 | 25 | **35.75** | Low |

Expected ranking order: 1, 2, 4, 3, 9, 10, 7, 5, 6, 8 -- verified in `MatchScorerTest::testGoldenSetRankingOrder`.

---

## 5. API Endpoints

### 5.1 GET /api/matches

Query parameters (exactly one required):
- `requirement_id` -- integer, NGO owner only; returns ranked donations
- `donation_id` -- integer, donor owner only; returns ranked NGO requirements

Privacy guarantee: `latitude_public`/`longitude_public` are snapped values; `address_text` is never present.

### 5.2 GET /api/map/donations

Query parameters (at most one):
- `requirement_id` -- shows eligible candidates for that requirement
- `bbox` -- `minLng,minLat,maxLng,maxLat` -- general bounding box

Returns up to 200 markers with snapped public coordinates only.

---

## 6. Assumptions and Limitations

| # | Note |
|---|------|
| A1 | Weights (0.35/0.30/0.20/0.15) are design choices; no user study or ML training was performed |
| A2 | Category compatibility is symmetric (both directions checked) |
| A3 | Location matching uses snapped public coordinates, introducing up to ~550 m imprecision (D-9 privacy trade-off) |
| A4 | Donations with `expires_at = NULL` never expire |
| A5 | S_urg deadline boost only applies within 14 days |
| A6 | Scoring is computed in PHP (not SQL) for independent unit testability |

> "The matching algorithm surfaces relevant candidates, not guarantees of suitability." -- spec 8.6/Appendix C
