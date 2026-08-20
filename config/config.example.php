<?php

declare(strict_types=1);

// Copy this file to config.php (gitignored) and fill in real values.
// On the hosting server, config.php should live outside the web root or be
// blocked from direct HTTP access.

return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=fando_keeper;charset=utf8mb4',
        'username' => 'fando_keeper',
        'password' => '',
    ],
    'cbs' => [
        'roster_url' => 'https://4and1.football.cbssports.com/teams/all',
        'draft_results_url' => 'https://4and1.football.cbssports.com/draft/results',
    ],
    // Shared secret for the hidden /admin screen -- generate with e.g.
    // `php -r "echo bin2hex(random_bytes(24));"` and keep it out of git.
    'admin_secret' => '',
    // Key used to encrypt the CBS session cookie at rest (sodium). Generate with
    // `php -r "echo bin2hex(random_bytes(32));"`.
    'credentials_encryption_key' => '',
];
