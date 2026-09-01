<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';

if (!canEdit()) {
    setFlashMessage('danger', 'A Mobil Vonalkód Terminálhoz legalább Raktáros (Operátor) jogosultság szükséges!');
    redirect('dashboard.php');
}

$db = Database::getInstance();
$currentUser = getCurrentUser();
$settingsObj = new Settings();
$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');

// Telephelyek
$locations = $db->fetchAll("SELECT * FROM locations WHERE active = 1 ORDER BY code ASC");
$activeLocId = getActiveLocationId();

// Ha telephely váltás történt
if (isset($_GET['set_location'])) {
    $_SESSION['active_location_id'] = $_GET['set_location'];
    redirect('mobile.php');
}
?>
<!DOCTYPE html>
<html lang="hu" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#0f172a">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Mobil Vonalkód Terminál - <?php echo escape($companyName); ?></title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://unpkg.com/html5-qrcode"></script>
  
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: { 50: '#f0fdf4', 100: '#dcfce7', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' }
          }
        }
      }
    }
  </script>

  <style>
    /* Mobil optimalizált sima görgetés és érintésérzékenység */
    * { -webkit-tap-highlight-color: transparent; }
    body { touch-action: manipulation; }
    
    /* Kamera lézer csík animáció */
    @keyframes laserScan {
      0% { top: 10%; opacity: 0.8; }
      50% { top: 90%; opacity: 1; }
      100% { top: 10%; opacity: 0.8; }
    }
    .laser-line {
      position: absolute;
      left: 5%;
      right: 5%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #22c55e, #10b981, transparent);
      box-shadow: 0 0 12px #22c55e;
      animation: laserScan 2s infinite ease-in-out;
      pointer-events: none;
    }
  </style>

  <script>
    // Sötét mód szinkronizálása
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  </script>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex flex-col font-sans select-none overflow-x-hidden">

  <!-- MOBIL FEJLÉC -->
  <header class="bg-slate-900/95 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 px-3 py-2.5 flex items-center justify-between shadow-md">
    <div class="flex items-center space-x-2.5">
      <div class="w-9 h-9 rounded-xl bg-brand-600 text-white flex items-center justify-center shadow-md shadow-brand-600/30 shrink-0">
        <i data-lucide="shirt" class="w-5 h-5"></i>
      </div>
      <div>
        <div class="flex items-center space-x-1.5">
          <span class="font-bold text-white text-sm leading-tight"><?php echo escape($companyName); ?></span>
          <span class="px-1.5 py-0.2 text-[10px] font-bold bg-brand-500/20 text-brand-400 rounded-md"><?php echo escape(getAppVersion()); ?></span>
        </div>
        <p class="text-[10px] text-slate-400 font-medium">Mobil Vonalkód Terminál</p>
      </div>
    </div>

    <!-- Gyors Vezérlők -->
    <div class="flex items-center space-x-1.5">
      <!-- Telephely választó -->
      <select onchange="location.href='mobile.php?set_location=' + this.value" class="bg-slate-800 text-slate-200 text-xs font-bold py-1.5 px-2 rounded-xl border border-slate-700 focus:outline-none focus:ring-1 focus:ring-brand-500 max-w-[120px] truncate">
        <?php foreach ($locations as $loc): ?>
          <option value="<?php echo $loc['id']; ?>" <?php echo ($activeLocId == $loc['id']) ? 'selected' : ''; ?>>
            <?php echo escape($loc['short_name'] ?: $loc['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Asztali Mód Gomb -->
      <a href="scanner.php" title="Asztali Nézet" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl border border-slate-700 transition-all">
        <i data-lucide="monitor" class="w-4 h-4"></i>
      </a>

      <!-- Kijelentkezés -->
      <a href="logout.php" title="Kijelentkezés" class="p-2 bg-red-950/60 hover:bg-red-900 text-red-300 rounded-xl border border-red-800/50 transition-all">
        <i data-lucide="log-out" class="w-4 h-4"></i>
      </a>
    </div>
  </header>

  <!-- FŐ TARTALOM (GÖRGETHETŐ) -->
  <main class="flex-1 overflow-y-auto p-3.5 space-y-3 pb-28">

    <!-- 1. MÓDVÁLASZTÓ GOMBOK (NAGY ÉRINTŐGOMBOK) -->
    <div class="grid grid-cols-3 gap-2">
      <!-- MOS-KI -->
      <button type="button" id="btn-mode-out" onclick="setScanMode('OUT')"
        class="mode-btn py-2.5 px-2 rounded-2xl font-bold text-xs flex flex-col items-center justify-center space-y-1 transition-all border-2 border-brand-500 bg-brand-600/20 text-brand-300 shadow-lg shadow-brand-950">
        <i data-lucide="log-out" class="w-5 h-5 rotate-90 text-brand-400"></i>
        <span>MOSODÁBA</span>
        <span class="text-[9px] font-normal opacity-80">(MOS-KI)</span>
      </button>

      <!-- MOS-BE -->
      <button type="button" id="btn-mode-in" onclick="setScanMode('IN')"
        class="mode-btn py-2.5 px-2 rounded-2xl font-bold text-xs flex flex-col items-center justify-center space-y-1 transition-all border-2 border-slate-800 bg-slate-900 text-slate-400">
        <i data-lucide="log-in" class="w-5 h-5 -rotate-90"></i>
        <span>VISSZAVÉTEL</span>
        <span class="text-[9px] font-normal opacity-80">(MOS-BE)</span>
      </button>

      <!-- TESZT -->
      <button type="button" id="btn-mode-test" onclick="setScanMode('TEST')"
        class="mode-btn py-2.5 px-2 rounded-2xl font-bold text-xs flex flex-col items-center justify-center space-y-1 transition-all border-2 border-slate-800 bg-slate-900 text-slate-400">
        <i data-lucide="scan-line" class="w-5 h-5"></i>
        <span>TESZT / INFÓ</span>
        <span class="text-[9px] font-normal opacity-80">(Csak ellenőrzés)</span>
      </button>
    </div>

    <!-- 2. KAMERA NÉZŐKE (ÉLŐ KAMERÁS SZKENNER) -->
    <div class="bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden shadow-2xl relative">
      
      <!-- Kamera Fejléc és Vezérlők -->
      <div class="px-3.5 py-2 bg-slate-900/90 border-b border-slate-800 flex items-center justify-between text-xs">
        <div class="flex items-center space-x-2">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span id="camera-status-label" class="font-bold text-slate-200">Kamera Kész</span>
        </div>

        <div class="flex items-center space-x-2">
          <!-- Vaku / Zseblámpa Gomb -->
          <button type="button" id="btn-toggle-torch" onclick="toggleTorch()" title="Zseblámpa bekapcsolása" class="hidden p-1.5 bg-slate-800 hover:bg-slate-700 text-amber-300 rounded-lg text-xs font-bold transition-all border border-slate-700">
            <i data-lucide="zap" class="w-4 h-4"></i>
          </button>
          
          <!-- Kamera Szünet/Indítás -->
          <button type="button" id="btn-toggle-cam-stream" onclick="toggleCameraStream()" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-bold transition-all border border-slate-700">
            <i data-lucide="pause" id="cam-play-pause-icon" class="w-4 h-4"></i>
          </button>
        </div>
      </div>

      <!-- Kamera Videó Konténer -->
      <div class="relative bg-black min-h-[220px] max-h-[280px] flex items-center justify-center overflow-hidden">
        <div id="mobile-qr-reader" class="w-full h-full"></div>
        <div id="laser-line" class="laser-line"></div>
      </div>

      <!-- Kézi / Bluetooth Scanner beviteli sáv -->
      <div class="p-2.5 bg-slate-900 border-t border-slate-800 flex items-center space-x-2">
        <div class="relative flex-1">
          <input type="text" id="mobile-manual-barcode" autocomplete="off"
            class="w-full text-center font-mono font-bold text-sm py-2 pl-3 pr-8 bg-slate-950 border border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-500 text-white placeholder:text-slate-600"
            placeholder="Vonalkód kézi beírása / Bluetooth...">
          <button type="button" onclick="clearManualInput()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
        <button type="button" onclick="submitManualInput()" class="px-3.5 py-2 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center space-x-1 shrink-0">
          <i data-lucide="corner-down-left" class="w-3.5 h-3.5"></i>
          <span>OK</span>
        </button>
      </div>

    </div>

    <!-- 3. AZONNALI BEOLVASÁSI VISSZAJELZŐ KÁRTYA (HUD) -->
    <div id="scan-result-card" class="hidden p-4 rounded-2xl border transition-all animate-in fade-in duration-200">
      <!-- Dinamikus tartalom JS-ből -->
    </div>

    <!-- 4. AKTUÁLIS CSOMAG MUNKAMENET TÉTELEK -->
    <div class="bg-slate-900 rounded-3xl border border-slate-800 p-4 space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <i data-lucide="layers" class="w-4 h-4 text-slate-400"></i>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Legutóbb Beolvasva</h3>
        </div>
        <span id="session-counter-badge" class="px-2 py-0.5 text-xs font-mono font-bold bg-slate-800 text-slate-300 rounded-full border border-slate-700">0 db</span>
      </div>

      <div id="recent-scans-list" class="space-y-2 max-h-48 overflow-y-auto pr-1 text-xs">
        <div class="py-6 text-center text-slate-500 font-sans">
          Még nincs beolvasott ruha ebben a munkamenetben.
        </div>
      </div>
    </div>

  </main>

  <!-- 5. RÖGZÍTETT ALSÓ MŰVELETI SÁV (STICKY FOOTER) -->
  <footer class="fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-md border-t border-slate-800 p-3 flex items-center justify-between gap-3 shadow-2xl">
    <div class="flex flex-col">
      <span class="text-[10px] text-slate-400 uppercase font-bold">Csomag állapota:</span>
      <div class="flex items-center space-x-1">
        <span id="footer-batch-count" class="text-lg font-mono font-black text-brand-400">0 db</span>
        <span id="footer-mode-label" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-brand-950 text-brand-300 border border-brand-800">MOS-KI</span>
      </div>
    </div>

    <div class="flex items-center space-x-2">
      <button type="button" id="btn-finish-batch" onclick="finishCurrentBatch()"
        class="px-4 py-2.5 bg-gradient-to-r from-brand-600 to-emerald-600 hover:from-brand-500 hover:to-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-brand-900/50 flex items-center space-x-1.5 transition-all">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        <span>Csomag Lezárása</span>
      </button>
    </div>
  </footer>

  <!-- SZÁLLÍTÓLEVÉL / SIKERES LEZÁRÁS MODAL -->
  <div id="batch-completed-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 max-w-sm w-full rounded-3xl border border-slate-800 shadow-2xl overflow-hidden p-6 space-y-5 text-center">
      <div class="w-14 h-14 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center mx-auto shadow-inner">
        <i data-lucide="check-circle-2" class="w-8 h-8"></i>
      </div>
      
      <div class="space-y-1">
        <h3 class="text-lg font-black text-white">Csomag Sikeresen Lezárva!</h3>
        <p id="modal-batch-num" class="font-mono font-bold text-brand-400 text-sm"></p>
        <p id="modal-batch-summary" class="text-xs text-slate-400"></p>
      </div>

      <div class="pt-2 space-y-2">
        <a id="btn-view-receipt" href="#" target="_blank" class="w-full py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 shadow-md">
          <i data-lucide="printer" class="w-4 h-4"></i>
          <span>Szállítólevél Megtekintése</span>
        </a>
        <button type="button" onclick="closeCompletedModal()" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl">
          Új Csomag Indítása
        </button>
      </div>
    </div>
  </div>

  <script src="js/audio.js"></script>
  <script>
    let currentMode = 'OUT'; // 'OUT' (Mosodába), 'IN' (Visszavétel), 'TEST' (Csak ellenőrzés)
    let currentBatch = null;
    let sessionItems = [];
    let html5QrCode = null;
    let isCamPaused = false;
    let lastScannedCode = '';
    let lastScanTime = 0;
    let videoTrack = null;
    let isTorchOn = false;

    const manualInput = document.getElementById('mobile-manual-barcode');
    const resultCard = document.getElementById('scan-result-card');
    const recentList = document.getElementById('recent-scans-list');
    const sessionBadge = document.getElementById('session-counter-badge');
    const footerCount = document.getElementById('footer-batch-count');
    const footerModeLabel = document.getElementById('footer-mode-label');
    const btnTorch = document.getElementById('btn-toggle-torch');

    // 1. Módváltás
    function setScanMode(mode) {
      currentMode = mode;
      document.querySelectorAll('.mode-btn').forEach(b => {
        b.className = 'mode-btn py-2.5 px-2 rounded-2xl font-bold text-xs flex flex-col items-center justify-center space-y-1 transition-all border-2 border-slate-800 bg-slate-900 text-slate-400';
      });

      if (mode === 'OUT') {
        const btn = document.getElementById('btn-mode-out');
        btn.className = 'mode-btn py-2.5 px-2 rounded-2xl font-bold text-xs flex flex-col items-center justify-center space-y-1 transition-all border-2 border-brand-500 bg-brand-600/20 text-brand-300 shadow-lg shadow-brand-950';
        footerModeLabel.textContent = 'MOS-KI';
        footerModeLabel.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded bg-brand-950 text-brand-300 border border-brand-800';
        document.getElementById('btn-finish-batch').classList.remove('hidden');
        loadActiveBatch();
      } else if (mode === 'IN') {
        const btn = document.getElementById('btn-mode-in');
        btn.className = 'mode-btn py-2.5 px-2 rounded-2xl font-bold text-xs flex flex-col items-center justify-center space-y-1 transition-all border-2 border-blue-500 bg-blue-600/20 text-blue-300 shadow-lg shadow-blue-950';
        footerModeLabel.textContent = 'MOS-BE';
        footerModeLabel.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-950 text-blue-300 border border-blue-800';
        document.getElementById('btn-finish-batch').classList.remove('hidden');
        loadActiveBatch();
      } else if (mode === 'TEST') {
        const btn = document.getElementById('btn-mode-test');
        btn.className = 'mode-btn py-2.5 px-2 rounded-2xl font-bold text-xs flex flex-col items-center justify-center space-y-1 transition-all border-2 border-amber-500 bg-amber-600/20 text-amber-300 shadow-lg shadow-amber-950';
        footerModeLabel.textContent = 'TESZT';
        footerModeLabel.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-800';
        document.getElementById('btn-finish-batch').classList.add('hidden');
      }

      if (window.lucide) lucide.createIcons();
    }

    // 2. Aktív csomag betöltése a szerverről
    async function loadActiveBatch() {
      if (currentMode === 'TEST') return;
      try {
        const res = await fetch('ajax_scanner.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'get_current_batch',
            direction: currentMode,
            location_id: '<?php echo $activeLocId; ?>'
          })
        });
        const data = await res.json();
        if (data.success && data.has_batch) {
          currentBatch = data.batch;
          sessionItems = (data.items || []).map(i => ({
            cloth_id: i.cloth_id,
            scanned_at: new Date(i.scanned_at).toLocaleTimeString('hu-HU'),
            barcode: i.barcode,
            cloth_name: i.cloth_name,
            category: i.category,
            size: i.size,
            employee_name: i.employee_name,
            status: (currentMode === 'OUT') ? 'Mosásba küldve' : 'Visszavéve'
          }));
        } else {
          currentBatch = null;
          sessionItems = [];
        }
        updateSessionUI();
      } catch (e) {}
    }

    // 3. Kamera Inicializálása (Html5Qrcode)
    async function startMobileCamera() {
      html5QrCode = new Html5Qrcode("mobile-qr-reader");
      const config = {
        fps: 15,
        qrbox: { width: 260, height: 160 },
        aspectRatio: 1.5
      };

      try {
        await html5QrCode.start(
          { facingMode: "environment" },
          config,
          (decodedText) => {
            handleDetectedBarcode(decodedText.trim());
          },
          () => {}
        );

        // Vaku képesség ellenőrzése
        try {
          const videoEl = document.querySelector("#mobile-qr-reader video");
          if (videoEl && videoEl.srcObject) {
            videoTrack = videoEl.srcObject.getVideoTracks()[0];
            const capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
            if (capabilities.torch) {
              btnTorch.classList.remove('hidden');
            }
          }
        } catch(e){}

      } catch (err) {
        document.getElementById('camera-status-label').textContent = 'Kamera hiba: ' + err;
      }
    }

    // Vaku ki/be kapcsolása
    async function toggleTorch() {
      if (!videoTrack) return;
      try {
        isTorchOn = !isTorchOn;
        await videoTrack.applyConstraints({ advanced: [{ torch: isTorchOn }] });
        btnTorch.classList.toggle('bg-amber-500', isTorchOn);
        btnTorch.classList.toggle('text-black', isTorchOn);
      } catch(e){}
    }

    // Kamera szüneteltetése
    function toggleCameraStream() {
      if (!html5QrCode) return;
      if (isCamPaused) {
        html5QrCode.resume();
        isCamPaused = false;
        document.getElementById('cam-play-pause-icon').setAttribute('data-lucide', 'pause');
        document.getElementById('laser-line').classList.remove('hidden');
      } else {
        html5QrCode.pause(true);
        isCamPaused = true;
        document.getElementById('cam-play-pause-icon').setAttribute('data-lucide', 'play');
        document.getElementById('laser-line').classList.add('hidden');
      }
      if (window.lucide) lucide.createIcons();
    }

    // 4. Beolvasott vonalkód lekezelése (Debounce & AJAX)
    async function handleDetectedBarcode(barcode) {
      const now = Date.now();
      // Duplikált olvasás elkerülése 2.5 másodpercen belül ugyanarra a kódra
      if (barcode === lastScannedCode && (now - lastScanTime) < 2500) {
        return;
      }
      lastScannedCode = barcode;
      lastScanTime = now;

      await processMobileScan(barcode);
    }

    async function processMobileScan(barcode) {
      if (!barcode) return;

      // Haptic rezgés a telefonon
      if (navigator.vibrate) navigator.vibrate(80);

      try {
        if (currentMode === 'TEST') {
          // TESZT MÓD
          const res = await fetch('ajax_scanner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'test_scan', barcode: barcode })
          });
          const data = await res.json();
          renderTestResult(data, barcode);
        } else {
          // ÉLES KIADÁS VAGY BEVÉTEL
          const res = await fetch('ajax_scanner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'scan',
              barcode: barcode,
              direction: currentMode,
              batch_id: currentBatch ? currentBatch.id : null,
              location_id: '<?php echo $activeLocId; ?>'
            })
          });
          const data = await res.json();
          renderLiveResult(data, barcode);
        }
      } catch (err) {
        playSound('error');
        showErrorHUD('Hálózati hiba a mentéskor!');
      }
    }

    function renderLiveResult(data, barcode) {
      if (data.sound === 'success') playSound('success');
      else if (data.sound === 'warning') playSound('warning');
      else playSound('error');

      resultCard.classList.remove('hidden');

      if (data.success && data.cloth) {
        currentBatch = data.batch;
        const c = data.cloth;

        sessionItems.unshift({
          cloth_id: c.id,
          scanned_at: new Date().toLocaleTimeString('hu-HU'),
          barcode: c.barcode,
          cloth_name: c.name,
          category: c.category,
          size: c.size,
          employee_name: c.employee_name,
          status: (currentMode === 'OUT') ? 'Mosásba küldve' : 'Visszavéve'
        });

        updateSessionUI();

        resultCard.className = 'p-4 rounded-2xl border bg-emerald-950/60 border-emerald-500/50 space-y-2 text-xs animate-in zoom-in-95';
        resultCard.innerHTML = `
          <div class="flex items-center justify-between">
            <span class="font-bold text-emerald-400 flex items-center space-x-1">
              <i data-lucide="check-circle" class="w-4 h-4"></i>
              <span>${currentMode === 'OUT' ? 'MOSODÁBA RÖGZÍTVE' : 'VISSZAVÉVE'}</span>
            </span>
            <span class="font-mono text-[11px] text-slate-400">${c.barcode}</span>
          </div>
          <div class="flex items-center justify-between">
            <strong class="text-sm font-black text-white">${c.name} (${c.size || '-'})</strong>
            <span class="font-semibold text-slate-200">${c.employee_name || 'Tartalék'}</span>
          </div>
          <div class="flex items-center justify-between text-[11px] text-slate-300 pt-1 border-t border-emerald-900">
            <span>Mosási ciklus: <strong>${c.wash_count || 1} / ${c.max_wash_count || 50}</strong></span>
            <span class="text-emerald-300 font-bold">${c.location_short || ''}</span>
          </div>
        `;
      } else {
        resultCard.className = 'p-4 rounded-2xl border bg-red-950/60 border-red-500/50 space-y-1 text-xs animate-in zoom-in-95';
        resultCard.innerHTML = `
          <div class="flex items-center space-x-1.5 text-red-400 font-bold">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            <span>${data.message || 'Ismeretlen vonalkód!'}</span>
          </div>
          <p class="font-mono text-slate-400">${barcode}</p>
        `;
      }

      if (window.lucide) lucide.createIcons();
    }

    function renderTestResult(data, barcode) {
      resultCard.classList.remove('hidden');

      if (data.found && data.cloth) {
        playSound('success');
        const c = data.cloth;
        resultCard.className = 'p-4 rounded-2xl border bg-amber-950/60 border-amber-500/50 space-y-2 text-xs animate-in zoom-in-95';
        resultCard.innerHTML = `
          <div class="flex items-center justify-between">
            <span class="font-bold text-amber-400 flex items-center space-x-1">
              <i data-lucide="info" class="w-4 h-4"></i>
              <span>TESZT / RUHA INFORMÁCIÓ</span>
            </span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-900 text-amber-200">Változatlan</span>
          </div>
          <div class="flex items-center justify-between">
            <strong class="text-sm font-black text-white">${c.name} (${c.size || '-'})</strong>
            <span class="font-semibold text-slate-200">${c.employee_name || 'Tartalék'}</span>
          </div>
          <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300 pt-1 border-t border-amber-900">
            <span>Státusz: <strong>${c.status === 'ACTIVE' ? 'Dolgozónál' : (c.status === 'IN_LAUNDRY' ? 'Mosásban' : 'Tartalék')}</strong></span>
            <span>Mosások: <strong>${data.wash_count} / ${data.max_wash_count}</strong></span>
          </div>
        `;
      } else {
        playSound('error');
        resultCard.className = 'p-4 rounded-2xl border bg-red-950/60 border-red-500/50 space-y-1 text-xs animate-in zoom-in-95';
        resultCard.innerHTML = `
          <div class="flex items-center space-x-1.5 text-red-400 font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4"></i>
            <span>Nincs ilyen ruha az adatbázisban!</span>
          </div>
          <p class="font-mono text-slate-400">${barcode}</p>
        `;
      }

      if (window.lucide) lucide.createIcons();
    }

    function updateSessionUI() {
      sessionBadge.textContent = `${sessionItems.length} db`;
      footerCount.textContent = `${sessionItems.length} db`;

      if (sessionItems.length === 0) {
        recentList.innerHTML = '<div class="py-6 text-center text-slate-500 font-sans">Még nincs beolvasott ruha ebben a munkamenetben.</div>';
        return;
      }

      recentList.innerHTML = sessionItems.slice(0, 8).map((item, idx) => `
        <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
          <div class="space-y-0.5">
            <div class="font-bold text-slate-200">${item.cloth_name} (${item.size || '-'})</div>
            <div class="text-[10px] text-slate-400">${item.employee_name || 'Tartalék'} &bull; <span class="font-mono">${item.barcode}</span></div>
          </div>
          <span class="font-mono text-[10px] text-slate-500">${item.scanned_at}</span>
        </div>
      `).join('');
    }

    // 5. Kézi beviteli események
    function clearManualInput() {
      manualInput.value = '';
      manualInput.focus();
    }

    function submitManualInput() {
      const code = manualInput.value.trim();
      if (code) {
        processMobileScan(code);
        manualInput.value = '';
      }
    }

    manualInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitManualInput();
      }
    });

    // 6. Csomag lezárása
    async function finishCurrentBatch() {
      if (!currentBatch || sessionItems.length === 0) {
        alert('Nincs lezárandó csomag! Előbb olvasson be ruhákat.');
        return;
      }

      if (!confirm(`Biztosan lezárja a mostani (${sessionItems.length} db ruhát tartalmazó) csomagot?`)) {
        return;
      }

      try {
        const res = await fetch('ajax_scanner.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'finish_batch',
            batch_id: currentBatch.id,
            notes: 'Mobil terminálról lezárva'
          })
        });
        const data = await res.json();

        if (data.success) {
          document.getElementById('modal-batch-num').textContent = data.batch.batch_number;
          document.getElementById('modal-batch-summary').textContent = `${data.items.length} db munkaruha &bull; ${data.batch.direction === 'OUT' ? 'Kiadás mosodába' : 'Visszavétel mosásból'}`;
          document.getElementById('btn-view-receipt').href = 'batches.php?view=' + data.batch.id;
          document.getElementById('batch-completed-modal').classList.remove('hidden');

          sessionItems = [];
          currentBatch = null;
          updateSessionUI();
        } else {
          alert('Hiba: ' + data.message);
        }
      } catch (err) {
        alert('Hiba a lezárás során: ' + err.message);
      }
    }

    function closeCompletedModal() {
      document.getElementById('batch-completed-modal').classList.add('hidden');
      loadActiveBatch();
    }

    function playSound(type) {
      try {
        if (window.SoundEffects) {
          if (type === 'success') SoundEffects.playSuccess();
          else if (type === 'warning') SoundEffects.playWarning();
          else if (type === 'error') SoundEffects.playError();
        }
      } catch(e){}
    }

    function showErrorHUD(msg) {
      resultCard.classList.remove('hidden');
      resultCard.className = 'p-4 rounded-2xl border bg-red-950/60 border-red-500/50 text-red-300 text-xs font-bold';
      resultCard.textContent = msg;
    }

    // Inicializálás
    document.addEventListener('DOMContentLoaded', () => {
      startMobileCamera();
      loadActiveBatch();
      if (window.lucide) lucide.createIcons();
    });
  </script>

</body>
</html>
