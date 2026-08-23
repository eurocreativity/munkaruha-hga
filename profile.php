<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

$currentUser = getCurrentUser();
$db = Database::getInstance();

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

    <div class="space-y-4 text-sm">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
        <div>
          <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Felhasználónév:</span>
          <p class="font-mono font-bold text-slate-900 text-base"><?php echo escape($user['username']); ?></p>
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Teljes Név:</span>
          <p class="font-bold text-slate-900 text-base"><?php echo escape($user['full_name']); ?></p>
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Szerepkör:</span>
          <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-slate-200 text-slate-800 uppercase inline-block">
            <?php echo escape($user['role']); ?>
          </span>
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Alapértelmezett Telephely:</span>
          <p class="font-semibold text-slate-800"><?php echo escape($user['location_short'] ?: ($user['location_name'] ?: 'Minden telephely')); ?></p>
        </div>
      </div>

      <!-- JELSZÓMÓDOSÍTÁSI BIZTONSÁGI TÁJÉKOZTATÓ -->
      <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start space-x-3 text-xs text-amber-900">
        <i data-lucide="shield-alert" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
        <div class="space-y-1">
          <p class="font-bold">Jelszómódosítási szabályzat</p>
          <p class="leading-relaxed text-amber-800">
            A rendszer biztonsági szabályzata szerint a felhasználói jelszavakat kizárólag a <strong>Rendszergazda (Adminisztrátor)</strong> módosíthatja a Felhasználók menüpontban. Jelszócseréhez kérjük forduljon a rendszergazdához!
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
