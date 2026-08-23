<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
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

  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 md:p-8">
    <div class="max-w-2xl mx-auto text-center space-y-4">
      <div class="flex items-center justify-center space-x-2 text-slate-500 text-sm font-semibold">
        <i data-lucide="barcode" class="w-5 h-5 text-brand-600"></i>
        <span>VONALKÓD BEOLVASÁSA (Kézi szkenner vagy billentyűzet)</span>
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

      <div class="flex items-center justify-center space-x-6 text-xs text-slate-400 font-medium pt-1">
        <span class="flex items-center"><i data-lucide="volume-2" class="w-4 h-4 mr-1 text-emerald-500"></i> Hangjelzés aktív</span>
        <span class="flex items-center"><i data-lucide="zap" class="w-4 h-4 mr-1 text-amber-500"></i> Azonnali rögzítés Enter-re</span>
      </div>
    </div>

    <div id="scan-feedback" class="hidden mt-6 p-4 rounded-xl text-center font-bold text-base transition-all duration-300"></div>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="flex items-center space-x-2">
          <h2 class="text-base font-bold text-slate-900">Aktuális Beolvasott Csomag</h2>
          <span id="current-batch-number" class="px-2 py-0.5 text-xs font-mono font-semibold bg-slate-200 text-slate-800 rounded"></span>
        </div>
        <p class="text-xs text-slate-500 mt-0.5">A most beolvasott munkaruhák listája</p>
      </div>

      <div class="flex items-center space-x-3">
        <div class="text-right">
          <span class="text-xs text-slate-500 font-semibold block">Beolvasva:</span>
          <span id="session-count" class="text-2xl font-black text-brand-600">0 db</span>
        </div>
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
            <th class="px-6 py-3 text-right">Státusz</th>
          </tr>
        </thead>
        <tbody id="session-items-table" class="divide-y divide-slate-100 bg-white">
          <tr>
            <td colspan="8" class="px-6 py-8 text-center text-slate-400">
              Még nincs beolvasott ruha ebben a munkamenetben. Használja a vonalkód olvasót!
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

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
            <th class="py-2 px-3">Dolgozó Neve (Törzsszám)</th>
          </tr>
        </thead>
        <tbody id="print-items-tbody" class="divide-y divide-slate-200"></tbody>
      </table>

      <div class="grid grid-cols-2 gap-12 pt-8 text-xs border-t border-slate-200">
        <div class="text-center">
          <div class="border-b border-slate-400 pb-8"></div>
          <p class="mt-2 font-bold text-slate-700">Átadó (HGA Biomed képviselője)</p>
        </div>
        <div class="text-center">
          <div class="border-b border-slate-400 pb-8"></div>
          <p class="mt-2 font-bold text-slate-700">Átvevő (Mosoda képviselője / Futár)</p>
        </div>
      </div>
    </div>

    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
      <button id="close-batch-modal-btn" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl text-sm font-semibold">Bezárás</button>
      <button onclick="window.print()" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl transition-all flex items-center space-x-2 shadow-sm">
        <i data-lucide="printer" class="w-4 h-4"></i>
        <span>Nyomtatás</span>
      </button>
    </div>
  </div>
</div>

<script>
let scanMode = 'OUT';
let currentBatch = null;
let sessionItems = [];
let html5QrCode = null;

const barcodeInput = document.getElementById('barcode-input');
const modeOutBtn = document.getElementById('scan-mode-out');
const modeInBtn = document.getElementById('scan-mode-in');
const badgeOut = document.getElementById('badge-mode-out');
const badgeIn = document.getElementById('badge-mode-in');

modeOutBtn.addEventListener('click', () => {
  scanMode = 'OUT';
  modeOutBtn.className = 'p-5 rounded-2xl border-3 border-brand-600 bg-brand-50/80 shadow-md text-left transition-all relative overflow-hidden group';
  modeInBtn.className = 'p-5 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 shadow-xs text-left transition-all relative overflow-hidden group';
  badgeOut.classList.remove('hidden');
  badgeIn.classList.add('hidden');
  barcodeInput.focus();
});

modeInBtn.addEventListener('click', () => {
  scanMode = 'IN';
  modeInBtn.className = 'p-5 rounded-2xl border-3 border-blue-600 bg-blue-50/80 shadow-md text-left transition-all relative overflow-hidden group';
  modeOutBtn.className = 'p-5 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 shadow-xs text-left transition-all relative overflow-hidden group';
  badgeIn.classList.remove('hidden');
  badgeOut.classList.add('hidden');
  barcodeInput.focus();
});

barcodeInput.addEventListener('keydown', async (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const code = barcodeInput.value.trim();
    if (!code) return;
    barcodeInput.value = '';
    await processBarcode(code);
  }
});

async function processBarcode(barcode) {
  const feedbackEl = document.getElementById('scan-feedback');
  feedbackEl.classList.remove('hidden');

  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'scan',
        barcode: barcode,
        direction: scanMode,
        location_id: '<?php echo $activeLocationId; ?>',
        batch_id: currentBatch ? currentBatch.id : null
      })
    });
    const data = await res.json();

    if (data.already_scanned) {
      window.sounds.playWarning();
      feedbackEl.className = 'mt-6 p-4 rounded-2xl text-center font-bold text-base bg-amber-100 text-amber-900 border border-amber-300 shadow-sm';
      feedbackEl.innerHTML = `⚠️ ${data.message}`;
      barcodeInput.focus();
      return;
    }

    if (!data.success) {
      window.sounds.playError();
      feedbackEl.className = 'mt-6 p-4 rounded-2xl text-center font-bold text-base bg-red-100 text-red-900 border border-red-300 shadow-sm';
      feedbackEl.innerHTML = `❌ ${data.message}`;
      barcodeInput.focus();
      return;
    }

    window.sounds.playSuccess();
    currentBatch = data.batch;
    sessionItems.unshift({
      time: new Date().toLocaleTimeString('hu-HU'),
      cloth: data.cloth,
      direction: scanMode
    });

    feedbackEl.className = 'mt-6 p-4 rounded-2xl text-center font-bold text-base bg-emerald-100 text-emerald-900 border border-emerald-300 shadow-sm';
    const actionTxt = scanMode === 'OUT' ? 'MOSODÁBA ÁTADVA' : 'MOSODÁBÓL VISSZAVÉVE';
    feedbackEl.innerHTML = `
      <div class="flex items-center justify-center space-x-3">
        <span class="p-1.5 rounded-full bg-emerald-600 text-white"><i data-lucide="check" class="w-5 h-5"></i></span>
        <span>${data.cloth.name} [${data.cloth.barcode}] &bull; <strong>${data.cloth.employee_name || 'Tartalék'}</strong> &bull; <span class="text-emerald-700 font-extrabold uppercase">${actionTxt}</span></span>
      </div>
    `;
    if (window.lucide) lucide.createIcons();
    updateSessionUi();
  } catch (err) {
    window.sounds.playError();
    feedbackEl.className = 'mt-6 p-4 rounded-2xl text-center font-bold text-base bg-red-100 text-red-900 border border-red-300 shadow-sm';
    feedbackEl.innerHTML = `❌ Hálózati hiba a beolvasáskor!`;
  }
  barcodeInput.focus();
}

function updateSessionUi() {
  document.getElementById('session-count').textContent = `${sessionItems.length} db`;
  document.getElementById('current-batch-number').textContent = currentBatch ? currentBatch.batch_number : '';

  const catCounts = {};
  sessionItems.forEach(item => {
    const cat = item.cloth.category || 'Egyéb';
    catCounts[cat] = (catCounts[cat] || 0) + 1;
  });

  document.getElementById('batch-category-summary').innerHTML = Object.entries(catCounts).map(([cat, count]) => `
    <span class="px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg">
      ${cat}: <strong class="text-slate-900">${count} db</strong>
    </span>
  `).join('');

  const tbody = document.getElementById('session-items-table');
  if (sessionItems.length === 0) {
    tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Még nincs beolvasott ruha.</td></tr>`;
    return;
  }

  tbody.innerHTML = sessionItems.map(item => `
    <tr class="hover:bg-slate-50">
      <td class="px-6 py-3 font-mono text-xs text-slate-500">${item.time}</td>
      <td class="px-6 py-3 font-mono font-bold text-slate-900">${item.cloth.barcode}</td>
      <td class="px-6 py-3 font-medium text-slate-900">${item.cloth.name}</td>
      <td class="px-6 py-3 text-slate-600">${item.cloth.category} / ${item.cloth.color || '-'}</td>
      <td class="px-6 py-3 font-mono text-slate-600">${item.cloth.size || '-'}</td>
      <td class="px-6 py-3 font-medium text-slate-900">${item.cloth.employee_name || 'Tartalék'}</td>
      <td class="px-6 py-3 text-slate-600">${item.cloth.location_short || '-'}</td>
      <td class="px-6 py-3 text-right">
        <span class="px-2.5 py-1 text-xs font-bold rounded-full ${item.direction === 'OUT' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">
          ${item.direction === 'OUT' ? 'Mosásba adva' : 'Visszavéve'}
        </span>
      </td>
    </tr>
  `).join('');
}

document.getElementById('finish-batch-btn').addEventListener('click', async () => {
  if (!currentBatch || sessionItems.length === 0) {
    alert('Nincs lezárandó csomag!');
    return;
  }
  const res = await fetch('ajax_scanner.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'finish_batch', batch_id: currentBatch.id })
  });
  const data = await res.json();
  if (data.success) {
    openDeliveryModal(currentBatch.id);
    currentBatch = null;
    sessionItems = [];
    updateSessionUi();
  }
});

async function openDeliveryModal(batchId) {
  const res = await fetch('ajax_scanner.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_batch_details', batch_id: batchId })
  });
  const data = await res.json();
  if (!data.success) return;

  const b = data.batch;
  document.getElementById('print-batch-number').textContent = b.batch_number;
  document.getElementById('print-batch-date').textContent = new Date(b.created_at).toLocaleString('hu-HU');
  document.getElementById('print-location-name').textContent = b.location_name || 'HGA Biomed';
  document.getElementById('print-location-address').textContent = b.location_address || '';
  document.getElementById('print-user-name').textContent = b.user_name || '-';
  document.getElementById('print-direction-label').textContent = b.direction === 'OUT' ? 'MOSODÁBA KÜLDVE (Tisztításra átadva)' : 'MOSODÁBÓL VISSZAVÉVE (Átvéve)';
  document.getElementById('print-total-count').textContent = `${data.items.length} db`;

  document.getElementById('print-category-breakdown').innerHTML = Object.entries(data.categoryCounts).map(([cat, c]) => `
    <span class="px-2.5 py-1 bg-slate-100 border border-slate-300 rounded font-semibold text-slate-800">${cat}: ${c} db</span>
  `).join('');

  document.getElementById('print-items-tbody').innerHTML = data.items.map((item, i) => `
    <tr>
      <td class="py-1.5 px-3 font-mono">${i + 1}.</td>
      <td class="py-1.5 px-3 font-mono font-bold">${item.barcode}</td>
      <td class="py-1.5 px-3 font-medium">${item.cloth_name} (${item.category} / ${item.color || '-'})</td>
      <td class="py-1.5 px-3 font-mono">${item.size || '-'}</td>
      <td class="py-1.5 px-3">${item.employee_name || 'Tartalék'} ${item.employee_code ? '(' + item.employee_code + ')' : ''}</td>
    </tr>
  `).join('');

  document.getElementById('batch-modal').classList.remove('hidden');
}

document.getElementById('close-batch-modal-btn').addEventListener('click', () => {
  document.getElementById('batch-modal').classList.add('hidden');
});

document.getElementById('camera-scan-btn').addEventListener('click', () => {
  const container = document.getElementById('camera-container');
  container.classList.remove('hidden');
  if (!html5QrCode && window.Html5Qrcode) {
    html5QrCode = new Html5Qrcode('qr-reader');
  }
  if (html5QrCode) {
    html5QrCode.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 250 } }, (text) => {
      processBarcode(text);
    }).catch(e => {
      alert('Kamera hiba: ' + e);
      container.classList.add('hidden');
    });
  }
});

document.getElementById('close-camera-btn').addEventListener('click', () => {
  if (html5QrCode) {
    html5QrCode.stop().then(() => {
      document.getElementById('camera-container').classList.add('hidden');
    });
  } else {
    document.getElementById('camera-container').classList.add('hidden');
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
