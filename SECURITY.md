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
