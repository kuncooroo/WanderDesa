# TASK-003 — Authentication

| Field | Value |
|---|---|
| **Task ID** | TASK-003 |
| **Title** | Authentication |
| **Priority** | 3 Backend |

## Objective
Implement staff session authentication and Sanctum foundation for user/device API tokens.

## Background
Dashboard uses session auth; kiosk uses device Sanctum tokens (`docs/03`, `docs/07`, `docs/08`). Visitors do not log into kiosk.

## Dependencies
- TASK-002

## Affected Files
- `backend/config/auth.php`, `sanctum.php`
- `backend/routes/web.php`, `api.php`
- `backend/app/Http/Controllers/Api/V1/Auth/*`
- `backend/app/Models/User.php`
- `backend/resources/views` or Livewire login
- `backend/tests/Feature/Auth/*`

## Database Changes
- Sanctum `personal_access_tokens` migration if not present
- Ensure `users.is_active`, `last_login_at`

## Backend Requirements
- Staff login/logout (web)
- Inactive users blocked
- API: `POST /api/v1/auth/login`, `POST /auth/logout`, `GET /auth/me`
- Session CSRF for web
- Password hashed

## API Requirements
- Per `docs/08-API-CONTRACT.md` §3
- Rate limit auth endpoints

## Dashboard Requirements
- Login page (minimal UI OK; polish in TASK-018)
- Logout

## Kiosk Requirements
- None yet (device activation in TASK-019)

## Validation
- email/password required; credentials validated server-side

## Authorization
- Authenticated-only routes for logout/me
- Device vs user guards prepared

## Business Rules
- No public visitor accounts

## Edge Cases
- Wrong password; inactive user; revoked token

## Security
- No password in logs; lockout/rate limit; secure cookies in production config notes

## Testing
- Login success/fail/inactive
- Logout revokes token when using API token

## Acceptance Criteria
- [ ] Staff can login/logout via web
- [ ] API login returns Bearer token for active user
- [ ] Inactive user cannot authenticate
- [ ] Failed login logged/auditable hook ready

## Definition of Done
- Auth works for staff; Sanctum ready for RBAC and devices
- Tests green for auth cases
