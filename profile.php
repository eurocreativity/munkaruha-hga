<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

$currentUser = getCurrentUser();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $email = trim($_POST['email'] ?? '');
        $full = trim($_POST['full_name'] ?? '');

        if (!empty($full)) {
            $db->execute("UPDATE users SET full_name = ?, email = ? WHERE id = ?", [$full, $email ?: null, $currentUser['id']]);
            $_SESSION['full_name'] = $full;
            setFlashMessage('success', 'Adatai sikeresen frissítve!');
            redirect('profile.php');
        }
    }
}

$user = $db->fetchOne("
    SELECT u.*, l.name as location_name, l.short_name as location_short
    FROM users u
    LEFT JOIN locations l ON u.default_location_id = l.id
    WHERE u.id = ?
", [$currentUser['id']]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
  <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">
    <div class="flex items-center space-x-3 pb-4 border-b border-slate-100">
      <div class="p-3 bg-brand-50 text-brand-600 rounded-xl"><i data-lucide="user" class="w-6 h-6"></i></div>
      <div>
        <h2 class="text-xl font-bold text-slate-900">Saját Felhasználói Fiók</h2>
        <p class="text-xs text-slate-500">A bejelentkezett felhasználó adatai</p>
      </div>
    </div>

    <form method="POST" action="profile.php" class="space-y-4 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
        <div>
          <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Felhasználónév:</span>
          <p class="font-mono font-bold text-slate-900 text-base"><?php echo escape($user['username']); ?></p>
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Szerepkör:</span>
          <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-slate-200 text-slate-800 uppercase inline-block">
            <?php echo escape($user['role']); ?>
          </span>
        </div>
        <div class="sm:col-span-2">
          <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Alapértelmezett Telephely:</span>
          <p class="font-semibold text-slate-800"><?php echo escape($user['location_short'] ?: ($user['location_name'] ?: 'Minden telephely')); ?></p>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Teljes Név</label>
        <input type="text" name="full_name" value="<?php echo escape($user['full_name']); ?>" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Email Cím (Értesítésekhez & Jelszóvisszaállításhoz)</label>
        <input type="email" name="email" value="<?php echo escape($user['email'] ?? ''); ?>" placeholder="pl. kiss.anna@hgabiomed.hu" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>

      <div class="flex justify-end pt-2">
        <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition-all flex items-center space-x-1.5 shadow-xs">
          <i data-lucide="save" class="w-4 h-4"></i>
          <span>Adatok Mentése</span>
        </button>
      </div>
    </form>

    <!-- JELSZÓMÓDOSÍTÁSI BIZTONSÁGI TÁJÉKOZTATÓ -->
    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start space-x-3 text-xs text-amber-900">
      <i data-lucide="shield-alert" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
      <div class="space-y-1">
        <p class="font-bold">Jelszómódosítási szabályzat</p>
        <p class="leading-relaxed text-amber-800">
          A rendszer biztonsági szabályzata szerint a felhasználói jelszavakat kizárólag a <strong>Rendszergazda (Adminisztrátor)</strong> módosíthatja a Felhasználók menüpontban, vagy az <strong>Elfelejtett jelszó</strong> funkcióval a megadott email címre érkező megerősítő linken keresztül.
        </p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
