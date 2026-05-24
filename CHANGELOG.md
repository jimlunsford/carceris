# Changelog

## v0.6.14, Migration Runner Buffering Fix

- Fixed upgrade failures caused by marker migrations that returned result sets on hosts using unbuffered PDO MySQL queries.
- Changed marker migrations to comment-only release markers so retries from a failed v0.6.13 upgrade can recover safely.
- Added buffered-query support for PDO MySQL connections when available.
- Hardened the migration runner so result-returning SQL statements are fetched and closed before the next query runs.

## v0.6.13, Regression Hardening Pass

- Fixed direct live-log PDF access for Viewer users.
- Rejected extra files in upgrade ZIPs that are not listed in `RELEASE_MANIFEST.json`.
- Added stale upgrade-file cleanup for the deprecated maintenance-notes migration.
- Stopped the web cron endpoint from printing raw exception messages.
- Strengthened `tools/release-audit.php` to fail when project files are missing from the release manifest.


## v0.6.12, Release Audit Hardening Pass

- Added fresh-install migration baselining.
- Added release manifest hash validation for future upgrade packages.
- Added `RELEASE_MANIFEST.json` package support.
- Added schema health checks to System Status and post-upgrade validation.
- Added mail transport capability checks and clearer transport failures.
- Added a user Account page for password changes.
- Tightened Viewer role behavior for the live Active Log.
- Fixed correction/void return handling and corrected-time validation.
- Removed a Report Delivery page side effect that created log days while viewing settings.
- Strengthened backup/restore warnings and added backup database checksum validation.
- Added `tools/release-audit.php`.
- Updated docs for fixed core templates, upgrade preservation, backups, and operational log completion.

## v0.6.11, Documentation Verification Pass

- Verified public-release documentation cleanup.
- Confirmed deprecated routes were removed from the active package.