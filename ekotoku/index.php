<?php
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enetoku - 水道光熱費管理</title>
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700;900&family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div id="app">

  <!-- ========== ログイン画面 ========== -->
  <div id="login-screen" class="<?= $currentUser ? 'hidden' : '' ?>">
    <div class="login-bg">
      <div class="water-blob blob1"></div>
      <div class="water-blob blob2"></div>
      <div class="water-blob blob3"></div>
    </div>
    <div class="login-card">
      <div class="login-logo">
        <img src="Enetoku_logo.png" alt="Enetoku">
      </div>
      <div id="login-form-wrap">
        <h2 class="login-title">ログイン</h2>
        <div class="form-group">
          <label>ユーザー名</label>
          <input type="text" id="login-username" placeholder="username" autocomplete="username">
        </div>
        <div class="form-group">
          <label>パスワード</label>
          <input type="password" id="login-password" placeholder="••••••••" autocomplete="current-password">
        </div>
        <div id="login-error" class="error-msg hidden"></div>
        <button class="btn btn-primary btn-full" onclick="doLogin()">ログイン</button>
        <div class="login-switch">
          アカウントをお持ちでない方は <a href="#" onclick="showRegister()">新規登録</a>
        </div>
      </div>
      <div id="register-form-wrap" class="hidden">
        <h2 class="login-title">新規登録</h2>
        <div class="form-group">
          <label>ユーザー名 <span class="req">*</span></label>
          <input type="text" id="reg-username" placeholder="例: yamada_taro">
        </div>
        <div class="form-group">
          <label>表示名</label>
          <input type="text" id="reg-displayname" placeholder="例: 山田太郎">
        </div>
        <div class="form-group">
          <label>メールアドレス</label>
          <input type="email" id="reg-email" placeholder="例: taro@example.com">
        </div>
        <div class="form-group">
          <label>パスワード <span class="req">*</span> (6文字以上)</label>
          <input type="password" id="reg-password" placeholder="••••••••">
        </div>
        <div id="reg-error" class="error-msg hidden"></div>
        <button class="btn btn-primary btn-full" onclick="doRegister()">登録する</button>
        <div class="login-switch">
          <a href="#" onclick="showLogin()">← ログインに戻る</a>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== メインアプリ ========== -->
  <div id="main-app" class="<?= $currentUser ? '' : 'hidden' ?>">
    
    <!-- サイドバー -->
    <nav id="sidebar">
      <div class="sidebar-logo">
        <img src="Enetoku_logo.png" alt="Enetoku">
      </div>
      <div class="nav-menu">
        <a href="#" class="nav-item active" data-page="dashboard" onclick="showPage('dashboard', this)">
          <span class="nav-icon">📊</span><span>ダッシュボード</span>
        </a>
        <a href="#" class="nav-item" data-page="records" onclick="showPage('records', this)">
          <span class="nav-icon">📋</span><span>記録一覧</span>
        </a>
        <a href="#" class="nav-item" data-page="add" onclick="showPage('add', this)">
          <span class="nav-icon">➕</span><span>記録追加</span>
        </a>
        <a href="#" class="nav-item" data-page="ocr" onclick="showPage('ocr', this)">
          <span class="nav-icon">📷</span><span>OCR読み取り</span>
        </a>
        <a href="#" class="nav-item" data-page="ai" onclick="showPage('ai', this)">
          <span class="nav-icon">🤖</span><span>AI節約分析</span>
        </a>
        <a href="#" class="nav-item admin-only hidden" data-page="admin" onclick="showPage('admin', this)">
          <span class="nav-icon">👑</span><span>管理者画面</span>
        </a>
      </div>
      <div class="sidebar-user">
        <div class="user-avatar" id="user-avatar-text">U</div>
        <div class="user-info">
          <div class="user-name" id="user-display-name">ユーザー</div>
          <div class="user-role" id="user-role-label">一般ユーザー</div>
        </div>
        <button class="logout-btn" onclick="doLogout()" title="ログアウト">⏻</button>
      </div>
    </nav>

    <!-- ハンバーガー (モバイル) -->
    <button id="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- メインコンテンツ -->
    <main id="content">

      <!-- ダッシュボード -->
      <div id="page-dashboard" class="page active">
        <div class="page-header">
          <h1>ダッシュボード</h1>
          <p class="page-subtitle" id="dashboard-period">今月の光熱費</p>
        </div>
        <div class="summary-cards" id="summary-cards">
          <div class="summary-card water">
            <div class="card-icon">💧</div>
            <div class="card-info">
              <div class="card-label">水道代</div>
              <div class="card-amount" id="sum-water">¥---</div>
            </div>
          </div>
          <div class="summary-card electricity">
            <div class="card-icon">⚡</div>
            <div class="card-info">
              <div class="card-label">電気代</div>
              <div class="card-amount" id="sum-electricity">¥---</div>
            </div>
          </div>
          <div class="summary-card gas">
            <div class="card-icon">🔥</div>
            <div class="card-info">
              <div class="card-label">ガス代</div>
              <div class="card-amount" id="sum-gas">¥---</div>
            </div>
          </div>
          <div class="summary-card total">
            <div class="card-icon">💴</div>
            <div class="card-info">
              <div class="card-label">合計</div>
              <div class="card-amount" id="sum-total">¥---</div>
            </div>
          </div>
        </div>

        <!-- グラフエリア -->
        <div class="charts-grid">
          <div class="chart-card">
            <h3>月別推移（過去12ヶ月）</h3>
            <div class="chart-wrap">
              <canvas id="chart-monthly"></canvas>
            </div>
          </div>
          <div class="chart-card">
            <h3>光熱費の内訳</h3>
            <div class="chart-wrap chart-wrap-sm">
              <canvas id="chart-breakdown"></canvas>
            </div>
          </div>
        </div>

        <!-- 直近の記録 -->
        <div class="recent-section">
          <div class="section-header">
            <h3>直近の記録</h3>
            <a href="#" onclick="showPage('records', document.querySelector('[data-page=records]'))">すべて見る →</a>
          </div>
          <div id="recent-records"></div>
        </div>
      </div>

      <!-- 記録一覧 -->
      <div id="page-records" class="page">
        <div class="page-header">
          <h1>記録一覧</h1>
          <div class="header-actions">
            <select id="filter-type" onchange="loadRecords()" class="filter-select">
              <option value="">すべての種別</option>
              <option value="water">水道</option>
              <option value="electricity">電気</option>
              <option value="gas">ガス</option>
              <option value="other">その他</option>
            </select>
            <select id="filter-year" onchange="loadRecords()" class="filter-select">
              <option value="">すべての年</option>
            </select>
          </div>
        </div>
        <div id="records-table-wrap">
          <table class="data-table" id="records-table">
            <thead>
              <tr>
                <th>年月</th>
                <th>種別</th>
                <th>使用量</th>
                <th>請求金額</th>
                <th>メモ</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody id="records-tbody"></tbody>
          </table>
          <div id="records-empty" class="empty-state hidden">
            <div class="empty-icon">📭</div>
            <p>まだ記録がありません。<br>「記録追加」から入力してください。</p>
          </div>
        </div>
      </div>

      <!-- 記録追加/編集 -->
      <div id="page-add" class="page">
        <div class="page-header">
          <h1 id="add-page-title">記録を追加</h1>
        </div>
        <div class="form-card">
          <input type="hidden" id="edit-record-id">
          <div class="form-grid">
            <div class="form-group">
              <label>光熱費の種別 <span class="req">*</span></label>
              <div class="type-selector" id="type-selector">
                <button class="type-btn" data-type="water" onclick="selectType('water', this)">💧 水道</button>
                <button class="type-btn" data-type="electricity" onclick="selectType('electricity', this)">⚡ 電気</button>
                <button class="type-btn" data-type="gas" onclick="selectType('gas', this)">🔥 ガス</button>
                <button class="type-btn" data-type="other" onclick="selectType('other', this)">📦 その他</button>
              </div>
              <input type="hidden" id="input-type">
            </div>
            <div class="form-group">
              <label>請求年月 <span class="req">*</span></label>
              <div class="date-inputs">
                <input type="number" id="input-year" placeholder="2024" min="2000" max="2099" class="input-year">
                <span>年</span>
                <input type="number" id="input-month" placeholder="1" min="1" max="12" class="input-month">
                <span>月</span>
              </div>
            </div>
            <div class="form-group">
              <label>請求金額（円） <span class="req">*</span></label>
              <input type="number" id="input-amount" placeholder="例: 3500" min="0" step="1">
            </div>
            <div class="form-group">
              <label>使用量</label>
              <div class="usage-inputs">
                <input type="number" id="input-usage" placeholder="例: 10.5" min="0" step="0.01" class="input-usage">
                <select id="input-unit" class="input-unit">
                  <option value="m3">m³ (水道/ガス)</option>
                  <option value="kWh">kWh (電気)</option>
                  <option value="MJ">MJ (ガス)</option>
                  <option value="L">L</option>
                  <option value="">単位なし</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>支払期限</label>
              <input type="date" id="input-billing-date">
            </div>
            <div class="form-group form-full">
              <label>メモ</label>
              <textarea id="input-memo" placeholder="節約した施策や気になることなど..." rows="3"></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-outline" onclick="resetAddForm()">リセット</button>
            <button class="btn btn-primary" onclick="saveRecord()">💾 保存する</button>
          </div>
          <div id="save-msg" class="success-msg hidden"></div>
        </div>
      </div>

      <!-- OCR読み取り -->
      <div id="page-ocr" class="page">
        <div class="page-header">
          <h1>OCR読み取り</h1>
          <p class="page-subtitle">請求書・明細書の画像をアップロードして自動読み取り</p>
        </div>
        <div class="ocr-layout">
          <div class="ocr-upload-card">
            <div class="upload-area" id="upload-area" 
                 ondragover="event.preventDefault()" 
                 ondrop="handleDrop(event)">
              <div class="upload-icon">📷</div>
              <p>画像をドラッグ＆ドロップ<br>または</p>
              <label class="btn btn-outline" for="ocr-file-input">ファイルを選択</label>
              <input type="file" id="ocr-file-input" accept="image/*" onchange="handleFileSelect(this)" class="hidden">
              <p class="upload-hint">JPEG, PNG, GIF, WebP対応 / 最大10MB</p>
            </div>
            <div id="ocr-preview" class="hidden">
              <img id="ocr-preview-img" alt="プレビュー">
              <button class="btn btn-sm btn-outline" onclick="clearOcr()">✕ 取り消し</button>
            </div>
            <div id="ocr-status" class="ocr-status hidden"></div>
          </div>

          <div class="ocr-result-card" id="ocr-result-card">
            <h3>読み取り結果</h3>
            <div id="ocr-result-empty" class="empty-hint">
              画像をアップロードすると<br>ここに結果が表示されます
            </div>
            <div id="ocr-result-data" class="hidden">
              <div class="ocr-field">
                <label>種別</label>
                <div id="ocr-type-badge" class="type-badge"></div>
              </div>
              <div class="ocr-field">
                <label>請求年月</label>
                <div id="ocr-date"></div>
              </div>
              <div class="ocr-field">
                <label>請求金額</label>
                <div id="ocr-amount" class="ocr-amount-val"></div>
              </div>
              <div class="ocr-field">
                <label>使用量</label>
                <div id="ocr-usage"></div>
              </div>
              <div class="ocr-raw">
                <label>読み取りテキスト</label>
                <div id="ocr-raw-text"></div>
              </div>
              <div class="ocr-actions">
                <button class="btn btn-primary btn-full" onclick="useOcrData()">
                  ✓ この内容で記録する
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- AI節約分析 -->
      <div id="page-ai" class="page">
        <div class="page-header">
          <h1>AI節約分析</h1>
          <p class="page-subtitle">あなたのデータをAIが分析して節約術を提案します</p>
        </div>
        <div class="ai-layout">
          <div class="ai-trigger-card">
            <div class="ai-icon">🤖</div>
            <h3>AIに分析してもらう</h3>
            <p>過去の光熱費データをもとに、あなただけの節約アドバイスをAIが提案します。</p>
            <button class="btn btn-primary btn-ai" id="ai-analyze-btn" onclick="runAiAnalysis()">
              <span class="btn-icon">✨</span> AI分析を開始する
            </button>
          </div>
          <div class="ai-result-card" id="ai-result-card">
            <div id="ai-result-content" class="ai-placeholder">
              <p>ボタンを押して分析を開始してください</p>
            </div>
          </div>
        </div>
        <div id="past-analyses" class="past-analyses">
          <h3>過去の分析履歴</h3>
          <div id="past-analyses-list"></div>
        </div>
      </div>

      <!-- 管理者画面 -->
      <div id="page-admin" class="page">
        <div class="page-header">
          <h1>👑 管理者画面</h1>
          <p class="page-subtitle">全ユーザーのデータを管理できます</p>
        </div>
        
        <div class="admin-tabs">
          <button class="admin-tab active" onclick="showAdminTab('users', this)">ユーザー一覧</button>
          <button class="admin-tab" onclick="showAdminTab('allrecords', this)">全記録</button>
          <button class="admin-tab" onclick="showAdminTab('stats', this)">統計</button>
        </div>

        <div id="admin-tab-users" class="admin-tab-content active">
          <div id="admin-users-list"></div>
        </div>
        <div id="admin-tab-allrecords" class="admin-tab-content">
          <table class="data-table">
            <thead>
              <tr><th>ユーザー</th><th>年月</th><th>種別</th><th>使用量</th><th>金額</th><th>操作</th></tr>
            </thead>
            <tbody id="admin-records-tbody"></tbody>
          </table>
        </div>
        <div id="admin-tab-stats" class="admin-tab-content">
          <div id="admin-stats-content"></div>
        </div>
      </div>

    </main>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<!-- marked.js (Markdown→HTML) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
<script src="app.js"></script>

<!-- ========================================
     BUG FIX: ログアウト確認ダイアログ＋完全再読み込み
     YES押下時に location.reload() でページを完全リセットし
     DOM上の残留データ・管理者権限の痕跡をゼロにする。
     ======================================== -->

<!-- 確認ダイアログ (モーダル) -->
<div id="logout-overlay" style="
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,0.55); backdrop-filter:blur(3px);
  align-items:center; justify-content:center;">
  <div style="
    background:#fff; border-radius:16px; padding:36px 40px;
    box-shadow:0 8px 40px rgba(0,0,0,0.22); text-align:center;
    max-width:340px; width:90%; animation:_fadeUp .18s ease;">
    <div style="font-size:2.2rem; margin-bottom:12px;">⏻</div>
    <h3 style="margin:0 0 8px; font-size:1.15rem; color:#1a2a4a;">ログアウトしますか？</h3>
    <p style="margin:0 0 28px; font-size:0.9rem; color:#666;">
      入力中のデータは破棄されます。
    </p>
    <div style="display:flex; gap:12px; justify-content:center;">
      <button id="logout-cancel-btn" style="
        flex:1; padding:10px 0; border:2px solid #d0d8e8; border-radius:8px;
        background:#fff; color:#444; font-size:0.95rem; cursor:pointer;
        font-weight:600; transition:background .15s;">
        キャンセル
      </button>
      <button id="logout-confirm-btn" style="
        flex:1; padding:10px 0; border:none; border-radius:8px;
        background:linear-gradient(135deg,#e05555,#c0392b);
        color:#fff; font-size:0.95rem; cursor:pointer;
        font-weight:700; transition:opacity .15s;">
        ログアウト
      </button>
    </div>
  </div>
</div>
<style>
@keyframes _fadeUp {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0);    }
}
</style>

<script>
(function () {
  var overlay     = document.getElementById('logout-overlay');
  var cancelBtn   = document.getElementById('logout-cancel-btn');
  var confirmBtn  = document.getElementById('logout-confirm-btn');

  /* ── ダイアログを開く ── */
  function openLogoutDialog() {
    overlay.style.display = 'flex';
    confirmBtn.focus();
  }

  /* ── ダイアログを閉じる ── */
  function closeLogoutDialog() {
    overlay.style.display = 'none';
  }

  /* ── YES: APIログアウト → location.reload() ── */
  async function confirmLogout() {
    confirmBtn.disabled = true;
    confirmBtn.textContent = '処理中…';
    try {
      await fetch('api.php?action=logout', { method: 'POST' });
    } catch (e) {
      // 通信失敗でもリロードは必ず実行
    } finally {
      // ★ ページを完全再読み込み → DOM・JS変数・管理者痕跡をすべて消去
      location.replace(location.pathname);
    }
  }

  /* ── app.js の doLogout() をラップして確認ダイアログに差し替え ── */
  function wrapDoLogout() {
    // 元の doLogout があれば退避（使わないが念のため）
    window._originalDoLogout = window.doLogout;
    window.doLogout = function () {
      openLogoutDialog();
    };
  }

  /* ── イベント登録 ── */
  cancelBtn.addEventListener('click', closeLogoutDialog);
  confirmBtn.addEventListener('click', confirmLogout);

  // オーバーレイ背景クリックでキャンセル
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeLogoutDialog();
  });

  // Escape キーでキャンセル
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.style.display === 'flex') {
      closeLogoutDialog();
    }
  });

  // app.js 読み込み後にラップ適用
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wrapDoLogout);
  } else {
    wrapDoLogout();
  }
})();
</script>
</body>
</html>
