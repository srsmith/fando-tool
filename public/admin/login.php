<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/../../vendor/autoload.php';
$config = require __DIR__ . '/../../config/config.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = (string) ($_POST['secret'] ?? '');
    if (hash_equals($config['admin_secret'], $submitted)) {
        $_SESSION['admin_authed'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Incorrect.';
}
?>
<!doctype html>
<html>
<head><title>FANDO Keeper Admin</title></head>
<body>
<h1>Admin</h1>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input type="password" name="secret" placeholder="Admin passphrase" autofocus>
    <button type="submit">Enter</button>
</form>
</body>
</html>
