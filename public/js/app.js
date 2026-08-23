
// HGA Biomed - Munkaruha Rendszer Fő Alkalmazás Logika
const State = {
  user: null,
  locationId: '',
  currentTab: 'scanner',
  scanMode: 'OUT', // 'OUT' vagy 'IN'
  currentBatch: null,
  sessionItems: [],
  html5QrCode: null,
  categoryChart: null,
  colorChart: null
};

// Inicializálás betöltéskor
document.addEventListener('DOMContentLoaded', async () => {
  initIcons();
  setupEventListeners();

  const token = API.getToken();
  if (token) {
    try {
      const res = await API.getMe();
      if (res && res.user) {
        setLoggedInUser(res.user);
        return;
      }
    } catch (e) {
      console.warn('Session expired:', e);
    }
  }

  showLogin();
});

function initIcons() {
  if (window.lucide) {
    lucide.createIcons();
  }
}

function showLogin() {
  document.getElementById('login-view').classList.remove('hidden');
  document.getElementById('app-container').classList.add('hidden');
}

function setLoggedInUser(user) {
  State.user = user;
  API.setUser(user);

  document.getElementById('login-view').classList.add('hidden');
  document.getElementById('app-container').classList.remove('hidden');

  document.getElementById('user-display-name').textContent = user.full_name;
  const roleNames = { admin: 'Adminisztrátor', operator: 'Operátor (Raktár)', viewer: 'Megtekintő (Vezető)' };
  document.getElementById('user-display-role').textContent = roleNames[user.role] || user.role;

  // Admin fül elrejtése nem-adminoknak
  const adminTab = document.getElementById('admin-nav-tab');
  if (adminTab) {
    adminTab.style.display = (user.role === 'admin') ? 'flex' : 'none';
  }

  // Alapértelmezett telephely beállítása
  if (user.default_location_id) {
    State.locationId = String(user.default_location_id);
    document.getElementById('global-location-select').value = State.locationId;
  }

  switchTab('scanner');
  loadEmployeesForDropdowns();
}

function setupEventListeners() {
  // Bejelentkezés form
  document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errEl = document.getElementById('login-error');
    errEl.classList.add('hidden');

    const u = document.getElementById('login-username').value.trim();
    const p = document.getElementById('login-password').value;

    try {
      const data = await API.login(u, p);
      API.setToken(data.token);
      setLoggedInUser(data.user);
    } catch (err) {
      errEl.textContent = err.message || 'Sikertelen bejelentkezés!';
      errEl.classList.remove('hidden');
    }
  });

  // Kijelentkezés
  document.getElementById('logout-btn').addEventListener('click', () => {
    API.setToken(null);
    API.setUser(null);
    window.location.reload();
  });

  // Globális telephely váltó
  document.getElementById('global-location-select').addEventListener('change', (e) => {
    State.locationId = e.target.value;
    loadTabContent(State.currentTab);
  });

  // Főmenü fülek váltása
  document.querySelectorAll('.nav-tab').forEach(tabBtn => {
    tabBtn.addEventListener('click', () => {
      const tabName = tabBtn.dataset.tab;
      switchTab(tabName);
    });
  });
}

// Tab váltás
function switchTab(tabName) {
  State.currentTab = tabName;

  document.querySelectorAll('.nav-tab').forEach(btn => {
    if (btn.dataset.tab === tabName) {
      btn.className = 'nav-tab flex items-center space-x-2 px-3.5 py-2 rounded-lg text-white bg-brand-600 shadow-sm transition-all whitespace-nowrap font-bold';
    } else {
      btn.className = 'nav-tab flex items-center space-x-2 px-3.5 py-2 rounded-lg hover:text-white hover:bg-slate-800 transition-all whitespace-nowrap text-slate-300';
    }
  });

  document.querySelectorAll('.tab-content').forEach(sec => {
    sec.classList.add('hidden');
  });

  const targetView = document.getElementById(`view-${tabName}`);
  if (targetView) {
    targetView.classList.remove('hidden');
  }

  initIcons();
  loadTabContent(tabName);

  if (tabName === 'scanner') {
    setTimeout(() => {
      const input = document.getElementById('barcode-input');
      if (input) input.focus();
    }, 100);
  }
}

function loadTabContent(tabName) {
  switch (tabName) {
    case 'scanner':
      // Scanner mindig azonnal kész
      break;
    case 'dashboard':
      loadDashboard();
      break;
    case 'clothes':
      loadClothes();
      break;
    case 'employees':
      loadEmployees();
      break;
    case 'batches':
      loadBatches();
      break;
    case 'in-laundry':
      loadInLaundry();
      break;
    case 'admin':
      if (State.user && State.user.role === 'admin') {
        loadUsers();
        loadAuditLogs();
      }
      break;
  }
}

// ==================== 1. GYORS VONALKÓD OLVASÓ MODUL ====================
function setupScannerModule() {
  const barcodeInput = document.getElementById('barcode-input');
  const modeOutBtn = document.getElementById('scan-mode-out');
  const modeInBtn = document.getElementById('scan-mode-in');
  const badgeOut = document.getElementById('badge-mode-out');
  const badgeIn = document.getElementById('badge-mode-in');

  // Módváltás (Mosodába küldés / Kiolvasás vs Visszavétel / Beolvasás)
  modeOutBtn.addEventListener('click', () => {
    State.scanMode = 'OUT';
    modeOutBtn.className = 'p-5 rounded-2xl border-3 border-brand-600 bg-brand-50/80 shadow-md text-left transition-all relative overflow-hidden group';
    modeInBtn.className = 'p-5 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 shadow-xs text-left transition-all relative overflow-hidden group';
    badgeOut.classList.remove('hidden');
    badgeIn.classList.add('hidden');
    barcodeInput.focus();
  });

  modeInBtn.addEventListener('click', () => {
    State.scanMode = 'IN';
    modeInBtn.className = 'p-5 rounded-2xl border-3 border-blue-600 bg-blue-50/80 shadow-md text-left transition-all relative overflow-hidden group';
    modeOutBtn.className = 'p-5 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 shadow-xs text-left transition-all relative overflow-hidden group';
    badgeIn.classList.remove('hidden');
    badgeOut.classList.add('hidden');
    barcodeInput.focus();
  });

  // Vonalkód beolvasás Enter leütésre
  barcodeInput.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const code = barcodeInput.value.trim();
      if (!code) return;
      barcodeInput.value = '';
      await processBarcodeScan(code);
    }
  });

  // Csomag lezárása
  document.getElementById('finish-batch-btn').addEventListener('click', async () => {
    if (!State.currentBatch || State.sessionItems.length === 0) {
      alert('Nincs lezárandó csomag (még nem olvasott be ruhát ebben a munkamenetben)!');
      return;
    }

    try {
      await API.finishBatch(State.currentBatch.id, '');
      showDeliveryNoteModal(State.currentBatch.id);
      State.sessionItems = [];
      State.currentBatch = null;
      updateSessionTable();
    } catch (err) {
      alert('Hiba a lezárás során: ' + err.message);
    }
  });

  // Kamerás szkenner
  const cameraBtn = document.getElementById('camera-scan-btn');
  const closeCamBtn = document.getElementById('close-camera-btn');
  const camContainer = document.getElementById('camera-container');

  cameraBtn.addEventListener('click', () => {
    camContainer.classList.remove('hidden');
    if (!State.html5QrCode && window.Html5Qrcode) {
      State.html5QrCode = new Html5Qrcode('qr-reader');
    }
    if (State.html5QrCode) {
      State.html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
          processBarcodeScan(decodedText);
          if (navigator.vibrate) navigator.vibrate(100);
        },
        (error) => {}
      ).catch(err => {
        alert('Kamera hiba: ' + err);
        camContainer.classList.add('hidden');
      });
    }
  });

  closeCamBtn.addEventListener('click', () => {
    if (State.html5QrCode) {
      State.html5QrCode.stop().then(() => {
        camContainer.classList.add('hidden');
      });
    } else {
      camContainer.classList.add('hidden');
    }
  });
}

// Vonalkód feldolgozás
async function processBarcodeScan(barcode) {
  const feedbackEl = document.getElementById('scan-feedback');
  feedbackEl.classList.remove('hidden', 'bg-emerald-100', 'text-emerald-900', 'border-emerald-300', 'bg-amber-100', 'text-amber-900', 'border-amber-300', 'bg-red-100', 'text-red-900', 'border-red-300');

  try {
    const res = await API.scanLaundry({
      barcode: barcode,
      direction: State.scanMode,
      location_id: State.locationId ? parseInt(State.locationId, 10) : undefined,
      batch_id: State.currentBatch ? State.currentBatch.id : undefined
    });

    if (res.already_scanned) {
      window.sounds.playWarning();
      feedbackEl.className = 'mt-6 p-4 rounded-2xl text-center font-bold text-base bg-amber-100 text-amber-900 border border-amber-300 shadow-sm';
      feedbackEl.innerHTML = `⚠️ ${res.message}`;
      return;
    }

    // Sikeres beolvasás
    window.sounds.playSuccess();
    State.currentBatch = res.batch;
    State.sessionItems.unshift({
      time: new Date().toLocaleTimeString('hu-HU'),
      cloth: res.cloth,
      direction: State.scanMode
    });

    feedbackEl.className = 'mt-6 p-4 rounded-2xl text-center font-bold text-base bg-emerald-100 text-emerald-900 border border-emerald-300 shadow-sm';
    const actionText = State.scanMode === 'OUT' ? 'Mosodába átadva' : 'Mosodából visszavéve';
    feedbackEl.innerHTML = `
      <div class="flex items-center justify-center space-x-3">
        <span class="p-1.5 rounded-full bg-emerald-600 text-white"><i data-lucide="check" class="w-5 h-5"></i></span>
        <span>${res.cloth.name} [${res.cloth.barcode}] &bull; <strong>${res.cloth.employee_name || 'Tartalék'}</strong> &bull; <span class="text-emerald-700 font-extrabold uppercase">${actionText}</span></span>
      </div>
    `;
    initIcons();

    updateSessionTable();
  } catch (err) {
    window.sounds.playError();
    feedbackEl.className = 'mt-6 p-4 rounded-2xl text-center font-bold text-base bg-red-100 text-red-900 border border-red-300 shadow-sm';
    feedbackEl.innerHTML = `❌ ${err.message || 'Ismeretlen hiba beolvasáskor'}`;
  }

  // Fókusz azonnali visszadása
  const input = document.getElementById('barcode-input');
  if (input) input.focus();
}

function updateSessionTable() {
  const tbody = document.getElementById('session-items-table');
  const countEl = document.getElementById('session-count');
  const batchNumEl = document.getElementById('current-batch-number');
  const catSummaryEl = document.getElementById('batch-category-summary');

  countEl.textContent = `${State.sessionItems.length} db`;
  batchNumEl.textContent = State.currentBatch ? State.currentBatch.batch_number : '';

  // Kategória összesítő
  const catCounts = {};
  State.sessionItems.forEach(item => {
    const cat = item.cloth.category || 'Egyéb';
    catCounts[cat] = (catCounts[cat] || 0) + 1;
  });

  catSummaryEl.innerHTML = Object.entries(catCounts).map(([cat, count]) => `
    <span class="px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg">
      ${cat}: <strong class="text-slate-900">${count} db</strong>
    </span>
  `).join('');

  if (State.sessionItems.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" class="px-6 py-8 text-center text-slate-400">
          Még nincs beolvasott ruha ebben a munkamenetben. Használja a vonalkód olvasót!
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = State.sessionItems.map(item => `
    <tr class="hover:bg-slate-50">
      <td class="px-6 py-3 font-mono text-xs text-slate-500">${item.time}</td>
      <td class="px-6 py-3 font-mono font-bold text-slate-900">${item.cloth.barcode}</td>
      <td class="px-6 py-3 font-medium text-slate-900">${item.cloth.name}</td>
      <td class="px-6 py-3 text-slate-600">${item.cloth.category} / ${item.cloth.color || '-'}</td>
      <td class="px-6 py-3 font-mono text-slate-600">${item.cloth.size || '-'}</td>
      <td class="px-6 py-3 font-medium text-slate-900">${item.cloth.employee_name || 'Tartalék'}</td>
      <td class="px-6 py-3 text-slate-600">${item.cloth.location_short || item.cloth.location_name || '-'}</td>
      <td class="px-6 py-3 text-right">
        <span class="px-2.5 py-1 text-xs font-bold rounded-full ${item.direction === 'OUT' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">
          ${item.direction === 'OUT' ? 'Mosásba adva' : 'Visszavéve'}
        </span>
      </td>
    </tr>
  `).join('');
}

// ==================== 2. VEZÉRLŐPULT (DASHBOARD) ====================
async function loadDashboard() {
  try {
    const data = await API.getStats({ location_id: State.locationId });

    document.getElementById('kpi-total').textContent = `${data.totalClothes} db`;
    document.getElementById('kpi-in-laundry').textContent = `${data.inLaundry} db`;
    document.getElementById('kpi-active').textContent = `${data.active} db`;
    document.getElementById('kpi-reserve').textContent = `${data.reserve} db`;
    document.getElementById('kpi-lost').textContent = `${data.lost} db`;
    document.getElementById('kpi-total-value').textContent = `${Math.round(data.totalValue).toLocaleString('hu-HU')} Ft`;

    // Diagramok inicializálása Chart.js-sel
    renderCategoryChart(data.categories);
    renderColorChart(data.colors);

    // Telephelyek kártyák
    const locRes = await API.getLocations();
    const locContainer = document.getElementById('location-summary-cards');
    locContainer.innerHTML = locRes.locations.map(loc => `
      <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
        <div class="flex items-center justify-between font-bold text-sm text-slate-800 mb-1">
          <span>${loc.code}. ${loc.short_name || loc.name}</span>
          <span class="text-brand-600">${loc.total_clothes || 0} db ruha</span>
        </div>
        <div class="grid grid-cols-3 gap-2 text-xs text-slate-500 font-medium">
          <span>Dolgozónál: <strong class="text-emerald-700">${loc.active_clothes || 0}</strong></span>
          <span>Mosásban: <strong class="text-amber-700">${loc.in_laundry_clothes || 0}</strong></span>
          <span>Tartalék: <strong class="text-blue-700">${loc.reserve_clothes || 0}</strong></span>
        </div>
      </div>
    `).join('');

    // Legutóbbi mozgások táblázat
    const recentTbody = document.getElementById('dashboard-recent-table');
    if (data.recentActivity && data.recentActivity.length > 0) {
      recentTbody.innerHTML = data.recentActivity.map(r => `
        <tr class="hover:bg-slate-50">
          <td class="px-6 py-3 font-mono text-xs text-slate-500">${new Date(r.scanned_at).toLocaleString('hu-HU')}</td>
          <td class="px-6 py-3">
            <span class="px-2 py-0.5 text-xs font-bold rounded-full ${r.direction === 'OUT' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">
              ${r.direction === 'OUT' ? 'Mosodába' : 'Mosodából'}
            </span>
          </td>
          <td class="px-6 py-3 font-mono font-bold text-slate-800">${r.barcode}</td>
          <td class="px-6 py-3 font-medium text-slate-900">${r.cloth_name}</td>
          <td class="px-6 py-3 text-slate-700">${r.employee_name || 'Tartalék'}</td>
          <td class="px-6 py-3 text-slate-600">${r.location_short || '-'}</td>
          <td class="px-6 py-3 text-slate-500 text-xs">${r.user_name || '-'}</td>
        </tr>
      `).join('');
    } else {
      recentTbody.innerHTML = `<tr><td colspan="7" class="px-6 py-6 text-center text-slate-400">Még nincs rögzített mosodai mozgás</td></tr>`;
    }
  } catch (err) {
    console.error('Dashboard hiba:', err);
  }
}

function renderCategoryChart(categories) {
  const ctx = document.getElementById('categoryChart');
  if (!ctx) return;

  if (State.categoryChart) State.categoryChart.destroy();

  const labels = (categories || []).map(c => c.category || 'Egyéb');
  const counts = (categories || []).map(c => c.count);

  State.categoryChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: labels.length ? labels : ['Nincs adat'],
      datasets: [{
        data: counts.length ? counts : [1],
        backgroundColor: ['#16a34a', '#2563eb', '#f59e0b', '#8b5cf6', '#64748b']
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
  });
}

function renderColorChart(colors) {
  const ctx = document.getElementById('colorChart');
  if (!ctx) return;

  if (State.colorChart) State.colorChart.destroy();

  const labels = (colors || []).map(c => c.color || 'Egyéb');
  const counts = (colors || []).map(c => c.count);

  State.colorChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: labels.length ? labels : ['Nincs adat'],
      datasets: [{
        data: counts.length ? counts : [1],
        backgroundColor: ['#e2e8f0', '#15803d', '#14532d', '#1d4ed8', '#94a3b8']
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
  });
}

// ==================== 3. MUNKARUHÁK LELTÁRA ====================
async function loadClothes() {
  const search = document.getElementById('clothes-search').value;
  const category = document.getElementById('clothes-filter-category').value;
  const status = document.getElementById('clothes-filter-status').value;
  const color = document.getElementById('clothes-filter-color').value;

  try {
    const res = await API.getClothes({
      location_id: State.locationId,
      search,
      category,
      status,
      color
    });

    document.getElementById('clothes-count-label').textContent = `Összesen: ${res.total} db tétel a szűrésben`;
    const tbody = document.getElementById('clothes-table-body');

    if (res.clothes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Nincs a szűrésnek megfelelő munkaruha.</td></tr>`;
      return;
    }

    const statusBadges = {
      ACTIVE: '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">Aktív (Dolgozónál)</span>',
      IN_LAUNDRY: '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800">Mosásban</span>',
      RESERVE: '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800">Tartalék</span>',
      LOST: '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-red-100 text-red-800">Hiányzó / Elveszett</span>',
      SCRAPPED: '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-slate-200 text-slate-700">Selejtezve</span>'
    };

    tbody.innerHTML = res.clothes.map(c => `
      <tr class="hover:bg-slate-50">
        <td class="px-6 py-3.5 font-mono font-bold text-slate-900">${c.barcode}</td>
        <td class="px-6 py-3.5">
          <div class="font-medium text-slate-900">${c.name}</div>
          <div class="text-xs text-slate-400 font-mono">${c.item_code || '-'}</div>
        </td>
        <td class="px-6 py-3.5 text-slate-600">${c.category || 'Egyéb'}</td>
        <td class="px-6 py-3.5 text-slate-600">
          <span>${c.color || '-'}</span> / <span class="font-mono">${c.size || '-'}</span>
        </td>
        <td class="px-6 py-3.5 font-medium text-slate-900">${c.employee_name || 'Tartalék'}</td>
        <td class="px-6 py-3.5 text-slate-600">${c.location_short || '-'}</td>
        <td class="px-6 py-3.5">${statusBadges[c.status] || c.status}</td>
        <td class="px-6 py-3.5 text-right">
          <button onclick="openEditClothModal(${c.id})" class="p-1.5 text-slate-500 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-all" title="Szerkesztés">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
          </button>
        </td>
      </tr>
    `).join('');

    initIcons();
  } catch (err) {
    console.error('Ruhák betöltési hiba:', err);
  }
}

// ==================== 4. DOLGOZÓK MODUL ====================
async function loadEmployees() {
  const search = document.getElementById('employees-search').value;

  try {
    const res = await API.getEmployees({
      location_id: State.locationId,
      search,
      include_reserve: '1'
    });

    const grid = document.getElementById('employees-grid');
    if (res.employees.length === 0) {
      grid.innerHTML = `<div class="col-span-3 py-12 text-center text-slate-400">Nincs találat a dolgozói keresésre.</div>`;
      return;
    }

    grid.innerHTML = res.employees.map(emp => `
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between hover:border-brand-300 transition-all">
        <div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-mono font-bold px-2 py-0.5 bg-slate-100 text-slate-700 rounded">${emp.employee_code}</span>
            <span class="text-xs font-semibold text-slate-500">${emp.location_short || ''}</span>
          </div>
          <h4 class="font-bold text-slate-900 text-base mb-1">${emp.full_name}</h4>
          <p class="text-xs text-slate-400 mb-4">${emp.locker_number ? `Szekrény: ${emp.locker_number}` : 'Nincs szekrény rendelve'}</p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
          <div class="flex items-center space-x-3">
            <span title="Összes ruha">👕 <strong class="text-slate-800">${emp.total_clothes || 0}</strong> db</span>
            <span title="Mosásban" class="text-amber-700 font-semibold">🌊 ${emp.in_laundry_count || 0}</span>
            <span title="Dolgozónál aktív" class="text-emerald-700 font-semibold">✓ ${emp.active_count || 0}</span>
          </div>
          <button onclick="viewEmployeeClothes(${emp.id})" class="text-brand-600 hover:text-brand-800 font-bold">Ruhák listája &rarr;</button>
        </div>
      </div>
    `).join('');
  } catch (err) {
    console.error('Dolgozók hiba:', err);
  }
}

// ==================== 5. MOSODAI CSOMAGOK & SZÁLLÍTÓLEVELEK ====================
async function loadBatches() {
  const dir = document.getElementById('batches-filter-direction').value;

  try {
    const res = await API.getBatches({
      location_id: State.locationId,
      direction: dir
    });

    const tbody = document.getElementById('batches-table-body');
    if (res.batches.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Még nincs rögzített mosodai csomag / szállítólevél.</td></tr>`;
      return;
    }

    tbody.innerHTML = res.batches.map(b => `
      <tr class="hover:bg-slate-50">
        <td class="px-6 py-3.5 font-mono font-bold text-slate-900">${b.batch_number}</td>
        <td class="px-6 py-3.5">
          <span class="px-2.5 py-1 text-xs font-bold rounded-full ${b.direction === 'OUT' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">
            ${b.direction === 'OUT' ? 'Kiadás (Mosodába)' : 'Bevétel (Mosodából)'}
          </span>
        </td>
        <td class="px-6 py-3.5 text-slate-700">${b.location_short || b.location_name || '-'}</td>
        <td class="px-6 py-3.5 font-bold text-slate-900">${b.item_count} db</td>
        <td class="px-6 py-3.5 text-xs text-slate-500 font-mono">${new Date(b.created_at).toLocaleString('hu-HU')}</td>
        <td class="px-6 py-3.5 text-xs text-slate-600">${b.user_name || '-'}</td>
        <td class="px-6 py-3.5">
          <span class="px-2 py-0.5 text-xs font-semibold rounded ${b.status === 'COMPLETED' ? 'bg-slate-100 text-slate-700' : 'bg-brand-100 text-brand-800'}">
            ${b.status === 'COMPLETED' ? 'Lezárva' : 'Folyamatban'}
          </span>
        </td>
        <td class="px-6 py-3.5 text-right">
          <button onclick="showDeliveryNoteModal(${b.id})" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold flex items-center space-x-1 ml-auto shadow-xs">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
            <span>Átadóív / Nyomtatás</span>
          </button>
        </td>
      </tr>
    `).join('');

    initIcons();
  } catch (err) {
    console.error('Csomagok hiba:', err);
  }
}

// Szállítólevél / Átadóív Modál megjelenítése
async function showDeliveryNoteModal(batchId) {
  try {
    const res = await API.getBatchDetails(batchId);
    const b = res.batch;
    const items = res.items;

    document.getElementById('print-batch-number').textContent = b.batch_number;
    document.getElementById('print-batch-date').textContent = new Date(b.created_at).toLocaleString('hu-HU');
    document.getElementById('print-location-name').textContent = b.location_name || 'HGA Biomed';
    document.getElementById('print-location-address').textContent = b.location_address || '';
    document.getElementById('print-user-name').textContent = b.user_name || '-';
    document.getElementById('print-direction-label').textContent = b.direction === 'OUT' ? 'MOSODÁBA KÜLDVE (Tisztításra átadva)' : 'MOSODÁBÓL VISSZAVÉVE (Tisztítás után átvéve)';
    document.getElementById('print-total-count').textContent = `${items.length} db`;

    // Kategória összesítő
    const catEl = document.getElementById('print-category-breakdown');
    catEl.innerHTML = Object.entries(res.categoryCounts).map(([cat, count]) => `
      <span class="px-2.5 py-1 bg-slate-100 border border-slate-300 rounded font-semibold text-slate-800">
        ${cat}: ${count} db
      </span>
    `).join('');

    // Tételek táblázat
    const tbody = document.getElementById('print-items-tbody');
    tbody.innerHTML = items.map((item, idx) => `
      <tr>
        <td class="py-1.5 px-3 font-mono">${idx + 1}.</td>
        <td class="py-1.5 px-3 font-mono font-bold">${item.barcode}</td>
        <td class="py-1.5 px-3 font-medium">${item.cloth_name} (${item.category} / ${item.color || '-'})</td>
        <td class="py-1.5 px-3 font-mono">${item.size || '-'}</td>
        <td class="py-1.5 px-3">${item.employee_name || 'Tartalék'} ${item.employee_code ? `(${item.employee_code})` : ''}</td>
      </tr>
    `).join('');

    document.getElementById('batch-modal').classList.remove('hidden');
  } catch (err) {
    alert('Hiba a szállítólevél betöltésekor: ' + err.message);
  }
}

// ==================== 6. MOSÁSBAN LÉVŐ RUHÁK (HIÁNYLISTA) ====================
async function loadInLaundry() {
  try {
    const res = await API.getInLaundry({ location_id: State.locationId });
    document.getElementById('in-laundry-badge-count').textContent = `${res.total} db`;

    const tbody = document.getElementById('in-laundry-table-body');
    if (res.inLaundry.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Jelenleg egyetlen ruha sincs mosodában (minden beérkezett).</td></tr>`;
      return;
    }

    tbody.innerHTML = res.inLaundry.map(c => {
      const days = c.days_in_laundry || 0;
      let daysBadge = `<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-slate-100 text-slate-700">${days} napja</span>`;
      if (days > 14) {
        daysBadge = `<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800 animate-pulse">⚠️ ${days} napja (Késik!)</span>`;
      } else if (days > 7) {
        daysBadge = `<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800">${days} napja</span>`;
      }

      return `
        <tr class="hover:bg-slate-50">
          <td class="px-6 py-3.5 font-mono font-bold text-slate-900">${c.barcode}</td>
          <td class="px-6 py-3.5 font-medium text-slate-900">${c.name}</td>
          <td class="px-6 py-3.5 text-slate-600">${c.category} / ${c.color || '-'}</td>
          <td class="px-6 py-3.5 font-mono text-slate-600">${c.size || '-'}</td>
          <td class="px-6 py-3.5 font-medium text-slate-900">${c.employee_name || 'Tartalék'}</td>
          <td class="px-6 py-3.5 text-slate-600">${c.location_short || '-'}</td>
          <td class="px-6 py-3.5 font-mono text-xs text-slate-500">${c.last_sent_to_laundry ? new Date(c.last_sent_to_laundry).toLocaleDateString('hu-HU') : '-'}</td>
          <td class="px-6 py-3.5">${daysBadge}</td>
        </tr>
      `;
    }).join('');
  } catch (err) {
    console.error('Hiánylista hiba:', err);
  }
}

// ==================== 7. CSV IMPORT / EXPORT ====================
function setupImportExport() {
  document.getElementById('download-csv-btn').addEventListener('click', () => {
    const locParam = State.locationId ? `?location_id=${State.locationId}` : '';
    window.location.href = `/api/inventory/export-csv${locParam}`;
  });

  document.getElementById('csv-import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fileInput = document.getElementById('csv-file-input');
    if (!fileInput.files || fileInput.files.length === 0) {
      alert('Válasszon ki egy CSV fájlt!');
      return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    try {
      const res = await API.importCsv(formData);
      alert(res.message);
      fileInput.value = '';
      loadClothes();
    } catch (err) {
      alert('Import hiba: ' + err.message);
    }
  });
}

// ==================== 8. ADMIN & AUDIT ====================
async function loadUsers() {
  try {
    const res = await API.getUsers();
    const tbody = document.getElementById('users-table-body');
    const roleNames = { admin: 'Adminisztrátor', operator: 'Operátor (Raktáros)', viewer: 'Megtekintő (Vezető)' };

    tbody.innerHTML = res.users.map(u => `
      <tr class="hover:bg-slate-50">
        <td class="px-6 py-3 font-mono font-bold text-slate-900">${u.username}</td>
        <td class="px-6 py-3 font-medium text-slate-900">${u.full_name}</td>
        <td class="px-6 py-3"><span class="px-2 py-0.5 text-xs font-bold rounded bg-slate-100 text-slate-800">${roleNames[u.role] || u.role}</span></td>
        <td class="px-6 py-3 text-slate-600">${u.location_short || 'Összes telephely'}</td>
        <td class="px-6 py-3"><span class="text-xs font-bold text-emerald-700">Aktív</span></td>
      </tr>
    `).join('');
  } catch (err) {
    console.error('Felhasználók hiba:', err);
  }
}

async function loadAuditLogs() {
  try {
    const res = await API.getAuditLogs({ limit: 100 });
    const tbody = document.getElementById('audit-table-body');

    tbody.innerHTML = res.logs.map(log => `
      <tr class="hover:bg-slate-50 text-xs">
        <td class="px-6 py-2.5 text-slate-500">${new Date(log.created_at).toLocaleString('hu-HU')}</td>
        <td class="px-6 py-2.5 font-bold text-slate-800">${log.username || '-'}</td>
        <td class="px-6 py-2.5 font-semibold text-brand-700">${log.action}</td>
        <td class="px-6 py-2.5 text-slate-600 font-sans">${log.details || ''}</td>
      </tr>
    `).join('');
  } catch (err) {
    console.error('Audit hiba:', err);
  }
}

// ==================== MODÁLOK ÉS SEGÉDFÜGGVÉNYEK ====================
async function loadEmployeesForDropdowns() {
  try {
    const res = await API.getEmployees({ include_reserve: '1' });
    const clothEmpSelect = document.getElementById('cloth-form-employee');
    clothEmpSelect.innerHTML = '<option value="">-- Nincs hozzárendelve / Tartalék --</option>' +
      res.employees.map(e => `<option value="${e.id}">${e.full_name} (${e.employee_code})</option>`).join('');
  } catch (e) {
    console.warn(e);
  }
}

function setupModals() {
  // Ruha Modál
  document.getElementById('open-new-cloth-modal').addEventListener('click', () => {
    document.getElementById('cloth-modal-title').textContent = 'Új Munkaruha Hozzáadása';
    document.getElementById('cloth-form').reset();
    document.getElementById('cloth-form-id').value = '';
    document.getElementById('cloth-modal').classList.remove('hidden');
  });
  document.getElementById('close-cloth-modal-btn').addEventListener('click', () => document.getElementById('cloth-modal').classList.add('hidden'));
  document.getElementById('cancel-cloth-modal-btn').addEventListener('click', () => document.getElementById('cloth-modal').classList.add('hidden'));

  document.getElementById('cloth-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('cloth-form-id').value;
    const data = {
      barcode: document.getElementById('cloth-form-barcode').value,
      name: document.getElementById('cloth-form-name').value,
      category: document.getElementById('cloth-form-category').value,
      color: document.getElementById('cloth-form-color').value,
      size: document.getElementById('cloth-form-size').value,
      item_code: document.getElementById('cloth-form-item-code').value,
      location_id: parseInt(document.getElementById('cloth-form-location').value, 10),
      status: document.getElementById('cloth-form-status').value,
      employee_id: document.getElementById('cloth-form-employee').value ? parseInt(document.getElementById('cloth-form-employee').value, 10) : null,
      notes: document.getElementById('cloth-form-notes').value
    };

    try {
      if (id) {
        await API.updateCloth(id, data);
      } else {
        await API.createCloth(data);
      }
      document.getElementById('cloth-modal').classList.add('hidden');
      loadClothes();
    } catch (err) {
      alert('Hiba: ' + err.message);
    }
  });

  // Dolgozó Modál
  document.getElementById('open-new-employee-modal').addEventListener('click', () => {
    document.getElementById('employee-form').reset();
    document.getElementById('employee-form-id').value = '';
    document.getElementById('employee-modal').classList.remove('hidden');
  });
  document.getElementById('close-employee-modal-btn').addEventListener('click', () => document.getElementById('employee-modal').classList.add('hidden'));
  document.getElementById('cancel-employee-modal-btn').addEventListener('click', () => document.getElementById('employee-modal').classList.add('hidden'));

  document.getElementById('employee-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      employee_code: document.getElementById('employee-form-code').value,
      last_name: document.getElementById('employee-form-last-name').value,
      first_name: document.getElementById('employee-form-first-name').value,
      location_id: parseInt(document.getElementById('employee-form-location').value, 10),
      locker_number: document.getElementById('employee-form-locker').value
    };

    try {
      await API.createEmployee(data);
      document.getElementById('employee-modal').classList.add('hidden');
      loadEmployees();
      loadEmployeesForDropdowns();
    } catch (err) {
      alert('Hiba: ' + err.message);
    }
  });

  // Szállítólevél bezárás
  document.getElementById('close-batch-modal-btn').addEventListener('click', () => {
    document.getElementById('batch-modal').classList.add('hidden');
  });

  // Felhasználó Modál (Admin)
  const openUserBtn = document.getElementById('open-new-user-modal');
  if (openUserBtn) {
    openUserBtn.addEventListener('click', () => {
      document.getElementById('user-form').reset();
      document.getElementById('user-modal').classList.remove('hidden');
    });
  }
  document.getElementById('close-user-modal-btn').addEventListener('click', () => document.getElementById('user-modal').classList.add('hidden'));
  document.getElementById('cancel-user-modal-btn').addEventListener('click', () => document.getElementById('user-modal').classList.add('hidden'));

  document.getElementById('user-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      username: document.getElementById('user-form-username').value,
      password: document.getElementById('user-form-password').value,
      full_name: document.getElementById('user-form-fullname').value,
      role: document.getElementById('user-form-role').value,
      default_location_id: parseInt(document.getElementById('user-form-location').value, 10)
    };

    try {
      await API.createUser(data);
      document.getElementById('user-modal').classList.add('hidden');
      loadUsers();
    } catch (err) {
      alert('Hiba: ' + err.message);
    }
  });

  // Kereső és szűrő események
  ['clothes-search', 'clothes-filter-category', 'clothes-filter-status', 'clothes-filter-color'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => loadClothes());
  });

  const empSearch = document.getElementById('employees-search');
  if (empSearch) empSearch.addEventListener('input', () => loadEmployees());

  const batchFilter = document.getElementById('batches-filter-direction');
  if (batchFilter) batchFilter.addEventListener('change', () => loadBatches());
}

// Ruha szerkesztése gomb
async function openEditClothModal(id) {
  try {
    const res = await API.getClothes({ limit: 1000 });
    const cloth = res.clothes.find(c => c.id === id);
    if (!cloth) return;

    document.getElementById('cloth-modal-title').textContent = 'Munkaruha Módosítása';
    document.getElementById('cloth-form-id').value = cloth.id;
    document.getElementById('cloth-form-barcode').value = cloth.barcode;
    document.getElementById('cloth-form-name').value = cloth.name;
    document.getElementById('cloth-form-category').value = cloth.category || 'Egyéb';
    document.getElementById('cloth-form-color').value = cloth.color || 'Egyéb';
    document.getElementById('cloth-form-size').value = cloth.size || '';
    document.getElementById('cloth-form-item-code').value = cloth.item_code || '';
    document.getElementById('cloth-form-location').value = String(cloth.location_id || 1);
    document.getElementById('cloth-form-status').value = cloth.status || 'ACTIVE';
    document.getElementById('cloth-form-employee').value = cloth.employee_id ? String(cloth.employee_id) : '';
    document.getElementById('cloth-form-notes').value = cloth.notes || '';

    document.getElementById('cloth-modal').classList.remove('hidden');
  } catch (err) {
    alert('Hiba: ' + err.message);
  }
}

// Dolgozó ruháinak megtekintése szűréssel
function viewEmployeeClothes(employeeId) {
  switchTab('clothes');
  setTimeout(async () => {
    try {
      const res = await API.getEmployee(employeeId);
      document.getElementById('clothes-search').value = res.employee.full_name;
      loadClothes();
    } catch (e) {}
  }, 100);
}

// Modulok feliratkozása induláskor
setupScannerModule();
setupImportExport();
setupModals();
