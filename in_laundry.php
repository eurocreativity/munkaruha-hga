<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

$where = ["c.status = 'IN_LAUNDRY'"];
$params = [];

if ($activeLoc) {
    $where[] = "c.location_id = ?";
    $params[] = intval($activeLoc);
}

$whereClause = implode(" AND ", $where);
$inLaundry = $db->fetchAll("
    SELECT c.*, e.full_name as employee_name, e.employee_code, l.short_name as location_short,
      DATEDIFF(NOW(), c.last_sent_to_laundry) as days_in_laundry
    FROM clothes c
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE {$whereClause}
    ORDER BY c.last_sent_to_laundry ASC
", $params);

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <div class="flex items-center space-x-2">
        <h2 class="text-xl font-bold text-slate-900">Jelenleg Mosásban Lévő Ruhák</h2>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800"><?php echo count($inLaundry); ?> db</span>
      </div>
      <p class="text-xs text-slate-500">Melyik ruha van elküldve a mosodába és mióta nem érkezett vissza</p>
    </div>
    <button onclick="window.print()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition-all flex items-center space-x-2">
      <i data-lucide="printer" class="w-4 h-4"></i>
      <span>Hiánylista Nyomtatása</span>
    </button>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold text-left">
          <tr>
            <th class="px-6 py-3.5">Vonalkód</th>
            <th class="px-6 py-3.5">Megnevezés</th>
            <th class="px-6 py-3.5">Kategória / Szín</th>
            <th class="px-6 py-3.5">Méret</th>
            <th class="px-6 py-3.5">Dolgozó</th>
            <th class="px-6 py-3.5">Telephely</th>
            <th class="px-6 py-3.5">Elküldés Dátuma</th>
            <th class="px-6 py-3.5">Eltelt Idő</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <?php if (empty($inLaundry)): ?>
            <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Jelenleg egyetlen ruha sincs mosodában.</td></tr>
          <?php else: ?>
            <?php foreach ($inLaundry as $c): ?>
              <?php 
                $days = $c['days_in_laundry'] ?: 0;
                $badge = "<span class='px-2 py-0.5 text-xs font-bold rounded-full bg-slate-100 text-slate-700'>{$days} napja</span>";
                if ($days > 14) {
                    $badge = "<span class='px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800 animate-pulse'>⚠️ {$days} napja (Késik!)</span>";
                } elseif ($days > 7) {
                    $badge = "<span class='px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800'>{$days} napja</span>";
                }
              ?>
              <tr class="hover:bg-slate-50">
                <td class="px-6 py-3.5 font-mono font-bold text-slate-900"><?php echo escape($c['barcode']); ?></td>
                <td class="px-6 py-3.5 font-medium text-slate-900"><?php echo escape($c['name']); ?></td>
                <td class="px-6 py-3.5 text-slate-600"><?php echo escape($c['category'] . ' / ' . ($c['color'] ?: '-')); ?></td>
                <td class="px-6 py-3.5 font-mono text-slate-600"><?php echo escape($c['size'] ?: '-'); ?></td>
                <td class="px-6 py-3.5 font-medium text-slate-900"><?php echo escape($c['employee_name'] ?: 'Tartalék'); ?></td>
                <td class="px-6 py-3.5 text-slate-600"><?php echo escape($c['location_short'] ?: '-'); ?></td>
                <td class="px-6 py-3.5 font-mono text-xs text-slate-500"><?php echo $c['last_sent_to_laundry'] ? date('Y.m.d H:i', strtotime($c['last_sent_to_laundry'])) : '-'; ?></td>
                <td class="px-6 py-3.5"><?php echo $badge; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
