<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

$settingsObj = new Settings();
$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');
$companyLogo = $settingsObj->get('company_logo', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canEdit()) {
        setFlashMessage('danger', 'Megtekintő (Viewer) jogosultságú felhasználóval nem rögzíthető új dolgozó!');
        redirect('employees.php');
    }
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $code = trim($_POST['employee_code'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $full = trim("{$last} {$first}");
        $loc = intval($_POST['location_id'] ?? 1);

        if (!empty($code) && !empty($last)) {
            $db->execute("
                INSERT INTO employees (employee_code, last_name, first_name, full_name, location_id)
                VALUES (?, ?, ?, ?, ?)
            ", [$code, $last, $first, $full, $loc]);
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
    SELECT e.*, l.name as location_name, l.short_name as location_short,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id) as total_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'IN_LAUNDRY') as in_laundry_count,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'ACTIVE') as active_count
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE {$whereClause}
    ORDER BY e.is_reserve ASC, e.last_name ASC
", $params);

$locations = $db->fetchAll("SELECT * FROM locations ORDER BY id ASC");

require_once __DIR__ . '/includes/header.php';
?>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  #receipt-modal, #receipt-modal * {
    visibility: visible;
  }
  #receipt-modal {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    background: white !important;
    display: block !important;
  }
  #receipt-modal .bg-white {
    box-shadow: none !important;
    border: none !important;
    padding: 10mm !important;
    max-width: 100% !important;
    width: 100% !important;
  }
  .print\:hidden {
    display: none !important;
  }
  @page {
    size: A4 portrait;
    margin: 10mm;
  }
}
</style>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold text-slate-900">Dolgozói Nyilvántartás</h2>
      <p class="text-xs text-slate-500">Személyekhez rendelt kódok, ruha-készletek és átadás-átvételi nyilatkozatok</p>
    </div>
    <div class="flex items-center space-x-3">
      <form method="GET" action="employees.php" class="relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Keresés név, törzsszám..."
          class="pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none">
      </form>
      <?php if (isAdmin()): ?>
        <a href="admin_employees.php" class="px-4 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/60 text-red-600 dark:text-red-400 font-bold text-sm rounded-xl transition-all flex items-center space-x-2 border border-red-200 dark:border-red-900">
          <i data-lucide="user-x" class="w-4 h-4"></i>
          <span>Dolgozók Törlése (Admin)</span>
        </a>
      <?php endif; ?>
      <?php if (canEdit()): ?>
        <button onclick="document.getElementById('emp-modal').classList.remove('hidden')" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm rounded-xl transition-all flex items-center space-x-2 shadow-sm">
          <i data-lucide="user-plus" class="w-4 h-4"></i>
          <span>Új Dolgozó</span>
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($employees as $emp): ?>
      <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between hover:border-brand-300 dark:hover:border-slate-700 transition-all">
        <div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-mono font-bold px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded"><?php echo escape($emp['employee_code']); ?></span>
            <div class="flex items-center space-x-2">
              <span class="text-xs font-semibold text-slate-500"><?php echo escape($emp['location_short'] ?: ($emp['location_name'] ?: '')); ?></span>
              <?php if (isAdmin()): ?>
                <a href="admin_employees.php?search=<?php echo urlencode($emp['employee_code']); ?>" class="p-1 text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded-md transition-colors" title="Dolgozó kezelése és törlése (Admin)">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
          <h4 class="font-bold text-slate-900 dark:text-white text-base mb-1"><?php echo escape($emp['full_name']); ?></h4>
          <p class="text-xs text-slate-500 mb-4 flex items-center">
            <i data-lucide="map-pin" class="w-3.5 h-3.5 mr-1 text-slate-400"></i>
            <span>Telephely: <strong><?php echo escape($emp['location_name'] ?: ($emp['location_short'] ?: 'Nincs megadva')); ?></strong></span>
          </p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
          <div class="flex items-center space-x-3">
            <span title="Összes ruha">👕 <strong class="text-slate-800"><?php echo $emp['total_clothes']; ?></strong> db</span>
            <span title="Mosásban" class="text-amber-700 font-semibold">🌊 <?php echo $emp['in_laundry_count']; ?></span>
            <span title="Dolgozónál aktív" class="text-emerald-700 font-semibold">✓ <?php echo $emp['active_count']; ?></span>
          </div>
          
          <div class="flex items-center space-x-2">
            <?php if ($emp['total_clothes'] > 0): ?>
              <button onclick="openReceiptModal(<?php echo $emp['id']; ?>)" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-semibold flex items-center space-x-1 transition-all" title="Átadás-átvételi nyilatkozat nyomtatása">
                <i data-lucide="file-text" class="w-3.5 h-3.5 text-slate-600"></i>
                <span>Nyilatkozat</span>
              </button>
            <?php endif; ?>
            <a href="clothes.php?search=<?php echo urlencode($emp['full_name']); ?>" class="text-brand-600 hover:text-brand-800 font-bold">Ruhák &rarr;</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- DOLGOZÓI ÁTADÁS-ÁTVÉTELI NYILATKOZAT NYOMTATÁSI MODÁL -->
<div id="receipt-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-3xl w-full p-6 space-y-4 my-auto">
    <div id="printable-area" class="space-y-4 text-slate-800">
      
      <!-- FEJLÉC -->
      <div class="border-b-2 border-slate-800 pb-3 flex justify-between items-start">
        <div>
          <h1 class="text-xl font-black tracking-tight text-slate-900"><?php echo escape($companyName); ?></h1>
          <p class="text-[11px] text-slate-600 font-bold uppercase mt-0.5">MUNKARUHA ÁTADÁS-ÁTVÉTELI ÉS FELELŐSSÉGVÁLLALÁSI NYILATKOZAT</p>
        </div>
        <div class="text-right">
          <p class="text-[10px] text-slate-500 font-bold uppercase">Dokumentumszám</p>
          <p id="receipt-doc-number" class="text-sm font-mono font-black text-slate-900"></p>
          <p id="receipt-doc-date" class="text-[11px] text-slate-500 font-medium mt-0.5"><?php echo date('Y.m.d'); ?></p>
        </div>
      </div>

      <!-- DOLGOZÓ ADATAI -->
      <div class="grid grid-cols-2 gap-4 bg-slate-50 p-3 rounded-xl text-xs border border-slate-200">
        <div>
          <span class="font-bold text-slate-500 uppercase block mb-0.5 text-[10px]">Munkavállaló (Átvevő):</span>
          <p id="receipt-emp-name" class="font-bold text-slate-900 text-sm"></p>
          <p class="text-slate-600 mt-0.5">Törzsszám: <strong id="receipt-emp-code" class="font-mono text-slate-900"></strong></p>
        </div>
        <div>
          <span class="font-bold text-slate-500 uppercase block mb-0.5 text-[10px]">Telephely:</span>
          <p id="receipt-emp-location" class="font-semibold text-slate-900 text-sm"></p>
        </div>
      </div>

      <!-- TÉTELES RUHALISTA -->
      <div>
        <h4 class="text-[11px] font-bold uppercase text-slate-700 mb-1.5">Átadott / Kiosztott Munkaruházati Cikkek</h4>
        <table class="min-w-full divide-y divide-slate-300 text-xs">
          <thead class="bg-slate-100 font-bold text-slate-700 text-left">
            <tr>
              <th class="py-1.5 px-2">Ssz.</th>
              <th class="py-1.5 px-2">Vonalkód</th>
              <th class="py-1.5 px-2">Megnevezés</th>
              <th class="py-1.5 px-2">Kategória / Szín</th>
              <th class="py-1.5 px-2">Méret</th>
              <th class="py-1.5 px-2">Cikkszám</th>
            </tr>
          </thead>
          <tbody id="receipt-clothes-body" class="divide-y divide-slate-200 font-mono text-[11px]"></tbody>
        </table>
      </div>

      <!-- JOGI ÉS MUNKAVÉDELMI NYILATKOZAT -->
      <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[10px] text-slate-600 leading-tight space-y-1">
        <p class="font-bold text-slate-800">Felelősségvállalási Záradék:</p>
        <p>
          Alulírott munkavállaló ezennel igazolom, hogy a fent részletezett munkaruházati cikkeket hiánytalanul, tiszta és rendeltetésszerű használatra alkalmas állapotban átvettem. Vállalom, hogy a munkaruhát a munkavédelmi, higiéniai és technológiai előírásoknak megfelelően viselem, megóvom és a mosodai ciklus szerint leadom. Tudomásul veszem, hogy munkaviszonyom megszűnésekor a fenti tételekkel a munkáltató felé elszámolni köteles vagyok.
        </p>
      </div>

      <!-- ALÁÍRÁSOK -->
      <div class="grid grid-cols-2 gap-12 pt-6 border-t border-slate-200 text-center text-xs">
        <div>
          <div class="border-b border-slate-400 pb-1 mb-2"></div>
          <p class="font-bold text-slate-800">Kiadó (Munkáltató által megbízott személy)</p>
        </div>
        <div>
          <div class="border-b border-slate-400 pb-1 mb-2"></div>
          <p class="font-bold text-slate-800">Átvevő (Munkavállaló)</p>
        </div>
      </div>
    </div>

    <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100 print:hidden">
      <button onclick="document.getElementById('receipt-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">Bezárás</button>
      <button onclick="window.print()" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold rounded-xl shadow-md flex items-center space-x-1.5">
        <i data-lucide="printer" class="w-4 h-4"></i>
        <span>Nyilatkozat Nyomtatása</span>
      </button>
    </div>
  </div>
</div>

<?php if (canEdit()): ?>
<!-- ÚJ DOLGOZÓ MODÁL -->
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
          <label class="block text-xs font-bold text-slate-600 mb-1">Keresztnév *</label>
          <input type="text" name="first_name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Telephely</label>
        <select name="location_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
          <?php foreach ($locations as $loc): ?>
            <option value="<?php echo $loc['id']; ?>"><?php echo escape($loc['code'] . ' - ' . ($loc['short_name'] ?: $loc['name'])); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pt-4 flex justify-end space-x-3 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('emp-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl">Mégse</button>
        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-sm">Mentés</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
async function openReceiptModal(empId) {
  try {
    const res = await fetch('ajax_scanner.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_employee_receipt', employee_id: empId })
    });
    const data = await res.json();
    if (data.success) {
      const emp = data.employee;
      const clothes = data.clothes;

      const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
      document.getElementById('receipt-doc-number').textContent = `HGAV-${dateStr}-${emp.employee_code}`;
      document.getElementById('receipt-emp-name').textContent = emp.full_name;
      document.getElementById('receipt-emp-code').textContent = emp.employee_code;
      document.getElementById('receipt-emp-location').textContent = emp.location_name || (emp.location_short || 'HGA Biomed');

      document.getElementById('receipt-clothes-body').innerHTML = clothes.map((c, idx) => `
        <tr>
          <td class="py-1 px-2 text-slate-500">${idx + 1}.</td>
          <td class="py-1 px-2 font-bold text-slate-900">${c.barcode}</td>
          <td class="py-1 px-2 font-sans font-medium text-slate-800">${c.name}</td>
          <td class="py-1 px-2 font-sans text-slate-600">${c.category} / ${c.color || '-'}</td>
          <td class="py-1 px-2 font-sans">${c.size || '-'}</td>
          <td class="py-1 px-2 text-slate-500">${c.item_code || '-'}</td>
        </tr>
      `).join('');

      document.getElementById('receipt-modal').classList.remove('hidden');
      if (window.lucide) lucide.createIcons();
    } else {
      alert('Hiba: ' + data.message);
    }
  } catch(e) {
    alert('Hálózati hiba: ' + e.message);
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
