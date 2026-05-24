# Security Policy

## Deployment Security

Carceris is intended for internal facility deployment.

Minimum security expectations:

```text
HTTPS required for real operational use
not publicly exposed unless separately hardened
firewall restricted
unique user accounts
least-privilege database user
admin access limited
server patched
regular encrypted backups
installer removed or protected
```

## Sensitive Folders

These folders must not be publicly browsable:

```text
/app
/app/config
/database
/storage
/tools
/vendor
```

Apache `.htaccess` files are included. If your server does not honor `.htaccess`, configure equivalent server-level rules.

## Sessions

Carceris uses:

```text
HttpOnly cookies
SameSite=Strict cookies
Secure cookies when HTTPS is detected
session regeneration on login
```

No idle timeout is currently enforced by design. Facilities should require workstation locking and unique user accounts.

## Reporting Vulnerabilities

Do not include real facility data in security reports.

Report issues privately to the project maintainer before public disclosure.


## Backup Security

Backup bundles contain sensitive operational and configuration data.

Protect backup files like live system data.

Do not store backup bundles in public web directories, shared personal drives, or unsecured email accounts.


## Logout CSRF Protection

Logout uses a POST form with CSRF protection. A GET request to `/logout.php` displays a confirmation screen instead of immediately ending the session.


## Mail Credentials and Backups

Carceris stores report-delivery SMTP credentials in the application database so shared-hosting deployments can work without external secret managers. The SMTP password is write-only in the admin interface, but it is still present in database backups.

Treat every Carceris backup as sensitive. Backup bundles may contain operational logs, users, audit records, local configuration, database dumps, and saved mail credentials. Store backups only in approved secure storage.

## Upgrade Package Trust

Only install Carceris upgrade packages from a trusted source. Current release packages include `RELEASE_MANIFEST.json` with file hashes. The in-app upgrader validates the manifest before accepting future packages.

## Cron Error Disclosure

The web cron endpoint returns generic failure text to the browser. Detailed scheduled-report errors are written to the server error log and internal audit/report delivery records where applicable.
