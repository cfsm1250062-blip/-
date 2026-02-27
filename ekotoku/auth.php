<?php
// ========================================
// Enetoku - 認証ロジック
// ========================================

require_once __DIR__ . '/config.php';

// セッションがまだ始まっていない場合のみ開始
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'           => $_SESSION['user_id'],
        'username'     => $_SESSION['username'],
        'display_name' => $_SESSION['display_name'],
        'is_admin'     => $_SESSION['is_admin'],
    ];
}

function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {

        // ★ BUG FIX:
        // ログイン前に古いセッションデータを完全にクリアし、
        // セッションIDを再生成する。
        // これにより、admin → 一般ユーザーへの切り替え時に
        // 前ユーザーの is_admin フラグ等が残留するバグを防ぐ。
        session_unset();                         // セッション変数を全消去
        session_regenerate_id(true);             // セッションIDを再生成（旧IDは削除）

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
        $_SESSION['is_admin']     = (bool)$user['is_admin'];
        return true;
    }
    return false;
}

function logout(): void {
    // ★ BUG FIX:
    // セッション変数クリア → クッキー削除 → セッション破棄 の順で
    // 確実にセッションを消去する。
    session_unset();

    // クッキーを明示的に無効化
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function registerUser(string $username, string $password, string $displayName = '', string $email = ''): bool|string {
    $db = getDB();
    // ユーザー名チェック
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return 'このユーザー名はすでに使用されています。';
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        return 'ユーザー名は3〜50文字にしてください。';
    }
    if (strlen($password) < 6) {
        return 'パスワードは6文字以上にしてください。';
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, password_hash, display_name, email) VALUES (?, ?, ?, ?)');
    $stmt->execute([$username, $hash, $displayName ?: $username, $email]);
    return true;
}
