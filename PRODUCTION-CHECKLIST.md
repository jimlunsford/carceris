# Production Readiness Checklist

Do not mark Carceris production-ready until these are complete.

## Application

- [ ] Environment Mode is Production
- [ ] Debug is disabled
- [ ] HTTPS is enabled
- [ ] Force HTTPS is enabled when HTTPS is available
- [ ] Installer is locked or removed
- [ ] System Status shows no failed production checks
- [ ] Migrations are current
- [ ] No real data exists in testing environment

## Server

- [ ] Server is patched
- [ ] Firewall restricts access
- [ ] App is not publicly exposed unless separately hardened
- [ ] Database is not reachable from officer workstations
- [ ] Internal folders are protected
- [ ] `.htaccess` or equivalent server rules are verified

## Users

- [ ] No shared user accounts
- [ ] Admin accounts limited
- [ ] Supervisor accounts assigned correctly
- [ ] Officer accounts assigned correctly
- [ ] Password policy communicated

## Workstations

- [ ] Workstations auto-lock
- [ ] Staff lock screens when unattended
- [ ] Browser password saving policy defined
- [ ] Shared workstation rules documented

## Backups

- [ ] Admin Backup & Restore has been tested with fake data
- [ ] Restore from a Carceris backup bundle has been tested on a non-production system
- [ ] Database backup works
- [ ] File/config backup works
- [ ] Restore has been tested
- [ ] Backup retention approved
- [ ] Backups are encrypted or otherwise protected

## Workflow

- [ ] Normal entries tested
- [ ] Late/backfilled entries tested
- [ ] Corrections tested
- [ ] Voids tested
- [ ] Archive search tested
- [ ] Audit search tested
- [ ] PDF download tested
- [ ] Print view approved
- [ ] Log Delivery tested if used
- [ ] Upgrade and rollback tested


## Release Candidate Guardrails

- [ ] Upgrade password confirmation tested
- [ ] Backup acknowledgement required before upgrade
- [ ] Deprecated maintenance notes route files are absent
- [ ] Backup restore upload size and format checks tested


## Additional Release Candidate Checks

- [ ] Logout POST flow tested
- [ ] Sensitive downloads tested
- [ ] Upgrade oversized upload rejection tested
- [ ] Backup temporary working-file cleanup reviewed
