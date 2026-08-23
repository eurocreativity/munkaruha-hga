<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Settings.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak adminisztrátorok végezhetnek frissítést!');
    redirect('dashboard.php');
}

$settingsObj = new Settings();
$repo = $settingsObj->get('github_repo', 'eurocreativity/munkaruha-hga');
$hasToken = defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN) || !empty($settingsObj->get('github_token', ''));

$localCommit = '1.0.0';
$versionFile = __DIR__ . '/version.txt';
if (file_exists($versionFile)) {
    $localCommit = substr(trim(file_get_contents($versionFile)), 0, 8);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center space-x-3">
        <div class="p-3 bg-brand-50 text-brand-600 rounded-xl"><i data-lucide="refresh-cw" class="w-6 h-6"></i></div>
        <div>
          <h2 class="text-xl font-bold text-slate-900">Rendszerfrissítés és Verziókezelés</h2>
          <p class="text-xs text-slate-500">Automatikus frissítés GitHub repóból (<?php echo escape($repo); ?>)</p>
        </div>
      </div>
      <span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-mono font-bold rounded-lg">Verzió: <?php echo escape($localCommit); ?></span>
    </div>

    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
      <div>
        <h4 class="font-bold text-slate-800 text-sm">Frissítések Ellenőrzése</h4>
        <p class="text-xs text-slate-500 mt-0.5">Lekéri a legújabb elérhető verziót a GitHub szerverről</p>
      </div>
      <button id="btnCheckUpdate" class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center space-x-2">
        <i data-lucide="cloud-download" class="w-4 h-4"></i>
        <span>Ellenőrzés Most</span>
      </button>
    </div>

    <div id="update-status-box" class="hidden p-5 rounded-2xl border text-sm space-y-4"></div>

    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
      <div>
        <h4 class="font-bold text-slate-800 text-sm">Biztonsági Adatbázis Mentés</h4>
        <p class="text-xs text-slate-500 mt-0.5">Manuális SQL mentés letöltése a szerverről</p>
      </div>
      <button id="btnCreateBackup" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all flex items-center space-x-2">
        <i data-lucide="database" class="w-4 h-4"></i>
        <span>Adatbázis Mentés (.sql)</span>
      </button>
    </div>
  </div>
</div>

<script>
const btnCheck = document.getElementById('btnCheckUpdate');
const statusBox = document.getElementById('update-status-box');

btnCheck.addEventListener('click', async () => {
  btnCheck.disabled = true;
  btnCheck.innerHTML = '<span class="animate-spin">⏳</span> Ellenőrzés...';

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=check_update&csrf_token=<?php echo getCsrfToken(); ?>'
    });
    const data = await res.json();

    statusBox.classList.remove('hidden', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-900', 'bg-blue-50', 'border-blue-200', 'text-blue-900', 'bg-red-50', 'border-red-200', 'text-red-900');

    if (data.update_available) {
      statusBox.className = 'p-5 rounded-2xl border bg-blue-50 border-blue-200 text-blue-900 text-sm space-y-3';
      statusBox.innerHTML = `
        <div class="font-bold flex items-center space-x-2">
          <span>🚀 Új verzió érhető el: <code>${data.remote_commit.substring(0, 8)}</code></span>
        </div>
        <p class="text-xs text-blue-700">${data.commit_message || ''}</p>
        <button id="btnInstallUpdate" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm">Frissítés Telepítése Most</button>
      `;
      document.getElementById('btnInstallUpdate').addEventListener('click', runInstallUpdate);
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

async function runInstallUpdate() {
  const btn = document.getElementById('btnInstallUpdate');
  btn.disabled = true;
  btn.textContent = 'Frissítés letöltése és telepítése...';

  try {
    const res = await fetch('ajax_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=apply_update&csrf_token=<?php echo getCsrfToken(); ?>'
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
    alert('Hiba: ' + e.message);
  }
}

document.getElementById('btnCreateBackup').addEventListener('click', async () => {
  window.location.href = 'ajax_update.php?action=download_backup&csrf_token=<?php echo getCsrfToken(); ?>';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
