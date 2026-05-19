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
