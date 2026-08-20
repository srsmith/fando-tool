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
$snippet = null;
$error = null;

if ($creds === null) {
    $error = 'No CBS session cookie saved yet -- paste one on the admin screen first.';
} else {
    $client = new CbsClient($creds['cookie']);

    try {
        $html = $client->fetch($config['cbs']['roster_url']);
        $snippet = substr($html, 0, 4000);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head><title>CBS Session Test</title></head>
<body>
<h1>CBS Session Test</h1>
<p><a href="index.php">&larr; back</a></p>

<?php if ($error): ?>
    <p style="color:red;"><strong><?= htmlspecialchars($error) ?></strong></p>
<?php endif; ?>

<?php if ($snippet !== null): ?>
    <p style="color:green;"><strong>Fetched the roster page without being bounced to a login page.</strong>
        Doesn't guarantee the table parser will work, but the session cookie is good.</p>
    <h2>Roster page HTML (first 4000 chars)</h2>
    <pre style="white-space: pre-wrap; border: 1px solid #ccc; padding: 8px; max-height: 400px; overflow: auto;"><?= htmlspecialchars($snippet) ?></pre>
<?php endif; ?>

</body>
</html>
