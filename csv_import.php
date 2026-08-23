<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

if (!canEdit()) {
    setFlashMessage('danger', 'Megtekintő (Viewer) jogosultságú felhasználóval nem végezhető CSV importálás!');
    redirect('clothes.php');
}

$db = Database::getInstance();
$message = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        $message = 'Érvénytelen CSRF token!';
        $msgType = 'danger';
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Hiba a fájlfeltöltés során!';
        $msgType = 'danger';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $raw = file_get_contents($file);
        $lines = preg_split('/\r\n|\r|\n/', $raw);

        $imported = 0;
        $db->beginTransaction();
        try {
            foreach ($lines as $idx => $line) {
                if ($idx === 0) continue;
                $line = trim($line);
                if (!$line) continue;
                $cols = explode(';', $line);
                if (count($cols) < 11) continue;

                $locCode = trim($cols[0] ?? '');
                if (!is_numeric($locCode)) continue;
                $locId = intval($locCode);

                $empCode = trim($cols[4] ?? '');
                $lastName = trim($cols[5] ?? '');
                $firstName = trim($cols[6] ?? '');
                $itemCode = trim($cols[7] ?? '');
                $itemName = trim($cols[8] ?? '');
                $size = trim($cols[9] ?? '');
                $barcode = trim($cols[10] ?? '');
                $variant = trim($cols[13] ?? '');
                $logo = trim($cols[16] ?? '');
                $notes = trim($cols[17] ?? ($cols[21] ?? ''));
                $netValStr = trim($cols[20] ?? '');
                preg_match('/\d+/', str_replace(' ', '', $netValStr), $matches);
                $netVal = !empty($matches[0]) ? floatval($matches[0]) : 0;

                if (!$barcode) continue;

                $isReserve = (stripos($lastName, 'tartalék') !== false || empty(trim($lastName . $firstName))) ? 1 : 0;
                $fullName = trim("{$lastName} {$firstName}");
                if ($isReserve) {
                    $fullName = "Tartalék (" . ($locId == 1 ? 'Jutai út' : 'Nagygát u.') . ")";
                    $empCode = ($locId == 1 ? '0082' : '0083');
                }

                $emp = $db->fetchOne("SELECT id FROM employees WHERE employee_code = ? AND location_id = ?", [$empCode, $locId]);
                if (!$emp) {
                    $db->execute("
                        INSERT INTO employees (employee_code, last_name, first_name, full_name, location_id, is_reserve)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ", [$empCode, $lastName ?: 'Tartalék', $firstName, $fullName, $locId, $isReserve]);
                    $empId = $db->lastInsertId();
                } else {
                    $empId = $emp['id'];
                }

                $n = strtolower($itemName);
                $c = strtoupper($itemCode);
                $cat = 'Egyéb';
                if (strpos($n, 'póló') !== false || strpos($c, 'TSA') === 0) $cat = 'Póló';
                elseif (strpos($n, 'köp') !== false || strpos($c, '01F') === 0 || strpos($c, '02F') === 0 || strpos($c, 'W2S') === 0) $cat = 'Köpeny';
                elseif (strpos($n, 'nadr') !== false || strpos($c, '04F') === 0 || strpos($c, '15F') === 0 || strpos($c, 'W4S') === 0 || strpos($c, 'W5S') === 0) $cat = 'Nadrág';
                elseif (strpos($n, 'kazak') !== false || strpos($c, 'W3S') === 0) $cat = 'Kazak';

                $col = 'Egyéb';
                if (strpos($n, 'fehér') !== false || strpos($n, 'whitel') !== false) $col = 'Fehér';
                elseif (strpos($n, 'bottlezöld') !== false) $col = 'Bottlezöld';
                elseif (strpos($n, 'zöld') !== false) $col = 'Zöld';
                elseif (strpos($n, 'kék') !== false) $col = 'Kék';

                $status = $isReserve ? 'RESERVE' : 'ACTIVE';
                if (stripos($notes, 'nincs meg') !== false || stripos($notes, 'elveszett') !== false) {
                    $status = 'LOST';
                }

                $db->execute("
                    INSERT INTO clothes (barcode, item_code, name, category, color, size, employee_id, location_id, status, variant, logo, notes, net_value)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        item_code = VALUES(item_code),
                        name = VALUES(name),
                        category = VALUES(category),
                        color = VALUES(color),
                        size = VALUES(size),
                        employee_id = VALUES(employee_id),
                        location_id = VALUES(location_id),
                        variant = VALUES(variant),
                        logo = VALUES(logo),
                        notes = VALUES(notes),
                        net_value = VALUES(net_value),
                        status = IF(status = 'IN_LAUNDRY', 'IN_LAUNDRY', VALUES(status))
                ", [$barcode, $itemCode, $itemName, $cat, $col, $size, $empId, $locId, $status, $variant, $logo, $notes, $netVal]);

                $imported++;
            }
            $db->commit();
            $message = "Sikeresen importálva és frissítve {$imported} db munkaruha!";
            $msgType = 'success';
        } catch (Exception $e) {
            $db->rollback();
            $message = "Hiba történt az importálás közben: " . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
  <?php if ($message): ?>
    <div class="p-4 rounded-xl border flex items-center space-x-3 <?php echo $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'; ?>">
      <i data-lucide="<?php echo $msgType === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5"></i>
      <span class="font-medium text-sm"><?php echo escape($message); ?></span>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4">
      <div>
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
          <i data-lucide="download" class="w-6 h-6"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-900">Leltár Exportálása (CSV)</h3>
        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
          Letölti a teljes adatbázist a HGA Biomed pontosvesszővel tagolt szabványos CSV formátumában.
          Ez a fájl közvetlenül megnyitható Excelben is.
        </p>
      </div>
      <a href="csv_export.php" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded-xl transition-all flex items-center justify-center space-x-2 shadow-sm">
        <i data-lucide="file-down" class="w-4 h-4"></i>
        <span>Aktuális CSV Letöltése</span>
      </a>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4">
      <div>
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
          <i data-lucide="upload" class="w-6 h-6"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-900">Frissített Leltár Betöltése (CSV Import)</h3>
        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
          Amennyiben a vezetőségtől új leltár érkezik, itt egy gombnyomással beimportálhatja.
          A meglévő vonalkódok frissülnek, az újak hozzáadódnak.
        </p>
      </div>

      <form method="POST" action="csv_import.php" enctype="multipart/form-data" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
        <input type="file" name="csv_file" accept=".csv,text/csv" required
          class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-xl transition-all flex items-center justify-center space-x-2 shadow-sm">
          <i data-lucide="file-up" class="w-4 h-4"></i>
          <span>CSV Fájl Feldolgozása</span>
        </button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
