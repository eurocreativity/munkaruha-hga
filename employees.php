<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $code = trim($_POST['employee_code'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $full = trim("{$last} {$first}");
        $loc = intval($_POST['location_id'] ?? 1);
        $locker = trim($_POST['locker_number'] ?? '');

        if (!empty($code) && !empty($last)) {
            $db->execute("
                INSERT INTO employees (employee_code, last_name, first_name, full_name, location_id, locker_number)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [$code, $last, $first, $full, $loc, $locker]);
            setFlashMessage('success', "Új dolgozó felvéve: {$full} ({$code})");
            redirect('employees.php');
        }
    }
}

$search = trim($_GET['search'] ?? '');
$where = ["active = 1"];
$params = [];

if ($activeLoc) {
    $where[] = "location_id = ?";
    $params[] = intval($activeLoc);
}
if ($search) {
    $where[] = "(full_name LIKE ? OR employee_code LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s;
    $params[] = $s;
}

$whereClause = implode(" AND ", $where);
$employees = $db->fetchAll("
    SELECT e.*, l.short_name as location_short,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id) as total_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'IN_LAUNDRY') as in_laundry_count,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'ACTIVE') as active_count
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE {$whereClause}
    ORDER BY e.is_reserve ASC, e.last_name ASC
", $params);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold text-slate-900">Dolgozói Nyilvántartás</h2>
      <p class="text-xs text-slate-500">Személyekhez rendelt kódok és ruha-készletek</p>
    </div>
    <div class="flex items-center space-x-3">
      <form method="GET" action="employees.php" class="relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Keresés név, törzsszám..."
          class="pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none">
      </form>
      <button onclick="document.getElementById('emp-modal').classList.remove('hidden')" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm rounded-xl transition-all flex items-center space-x-2 shadow-sm">
        <i data-lucide="user-plus" class="w-4 h-4"></i>
        <span>Új Dolgozó</span>
      </button>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($employees as $emp): ?>
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between hover:border-brand-300 transition-all">
        <div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-mono font-bold px-2 py-0.5 bg-slate-100 text-slate-700 rounded"><?php echo escape($emp['employee_code']); ?></span>
            <span class="text-xs font-semibold text-slate-500"><?php echo escape($emp['location_short'] ?: ''); ?></span>
          </div>
          <h4 class="font-bold text-slate-900 text-base mb-1"><?php echo escape($emp['full_name']); ?></h4>
          <p class="text-xs text-slate-400 mb-4"><?php echo $emp['locker_number'] ? 'Szekrény: ' . escape($emp['locker_number']) : 'Nincs szekrény rendelve'; ?></p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
          <div class="flex items-center space-x-3">
            <span title="Összes ruha">👕 <strong class="text-slate-800"><?php echo $emp['total_clothes']; ?></strong> db</span>
            <span title="Mosásban" class="text-amber-700 font-semibold">🌊 <?php echo $emp['in_laundry_count']; ?></span>
            <span title="Dolgozónál aktív" class="text-emerald-700 font-semibold">✓ <?php echo $emp['active_count']; ?></span>
          </div>
          <a href="clothes.php?search=<?php echo urlencode($emp['full_name']); ?>" class="text-brand-600 hover:text-brand-800 font-bold">Ruhák &rarr;</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div id="emp-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-md w-full p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 class="text-lg font-bold text-slate-900">Új Dolgozó Rögzítése</h3>
      <button onclick="document.getElementById('emp-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>

    <form method="POST" action="employees.php" class="space-y-3 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Dolgozói Törzsszám / Kód *</label>
        <input type="text" name="employee_code" required placeholder="pl. 0002" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 font-mono">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Vezetéknév *</label>
          <input type="text" name="last_name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Keresztnév</label>
          <input type="text" name="first_name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Telephely *</label>
        <select name="location_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
          <option value="1">1 - Jutai út 50.</option>
          <option value="2">2 - Nagygát u. 1.</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Szekrény / Fakk szám</label>
        <input type="text" name="locker_number" placeholder="pl. A/12" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
      </div>

      <div class="pt-4 flex justify-end space-x-3 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('emp-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl">Mégse</button>
        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm">Mentés</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
