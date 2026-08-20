<?php

declare(strict_types=1);

/**
 * One-off diagnostic script: fetches and saves the raw HTML of the roster
 * and draft-results pages to disk, using a CBS session cookie captured from
 * a real logged-in browser (CBS's login is behind reCAPTCHA and can't be
 * automated -- see README-scraper.md). Run this from an environment that
 * can actually reach cbssports.com (this sandbox can't), then share the
 * saved files back so the scraper's table-column detection can be
 * validated/adjusted against real markup.
 *
 * Usage: CBS_SESSION_COOKIE='...' php scripts/capture_pages.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Fando\Keeper\Scraper\CbsClient;

$config = require __DIR__ . '/../config/config.php';

$outDir = __DIR__ . '/../captures';
if (!is_dir($outDir)) {
    mkdir($outDir, 0700, true);
}

$client = new CbsClient(
    getenv('CBS_SESSION_COOKIE') ?: throw new RuntimeException('Set CBS_SESSION_COOKIE env var'),
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
