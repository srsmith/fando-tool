<?php

declare(strict_types=1);

require __DIR__ . '/_guard.php';
require __DIR__ . '/../../vendor/autoload.php';

use Fando\Keeper\Db\CredentialsRepository;
use Fando\Keeper\Db\Database;
use Fando\Keeper\Scraper\CbsClient;

$config = require __DIR__ . '/../../config/config.php';
$pdo = Database::connect($config['db']);
$credentials = new CredentialsRepository($pdo, $config['credentials_encryption_key']);

$creds = $credentials->load();
$diagnostics = null;
$error = null;

if ($creds === null) {
    $error = 'No CBS credentials saved yet -- set them on the admin screen first.';
} else {
    $client = new CbsClient(
        loginUrl: $config['cbs']['login_url'],
        username: $creds['username'],
        password: $creds['password'],
        usernameField: $config['cbs']['username_field'],
        passwordField: $config['cbs']['password_field'],
    );

    try {
        $diagnostics = $client->diagnoseLogin();
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head><title>CBS Login Diagnostics</title></head>
<body>
<h1>CBS Login Diagnostics</h1>
<p><a href="index.php">&larr; back</a></p>

<?php if ($error): ?>
    <p style="color:red;"><strong><?= htmlspecialchars($error) ?></strong></p>
<?php endif; ?>

<?php if ($diagnostics): ?>
    <h2>What we requested</h2>
    <p>Login URL: <code><?= htmlspecialchars($diagnostics['login_url']) ?></code></p>
    <p>Detected form action: <code><?= htmlspecialchars($diagnostics['form_action']) ?></code></p>
    <p>All input field names found on the login form:
        <code><?= htmlspecialchars(implode(', ', $diagnostics['detected_fields'])) ?></code>
    </p>
    <p>Guessed username field: <strong><?= htmlspecialchars($diagnostics['guessed_username_field'] ?? '(none found)') ?></strong></p>
    <p>Guessed password field: <strong><?= htmlspecialchars($diagnostics['guessed_password_field'] ?? '(none found)') ?></strong></p>

    <?php if (isset($diagnostics['response_still_has_password_field'])): ?>
        <h2>Result</h2>
        <p>
            Response still contains a password field (i.e. login looked like it failed):
            <strong><?= $diagnostics['response_still_has_password_field'] ? 'YES' : 'no' ?></strong>
        </p>
    <?php endif; ?>

    <h2>Login page HTML (first 4000 chars)</h2>
    <pre style="white-space: pre-wrap; border: 1px solid #ccc; padding: 8px; max-height: 400px; overflow: auto;"><?= htmlspecialchars($diagnostics['login_page_snippet']) ?></pre>

    <?php if (isset($diagnostics['response_snippet'])): ?>
        <h2>Response after submitting login (first 4000 chars)</h2>
        <pre style="white-space: pre-wrap; border: 1px solid #ccc; padding: 8px; max-height: 400px; overflow: auto;"><?= htmlspecialchars($diagnostics['response_snippet']) ?></pre>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
