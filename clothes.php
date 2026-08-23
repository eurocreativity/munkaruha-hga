<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

// Új ruha / Módosítás mentése
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (validateCsrfToken($csrf)) {
        $barcode = trim($_POST['barcode'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? 'Egyéb';
        $color = $_POST['color'] ?? 'Egyéb';
        $size = trim($_POST['size'] ?? '');
        $item_code = trim($_POST['item_code'] ?? '');
        $location_id = intval($_POST['location_id'] ?? 1);
        $status = $_POST['status'] ?? 'ACTIVE';
        $emp_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $notes = trim($_POST['notes'] ?? '');

        if ($action === 'create' && !empty($barcode) && !empty($name)) {
            $db->execute("
                INSERT INTO clothes (barcode, item_code, name, category, color, size, employee_id, location_id, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$barcode, $item_code, $name, $category, $color, $size, $emp_id, $location_id, $status, $notes]);
            setFlashMessage('success', "Munkaruha sikeresen hozzáadva: {$name} ({$barcode})");
        } elseif ($action === 'update' && !empty($_POST['cloth_id'])) {
            $clothId = intval($_POST['cloth_id']);
            $db->execute("
                UPDATE clothes SET barcode = ?, item_code = ?, name = ?, category = ?, color = ?, size = ?, employee_id = ?, location_id = ?, status = ?, notes = ?
                WHERE id = ?
            ", [$barcode, $item_code, $name, $category, $color, $size, $emp_id, $location_id, $status, $notes, $clothId]);
            setFlashMessage('success', "Munkaruha sikeresen módosítva: {$barcode}");
        }
        redirect('clothes.php');
    }
}

// Szűrések
$search = trim($_GET['search'] ?? '');
$filterCat = $_GET['category'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterColor = $_GET['color'] ?? '';

$where = ["1=1"];
$params = [];

if ($activeLoc) {
    $where[] = "c.location_id = ?";
    $params[] = intval($activeLoc);
}
if ($filterCat) {
    $where[] = "c.category = ?";
    $params[] = $filterCat;
}
if ($filterStatus) {
    $where[] = "c.status = ?";
    $params[] = $filterStatus;
}
if ($filterColor) {
    $where[] = "c.color = ?";
    $params[] = $filterColor;
}
if ($search) {
    $where[] = "(c.barcode LIKE ? OR c.item_code LIKE ? OR c.name LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

$whereClause = implode(" AND ", $where);
$clothes = $db->fetchAll("
    SELECT c.*, e.full_name as employee_name, e.employee_code, l.short_name as location_short
    FROM clothes c
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE {$whereClause}
    ORDER BY c.updated_at DESC, c.id DESC
", $params);

$employees = $db->fetchAll("SELECT * FROM employees WHERE active = 1 ORDER BY is_reserve ASC, full_name ASC");

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Munkaruhák Nyilvántartása</h2>
        <p class="text-xs text-slate-500">Keresés, szűrés, dolgozókhoz rendelés és státuszmódosítás</p>
      </div>
      <button onclick="openClothModal()" class="px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm rounded-xl transition-all flex items-center space-x-2 shadow-sm">
        <i data-lucide="plus" class="w-4 h-4"></i>
        <span>Új Ruha Hozzáadása</span>
      </button>
    </div>

    <!-- Szűrők -->
    <form method="GET" action="clothes.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 pt-2">
      <div class="md:col-span-2 relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Keresés vonalkód, név, dolgozó..."
          class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none">
      </div>
      <div>
        <select name="category" onchange="this.form.submit()" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
          <option value="">Összes kategória</option>
          <option value="Póló" <?php echo $filterCat === 'Póló' ? 'selected' : ''; ?>>Póló</option>
          <option value="Köpeny" <?php echo $filterCat === 'Köpeny' ? 'selected' : ''; ?>>Köpeny</option>
          <option value="Nadrág" <?php echo $filterCat === 'Nadrág' ? 'selected' : ''; ?>>Nadrág</option>
          <option value="Kazak" <?php echo $filterCat === 'Kazak' ? 'selected' : ''; ?>>Kazak</option>
          <option value="Egyéb" <?php echo $filterCat === 'Egyéb' ? 'selected' : ''; ?>>Egyéb</option>
        </select>
      </div>
      <div>
        <select name="status" onchange="this.form.submit()" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
          <option value="">Összes státusz</option>
          <option value="ACTIVE" <?php echo $filterStatus === 'ACTIVE' ? 'selected' : ''; ?>>Aktív (Dolgozónál)</option>
          <option value="IN_LAUNDRY" <?php echo $filterStatus === 'IN_LAUNDRY' ? 'selected' : ''; ?>>Mosásban</option>
          <option value="RESERVE" <?php echo $filterStatus === 'RESERVE' ? 'selected' : ''; ?>>Tartalék</option>
          <option value="LOST" <?php echo $filterStatus === 'LOST' ? 'selected' : ''; ?>>Hiányzó / Elveszett</option>
          <option value="SCRAPPED" <?php echo $filterStatus === 'SCRAPPED' ? 'selected' : ''; ?>>Selejtezve</option>
        </select>
      </div>
      <div>
        <select name="color" onchange="this.form.submit()" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
          <option value="">Összes szín</option>
          <option value="Fehér" <?php echo $filterColor === 'Fehér' ? 'selected' : ''; ?>>Fehér</option>
          <option value="Zöld" <?php echo $filterColor === 'Zöld' ? 'selected' : ''; ?>>Zöld</option>
          <option value="Bottlezöld" <?php echo $filterColor === 'Bottlezöld' ? 'selected' : ''; ?>>Bottlezöld</option>
          <option value="Kék" <?php echo $filterColor === 'Kék' ? 'selected' : ''; ?>>Kék</option>
        </select>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold text-left">
          <tr>
            <th class="px-6 py-3.5">Vonalkód</th>
            <th class="px-6 py-3.5">Cikkszám / Név</th>
            <th class="px-6 py-3.5">Kategória</th>
            <th class="px-6 py-3.5">Szín / Méret</th>
            <th class="px-6 py-3.5">Hozzárendelt Dolgozó</th>
            <th class="px-6 py-3.5">Telephely</th>
            <th class="px-6 py-3.5">Státusz</th>
            <th class="px-6 py-3.5 text-right">Művelet</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <?php if (empty($clothes)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Nincs találat a megadott szűrésekre.</td></tr>
          <?php else: ?>
            <?php foreach ($clothes as $c): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-6 py-3.5 font-mono font-bold text-slate-900"><?php echo escape($c['barcode']); ?></td>
                <td class="px-6 py-3.5">
                  <div class="font-medium text-slate-900"><?php echo escape($c['name']); ?></div>
                  <div class="text-xs text-slate-400 font-mono"><?php echo escape($c['item_code'] ?: '-'); ?></div>
                </td>
                <td class="px-6 py-3.5 text-slate-600"><?php echo escape($c['category']); ?></td>
                <td class="px-6 py-3.5 text-slate-600">
                  <span><?php echo escape($c['color'] ?: '-'); ?></span> / <span class="font-mono"><?php echo escape($c['size'] ?: '-'); ?></span>
                </td>
                <td class="px-6 py-3.5 font-medium text-slate-900"><?php echo escape($c['employee_name'] ?: 'Tartalék'); ?></td>
                <td class="px-6 py-3.5 text-slate-600"><?php echo escape($c['location_short'] ?: '-'); ?></td>
                <td class="px-6 py-3.5">
                  <?php 
                    $badges = [
                      'ACTIVE' => 'bg-emerald-100 text-emerald-800',
                      'IN_LAUNDRY' => 'bg-amber-100 text-amber-800',
                      'RESERVE' => 'bg-blue-100 text-blue-800',
                      'LOST' => 'bg-red-100 text-red-800',
                      'SCRAPPED' => 'bg-slate-200 text-slate-700'
                    ];
                    $labels = [
                      'ACTIVE' => 'Aktív (Dolgozónál)',
                      'IN_LAUNDRY' => 'Mosásban',
                      'RESERVE' => 'Tartalék',
                      'LOST' => 'Hiányzó / Elveszett',
                      'SCRAPPED' => 'Selejtezve'
                    ];
                  ?>
                  <span class="px-2.5 py-1 text-xs font-bold rounded-full <?php echo $badges[$c['status']] ?? 'bg-slate-100'; ?>">
                    <?php echo $labels[$c['status']] ?? $c['status']; ?>
                  </span>
                </td>
                <td class="px-6 py-3.5 text-right">
                  <button onclick='editCloth(<?php echo json_encode($c); ?>)' class="p-1.5 text-slate-500 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-all" title="Szerkesztés">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 text-xs text-slate-500">
      Összesen: <?php echo count($clothes); ?> db tétel
    </div>
  </div>
</div>

<!-- Ruha Modál -->
<div id="cloth-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 id="cloth-modal-title" class="text-lg font-bold text-slate-900">Munkaruha Adatlap</h3>
      <button onclick="document.getElementById('cloth-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>

    <form method="POST" action="clothes.php" class="space-y-3 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
      <input type="hidden" name="form_action" id="cloth-form-action" value="create">
      <input type="hidden" name="cloth_id" id="cloth-form-id" value="">

      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Vonalkód *</label>
        <input type="text" name="barcode" id="cloth-form-barcode" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 font-mono font-bold">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Megnevezés *</label>
        <input type="text" name="name" id="cloth-form-name" required placeholder="pl. Rövid ujjú póló, fehér" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Kategória</label>
          <select name="category" id="cloth-form-category" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="Póló">Póló</option>
            <option value="Köpeny">Köpeny</option>
            <option value="Nadrág">Nadrág</option>
            <option value="Kazak">Kazak</option>
            <option value="Egyéb">Egyéb</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Szín</label>
          <select name="color" id="cloth-form-color" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="Fehér">Fehér</option>
            <option value="Zöld">Zöld</option>
            <option value="Bottlezöld">Bottlezöld</option>
            <option value="Kék">Kék</option>
            <option value="Egyéb">Egyéb</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Méret</label>
          <input type="text" name="size" id="cloth-form-size" placeholder="pl. L, XL, 52/105" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Cikkszám</label>
          <input type="text" name="item_code" id="cloth-form-item-code" placeholder="pl. TSA1, W4S1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Telephely *</label>
          <select name="location_id" id="cloth-form-location" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="1">1 - Jutai út 50.</option>
            <option value="2">2 - Nagygát u. 1.</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Státusz</label>
          <select name="status" id="cloth-form-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="ACTIVE">Aktív (Dolgozónál)</option>
            <option value="IN_LAUNDRY">Mosásban</option>
            <option value="RESERVE">Tartalék</option>
            <option value="LOST">Elveszett / Nincs meg</option>
            <option value="SCRAPPED">Selejt</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Hozzárendelt Dolgozó</label>
        <select name="employee_id" id="cloth-form-employee" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
          <option value="">-- Nincs hozzárendelve / Tartalék --</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?php echo $e['id']; ?>"><?php echo escape($e['full_name'] . ' (' . $e['employee_code'] . ')'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Megjegyzés</label>
        <input type="text" name="notes" id="cloth-form-notes" placeholder="pl. csere póló, 5 éve nincs meg..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
      </div>

      <div class="pt-4 flex justify-end space-x-3 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('cloth-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl">Mégse</button>
        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm">Mentés</button>
      </div>
    </form>
  </div>
</div>

<script>
function openClothModal() {
  document.getElementById('cloth-modal-title').textContent = 'Új Munkaruha Hozzáadása';
  document.getElementById('cloth-form-action').value = 'create';
  document.getElementById('cloth-form-id').value = '';
  document.getElementById('cloth-form-barcode').value = '';
  document.getElementById('cloth-form-name').value = '';
  document.getElementById('cloth-form-size').value = '';
  document.getElementById('cloth-form-item-code').value = '';
  document.getElementById('cloth-form-notes').value = '';
  document.getElementById('cloth-modal').classList.remove('hidden');
}

function editCloth(c) {
  document.getElementById('cloth-modal-title').textContent = 'Munkaruha Módosítása';
  document.getElementById('cloth-form-action').value = 'update';
  document.getElementById('cloth-form-id').value = c.id;
  document.getElementById('cloth-form-barcode').value = c.barcode;
  document.getElementById('cloth-form-name').value = c.name;
  document.getElementById('cloth-form-category').value = c.category || 'Egyéb';
  document.getElementById('cloth-form-color').value = c.color || 'Egyéb';
  document.getElementById('cloth-form-size').value = c.size || '';
  document.getElementById('cloth-form-item-code').value = c.item_code || '';
  document.getElementById('cloth-form-location').value = c.location_id || 1;
  document.getElementById('cloth-form-status').value = c.status || 'ACTIVE';
  document.getElementById('cloth-form-employee').value = c.employee_id || '';
  document.getElementById('cloth-form-notes').value = c.notes || '';
  document.getElementById('cloth-modal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
