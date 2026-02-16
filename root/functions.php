<?php
// XSS対策
function h($s){
    return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
}

// セッションにトークンセット（CSRF）
function setToken(){
    $token = sha1(uniqid(mt_rand(), true));
    $_SESSION['token'] = $token;
}

// トークンが無ければ作る
function ensureToken(){
    if (empty($_SESSION['token'])) {
        setToken();
    }
}

// POSTされたトークンをチェック（従来フォーム用）
function checkToken(){
    if(empty($_SESSION['token']) || (!isset($_POST['token'])) || ($_SESSION['token'] != $_POST['token'])){
        echo 'Invalid POST', PHP_EOL;
        exit;
    }
}

// Header の CSRF トークンをチェック（Fetch用）
function checkTokenHeader(){
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    if (empty($_SESSION['token']) || $token !== $_SESSION['token']) {
        http_response_code(403);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(["error" => "csrf"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// どちらか通ればOK（POST token or header）
function checkTokenAny(){
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $hToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    $pToken = $_POST['token'] ?? '';
    if (empty($_SESSION['token']) || (($hToken !== $_SESSION['token']) && ($pToken !== $_SESSION['token']))) {
        http_response_code(403);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(["error" => "csrf"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function requireLogin(){
    if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
        http_response_code(401);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(["error" => "not_logged_in"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// POSTされた値のバリデーション
function validation($datas,$confirm = true)
{
    $errors = [];

    // ユーザー名のチェック
    if(empty($datas['name'])) {
        $errors['name'] = 'Please enter username.';
    }else if(mb_strlen($datas['name']) > 20) {
        $errors['name'] = 'Please enter up to 20 characters.';
    }

    // パスワードのチェック（正規表現）
    if(empty($datas["password"])){
        $errors['password']  = "Please enter a password.";
    }else if(!preg_match('/\A[a-z\d]{8,100}+\z/i',$datas["password"])){
        $errors['password'] = "Please set a password with at least 8 characters.";
    }

    // パスワード入力確認チェック（ユーザー新規登録時のみ使用）
    if($confirm){
        if(empty($datas["confirm_password"])){
            $errors['confirm_password']  = "Please confirm password.";
        }else if(empty($errors['password']) && ($datas["password"] != $datas["confirm_password"])){
            $errors['confirm_password'] = "Password did not match.";
        }
    }

    return $errors;
}

// ---------- ユーザー別ファイル保存 ----------

// data ディレクトリ（htdocs配下に data/ を置く想定）
function dataRoot(): string {
    return __DIR__ . "/data";
}

function userDir(int $userId): string {
    return dataRoot() . "/users/u_" . $userId;
}

function ensureUserDir(int $userId): string {
    $dir = userDir($userId);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

function readingsPath(int $userId): string {
    return ensureUserDir($userId) . "/readings.json";
}

function loadReadings(int $userId): array {
    $path = readingsPath($userId);
    if (!file_exists($path)) return [];
    $raw = file_get_contents($path);
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : [];
}

function saveReadings(int $userId, array $items): void {
    $path = readingsPath($userId);
    $tmp = $path . ".tmp";
    file_put_contents($tmp, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $path);
    @chmod($path, 0600);
}
