# TASK-001 — Project Foundation

| Field | Value |
|---|---|
| **Task ID** | TASK-001 |
| **Title** | Project foundation |
| **Priority** | 1 Foundation |

## Objective
Create the WanderDesa monorepo skeleton and engineering guardrails so later tasks have a consistent place to land.

## Background
WanderDesa is an Integrated Tourism System (`CURSOR.md`): Laravel business authority, Flutter physical kiosk client, Blade/Livewire dashboard client. Structure is defined in `docs/10-PROJECT-STRUCTURE.md` and roadmap Phase 0.

## Dependencies
- None (first task)
- Local toolchain should be inspectable (PHP 8.4.x, Composer, MySQL 8.4.x, Flutter)

## Affected Files
- `README.md`
- `CURSOR.md` (already exists — verify linked)
- `docs/` (already exists)
- `.cursor/rules/` (optional architecture rules)
- `.gitignore`
- `backend/` (placeholder or empty until TASK scaffolding in later tasks)
- `kiosk/` (placeholder)
- `.cursor/tasks/` (this index)

## Database Changes
- None

## Backend Requirements
- Ensure `backend/` directory reserved for Laravel 13.x app (scaffold may occur here or at start of TASK-002/003 wave — prefer creating Laravel app in this task or immediately as first deliverable of foundation)
- Document that Laravel scaffold is part of foundation completion

## API Requirements
- None

## Dashboard Requirements
- None

## Kiosk Requirements
- Create `kiosk/` placeholder with README noting physical terminal (not consumer app)

## Validation
- N/A

## Authorization
- N/A

## Business Rules
- One system; no Staff Flutter folder
- Docs remain normative

## Edge Cases
- Existing docs must not be deleted
- Do not invent Laravel patch version in README without inspection

## Security
- Gitignore `.env`, keys, credentials, IDE secrets
- Never commit secrets

## Testing
- Verify repo paths exist; README links to docs and CURSOR.md

## Acceptance Criteria
- [x] Top-level `backend/`, `kiosk/`, `docs/`, `.cursor/` present
- [x] Root README explains system shape and points to `CURSOR.md` + docs index
- [x] `.gitignore` blocks secrets and common build artifacts
- [x] Inspected toolchain versions recorded (or TBD where not inspectable)
- [x] No Staff Flutter project created

## Definition of Done
- Structure matches `docs/10` intent
- Another agent can start TASK-002 without re-deciding layout
- No application business logic yet
