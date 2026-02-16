<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ekotoku</title>

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

  <!-- OCR: Tesseract.js -->
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

  <style>
    body { font: 14px sans-serif; }
    #previewWrap { display: grid; gap: 12px; grid-template-columns: 1fr; }
    @media (min-width: 992px) { #previewWrap { grid-template-columns: 1fr 1fr; } }
    canvas { width: 100%; height: auto; background: #111; border-radius: 8px; }
    /* 切り抜き操作でページスクロールが暴れないように */
    #srcCanvas { touch-action: none; }
    video { width: 100%; border-radius: 8px; background: #111; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .small { font-size: 12px; color: #666; }
    table td, table th { vertical-align: middle !important; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0"><img src="Enetoku_logo.png" alt="enetoku" width="50" height="50">enetoku</h3>
      <div class="small">ログインユーザー: <?php echo htmlspecialchars($_SESSION["name"]); ?></div>
    </div>
    <a href="logout.php" class="btn btn-outline-danger">ログアウト</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title mb-3">1) 画像入力</h5>

      <div class="row">
        <div class="col-lg-6 mb-3">
          <label class="font-weight-bold">画像を選択</label>
          <input id="fileInput" class="form-control" type="file" accept="image/*" />
          <div class="small mt-2">スマホは「ファイル選択」からカメラを選べることが多いです。</div>
        </div>

        <div class="col-lg-6 mb-3">
          <label class="font-weight-bold">ブラウザでカメラ撮影</label>
          <div class="d-flex flex-wrap" style="gap:8px;">
            <button id="startCam" class="btn btn-sm btn-secondary" type="button">カメラ開始</button>
            <button id="capture" class="btn btn-sm btn-primary" type="button" disabled>撮影</button>
            <button id="stopCam" class="btn btn-sm btn-outline-secondary" type="button" disabled>停止</button>
          </div>
          <div class="small mt-2">※HTTPS/localhost でないと動かない環境があります。</div>
        </div>
      </div>

      <div id="previewWrap" class="mt-3">
        <div>
          <div class="font-weight-bold mb-2">プレビュー（元画像）</div>
          <video id="video" playsinline muted></video>
          <canvas id="srcCanvas" class="mt-2"></canvas>

          <div class="mt-2 d-flex flex-wrap" style="gap:8px;">
            <button id="cropToggle" class="btn btn-sm btn-outline-primary" type="button" disabled>切り抜き：OFF</button>
            <button id="cropReset" class="btn btn-sm btn-outline-secondary" type="button" disabled>切り抜き解除</button>
          </div>
          <div id="cropInfo" class="small mt-1">液晶ディスプレイ部分をドラッグで囲うと、その範囲だけで前処理→OCRします。</div>
        </div>
        <div>
          <div class="font-weight-bold mb-2">前処理後（OCRに使う画像）</div>
          <canvas id="procCanvas"></canvas>
          <div class="small mt-2">二値化＋（必要なら）切り抜き。まずは液晶表示だけを狙うと精度が上がりやすいです。</div>
        </div>
      </div>

      <hr class="my-4" />

      <h5 class="card-title mb-3">2) OCR → 確定</h5>
      <div class="row">
        <div class="col-lg-4 mb-2">
          <button id="runOcr" class="btn btn-success btn-block" type="button" disabled>OCR実行</button>
        </div>
        <div class="col-lg-8 mb-2">
          <div class="small">OCR結果（編集可）</div>
          <input id="readingInput" class="form-control mono" placeholder="例: 012345" inputmode="numeric" />
        </div>
      </div>

      <div class="d-flex flex-wrap mt-2" style="gap:8px;">
        <button id="save" class="btn btn-primary" type="button">保存（サーバ）</button>
        <button id="clearAll" class="btn btn-outline-danger" type="button">全削除</button>
        <button id="exportCsv" class="btn btn-outline-secondary" type="button">CSV出力</button>
      </div>

      <div id="status" class="mt-3 small"></div>
      <div id="progress" class="small mono"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title mb-3">履歴</h5>
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>日時</th>
              <th class="text-right">指示数</th>
              <th class="text-right">差分</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="historyBody"></tbody>
        </table>
      </div>
      <div class="small">※保存先はサーバです（同じアカウントなら別端末でも共有されます）。</div>
    </div>
  </div>
</div>
<script>
const CSRF_TOKEN = "<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>";
</script>
<script>
(() => {

// --- サーバ保存API ---
async function api(action, body = null, method = "POST") {
  const url = `api_readings.php?action=${encodeURIComponent(action)}`;
  const opt = {
    method,
    headers: {
      "X-CSRF-Token": CSRF_TOKEN,
      ...(body ? { "Content-Type": "application/json" } : {})
    },
    body: body ? JSON.stringify(body) : null
  };
  const res = await fetch(url, opt);
  if (!res.ok) throw new Error(`API ${action} failed: ${res.status}`);
  // export_csv はここでは扱わない（CSVは blob で受ける）
  return await res.json();
}

async function loadHistory() {
  const res = await api("list", null, "POST");
  return res.items || [];
}

async function addHistory(reading) {
  await api("add", { reading });
}

async function deleteHistory(timestamp) {
  await api("delete", { timestamp });
}

async function clearAll() {
  await api("clear", {});
}



  const fileInput = document.getElementById("fileInput");
  const startCamBtn = document.getElementById("startCam");
  const captureBtn = document.getElementById("capture");
  const stopCamBtn = document.getElementById("stopCam");

  const video = document.getElementById("video");
  const srcCanvas = document.getElementById("srcCanvas");
  const procCanvas = document.getElementById("procCanvas");
  const runOcrBtn = document.getElementById("runOcr");

  const cropToggleBtn = document.getElementById("cropToggle");
  const cropResetBtn = document.getElementById("cropReset");
  const cropInfoEl = document.getElementById("cropInfo");

  const readingInput = document.getElementById("readingInput");
  const saveBtn = document.getElementById("save");
  const clearAllBtn = document.getElementById("clearAll");
  const exportCsvBtn = document.getElementById("exportCsv");

  const statusEl = document.getElementById("status");
  const progressEl = document.getElementById("progress");
  const historyBody = document.getElementById("historyBody");

  let stream = null;
  let hasImage = false;

  // 元画像保持用（切り抜き枠描画で劣化しないように別キャンバスに保持）
  const fullCanvas = document.createElement("canvas");
  const fullCtx = fullCanvas.getContext("2d");

  // 切り抜き
  let cropEnabled = false;
  let cropRect = null; // {x,y,w,h} in fullCanvas coords
  let isDragging = false;
  let dragStart = null;

  function setStatus(msg) { statusEl.textContent = msg || ""; }
  function setProgress(msg) { progressEl.textContent = msg || ""; }

  function fmtDate(ts) {
    const d = new Date(ts);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

async function renderHistory() {
  const items = await loadHistory();
  items.sort((a, b) => a.timestamp - b.timestamp);

  historyBody.innerHTML = "";

  for (let i = 0; i < items.length; i++) {
    const cur = items[i];
    const prev = items[i - 1];
    const diff = prev ? (Number(cur.reading) - Number(prev.reading)) : null;

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="mono">${fmtDate(cur.timestamp)}</td>
      <td class="text-right mono">${cur.reading}</td>
      <td class="text-right mono">${diff === null ? "-" : diff}</td>
      <td class="text-right">
        <button class="btn btn-sm btn-outline-danger" data-del="${cur.timestamp}">削除</button>
      </td>
    `;
    historyBody.appendChild(tr);
  }

  // 削除ボタン処理（サーバAPI版）
  historyBody.querySelectorAll("button[data-del]").forEach(btn => {
    btn.addEventListener("click", async () => {
      const ts = Number(btn.getAttribute("data-del"));
      try {
        await api("delete", { timestamp: ts });
        await renderHistory();
      } catch (e) {
        console.error(e);
      }
    });
  });
}


  function drawToCanvasFromImage(img) {
    // サイズ: 長辺を 1200px 程度に揃えてOCRを安定させる
    const maxSide = 1200;
    const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
    const w = Math.round(img.width * scale);
    const h = Math.round(img.height * scale);

    // 元画像は fullCanvas に保持
    fullCanvas.width = w;
    fullCanvas.height = h;
    fullCtx.drawImage(img, 0, 0, w, h);

    // 新規画像では切り抜きをリセット
    cropRect = null;
    cropEnabled = false;
    cropToggleBtn.textContent = "切り抜き：OFF";
    cropToggleBtn.classList.add("btn-outline-primary");
    cropToggleBtn.classList.remove("btn-primary");

    // 表示用
    srcCanvas.width = w;
    srcCanvas.height = h;
    drawSrcWithOverlay();

    video.style.display = "none";
    hasImage = true;
    runOcrBtn.disabled = false;
    cropToggleBtn.disabled = false;
    cropResetBtn.disabled = false;
    preprocess();
  }

  function drawSrcWithOverlay() {
    const ctx = srcCanvas.getContext("2d");
    ctx.clearRect(0, 0, srcCanvas.width, srcCanvas.height);
    ctx.drawImage(fullCanvas, 0, 0);

    if (cropRect) {
      ctx.save();
      ctx.lineWidth = Math.max(2, Math.round(Math.min(srcCanvas.width, srcCanvas.height) / 250));
      ctx.strokeStyle = "#00d4ff";
      ctx.setLineDash([8, 6]);
      ctx.strokeRect(cropRect.x + 0.5, cropRect.y + 0.5, cropRect.w, cropRect.h);
      ctx.fillStyle = "rgba(0,0,0,0.25)";
      // 外側を薄く暗くする
      ctx.beginPath();
      ctx.rect(0, 0, srcCanvas.width, srcCanvas.height);
      ctx.rect(cropRect.x, cropRect.y, cropRect.w, cropRect.h);
      ctx.fill("evenodd");
      ctx.restore();
    }
  }

  function preprocess() {
    // 簡易前処理: グレースケール → 二値化（固定しきい値）
    const pctx = procCanvas.getContext("2d");

    // 切り抜きが有効ならその範囲だけを前処理
    const useCrop = cropEnabled && cropRect && cropRect.w >= 10 && cropRect.h >= 10;
    const sx = useCrop ? Math.round(cropRect.x) : 0;
    const sy = useCrop ? Math.round(cropRect.y) : 0;
    const sw = useCrop ? Math.round(cropRect.w) : fullCanvas.width;
    const sh = useCrop ? Math.round(cropRect.h) : fullCanvas.height;

    procCanvas.width = sw;
    procCanvas.height = sh;

    const imgData = fullCtx.getImageData(sx, sy, sw, sh);
    const d = imgData.data;

    // グレースケール + コントラスト気持ち強め
    for (let i = 0; i < d.length; i += 4) {
      const r = d[i], g = d[i+1], b = d[i+2];
      let y = (0.299*r + 0.587*g + 0.114*b);
      // コントラスト（簡易）
      y = (y - 128) * 1.3 + 128;
      y = Math.max(0, Math.min(255, y));
      d[i] = d[i+1] = d[i+2] = y;
    }

    // 二値化（固定しきい値。必要なら後で自動化する）
    const thr = 160;
    for (let i = 0; i < d.length; i += 4) {
      const v = d[i] > thr ? 255 : 0;
      d[i] = d[i+1] = d[i+2] = v;
    }

    pctx.putImageData(imgData, 0, 0);

    // 情報表示
    if (useCrop) {
      cropInfoEl.textContent = `切り抜き中：x=${sx}, y=${sy}, w=${sw}, h=${sh}（この範囲だけ前処理→OCR）`;
    } else {
      cropInfoEl.textContent = "液晶ディスプレイ部分をドラッグで囲うと、その範囲だけで前処理→OCRします。";
    }
  }

  function getCanvasPointFromEvent(ev) {
    const rect = srcCanvas.getBoundingClientRect();
    // CSS表示サイズとキャンバス実サイズのズレを補正
    const scaleX = srcCanvas.width / rect.width;
    const scaleY = srcCanvas.height / rect.height;
    const x = (ev.clientX - rect.left) * scaleX;
    const y = (ev.clientY - rect.top) * scaleY;
    return { x, y };
  }

  function normalizeRect(a, b) {
    const x1 = Math.min(a.x, b.x);
    const y1 = Math.min(a.y, b.y);
    const x2 = Math.max(a.x, b.x);
    const y2 = Math.max(a.y, b.y);
    const x = Math.max(0, Math.min(fullCanvas.width, x1));
    const y = Math.max(0, Math.min(fullCanvas.height, y1));
    const w = Math.max(0, Math.min(fullCanvas.width - x, x2 - x1));
    const h = Math.max(0, Math.min(fullCanvas.height - y, y2 - y1));
    return { x, y, w, h };
  }

  // 切り抜きUI
  cropToggleBtn.addEventListener("click", () => {
    cropEnabled = !cropEnabled;
    cropToggleBtn.textContent = cropEnabled ? "切り抜き：ON" : "切り抜き：OFF";
    cropToggleBtn.classList.toggle("btn-outline-primary", !cropEnabled);
    cropToggleBtn.classList.toggle("btn-primary", cropEnabled);
    // ONにした瞬間は枠が無いので案内だけ
    preprocess();
  });

  cropResetBtn.addEventListener("click", () => {
    cropRect = null;
    isDragging = false;
    dragStart = null;
    drawSrcWithOverlay();
    preprocess();
  });

  // ドラッグ/タッチで切り抜き範囲指定
  srcCanvas.addEventListener("pointerdown", (ev) => {
    if (!hasImage || !cropEnabled) return;
    srcCanvas.setPointerCapture(ev.pointerId);
    isDragging = true;
    dragStart = getCanvasPointFromEvent(ev);
    cropRect = { x: dragStart.x, y: dragStart.y, w: 1, h: 1 };
    drawSrcWithOverlay();
  });

  srcCanvas.addEventListener("pointermove", (ev) => {
    if (!isDragging || !dragStart || !cropEnabled) return;
    const cur = getCanvasPointFromEvent(ev);
    cropRect = normalizeRect(dragStart, cur);
    drawSrcWithOverlay();
  });

  srcCanvas.addEventListener("pointerup", (ev) => {
    if (!isDragging) return;
    isDragging = false;
    srcCanvas.releasePointerCapture(ev.pointerId);
    drawSrcWithOverlay();
    preprocess();
  });

  srcCanvas.addEventListener("pointercancel", (ev) => {
    if (!isDragging) return;
    isDragging = false;
    try { srcCanvas.releasePointerCapture(ev.pointerId); } catch {}
    drawSrcWithOverlay();
    preprocess();
  });

  async function runOcr() {
    if (!hasImage) return;
    setStatus("OCR中…");
    setProgress("");

    const dataUrl = procCanvas.toDataURL("image/png");

    const result = await Tesseract.recognize(
      dataUrl,
      "eng",
      {
        logger: m => {
          if (m.status) setProgress(`${m.status} ${(m.progress*100).toFixed(0)}%`);
        }
      }
    );

    const text = (result.data.text || "").replace(/\s/g, "");
    // 数字列を抽出（最長を採用）
    const matches = text.match(/\d+/g) || [];
    const best = matches.sort((a,b) => b.length - a.length)[0] || "";

    readingInput.value = best;
    setStatus(best ? "OCR完了（必要なら修正して保存）" : "OCR結果が空です。読み取り部分が小さい/反射/ピンボケの可能性。");
  }

  async function saveReading() {
  const raw = (readingInput.value || "").trim();
  if (!/^\d+$/.test(raw)) {
    setStatus("数値が不正です（数字のみ）。");
    return;
  }
  try {
    await addHistory(raw);
    await renderHistory();
    setStatus("保存しました（サーバ）。");
  } catch (e) {
    console.error(e);
    setStatus("保存に失敗しました（サーバ）。");
  }
}

async function exportCsv() {
  try {
    const res = await fetch("api_readings.php?action=export_csv", {
      method: "POST",
      headers: { "X-CSRF-Token": CSRF_TOKEN }
    });
    if (!res.ok) throw new Error("export failed");
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "readings.csv";
    a.click();
    URL.revokeObjectURL(url);
    setStatus("CSVを出力しました。");
  } catch (e) {
    console.error(e);
    setStatus("CSV出力に失敗しました。");
  }
}

  // 画像選択
  fileInput.addEventListener("change", () => {
    const f = fileInput.files && fileInput.files[0];
    if (!f) return;
    const img = new Image();
    img.onload = () => drawToCanvasFromImage(img);
    img.src = URL.createObjectURL(f);
    setStatus("画像を読み込みました。");
  });

  // カメラ開始
  startCamBtn.addEventListener("click", async () => {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: "environment" } },
        audio: false
      });
      video.srcObject = stream;
      await video.play();

      video.style.display = "block";
      captureBtn.disabled = false;
      stopCamBtn.disabled = false;
      runOcrBtn.disabled = true;
      hasImage = false;
      cropToggleBtn.disabled = true;
      cropResetBtn.disabled = true;
      cropRect = null;
      cropEnabled = false;
      cropToggleBtn.textContent = "切り抜き：OFF";
      cropInfoEl.textContent = "液晶ディスプレイ部分をドラッグで囲うと、その範囲だけで前処理→OCRします。";
      setStatus("カメラ起動中。撮影してください。");
    } catch (e) {
      setStatus("カメラを起動できませんでした（権限/HTTPS/端末対応）。");
    }
  });

  // 撮影
  captureBtn.addEventListener("click", () => {
    if (!video.videoWidth) return;
    const maxSide = 1200;
    const scale = Math.min(1, maxSide / Math.max(video.videoWidth, video.videoHeight));
    const w = Math.round(video.videoWidth * scale);
    const h = Math.round(video.videoHeight * scale);

    fullCanvas.width = w;
    fullCanvas.height = h;
    fullCtx.drawImage(video, 0, 0, w, h);

    // 撮影時も切り抜きをリセット
    cropRect = null;
    cropEnabled = false;
    cropToggleBtn.textContent = "切り抜き：OFF";
    cropToggleBtn.classList.add("btn-outline-primary");
    cropToggleBtn.classList.remove("btn-primary");

    srcCanvas.width = w;
    srcCanvas.height = h;
    drawSrcWithOverlay();

    video.style.display = "none";
    hasImage = true;
    runOcrBtn.disabled = false;
    cropToggleBtn.disabled = false;
    cropResetBtn.disabled = false;
    preprocess();
    setStatus("撮影しました。OCRを実行できます。");
  });

  // 停止
  stopCamBtn.addEventListener("click", () => {
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
    captureBtn.disabled = true;
    stopCamBtn.disabled = true;
    setStatus("カメラ停止。");
  });

  runOcrBtn.addEventListener("click", () => runOcr());
  saveBtn.addEventListener("click", async () => saveReading());

  clearAllBtn.addEventListener("click", async () => {
    try {
      await clearAll();
      await renderHistory();
      setStatus("全削除しました。");
    } catch (e) {
      console.error(e);
      setStatus("全削除に失敗しました。");
    }
  });

  exportCsvBtn.addEventListener("click", async () => exportCsv());

  // 初期表示
  renderHistory().catch(console.error);
})();
</script>
</body>
</html>
