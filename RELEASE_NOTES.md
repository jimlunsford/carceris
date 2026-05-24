# Carceris v0.6.14, Migration Runner Buffering Fix

This release fixes the failed upgrade path reported when upgrading from v0.6.11 to v0.6.13.

## Fixed

- Fixed migration execution so result-returning SQL statements are fetched and closed before the next migration query runs.
- Added PDO MySQL buffered-query support when the driver constant is available.
- Converted the v0.6.13 release marker migration to a comment-only marker so a retry from a failed v0.6.13 upgrade can recover safely.
- Added a v0.6.14 comment-only migration marker for upgrade tracking.

## Changed files

- `app/config/database.php`
- `app/includes/upgrade.php`
- `database/migrations/0.6.13-regression-hardening.sql`
- `database/migrations/0.6.14-migration-runner-buffering-fix.sql`
- release/version documentation and manifest files

## Upgrade notes

No database structure changes are required. This release is safe to install over a failed v0.6.13 upgrade attempt because it removes the result-returning SQL from the pending v0.6.13 marker migration.

The upgrade system still preserves local config, storage, installed lock files, backups, exports, and user data.
