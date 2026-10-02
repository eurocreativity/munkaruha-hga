<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak rendszergazdák (Admin) jogosultak a dolgozók törlésére és adminisztrációjára!');
    redirect('employees.php');
}

$db = Database::getInstance();
$currentUser = getCurrentUser();
$activeLoc = getActiveLocationId();

// Törlés / Módosítás feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $empId = intval($_POST['employee_id'] ?? 0);
            $emp = $db->fetchOne("SELECT * FROM employees WHERE id = ?", [$empId]);

            if ($emp) {
                $clothesCount = $db->fetchOne("SELECT COUNT(*) as c FROM clothes WHERE employee_id = ?", [$empId])['c'] ?? 0;
                $clothAction = $_POST['cloth_action'] ?? 'reserve'; // 'reserve' or 'detach'

                // Ruhák leválasztása vagy tartalékba helyezése
                if ($clothesCount > 0) {
                    if ($clothAction === 'reserve') {
                        $db->execute("UPDATE clothes SET employee_id = NULL, status = 'RESERVE', notes = CONCAT(COALESCE(notes, ''), ' [Korábbi tulajdonos: ', ?, ']') WHERE employee_id = ?", [$emp['full_name'], $empId]);
                    } else {
                        $db->execute("UPDATE clothes SET employee_id = NULL WHERE employee_id = ?", [$empId]);
                    }
                }

                // Dolgozó végleges törlése az adatbázisból
                $db->execute("DELETE FROM employees WHERE id = ?", [$empId]);

                // Audit naplózás
                $details = "Dolgozó törölve: {$emp['full_name']} ({$emp['employee_code']}). Hozzátartozó {$clothesCount} db ruha lecsatolva.";
                $db->execute("
                    INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
                    VALUES (?, ?, 'EMPLOYEE_DELETE', 'EMPLOYEE', ?, ?, ?)
                ", [$currentUser['id'], $currentUser['username'], $empId, $details, $emp['location_id']]);

                setFlashMessage('success', "A(z) {$emp['full_name']} ({$emp['employee_code']}) dolgozó sikeresen törölve lett az adatbázisból!");
            } else {
                setFlashMessage('danger', 'A megadott dolgozó nem található!');
            }
            redirect('admin_employees.php');
        } elseif ($action === 'edit') {
            $empId = intval($_POST['employee_id'] ?? 0);
            $code = trim($_POST['employee_code'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $first = trim($_POST['first_name'] ?? '');
            $full = trim("{$last} {$first}");
            $loc = intval($_POST['location_id'] ?? 1);

            if (!empty($code) && !empty($last)) {
                $db->execute("
                    UPDATE employees 
                    SET employee_code = ?, last_name = ?, first_name = ?, full_name = ?, location_id = ?
                    WHERE id = ?
                ", [$code, $last, $first, $full, $loc, $empId]);

                $db->execute("
                    INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
                    VALUES (?, ?, 'EMPLOYEE_UPDATE', 'EMPLOYEE', ?, ?, ?)
                ", [$currentUser['id'], $currentUser['username'], $empId, "Dolgozó adatai módosítva: {$full} ({$code})", $loc]);

                setFlashMessage('success', "Dolgozó adatai sikeresen módosítva: {$full}");
            }
            redirect('admin_employees.php');
        }
    }
}

$search = trim($_GET['search'] ?? '');
$locFilter = $_GET['location_id'] ?? $activeLoc;

$where = ["1=1"];
$params = [];

if (!empty($locFilter)) {
    $where[] = "e.location_id = ?";
    $params[] = intval($locFilter);
}
if ($search) {
    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s;
    $params[] = $s;
}

$whereClause = implode(" AND ", $where);
$employees = $db->fetchAll("
    SELECT e.*, l.name as location_name, l.short_name as location_short,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id) as total_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'IN_LAUNDRY') as in_laundry_count,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'ACTIVE') as active_count
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE {$whereClause}
    ORDER BY e.is_reserve ASC, e.last_name ASC, e.first_name ASC
", $params);

$locations = $db->fetchAll("SELECT * FROM locations ORDER BY id ASC");

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <!-- FEJLÉC -->
  <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 flex items-center justify-center">
          <i data-lucide="user-x" class="w-5 h-5"></i>
        </div>
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Dolgozók Adminisztrációja & Törlése</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">Kizárólag Rendszergazdák részére fenntartott felület dolgozók adatainak szerkesztésére és végleges törlésére</p>
        </div>
      </div>
    </div>
    
    <div class="flex items-center space-x-3">
      <a href="employees.php" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl transition-all flex items-center space-x-2">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span>Vissza a Dolgozói Listához</span>
      </a>
    </div>
  </div>

  <!-- SZŰRŐK ÉS KERESÉS -->
  <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="admin_employees.php" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
      <div class="relative min-w-[240px]">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Keresés név, törzsszám alapján..."
          class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500 focus:outline-none">
      </div>

      <select name="location_id" onchange="this.form.submit()" class="px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500">
        <option value="">Összes telephely</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?php echo $loc['id']; ?>" <?php echo ((string)$locFilter === (string)$loc['id']) ? 'selected' : ''; ?>>
            <?php echo escape($loc['short_name'] ?: $loc['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm font-medium">Szűrés</button>
      <?php if ($search || $locFilter): ?>
        <a href="admin_employees.php" class="px-3 py-2 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 text-sm">Szűrők törlése</a>
      <?php endif; ?>
    </form>

    <div class="text-xs text-slate-500 font-semibold">
      Összesen: <strong class="text-slate-800 dark:text-slate-100"><?php echo count($employees); ?></strong> dolgozó
    </div>
  </div>

  <!-- DOLGOZÓK TÁBLÁZATA -->
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">
            <th class="py-3 px-4">Törzsszám</th>
            <th class="py-3 px-4">Dolgozó Neve</th>
            <th class="py-3 px-4">Telephely</th>
            <th class="py-3 px-4 text-center">Ruhák Száma</th>
            <th class="py-3 px-4 text-center">Aktív / Mosás</th>
            <th class="py-3 px-4 text-right">Műveletek</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
          <?php if (empty($employees)): ?>
            <tr>
              <td colspan="6" class="py-8 text-center text-slate-400">
                <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                <p>Nincs a keresésnek megfelelő dolgozó.</p>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($employees as $emp): ?>
            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                <?php echo escape($emp['employee_code']); ?>
              </td>
              <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                <?php echo escape($emp['full_name']); ?>
                <?php if ($emp['is_reserve']): ?>
                  <span class="ml-2 px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded">Tartalék fakk</span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">
                <span class="flex items-center">
                  <i data-lucide="map-pin" class="w-3.5 h-3.5 mr-1 text-slate-400"></i>
                  <?php echo escape($emp['location_short'] ?: ($emp['location_name'] ?: '-')); ?>
                </span>
              </td>
              <td class="py-3 px-4 text-center">
                <?php if ($emp['total_clothes'] > 0): ?>
                  <span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 font-bold rounded-lg text-xs">
                    👕 <?php echo $emp['total_clothes']; ?> db
                  </span>
                <?php else: ?>
                  <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-xs">0 db (üres)</span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 text-center text-xs">
                <span class="text-emerald-700 font-bold" title="Aktív készlet">✓ <?php echo $emp['active_count']; ?></span>
                <span class="text-slate-400 mx-1">/</span>
                <span class="text-amber-700 font-bold" title="Mosodában">🌊 <?php echo $emp['in_laundry_count']; ?></span>
              </td>
              <td class="py-3 px-4 text-right">
                <div class="flex items-center justify-end space-x-2">
                  <button type="button" onclick='openEditEmpModal(<?php echo json_encode($emp, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="p-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold flex items-center space-x-1" title="Adatok szerkesztése">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                  </button>

                  <button type="button" onclick='openDeleteEmpModal(<?php echo json_encode($emp, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/60 text-red-600 dark:text-red-400 rounded-lg text-xs font-bold flex items-center space-x-1 border border-red-200 dark:border-red-900 transition-all" title="Dolgozó törlése az adatbázisból">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>Törlés</span>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- TÖRLÉS MEGERŐSÍTŐ MODÁL -->
<div id="delete-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4">
  <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95">
    <div class="flex items-center space-x-3 text-red-600">
      <div class="w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-950/60 flex items-center justify-center shrink-0">
        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
      </div>
      <div>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Dolgozó Törlése</h3>
        <p class="text-xs text-slate-500">Biztosan törölni szeretnéd ezt a dolgozót?</p>
      </div>
    </div>

    <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-xl space-y-2 border border-slate-200 dark:border-slate-700 text-xs">
      <div class="flex justify-between">
        <span class="text-slate-500 font-medium">Dolgozó neve:</span>
        <strong id="del-emp-name" class="text-slate-900 dark:text-white text-sm"></strong>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-500 font-medium">Törzsszám / Kód:</span>
        <strong id="del-emp-code" class="font-mono text-slate-800 dark:text-slate-200"></strong>
      </div>
      <div class="flex justify-between">
        <span class="text-slate-500 font-medium">Telephely:</span>
        <span id="del-emp-loc" class="text-slate-700 dark:text-slate-300 font-medium"></span>
      </div>
      <div class="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2">
        <span class="text-slate-500 font-medium">Hozzárendelt munkaruhák:</span>
        <strong id="del-emp-clothes" class="text-amber-600 dark:text-amber-400"></strong>
      </div>
    </div>

    <form method="POST" action="admin_employees.php" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="employee_id" id="del-emp-id" value="">

      <div id="del-clothes-options" class="space-y-2 text-xs">
        <label class="font-bold text-slate-700 dark:text-slate-300 block">Munkaruhák kezelése a törlés után:</label>
        <div class="space-y-1.5 bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
          <label class="flex items-start space-x-2 cursor-pointer">
            <input type="radio" name="cloth_action" value="reserve" checked class="mt-0.5 text-brand-600 focus:ring-brand-500">
            <div>
              <span class="font-bold text-slate-800 dark:text-white block">Áthelyezés Tartalék / Raktári Készletbe (Ajánlott)</span>
              <span class="text-[11px] text-slate-500">A ruhák megmaradnak a rendszerben, státuszuk Tartalék (RESERVE) lesz, így másnak újra kioszthatók.</span>
            </div>
          </label>
          <label class="flex items-start space-x-2 cursor-pointer pt-2 border-t border-slate-200 dark:border-slate-700">
            <input type="radio" name="cloth_action" value="detach" class="mt-0.5 text-brand-600 focus:ring-brand-500">
            <div>
              <span class="font-bold text-slate-800 dark:text-white block">Csak leválasztás a dolgozóról</span>
              <span class="text-[11px] text-slate-500">A ruhák jelenlegi státusza nem változik, csak a dolgozóhoz való kötődés szűnik meg.</span>
            </div>
          </label>
        </div>
      </div>

      <div class="flex justify-end space-x-3 pt-2">
        <button type="button" onclick="document.getElementById('delete-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl">Mégse</button>
        <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl shadow-md flex items-center space-x-1.5">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
          <span>Végleges Törlés</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- SZERKESZTÉS MODÁL -->
<div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4">
  <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">Dolgozó Adatai</h3>
      <button onclick="document.getElementById('edit-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>

    <form method="POST" action="admin_employees.php" class="space-y-3 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="employee_id" id="edit-emp-id" value="">

      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Dolgozói Törzsszám / Kód *</label>
        <input type="text" name="employee_code" id="edit-emp-code" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-mono focus:ring-2 focus:ring-brand-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Vezetéknév *</label>
          <input type="text" name="last_name" id="edit-emp-last" required class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Keresztnév</label>
          <input type="text" name="first_name" id="edit-emp-first" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-brand-500">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Telephely</label>
        <select name="location_id" id="edit-emp-loc" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl">
          <?php foreach ($locations as $loc): ?>
            <option value="<?php echo $loc['id']; ?>"><?php echo escape($loc['code'] . ' - ' . ($loc['short_name'] ?: $loc['name'])); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pt-4 flex justify-end space-x-3 border-t border-slate-100 dark:border-slate-800">
        <button type="button" onclick="document.getElementById('edit-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl">Mégse</button>
        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm">Mentés</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeleteEmpModal(emp) {
  document.getElementById('del-emp-id').value = emp.id;
  document.getElementById('del-emp-name').textContent = emp.full_name;
  document.getElementById('del-emp-code').textContent = emp.employee_code;
  document.getElementById('del-emp-loc').textContent = emp.location_short || (emp.location_name || 'Nincs megadva');
  document.getElementById('del-emp-clothes').textContent = emp.total_clothes + ' db';

  const optDiv = document.getElementById('del-clothes-options');
  if (parseInt(emp.total_clothes) === 0) {
    optDiv.classList.add('hidden');
  } else {
    optDiv.classList.remove('hidden');
  }

  document.getElementById('delete-modal').classList.remove('hidden');
  if (window.lucide) lucide.createIcons();
}

function openEditEmpModal(emp) {
  document.getElementById('edit-emp-id').value = emp.id;
  document.getElementById('edit-emp-code').value = emp.employee_code;
  document.getElementById('edit-emp-last').value = emp.last_name || '';
  document.getElementById('edit-emp-first').value = emp.first_name || '';
  document.getElementById('edit-emp-loc').value = emp.location_id || '1';

  document.getElementById('edit-modal').classList.remove('hidden');
  if (window.lucide) lucide.createIcons();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
