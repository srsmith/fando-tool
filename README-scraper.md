# Scraper validation checklist

The CBS scraper (`src/Scraper/`) was written without ever seeing a live
`cbssports.com` page -- this sandbox's outbound network policy blocks that
host entirely. Everything here works and is unit-tested against *fixture*
HTML shaped like what CBS is expected to produce, but the fixtures are a
best guess, not a capture. Before relying on this in production, validate
it against the real site:

1. Copy `config/config.example.php` to `config/config.php` and fill in the
   `cbs.login_url` (confirm the actual CBS Sportsline login page URL --
   `https://www.cbssports.com/login/` in the example is a placeholder) plus
   DB/admin secrets.
2. From an environment that can actually reach cbssports.com (i.e. not this
   sandbox -- your own machine or the hosting server), run:
   ```
   CBS_USERNAME=... CBS_PASSWORD=... php scripts/capture_pages.php
   ```
   This logs in and saves the real roster and draft-results pages under
   `captures/` (gitignored).
3. Check `CbsClient::login()` worked. If it throws "could not auto-detect
   login form fields," open the saved login page HTML, find the actual
   `name=` attributes on the username/password inputs, and set
   `username_field`/`password_field` in `config.php`.
4. Compare the captured `roster.html` / `draft_results.html` against what
   `RosterScraper`/`DraftResultsScraper` expect:
   - `RosterScraper` looks for a `<table>` with a header row containing a
     column whose text matches "player" or "name," plus optional
     Pos/Round/Next Yr/Accelerated columns, with a team name in the nearest
     preceding heading.
   - `DraftResultsScraper` looks for a `<table>` with Round + Team columns,
     where a traded pick's team cell contains `(from X)` or `(via X)`.
   - If CBS's real markup doesn't match (different column labels, picks
     grouped some other way, team names not in a heading, etc.), share the
     captured HTML and the parsers will get adjusted to match.
5. Once parsing is confirmed, seed `player_holds.effective_hold_year` /
   `accelerated_count` for every *currently held* player by hand (a one-time
   data-entry pass) -- the importer only auto-seeds players it's never seen
   before at year 0, since it deliberately never overwrites the engine's own
   running counters.
6. Insert a row into `seasons` with `is_current = 1` before running a scrape
   (`ScrapeRunner` looks this up to know which season's draft picks to
   store).
