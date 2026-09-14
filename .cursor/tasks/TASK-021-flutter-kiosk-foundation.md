# TASK-021 — Flutter Kiosk Foundation

| Field | Value |
|---|---|
| **Task ID** | TASK-021 |
| **Title** | Flutter kiosk foundation |
| **Priority** | 9 Kiosk |

## Objective
Scaffold the Flutter physical kiosk app structure, theme, device auth storage, API client, heartbeat, and idle/home/maintenance shells — without checkout commerce yet.

## Background
Flutter is a terminal client, not a mobile consumer app (`docs/09`, `docs/10`, CURSOR Kiosk Rule).

## Dependencies
- TASK-020
- TASK-019 activation/heartbeat APIs

## Affected Files
- `kiosk/pubspec.yaml`
- `kiosk/lib/**` per structure (core, network, storage, security, device, shared, features/idle|home|maintenance)
- Android landscape / kiosk mode config
- tests

## Database Changes
- None

## Backend Requirements
- Consume existing APIs only

## API Requirements
- Activation exchange, heartbeat, me/config, auth headers

## Dashboard Requirements
- None

## Kiosk Requirements
- Folder boundaries per docs/10
- Landscape locked fullscreen intent
- Large touch theme tokens
- Bahasa Indonesia strings baseline
- Secure token storage
- Idle + Home + Maintenance + Network error screens (shell)
- Heartbeat loop
- No business pricing logic

## Validation
- Client-side UX only; server validates

## Authorization
- Device token attached to requests

## Business Rules
- Local storage never authoritative for PAID/ISSUED/USED

## Edge Cases
- Invalid token → re-activation guidance
- Offline on launch

## Security
- No secrets in source; token in secure storage

## Testing
- Unit tests for API client auth header
- Widget smoke for idle/home

## Acceptance Criteria
- [ ] App runs landscape on target profile
- [ ] Can activate (staging) and heartbeat
- [ ] Maintenance screen renders when API status says so
- [ ] Project structure matches docs/10

## Definition of Done
- Ready for checkout feature (TASK-022)
