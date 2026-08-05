<?php

declare(strict_types=1);

namespace Fando\Keeper\Scraper;

use Fando\Keeper\Db\CredentialsRepository;
use Fando\Keeper\Db\ScrapeImportService;

final class ScrapeRunner
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly array $cbsConfig,
        private readonly CredentialsRepository $credentials,
    ) {
    }

    /** @return string[] warnings from the import */
    public function run(): array
    {
        $creds = $this->credentials->load();
        if ($creds === null) {
            throw new \RuntimeException('No CBS credentials saved yet -- set them on the admin screen first.');
        }

        $client = new CbsClient(
            loginUrl: $this->cbsConfig['login_url'],
            username: $creds['username'],
            password: $creds['password'],
            usernameField: $this->cbsConfig['username_field'],
            passwordField: $this->cbsConfig['password_field'],
        );

        $seasonId = $this->currentSeasonId();
        $importer = new ScrapeImportService($this->pdo);
        $warnings = [];

        $warnings = array_merge($warnings, $this->timed('roster', function () use ($client, $importer, $seasonId) {
            $html = $client->fetch($this->cbsConfig['roster_url']);
            $players = (new RosterScraper())->parse($html, $this->cbsConfig['roster_url']);
            return $importer->importRoster($players, $seasonId);
        }));

        $this->timed('draft_results', function () use ($client, $importer, $seasonId) {
            $html = $client->fetch($this->cbsConfig['draft_results_url']);
            $picks = (new DraftResultsScraper())->parse($html);
            $importer->importDraftPicks($picks, $seasonId);
            return [];
        });

        return $warnings;
    }

    private function timed(string $target, callable $work): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO scrape_log (target, started_at, status) VALUES (:t, NOW(), \'running\')');
        $stmt->execute(['t' => $target]);
        $logId = (int) $this->pdo->lastInsertId();

        try {
            $result = $work();
            $message = empty($result) ? null : implode(' | ', $result);
            $this->finishLog($logId, 'success', $message);
            return $result;
        } catch (\Throwable $e) {
            $this->finishLog($logId, 'failed', $e->getMessage());
            throw $e;
        }
    }

    private function finishLog(int $logId, string $status, ?string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE scrape_log SET finished_at = NOW(), status = :status, message = :message WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'message' => $message, 'id' => $logId]);
    }

    private function currentSeasonId(): int
    {
        $stmt = $this->pdo->query('SELECT id FROM seasons WHERE is_current = 1 LIMIT 1');
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('No season is marked is_current=1 in the seasons table.');
        }
        return (int) $id;
    }
}
