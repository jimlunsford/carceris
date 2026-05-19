# Backup and Restore

## Admin Backup & Restore

Carceris includes an Admin-only Backup & Restore area:

```text
Admin -> Backup & Restore
```

## Backup Bundle

A backup bundle contains:

```text
manifest.json
database.sql
config/config.local.php, if present
storage files, excluding backup and upgrade working folders
RESTORE-README.txt
```

Backup bundles contain sensitive operational data and configuration information. Store them securely.

## Creating a Backup

Go to:

```text
Admin -> Backup & Restore
```

Then:

1. Enter your admin password.
2. Acknowledge that the backup contains sensitive data.
3. Download the backup bundle.

Backup creation is recorded in Audit.

## Restoring a Backup

The in-app restore imports:

```text
database.sql
```

It does not automatically restore:

```text
config/config.local.php
storage files
```

Those files are included for manual disaster recovery.

Restore requires:

```text
admin password
uploaded Carceris backup ZIP
typing RESTORE
restore acknowledgement checkbox
```

During restore, Carceris:

```text
creates a pre-restore safety database backup
enables maintenance mode
imports database.sql
disables maintenance mode
records audit events
```

## Before Restoring

Always create an external backup before restoring.

A restore replaces the current database state, including:

```text
users
log entries
audit records
corrections
voids
settings
delivery history
```

## Pre-Restore Safety Backup

Before importing, Carceris writes a safety backup into:

```text
storage/backups
```

This backup is not a substitute for a proper external backup.


## Temporary Working File Cleanup

Backup & Restore cleans temporary working files older than 24 hours from `storage/backups`. Pre-restore safety backups are not removed by this cleanup.
