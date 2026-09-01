<?php
declare(strict_types=1);

// Auth del panel (Edu&Cora): sesión PHP server-side + password_hash/password_verify.
// Nunca comparar la contraseña en JS — eso solo es una cortina, no una barrera.

const PANEL_PASSHASH_FILE = __DIR__ . '/../config/panel.passhash';
const PANEL_ATTEMPTS_FILE = __DIR__ . '/../guardado/panel_attempts.json';
const PANEL_MAX_ATTEMPTS = 5;
const PANEL_LOCKOUT_SECONDS = 300; // 5 minutos

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function is_panel_authenticated(): bool {
    start_secure_session();
    return !empty($_SESSION['panel_auth']);
}

function require_panel_auth(): void {
    if (!is_panel_authenticated()) {
        header('Location: panel-login.php');
        exit;
    }
}

function read_attempts(): array {
    if (!file_exists(PANEL_ATTEMPTS_FILE)) return ['count' => 0, 'locked_until' => 0];
    $raw = @file_get_contents(PANEL_ATTEMPTS_FILE);
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : ['count' => 0, 'locked_until' => 0];
}

function write_attempts(array $data): void {
    @file_put_contents(PANEL_ATTEMPTS_FILE, json_encode($data), LOCK_EX);
}

function panel_is_locked_out(): bool {
    $a = read_attempts();
    return ($a['locked_until'] ?? 0) > time();
}

function panel_seconds_until_unlock(): int {
    $a = read_attempts();
    return max(0, (int)($a['locked_until'] ?? 0) - time());
}

function panel_record_failed_attempt(): void {
    $a = read_attempts();
    $a['count'] = (int)($a['count'] ?? 0) + 1;
    if ($a['count'] >= PANEL_MAX_ATTEMPTS) {
        $a['locked_until'] = time() + PANEL_LOCKOUT_SECONDS;
        $a['count'] = 0;
    }
    write_attempts($a);
}

function panel_record_success(): void {
    write_attempts(['count' => 0, 'locked_until' => 0]);
}

function panel_verify_password(string $password): bool {
    if (!file_exists(PANEL_PASSHASH_FILE)) return false;
    $hash = trim((string) @file_get_contents(PANEL_PASSHASH_FILE));
    if ($hash === '') return false;
    return password_verify($password, $hash);
}
