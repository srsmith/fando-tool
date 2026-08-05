<?php

declare(strict_types=1);

/**
 * Cron entry point: refreshes rosters + draft pick ownership from CBS.
 * Usage: php scripts/scrape.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Fando\Keeper\Db\CredentialsRepository;
use Fando\Keeper\Db\Database;
use Fando\Keeper\Scraper\ScrapeRunner;

$config = require __DIR__ . '/../config/config.php';
$pdo = Database::connect($config['db']);
$credentials = new CredentialsRepository($pdo, $config['credentials_encryption_key']);

$runner = new ScrapeRunner($pdo, $config['cbs'], $credentials);

try {
    $warnings = $runner->run();
    echo "Scrape completed.\n";
    foreach ($warnings as $warning) {
        echo "WARNING: {$warning}\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Scrape failed: {$e->getMessage()}\n");
    exit(1);
}
