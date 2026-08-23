<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

$dirFilter = $_GET['direction'] ?? '';
$where = ["1=1"];
$params = [];

if ($activeLoc) {
    $where[] = "b.location_id = ?";
    $params[] = intval($activeLoc);
}
if ($dirFilter) {
    $where[] = "b.direction = ?";
    $params[] = $dirFilter;
}

$whereClause = implode(" AND ", $where);
$batches = $db->fetchAll("
    SELECT b.*, l.short_name as location_short, l.name as location_name, u.full_name as user_name
    FROM laundry_batches b
    LEFT JOIN locations l ON b.location_id = l.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE {$whereClause}
    ORDER BY b.created_at DESC
", $params);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold text-slate-900">Mosodai Szállítólevelek & Csomagok</h2>
      <p class="text-xs text-slate-500">Minden elküldött és beérkezett tétel hivatalos átadóíve és előzménye</p>
    </div>
    <form method="GET" action="batches.php" class="flex items-center space-x-3">
      <select name="direction" onchange="this.form.submit()" class="py-2 px-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
        <option value="">Összes irány (Kiadás & Bevétel)</option>
        <option value="OUT" <?php echo $dirFilter === 'OUT' ? 'selected' : ''; ?>>Kiadás (Mosodába küldve)</option>
        <option value="IN" <?php echo $dirFilter === 'IN' ? 'selected' : ''; ?>>Bevétel (Mosodából visszavéve)</option>
      </select>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold text-left">
          <tr>
            <th class="px-6 py-3.5">Csomag / Szállítólevél Szám</th>
            <th class="px-6 py-3.5">Irány</th>
            <th class="px-6 py-3.5">Telephely</th>
            <th class="px-6 py-3.5">Darabszám</th>
            <th class="px-6 py-3.5">Létrehozva</th>
            <th class="px-6 py-3.5">Kezelő</th>
            <th class="px-6 py-3.5">Státusz</th>
            <th class="px-6 py-3.5 text-right">Szállítólevél</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <?php if (empty($batches)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Még nincs rögzített mosodai csomag.</td></tr>
          <?php else: ?>
            <?php foreach ($batches as $b): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-6 py-3.5 font-mono font-bold text-slate-900"><?php echo escape($b['batch_number']); ?></td>
                <td class="px-6 py-3.5">
                  <span class="px-2.5 py-1 text-xs font-bold rounded-full <?php echo $b['direction'] === 'OUT' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'; ?>">
                    <?php echo $b['direction'] === 'OUT' ? 'Kiadás (Mosodába)' : 'Bevétel (Mosodából)'; ?>
                  </span>
                </td>
                <td class="px-6 py-3.5 text-slate-700"><?php echo escape($b['location_short'] ?: $b['location_name']); ?></td>
                <td class="px-6 py-3.5 font-bold text-slate-900"><?php echo $b['item_count']; ?> db</td>
                <td class="px-6 py-3.5 text-xs text-slate-500 font-mono"><?php echo date('Y.m.d H:i', strtotime($b['created_at'])); ?></td>
                <td class="px-6 py-3.5 text-xs text-slate-600"><?php echo escape($b['user_name'] ?: '-'); ?></td>
                <td class="px-6 py-3.5">
                  <span class="px-2 py-0.5 text-xs font-semibold rounded <?php echo $b['status'] === 'COMPLETED' ? 'bg-slate-100 text-slate-700' : 'bg-brand-100 text-brand-800'; ?>">
                    <?php echo $b['status'] === 'COMPLETED' ? 'Lezárva' : 'Folyamatban'; ?>
                  </span>
                </td>
                <td class="px-6 py-3.5 text-right">
                  <button onclick="openDeliveryModal(<?php echo $b['id']; ?>)" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold flex items-center space-x-1 ml-auto shadow-xs">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                    <span>Átadóív / Nyomtatás</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="batch-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-3xl w-full p-8 space-y-6 my-auto">
    <div id="printable-area" class="space-y-6 text-slate-800">
      <div class="border-b-2 border-slate-800 pb-4 flex justify-between items-start">
        <div>
          <h1 class="text-2xl font-black tracking-tight text-slate-900">HGA Biomed Kft.</h1>
          <p class="text-xs text-slate-600 font-semibold mt-1">MUNKARUHA MOSODAI ÁTADÁS-ÁTVÉTELI JEGYZÉK</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-slate-500 font-bold uppercase">Bizonylatszám</p>
          <p id="print-batch-number" class="text-lg font-mono font-black text-slate-900"></p>
          <p id="print-batch-date" class="text-xs text-slate-500 font-medium mt-0.5"></p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl text-xs border border-slate-200">
        <div>
          <span class="font-bold text-slate-500 uppercase block mb-1">Küldő / Kiadó Telephely:</span>
          <p id="print-location-name" class="font-bold text-slate-900 text-sm">HGA Biomed</p>
          <p id="print-location-address" class="text-slate-600"></p>
          <p class="text-slate-500 mt-1">Kezelő: <span id="print-user-name" class="font-semibold text-slate-800"></span></p>
        </div>
        <div>
          <span class="font-bold text-slate-500 uppercase block mb-1">Művelet Jellege:</span>
          <p id="print-direction-label" class="font-black text-brand-700 text-sm"></p>
          <p class="text-slate-600">Mosodai Szolgáltató részére</p>
          <p class="text-slate-500 mt-1">Összes darabszám: <span id="print-total-count" class="font-black text-slate-900 text-sm">0 db</span></p>
        </div>
      </div>

      <div id="print-category-breakdown" class="flex flex-wrap gap-2 text-xs"></div>

      <table class="min-w-full divide-y divide-slate-300 text-xs">
        <thead class="bg-slate-100 font-bold text-slate-700 text-left">
          <tr>
            <th class="py-2 px-3">Ssz.</th>
            <th class="py-2 px-3">Vonalkód</th>
            <th class="py-2 px-3">Megnevezés</th>
            <th class="py-2 px-3">Méret</th>
            <th class="py-2 px-3">Dolgozó Neve (Törzsszám)</th>
          </tr>
        </thead>
        <tbody id="print-items-tbody" class="divide-y divide-slate-200"></tbody>
      </table>

      <div class="grid grid-cols-2 gap-12 pt-8 text-xs border-t border-slate-200">
        <div class="text-center">
          <div class="border-b border-slate-400 pb-8"></div>
          <p class="mt-2 font-bold text-slate-700">Átadó (HGA Biomed képviselője)</p>
        </div>
        <div class="text-center">
          <div class="border-b border-slate-400 pb-8"></div>
          <p class="mt-2 font-bold text-slate-700">Átvevő (Mosoda képviselője / Futár)</p>
        </div>
      </div>
    </div>

    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
      <button onclick="document.getElementById('batch-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl text-sm font-semibold">Bezárás</button>
      <button onclick="window.print()" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl transition-all flex items-center space-x-2 shadow-sm">
        <i data-lucide="printer" class="w-4 h-4"></i>
        <span>Nyomtatás</span>
      </button>
    </div>
  </div>
</div>

<script>
async function openDeliveryModal(batchId) {
  const res = await fetch('ajax_scanner.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_batch_details', batch_id: batchId })
  });
  const data = await res.json();
  if (!data.success) return;

  const b = data.batch;
  document.getElementById('print-batch-number').textContent = b.batch_number;
  document.getElementById('print-batch-date').textContent = new Date(b.created_at).toLocaleString('hu-HU');
  document.getElementById('print-location-name').textContent = b.location_name || 'HGA Biomed';
  document.getElementById('print-location-address').textContent = b.location_address || '';
  document.getElementById('print-user-name').textContent = b.user_name || '-';
  document.getElementById('print-direction-label').textContent = b.direction === 'OUT' ? 'MOSODÁBA KÜLDVE (Tisztításra átadva)' : 'MOSODÁBÓL VISSZAVÉVE (Átvéve)';
  document.getElementById('print-total-count').textContent = `${data.items.length} db`;

  document.getElementById('print-category-breakdown').innerHTML = Object.entries(data.categoryCounts).map(([cat, c]) => `
    <span class="px-2.5 py-1 bg-slate-100 border border-slate-300 rounded font-semibold text-slate-800">${cat}: ${c} db</span>
  `).join('');

  document.getElementById('print-items-tbody').innerHTML = data.items.map((item, i) => `
    <tr>
      <td class="py-1.5 px-3 font-mono">${i + 1}.</td>
      <td class="py-1.5 px-3 font-mono font-bold">${item.barcode}</td>
      <td class="py-1.5 px-3 font-medium">${item.cloth_name} (${item.category} / ${item.color || '-'})</td>
      <td class="py-1.5 px-3 font-mono">${item.size || '-'}</td>
      <td class="py-1.5 px-3">${item.employee_name || 'Tartalék'} ${item.employee_code ? '(' + item.employee_code + ')' : ''}</td>
    </tr>
  `).join('');

  document.getElementById('batch-modal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
