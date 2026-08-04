<?php

declare(strict_types=1);

namespace Fando\Keeper\Db;

use Fando\Keeper\Scraper\Dto\PickOwnership;
use Fando\Keeper\Scraper\Dto\RosterPlayer;

/**
 * Persists scraped rosters and draft-pick ownership.
 *
 * Important: this service never overwrites effective_hold_year or
 * accelerated_count for a player we've already seen -- those are owned by
 * the keeper calculation engine, not CBS's page. A brand-new player gets
 * seeded at effective_hold_year=0 (i.e. "just drafted, not held yet"). If a
 * previously-seen player's scraped draft_round has changed, that likely
 * means a fresh draft/redraft happened outside this tool's tracking, so we
 * flag it rather than silently resetting state.
 */
final class ScrapeImportService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param RosterPlayer[] $players
     * @return string[] warnings worth surfacing to the admin
     */
    public function importRoster(array $players, int $seasonId): array
    {
        $warnings = [];

        foreach ($players as $player) {
            $teamId = $player->teamName !== null ? $this->upsertTeam($player->teamName) : null;

            $existing = $this->fetchPlayer($player->cbsPlayerId);
            $playerId = $existing['id'] ?? $this->insertPlayer($player, $teamId);

            if ($existing !== null) {
                $this->updatePlayerTeam($playerId, $teamId);
            }

            $existingHold = $this->fetchHold($playerId);
            if ($existingHold === null) {
                $this->insertHold($playerId, $teamId, $player, $seasonId);
            } else {
                if ($player->draftRound !== null && $existingHold['draft_round'] != $player->draftRound) {
                    $warnings[] = sprintf(
                        "%s: scraped draft round (%d) differs from stored (%d) -- possible redraft, review manually.",
                        $player->name,
                        $player->draftRound,
                        $existingHold['draft_round'],
                    );
                }
                if ($teamId !== null) {
                    $this->updateHoldTeam($playerId, $teamId, $seasonId);
                }
            }
        }

        return $warnings;
    }

    /** @param PickOwnership[] $picks */
    public function importDraftPicks(array $picks, int $seasonId): void
    {
        $this->pdo->prepare('DELETE FROM draft_picks WHERE season_id = :season')->execute(['season' => $seasonId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO draft_picks (season_id, round, owning_team_id, original_team_id)
             VALUES (:season, :round, :owner, :original)'
        );

        foreach ($picks as $pick) {
            $ownerId = $this->upsertTeam($pick->owningTeamName);
            $originalId = $pick->originalTeamName !== null ? $this->upsertTeam($pick->originalTeamName) : null;

            $stmt->execute([
                'season' => $seasonId,
                'round' => $pick->round,
                'owner' => $ownerId,
                'original' => $originalId,
            ]);
        }
    }

    private function upsertTeam(string $name): int
    {
        // CBS doesn't give us a stable id for this parser stage, so the team
        // name itself is used as the natural key here; if CBS team ids turn
        // up during real-page validation, prefer those instead.
        $stmt = $this->pdo->prepare('SELECT id FROM teams WHERE cbs_team_id = :key');
        $stmt->execute(['key' => $name]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO teams (cbs_team_id, name) VALUES (:key, :name)');
        $insert->execute(['key' => $name, 'name' => $name]);
        return (int) $this->pdo->lastInsertId();
    }

    private function fetchPlayer(string $cbsPlayerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM players WHERE cbs_player_id = :id');
        $stmt->execute(['id' => $cbsPlayerId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function insertPlayer(RosterPlayer $player, ?int $teamId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO players (cbs_player_id, name, position, current_team_id) VALUES (:id, :name, :pos, :team)'
        );
        $stmt->execute([
            'id' => $player->cbsPlayerId,
            'name' => $player->name,
            'pos' => $player->position,
            'team' => $teamId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function updatePlayerTeam(int $playerId, ?int $teamId): void
    {
        $stmt = $this->pdo->prepare('UPDATE players SET current_team_id = :team WHERE id = :id');
        $stmt->execute(['team' => $teamId, 'id' => $playerId]);
    }

    private function fetchHold(int $playerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM player_holds WHERE player_id = :id');
        $stmt->execute(['id' => $playerId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function insertHold(int $playerId, ?int $teamId, RosterPlayer $player, int $seasonId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO player_holds
                (player_id, team_id, draft_round, draft_season, is_faq_pickup, effective_hold_year, accelerated_count, updated_season_id)
             VALUES (:player, :team, :round, :season_year, :is_faq, 0, 0, :season_id)'
        );
        $stmt->execute([
            'player' => $playerId,
            'team' => $teamId,
            'round' => $player->draftRound ?? 10, // undrafted/FA basis
            'season_year' => date('Y'),
            'is_faq' => $player->draftRound === null ? 1 : 0,
            'season_id' => $seasonId,
        ]);
    }

    private function updateHoldTeam(int $playerId, int $teamId, int $seasonId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE player_holds SET team_id = :team, updated_season_id = :season WHERE player_id = :player'
        );
        $stmt->execute(['team' => $teamId, 'season' => $seasonId, 'player' => $playerId]);
    }
}
