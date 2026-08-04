<?php

declare(strict_types=1);

/**
 * One-off diagnostic script: logs into CBS and saves the raw HTML of the
 * login, roster, and draft-results pages to disk. Run this from an
 * environment that can actually reach cbssports.com (this sandbox can't --
 * see README-scraper.md), then share the saved files back so the scraper's
 * table-column detection can be validated/adjusted against real markup.
 *
 * Usage: php scripts/capture_pages.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Fando\Keeper\Scraper\CbsClient;

$config = require __DIR__ . '/../config/config.php';

$outDir = __DIR__ . '/../captures';
if (!is_dir($outDir)) {
    mkdir($outDir, 0700, true);
}

$client = new CbsClient(
    loginUrl: $config['cbs']['login_url'],
    username: getenv('CBS_USERNAME') ?: throw new RuntimeException('Set CBS_USERNAME env var'),
    password: getenv('CBS_PASSWORD') ?: throw new RuntimeException('Set CBS_PASSWORD env var'),
    usernameField: $config['cbs']['username_field'],
    passwordField: $config['cbs']['password_field'],
);

$targets = [
    'roster.html' => $config['cbs']['roster_url'],
    'draft_results.html' => $config['cbs']['draft_results_url'],
];

foreach ($targets as $filename => $url) {
    echo "Fetching {$url} ...\n";
    $html = $client->fetch($url);
    file_put_contents("{$outDir}/{$filename}", $html);
    echo "  saved to captures/{$filename} (" . strlen($html) . " bytes)\n";
}

echo "Done. The captures/ directory is gitignored -- share these files back for parser tuning.\n";
