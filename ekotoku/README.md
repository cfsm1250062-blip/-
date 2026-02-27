# Enetoku - 水道光熱費管理アプリ
## セットアップ手順

### 必要環境
- PHP 8.0以上 + cURL拡張
- MySQL 5.7以上 / MariaDB 10.3以上
- Webサーバー (Apache / Nginx / XAMPP / MAMP など)
- Anthropic API キー（OCR・AI分析機能に使用）

---

## インストール手順

### 1. ファイルをWebサーバーに配置

```
htdocs/enetoku/ または /var/www/html/enetoku/ などに配置
```

ファイル構成:
```
enetoku/
├── index.php        ← メイン画面
├── api.php          ← APIエンドポイント
├── auth.php         ← 認証ロジック
├── config.php       ← 設定ファイル
├── style.css        ← スタイルシート
├── app.js           ← フロントエンドJS
├── install.sql      ← DBセットアップSQL
├── Enetoku_logo.png ← ロゴ画像
└── uploads/         ← 自動作成されます
```

### 2. データベースを作成

phpMyAdminでSQLを実行:
1. phpMyAdminを開く (`http://localhost/phpmyadmin`)
2. 「SQL」タブを開く
3. `install.sql` の内容を貼り付けて実行

または、コマンドラインから:
```bash
mysql -u root -p < install.sql
```

### 3. 設定ファイルを編集

`config.php` を開いて以下を設定:

```php
define('DB_HOST', 'localhost');  // DBホスト
define('DB_USER', 'root');       // MySQLユーザー名
define('DB_PASS', '');           // MySQLパスワード
define('DB_NAME', 'enetoku');    // DB名

// ★ 使いたいAIプロバイダーを選択
define('AI_PROVIDER', 'anthropic'); // 'anthropic' または 'gemini'

// Claude (Anthropic) を使う場合
define('ANTHROPIC_API_KEY', 'sk-ant-xxxxxxxxxx');
// → APIキー取得先: https://console.anthropic.com/

// Gemini (Google) を使う場合
define('GEMINI_API_KEY', 'AIzaSyxxxxxxxxxx');
define('GEMINI_MODEL', 'gemini-2.0-flash'); // または 'gemini-1.5-pro'
// → APIキー取得先: https://aistudio.google.com/app/apikey
```

### 4. アクセス

ブラウザで開く:
```
http://localhost/enetoku/
```

### デフォルトアカウント

| ユーザー名 | パスワード | 権限 |
|-----------|-----------|------|
| `admin`   | `password` | 管理者 |
| `testuser`| `test1234` | 一般ユーザー |

> ⚠️ **本番環境ではすぐにパスワードを変更してください**

---

## 機能一覧

| 機能 | 説明 |
|------|------|
| 📊 ダッシュボード | 今月の光熱費サマリー・グラフ表示 |
| 📋 記録一覧 | 全記録の閲覧・編集・削除 |
| ➕ 記録追加 | 手動で光熱費を登録 |
| 📷 OCR読み取り | 請求書画像をAIで自動読み取り |
| 🤖 AI節約分析 | データを元にAIが節約アドバイス |
| 👑 管理者画面 | 全ユーザーのデータ一括管理 |

---

## セキュリティ注意事項

- `config.php` のAPIキーは外部に漏れないよう注意
- `uploads/` ディレクトリにPHPファイルが実行されないよう `.htaccess` を設置推奨:
  ```
  <FilesMatch "\.(php|php3|php4|php5|phtml)$">
    Deny from all
  </FilesMatch>
  ```
- 本番環境では `config.php` の `DEBUG_MODE` を `false` に設定
