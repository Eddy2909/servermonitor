<?php
declare(strict_types=1);

use ModernMonitor\Auth;
use ModernMonitor\Csrf;
use ModernMonitor\Database;
use ModernMonitor\EmailNotifier;
use ModernMonitor\Monitor;
use ModernMonitor\Schema;
use ModernMonitor\ServerRepository;
use ModernMonitor\View;

require __DIR__ . '/app/bootstrap.php';

try {
    $config = load_config();
    configure_runtime($config);

    $db = new Database($config['db']);
    Schema::ensure($db);
    $auth = new Auth($db, $config);
    $repo = new ServerRepository($db);
    $appName = (string)$config['app']['name'];
    $action = (string)($_GET['action'] ?? '');
    $page = (string)($_GET['page'] ?? 'dashboard');
    if (!in_array($page, ['dashboard', 'servers', 'activity', 'settings', 'login'], true)) {
        $page = 'dashboard';
    }

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::requireValid();
        $auth->logout();
        redirect('index.php?page=login');
    }

    if ($page === 'login') {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validateRequest()) {
                $error = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
            } elseif ($auth->login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
                redirect('index.php');
            } else {
                $error = 'Benutzername oder Passwort ist falsch.';
            }
        }

        echo View::render('login.php', [
            'appName' => $appName,
            'csrf' => Csrf::token(),
            'error' => $error,
        ]);
        exit;
    }

    if ($action !== '') {
        ini_set('display_errors', '0');
        ob_start();
        $auth->requireLogin();
        Csrf::requireValid();
        handle_action($action, $repo);
    }

    $auth->requireLogin();
    echo View::render('dashboard.php', [
        'appName' => $appName,
        'username' => $auth->username(),
        'csrf' => Csrf::token(),
        'activePage' => $page,
        'servers' => $repo->all(),
        'stats' => $repo->stats(),
        'recentChecks' => $repo->recentChecks(),
        'activityChecks' => $repo->activityChecks(),
        'notifications' => $repo->notificationLog(null, 12),
        'settings' => $repo->settings(),
        'publicPages' => $repo->publicPages(),
        'chartData' => $repo->chartData(),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    $debug = isset($config) && !empty($config['app']['debug']);
    $details = $exception->getMessage();
    if ($debug && $exception->getPrevious() !== null) {
        $details .= ' ' . $exception->getPrevious()->getMessage();
    }
    if (!empty($action)) {
        json_response(['ok' => false, 'message' => $debug ? $details : 'Serverfehler bei der Aktion.'], 500);
    }
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="assets/app.css">';
    echo '<main class="auth-shell" style="padding:32px"><section class="auth-panel">';
    echo '<h1>Fehler</h1><p class="muted">Die Anwendung konnte nicht gestartet werden.</p>';
    echo '<div class="alert danger">' . e($debug ? $details : 'Bitte config.php, Datenbank und Tabellen pruefen.') . '</div>';
    echo '<a class="btn primary" href="install.php">Installation pruefen</a>';
    echo '</section></main>';
}

function handle_action(string $action, ServerRepository $repo): void
{
    try {
        if ($action === 'server.save') {
            $server = $repo->save($_POST);
            json_response(['ok' => true, 'row' => render_server_row($server)]);
        }

        if ($action === 'settings.save') {
            $settings = $repo->saveSettings($_POST);
            json_response(['ok' => true, 'settings' => $settings, 'message' => 'Einstellungen gespeichert.']);
        }

        if ($action === 'public_page.save') {
            $page = $repo->savePublicPage($_POST);
            json_response(['ok' => true, 'page' => $page, 'message' => 'Public Page gespeichert.']);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_response(['ok' => false, 'message' => 'Ungueltige ID.'], 422);
        }

        if ($action === 'public_page.delete') {
            $repo->deletePublicPage($id);
            json_response(['ok' => true, 'message' => 'Public Page geloescht.']);
        }

        if ($action === 'server.delete') {
            $repo->delete($id);
            json_response(['ok' => true]);
        }

        if ($action === 'server.toggle') {
            $server = $repo->toggle($id);
            json_response(['ok' => true, 'row' => render_server_row($server), 'message' => 'Status aktualisiert.']);
        }

        if ($action === 'server.check') {
            $server = $repo->find($id);
            if ($server === null) {
                json_response(['ok' => false, 'message' => 'Eintrag wurde nicht gefunden.'], 404);
            }
            $monitor = new Monitor();
            $result = $monitor->check($server);
            $updated = $repo->recordCheck($id, $result);
            (new EmailNotifier($repo))->notifyIfNeeded($server, $updated, $result);
            $server = $repo->find($id) ?? $updated;
            json_response(['ok' => true, 'row' => render_server_row($server), 'message' => 'Check abgeschlossen.']);
        }

        if ($action === 'server.details') {
            $details = $repo->serverDetails($id);
            if (empty($details)) {
                json_response(['ok' => false, 'message' => 'Eintrag wurde nicht gefunden.'], 404);
            }
            json_response(['ok' => true, 'details' => $details]);
        }

        json_response(['ok' => false, 'message' => 'Unbekannte Aktion.'], 404);
    } catch (InvalidArgumentException $exception) {
        json_response(['ok' => false, 'message' => $exception->getMessage()], 422);
    } catch (Throwable $exception) {
        json_response(['ok' => false, 'message' => $exception->getMessage()], 500);
    }
}

function render_server_row(array $server): string
{
    return View::render('partials/server_row.php', ['server' => $server]);
}
