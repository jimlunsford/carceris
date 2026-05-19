# Installing Carceris

## 1. Upload Files

Upload the Carceris files to your server.

For simple hosting, the web root may point at the project root. For stronger deployments, point the web root to `/public` and create explicit routes for root-level entry files only if needed.

## 2. Create a Database

Create a new MySQL or MariaDB database and a dedicated database user.

Use a database user that only has permissions for the Carceris database.

Recommended permissions:

```text
SELECT
INSERT
UPDATE
DELETE
CREATE
ALTER
INDEX
DROP
REFERENCES
```

`DROP` is useful for some migrations but should be evaluated under your facility policy.

## 3. Run Installer

Visit:

```text
/install/index.php
```

Default database host:

```text
127.0.0.1
```

Create the first admin user with a strong password.

## 4. Lock Installer

The installer writes:

```text
/storage/installed.lock
/app/config/config.local.php
```

For production, delete or server-protect the `/install` directory after installation.

## 5. Configure Production Settings

In Admin:

```text
Settings
Log Delivery
System Status
```

Set:

```text
Environment Mode = Production
Force HTTPS = enabled when HTTPS is available
Debug = disabled
```

## 6. Run System Status

Open:

```text
/status.php
```

Review the Production Readiness checks.
