# TASK-018 — Dashboard Shell

| Field | Value |
|---|---|
| **Task ID** | TASK-018 |
| **Title** | Dashboard shell & role-specific navigation |
| **Priority** | 8 Dashboard |

## Objective
Implement Blade layout, top nav, sidebar, and permission-filtered navigation for all staff roles.

## Background
UX shell from `docs/09-UI-UX.md`; RBAC from `docs/07`. Hide unauthorized modules (UX only); server still enforces.

## Dependencies
- TASK-004, TASK-016
- Integrates pages from TASK-017 and later reporting

## Affected Files
- `backend/resources/views/layouts/app.blade.php`
- `backend/app/Livewire/Layout/*` or view composers
- `backend/app/Support/Navigation/NavBuilder.php`
- Tailwind entries
- Profile/logout menu

## Database Changes
- None

## Backend Requirements
- Nav built from permissions not hardcoded role switches alone
- Groups: Operasional, Katalog, Perangkat, Keuangan, Laporan, Administrasi, Sistem
- Empty/loading/error layout patterns

## API Requirements
- None

## Dashboard Requirements
- Login uses layout guest
- Authenticated shell with breadcrumbs, notifications slot, profile
- Responsive collapse sidebar
- 403 page

## Kiosk Requirements
- None

## Validation
- N/A

## Authorization
- Each nav item requires permission; deep links still Policy-protected

## Business Rules
- Auditor sees audit/reports read-only entries
- Ticket officer sees assisted sale primary CTA

## Edge Cases
- User with no permissions sees empty nav + message
- Multi-role (if any) unions permissions

## Security
- Do not expose admin links to unauthorized users (still server-enforced)

## Testing
- Nav contents differ by seeded roles (feature/view tests)

## Acceptance Criteria
- [ ] Each MVP role sees appropriate modules per docs/09 matrix
- [ ] Unauthorized route/page denied server-side
- [ ] Shell usable on desktop/tablet

## Definition of Done
- Consistent home for all dashboard features
