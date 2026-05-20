<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> Login</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-panel">
            <p class="eyebrow">Secure Admin Console</p>
            <h1><?= e($appName) ?></h1>
            <p class="muted">Melde dich an, um Verfuegbarkeit, Latenz und Service-Status zu steuern.</p>

            <?php if (!empty($error)): ?>
                <div class="alert danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="index.php?page=login" class="stacked-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label>
                    Benutzername
                    <input type="text" name="username" autocomplete="username" required autofocus>
                </label>
                <label>
                    Passwort
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button type="submit" class="btn primary wide">Einloggen</button>
            </form>
        </section>
    </main>
</body>
</html>
