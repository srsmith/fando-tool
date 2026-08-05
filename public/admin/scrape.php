<?php

declare(strict_types=1);

require __DIR__ . '/_guard.php';
require __DIR__ . '/../../vendor/autoload.php';

use Fando\Keeper\Db\CredentialsRepository;
use Fando\Keeper\Db\Database;
use Fando\Keeper\Scraper\ScrapeRunner;

$config = require __DIR__ . '/../../config/config.php';
$pdo = Database::connect($config['db']);
$credentials = new CredentialsRepository($pdo, $config['credentials_encryption_key']);

$runner = new ScrapeRunner($pdo, $config['cbs'], $credentials);

try {
    $warnings = $runner->run();
    $_SESSION['flash'] = empty($warnings)
        ? 'Scrape completed successfully.'
        : 'Scrape completed with warnings: ' . implode(' / ', $warnings);
} catch (\Throwable $e) {
    $_SESSION['flash'] = 'Scrape failed: ' . $e->getMessage();
}

header('Location: index.php');
