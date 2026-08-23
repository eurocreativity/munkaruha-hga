<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Settings.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak adminisztrátorok végezhetnek frissítést és mentést!');
    redirect('dashboard.php');
}

$settingsObj = new Settings();
$repo = $settingsObj->get('github_repo', 'eurocreativity/munkaruha-hga');
$localCommit = getAppVersion();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-8 pb-12">
  
  <!-- 1. GITHUB RENDSZERFRISSÍTÉS -->
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center space-x-3">
        <div class="p-3 bg-brand-50 text-brand-600 rounded-xl"><i data-lucide="refresh-cw" class="w-6 h-6"></i></div>
        <div>
          <h2 class="text-xl font-bold text-slate-900">Rendszerfrissítés és Verziókezelés</h2>
          <p class="text-xs text-slate-500">Automatikus frissítés GitHub repóból (<code><?php echo escape($repo); ?></code>)</p>
        </div>
      </div>
      <span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-mono font-bold rounded-lg">Aktuális Verzió: <?php echo escape($localCommit); ?></span>
    </div>

    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h4 class="font-bold text-slate-800 text-sm">Frissítések Ellenőrzése</h4>
        <p class="text-xs text-slate-500 mt-0.5">Lekéri a legújabb elérhető verziót a GitHub szerverről</p>
      </div>
      <button id="btnCheckUpdate" class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center justify-center space-x-2">
        <i data-lucide="cloud-download" class="w-4 h-4"></i>
        <span>Ellenőrzés Most</span>
      </button>
    </div>

    <div id="update-status-box" class="hidden p-5 rounded-2xl border text-sm space-y-4"></div>
  </div>

  <!-- 2. ÚJ MENTÉS KÉSZÍTÉSE -->
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">
    <div class="flex items-center space-x-3 pb-4 border-b border-slate-100">
      <div class="p-3 bg-blue-50 text-blue-600 rounded-xl"><i data-lucide="shield-check" class="w-6 h-6"></i></div>
      <div>
        <h3 class="text-xl font-bold text-slate-900">Biztonsági Mentések Létrehozása</h3>
        <p class="text-xs text-slate-500">Készítsen teljes rendszermentést vagy csak adatbázis mentést</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Csak Adatbázis Mentés -->
      <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col justify-between space-y-4">
        <div>
          <div class="flex items-center space-x-2 text-slate-900 font-bold mb-1">
            <i data-lucide="database" class="w-5 h-5 text-brand-600"></i>
            <span>Csak Adatbázis Mentés (.sql)</span>
          </div>
          <p class="text-xs text-slate-500 leading-relaxed">
            Kimenti az összes táblát (munkaruhák, leltár, dolgozók, telephelyek, mozgások, felhasználók).
            Bármikor egy kattintással visszaállítható.
          </p>
        </div>
        <button id="btnCreateDbBackup" class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs rounded-xl transition-all flex items-center justify-center space-x-2 shadow-xs">
          <i data-lucide="save" class="w-4 h-4"></i>
          <span>Adatbázis Mentés Készítése (.sql)</span>
        </button>
      </div>

      <!-- Teljes Rendszermentés -->
      <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col justify-between space-y-4">
        <div>
          <div class="flex items-center space-x-2 text-slate-900 font-bold mb-1">
            <i data-lucide="archive" class="w-5 h-5 text-blue-600"></i>
            <span>Teljes Rendszermentés (.zip)</span>
          </div>
          <p class="text-xs text-slate-500 leading-relaxed">
            Kimenti a teljes forráskódot, a beállításokat és az adatbázis tartalmát egyetlen ZIP csomagba.
          </p>
        </div>
        <button id="btnCreateFullBackup" class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition-all flex items-center justify-center space-x-2 shadow-xs">
          <i data-lucide="package-check" class="w-4 h-4"></i>
          <span>Teljes Rendszermentés Készítése (.zip)</span>
        </button>
      </div>
    </div>
  </div>

  <!-- 3. MENTÉSI ARCHÍVUM & VISSZAÁLLÍTÁS -->
  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h3 class="text-lg font-bold text-slate-900">Mentési Archívum (Szerveren tárolt mentések)</h3>
        <p class="text-xs text-slate-500">Időrendi sorrendben a legfrissebbtől a régebbiek felé</p>
      </div>
      <button id="btnRefreshBackupsList" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all flex items-center space-x-1">
        <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i>
        <span>Lista Frissítése</span>
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold text-left">
          <tr>
            <th class="px-6 py-3.5">Mentés Dátuma / Időpontja</th>
            <th class="px-6 py-3.5">Fájlnév</th>
            <th class="px-6 py-3.5">Típus</th>
            <th class="px-6 py-3.5">Méret</th>
            <th class="px-6 py-3.5 text-right">Műveletek</th>
          </tr>
        </thead>
        <tbody id="backups-table-body" class="divide-y divide-slate-100 bg-white">
          <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">Mentések betöltése...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 4. KÜLSŐ SQL MENTÉS VISSZAÁLLÍTÁSA -->
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs space-y-4">
    <div class="flex items-center space-x-3 pb-3 border-b border-slate-100">
      <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl"><i data-lucide="upload-cloud" class="w-5 h-5"></i></div>
      <div>
        <h4 class="font-bold text-slate-900 text-base">Külső Adatbázis Mentés (.sql) Feltöltése és Visszaállítása</h4>
        <p class="text-xs text-slate-500">Ha a saját számítógépedről szeretnél egy korábban letöltött .sql mentést betölteni</p>
      </div>
    </div>

    <form id="formUploadRestore" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
      <input type="file" id="sql-upload-file" accept=".sql" required
        class="flex-1 text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
      <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center justify-center space-x-2 whitespace-nowrap">
        <i data-lucide="arrow-up-circle" class="w-4 h-4"></i>
        <span>Feltöltés & Visszaállítás</span>
      </button>
    </form>
  </div>

</div>

<script>
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

// 1. Frissítések ellenőrzése
const btnCheck = document.getElementById('btnCheckUpdate');
const statusBox = document.getElementById('update-status-box');

btnCheck.addEventListener('click', async () => {
  btnCheck.disabled = true;
  btnCheck.innerHTML = '<span class="animate-spin">⏳</span> Ellenőrzés...';

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=check_update&csrf_token=${CSRF_TOKEN}`
    });
    const data = await res.json();

    statusBox.classList.remove('hidden');

    if (data.update_available) {
      statusBox.className = 'p-5 rounded-2xl border bg-blue-50 border-blue-200 text-blue-900 text-sm space-y-3';
      statusBox.innerHTML = `
        <div class="font-bold flex items-center space-x-2">
          <span>🚀 Új verzió érhető el a GitHubon: <code>${data.remote_commit.substring(0, 8)}</code></span>
        </div>
        <p class="text-xs text-blue-800">Frissítési megjegyzés: <b>${data.commit_message || ''}</b></p>
        <p class="text-xs text-slate-500">A frissítés indításakor a rendszer automatikusan biztonsági mentést készít az adatbázisról.</p>
        <button id="btnInstallUpdate" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all flex items-center space-x-2">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span>Frissítés Telepítése Most</span>
        </button>
      `;
      document.getElementById('btnInstallUpdate').addEventListener('click', () => runInstallUpdate(data.remote_commit));
    } else {
      statusBox.className = 'p-5 rounded-2xl border bg-emerald-50 border-emerald-200 text-emerald-900 text-sm';
      statusBox.innerHTML = `✓ A rendszer naprakész! A legfrissebb verzió fut (${data.local_commit.substring(0, 8)}).`;
    }
  } catch (err) {
    statusBox.className = 'p-5 rounded-2xl border bg-red-50 border-red-200 text-red-900 text-sm';
    statusBox.innerHTML = `❌ Hiba a frissítések lekérésekor: ${err.message}`;
  }

  btnCheck.disabled = false;
  btnCheck.innerHTML = '<span>Ellenőrzés Most</span>';
  if (window.lucide) lucide.createIcons();
});

async function runInstallUpdate(remoteCommit) {
  const btn = document.getElementById('btnInstallUpdate');
  btn.disabled = true;
  btn.textContent = 'Frissítés letöltése és telepítése...';

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=apply_update&remote_commit=${encodeURIComponent(remoteCommit)}&csrf_token=${CSRF_TOKEN}`
    });
    const data = await res.json();
    if (data.success) {
      alert('Sikeres frissítés! Az oldal most újraindul.');
      window.location.reload();
    } else {
      alert('Hiba a telepítés során: ' + data.message);
      btn.disabled = false;
      btn.textContent = 'Újrapróbálás';
    }
  } catch (e) {
    alert('Hiba a frissítés során: ' + e.message);
    btn.disabled = false;
    btn.textContent = 'Újrapróbálás';
  }
}

// 2. Mentések listázása
async function loadBackupsList() {
  const tbody = document.getElementById('backups-table-body');
  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=list_backups&csrf_token=${CSRF_TOKEN}`
    });
    const data = await res.json();

    if (!data.success || !data.backups || data.backups.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">Még nem készült mentés a backups mappában.</td></tr>';
      return;
    }

    tbody.innerHTML = data.backups.map(b => `
      <tr class="hover:bg-slate-50">
        <td class="px-6 py-3.5 font-mono text-xs text-slate-600 whitespace-nowrap">
          <div class="font-bold text-slate-900">${b.created_at}</div>
          ${b.is_auto ? '<span class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-sans font-semibold">Auto (Frissítés előtt)</span>' : ''}
        </td>
        <td class="px-6 py-3.5 font-mono text-xs text-slate-800 max-w-xs truncate" title="${b.filename}">
          ${b.filename}
        </td>
        <td class="px-6 py-3.5 text-xs font-semibold">
          <span class="px-2.5 py-1 rounded-full ${b.is_sql ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}">
            ${b.type}
          </span>
        </td>
        <td class="px-6 py-3.5 text-xs font-mono text-slate-500">${b.size}</td>
        <td class="px-6 py-3.5 text-right whitespace-nowrap space-x-2">
          <a href="ajax_update.php?action=download_file&file=${encodeURIComponent(b.filename)}&csrf_token=${CSRF_TOKEN}"
             class="inline-flex items-center px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all" title="Letöltés a számítógépre">
            <i data-lucide="download" class="w-3.5 h-3.5 mr-1"></i> Letöltés
          </a>
          ${b.is_sql ? `
            <button onclick="restoreBackup('${b.filename}')"
                    class="inline-flex items-center px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold rounded-lg transition-all" title="Adatbázis visszaállítása erre az állapotra">
              <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 mr-1"></i> Visszaállítás
            </button>
          ` : ''}
          <button onclick="deleteBackup('${b.filename}')"
                  class="inline-flex items-center p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Törlés">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
          </button>
        </td>
      </tr>
    `).join('');

    if (window.lucide) lucide.createIcons();
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-red-600 text-xs">Hiba a lista lekérésekor: ${err.message}</td></tr>`;
  }
}

// 3. Csak DB mentés létrehozása
document.getElementById('btnCreateDbBackup').addEventListener('click', async () => {
  const btn = document.getElementById('btnCreateDbBackup');
  btn.disabled = true;
  btn.innerHTML = '<span class="animate-spin">⏳</span> Adatbázis mentése...';

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=create_db_backup&csrf_token=${CSRF_TOKEN}`
    });
    const data = await res.json();
    if (data.success) {
      alert(data.message);
      loadBackupsList();
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch (e) {
    alert('Hiba a mentés során: ' + e.message);
  }

  btn.disabled = false;
  btn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i><span>Adatbázis Mentés Készítése (.sql)</span>';
  if (window.lucide) lucide.createIcons();
});

// 4. Teljes mentés létrehozása
document.getElementById('btnCreateFullBackup').addEventListener('click', async () => {
  const btn = document.getElementById('btnCreateFullBackup');
  btn.disabled = true;
  btn.innerHTML = '<span class="animate-spin">⏳</span> Teljes mentés csomagolása...';

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=create_full_backup&csrf_token=${CSRF_TOKEN}`
    });
    const data = await res.json();
    if (data.success) {
      alert(data.message);
      loadBackupsList();
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch (e) {
    alert('Hiba a teljes mentés során: ' + e.message);
  }

  btn.disabled = false;
  btn.innerHTML = '<i data-lucide="package-check" class="w-4 h-4 mr-2"></i><span>Teljes Rendszermentés Készítése (.zip)</span>';
  if (window.lucide) lucide.createIcons();
});

// 5. Visszaállítás szerverfájlból
async function restoreBackup(filename) {
  if (!confirm(`FIGYELEM! Biztosan visszaállítja az adatbázist a kiválasztott mentésből (${filename})?\n\nA jelenlegi adatbázis adatai felül fognak íródni a mentett állapotra!`)) {
    return;
  }

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=restore_db_file&file=${encodeURIComponent(filename)}&csrf_token=${CSRF_TOKEN}`
    });
    const data = await res.json();
    if (data.success) {
      alert(data.message);
      window.location.reload();
    } else {
      alert('Hiba a visszaállítás során: ' + data.message);
    }
  } catch (e) {
    alert('Hiba: ' + e.message);
  }
}

// 6. Mentés törlése
async function deleteBackup(filename) {
  if (!confirm(`Biztosan törölni szeretné ezt a mentést: ${filename}?`)) {
    return;
  }

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=delete_backup_file&file=${encodeURIComponent(filename)}&csrf_token=${CSRF_TOKEN}`
    });
    const data = await res.json();
    if (data.success) {
      loadBackupsList();
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch (e) {
    alert('Hiba: ' + e.message);
  }
}

// 7. Külső SQL feltöltése és visszaállítása
document.getElementById('formUploadRestore').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fileInput = document.getElementById('sql-upload-file');
  if (!fileInput.files || fileInput.files.length === 0) return;

  if (!confirm('FIGYELEM! Biztosan felülírja a jelenlegi adatbázist a feltöltött SQL mentéssel?')) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'upload_and_restore_sql');
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('sql_file', fileInput.files[0]);

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      alert(data.message);
      window.location.reload();
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch (e) {
    alert('Hiba a feltöltés során: ' + e.message);
  }
});

document.getElementById('btnRefreshBackupsList').addEventListener('click', loadBackupsList);

// Inicializálás
loadBackupsList();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
