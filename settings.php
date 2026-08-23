<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Settings.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak adminisztrátorok módosíthatják a beállításokat!');
    redirect('dashboard.php');
}

$settingsObj = new Settings();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $action = $_POST['setting_action'] ?? 'save_settings';

        if ($action === 'delete_logo') {
            $currentLogo = $settingsObj->get('company_logo', '');
            if ($currentLogo && file_exists(__DIR__ . '/' . strtok($currentLogo, '?'))) {
                @unlink(__DIR__ . '/' . strtok($currentLogo, '?'));
            }
            $settingsObj->set('company_logo', '');
            setFlashMessage('success', 'Egyedi logó sikeresen törölve, alapértelmezett logó visszaállítva!');
            redirect('settings.php');
        }

        // Alapbeállítások mentése
        $companyName = trim($_POST['company_name'] ?? 'HGA Biomed Kft.');
        $githubRepo = trim($_POST['github_repo'] ?? 'eurocreativity/munkaruha-hga');
        $githubToken = trim($_POST['github_token'] ?? '');

        $settingsObj->set('company_name', $companyName);
        $settingsObj->set('github_repo', $githubRepo);
        $settingsObj->set('github_token', $githubToken);

        // Logó feltöltés kezelése
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['company_logo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                $filename = 'company_logo.' . $ext;
                $targetPath = $uploadDir . '/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    @chmod($targetPath, 0644);
                    $settingsObj->set('company_logo', 'uploads/' . $filename . '?v=' . time());
                    setFlashMessage('success', 'Beállítások és az új céglogó sikeresen elmentve!');
                } else {
                    setFlashMessage('warning', 'Beállítások elmentve, de a logó feltöltése nem sikerült.');
                }
            } else {
                setFlashMessage('warning', 'Beállítások elmentve, de érvénytelen logó formátum (csak PNG, JPG, SVG, WEBP megengedett)!');
            }
        } else {
            setFlashMessage('success', 'Beállítások sikeresen elmentve!');
        }

        redirect('settings.php');
    }
}

$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');
$githubRepo = $settingsObj->get('github_repo', 'eurocreativity/munkaruha-hga');
$githubToken = $settingsObj->get('github_token', '');
$companyLogo = $settingsObj->get('company_logo', '');
$localCommit = getAppVersion();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
  <!-- Cég és Rendszer Beállítások -->
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">
    <div class="flex items-center space-x-3 pb-4 border-b border-slate-100">
      <div class="p-3 bg-brand-50 text-brand-600 rounded-xl"><i data-lucide="sliders" class="w-6 h-6"></i></div>
      <div>
        <h2 class="text-xl font-bold text-slate-900">Rendszerbeállítások</h2>
        <p class="text-xs text-slate-500">Céglogó, elnevezés és GitHub forráskód-kezelés</p>
      </div>
    </div>

    <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-6 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
      <input type="hidden" name="setting_action" value="save_settings">

      <!-- LOGÓ FELTÖLTÉS SZEKCIÓ -->
      <div class="p-5 bg-slate-50 border border-slate-200 rounded-2xl space-y-4">
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Céglogó Megjelenítése</label>
        
        <div class="flex flex-wrap items-center gap-6">
          <div class="w-36 h-20 bg-white border-2 border-dashed border-slate-300 rounded-xl flex items-center justify-center p-2 overflow-hidden shadow-xs">
            <?php if ($companyLogo && file_exists(__DIR__ . '/' . strtok($companyLogo, '?'))): ?>
              <img src="<?php echo escape($companyLogo); ?>" alt="Céglogó" class="max-h-full max-w-full object-contain">
            <?php else: ?>
              <div class="flex items-center space-x-2 text-slate-400 text-xs">
                <i data-lucide="image" class="w-5 h-5"></i>
                <span>Alapértelmezett</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="flex-1 space-y-2">
            <input type="file" name="company_logo" id="company_logo" accept="image/png, image/jpeg, image/svg+xml, image/webp"
              class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
            <p class="text-[11px] text-slate-400">Ajánlott: átlátszó hátterű PNG vagy SVG (max. 300x100px). Méretarányosan jelenik meg a fejlécben és a szállítóleveleken.</p>
          </div>
        </div>

        <?php if ($companyLogo && file_exists(__DIR__ . '/' . strtok($companyLogo, '?'))): ?>
          <div class="pt-2 border-t border-slate-200 flex justify-end">
            <button type="submit" name="setting_action" value="delete_logo" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-xl text-xs font-semibold flex items-center space-x-1 transition-all">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              <span>Feltöltött logó törlése</span>
            </button>
          </div>
        <?php endif; ?>
      </div>

      <!-- CÉGNÉV -->
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Cégnév / Rendszer Megnevezés</label>
        <input type="text" name="company_name" value="<?php echo escape($companyName); ?>" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 font-medium">
      </div>

      <!-- GITHUB REPO & TOKEN -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">GitHub Repository</label>
          <input type="text" name="github_repo" value="<?php echo escape($githubRepo); ?>" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">GitHub Token (Opcionális)</label>
          <input type="password" name="github_token" value="<?php echo escape($githubToken); ?>" placeholder="ghp_xxxxxxxxxxxx" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs focus:ring-2 focus:ring-brand-500">
        </div>
      </div>

      <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
        <span class="text-xs text-slate-400 font-mono">Telepített verzió: <?php echo escape($localCommit); ?></span>
        <button type="submit" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm transition-all flex items-center space-x-2">
          <i data-lucide="save" class="w-4 h-4"></i>
          <span>Beállítások Mentése</span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
