<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

$locWhere = $activeLoc ? " WHERE location_id = " . intval($activeLoc) : "";
$locAnd = $activeLoc ? " AND location_id = " . intval($activeLoc) : "";

$totalClothes = $db->fetchOne("SELECT COUNT(*) as c FROM clothes" . $locWhere)['c'];
$inLaundry = $db->fetchOne("SELECT COUNT(*) as c FROM clothes WHERE status = 'IN_LAUNDRY'" . $locAnd)['c'];
$activeClothes = $db->fetchOne("SELECT COUNT(*) as c FROM clothes WHERE status = 'ACTIVE'" . $locAnd)['c'];
$reserveClothes = $db->fetchOne("SELECT COUNT(*) as c FROM clothes WHERE status = 'RESERVE'" . $locAnd)['c'];
$lostClothes = $db->fetchOne("SELECT COUNT(*) as c FROM clothes WHERE status = 'LOST'" . $locAnd)['c'];
$totalNetValue = $db->fetchOne("SELECT SUM(net_value) as s FROM clothes" . $locWhere)['s'] ?: 0;

$categories = $db->fetchAll("SELECT category, COUNT(*) as count FROM clothes" . $locWhere . " GROUP BY category");
$colors = $db->fetchAll("SELECT color, COUNT(*) as count FROM clothes" . $locWhere . " GROUP BY color");

$recentSql = "
  SELECT li.*, c.name as cloth_name, c.barcode, e.full_name as employee_name, u.full_name as user_name, l.short_name as location_short
  FROM laundry_items li
  JOIN clothes c ON li.cloth_id = c.id
  LEFT JOIN employees e ON c.employee_id = e.id
  LEFT JOIN users u ON li.user_id = u.id
  LEFT JOIN locations l ON li.location_id = l.id
  " . ($activeLoc ? " WHERE li.location_id = " . intval($activeLoc) : "") . "
  ORDER BY li.scanned_at DESC LIMIT 10
";
$recentActivity = $db->fetchAll($recentSql);

$locations = $db->fetchAll("
  SELECT l.*,
    (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id) as total_clothes,
    (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'ACTIVE') as active_clothes,
    (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'IN_LAUNDRY') as in_laundry_clothes,
    (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'RESERVE') as reserve_clothes
  FROM locations l ORDER BY l.id ASC
");

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
      <div class="flex items-center justify-between text-slate-500 mb-2">
        <span class="text-xs font-bold uppercase tracking-wider">Összes Ruha</span>
        <div class="p-2 rounded-xl bg-slate-100 text-slate-700"><i data-lucide="layers" class="w-5 h-5"></i></div>
      </div>
      <p class="text-3xl font-black text-slate-900"><?php echo number_format($totalClothes, 0, ',', ' '); ?> db</p>
      <span class="text-xs text-slate-400 font-medium">Leltárban rögzítve</span>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-amber-200 bg-amber-50/30 shadow-xs">
      <div class="flex items-center justify-between text-amber-600 mb-2">
        <span class="text-xs font-bold uppercase tracking-wider">Mosásban</span>
        <div class="p-2 rounded-xl bg-amber-100 text-amber-700"><i data-lucide="waves" class="w-5 h-5"></i></div>
      </div>
      <p class="text-3xl font-black text-amber-700"><?php echo number_format($inLaundry, 0, ',', ' '); ?> db</p>
      <span class="text-xs text-amber-600/80 font-medium">Mosodánál lévő tételek</span>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-emerald-200 bg-emerald-50/30 shadow-xs">
      <div class="flex items-center justify-between text-emerald-600 mb-2">
        <span class="text-xs font-bold uppercase tracking-wider">Dolgozónál</span>
        <div class="p-2 rounded-xl bg-emerald-100 text-emerald-700"><i data-lucide="user-check" class="w-5 h-5"></i></div>
      </div>
      <p class="text-3xl font-black text-emerald-700"><?php echo number_format($activeClothes, 0, ',', ' '); ?> db</p>
      <span class="text-xs text-emerald-600/80 font-medium">Kiosztott tiszta ruhák</span>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-blue-200 bg-blue-50/30 shadow-xs">
      <div class="flex items-center justify-between text-blue-600 mb-2">
        <span class="text-xs font-bold uppercase tracking-wider">Tartalék</span>
        <div class="p-2 rounded-xl bg-blue-100 text-blue-700"><i data-lucide="package" class="w-5 h-5"></i></div>
      </div>
      <p class="text-3xl font-black text-blue-700"><?php echo number_format($reserveClothes, 0, ',', ' '); ?> db</p>
      <span class="text-xs text-blue-600/80 font-medium">Raktáron lévő készlet</span>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-red-200 bg-red-50/30 shadow-xs">
      <div class="flex items-center justify-between text-red-600 mb-2">
        <span class="text-xs font-bold uppercase tracking-wider">Hiányzó / Selejt</span>
        <div class="p-2 rounded-xl bg-red-100 text-red-700"><i data-lucide="alert-triangle" class="w-5 h-5"></i></div>
      </div>
      <p class="text-3xl font-black text-red-700"><?php echo number_format($lostClothes, 0, ',', ' '); ?> db</p>
      <span class="text-xs text-red-600/80 font-medium">Elveszett vagy selejtezett</span>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
      <h3 class="font-bold text-slate-900 mb-4 flex items-center">
        <i data-lucide="pie-chart" class="w-4 h-4 mr-2 text-brand-600"></i> Ruhatípusok megoszlása
      </h3>
      <div class="h-64 relative flex items-center justify-center">
        <canvas id="categoryChart"></canvas>
      </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
      <h3 class="font-bold text-slate-900 mb-4 flex items-center">
        <i data-lucide="palette" class="w-4 h-4 mr-2 text-blue-600"></i> Színek megoszlása
      </h3>
      <div class="h-64 relative flex items-center justify-center">
        <canvas id="colorChart"></canvas>
      </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
      <div>
        <h3 class="font-bold text-slate-900 mb-4 flex items-center">
          <i data-lucide="map" class="w-4 h-4 mr-2 text-indigo-600"></i> Telephelyek Leltára
        </h3>
        <div class="space-y-3">
          <?php foreach ($locations as $loc): ?>
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
              <div class="flex items-center justify-between font-bold text-sm text-slate-800 mb-1">
                <span><?php echo escape($loc['code'] . '. ' . ($loc['short_name'] ?: $loc['name'])); ?></span>
                <span class="text-brand-600"><?php echo $loc['total_clothes']; ?> db ruha</span>
              </div>
              <div class="grid grid-cols-3 gap-2 text-xs text-slate-500 font-medium">
                <span>Dolgozónál: <strong class="text-emerald-700"><?php echo $loc['active_clothes']; ?></strong></span>
                <span>Mosásban: <strong class="text-amber-700"><?php echo $loc['in_laundry_clothes']; ?></strong></span>
                <span>Tartalék: <strong class="text-blue-700"><?php echo $loc['reserve_clothes']; ?></strong></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span>Teljes amortizációs leltárérték:</span>
        <span class="font-bold text-slate-900 text-sm"><?php echo number_format($totalNetValue, 0, ',', ' '); ?> Ft</span>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
      <h3 class="font-bold text-slate-900 flex items-center">
        <i data-lucide="history" class="w-4 h-4 mr-2 text-slate-500"></i> Legutóbbi Mosodai Mozgások
      </h3>
      <span class="text-xs text-slate-500 font-medium">Valós idejű audit napló</span>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50/50 text-slate-500 text-xs font-bold text-left uppercase">
          <tr>
            <th class="px-6 py-3">Időpont</th>
            <th class="px-6 py-3">Irány</th>
            <th class="px-6 py-3">Vonalkód</th>
            <th class="px-6 py-3">Megnevezés</th>
            <th class="px-6 py-3">Dolgozó</th>
            <th class="px-6 py-3">Telephely</th>
            <th class="px-6 py-3">Kezelő</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <?php if (empty($recentActivity)): ?>
            <tr><td colspan="7" class="px-6 py-6 text-center text-slate-400">Még nincs rögzített mosodai mozgás</td></tr>
          <?php else: ?>
            <?php foreach ($recentActivity as $r): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-mono text-xs text-slate-500"><?php echo date('Y.m.d H:i', strtotime($r['scanned_at'])); ?></td>
                <td class="px-6 py-3">
                  <span class="px-2.5 py-1 text-xs font-bold rounded-full <?php echo $r['direction'] === 'OUT' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'; ?>">
                    <?php echo $r['direction'] === 'OUT' ? 'Mosodába' : 'Mosodából'; ?>
                  </span>
                </td>
                <td class="px-6 py-3 font-mono font-bold text-slate-800"><?php echo escape($r['barcode']); ?></td>
                <td class="px-6 py-3 font-medium text-slate-900"><?php echo escape($r['cloth_name']); ?></td>
                <td class="px-6 py-3 text-slate-700"><?php echo escape($r['employee_name'] ?: 'Tartalék'); ?></td>
                <td class="px-6 py-3 text-slate-600"><?php echo escape($r['location_short'] ?: '-'); ?></td>
                <td class="px-6 py-3 text-slate-500 text-xs"><?php echo escape($r['user_name'] ?: '-'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const catLabels = <?php echo json_encode(array_column($categories, 'category')); ?>;
    const catData = <?php echo json_encode(array_column($categories, 'count')); ?>;
    new Chart(document.getElementById('categoryChart'), {
      type: 'doughnut',
      data: {
        labels: catLabels.length ? catLabels : ['Nincs adat'],
        datasets: [{ data: catData.length ? catData : [1], backgroundColor: ['#16a34a', '#2563eb', '#f59e0b', '#8b5cf6', '#64748b'] }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
    });

    const colLabels = <?php echo json_encode(array_column($colors, 'color')); ?>;
    const colData = <?php echo json_encode(array_column($colors, 'count')); ?>;
    new Chart(document.getElementById('colorChart'), {
      type: 'pie',
      data: {
        labels: colLabels.length ? colLabels : ['Nincs adat'],
        datasets: [{ data: colData.length ? colData : [1], backgroundColor: ['#e2e8f0', '#15803d', '#14532d', '#1d4ed8', '#94a3b8'] }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
    });
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
