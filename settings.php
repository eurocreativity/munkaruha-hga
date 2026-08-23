<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Settings.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak adminisztrátorok módosíthatják a beállításokat!');
    redirect('dashboard.php');
}

$settingsObj = new Settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $settingsObj->set('company_name', trim($_POST['company_name'] ?? 'HGA Biomed Kft.'));
        $settingsObj->set('github_repo', trim($_POST['github_repo'] ?? 'eurocreativity/munkaruha-hga'));
        if (isset($_POST['github_token'])) {
            $settingsObj->set('github_token', trim($_POST['github_token']));
        }
        setFlashMessage('success', 'Beállítások sikeresen elmentve!');
        redirect('settings.php');
    }
}

$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');
$githubRepo = $settingsObj->get('github_repo', 'eurocreativity/munkaruha-hga');
$githubToken = $settingsObj->get('github_token', '');
$localCommit = getAppVersion();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
  <!-- Cég és GitHub Beállítások -->
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs">
    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-100">
      <div class="p-3 bg-brand-50 text-brand-600 rounded-xl"><i data-lucide="sliders" class="w-6 h-6"></i></div>
      <div>
        <h2 class="text-xl font-bold text-slate-900">Rendszerbeállítások</h2>
        <p class="text-xs text-slate-500">Cégadatok és GitHub forráskód-kezelés</p>
      </div>
    </div>

    <form method="POST" action="settings.php" class="space-y-4 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Cégnév / Megnevezés</label>
        <input type="text" name="company_name" value="<?php echo escape($companyName); ?>" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">GitHub Repository (Frissítés Forrása)</label>
        <input type="text" name="github_repo" value="<?php echo escape($githubRepo); ?>" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs focus:ring-2 focus:ring-brand-500">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">GitHub Personal Access Token (Opcionális)</label>
        <input type="password" name="github_token" value="<?php echo escape($githubToken); ?>" placeholder="ghp_xxxxxxxxxxxx" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs focus:ring-2 focus:ring-brand-500">
        <span class="text-xs text-slate-400 block mt-1">Privát repó vagy magasabb API limit esetén szükséges.</span>
      </div>

      <div class="pt-4 border-t border-slate-100 flex justify-end">
        <button type="submit" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm">Mentés</button>
      </div>
    </form>
  </div>

  <!-- Git Automata Rendszerfrissítés Kártya -->
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs space-y-5">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
      <div class="flex items-center space-x-3">
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl"><i data-lucide="git-branch" class="w-6 h-6"></i></div>
        <div>
          <h3 class="text-lg font-bold text-slate-900">Git Rendszerfrissítés</h3>
          <p class="text-xs text-slate-500">Közvetlen frissítés a <code><?php echo escape($githubRepo); ?></code> repóból</p>
        </div>
      </div>
      <span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-mono font-bold rounded-lg">Aktív verzió: <?php echo escape($localCommit); ?></span>
    </div>

    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl">
      <div>
        <p class="text-sm font-bold text-slate-800">Frissítések Keresése & Telepítése</p>
        <p class="text-xs text-slate-500 mt-0.5">Ellenőrzi, hogy van-e újabb commit a GitHubon és egy gombnyomásra letölti.</p>
      </div>
      <a href="update.php" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition-all flex items-center space-x-2 shadow-sm whitespace-nowrap">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        <span>Frissítő Megnyitása &rarr;</span>
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
