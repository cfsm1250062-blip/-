// ========================================
// Enetoku - フロントエンド JavaScript
// ========================================

let currentUser = null;
let allRecords = [];
let monthlyChart = null;
let breakdownChart = null;
let ocrData = null;

// ──────────── 初期化 ──────────────────
document.addEventListener('DOMContentLoaded', async () => {
  // PHPでセッションチェック
  const res = await api('me');
  if (res.ok) {
    setUser(res.user);
    await initApp();
  } else {
    showLoginScreen();
  }

  // 年フィルターに現在年前後を追加
  const yearFilter = document.getElementById('filter-year');
  const now = new Date();
  for (let y = now.getFullYear(); y >= now.getFullYear() - 5; y--) {
    const opt = document.createElement('option');
    opt.value = y;
    opt.textContent = y + '年';
    yearFilter.appendChild(opt);
  }

  // フォームの年月をデフォルト設定
  document.getElementById('input-year').value = now.getFullYear();
  document.getElementById('input-month').value = now.getMonth() + 1;
});

async function initApp() {
  await loadRecords();
  renderDashboard();
  // admin-onlyメニューの表示/非表示を権限に応じて確実に制御
  document.querySelectorAll('.admin-only').forEach(el => {
    el.classList.toggle('hidden', !currentUser?.is_admin);
  });
}

// ──────────── API共通 ──────────────────
async function api(action, data = null, method = 'POST') {
  const url = 'api.php?action=' + action;
  const opts = {
    method,
    credentials: 'same-origin', // セッションCookieを必ず送信する
  };

  if (data) {
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(data);
  }

  const res = await fetch(url, opts);

  // 401はセッション未確立（未ログイン）を意味するので、
  // throwせずJSONをそのまま返し、呼び出し元でハンドリングする
  return res.json();
}

// ──────────── 認証 ──────────────────
async function doLogin() {
  const username = document.getElementById('login-username').value.trim();
  const password = document.getElementById('login-password').value;
  const errEl = document.getElementById('login-error');
  errEl.classList.add('hidden');

  if (!username || !password) {
    errEl.textContent = 'ユーザー名とパスワードを入力してください。';
    errEl.classList.remove('hidden');
    return;
  }

  const res = await api('login', { username, password });
  if (res.ok) {
    setUser(res.user);
    showMainApp();
    await initApp();
    showPage('dashboard', document.querySelector('[data-page=dashboard]'));
  } else {
    errEl.textContent = res.error;
    errEl.classList.remove('hidden');
  }
}

async function doRegister() {
  const errEl = document.getElementById('reg-error');
  errEl.classList.add('hidden');
  const data = {
    username: document.getElementById('reg-username').value.trim(),
    password: document.getElementById('reg-password').value,
    display_name: document.getElementById('reg-displayname').value.trim(),
    email: document.getElementById('reg-email').value.trim(),
  };
  const res = await api('register', data);
  if (res.ok) {
    alert('登録しました！ログインしてください。');
    showLogin();
  } else {
    errEl.textContent = res.error;
    errEl.classList.remove('hidden');
  }
}

async function doLogout() {
  await api('logout');
  // 状態を完全リセット
  currentUser = null;
  allRecords = [];
  if (monthlyChart) { monthlyChart.destroy(); monthlyChart = null; }
  if (breakdownChart) { breakdownChart.destroy(); breakdownChart = null; }
  // admin専用メニューを必ず非表示に戻す
  document.querySelectorAll('.admin-only').forEach(el => el.classList.add('hidden'));
  // admin画面が開いていたらdashboardに戻す
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('page-dashboard').classList.add('active');
  showLoginScreen();
}

function setUser(user) {
  currentUser = user;
  document.getElementById('user-display-name').textContent = user.display_name;
  document.getElementById('user-role-label').textContent = user.is_admin ? '管理者' : '一般ユーザー';
  document.getElementById('user-avatar-text').textContent = (user.display_name || user.username)[0].toUpperCase();
  // 権限に応じてadmin専用メニューを明示的に制御（非adminでは必ず隠す）
  document.querySelectorAll('.admin-only').forEach(el => {
    el.classList.toggle('hidden', !user.is_admin);
  });
}

function showLoginScreen() {
  document.getElementById('login-screen').classList.remove('hidden');
  document.getElementById('main-app').classList.add('hidden');
}

function showMainApp() {
  document.getElementById('login-screen').classList.add('hidden');
  document.getElementById('main-app').classList.remove('hidden');
}

function showLogin() {
  document.getElementById('login-form-wrap').classList.remove('hidden');
  document.getElementById('register-form-wrap').classList.add('hidden');
}

function showRegister() {
  document.getElementById('login-form-wrap').classList.add('hidden');
  document.getElementById('register-form-wrap').classList.remove('hidden');
}

// Enterキーでログイン
document.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    if (!document.getElementById('login-form-wrap').classList.contains('hidden')) doLogin();
    if (!document.getElementById('register-form-wrap').classList.contains('hidden')) doRegister();
  }
});

// ──────────── ナビゲーション ──────────────────
function showPage(pageId, linkEl) {
  // 非adminがadminページにアクセスしようとしたら弾く
  if (pageId === 'admin' && !currentUser?.is_admin) {
    return false;
  }

  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + pageId)?.classList.add('active');
  if (linkEl) linkEl.classList.add('active');

  // ページ固有の初期化
  if (pageId === 'records') loadRecordsTable();
  if (pageId === 'admin') loadAdminData();
  if (pageId === 'ai') loadPastAnalyses();

  // モバイルでサイドバーを閉じる
  if (window.innerWidth <= 900) {
    document.getElementById('sidebar').classList.remove('open');
  }
  return false;
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ──────────── データロード ──────────────────
async function loadRecords() {
  // adminは全ユーザーのデータをダッシュボードに集計表示する
  const params = currentUser?.is_admin ? { all: '1' } : {};
  const res = await apiGet('records', params);
  if (res.ok) allRecords = res.records;
}

// ──────────── ダッシュボード ──────────────────
function renderDashboard() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth() + 1;

  // adminは全ユーザー集計、一般は自分のデータ
  const adminLabel = currentUser?.is_admin ? '（全ユーザー合計）' : '';
  document.getElementById('dashboard-period').textContent = `${year}年${month}月の光熱費 ${adminLabel}`;

  // 今月の集計
  const thisMonth = allRecords.filter(r => +r.billing_year === year && +r.billing_month === month);
  const getSum = type => thisMonth.filter(r => r.utility_type === type).reduce((a, r) => a + parseFloat(r.billing_amount), 0);

  const water = getSum('water');
  const elec = getSum('electricity');
  const gas = getSum('gas');
  const total = thisMonth.reduce((a, r) => a + parseFloat(r.billing_amount), 0);

  document.getElementById('sum-water').textContent = water > 0 ? '¥' + water.toLocaleString() : '未記録';
  document.getElementById('sum-electricity').textContent = elec > 0 ? '¥' + elec.toLocaleString() : '未記録';
  document.getElementById('sum-gas').textContent = gas > 0 ? '¥' + gas.toLocaleString() : '未記録';
  document.getElementById('sum-total').textContent = total > 0 ? '¥' + total.toLocaleString() : '未記録';

  renderCharts();
  renderRecentRecords();
}

function renderCharts() {
  // 過去12ヶ月のデータを準備
  const months = [];
  const now = new Date();
  for (let i = 11; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    months.push({ year: d.getFullYear(), month: d.getMonth() + 1, label: `${d.getMonth() + 1}月` });
  }

  const getMonthTotal = (year, month, type) => {
    const recs = allRecords.filter(r => +r.billing_year === year && +r.billing_month === month && (!type || r.utility_type === type));
    return recs.reduce((a, r) => a + parseFloat(r.billing_amount), 0);
  };

  // 月別推移
  if (monthlyChart) monthlyChart.destroy();
  const ctx1 = document.getElementById('chart-monthly').getContext('2d');
  monthlyChart = new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: months.map(m => m.label),
      datasets: [
        {
          label: '水道', data: months.map(m => getMonthTotal(m.year, m.month, 'water')),
          backgroundColor: 'rgba(14,165,233,0.7)', borderRadius: 4,
        },
        {
          label: '電気', data: months.map(m => getMonthTotal(m.year, m.month, 'electricity')),
          backgroundColor: 'rgba(245,158,11,0.7)', borderRadius: 4,
        },
        {
          label: 'ガス', data: months.map(m => getMonthTotal(m.year, m.month, 'gas')),
          backgroundColor: 'rgba(249,115,22,0.7)', borderRadius: 4,
        },
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { font: { family: 'Noto Sans JP' } } } },
      scales: {
        x: { stacked: true, grid: { display: false } },
        y: { stacked: true, ticks: { callback: v => '¥' + v.toLocaleString() } }
      }
    }
  });

  // 内訳（ドーナツ）
  const now2 = new Date();
  const w = getMonthTotal(now2.getFullYear(), now2.getMonth() + 1, 'water');
  const e = getMonthTotal(now2.getFullYear(), now2.getMonth() + 1, 'electricity');
  const g = getMonthTotal(now2.getFullYear(), now2.getMonth() + 1, 'gas');
  const o = getMonthTotal(now2.getFullYear(), now2.getMonth() + 1, 'other');

  if (breakdownChart) breakdownChart.destroy();
  const ctx2 = document.getElementById('chart-breakdown').getContext('2d');
  breakdownChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
      labels: ['水道', '電気', 'ガス', 'その他'],
      datasets: [{
        data: [w, e, g, o],
        backgroundColor: ['#0EA5E9', '#F59E0B', '#F97316', '#94A3B8'],
        borderWidth: 0,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: { position: 'bottom', labels: { font: { family: 'Noto Sans JP' }, padding: 12 } },
        tooltip: { callbacks: { label: ctx => ctx.label + ': ¥' + ctx.raw.toLocaleString() } }
      }
    }
  });
}

function renderRecentRecords() {
  const recent = allRecords.slice(0, 5);
  const el = document.getElementById('recent-records');
  if (!recent.length) {
    el.innerHTML = '<p style="color:#94A3B8;text-align:center;padding:20px">まだ記録がありません</p>';
    return;
  }
  el.innerHTML = `
    <table class="data-table">
      <thead><tr><th>年月</th><th>種別</th><th>使用量</th><th>金額</th></tr></thead>
      <tbody>
        ${recent.map(r => `
          <tr>
            <td>${r.billing_year}年${r.billing_month}月</td>
            <td>${typeChip(r.utility_type)}</td>
            <td>${r.usage_amount ? r.usage_amount + r.usage_unit : '-'}</td>
            <td class="amount-val">¥${parseFloat(r.billing_amount).toLocaleString()}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

// ──────────── 記録一覧 ──────────────────
function loadRecordsTable() {
  const typeFilter = document.getElementById('filter-type').value;
  const yearFilter = document.getElementById('filter-year').value;

  let filtered = allRecords;
  if (typeFilter) filtered = filtered.filter(r => r.utility_type === typeFilter);
  if (yearFilter) filtered = filtered.filter(r => +r.billing_year === +yearFilter);

  const tbody = document.getElementById('records-tbody');
  const empty = document.getElementById('records-empty');

  if (!filtered.length) {
    tbody.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  tbody.innerHTML = filtered.map(r => `
    <tr>
      <td>${r.billing_year}年${r.billing_month}月</td>
      <td>${typeChip(r.utility_type)}</td>
      <td>${r.usage_amount ? r.usage_amount + ' ' + (r.usage_unit || '') : '-'}</td>
      <td class="amount-val">¥${parseFloat(r.billing_amount).toLocaleString()}</td>
      <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.memo || '-'}</td>
      <td>
        <button class="btn-edit" onclick="editRecord(${r.id})">編集</button>
        <button class="btn-danger" onclick="deleteRecord(${r.id})">削除</button>
      </td>
    </tr>
  `).join('');
}

function typeChip(type) {
  const map = { water: ['💧', '水道'], electricity: ['⚡', '電気'], gas: ['🔥', 'ガス'], other: ['📦', 'その他'] };
  const [icon, label] = map[type] || ['?', type];
  return `<span class="type-chip ${type}">${icon} ${label}</span>`;
}

// ──────────── 記録追加/編集 ──────────────────
function selectType(type, btn) {
  document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('input-type').value = type;

  // 使用量単位を自動切り替え
  const unitSel = document.getElementById('input-unit');
  if (type === 'electricity') unitSel.value = 'kWh';
  else if (type === 'water') unitSel.value = 'm3';
  else if (type === 'gas') unitSel.value = 'm3';
}

async function saveRecord() {
  const type = document.getElementById('input-type').value;
  const year = document.getElementById('input-year').value;
  const month = document.getElementById('input-month').value;
  const amount = document.getElementById('input-amount').value;

  if (!type) return alert('種別を選択してください。');
  if (!year || !month) return alert('年月を入力してください。');
  if (!amount) return alert('請求金額を入力してください。');

  const data = {
    utility_type: type,
    billing_year: parseInt(year),
    billing_month: parseInt(month),
    billing_amount: parseFloat(amount),
    usage_amount: document.getElementById('input-usage').value || null,
    usage_unit: document.getElementById('input-unit').value || null,
    billing_date: document.getElementById('input-billing-date').value || null,
    memo: document.getElementById('input-memo').value || null,
  };

  const editId = document.getElementById('edit-record-id').value;
  if (editId) data.id = editId;

  const res = await api('save_record', data);
  const msg = document.getElementById('save-msg');
  if (res.ok) {
    msg.textContent = '✓ 保存しました！';
    msg.classList.remove('hidden');
    setTimeout(() => msg.classList.add('hidden'), 3000);
    resetAddForm();
    await loadRecords();
    renderDashboard();
  } else {
    alert('エラー: ' + res.error);
  }
}

function resetAddForm() {
  document.getElementById('edit-record-id').value = '';
  document.getElementById('add-page-title').textContent = '記録を追加';
  document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('selected'));
  document.getElementById('input-type').value = '';
  const now = new Date();
  document.getElementById('input-year').value = now.getFullYear();
  document.getElementById('input-month').value = now.getMonth() + 1;
  document.getElementById('input-amount').value = '';
  document.getElementById('input-usage').value = '';
  document.getElementById('input-billing-date').value = '';
  document.getElementById('input-memo').value = '';
}

function editRecord(id) {
  const r = allRecords.find(r => +r.id === id);
  if (!r) return;
  showPage('add', document.querySelector('[data-page=add]'));
  document.getElementById('add-page-title').textContent = '記録を編集';
  document.getElementById('edit-record-id').value = r.id;
  document.getElementById('input-year').value = r.billing_year;
  document.getElementById('input-month').value = r.billing_month;
  document.getElementById('input-amount').value = r.billing_amount;
  document.getElementById('input-usage').value = r.usage_amount || '';
  document.getElementById('input-unit').value = r.usage_unit || 'm3';
  document.getElementById('input-billing-date').value = r.billing_date || '';
  document.getElementById('input-memo').value = r.memo || '';
  // タイプボタン
  document.querySelectorAll('.type-btn').forEach(b => {
    b.classList.toggle('selected', b.dataset.type === r.utility_type);
  });
  document.getElementById('input-type').value = r.utility_type;
}

async function deleteRecord(id) {
  if (!confirm('この記録を削除しますか？')) return;
  const res = await api('delete_record', { id });
  if (res.ok) {
    await loadRecords();
    loadRecordsTable();
    renderDashboard();
  } else {
    alert('エラー: ' + res.error);
  }
}

// ──────────── OCR ──────────────────
function handleFileSelect(input) {
  if (input.files[0]) processOcrFile(input.files[0]);
}

function handleDrop(e) {
  e.preventDefault();
  const file = e.dataTransfer.files[0];
  if (file) processOcrFile(file);
}

async function processOcrFile(file) {
  // プレビュー
  const preview = document.getElementById('ocr-preview');
  const previewImg = document.getElementById('ocr-preview-img');
  const reader = new FileReader();
  reader.onload = e => {
    previewImg.src = e.target.result;
    preview.classList.remove('hidden');
    document.getElementById('upload-area').style.display = 'none';
  };
  reader.readAsDataURL(file);

  // OCR実行
  const status = document.getElementById('ocr-status');
  status.className = 'ocr-status loading';
  status.textContent = '🔍 AIが画像を解析中です...';
  status.classList.remove('hidden');

  const formData = new FormData();
  formData.append('image', file);

  try {
    const res = await fetch('api.php?action=ocr', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.ok && data.data && !data.data.error) {
      ocrData = data.data;
      showOcrResult(ocrData);
      status.className = 'ocr-status success';
      status.textContent = '✓ 読み取り完了！';
    } else {
      status.className = 'ocr-status error';
      status.textContent = '⚠ ' + (data.data?.error || data.error || '読み取りに失敗しました。');
    }
  } catch (e) {
    status.className = 'ocr-status error';
    status.textContent = '⚠ 通信エラーが発生しました。';
  }
}

function showOcrResult(d) {
  const typeMap = { water: '💧 水道', electricity: '⚡ 電気', gas: '🔥 ガス', other: '📦 その他' };
  const typeName = typeMap[d.utility_type] || d.utility_type || '不明';
  document.getElementById('ocr-type-badge').textContent = typeName;
  document.getElementById('ocr-type-badge').className = 'type-badge type-chip ' + (d.utility_type || '');
  document.getElementById('ocr-date').textContent = d.billing_year && d.billing_month ? `${d.billing_year}年${d.billing_month}月` : '不明';
  document.getElementById('ocr-amount').textContent = d.billing_amount ? '¥' + parseFloat(d.billing_amount).toLocaleString() : '不明';
  document.getElementById('ocr-usage').textContent = d.usage_amount ? `${d.usage_amount} ${d.usage_unit || ''}` : '-';
  document.getElementById('ocr-raw-text').textContent = d.raw_text || '';
  document.getElementById('ocr-result-empty').classList.add('hidden');
  document.getElementById('ocr-result-data').classList.remove('hidden');
}

function useOcrData() {
  if (!ocrData) return;
  showPage('add', document.querySelector('[data-page=add]'));
  if (ocrData.utility_type) {
    document.getElementById('input-type').value = ocrData.utility_type;
    document.querySelectorAll('.type-btn').forEach(b => {
      b.classList.toggle('selected', b.dataset.type === ocrData.utility_type);
    });
  }
  if (ocrData.billing_year) document.getElementById('input-year').value = ocrData.billing_year;
  if (ocrData.billing_month) document.getElementById('input-month').value = ocrData.billing_month;
  if (ocrData.billing_amount) document.getElementById('input-amount').value = ocrData.billing_amount;
  if (ocrData.usage_amount) document.getElementById('input-usage').value = ocrData.usage_amount;
  if (ocrData.usage_unit) document.getElementById('input-unit').value = ocrData.usage_unit;
  if (ocrData.billing_date) document.getElementById('input-billing-date').value = ocrData.billing_date;
  document.getElementById('input-memo').value = 'OCR読み取りデータ';
}

function clearOcr() {
  document.getElementById('ocr-preview').classList.add('hidden');
  document.getElementById('upload-area').style.display = '';
  document.getElementById('ocr-status').classList.add('hidden');
  document.getElementById('ocr-result-empty').classList.remove('hidden');
  document.getElementById('ocr-result-data').classList.add('hidden');
  document.getElementById('ocr-file-input').value = '';
  ocrData = null;
}

// ──────────── AI分析 ──────────────────
async function runAiAnalysis() {
  const btn = document.getElementById('ai-analyze-btn');
  const content = document.getElementById('ai-result-content');

  btn.disabled = true;
  btn.innerHTML = '<span class="ai-spinner"></span> 分析中...';

  content.innerHTML = `
    <div class="ai-loading">
      <div class="ai-spinner"></div>
      <p>AIがあなたのデータを分析しています...</p>
    </div>
  `;

  try {
    const res = await api('ai_analysis', {});
    if (res.ok) {
      const providerLabel = res.provider === 'gemini'
        ? '<span style="background:#4285F4;color:white;padding:2px 10px;border-radius:20px;font-size:0.75rem;margin-left:8px">✦ Gemini</span>'
        : '<span style="background:#D97706;color:white;padding:2px 10px;border-radius:20px;font-size:0.75rem;margin-left:8px">✦ Claude</span>';
      content.innerHTML = `<div style="margin-bottom:10px;color:#94A3B8;font-size:0.82rem">AI分析結果 ${providerLabel}</div><div class="ai-content">${marked.parse(res.analysis)}</div>`;
      loadPastAnalyses();
    } else {
      content.innerHTML = `<div class="ai-placeholder">⚠ ${res.error}</div>`;
    }
  } catch (e) {
    content.innerHTML = `<div class="ai-placeholder">⚠ 通信エラーが発生しました。</div>`;
  }

  btn.disabled = false;
  btn.innerHTML = '<span class="btn-icon">✨</span> AI分析を再実行する';
}

async function loadPastAnalyses() {
  const res = await apiGet('past_analyses');
  if (!res.ok || !res.analyses.length) {
    document.getElementById('past-analyses').style.display = 'none';
    return;
  }
  document.getElementById('past-analyses').style.display = '';
  const list = document.getElementById('past-analyses-list');
  list.innerHTML = res.analyses.map(a => `
    <div class="past-analysis-item" onclick="this.querySelector('.past-analysis-full').style.display = this.querySelector('.past-analysis-full').style.display === 'none' ? 'block' : 'none'">
      <div class="past-analysis-date">${new Date(a.created_at).toLocaleString('ja-JP')}</div>
      <div class="past-analysis-preview">${a.analysis_text.substring(0, 100)}...</div>
      <div class="past-analysis-full ai-content" style="display:none;margin-top:12px;border-top:1px solid #F1F5F9;padding-top:12px">
        ${marked.parse(a.analysis_text)}
      </div>
    </div>
  `).join('');
}

// ──────────── 管理者画面 ──────────────────
async function loadAdminData() {
  if (!currentUser?.is_admin) return;
  const [usersRes, statsRes, allRecordsRes] = await Promise.all([
    apiGet('admin_users'),
    apiGet('admin_stats'),
    apiGet('records', { all: '1' }),
  ]);

  // ユーザー一覧
  if (usersRes.ok) {
    const list = document.getElementById('admin-users-list');
    list.innerHTML = usersRes.users.map(u => `
      <div class="admin-user-card">
        <div class="admin-user-avatar">${(u.display_name || u.username)[0].toUpperCase()}</div>
        <div class="admin-user-info">
          <div class="admin-user-name">${u.display_name} <span style="color:#94A3B8;font-weight:400">@${u.username}</span></div>
          <div class="admin-user-meta">
            登録: ${new Date(u.created_at).toLocaleDateString('ja-JP')} / 
            記録数: ${u.record_count}件 /
            今月: ¥${parseFloat(u.this_month_total || 0).toLocaleString()}
          </div>
        </div>
        <span class="admin-badge ${u.is_admin ? 'admin' : 'user'}">${u.is_admin ? '👑 管理者' : '👤 ユーザー'}</span>
      </div>
    `).join('');
  }

  // 全記録
  if (allRecordsRes.ok) {
    const tbody = document.getElementById('admin-records-tbody');
    tbody.innerHTML = allRecordsRes.records.map(r => `
      <tr>
        <td>${r.display_name} <small style="color:#94A3B8">@${r.username}</small></td>
        <td>${r.billing_year}年${r.billing_month}月</td>
        <td>${typeChip(r.utility_type)}</td>
        <td>${r.usage_amount ? r.usage_amount + ' ' + (r.usage_unit || '') : '-'}</td>
        <td class="amount-val">¥${parseFloat(r.billing_amount).toLocaleString()}</td>
        <td><button class="btn-danger" onclick="deleteRecord(${r.id})">削除</button></td>
      </tr>
    `).join('');
  }

  // 統計
  if (statsRes.ok) {
    const statsEl = document.getElementById('admin-stats-content');
    const totalAll = statsRes.by_user.reduce((a, u) => a + parseFloat(u.total || 0), 0);
    const totalRecords = statsRes.by_user.reduce((a, u) => a + parseInt(u.records || 0), 0);
    statsEl.innerHTML = `
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-number">${usersRes.users?.length || 0}</div>
          <div class="stat-label">総ユーザー数</div>
        </div>
        <div class="stat-card">
          <div class="stat-number">${totalRecords}</div>
          <div class="stat-label">総記録数</div>
        </div>
        <div class="stat-card">
          <div class="stat-number">¥${Math.round(totalAll).toLocaleString()}</div>
          <div class="stat-label">累計光熱費合計</div>
        </div>
      </div>
      <h4 style="margin:16px 0 10px;color:#334155">ユーザー別光熱費合計</h4>
      <table class="data-table">
        <thead><tr><th>ユーザー</th><th>合計金額</th><th>記録数</th></tr></thead>
        <tbody>
          ${statsRes.by_user.map(u => `
            <tr>
              <td>${u.display_name} <small style="color:#94A3B8">@${u.username}</small></td>
              <td class="amount-val">¥${parseFloat(u.total || 0).toLocaleString()}</td>
              <td>${u.records}件</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  }
}

function showAdminTab(tabId, btn) {
  document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.admin-tab-content').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('admin-tab-' + tabId).classList.add('active');
}