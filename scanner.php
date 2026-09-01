<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <?php if (!canEdit()): ?>
    <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center space-x-3 text-amber-900 text-sm font-medium shadow-xs">
      <i data-lucide="shield-alert" class="w-5 h-5 text-amber-600 shrink-0"></i>
      <span><strong>Csak Megtekintés:</strong> Az Ön fiókja (Megtekintő / Vezető) olvasási jogosultsággal rendelkezik. Vonalkód olvasást és csomagküldést kizárólag Operátor (Raktáros) és Rendszergazda végezhet.</span>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('barcode-input');
        if (input) {
          input.disabled = true;
          input.placeholder = 'Csak megtekintés (Olvasási mód)';
        }
        const manBtn = document.getElementById('btnOpenManualSelectModal');
        if (manBtn) manBtn.classList.add('hidden');
        const camBtn = document.getElementById('camera-scan-btn');
        if (camBtn) camBtn.classList.add('hidden');
        const cancelBtn = document.getElementById('btnCancelBatch');
        if (cancelBtn) cancelBtn.classList.add('hidden');
        const finishBtn = document.getElementById('finish-batch-btn');
        if (finishBtn) finishBtn.classList.add('hidden');
      });
    </script>
  <?php endif; ?>

  <!-- Módváltó gombok -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <button id="scan-mode-out" class="p-5 rounded-2xl border-3 border-brand-600 bg-brand-50/80 shadow-md text-left transition-all relative overflow-hidden group">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-12 h-12 rounded-xl bg-brand-600 text-white flex items-center justify-center shadow-lg shadow-brand-600/30">
            <i data-lucide="log-out" class="w-6 h-6 rotate-90"></i>
          </div>
          <div>
            <h3 class="font-bold text-slate-900 text-lg">MOSODÁBA KÜLDÉS (Kiolvasás)</h3>
            <p class="text-xs text-slate-600">Szennyes ruhák átadása a mosodának</p>
          </div>
        </div>
        <span id="badge-mode-out" class="px-3 py-1 bg-brand-600 text-white text-xs font-bold rounded-full uppercase tracking-wider">AKTÍV MÓD</span>
      </div>
    </button>

    <button id="scan-mode-in" class="p-5 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 shadow-xs text-left transition-all relative overflow-hidden group">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-600/30">
            <i data-lucide="log-in" class="w-6 h-6 -rotate-90"></i>
          </div>
          <div>
            <h3 class="font-bold text-slate-900 text-lg">VISSZAVÉTEL MOSÁSBÓL (Beolvasás)</h3>
            <p class="text-xs text-slate-600">Tiszta ruhák beérkezése és visszarendelése</p>
          </div>
        </div>
        <span id="badge-mode-in" class="hidden px-3 py-1 bg-blue-600 text-white text-xs font-bold rounded-full uppercase tracking-wider">AKTÍV MÓD</span>
      </div>
    </button>
  </div>

  <!-- Beviteli és Kézi kiválasztó kártya -->
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 md:p-8">
    <div class="max-w-2xl mx-auto text-center space-y-4">
      <div class="flex items-center justify-center space-x-2 text-slate-500 text-sm font-semibold">
        <i data-lucide="barcode" class="w-5 h-5 text-brand-600"></i>
        <span>VONALKÓD BEOLVASÁSA VAGY KÉZI KIVÁLASZTÁS</span>
      </div>

      <div class="relative">
        <input type="text" id="barcode-input" autofocus autocomplete="off"
          class="w-full text-center text-3xl md:text-4xl font-mono font-bold tracking-widest py-5 px-6 bg-slate-50 border-2 border-brand-500 rounded-2xl focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-500/20 text-slate-900 transition-all placeholder:text-slate-300 placeholder:font-sans placeholder:text-xl"
          placeholder="Olvassa be a vonalkódot...">
        
        <button id="camera-scan-btn" title="Kamerás olvasás bekapcsolása" class="absolute right-4 top-1/2 -translate-y-1/2 p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition-all">
          <i data-lucide="camera" class="w-6 h-6"></i>
        </button>
      </div>

      <div id="camera-container" class="hidden max-w-md mx-auto overflow-hidden rounded-2xl border-2 border-slate-300 shadow-md">
        <div id="qr-reader" style="width: 100%;"></div>
        <button id="close-camera-btn" class="w-full py-2 bg-slate-800 text-white text-xs font-bold">Kamera bezárása</button>
      </div>

      <!-- Kézi Kiválasztás & Mobil Gombok -->
      <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
        <a href="mobile.php" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center space-x-2">
          <i data-lucide="smartphone" class="w-4 h-4"></i>
          <span>📱 Mobil Vonalkód Terminál</span>
        </a>
        <button type="button" id="btnOpenManualSelectModal" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs rounded-xl shadow-xs transition-all flex items-center space-x-2">
          <i data-lucide="list-plus" class="w-4 h-4 text-brand-400"></i>
          <span>📋 Munkaruhák Kiválasztása Listából</span>
        </button>
      </div>

      <div class="flex items-center justify-center space-x-6 text-xs text-slate-400 font-medium pt-1">
        <span class="flex items-center"><i data-lucide="volume-2" class="w-4 h-4 mr-1 text-emerald-500"></i> Hangjelzés aktív</span>
        <span class="flex items-center"><i data-lucide="zap" class="w-4 h-4 mr-1 text-amber-500"></i> Azonnali rögzítés Enter-re</span>
      </div>
    </div>

    <div id="scan-feedback" class="hidden mt-6 p-4 rounded-xl text-center font-bold text-base transition-all duration-300"></div>
  </div>

  <!-- Aktuális Csomag Táblázat -->
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="flex items-center space-x-2">
          <h2 class="text-base font-bold text-slate-900">Aktuális Beolvasott Csomag</h2>
          <span id="current-batch-number" class="px-2 py-0.5 text-xs font-mono font-semibold bg-slate-200 text-slate-800 rounded"></span>
        </div>
        <p class="text-xs text-slate-500 mt-0.5">A most rögzített munkaruhák listája (tételek egyenként is törölhetők)</p>
      </div>

      <div class="flex items-center space-x-3">
        <div class="text-right">
          <span class="text-xs text-slate-500 font-semibold block">Beolvasva:</span>
          <span id="session-count" class="text-2xl font-black text-brand-600">0 db</span>
        </div>
        <button id="btnCancelBatch" class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-bold rounded-xl transition-all flex items-center space-x-1" title="Csomag teljes törlése és visszaállítása">
          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
          <span>Csomag Kiürítése</span>
        </button>
        <button id="finish-batch-btn" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl transition-all flex items-center space-x-1.5 shadow-sm">
          <i data-lucide="check-circle" class="w-4 h-4"></i>
          <span>Csomag Lezárása & Szállítólevél</span>
        </button>
      </div>
    </div>

    <div id="batch-category-summary" class="px-6 py-3 bg-white border-b border-slate-100 flex flex-wrap gap-3 text-xs font-semibold text-slate-600"></div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold text-left">
          <tr>
            <th class="px-6 py-3">Időpont</th>
            <th class="px-6 py-3">Vonalkód</th>
            <th class="px-6 py-3">Megnevezés</th>
            <th class="px-6 py-3">Kategória / Szín</th>
            <th class="px-6 py-3">Méret</th>
            <th class="px-6 py-3">Dolgozó / Tartalék</th>
            <th class="px-6 py-3">Telephely</th>
            <th class="px-6 py-3">Státusz</th>
            <th class="px-6 py-3 text-right">Eltávolítás</th>
          </tr>
        </thead>
        <tbody id="session-items-table" class="divide-y divide-slate-100 bg-white">
          <tr>
            <td colspan="9" class="px-6 py-8 text-center text-slate-400">
              Még nincs beolvasott ruha ebben a munkamenetben. Használja a vonalkód olvasót vagy a kézi kiválasztót!
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- KÉZI RUHA KIVÁLASZTÓ MODÁL -->
<div id="manual-select-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <div class="p-2.5 bg-brand-50 text-brand-600 rounded-xl"><i data-lucide="list-plus" class="w-5 h-5"></i></div>
        <div>
          <h3 class="text-lg font-bold text-slate-900">Munkaruhák Kézi Kiválasztása a Csomaghoz</h3>
          <p class="text-xs text-slate-500" id="manual-modal-direction-hint">Jelölje be a mosodába küldendő munkaruhákat</p>
        </div>
      </div>
      <button id="btnCloseManualModal" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>

    <!-- Kereső és szűrősáv -->
    <div class="p-4 bg-slate-50 border-b border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="sm:col-span-2 relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" id="manual-search-input" placeholder="Keresés dolgozó neve, ruha megnevezése, méret vagy kód..."
          class="w-full pl-9 pr-3 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:outline-none">
      </div>
      <div>
        <select id="manual-category-filter" class="w-full py-2 px-3 text-xs bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
          <option value="">Összes kategória</option>
          <option value="Póló">Póló</option>
          <option value="Köpeny">Köpeny</option>
          <option value="Nadrág">Nadrág</option>
          <option value="Kazak">Kazak</option>
          <option value="Egyéb">Egyéb</option>
        </select>
      </div>
    </div>

    <!-- Ruha lista táblázat -->
    <div class="flex-1 overflow-y-auto p-4">
      <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-600 font-bold uppercase sticky top-0 bg-slate-50">
          <tr>
            <th class="py-2.5 px-3 w-10 text-center">
              <input type="checkbox" id="manual-select-all" class="w-4 h-4 text-brand-600 rounded border-slate-300">
            </th>
            <th class="py-2.5 px-3 text-left">Dolgozó / Cikkszám</th>
            <th class="py-2.5 px-3 text-left">Megnevezés</th>
            <th class="py-2.5 px-3 text-left">Kategória / Szín</th>
            <th class="py-2.5 px-3 text-left">Méret</th>
            <th class="py-2.5 px-3 text-left">Vonalkód</th>
            <th class="py-2.5 px-3 text-left">Telephely</th>
          </tr>
        </thead>
        <tbody id="manual-clothes-table-body" class="divide-y divide-slate-100 bg-white">
          <tr><td colspan="7" class="py-8 text-center text-slate-400">Ruhák betöltése...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Lábléc & Akciógomb -->
    <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
      <div class="text-xs text-slate-600">
        Kijelölve: <b id="manual-selected-count" class="text-brand-600 font-black">0</b> db ruha
      </div>
      <div class="flex items-center space-x-3">
        <button type="button" id="btnCancelManualSelect" class="px-4 py-2 text-slate-600 hover:bg-slate-200 rounded-xl text-xs font-semibold">Mégse</button>
        <button type="button" id="btnAddSelectedToBatch" class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all flex items-center space-x-1.5">
          <i data-lucide="plus-circle" class="w-4 h-4"></i>
          <span>Kijelöltek Hozzáadása a Csomaghoz</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- NYOMTATHATÓ SZÁLLÍTÓLEVÉL MODÁL -->
<div id="batch-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-3xl w-full p-8 space-y-6 my-auto">
    <div id="printable-area" class="space-y-6 text-slate-800">
      <div class="border-b-2 border-slate-800 pb-4 flex justify-between items-start">
        <div>
          <h1 class="text-2xl font-black tracking-tight text-slate-900">HGA Biomed Kft.</h1>
          <p class="text-xs text-slate-600 font-semibold mt-1">MUNKARUHA MOSODAI ÁTADÁS-ÁTVÉTELI JEGYZÉK</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-slate-500 font-bold uppercase">Bizonylatszám</p>
          <p id="print-batch-number" class="text-lg font-mono font-black text-slate-900"></p>
          <p id="print-batch-date" class="text-xs text-slate-500 font-medium mt-0.5"></p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl text-xs border border-slate-200">
        <div>
          <span class="font-bold text-slate-500 uppercase block mb-1">Küldő / Kiadó Telephely:</span>
          <p id="print-location-name" class="font-bold text-slate-900 text-sm">HGA Biomed</p>
          <p id="print-location-address" class="text-slate-600"></p>
          <p class="text-slate-500 mt-1">Kezelő: <span id="print-user-name" class="font-semibold text-slate-800"></span></p>
        </div>
        <div>
          <span class="font-bold text-slate-500 uppercase block mb-1">Művelet Jellege:</span>
          <p id="print-direction-label" class="font-black text-brand-700 text-sm"></p>
          <p class="text-slate-600">Mosodai Szolgáltató részére</p>
          <p class="text-slate-500 mt-1">Összes darabszám: <span id="print-total-count" class="font-black text-slate-900 text-sm">0 db</span></p>
        </div>
      </div>

      <div id="print-category-breakdown" class="flex flex-wrap gap-2 text-xs"></div>

      <table class="min-w-full divide-y divide-slate-300 text-xs">
        <thead class="bg-slate-100 font-bold text-slate-700 text-left">
          <tr>
            <th class="py-2 px-3">Ssz.</th>
            <th class="py-2 px-3">Vonalkód</th>
            <th class="py-2 px-3">Megnevezés</th>
            <th class="py-2 px-3">Méret</th>
            <th class="py-2 px-3">Dolgozó / Tartalék</th>
          </tr>
        </thead>
        <tbody id="print-items-body" class="divide-y divide-slate-200 font-mono"></tbody>
      </table>

      <div class="grid grid-cols-2 gap-12 pt-12 border-t border-slate-200 text-center text-xs">
        <div>
          <div class="border-b border-slate-400 pb-1 mb-2"></div>
          <p class="font-bold text-slate-800">Átadó (HGA Biomed Kft.)</p>
        </div>
        <div>
          <div class="border-b border-slate-400 pb-1 mb-2"></div>
          <p class="font-bold text-slate-800">Átvevő (Mosoda)</p>
        </div>
      </div>
    </div>

    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100 print:hidden">
      <button id="close-print-modal-btn" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">Bezárás</button>
      <button onclick="window.print()" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold rounded-xl shadow-md flex items-center space-x-1.5">
        <i data-lucide="printer" class="w-4 h-4"></i>
        <span>Jegyzék Nyomtatása</span>
      </button>
    </div>
  </div>
</div>

<script src="js/audio.js"></script>
<script>
let currentMode = 'OUT';
let currentBatch = null;
let sessionItems = [];
let availableClothesCache = [];

const barcodeInput = document.getElementById('barcode-input');
const modeOutBtn = document.getElementById('scan-mode-out');
const modeInBtn = document.getElementById('scan-mode-in');
const badgeOut = document.getElementById('badge-mode-out');
const badgeIn = document.getElementById('badge-mode-in');
const sessionCountEl = document.getElementById('session-count');
const batchNumberEl = document.getElementById('current-batch-number');
const sessionTableBody = document.getElementById('session-items-table');
const categorySummaryEl = document.getElementById('batch-category-summary');
const feedbackEl = document.getElementById('scan-feedback');

// 0. Nyitott (folyamatban lévő) csomag betöltése az adatbázisból
async function loadActiveBatch() {
  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'get_current_batch',
        direction: currentMode,
        location_id: '<?php echo getActiveLocationId(); ?>'
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
        color: i.color,
        size: i.size,
        employee_name: i.employee_name,
        location_short: i.location_short,
        status: (currentMode === 'OUT') ? 'Mosásba küldve' : 'Visszavéve'
      }));
    } else {
      currentBatch = null;
      sessionItems = [];
    }
    updateSessionTable();
  } catch (e) {
    console.warn('Aktív csomag lekérési hiba:', e);
  }
}

// 1. Módváltás
modeOutBtn.addEventListener('click', () => {
  currentMode = 'OUT';
  modeOutBtn.className = 'p-5 rounded-2xl border-3 border-brand-600 bg-brand-50/80 shadow-md text-left transition-all relative overflow-hidden group';
  modeInBtn.className = 'p-5 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 shadow-xs text-left transition-all relative overflow-hidden group';
  badgeOut.classList.remove('hidden');
  badgeIn.classList.add('hidden');
  loadActiveBatch();
  barcodeInput.focus();
});

modeInBtn.addEventListener('click', () => {
  currentMode = 'IN';
  modeInBtn.className = 'p-5 rounded-2xl border-3 border-blue-600 bg-blue-50/80 shadow-md text-left transition-all relative overflow-hidden group';
  modeOutBtn.className = 'p-5 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 shadow-xs text-left transition-all relative overflow-hidden group';
  badgeIn.classList.remove('hidden');
  badgeOut.classList.add('hidden');
  loadActiveBatch();
  barcodeInput.focus();
});

// Oldal betöltésekor automatikus betöltés
loadActiveBatch();

// 2. Vonalkód olvasás
barcodeInput.addEventListener('keydown', async (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const code = barcodeInput.value.trim();
    if (!code) return;
    barcodeInput.value = '';
    await processScan(code);
  }
});

async function processScan(barcode) {
  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'scan',
        barcode: barcode,
        direction: currentMode,
        batch_id: currentBatch ? currentBatch.id : null,
        location_id: '<?php echo getActiveLocationId(); ?>'
      })
    });
    const data = await res.json();

    if (data.sound === 'success') { try { if (window.SoundEffects) SoundEffects.playSuccess(); } catch(e){} }
    else if (data.sound === 'warning') { try { if (window.SoundEffects) SoundEffects.playWarning(); } catch(e){} }
    else if (data.sound === 'error') { try { if (window.SoundEffects) SoundEffects.playError(); } catch(e){} }

    showFeedback(data.message, data.success ? 'success' : (data.already_scanned ? 'warning' : 'error'));

    if (data.success && data.cloth) {
      currentBatch = data.batch;
      sessionItems.unshift({
        cloth_id: data.cloth.id,
        scanned_at: new Date().toLocaleTimeString('hu-HU'),
        barcode: data.cloth.barcode,
        cloth_name: data.cloth.name,
        category: data.cloth.category,
        color: data.cloth.color,
        size: data.cloth.size,
        employee_name: data.cloth.employee_name,
        location_short: data.cloth.location_short,
        status: (currentMode === 'OUT') ? 'Mosásba küldve' : 'Visszavéve'
      });
      updateSessionTable();
    }
  } catch (err) {
    try { if (window.SoundEffects) SoundEffects.playError(); } catch(e){}
    showFeedback('Hálózati hiba a mentés során!', 'error');
  }
  barcodeInput.focus();
}

function updateSessionTable() {
  sessionCountEl.textContent = `${sessionItems.length} db`;
  batchNumberEl.textContent = currentBatch ? currentBatch.batch_number : '';

  if (sessionItems.length === 0) {
    sessionTableBody.innerHTML = '<tr><td colspan="9" class="px-6 py-8 text-center text-slate-400">Még nincs beolvasott ruha ebben a munkamenetben. Használja a vonalkód olvasót vagy a kézi kiválasztót!</td></tr>';
    categorySummaryEl.innerHTML = '';
    return;
  }

  const cats = {};
  sessionItems.forEach(i => {
    cats[i.category] = (cats[i.category] || 0) + 1;
  });

  categorySummaryEl.innerHTML = Object.entries(cats).map(([cat, cnt]) => `
    <span class="px-2.5 py-1 bg-slate-100 rounded-lg border border-slate-200">
      ${cat}: <strong class="text-slate-900">${cnt} db</strong>
    </span>
  `).join('');

  sessionTableBody.innerHTML = sessionItems.map(i => `
    <tr class="hover:bg-slate-50">
      <td class="px-6 py-3 font-mono text-xs text-slate-500">${i.scanned_at}</td>
      <td class="px-6 py-3 font-mono font-bold text-slate-900">${i.barcode}</td>
      <td class="px-6 py-3 font-medium text-slate-800">${i.cloth_name}</td>
      <td class="px-6 py-3 text-slate-600">${i.category} / ${i.color}</td>
      <td class="px-6 py-3 font-mono text-slate-600">${i.size || '-'}</td>
      <td class="px-6 py-3 font-medium text-slate-900">${i.employee_name || 'Tartalék'}</td>
      <td class="px-6 py-3 text-slate-600">${i.location_short || '-'}</td>
      <td class="px-6 py-3">
        <span class="px-2 py-0.5 text-xs font-bold rounded-full ${currentMode === 'OUT' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">
          ${i.status}
        </span>
      </td>
      <td class="px-6 py-3 text-right">
        <button onclick="removeItemFromBatch(${i.cloth_id})" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Eltávolítás ebből a csomagból">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
      </td>
    </tr>
  `).join('');

  if (window.lucide) lucide.createIcons();
}

// Egyedi tétel törlése a csomagból
async function removeItemFromBatch(clothId) {
  if (!currentBatch) return;
  if (!confirm('Biztosan eltávolítja ezt a ruhát az aktuális csomagból? A ruha státusza visszaáll.')) return;

  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'remove_item_from_batch',
        cloth_id: clothId,
        batch_id: currentBatch.id
      })
    });
    const data = await res.json();
    if (data.success) {
      currentBatch = data.batch;
      sessionItems = (data.items || []).map(i => ({
        cloth_id: i.cloth_id,
        scanned_at: new Date(i.scanned_at).toLocaleTimeString('hu-HU'),
        barcode: i.barcode,
        cloth_name: i.cloth_name,
        category: i.category,
        color: i.color,
        size: i.size,
        employee_name: i.employee_name,
        location_short: i.location_short,
        status: (currentMode === 'OUT') ? 'Mosásba küldve' : 'Visszavéve'
      }));
      updateSessionTable();
      showFeedback(data.message, 'warning');
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch (err) {
    alert('Hálózati hiba: ' + err.message);
  }
}

// Teljes folyamatban lévő csomag törlése
document.getElementById('btnCancelBatch').addEventListener('click', async () => {
  if (!currentBatch || sessionItems.length === 0) {
    alert('Nincs folyamatban lévő csomag.');
    return;
  }
  if (!confirm('FIGYELEM! Biztosan törli a teljes folyamatban lévő csomagot? Minden tétel státusza visszaáll az eredeti állapotra.')) {
    return;
  }

  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'cancel_batch',
        batch_id: currentBatch.id
      })
    });
    const data = await res.json();
    if (data.success) {
      sessionItems = [];
      currentBatch = null;
      updateSessionTable();
      showFeedback(data.message, 'warning');
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch (err) {
    alert('Hálózati hiba: ' + err.message);
  }
});

function showFeedback(msg, type) {
  feedbackEl.classList.remove('hidden', 'bg-emerald-100', 'text-emerald-900', 'bg-amber-100', 'text-amber-900', 'bg-red-100', 'text-red-900');
  if (type === 'success') feedbackEl.classList.add('bg-emerald-100', 'text-emerald-900');
  else if (type === 'warning') feedbackEl.classList.add('bg-amber-100', 'text-amber-900');
  else feedbackEl.classList.add('bg-red-100', 'text-red-900');

  feedbackEl.textContent = msg;
  setTimeout(() => feedbackEl.classList.add('hidden'), 4000);
}

// 3. KÉZI RUHA KIVÁLASZTÓ MODÁL LOGIKA
const manualModal = document.getElementById('manual-select-modal');
const btnOpenManualModal = document.getElementById('btnOpenManualSelectModal');
const btnCloseManualModal = document.getElementById('btnCloseManualModal');
const btnCancelManualSelect = document.getElementById('btnCancelManualSelect');
const manualSearchInput = document.getElementById('manual-search-input');
const manualCategoryFilter = document.getElementById('manual-category-filter');
const manualTableBody = document.getElementById('manual-clothes-table-body');
const manualSelectAll = document.getElementById('manual-select-all');
const manualSelectedCount = document.getElementById('manual-selected-count');
const btnAddSelectedToBatch = document.getElementById('btnAddSelectedToBatch');

btnOpenManualModal.addEventListener('click', async () => {
  manualModal.classList.remove('hidden');
  document.getElementById('manual-modal-direction-hint').textContent = (currentMode === 'OUT') 
    ? 'Jelölje be a MOSODÁBA KÜLDENDŐ (szennyes) munkaruhákat' 
    : 'Jelölje be a MOSODÁBÓL VISSZAVÉTELREZENDŐ (tiszta) munkaruhákat';
  await loadAvailableClothes();
  manualSearchInput.focus();
});

[btnCloseManualModal, btnCancelManualSelect].forEach(btn => {
  btn.addEventListener('click', () => manualModal.classList.add('hidden'));
});

async function loadAvailableClothes() {
  manualTableBody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400">Ruhák betöltése...</td></tr>';
  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'get_available_clothes',
        direction: currentMode,
        location_id: '<?php echo getActiveLocationId(); ?>',
        search: manualSearchInput.value.trim(),
        category: manualCategoryFilter.value
      })
    });
    const data = await res.json();
    availableClothesCache = data.clothes || [];
    renderManualTable();
  } catch (err) {
    manualTableBody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-red-500">Hiba a ruhák betöltésekor.</td></tr>';
  }
}

function renderManualTable() {
  if (availableClothesCache.length === 0) {
    manualTableBody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400">Nincs a szűrésnek megfelelő munkaruha ebben a státuszban.</td></tr>';
    updateManualSelectedCount();
    return;
  }

  manualTableBody.innerHTML = availableClothesCache.map(c => `
    <tr class="hover:bg-slate-50 cursor-pointer" onclick="toggleClothCheckbox(${c.id}, event)">
      <td class="py-2.5 px-3 text-center" onclick="event.stopPropagation()">
        <input type="checkbox" value="${c.id}" class="manual-cloth-cb w-4 h-4 text-brand-600 rounded border-slate-300">
      </td>
      <td class="py-2.5 px-3 font-medium text-slate-900">
        <div>${c.employee_name || 'Tartalék'}</div>
        <div class="text-[10px] text-slate-400 font-mono">${c.employee_code || c.item_code || ''}</div>
      </td>
      <td class="py-2.5 px-3 text-slate-800 font-medium">${c.name}</td>
      <td class="py-2.5 px-3 text-slate-600">${c.category} / ${c.color || '-'}</td>
      <td class="py-2.5 px-3 font-mono text-slate-700">${c.size || '-'}</td>
      <td class="py-2.5 px-3 font-mono text-slate-500">${c.barcode}</td>
      <td class="py-2.5 px-3 text-slate-600">${c.location_short || '-'}</td>
    </tr>
  `).join('');

  document.querySelectorAll('.manual-cloth-cb').forEach(cb => {
    cb.addEventListener('change', updateManualSelectedCount);
  });
  updateManualSelectedCount();
}

function toggleClothCheckbox(id, event) {
  const cb = document.querySelector(`.manual-cloth-cb[value="${id}"]`);
  if (cb) {
    cb.checked = !cb.checked;
    updateManualSelectedCount();
  }
}

manualSelectAll.addEventListener('change', () => {
  const checked = manualSelectAll.checked;
  document.querySelectorAll('.manual-cloth-cb').forEach(cb => cb.checked = checked);
  updateManualSelectedCount();
});

function updateManualSelectedCount() {
  const count = document.querySelectorAll('.manual-cloth-cb:checked').length;
  manualSelectedCount.textContent = count;
}

manualSearchInput.addEventListener('input', () => {
  clearTimeout(window._searchTimer);
  window._searchTimer = setTimeout(loadAvailableClothes, 250);
});

manualCategoryFilter.addEventListener('change', loadAvailableClothes);

// Kijelöltek hozzáadása a csomaghoz
btnAddSelectedToBatch.addEventListener('click', async () => {
  const selectedCbs = document.querySelectorAll('.manual-cloth-cb:checked');
  const ids = Array.from(selectedCbs).map(cb => parseInt(cb.value));

  if (ids.length === 0) {
    alert('Kérjük jelöljön ki legalább egy munkaruhát!');
    return;
  }

  btnAddSelectedToBatch.disabled = true;
  btnAddSelectedToBatch.innerHTML = '<span class="animate-spin">⏳</span> Hozzáadás folyamatban...';

  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'manual_add_items',
        cloth_ids: ids,
        direction: currentMode,
        batch_id: currentBatch ? currentBatch.id : null,
        location_id: '<?php echo getActiveLocationId(); ?>'
      })
    });
    const data = await res.json();

    if (data.success) {
      try { if (window.SoundEffects) SoundEffects.playSuccess(); } catch(e){}
      currentBatch = data.batch;
      sessionItems = (data.items || []).map(i => ({
        cloth_id: i.cloth_id,
        scanned_at: new Date(i.scanned_at).toLocaleTimeString('hu-HU'),
        barcode: i.barcode,
        cloth_name: i.cloth_name,
        category: i.category,
        color: i.color,
        size: i.size,
        employee_name: i.employee_name,
        location_short: i.location_short,
        status: (currentMode === 'OUT') ? 'Mosásba küldve' : 'Visszavéve'
      }));
      updateSessionTable();
      showFeedback(data.message, 'success');
      manualModal.classList.add('hidden');
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch (err) {
    alert('Hálózati hiba a hozzáadás során: ' + err.message);
  }

  btnAddSelectedToBatch.disabled = false;
  btnAddSelectedToBatch.innerHTML = '<i data-lucide="plus-circle" class="w-4 h-4 mr-1.5"></i><span>Kijelöltek Hozzáadása a Csomaghoz</span>';
  if (window.lucide) lucide.createIcons();
});

// 4. Csomag lezárása & Szállítólevél
document.getElementById('finish-batch-btn').addEventListener('click', async () => {
  if (!currentBatch || sessionItems.length === 0) {
    alert('Nincs lezárandó csomag! Előbb adjon hozzá munkaruhákat.');
    return;
  }

  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'finish_batch',
        batch_id: currentBatch.id,
        notes: ''
      })
    });
    const data = await res.json();

    if (data.success) {
      showDeliveryNote(data.batch, data.items);
      sessionItems = [];
      currentBatch = null;
      updateSessionTable();
    }
  } catch (err) {
    alert('Hiba a lezárás során: ' + err.message);
  }
});

function showDeliveryNote(batch, items) {
  document.getElementById('print-batch-number').textContent = batch.batch_number;
  document.getElementById('print-batch-date').textContent = new Date(batch.created_at).toLocaleString('hu-HU');
  document.getElementById('print-location-name').textContent = batch.location_name || 'HGA Biomed';
  document.getElementById('print-location-address').textContent = batch.location_address || '';
  document.getElementById('print-user-name').textContent = batch.user_full_name || 'Kezelő';
  document.getElementById('print-direction-label').textContent = (batch.direction === 'OUT') ? 'KIADÁS MOSODÁBA' : 'BEVÉTEL MOSÁSBÓL';
  document.getElementById('print-total-count').textContent = `${items.length} db`;

  const cats = {};
  items.forEach(i => {
    cats[i.category] = (cats[i.category] || 0) + 1;
  });

  document.getElementById('print-category-breakdown').innerHTML = Object.entries(cats).map(([c, n]) => `
    <span class="px-2 py-0.5 bg-slate-100 border border-slate-300 rounded font-semibold text-[11px]">${c}: <b>${n} db</b></span>
  `).join('');

  document.getElementById('print-items-body').innerHTML = items.map((i, idx) => `
    <tr>
      <td class="py-1.5 px-3 text-slate-500">${idx + 1}.</td>
      <td class="py-1.5 px-3 font-bold">${i.barcode}</td>
      <td class="py-1.5 px-3 font-sans">${i.cloth_name} (${i.category} / ${i.color || '-'})</td>
      <td class="py-1.5 px-3 font-sans">${i.size || '-'}</td>
      <td class="py-1.5 px-3 font-sans font-medium">${i.employee_name || 'Tartalék'}</td>
    </tr>
  `).join('');

  document.getElementById('batch-modal').classList.remove('hidden');
  if (window.lucide) lucide.createIcons();
}

document.getElementById('close-print-modal-btn').addEventListener('click', () => {
  document.getElementById('batch-modal').classList.add('hidden');
});

// KAMERÁS VONALKÓD OLVASÓ (Html5Qrcode)
let html5QrCodeScanner = null;
let isCamActive = false;

const cameraBtn = document.getElementById('camera-scan-btn');
const cameraContainer = document.getElementById('camera-container');
const closeCamBtn = document.getElementById('close-camera-btn');

if (cameraBtn) {
  cameraBtn.addEventListener('click', async () => {
    if (isCamActive) {
      await stopCamera();
      return;
    }

    cameraContainer.classList.remove('hidden');
    cameraBtn.classList.add('bg-brand-600', 'text-white');
    cameraBtn.classList.remove('bg-slate-100', 'text-slate-600');

    html5QrCodeScanner = new Html5Qrcode("qr-reader");
    const config = { fps: 15, qrbox: { width: 280, height: 180 }, aspectRatio: 1.777778 };

    try {
      await html5QrCodeScanner.start(
        { facingMode: "environment" },
        config,
        async (decodedText) => {
          if (decodedText) {
            await processScan(decodedText.trim());
          }
        },
        (err) => {}
      );
      isCamActive = true;
    } catch (e) {
      alert('Nem sikerült elindítani a kamerát: ' + e);
      stopCamera();
    }
  });
}

if (closeCamBtn) {
  closeCamBtn.addEventListener('click', stopCamera);
}

async function stopCamera() {
  if (html5QrCodeScanner && isCamActive) {
    try {
      await html5QrCodeScanner.stop();
      html5QrCodeScanner.clear();
    } catch(e){}
    isCamActive = false;
  }
  if (cameraContainer) cameraContainer.classList.add('hidden');
  if (cameraBtn) {
    cameraBtn.classList.remove('bg-brand-600', 'text-white');
    cameraBtn.classList.add('bg-slate-100', 'text-slate-600');
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
