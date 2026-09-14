# TASK-007 — Pricing

| Field | Value |
|---|---|
| **Task ID** | TASK-007 |
| **Title** | Pricing & quote |
| **Priority** | 4 Business Logic |

## Objective
Implement authoritative `PricingService` and quote API that calculates all money fields server-side.

## Background
CRITICAL FINANCIAL RULE: never trust client money. Quote used by kiosk and assisted sale identically.

## Dependencies
- TASK-006

## Affected Files
- `backend/app/Services/Pricing/PricingService.php`
- `backend/app/Actions/Pricing/QuoteOrderAction.php`
- `backend/app/Http/Controllers/Api/V1/PricingController.php`
- `backend/app/Http/Requests/Api/V1/QuoteRequest.php`
- `backend/app/Http/Resources/Api/V1/QuoteResource.php`
- tests

## Database Changes
- None (uses ticket_types)

## Backend Requirements
- Calculate subtotal, discount_total (0 MVP unless rules), tax_total, service_fee_total, grand_total
- Recalculate from DB ticket types — ignore client prices
- Reject request bodies containing authoritative money fields

## API Requirements
- `POST /api/v1/pricing/quote` per docs/08
- Response money as IDR integers

## Dashboard Requirements
- Assisted sale will call same Action later

## Kiosk Requirements
- Uses quote before confirm

## Validation
- items required; quantity bounds; visit_date if required by type
- `money.client_values_forbidden` on forbidden fields

## Authorization
- Device catalog.read or staff authenticated / orders.create path

## Business Rules
- Same pricing engine for both channels
- Inactive types not quotable

## Edge Cases
- Empty items; mixed destinations; max_per_order exceeded

## Security
- No price tampering via request

## Testing
- Unit tests for calculations
- Feature: reject client grand_total
- Identical inputs → identical totals

## Acceptance Criteria
- [ ] Quote endpoint returns authoritative totals only from server math
- [ ] Client money fields rejected
- [ ] Inactive ticket type rejected

## Definition of Done
- PricingService reusable by CreateOrder (TASK-009)
