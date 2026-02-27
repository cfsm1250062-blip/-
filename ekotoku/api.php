<?php
// ========================================
// Enetoku - API エンドポイント (Gemini最適化版)
// ========================================

// ★ 出力バッファリング開始
ob_start();

// エラーを画面に出さず変数に捕捉する
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    return true;
});

// 致命的エラーもJSONで返す
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'サーバーエラー: ' . $error['message'],
            'detail' => $error['file'] . ' line ' . $error['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// JSON以外の出力が混入しないようバッファをクリアしてからヘッダー送信
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ログイン不要なアクション
if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    if (login($username, $password)) {
        jsonResponse(['ok' => true, 'user' => getCurrentUser()]);
    } else {
        jsonResponse(['ok' => false, 'error' => 'ユーザー名またはパスワードが間違っています。'], 401);
    }
}

if ($action === 'logout') {
    logout();
    jsonResponse(['ok' => true]);
}

if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $result = registerUser(
        trim($data['username'] ?? ''),
        $data['password'] ?? '',
        trim($data['display_name'] ?? ''),
        trim($data['email'] ?? '')
    );
    if ($result === true) {
        jsonResponse(['ok' => true]);
    } else {
        jsonResponse(['ok' => false, 'error' => $result], 400);
    }
}

// 以降はログイン必須
if (!isLoggedIn()) {
    jsonResponse(['ok' => false, 'error' => '認証が必要です。'], 401);
}

$user = getCurrentUser();
$db   = getDB();

// ★ BUG FIX: セッションの is_admin をDBの最新値で上書きする。
// admin → 一般ユーザーへの切り替え後もセッションが残存している場合に
// 権限が昇格したままになるバグを防ぐ。
// 画面遷移のたびにAPIが呼ばれるため、ここで毎回DBを確認する。
(function() use (&$user, $db) {
    $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if ($row === false) {
        // ユーザーがDBから削除されていた場合は強制ログアウト
        logout();
        jsonResponse(['ok' => false, 'error' => 'ユーザーが存在しません。'], 401);
    }
    $isAdminFromDB = (bool)$row['is_admin'];
    // セッションと食い違いがあれば補正
    if ($_SESSION['is_admin'] !== $isAdminFromDB) {
        $_SESSION['is_admin'] = $isAdminFromDB;
        $user['is_admin']     = $isAdminFromDB;
    }
})();

switch ($action) {
    case 'me':
        jsonResponse(['ok' => true, 'user' => $user]);

    case 'records':
        $targetUserId = $user['id'];
        if (isAdmin() && isset($_GET['user_id'])) {
            $targetUserId = (int)$_GET['user_id'];
        }
        if (isAdmin() && isset($_GET['all']) && $_GET['all'] === '1') {
            $stmt = $db->query('
                SELECT r.*, u.username, u.display_name 
                FROM utility_records r 
                JOIN users u ON r.user_id = u.id 
                ORDER BY r.billing_year DESC, r.billing_month DESC, r.utility_type
            ');
        } else {
            $stmt = $db->prepare('
                SELECT r.* FROM utility_records r 
                WHERE r.user_id = ? 
                ORDER BY r.billing_year DESC, r.billing_month DESC, r.utility_type
            ');
            $stmt->execute([$targetUserId]);
        }
        jsonResponse(['ok' => true, 'records' => $stmt->fetchAll()]);

    case 'save_record':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $recordUserId = $user['id'];
        if (isAdmin() && !empty($data['user_id'])) {
            $recordUserId = (int)$data['user_id'];
        }
        $requiredFields = ['utility_type', 'billing_year', 'billing_month', 'billing_amount'];
        foreach ($requiredFields as $f) {
            if (empty($data[$f])) {
                jsonResponse(['ok' => false, 'error' => "$f は必須です。"], 400);
            }
        }
        $sql = '
            INSERT INTO utility_records 
            (user_id, utility_type, billing_year, billing_month, usage_amount, usage_unit, billing_amount, billing_date, memo, ocr_raw_text)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            usage_amount=VALUES(usage_amount), usage_unit=VALUES(usage_unit),
            billing_amount=VALUES(billing_amount), billing_date=VALUES(billing_date),
            memo=VALUES(memo), ocr_raw_text=VALUES(ocr_raw_text), updated_at=NOW()
        ';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $recordUserId,
            $data['utility_type'],
            (int)$data['billing_year'],
            (int)$data['billing_month'],
            $data['usage_amount'] ?? null,
            $data['usage_unit'] ?? null,
            (float)$data['billing_amount'],
            $data['billing_date'] ?? null,
            $data['memo'] ?? null,
            $data['ocr_raw_text'] ?? null,
        ]);
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);

    case 'delete_record':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(['ok' => false, 'error' => 'IDが必要です。'], 400);
        if (isAdmin()) {
            $stmt = $db->prepare('DELETE FROM utility_records WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare('DELETE FROM utility_records WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
        }
        jsonResponse(['ok' => true]);

    // ──────────────────────────────
    // OCR解析 (Gemini Vision)
    // ──────────────────────────────
    case 'ocr':
        if (empty($_FILES['image'])) {
            jsonResponse(['ok' => false, 'error' => '画像ファイルが必要です。'], 400);
        }
        $file = $_FILES['image'];
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            jsonResponse(['ok' => false, 'error' => 'ファイルサイズが大きすぎます（最大10MB）。'], 400);
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            jsonResponse(['ok' => false, 'error' => '対応していないファイル形式です。'], 400);
        }

        $imageData = base64_encode(file_get_contents($file['tmp_name']));

        $ocrPrompt = <<<PROMPT
この画像は日本の水道・電気・ガスなどの公共料金の明細書です。
以下の情報を抽出してください：

{
  "utility_type": "water|electricity|gas|other",
  "billing_year": 年（数値）,
  "billing_month": 月（数値）,
  "billing_amount": 請求金額（数値、円）,
  "usage_amount": 使用量（数値）,
  "usage_unit": 使用量の単位（m3, kWh, MJ など）,
  "billing_date": "YYYY-MM-DD形式の支払期限",
  "raw_text": "読み取った主要なテキスト"
}

明細書でない場合や読み取れない場合は {"error": "読み取れませんでした"} を返してください。
数値は数字のみで返してください（カンマや単位は含めない）。
PROMPT;

        // responseMimeType => 'application/json' を指定して呼び出す
        [$httpCode, $response] = callVisionAI($ocrPrompt, $imageData, $mime, 1024);

        if ($httpCode !== 200) {
            jsonResponse(['ok' => false, 'error' => 'OCR API エラー: ' . $response], 500);
        }
        
        $text = extractTextFromAIResponse($response);
        
        // Gemini API側のJSONモードにより、直接パースできる可能性が極めて高い
        $parsed = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 万が一マークダウンブロック (```json ... ```) が残っていた場合のフォールバック
            if (preg_match('/\{.*\}/s', $text, $m)) {
                $parsed = json_decode($m[0], true);
            }
            if (!$parsed) {
                $parsed = ['error' => 'パースできませんでした'];
            }
        }
        jsonResponse(['ok' => true, 'data' => $parsed, 'raw' => $text, 'provider' => 'gemini']);

    // ──────────────────────────────
    // AI節約分析 (Gemini)
    // ──────────────────────────────
    case 'ai_analysis':
        $stmt = $db->prepare('
            SELECT utility_type, billing_year, billing_month, usage_amount, usage_unit, billing_amount
            FROM utility_records 
            WHERE user_id = ? 
            ORDER BY billing_year DESC, billing_month DESC 
            LIMIT 24
        ');
        $stmt->execute([$user['id']]);
        $records = $stmt->fetchAll();

        if (empty($records)) {
            jsonResponse(['ok' => false, 'error' => 'データがありません。まず記録を追加してください。']);
        }

        $dataText = "【光熱費データ】\n";
        foreach ($records as $r) {
            $typeLabel = ['water'=>'水道', 'electricity'=>'電気', 'gas'=>'ガス', 'other'=>'その他'][$r['utility_type']] ?? $r['utility_type'];
            $usage = $r['usage_amount'] ? "{$r['usage_amount']}{$r['usage_unit']}" : '不明';
            $dataText .= "{$r['billing_year']}年{$r['billing_month']}月 {$typeLabel}: {$r['billing_amount']}円 使用量:{$usage}\n";
        }

        $analysisPrompt = <<<PROMPT
あなたは日本の光熱費節約の専門家です。
以下のユーザーの光熱費データを分析して、具体的で実践的な節約アドバイスを日本語で提供してください。

$dataText

以下の観点で分析してください：
1. **トレンド分析**: 前月比・前年同月比の変化
2. **異常値の検出**: 突出して高い月があれば指摘
3. **具体的な節約術**: 各光熱費タイプに応じた3〜5つの実践的アドバイス
4. **節約効果の試算**: 実践した場合の概算削減額

読みやすいMarkdown形式で、絵文字も使って親しみやすく回答してください。
PROMPT;

        [$httpCode, $response] = callTextAI($analysisPrompt, 2048);

        if ($httpCode !== 200) {
            jsonResponse(['ok' => false, 'error' => 'AI API エラー'], 500);
        }
        $analysisText = extractTextFromAIResponse($response);

        $stmt = $db->prepare('INSERT INTO ai_analysis (user_id, analysis_text) VALUES (?, ?)');
        $stmt->execute([$user['id'], $analysisText]);

        jsonResponse(['ok' => true, 'analysis' => $analysisText, 'provider' => 'gemini']);

    case 'admin_users':
        if (!isAdmin()) jsonResponse(['ok' => false, 'error' => '権限がありません。'], 403);
        $stmt = $db->query('
            SELECT u.id, u.username, u.display_name, u.email, u.is_admin, u.created_at,
                   COUNT(r.id) as record_count,
                   SUM(CASE WHEN r.billing_year = YEAR(NOW()) AND r.billing_month = MONTH(NOW()) THEN r.billing_amount ELSE 0 END) as this_month_total
            FROM users u 
            LEFT JOIN utility_records r ON u.id = r.user_id
            GROUP BY u.id
            ORDER BY u.id
        ');
        jsonResponse(['ok' => true, 'users' => $stmt->fetchAll()]);

    case 'admin_stats':
        if (!isAdmin()) jsonResponse(['ok' => false, 'error' => '権限がありません。'], 403);
        $stmt = $db->query('
            SELECT billing_year, billing_month, utility_type,
                   COUNT(*) as user_count,
                   SUM(billing_amount) as total_amount,
                   AVG(billing_amount) as avg_amount
            FROM utility_records
            GROUP BY billing_year, billing_month, utility_type
            ORDER BY billing_year DESC, billing_month DESC, utility_type
            LIMIT 100
        ');
        $monthly = $stmt->fetchAll();

        $stmt = $db->query('
            SELECT u.display_name, u.username, 
                   SUM(r.billing_amount) as total,
                   COUNT(r.id) as records
            FROM users u
            LEFT JOIN utility_records r ON u.id = r.user_id
            WHERE u.is_admin = 0
            GROUP BY u.id
            ORDER BY total DESC
        ');
        $byUser = $stmt->fetchAll();

        jsonResponse(['ok' => true, 'monthly' => $monthly, 'by_user' => $byUser]);

    case 'past_analyses':
        $stmt = $db->prepare('SELECT * FROM ai_analysis WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$user['id']]);
        jsonResponse(['ok' => true, 'analyses' => $stmt->fetchAll()]);

    default:
        jsonResponse(['ok' => false, 'error' => '不明なアクション: ' . $action], 400);
}

// ============================================================
// Google Gemini API ヘルパー関数
// ============================================================

function callTextAI(string $prompt, int $maxTokens = 2048): array {
    $payload = [
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens
        ],
    ];
    return geminiPost($payload, 90);
}

function callVisionAI(string $prompt, string $base64Image, string $mime, int $maxTokens = 1024): array {
    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => [
                ['inline_data' => ['mime_type' => $mime, 'data' => $base64Image]],
                ['text' => $prompt],
            ],
        ]],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'responseMimeType' => 'application/json' // 【最適化】JSON形式での返却をGeminiに強制
        ],
    ];
    return geminiPost($payload, 60);
}

function extractTextFromAIResponse(string $rawResponse): string {
    $data = json_decode($rawResponse, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

function geminiPost(array $payload, int $timeout): array {
    $model = GEMINI_MODEL;
    $apiKey = GEMINI_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [$httpCode, $response];
}