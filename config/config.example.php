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
        'login_url' => 'https://www.cbssports.com/login/', // TODO: confirm actual login URL
        'roster_url' => 'https://4and1.football.cbssports.com/teams/all',
        'draft_results_url' => 'https://4and1.football.cbssports.com/draft/results',
        // Leave null to let CbsClient auto-detect the form's username/password
        // field names; set explicitly if auto-detection guesses wrong.
        'username_field' => null,
        'password_field' => null,
    ],
    // Shared secret for the hidden /admin screen -- generate with e.g.
    // `php -r "echo bin2hex(random_bytes(24));"` and keep it out of git.
    'admin_secret' => '',
    // Key used to encrypt CBS credentials at rest (sodium). Generate with
    // `php -r "echo bin2hex(sodium_crypto_secretbox_keygen());"`.
    'credentials_encryption_key' => '',
];
