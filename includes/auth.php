<?php
declare(strict_types=1);

const SESSION_IDLE_SECONDS = 7200;  // auto logout after 2h of inactivity
const LOGIN_MAX_ATTEMPTS   = 5;     // per IP
const LOGIN_WINDOW_MINUTES = 15;

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    if (empty($_SESSION['admin_id'])) {
        return $admin = null;
    }
    if (time() - (int) ($_SESSION['last_active'] ?? 0) > SESSION_IDLE_SECONDS) {
        logout_admin();
        return $admin = null;
    }
    $_SESSION['last_active'] = time();
    $st = db()->prepare('SELECT id, username, email, last_login FROM admins WHERE id = ?');
    $st->execute([$_SESSION['admin_id']]);
    $admin = $st->fetch() ?: null;
    if (!$admin) {
        logout_admin();
    }
    return $admin;
}

function require_login(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('login.php');
    }
    return $admin;
}

function too_many_login_attempts(string $ip): bool
{
    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)')->execute();
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([$ip]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function attempt_login(string $login, string $password): bool
{
    $ip = client_ip();
    $st = db()->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$login, $login]);
    $row = $st->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        return false;
    }

    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$row['id']]);

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $row['id'];
    $_SESSION['last_active'] = time();
    unset($_SESSION['csrf']);
    return true;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
