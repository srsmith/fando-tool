-- FANDO Keeper Tool schema
-- Target: MySQL 8.0+

CREATE TABLE IF NOT EXISTS seasons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    year SMALLINT UNSIGNED NOT NULL,
    -- redraft_cycle_start is the year of the most recent full-league redraft;
    -- (year - redraft_cycle_start) tells the app how far into the 5-year cycle we are.
    redraft_cycle_start SMALLINT UNSIGNED NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seasons_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cbs_team_id VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    owner_name VARCHAR(128) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_cbs_team_id (cbs_team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS players (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cbs_player_id VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    position VARCHAR(16) DEFAULT NULL,
    current_team_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_players_cbs_player_id (cbs_player_id),
    KEY idx_players_current_team (current_team_id),
    CONSTRAINT fk_players_team FOREIGN KEY (current_team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per player currently on a roster; this is the authoritative state
-- for the escalator engine (NOT CBS's own displayed "Next Year"/"Accelerated" fields).
CREATE TABLE IF NOT EXISTS player_holds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    draft_round TINYINT UNSIGNED NOT NULL,   -- round basis for cost formula; FA pickups store 10 here
    draft_season SMALLINT UNSIGNED NOT NULL, -- season the current draft_round basis was set (last redraft/FA pickup/trade-in)
    is_faq_pickup TINYINT(1) NOT NULL DEFAULT 0,
    effective_hold_year SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- chronological years held + accelerated_count
    accelerated_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- permanent, cumulative
    updated_season_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_player_holds_player (player_id),
    KEY idx_player_holds_team (team_id),
    CONSTRAINT fk_holds_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_holds_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_holds_season FOREIGN KEY (updated_season_id) REFERENCES seasons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which draft picks each team currently owns, post-trade. Source of record:
-- https://4and1.football.cbssports.com/draft/results
CREATE TABLE IF NOT EXISTS draft_picks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season_id INT UNSIGNED NOT NULL,
    round TINYINT UNSIGNED NOT NULL,
    owning_team_id INT UNSIGNED NOT NULL,
    original_team_id INT UNSIGNED DEFAULT NULL, -- NULL if never traded
    PRIMARY KEY (id),
    UNIQUE KEY uq_draft_picks_season_round_orig (season_id, round, original_team_id),
    KEY idx_draft_picks_owner (owning_team_id),
    CONSTRAINT fk_picks_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    CONSTRAINT fk_picks_owner FOREIGN KEY (owning_team_id) REFERENCES teams(id),
    CONSTRAINT fk_picks_original FOREIGN KEY (original_team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scrape_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    target VARCHAR(64) NOT NULL, -- 'roster' | 'draft_results'
    started_at DATETIME NOT NULL,
    finished_at DATETIME DEFAULT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'running', -- running | success | failed
    message TEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single-row table; the CBS session cookie a human copies from their own
-- logged-in browser (see CbsClient/README-scraper.md -- CBS's login is
-- behind reCAPTCHA and can't be automated), encrypted at rest.
-- Admin-only read/write.
CREATE TABLE IF NOT EXISTS credentials (
    id TINYINT UNSIGNED NOT NULL,
    cbs_session_cookie_encrypted VARBINARY(4096) NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
