<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

if (!canEdit()) {
    setFlashMessage('danger', 'A Vonalkód Tesztelő funkció csak Raktáros (Operátor) és Rendszergazda számára érhető el!');
    redirect('dashboard.php');
}

$db = Database::getInstance();
$currentUser = getCurrentUser();

// Minta ruhák lekérése a képernyős teszteléshez
$sampleClothes = $db->fetchAll("
    SELECT c.*, e.full_name as employee_name, e.employee_code, l.short_name as location_short, l.name as location_name
    FROM clothes c
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE c.barcode IS NOT NULL AND c.barcode != ''
    ORDER BY c.id ASC LIMIT 6
");

require_once __DIR__ . '/includes/header.php';
?>

<!-- JsBarcode és Html5Qrcode könyvtárak -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>

<div class="space-y-6 max-w-6xl mx-auto pb-12">

  <!-- FEJLÉC ÉS INFORMÁCIÓS SÁV -->
  <div class="bg-white dark:bg-slate-900 p-6 md:p-8 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-center space-x-4">
      <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-200 dark:border-indigo-800/60 shadow-inner shrink-0">
        <i data-lucide="scan-line" class="w-7 h-7"></i>
      </div>
      <div>
        <div class="flex items-center space-x-2 flex-wrap gap-y-1">
          <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Vonalkód Olvasó & Hardver Tesztelő</h1>
          <span class="px-2.5 py-0.5 text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 rounded-full border border-amber-300 dark:border-amber-800">Sandbox / Biztonságos Mód</span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Kockázatmentes tesztelés: a beolvasások <strong>NEM módosítják</strong> a ruhák státuszát, nem indítanak mosodai szállítólevelet és nem változtatják a leltárt.
        </p>
      </div>
    </div>

    <!-- Gyors Hangteszt Panel -->
    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800/80 p-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 self-start md:self-auto">
      <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 px-1 flex items-center">
        <i data-lucide="volume-2" class="w-3.5 h-3.5 mr-1 text-slate-400"></i> Hangok:
      </span>
      <button type="button" onclick="testSound('success')" title="Sikeres beolvasás hangja" class="px-2.5 py-1 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-lg text-xs font-bold transition-all">
        🟢 Siker
      </button>
      <button type="button" onclick="testSound('warning')" title="Figyelmeztetés hangja" class="px-2.5 py-1 bg-amber-100 hover:bg-amber-200 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 rounded-lg text-xs font-bold transition-all">
        🟡 Figyelem
      </button>
      <button type="button" onclick="testSound('error')" title="Hiba hangja" class="px-2.5 py-1 bg-red-100 hover:bg-red-200 text-red-800 dark:bg-red-950/60 dark:text-red-300 rounded-lg text-xs font-bold transition-all">
        🔴 Hiba
      </button>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- BAL OSZLOP (2 COL): FŐ BEVITELI MEZŐ, KAMERÁS SZKENNER ÉS HARDVER DIAGNOSZTIKA -->
    <div class="lg:col-span-2 space-y-6">

      <!-- FŐ BEVITELI KÁRTYA -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 md:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
        <div class="flex items-center justify-between">
          <label for="test-barcode-input" class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider flex items-center space-x-1.5">
            <i data-lucide="barcode" class="w-4 h-4 text-indigo-600"></i>
            <span>Irányítsa ide az olvasót vagy használja a kamerát:</span>
          </label>
          <div class="flex items-center space-x-2">
            <label class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 flex items-center cursor-pointer">
              <input type="checkbox" id="auto-focus-toggle" checked class="rounded text-brand-600 mr-1.5 focus:ring-brand-500">
              Automatikus Fókusz
            </label>
          </div>
        </div>

        <div class="relative">
          <input type="text" id="test-barcode-input" autofocus autocomplete="off"
            class="w-full text-center text-2xl sm:text-4xl font-mono font-black tracking-widest py-5 sm:py-6 pl-6 pr-28 bg-slate-50 dark:bg-slate-800 border-3 border-indigo-500/80 rounded-2xl focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-indigo-500/20 text-slate-900 dark:text-white transition-all placeholder:text-slate-300 dark:placeholder:text-slate-600 placeholder:font-sans placeholder:text-base sm:placeholder:text-lg"
            placeholder="Olvasson be egy vonalkódot...">
          
          <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center space-x-1.5">
            <!-- Telefonos / Webkamerás beolvasó gomb -->
            <button type="button" id="btnToggleCamera" onclick="toggleCameraScanner()" title="Telefonos kamera / Webkamera bekapcsolása" class="p-2.5 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 rounded-xl transition-all border border-indigo-200 dark:border-indigo-800">
              <i data-lucide="camera" class="w-5 h-5"></i>
            </button>
            <button type="button" id="clear-input-btn" onclick="clearInput()" title="Mező törlése" class="p-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 rounded-xl transition-all">
              <i data-lucide="delete" class="w-5 h-5"></i>
            </button>
          </div>
        </div>

        <!-- TELEFONOS / WEBKAMERA NÉZŐKE -->
        <div id="camera-container" class="hidden max-w-md mx-auto overflow-hidden rounded-2xl border-2 border-indigo-400 bg-black shadow-lg relative">
          <div class="p-2.5 bg-slate-900 text-white flex items-center justify-between text-xs">
            <span class="font-bold flex items-center space-x-1.5 text-indigo-300">
              <i data-lucide="camera" class="w-4 h-4"></i>
              <span>Kamera Aktív (Irányítsa a vonalkódra)</span>
            </span>
            <button type="button" onclick="stopCameraScanner()" class="px-2.5 py-0.5 bg-red-600 hover:bg-red-700 text-white rounded font-bold text-[11px]">Kamera Bezárása</button>
          </div>
          <div id="qr-reader" style="width: 100%;"></div>
        </div>

        <!-- HARDVER DIAGNOSZTIKA HUD -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 text-xs">
          <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-0.5">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase">Beolvasott Hossz</span>
            <p id="diag-char-count" class="font-mono font-bold text-base text-slate-900 dark:text-white">0 karakter</p>
          </div>
          <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-0.5">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase">Enter Végjel</span>
            <p id="diag-enter-detected" class="font-bold text-base text-slate-400">Várakozás...</p>
          </div>
          <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-0.5">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase">Válaszidő</span>
            <p id="diag-latency" class="font-mono font-bold text-base text-slate-900 dark:text-white">- ms</p>
          </div>
          <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-0.5">
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase">Időpont</span>
            <p id="diag-timestamp" class="font-mono font-bold text-base text-slate-900 dark:text-white">-</p>
          </div>
        </div>
      </div>

      <!-- BEOLVASÁSI EREDMÉNY KÁRTYA -->
      <div id="result-card" class="hidden bg-white dark:bg-slate-900 rounded-3xl p-6 md:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 transition-all">
        <!-- Dinamikus tartalom JS-ből -->
      </div>

      <!-- MUNKAMENET BEOLVASÁSI ELŐZMÉNYEK -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center space-x-2">
            <i data-lucide="history" class="w-4 h-4 text-slate-400"></i>
            <span>Teszt Munkamenet Előzményei</span>
          </h3>
          <span id="session-scan-total" class="text-xs font-mono font-bold px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full">0 beolvasás</span>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-xs text-left">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-700">
              <tr>
                <th class="py-2 px-3">Idő</th>
                <th class="py-2 px-3">Beolvasott Vonalkód</th>
                <th class="py-2 px-3">Azonosított Ruha</th>
                <th class="py-2 px-3">Dolgozó / Hely</th>
                <th class="py-2 px-3">Státusz</th>
              </tr>
            </thead>
            <tbody id="test-history-body" class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
              <tr>
                <td colspan="5" class="py-6 text-center text-slate-400 font-sans">Még nem történt beolvasás a tesztelőben.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- JOBB OSZLOP (1 COL): KÉPERNYŐRŐL LEOLVASHATÓ MINTA VONALKÓDOK -->
    <div class="space-y-6">

      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
          <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
            <i data-lucide="sparkles" class="w-4 h-4 text-amber-500"></i>
            <span>Képernyőről Leolvasható Minták</span>
          </h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            <strong>Kattintson bármelyik kártyára</strong> a nagyított felugró ablakért, vagy olvassa le közvetlenül a lézeres/telefonos szkennerrel:
          </p>
        </div>

        <div class="space-y-3.5">
          <?php if (!empty($sampleClothes)): ?>
            <?php foreach ($sampleClothes as $idx => $sCloth): ?>
              <?php 
                $clothJson = htmlspecialchars(json_encode([
                  'barcode' => $sCloth['barcode'],
                  'name' => $sCloth['name'],
                  'category' => $sCloth['category'],
                  'size' => $sCloth['size'] ?: '-',
                  'employee' => $sCloth['employee_name'] ?: 'Tartalék Készlet',
                  'location' => $sCloth['location_short'] ?: $sCloth['location_name']
                ]), ENT_QUOTES, 'UTF-8');
              ?>
              <div onclick='openSampleModal(<?php echo $clothJson; ?>)'
                class="p-4 bg-slate-50 dark:bg-slate-800/50 hover:bg-indigo-50/60 dark:hover:bg-indigo-950/40 rounded-2xl border border-slate-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-600 transition-all cursor-pointer group shadow-xs">
                <div class="flex items-center justify-between text-xs mb-1.5">
                  <span class="font-bold text-slate-800 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex items-center space-x-1">
                    <span><?php echo escape($sCloth['name']); ?></span>
                    <span class="font-mono text-slate-500 font-normal">(<?php echo escape($sCloth['size'] ?: '-'); ?>)</span>
                  </span>
                  <span class="text-[10px] px-2 py-0.5 bg-brand-100 dark:bg-brand-950/60 text-brand-800 dark:text-brand-300 font-semibold rounded-full"><?php echo escape($sCloth['location_short']); ?></span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mb-2">
                  Dolgozó: <strong class="text-slate-700 dark:text-slate-300"><?php echo escape($sCloth['employee_name'] ?: 'Tartalék'); ?></strong>
                </div>
                <div class="bg-white p-2 rounded-xl flex justify-center border border-slate-200 shadow-inner group-hover:shadow-md transition-all">
                  <svg id="sample-barcode-<?php echo $idx; ?>" class="max-w-full h-11"></svg>
                </div>
                <div class="text-center pt-1.5">
                  <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 group-hover:underline flex items-center justify-center space-x-1">
                    <i data-lucide="maximize-2" class="w-3 h-3"></i>
                    <span>Kattintson a nagyított felugró beolvasáshoz</span>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="p-6 text-center text-xs text-slate-400">
              Még nincs rögzített ruha az adatbázisban a mintákhoz.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- HASZNOS TIPPEK KÁRTYA -->
      <div class="bg-indigo-50/70 dark:bg-indigo-950/40 p-5 rounded-3xl border border-indigo-200/80 dark:border-indigo-800/50 space-y-2.5 text-xs text-indigo-950 dark:text-indigo-200">
        <h4 class="font-bold flex items-center space-x-1.5 text-indigo-900 dark:text-indigo-300">
          <i data-lucide="help-circle" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
          <span>Tippek a vonalkód olvasó beállításához:</span>
        </h4>
        <ul class="space-y-1.5 list-disc list-inside text-indigo-900/80 dark:text-indigo-300/90 leading-relaxed">
          <li><strong>Telefonos beolvasás:</strong> Kattintson a kamera ikonra (<i data-lucide="camera" class="w-3.5 h-3.5 inline"></i>), engedélyezze a kamerát, és irányítsa a telefonját a monitoron lévő vonalkódra!</li>
          <li><strong>Automatikus Enter:</strong> A legtöbb kézi olvasó füzetében található "Add Enter / CR Suffix" kód lecsippantásával kapcsolható be.</li>
        </ul>
      </div>

    </div>

  </div>

</div>

<!-- NAGYÍTOTT MINTA VONALKÓD FELUGRÓ ABLAK (MODAL) -->
<div id="sample-barcode-modal" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md hidden flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 max-w-lg w-full rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden p-6 sm:p-8 space-y-6 text-center animate-in fade-in zoom-in-95 duration-150">
    
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
      <div class="flex items-center space-x-2">
        <div class="p-2 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-xl">
          <i data-lucide="scan" class="w-5 h-5"></i>
        </div>
        <div class="text-left">
          <h3 class="text-base font-black text-slate-900 dark:text-white">Képernyős Minta Vonalkód</h3>
          <p class="text-[11px] text-slate-500">Irányítsa ide az olvasót vagy telefonját</p>
        </div>
      </div>
      <button type="button" onclick="closeSampleModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Ruha adatai -->
    <div class="bg-slate-50 dark:bg-slate-800/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 text-xs text-left grid grid-cols-2 gap-3">
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Munkaruha:</span>
        <strong id="modal-cloth-name" class="text-slate-900 dark:text-white text-sm"></strong>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Méret & Kategória:</span>
        <span id="modal-cloth-size" class="text-slate-800 dark:text-slate-200 font-semibold"></span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Dolgozó:</span>
        <span id="modal-cloth-employee" class="text-slate-800 dark:text-slate-200 font-bold"></span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Telephely:</span>
        <span id="modal-cloth-location" class="text-slate-800 dark:text-slate-200"></span>
      </div>
    </div>

    <!-- Nagy felbontású vonalkód SVG -->
    <div class="p-6 bg-white rounded-2xl border-2 border-indigo-500/40 shadow-md flex flex-col items-center justify-center">
      <svg id="modal-barcode-svg" class="max-w-full h-24"></svg>
      <p class="text-xs font-mono font-bold text-slate-500 mt-2">CODE 128 Szabvány</p>
    </div>

    <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs text-emerald-800 dark:text-emerald-300 font-medium flex items-center justify-center space-x-2">
      <i data-lucide="sparkles" class="w-4 h-4 text-emerald-600 shrink-0"></i>
      <span><strong>Automatikus bezárás:</strong> Amikor az olvasóval vagy a telefonnal leolvassa a fenti kódot, ez az ablak magától azonnal bezáródik!</span>
    </div>

    <div class="pt-2 flex justify-end">
      <button type="button" onclick="closeSampleModal()" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs rounded-xl transition-all">
        Bezárás
      </button>
    </div>

  </div>
</div>

<script src="js/audio.js"></script>
<script>
let scanHistory = [];
let keyStrokeStart = 0;
let keyStrokeCount = 0;

let html5QrCode = null;
let isCameraRunning = false;

const inputEl = document.getElementById('test-barcode-input');
const charCountEl = document.getElementById('diag-char-count');
const enterDetectedEl = document.getElementById('diag-enter-detected');
const latencyEl = document.getElementById('diag-latency');
const timestampEl = document.getElementById('diag-timestamp');
const resultCard = document.getElementById('result-card');
const historyBody = document.getElementById('test-history-body');
const totalCountEl = document.getElementById('session-scan-total');
const autoFocusToggle = document.getElementById('auto-focus-toggle');
const sampleModal = document.getElementById('sample-barcode-modal');

// Minták legenerálása JsBarcode segítségével az oldalon
document.addEventListener('DOMContentLoaded', () => {
  <?php if (!empty($sampleClothes)): ?>
    <?php foreach ($sampleClothes as $idx => $sCloth): ?>
      try {
        JsBarcode("#sample-barcode-<?php echo $idx; ?>", "<?php echo escape($sCloth['barcode']); ?>", {
          format: "CODE128",
          width: 1.5,
          height: 32,
          displayValue: true,
          fontSize: 11,
          margin: 2
        });
      } catch(e) {}
    <?php endforeach; ?>
  <?php endif; ?>

  if (window.lucide) lucide.createIcons();
});

// Automatikus fókusz megőrzése
document.addEventListener('click', (e) => {
  if (autoFocusToggle.checked && !sampleModal.classList.contains('hidden')) return; // ha a modal nyitva van, ne fókuszáljunk át erőszakosan
  if (autoFocusToggle.checked && e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A' && !isCameraRunning) {
    inputEl.focus();
  }
});

// Gépelési / Olvasási sebesség és karakterfigyelő
inputEl.addEventListener('keydown', (e) => {
  if (keyStrokeCount === 0) {
    keyStrokeStart = performance.now();
  }
  keyStrokeCount++;

  if (e.key === 'Enter') {
    e.preventDefault();
    const duration = Math.round(performance.now() - keyStrokeStart);
    const code = inputEl.value.trim();
    
    if (code) {
      processTestScan(code, duration, true);
    }
    inputEl.value = '';
    keyStrokeCount = 0;
  }
});

function clearInput() {
  inputEl.value = '';
  inputEl.focus();
}

function testSound(type) {
  try {
    if (window.SoundEffects) {
      if (type === 'success') SoundEffects.playSuccess();
      else if (type === 'warning') SoundEffects.playWarning();
      else if (type === 'error') SoundEffects.playError();
    }
  } catch(e) {}
}

// -------------------------------------------------------------
// FELUGRÓ MINTA VONALKÓD MODÁL
// -------------------------------------------------------------
function openSampleModal(cloth) {
  document.getElementById('modal-cloth-name').textContent = cloth.name;
  document.getElementById('modal-cloth-size').textContent = `${cloth.size || '-'} (${cloth.category})`;
  document.getElementById('modal-cloth-employee').textContent = cloth.employee;
  document.getElementById('modal-cloth-location').textContent = cloth.location;

  try {
    JsBarcode("#modal-barcode-svg", cloth.barcode, {
      format: "CODE128",
      width: 2.6,
      height: 80,
      displayValue: true,
      fontSize: 16,
      margin: 8
    });
  } catch(e) {}

  sampleModal.classList.remove('hidden');
  if (window.lucide) lucide.createIcons();
}

function closeSampleModal() {
  sampleModal.classList.add('hidden');
  if (autoFocusToggle.checked) {
    inputEl.focus();
  }
}

// -------------------------------------------------------------
// TELEFONOS / WEBKAMERÁS BEOLVASÓ (HTML5-QRCODE)
// -------------------------------------------------------------
async function toggleCameraScanner() {
  const container = document.getElementById('camera-container');
  const btn = document.getElementById('btnToggleCamera');

  if (isCameraRunning) {
    await stopCameraScanner();
    return;
  }

  container.classList.remove('hidden');
  btn.classList.add('bg-indigo-600', 'text-white');
  btn.classList.remove('bg-indigo-50', 'text-indigo-700');

  html5QrCode = new Html5Qrcode("qr-reader");

  const config = {
    fps: 15,
    qrbox: { width: 280, height: 180 },
    aspectRatio: 1.777778
  };

  try {
    await html5QrCode.start(
      { facingMode: "environment" },
      config,
      (decodedText) => {
        if (decodedText) {
          processTestScan(decodedText.trim(), 10, true);
        }
      },
      (err) => {}
    );
    isCameraRunning = true;
  } catch (err) {
    alert("Nem sikerült elindítani a kamerát: " + err);
    stopCameraScanner();
  }
}

async function stopCameraScanner() {
  if (html5QrCode && isCameraRunning) {
    try {
      await html5QrCode.stop();
      html5QrCode.clear();
    } catch(e){}
    isCameraRunning = false;
  }
  document.getElementById('camera-container')?.classList.add('hidden');
  const btn = document.getElementById('btnToggleCamera');
  if (btn) {
    btn.classList.remove('bg-indigo-600', 'text-white');
    btn.classList.add('bg-indigo-50', 'text-indigo-700');
  }
}

// -------------------------------------------------------------
// FŐ BEOLVASÁSI FELDOLGOZÓ (AJAX)
// -------------------------------------------------------------
async function processTestScan(barcode, latencyMs, enterSent) {
  // Beolvasáskor automatikusan zárjuk be a felugró minta ablakot, ha nyitva volt!
  closeSampleModal();

  charCountEl.textContent = `${barcode.length} karakter`;
  latencyEl.textContent = `${latencyMs} ms`;
  timestampEl.textContent = new Date().toLocaleTimeString('hu-HU');
  
  if (enterSent) {
    enterDetectedEl.innerHTML = '<span class="text-emerald-600 font-bold">✓ Észlelve (Enter)</span>';
  } else {
    enterDetectedEl.innerHTML = '<span class="text-amber-500 font-bold">⚠️ Nem észlelt</span>';
  }

  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'test_scan', barcode: barcode })
    });
    const data = await res.json();

    if (data.found && data.cloth) {
      testSound(data.is_overlimit ? 'warning' : 'success');
      renderSuccessResult(data);
      addHistoryItem(barcode, data.cloth.name, data.cloth.employee_name || 'Tartalék', data.cloth.status, true);
    } else {
      testSound('error');
      renderNotFoundResult(barcode);
      addHistoryItem(barcode, 'Nincs rögzítve', '-', 'Ismeretlen', false);
    }

  } catch (err) {
    testSound('error');
    resultCard.classList.remove('hidden');
    resultCard.innerHTML = `
      <div class="p-4 rounded-2xl bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-xs">
        <strong>Hiba a teszt lekérdezés során:</strong> ${err.message}
      </div>
    `;
  }

  if (autoFocusToggle.checked && !isCameraRunning) {
    inputEl.focus();
  }
}

function renderSuccessResult(data) {
  const c = data.cloth;
  const washPercent = data.wash_percent;
  let statusBadge = '';
  if (c.status === 'ACTIVE') statusBadge = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Dolgozónál (Aktív)</span>';
  else if (c.status === 'IN_LAUNDRY') statusBadge = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Mosodában van</span>';
  else statusBadge = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">Tartalék raktárban</span>';

  let washBarColor = 'bg-emerald-500';
  if (washPercent >= 100) washBarColor = 'bg-red-500';
  else if (washPercent >= 75) washBarColor = 'bg-amber-500';

  resultCard.classList.remove('hidden');
  resultCard.innerHTML = `
    <div class="flex items-start justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center space-x-3">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-200 dark:border-emerald-800">
          <i data-lucide="check-circle-2" class="w-6 h-6"></i>
        </div>
        <div>
          <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Azonosított Munkaruha</span>
          <h3 class="text-xl font-black text-slate-900 dark:text-white">${c.name}</h3>
        </div>
      </div>
      <div>${statusBadge}</div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Vonalkód:</span>
        <span class="font-mono font-bold text-slate-900 dark:text-white text-sm">${c.barcode}</span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Méret & Kategória:</span>
        <span class="font-semibold text-slate-800 dark:text-slate-200">${c.size || '-'} &bull; ${c.category}</span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Telephely:</span>
        <span class="font-semibold text-slate-800 dark:text-slate-200">${c.location_name || '-'}</span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Hozzárendelt Dolgozó:</span>
        <span class="font-bold text-slate-900 dark:text-white">${c.employee_name || 'Tartalék Készlet'}</span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Szekrény / Törzsszám:</span>
        <span class="font-mono text-slate-700 dark:text-slate-300">${c.locker_number ? 'Szekrény: ' + c.locker_number : (c.employee_code ? 'Kód: ' + c.employee_code : '-')}</span>
      </div>
      <div>
        <span class="text-slate-400 font-bold block mb-0.5">Mosási Életciklus:</span>
        <div class="flex items-center space-x-2">
          <div class="w-20 bg-slate-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
            <div class="${washBarColor} h-2 rounded-full" style="width: ${Math.min(100, washPercent)}%"></div>
          </div>
          <span class="font-mono font-bold ${data.is_overlimit ? 'text-red-600' : 'text-slate-700 dark:text-slate-300'}">${data.wash_count} / ${data.max_wash_count}</span>
        </div>
      </div>
    </div>

    <div class="p-3 bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex items-center justify-between text-xs text-emerald-900 dark:text-emerald-300">
      <span class="flex items-center space-x-1.5">
        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
        <span><strong>Adatbázis védve:</strong> A beolvasás teszt üzemmódban történt, a ruha státusza változatlan maradt.</span>
      </span>
      <a href="clothes.php?search=${encodeURIComponent(c.barcode)}" class="font-bold underline hover:text-emerald-700">Adatlap Megnyitása &rarr;</a>
    </div>
  `;
  if (window.lucide) lucide.createIcons();
}

function renderNotFoundResult(barcode) {
  resultCard.classList.remove('hidden');
  resultCard.innerHTML = `
    <div class="flex items-start justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center space-x-3">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-200 dark:border-amber-800">
          <i data-lucide="alert-circle" class="w-6 h-6"></i>
        </div>
        <div>
          <span class="text-xs font-mono font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Ismeretlen / Új Vonalkód</span>
          <h3 class="text-xl font-black text-slate-900 dark:text-white font-mono">${barcode}</h3>
        </div>
      </div>
      <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Nincs a rendszerben</span>
    </div>

    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
      A beolvasott kód sikeresen átjutott az olvasótól, de ehhez a vonalkódhoz még nem tartozik ruha az adatbázisban.
    </p>

    <div class="pt-2 flex flex-wrap items-center gap-3">
      <a href="clothes.php?new_barcode=${encodeURIComponent(barcode)}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs flex items-center space-x-1.5 transition-all">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>
        <span>➕ Új Ruha Regisztrálása Ezzel a Vonalkóddal</span>
      </a>
    </div>
  `;
  if (window.lucide) lucide.createIcons();
}

function addHistoryItem(barcode, clothName, employee, status, isFound) {
  scanHistory.unshift({
    time: new Date().toLocaleTimeString('hu-HU'),
    barcode: barcode,
    clothName: clothName,
    employee: employee,
    status: status,
    isFound: isFound
  });

  totalCountEl.textContent = `${scanHistory.length} beolvasás`;

  historyBody.innerHTML = scanHistory.slice(0, 10).map(item => `
    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
      <td class="py-2.5 px-3 text-slate-400">${item.time}</td>
      <td class="py-2.5 px-3 font-bold text-slate-900 dark:text-white">${item.barcode}</td>
      <td class="py-2.5 px-3 font-sans text-slate-800 dark:text-slate-200">${item.clothName}</td>
      <td class="py-2.5 px-3 font-sans text-slate-600 dark:text-slate-400">${item.employee}</td>
      <td class="py-2.5 px-3 font-sans">
        <span class="px-2 py-0.5 rounded text-[11px] font-bold ${item.isFound ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'}">
          ${item.status}
        </span>
      </td>
    </tr>
  `).join('');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
