# Upgrading Carceris

## Before Every Upgrade

1. Back up the database.
2. Back up `/app/config/config.local.php`.
3. Back up `/storage`.
4. Confirm no one is actively entering logs.
5. Keep a copy of the currently installed release ZIP.

## Admin ZIP Upgrade

Log in as an admin and open:

```text
/admin/upgrade.php
```

Upload the new Carceris release ZIP.

The upgrade system will:

```text
enable maintenance mode
extract the package
replace application files
run migration files
record migration status
disable maintenance mode
```

## After Upgrade

Open:

```text
/status.php
```

Confirm:

```text
version is current
migration status is current
database connection works
no upgrade failure warning appears
```

## Rollback

If an upgrade fails:

1. Restore the previous files.
2. Restore the database backup if migrations were partially applied.
3. Restore config and storage.
4. Confirm `/status.php` is clean.

Do not perform production upgrades without tested backups.


## Upgrade Guardrails

Browser-based upgrades require admin password confirmation and a backup acknowledgement checkbox.


## Upgrade Upload Limit

Browser-based upgrade ZIP uploads are limited to 100 MB.


## What Upgrades Preserve

The browser-based upgrader preserves:

- `app/config/config.local.php`
- the installed database and all user data
- `storage/installed.lock`
- `storage/` runtime files, backups, exports, logs, and uploaded upgrade packages

Upgrade ZIP packages are validated before installation. Current packages include `RELEASE_MANIFEST.json` so future upgrades can verify expected files and hashes.

## Strict Release Manifest Validation

Upgrade ZIP files are rejected when they contain files that are not listed in `RELEASE_MANIFEST.json`. This prevents a package from passing hash checks for known files while also carrying unexpected extra files.

## Removed File Cleanup

The upgrader can remove specific stale files that were present in older releases but removed from current packages. Local config and `storage/` are still preserved.
