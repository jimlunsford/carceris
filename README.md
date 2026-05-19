# Carceris

**Secure daily logging for correctional facilities.**

Carceris is a self-hosted PHP and MariaDB daily activity log system built for internal correctional facility use. It focuses on daily operational logs, role-based access, audit accountability, correction and void history, completed-log exports, and optional log delivery.

## Version

Carceris v0.6.11

## Current Status

Carceris v0.6.11 is an open-source release candidate for internal facility deployment.

It is suitable for controlled internal pilot testing and internal production-readiness evaluation. Public internet exposure is **not recommended** without additional hardening, access controls, and independent security review.

## Core Features

- Active daily operational log
- Operational day start-time settings
- Late and backfilled entries
- Categories and priorities
- Archive search with basic and advanced search
- Print view
- PDF, text, and HTML completed-log downloads
- Daily Logs operations dashboard
- Optional Log Delivery by configured mail transport
- Delivery history and failed-send review
- Correction and void workflow
- Correction and void history
- Audit log
- Admin, Supervisor, Officer, and Viewer roles
- Admin Backup & Restore
- Admin ZIP upgrade system
- Production readiness and system status checks

## Intended Deployment

Carceris is designed for:

```text
internal facility servers
LAN or VPN access
firewalled environments
facility-controlled workstations
self-hosted PHP/MariaDB deployments
```

Carceris is **not** currently positioned as a public-internet hardened web application.

## Minimum Requirements

- PHP 8.1 or newer
- MariaDB or MySQL
- PDO MySQL extension
- ZipArchive extension for backup, restore, and browser-based upgrades
- HTTPS or equivalent internal transport protection for real operational use

## Quick Start

Read these files before installing or deploying:

```text
INSTALL.md
DEPLOYMENT.md
SECURITY.md
BACKUP-RESTORE.md
PRODUCTION-CHECKLIST.md
TESTING.md
ROLE-PERMISSIONS.md
ADMIN-GUIDE.md
UPGRADE.md
```

Basic install flow:

```text
upload files
create database
visit /install/index.php
create first admin user
lock or remove installer
review /status.php
configure settings and users
test backup and restore
```

## Security Notes

Carceris includes:

- password hashing
- login throttling
- role-based permissions
- CSRF protection on state-changing actions
- CSRF-protected logout
- audit logging
- protected internal folder markers
- production status checks
- backup and restore guardrails
- upgrade guardrails

Production deployment still requires server-level hardening. Apache `.htaccess` files are included, but Nginx, IIS, Caddy, or other servers require equivalent server-level rules.

Sensitive folders must not be publicly browsable:

```text
/app
/app/config
/database
/storage
/tools
/vendor
```

## Backup and Restore

Admin Backup & Restore can create a backup bundle and restore the database from a Carceris backup ZIP.

Backup bundles may contain sensitive operational data, users, audit records, settings, and configuration details. Store them securely.

The in-app restore imports `database.sql` only. Config and storage files in the backup bundle are included for manual disaster recovery.

## Roles

Carceris includes four roles:

```text
Admin
Supervisor
Officer
Viewer
```

See:

```text
ROLE-PERMISSIONS.md
```

## Documentation

Project documentation:

```text
INSTALL.md
UPGRADE.md
SECURITY.md
DEPLOYMENT.md
BACKUP-RESTORE.md
ROLE-PERMISSIONS.md
ADMIN-GUIDE.md
PRODUCTION-CHECKLIST.md
TESTING.md
CONTRIBUTING.md
CODE-AUDIT.md
```

## License

Carceris is licensed under the **GNU Affero General Public License version 3**.

```text
SPDX-License-Identifier: AGPL-3.0-only
```

See:

```text
LICENSE
COPYING
NOTICE
```

## GitHub Safety

Do not commit or publish:

```text
app/config/config.local.php
storage contents
real logs
real audit records
backup bundles
database dumps
uploaded upgrade ZIPs
facility data
.env files
private keys
```

The included `.gitignore` is intended to help keep those files out of the repository.

## Release Positioning

Recommended public positioning:

```text
Carceris v0.6.11
Open-source release candidate for internal correctional facility daily logging.
```
