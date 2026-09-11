<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';

if (!canEdit()) {
    setFlashMessage('danger', 'Megtekintő (Viewer) jogosultságú felhasználóval nem végezhető CSV exportálás!');
    redirect('dashboard.php');
}

$db = Database::getInstance();
$activeLoc = getActiveLocationId();

$where = ["1=1"];
$params = [];

if ($activeLoc) {
    $where[] = "c.location_id = ?";
    $params[] = intval($activeLoc);
}

$whereClause = implode(" AND ", $where);
$rows = $db->fetchAll("
    SELECT c.*, e.employee_code, e.last_name, e.first_name, e.is_reserve, l.code as location_code, l.name as location_name
    FROM clothes c
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE {$whereClause}
    ORDER BY l.code ASC, e.employee_code ASC, c.id ASC
", $params);

$filename = "hga_munkaruha_leltar_" . date('Y-m-d') . ".csv";

// Clean output buffer before sending headers
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM for Microsoft Excel compatibility
echo "\xEF\xBB\xBF";

// Semicolon-delimited header with standard CRLF
$headers = [
    'Költséghely',
    'SzC-megnevezés',
    'Költséghely-megnevezés',
    'Szekrény/fakk',
    'Dolgozó',
    'Vezetéknév',
    'Keresztnév',
    'Cikksz.',
    'Megnevezés',
    'Méret',
    'Vonalkód',
    'Óra/darab?',
    'StátuszMegnev',
    'Változat',
    'bevonás',
    'NévCímke',
    'Logó',
    'Módosítások',
    'Kiolvasás 1',
    'Beolvasás 1',
    'Aktuális nettó amortizációs érték'
];

echo implode(';', $headers) . "\r\n";

foreach ($rows as $r) {
    $locCode = $r['location_code'] ?: '1';
    $locName = $r['location_name'] ?: '';
    $locker = '';
    $empCode = $r['is_reserve'] ? '0082' : ($r['employee_code'] ?: '');
    $lastName = $r['is_reserve'] ? 'Tartalék' : ($r['last_name'] ?: '');
    $firstName = $r['is_reserve'] ? '' : ($r['first_name'] ?: '');
    $itemCode = $r['item_code'] ?: '';
    $itemName = $r['name'] ?: '';
    $size = $r['size'] ?: '';
    $barcode = $r['barcode'] ?: '';
    $unit = 'Q';
    
    $status = 'aktív';
    if ($r['status'] === 'IN_LAUNDRY') {
        $status = 'mosásban';
    } elseif ($r['status'] === 'LOST') {
        $status = 'elveszett';
    } elseif ($r['status'] === 'SCRAPPED') {
        $status = 'selejtezve';
    } elseif ($r['status'] === 'RESERVE') {
        $status = 'tartalék';
    }
    
    $variant = $r['variant'] ?: '-';
    $bevonas = '';
    $label = '';
    $logo = $r['logo'] ?: '';
    $notes = $r['notes'] ?: '';
    $sentDate = $r['last_sent_to_laundry'] ? date('d.m.Y', strtotime($r['last_sent_to_laundry'])) : '';
    $recvDate = $r['last_received_from_laundry'] ? date('d.m.Y', strtotime($r['last_received_from_laundry'])) : '';
    $netVal = $r['net_value'] ? number_format($r['net_value'], 0, ',', ' ') . ' Ft' : '';

    $rowFields = [
        $locCode,
        $locName,
        $locName,
        $locker,
        $empCode,
        $lastName,
        $firstName,
        $itemCode,
        $itemName,
        $size,
        $barcode,
        $unit,
        $status,
        $variant,
        $bevonas,
        $label,
        $logo,
        str_replace(["\r", "\n", ";"], [" ", " ", ","], $notes),
        $sentDate,
        $recvDate,
        $netVal
    ];

    echo implode(';', $rowFields) . "\r\n";
}
exit;
