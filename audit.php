<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak rendszergazdák tekinthetik meg az audit naplót!');
    redirect('dashboard.php');
}

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

$search = trim($_GET['search'] ?? '');
$filterAction = $_GET['action_filter'] ?? '';

$where = ["1=1"];
$params = [];

if ($activeLoc) {
    $where[] = "(a.location_id = ? OR a.location_id IS NULL)";
    $params[] = intval($activeLoc);
}
if ($filterAction) {
    $where[] = "a.action = ?";
    $params[] = $filterAction;
}
if ($search) {
    $where[] = "(a.username LIKE ? OR a.details LIKE ? OR a.entity_id LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}

$whereClause = implode(" AND ", $where);
$logs = $db->fetchAll("
    SELECT a.*, l.short_name as location_short
    FROM audit_logs a
    LEFT JOIN locations l ON a.location_id = l.id
    WHERE {$whereClause}
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 200
", $params);

$actions = $db->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <div class="flex items-center space-x-2">
        <h2 class="text-xl font-bold text-slate-900">Rendszer Eseménynapló (Audit Log)</h2>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700"><?php echo count($logs); ?> esemény</span>
      </div>
      <p class="text-xs text-slate-500">Minden vonalkód-olvasás, kézi rögzítés, módosítás és csomagművelet részletes naplója</p>
    </div>

    <!-- Szűrők -->
    <form method="GET" action="audit.php" class="flex flex-wrap items-center gap-3">
      <div class="relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Keresés felhasználó, részlet..."
          class="pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>
      <select name="action_filter" onchange="this.form.submit()" class="py-2 px-3 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
        <option value="">Összes művelettípus</option>
        <?php foreach ($actions as $act): ?>
          <option value="<?php echo escape($act['action']); ?>" <?php echo $filterAction === $act['action'] ? 'selected' : ''; ?>>
            <?php echo escape($act['action']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-left">
          <tr>
            <th class="px-6 py-3.5">Időpont</th>
            <th class="px-6 py-3.5">Felhasználó</th>
            <th class="px-6 py-3.5">Művelet</th>
            <th class="px-6 py-3.5">Érintett Elem</th>
            <th class="px-6 py-3.5">Részletes Leírás</th>
            <th class="px-6 py-3.5 text-right">Telephely</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">Még nincs rögzített esemény a naplóban.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $l): ?>
              <?php
                $badgeColor = 'bg-slate-100 text-slate-700';
                if (strpos($l['action'], 'SCAN') !== false) $badgeColor = 'bg-brand-100 text-brand-800 font-bold';
                elseif (strpos($l['action'], 'CANCEL') !== false || strpos($l['action'], 'REMOVE') !== false) $badgeColor = 'bg-red-100 text-red-800 font-bold';
                elseif (strpos($l['action'], 'CREATE') !== false) $badgeColor = 'bg-emerald-100 text-emerald-800 font-bold';
                elseif (strpos($l['action'], 'UPDATE') !== false) $badgeColor = 'bg-blue-100 text-blue-800 font-bold';
              ?>
              <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-mono text-slate-500"><?php echo date('Y.m.d H:i:s', strtotime($l['created_at'])); ?></td>
                <td class="px-6 py-3 font-bold text-slate-800"><?php echo escape($l['username'] ?: 'Rendszer'); ?></td>
                <td class="px-6 py-3">
                  <span class="px-2 py-0.5 rounded text-[11px] font-mono <?php echo $badgeColor; ?>">
                    <?php echo escape($l['action']); ?>
                  </span>
                </td>
                <td class="px-6 py-3 text-slate-600 font-mono"><?php echo escape($l['entity_type'] . ($l['entity_id'] ? " #{$l['entity_id']}" : '')); ?></td>
                <td class="px-6 py-3 text-slate-900 font-medium"><?php echo escape($l['details']); ?></td>
                <td class="px-6 py-3 text-right text-slate-600"><?php echo escape($l['location_short'] ?: '-'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
