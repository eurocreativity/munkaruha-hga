<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak adminisztrátorok kezelhetik a felhasználókat!');
    redirect('dashboard.php');
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $full = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'operator';
        $locId = !empty($_POST['default_location_id']) ? intval($_POST['default_location_id']) : null;

        if (!empty($u) && !empty($p) && !empty($full)) {
            $hash = password_hash($p, PASSWORD_DEFAULT);
            $db->execute("
                INSERT INTO users (username, password_hash, full_name, role, default_location_id)
                VALUES (?, ?, ?, ?, ?)
            ", [$u, $hash, $full, $role, $locId]);
            setFlashMessage('success', "Felhasználó sikeresen létrehozva: {$u}");
            redirect('users.php');
        }
    }
}

$users = $db->fetchAll("
    SELECT u.*, l.short_name as location_short
    FROM users u
    LEFT JOIN locations l ON u.default_location_id = l.id
    ORDER BY u.id ASC
");

$locations = $db->fetchAll("SELECT * FROM locations ORDER BY id ASC");

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold text-slate-900">Rendszerfelhasználók Kezelése</h2>
      <p class="text-xs text-slate-500">Többfelhasználós jogosultságok és telephelyi hozzárendelések</p>
    </div>
    <button onclick="document.getElementById('user-modal').classList.remove('hidden')" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm rounded-xl transition-all flex items-center space-x-2">
      <i data-lucide="user-plus" class="w-4 h-4"></i>
      <span>Új Felhasználó</span>
    </button>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs font-bold uppercase text-left">
        <tr>
          <th class="px-6 py-3">Felhasználónév</th>
          <th class="px-6 py-3">Teljes Név</th>
          <th class="px-6 py-3">Szerepkör</th>
          <th class="px-6 py-3">Alapértelmezett Telephely</th>
          <th class="px-6 py-3">Státusz</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        <?php foreach ($users as $u): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-6 py-3 font-mono font-bold text-slate-900"><?php echo escape($u['username']); ?></td>
            <td class="px-6 py-3 font-medium text-slate-900"><?php echo escape($u['full_name']); ?></td>
            <td class="px-6 py-3"><span class="px-2 py-0.5 text-xs font-bold rounded bg-slate-100 text-slate-800 uppercase"><?php echo escape($u['role']); ?></span></td>
            <td class="px-6 py-3 text-slate-600"><?php echo escape($u['location_short'] ?: 'Összes telephely'); ?></td>
            <td class="px-6 py-3"><span class="text-xs font-bold text-emerald-700">Aktív</span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="user-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-md w-full p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 class="text-lg font-bold text-slate-900">Új Rendszerfelhasználó</h3>
      <button onclick="document.getElementById('user-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>

    <form method="POST" action="users.php" class="space-y-3 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Felhasználónév *</label>
        <input type="text" name="username" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Jelszó *</label>
        <input type="password" name="password" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Teljes Név *</label>
        <input type="text" name="full_name" required placeholder="pl. Kiss Anna" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Szerepkör</label>
          <select name="role" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="operator">Operátor (Raktáros)</option>
            <option value="admin">Adminisztrátor</option>
            <option value="viewer">Megtekintő (Vezető)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Alapért. Telephely</label>
          <select name="default_location_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <?php foreach ($locations as $loc): ?>
              <option value="<?php echo $loc['id']; ?>"><?php echo escape($loc['code'] . ' - ' . ($loc['short_name'] ?: $loc['name'])); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="pt-4 flex justify-end space-x-3 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('user-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl">Mégse</button>
        <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-sm">Létrehozás</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
