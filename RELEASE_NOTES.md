# Carceris v0.6.11, Documentation Verification Pass

## Fixed

- Cleaned `UPGRADE.md` by replacing a historical versioned heading with current upgrade guardrail language.
- Cleaned `PRODUCTION-CHECKLIST.md` so it no longer says the deprecated maintenance-notes route returns 404. The route files are now absent.
- Renamed a historical audit-pass checklist heading to `Additional Release Candidate Checks`.
- Updated Markdown files to the current package version.

## Verified

- Markdown files were checked for stale version references.
- Facility-specific example terms remain absent.
- Removed files remain removed.
- PHP syntax check passed.

## Database

No database structure changes.

## Migration

Included:

```text
database/migrations/0.6.11-documentation-verification.sql
```
