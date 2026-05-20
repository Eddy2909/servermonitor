<?php
declare(strict_types=1);

use ModernMonitor\Csrf;
use ModernMonitor\Database;
use ModernMonitor\Schema;

require __DIR__ . '/app/bootstrap.php';

$config = load_config(false);
$message = '';
$error = '';
$adminExists = false;
$configMissing = empty($config);

if (!$configMissing) {
    configure_runtime($config);
    start_secure_session($config['security']);
    try {
        $db = new Database($config['db']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validateRequest()) {
                $error = 'CSRF token ungueltig. Bitte Formular neu laden.';
            } else {
                if (!empty($_POST['create_schema'])) {
                    Schema::ensure($db);
                }

                $adminExists = admin_exists($db);
                if ($adminExists) {
                    $error = 'Es existiert bereits ein Admin. Bitte install.php jetzt loeschen.';
                } else {
                    $username = trim((string)($_POST['username'] ?? ''));
                    $email = trim((string)($_POST['email'] ?? ''));
                    $password = (string)($_POST['password'] ?? '');
                    $passwordRepeat = (string)($_POST['password_repeat'] ?? '');

                    if ($username === '' || $email === '' || strlen($password) < 12 || $password !== $passwordRepeat) {
                        $error = 'Bitte Benutzername, E-Mail und ein identisches Passwort mit mindestens 12 Zeichen angeben.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT, [
                            'cost' => (int)($config['security']['password_cost'] ?? 12),
                        ]);
                        $db->execute(
                            'INSERT INTO ' . $db->table('users') . '
                             (username, email, password_hash, role, created_at)
                             VALUES (:username, :email, :password_hash, "admin", NOW())',
                            [
                                'username' => $username,
                                'email' => $email,
                                'password_hash' => $hash,
                            ]
                        );
                        $message = 'Admin wurde angelegt. Loesche install.php vor dem produktiven Betrieb.';
                        $adminExists = true;
                    }
                }
            }
        } else {
            $adminExists = tables_exist($db) && admin_exists($db);
        }
    } catch (Throwable $exception) {
        $details = $exception->getMessage();
        if (!empty($config['app']['debug']) && $exception->getPrevious() !== null) {
            $details .= ' ' . $exception->getPrevious()->getMessage();
        }
        $error = !empty($config['app']['debug'])
            ? $details
            : 'Datenbankverbindung oder Schema nicht bereit. Pruefe config.php, Datenbankname, Benutzer, Passwort, Host, Port und Tabellen-Prefix.';
    }
}

function tables_exist(Database $db): bool
{
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name',
        ['table_name' => $db->prefix() . 'users']
    );

    return (int)($row['total'] ?? 0) === 1;
}

function admin_exists(Database $db): bool
{
    if (!tables_exist($db)) {
        return false;
    }

    $row = $db->fetchOne('SELECT COUNT(*) AS total FROM ' . $db->table('users'));
    return (int)($row['total'] ?? 0) > 0;
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="brand-mark">PSM</div>
            <p class="eyebrow">Setup</p>
            <h1>Installation</h1>

            <?php if ($configMissing): ?>
                <div class="alert danger">config.php fehlt. Kopiere config.php.sample nach config.php und trage die Datenbankdaten sowie den Cron-Token ein.</div>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <div class="alert"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (!$configMissing && !$adminExists): ?>
                <form method="post" class="stacked-form">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                    <label class="toggle-line"><input type="checkbox" name="create_schema" value="1" checked> Tabellen automatisch anlegen</label>
                    <label>Admin Benutzername<input name="username" required autocomplete="username"></label>
                    <label>Admin E-Mail<input name="email" type="email" required autocomplete="email"></label>
                    <label>Passwort<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
                    <label>Passwort wiederholen<input name="password_repeat" type="password" required minlength="12" autocomplete="new-password"></label>
                    <button class="btn primary wide" type="submit">Admin anlegen</button>
                </form>
            <?php elseif ($adminExists): ?>
                <p class="muted">Setup ist abgeschlossen. Entferne install.php vom Server und oeffne danach das Dashboard.</p>
                <a class="btn primary wide" href="index.php">Dashboard oeffnen</a>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
