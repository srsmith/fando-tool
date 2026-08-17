<?php

declare(strict_types=1);

require __DIR__ . '/_guard.php';
require __DIR__ . '/../../vendor/autoload.php';

use Fando\Keeper\Db\CredentialsRepository;
use Fando\Keeper\Db\Database;

$config = require __DIR__ . '/../../config/config.php';
$pdo = Database::connect($config['db']);
$credentials = new CredentialsRepository($pdo, $config['credentials_encryption_key']);

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_credentials') {
    $username = trim((string) ($_POST['cbs_username'] ?? ''));
    $password = (string) ($_POST['cbs_password'] ?? '');
    if ($username !== '' && $password !== '') {
        $credentials->save($username, $password);
        $message = 'CBS credentials saved.';
    } else {
        $message = 'Both fields are required.';
    }
}

$current = $credentials->load();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$logStmt = $pdo->query('SELECT target, started_at, finished_at, status, message FROM scrape_log ORDER BY id DESC LIMIT 10');
$recentScrapes = $logStmt->fetchAll();
?>
<!doctype html>
<html>
<head><title>FANDO Keeper Admin</title></head>
<body>
<h1>FANDO Keeper Admin</h1>

<?php if ($message): ?><p><strong><?= htmlspecialchars($message) ?></strong></p><?php endif; ?>
<?php if ($flash): ?><p><strong><?= htmlspecialchars($flash) ?></strong></p><?php endif; ?>

<h2>CBS Credentials</h2>
<p>Current username: <?= $current ? htmlspecialchars($current['username']) : '<em>not set</em>' ?></p>
<form method="post">
    <input type="hidden" name="action" value="save_credentials">
    <label>Username <input type="text" name="cbs_username" autocomplete="off"></label><br>
    <label>Password <input type="password" name="cbs_password" autocomplete="off"></label><br>
    <button type="submit">Save</button>
</form>

<h2>Scrape</h2>
<form method="post" action="scrape.php">
    <button type="submit">Refresh data from CBS now</button>
</form>
<p><a href="debug_login.php">Diagnose CBS login</a> (shows the login form CBS actually returns, and what we tried)</p>

<h3>Recent scrape log</h3>
<table border="1" cellpadding="4">
    <tr><th>Target</th><th>Started</th><th>Finished</th><th>Status</th><th>Message</th></tr>
    <?php foreach ($recentScrapes as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['target']) ?></td>
        <td><?= htmlspecialchars($row['started_at']) ?></td>
        <td><?= htmlspecialchars($row['finished_at'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['status']) ?></td>
        <td><?= htmlspecialchars($row['message'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
