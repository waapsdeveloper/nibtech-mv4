# Manual Adjustment Solution

## Problem Statement

There are two flows that can conflict:

1. **Distribution Flow**: When stock is pushed and distributed via formulas
   - Manual push → `manual_adjustment` = +1
   - Distribution runs → updates `listed_stock` → calls API with `listed_stock`
   - API now has the distributed `listed_stock` value

2. **Sync Flow**: When page loads or sync runs
   - Fetches from API → gets the `listed_stock` value (set by distribution)
   - Updates `listed_stock` from API
   - `manual_adjustment` remains unchanged

## Solution Architecture

### Core Principle
- **`listed_stock`** = API-synced stock (what's on the marketplace API)
- **`manual_adjustment`** = Manual pushes (internal offset, never synced, never distributed)
- **Total** = `listed_stock` + `manual_adjustment`

### Key Rules

1. **Manual Pushes**:
   - Only update `manual_adjustment` column
   - Do NOT update API
   - Do NOT trigger distribution
   - Do NOT touch `listed_stock`

2. **Distribution**:
   - Only uses `listed_stock` for calculations (ignores `manual_adjustment`)
   - Updates `listed_stock` based on formulas
   - Calls API with new `listed_stock` value (NOT including `manual_adjustment`)
   - Does NOT touch `manual_adjustment`

3. **API Sync**:
   - Fetches value from API
   - Updates `listed_stock` from API response
   - Does NOT touch `manual_adjustment`
   - Total = `listed_stock` (synced) + `manual_adjustment` (unchanged)

### Flow Examples

**Example 1: Manual Push Only**
- Current: `listed_stock` = 100, `manual_adjustment` = 0, Total = 100
- User pushes +5
- Result: `listed_stock` = 100, `manual_adjustment` = 5, Total = 105
- API: Still 100 (not updated)

**Example 2: Distribution After Manual Push**
- Current: `listed_stock` = 100, `manual_adjustment` = 5, Total = 105
- Distribution runs (from other source, not manual push)
- Distribution calculates based on `listed_stock` = 100 (ignores `manual_adjustment`)
- Updates `listed_stock` = 120 (distributed)
- Calls API with 120
- Result: `listed_stock` = 120, `manual_adjustment` = 5, Total = 125
- API: Now 120

**Example 3: Sync After Distribution**
- Current: `listed_stock` = 120, `manual_adjustment` = 5, Total = 125
- API has: 120 (from previous distribution)
- Sync runs, fetches 120 from API
- Updates `listed_stock` = 120 (from API)
- Result: `listed_stock` = 120, `manual_adjustment` = 5, Total = 125
- No change (already in sync)

**Example 4: Sync After Manual Push (No Distribution)**
- Current: `listed_stock` = 100, `manual_adjustment` = 5, Total = 105
- API has: 100 (not updated by manual push)
- Sync runs, fetches 100 from API
- Updates `listed_stock` = 100 (from API)
- Result: `listed_stock` = 100, `manual_adjustment` = 5, Total = 105
- No change (manual adjustment preserved)

## Implementation Checklist

- [x] Migration: Add `manual_adjustment` column
- [x] Model: Add `manual_adjustment` to fillable
- [x] Manual Push: Only update `manual_adjustment` (no API, no distribution)
- [x] Distribution: Only use `listed_stock` (ignore `manual_adjustment`)
- [x] API Sync: Only update `listed_stock` (don't touch `manual_adjustment`)
- [x] Total Calculation: Always include both (`listed_stock` + `manual_adjustment`)
- [x] View: Display total correctly
- [x] History: Log manual adjustments separately
