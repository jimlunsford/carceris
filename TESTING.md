# Testing Checklist

Run this before production and after each release.

## Install and Upgrade

- [ ] Fresh install works
- [ ] Upgrade from previous version works
- [ ] Migration status is current
- [ ] System Status has no critical warnings

## Roles

Test each role:

```text
admin
supervisor
officer
viewer
```

Confirm each can only access approved pages and actions.

## Log Entry

- [ ] Create normal entry
- [ ] Create late/backfilled entry
- [ ] Required fields work
- [ ] Entry timestamp is save-time for normal entries
- [ ] Manual event time works for late entries

## Correction and Void

- [ ] Supervisor can correct
- [ ] Supervisor can void
- [ ] Officer cannot correct/void
- [ ] History shows original snapshot
- [ ] Replacement links work

## Archive

- [ ] Basic search works
- [ ] Advanced search works
- [ ] Date filters work
- [ ] PDF download works
- [ ] Print view works

## Audit

- [ ] Admin can view/search/export/prune
- [ ] Supervisor can view/search
- [ ] Supervisor cannot export/prune

## Daily Logs

- [ ] Recent completed logs display
- [ ] PDF/Text/HTML downloads work
- [ ] Send buttons hide if Log Delivery is not configured
- [ ] Send buttons show when Log Delivery is configured
- [ ] Failed resend works

## Log Delivery

- [ ] Test email works
- [ ] Manual send works
- [ ] Scheduled send works
- [ ] Failed sends record errors
- [ ] Delivery history records correctly


## Logout

- [ ] Header Logout button signs out successfully.
- [ ] Direct GET to `/logout.php` shows a confirmation screen.
- [ ] Cancel returns to Active Log.
