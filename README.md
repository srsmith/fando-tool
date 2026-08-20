# fando-tool
Utility tools for FANDO management

## Keeper tool (Phase 1)

A PHP/MySQL app that scrapes the FANDO CBS Sportsline league and helps
owners plan which players to keep, including the true (accelerated) draft
pick cost and whether a team actually owns the picks needed to legally hold
its chosen players.

### Setup

```
composer install
cp config/config.example.php config/config.php   # fill in real values
mysql < sql/schema.sql
```

Then, from the admin screen (`public/admin/`, gated by `admin_secret` in
config), paste a CBS session cookie (see README-scraper.md -- CBS's login is
behind reCAPTCHA, so this is copied from a real logged-in browser rather
than automated) and trigger a scrape. Recurring refreshes can be run via
`php scripts/scrape.php` on a cron, though the pasted cookie will still
need periodic manual refreshing once it expires.

### Status

Phase 1 (data model, calculation engine, scraper skeleton, admin screen) is
in place. The keeper cost/acceleration engine and pick-collision logic are
fully unit-tested against the league's actual rules (see
`tests/KeeperCalculatorTest.php`, `tests/PickAssignmentTest.php`). The CBS
scraper has **not** been validated against a live page yet -- see
[README-scraper.md](README-scraper.md) for the steps to finish that once
it's run somewhere with network access to cbssports.com. The team-picker
keeper simulator UI (Phase 2) hasn't been built yet.

Run tests: `vendor/bin/phpunit tests/`
