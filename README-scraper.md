# Scraper validation checklist

The CBS scraper (`src/Scraper/`) was written without ever seeing a live
`cbssports.com` page -- this sandbox's outbound network policy blocks that
host entirely. Everything here works and is unit-tested against *fixture*
HTML shaped like what CBS is expected to produce, but the fixtures are a
best guess, not a capture. Before relying on this in production, validate
it against the real site:

1. Copy `config/config.example.php` to `config/config.php` and fill in
   DB/admin secrets.
2. CBS's login page is a JS single-page app behind reCAPTCHA -- there's no
   way to automate that login, and this tool doesn't try. Instead: log into
   CBS Sports normally in a real browser, open DevTools -> Network tab,
   grab the full `Cookie` request header value from any request to a
   cbssports.com page, and paste it into the admin screen (or use it below).
   It'll expire eventually and need refreshing the same way.
3. From an environment that can actually reach cbssports.com (i.e. not this
   sandbox -- your own machine or the hosting server), run:
   ```
   CBS_SESSION_COOKIE='...' php scripts/capture_pages.php
   ```
   This saves the real roster and draft-results pages under `captures/`
   (gitignored). If it throws "session cookie appears to be expired or
   invalid," grab a fresh cookie value and try again.
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
