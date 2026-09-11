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
$scrappedClothes = $db->fetchOne("SELECT COUNT(*) as c FROM clothes WHERE status = 'SCRAPPED'" . $locAnd)['c'];
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
    (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'RESERVE') as reserve_clothes,
    (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'LOST') as lost_clothes,
    (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'SCRAPPED') as scrapped_clothes
  FROM locations l ORDER BY l.id ASC
");

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <!-- INTERAKTÍV FŐ STATISZTIKAI KÁRTYÁK (KATTINTÁSRA AZONNALI SZŰRÉS) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
    
    <!-- 1. Összes Ruha -->
    <a href="clothes.php" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:border-slate-400 hover:shadow-md hover:scale-[1.02] transition-all cursor-pointer flex flex-col justify-between block group">
      <div>
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-xs font-bold uppercase tracking-wider group-hover:text-slate-900 transition-colors">Összes Ruha</span>
          <div class="p-2 rounded-xl bg-slate-100 text-slate-700 group-hover:bg-slate-200"><i data-lucide="layers" class="w-4 h-4"></i></div>
        </div>
        <p class="text-2xl font-black text-slate-900"><?php echo number_format($totalClothes, 0, ',', ' '); ?> db</p>
      </div>
      <span class="text-[11px] text-slate-400 font-medium mt-2 flex items-center justify-between">
        <span>Teljes állomány</span>
        <span class="text-brand-600 font-bold opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
      </span>
    </a>

    <!-- 2. Mosásban -->
    <a href="in_laundry.php" class="bg-white p-5 rounded-2xl border border-amber-200 bg-amber-50/20 shadow-xs hover:border-amber-400 hover:shadow-md hover:scale-[1.02] transition-all cursor-pointer flex flex-col justify-between block group">
      <div>
        <div class="flex items-center justify-between text-amber-600 mb-2">
          <span class="text-xs font-bold uppercase tracking-wider group-hover:text-amber-800 transition-colors">Mosásban</span>
          <div class="p-2 rounded-xl bg-amber-100 text-amber-700 group-hover:bg-amber-200"><i data-lucide="waves" class="w-4 h-4"></i></div>
        </div>
        <p class="text-2xl font-black text-amber-700"><?php echo number_format($inLaundry, 0, ',', ' '); ?> db</p>
      </div>
      <span class="text-[11px] text-amber-700/80 font-medium mt-2 flex items-center justify-between">
        <span>Mosodánál lévő</span>
        <span class="text-amber-700 font-bold opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
      </span>
    </a>

    <!-- 3. Dolgozónál -->
    <a href="clothes.php?status=ACTIVE" class="bg-white p-5 rounded-2xl border border-emerald-200 bg-emerald-50/20 shadow-xs hover:border-emerald-400 hover:shadow-md hover:scale-[1.02] transition-all cursor-pointer flex flex-col justify-between block group">
      <div>
        <div class="flex items-center justify-between text-emerald-600 mb-2">
          <span class="text-xs font-bold uppercase tracking-wider group-hover:text-emerald-800 transition-colors">Dolgozónál</span>
          <div class="p-2 rounded-xl bg-emerald-100 text-emerald-700 group-hover:bg-emerald-200"><i data-lucide="user-check" class="w-4 h-4"></i></div>
        </div>
        <p class="text-2xl font-black text-emerald-700"><?php echo number_format($activeClothes, 0, ',', ' '); ?> db</p>
      </div>
      <span class="text-[11px] text-emerald-700/80 font-medium mt-2 flex items-center justify-between">
        <span>Kiosztott aktív</span>
        <span class="text-emerald-700 font-bold opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
      </span>
    </a>

    <!-- 4. Tartalék -->
    <a href="clothes.php?status=RESERVE" class="bg-white p-5 rounded-2xl border border-blue-200 bg-blue-50/20 shadow-xs hover:border-blue-400 hover:shadow-md hover:scale-[1.02] transition-all cursor-pointer flex flex-col justify-between block group">
      <div>
        <div class="flex items-center justify-between text-blue-600 mb-2">
          <span class="text-xs font-bold uppercase tracking-wider group-hover:text-blue-800 transition-colors">Tartalék</span>
          <div class="p-2 rounded-xl bg-blue-100 text-blue-700 group-hover:bg-blue-200"><i data-lucide="package" class="w-4 h-4"></i></div>
        </div>
        <p class="text-2xl font-black text-blue-700"><?php echo number_format($reserveClothes, 0, ',', ' '); ?> db</p>
      </div>
      <span class="text-[11px] text-blue-700/80 font-medium mt-2 flex items-center justify-between">
        <span>Raktáron lévő</span>
        <span class="text-blue-700 font-bold opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
      </span>
    </a>

    <!-- 5. Hiányzó / Elveszett -->
    <a href="clothes.php?status=LOST" class="bg-white p-5 rounded-2xl border border-red-200 bg-red-50/20 shadow-xs hover:border-red-400 hover:shadow-md hover:scale-[1.02] transition-all cursor-pointer flex flex-col justify-between block group">
      <div>
        <div class="flex items-center justify-between text-red-600 mb-2">
          <span class="text-xs font-bold uppercase tracking-wider group-hover:text-red-800 transition-colors">Hiányzó</span>
          <div class="p-2 rounded-xl bg-red-100 text-red-700 group-hover:bg-red-200"><i data-lucide="alert-circle" class="w-4 h-4"></i></div>
        </div>
        <p class="text-2xl font-black text-red-700"><?php echo number_format($lostClothes, 0, ',', ' '); ?> db</p>
      </div>
      <span class="text-[11px] text-red-700/80 font-medium mt-2 flex items-center justify-between">
        <span>Elveszett / nincs meg</span>
        <span class="text-red-700 font-bold opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
      </span>
    </a>

    <!-- 6. Selejtezve -->
    <a href="clothes.php?status=SCRAPPED" class="bg-white p-5 rounded-2xl border border-slate-300 bg-slate-100/40 shadow-xs hover:border-slate-500 hover:shadow-md hover:scale-[1.02] transition-all cursor-pointer flex flex-col justify-between block group">
      <div>
        <div class="flex items-center justify-between text-slate-700 mb-2">
          <span class="text-xs font-bold uppercase tracking-wider group-hover:text-slate-900 transition-colors">Selejtezett</span>
          <div class="p-2 rounded-xl bg-slate-200 text-slate-800 group-hover:bg-slate-300"><i data-lucide="trash-2" class="w-4 h-4"></i></div>
        </div>
        <p class="text-2xl font-black text-slate-800"><?php echo number_format($scrappedClothes, 0, ',', ' '); ?> db</p>
      </div>
      <span class="text-[11px] text-slate-600 font-medium mt-2 flex items-center justify-between">
        <span>Selejtezett tételek</span>
        <span class="text-slate-800 font-bold opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
      </span>
    </a>

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
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
              <div class="flex items-center justify-between font-bold text-sm text-slate-800">
                <span><?php echo escape($loc['code'] . '. ' . ($loc['short_name'] ?: $loc['name'])); ?></span>
                <span class="text-brand-600"><?php echo $loc['total_clothes']; ?> db ruha</span>
              </div>
              <div class="grid grid-cols-3 gap-1.5 text-[11px] text-slate-500 font-medium">
                <span>Dolgozónál: <strong class="text-emerald-700"><?php echo $loc['active_clothes']; ?></strong></span>
                <span>Mosásban: <strong class="text-amber-700"><?php echo $loc['in_laundry_clothes']; ?></strong></span>
                <span>Tartalék: <strong class="text-blue-700"><?php echo $loc['reserve_clothes']; ?></strong></span>
              </div>
              <div class="grid grid-cols-2 gap-1.5 text-[11px] text-slate-500 font-medium pt-1 border-t border-slate-200/60">
                <span>Hiányzó: <strong class="text-red-700"><?php echo $loc['lost_clothes']; ?></strong></span>
                <span>Selejt: <strong class="text-slate-800"><?php echo $loc['scrapped_clothes']; ?></strong></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 font-semibold">
        <span>Leltári összérték:</span>
        <span class="text-sm font-black text-slate-900"><?php echo number_format($totalNetValue, 0, ',', ' '); ?> Ft</span>
      </div>
    </div>
  </div>

  <!-- Utolsó aktivitások -->
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-slate-900 flex items-center">
        <i data-lucide="history" class="w-4 h-4 mr-2 text-slate-500"></i> Legutóbbi Mosodai Mozgások
      </h3>
      <a href="batches.php" class="text-xs text-brand-600 font-bold hover:underline">Összes megtekintése &rarr;</a>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 font-bold text-slate-600 text-left">
          <tr>
            <th class="py-2.5 px-3">Időpont</th>
            <th class="py-2.5 px-3">Vonalkód</th>
            <th class="py-2.5 px-3">Megnevezés</th>
            <th class="py-2.5 px-3">Dolgozó</th>
            <th class="py-2.5 px-3">Művelet</th>
            <th class="py-2.5 px-3">Telephely</th>
            <th class="py-2.5 px-3">Kezelő</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-mono">
          <?php if (empty($recentActivity)): ?>
            <tr><td colspan="7" class="py-4 text-center text-slate-400 font-sans">Még nincs rögzített mosodai mozgás.</td></tr>
          <?php else: ?>
            <?php foreach ($recentActivity as $act): ?>
              <tr>
                <td class="py-2.5 px-3 text-slate-500"><?php echo date('Y.m.d H:i', strtotime($act['scanned_at'])); ?></td>
                <td class="py-2.5 px-3 font-bold text-slate-900"><?php echo escape($act['barcode']); ?></td>
                <td class="py-2.5 px-3 font-sans font-medium text-slate-800"><?php echo escape($act['cloth_name']); ?></td>
                <td class="py-2.5 px-3 font-sans text-slate-600"><?php echo escape($act['employee_name'] ?: 'Tartalék'); ?></td>
                <td class="py-2.5 px-3 font-sans">
                  <?php if ($act['direction'] === 'OUT'): ?>
                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold">Mosodába küldve</span>
                  <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">Mosásból visszavéve</span>
                  <?php endif; ?>
                </td>
                <td class="py-2.5 px-3 font-sans text-slate-500"><?php echo escape($act['location_short'] ?: '-'); ?></td>
                <td class="py-2.5 px-3 font-sans text-slate-500"><?php echo escape($act['user_name'] ?: 'Rendszer'); ?></td>
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
  const catData = <?php echo json_encode($categories); ?>;
  const colData = <?php echo json_encode($colors); ?>;

  if (catData.length > 0) {
    new Chart(document.getElementById('categoryChart'), {
      type: 'doughnut',
      data: {
        labels: catData.map(d => d.category),
        datasets: [{
          data: catData.map(d => d.count),
          backgroundColor: ['#16a34a', '#2563eb', '#f59e0b', '#dc2626', '#8b5cf6', '#64748b']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }
      }
    });
  }

  if (colData.length > 0) {
    new Chart(document.getElementById('colorChart'), {
      type: 'pie',
      data: {
        labels: colData.map(d => d.color),
        datasets: [{
          data: colData.map(d => d.count),
          backgroundColor: ['#e2e8f0', '#15803d', '#166534', '#3b82f6', '#94a3b8']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
