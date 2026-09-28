<?php
// Session + mot de passe partagé + jeton CSRF.

require_once __DIR__ . '/db.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('regie_sid');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Un seul compte partagé pour l'administration. Sans hash configuré, personne n'est admin.
function auth_enabled(): bool
{
    return config()['admin_password_hash'] !== '';
}

function is_admin(): bool
{
    start_session();
    return auth_enabled() && !empty($_SESSION['admin']);
}

function login(string $password): bool
{
    start_session();
    if (auth_enabled() && password_verify($password, config()['admin_password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        return true;
    }
    sleep(1); // freine les essais en rafale
    return false;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    start_session();
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}
