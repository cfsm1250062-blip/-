<?php
// ========================================
// Enetoku - 設定ファイル (Gemini最適化版)
// ========================================

// データベース設定 (環境に合わせて変更してください)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // MySQLユーザー名
define('DB_PASS', '');             // MySQLパスワード
define('DB_NAME', 'enetoku');
define('DB_PORT', 3306);

// セッション設定
define('SESSION_LIFETIME', 3600 * 8); // 8時間

// ファイルアップロード設定
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']);

// ──────────────────────────────────────────
// Google Gemini API 設定
// ──────────────────────────────────────────
define('GEMINI_API_KEY', 'AIzaSyCMFec_TJaX3OCvVKfvj79d2JjtpBwsOz0'); 
define('GEMINI_MODEL', 'gemini-3-flash-preview'); // 利用するモデルを指定

// アプリ設定
define('APP_NAME', 'Enetoku');
define('APP_TAGLINE', 'SAVE WATER, SAVE MONEY');
define('APP_VERSION', '1.0.0');

// タイムゾーン
date_default_timezone_set('Asia/Tokyo');

// エラー表示 (本番では false に)
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// DB接続取得
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'DB接続エラー: ' . $e->getMessage() . '  — config.php のDB設定を確認してください。',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $pdo;
}

// JSONレスポンス出力
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// アップロードディレクトリ作成
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}