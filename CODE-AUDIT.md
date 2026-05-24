# Carceris Code Audit Summary

## Version

Carceris v0.6.14

## Scope

This document summarizes the production-readiness code audit work completed before public GitHub release candidate packaging.

The audit focused on:

```text
route access control
role permission checks
CSRF coverage
state-changing actions
file upload guardrails
backup and restore guardrails
upgrade guardrails
logout behavior
sensitive download behavior
installer and production settings
deprecated route cleanup
GitHub hygiene
documentation cleanup
```

## Automated Checks Performed

The package was checked for:

```text
PHP syntax errors
POST-capable routes missing CSRF protection
obvious raw SQL built directly from request superglobals
raw echo of request superglobals
user-controlled include/require patterns
direct user-controlled file read/delete patterns
missing admin route targets
facility-specific example terms
stale generated audit output
dead maintenance-note routes
```

## Current Audit Result

The current package passed:

```text
PHP syntax check
POST-capable route CSRF check
static risky-pattern scan
admin route shim validation
GitHub hygiene scan
documentation cleanup scan
```

## Security-Relevant Changes Made During Audit

### Logout

Logout uses a POST form with CSRF protection.

A GET request to:

```text
/logout.php
```

shows a confirmation screen instead of immediately ending the session.

### Sensitive Downloads

No-store cache headers are used for sensitive generated downloads where applicable, including completed-log downloads and audit CSV export.

### Backup and Restore

Admin Backup & Restore includes:

```text
admin-only access
admin password confirmation
typed RESTORE confirmation
restore acknowledgement checkbox
upload validation
backup manifest validation
maintenance mode during restore
pre-restore safety backup
audit events
```

### Upgrade

Browser-based ZIP upgrades include:

```text
admin-only access
admin password confirmation
backup acknowledgement checkbox
ZIP upload validation
upload size guardrail
maintenance mode during upgrade
migration tracking
audit events
```

### Deprecated Routes

Removed route files are absent from the active package.

Generated route-audit JSON output was also removed. The route-audit tool remains available:

```text
tools/route-audit.php
```

## Manual Verification Still Required

This audit does not replace real deployment testing.

Before production use, manually verify:

```text
fresh install
upgrade from previous internal package
all roles by direct URL
backup bundle creation
restore on a non-production install
PDF/text/HTML downloads
audit search
archive search
correction and void workflows
Log Delivery if used
server-level folder protection
HTTPS or equivalent transport protection
workstation locking policy
real-user pilot workflow
```

## Production Verdict

Carceris v0.6.14 is suitable for public GitHub release as an open-source release candidate for internal facility deployment.

It should not be represented as independently certified, enterprise-hardened, or public-internet safe.


## Documentation Verification

The Markdown documentation was checked after the cleanup pass to remove stale release-history wording from public-facing docs.

Confirmed:

```text
README.md uses the current version
UPGRADE.md uses current upgrade guardrail language
PRODUCTION-CHECKLIST.md reflects that deprecated route files are absent
release notes describe the current package
```
