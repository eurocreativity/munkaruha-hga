<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $data['action'] ?? 'scan';

$db = Database::getInstance();
$currentUser = getCurrentUser();

if ($action === 'scan') {
    $barcode = trim($data['barcode'] ?? '');
    $direction = strtoupper($data['direction'] ?? 'OUT');
    $locationId = intval($data['location_id'] ?? $currentUser['location_id'] ?: 1);
    $batchId = !empty($data['batch_id']) ? intval($data['batch_id']) : null;

    if (empty($barcode)) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó vonalkód!']);
        exit();
    }

    $cloth = $db->fetchOne("
        SELECT c.*, e.full_name as employee_name, e.employee_code, e.is_reserve as employee_is_reserve, l.short_name as location_short
        FROM clothes c
        LEFT JOIN employees e ON c.employee_id = e.id
        LEFT JOIN locations l ON c.location_id = l.id
        WHERE c.barcode = ?
    ", [$barcode]);

    if (!$cloth) {
        http_response_code(404);
        echo json_encode(['success' => false, 'sound' => 'error', 'message' => "Ismeretlen vonalkód: {$barcode}! Nincs ilyen ruha a rendszerben."]);
        exit();
    }

    if (!$batchId) {
        $batch = $db->fetchOne("
            SELECT * FROM laundry_batches 
            WHERE direction = ? AND location_id = ? AND status = 'IN_PROGRESS'
            ORDER BY id DESC LIMIT 1
        ", [$direction, $locationId]);

        if (!$batch) {
            $today = date('Ymd');
            $prefix = $direction === 'OUT' ? 'MOS-KI' : 'MOS-BE';
            $batchNum = "{$prefix}-{$today}-" . rand(1000, 9999);
            $db->execute("
                INSERT INTO laundry_batches (batch_number, direction, location_id, user_id, status, item_count, created_at)
                VALUES (?, ?, ?, ?, 'IN_PROGRESS', 0, NOW())
            ", [$batchNum, $direction, $locationId, $currentUser['id']]);
            $batchId = $db->lastInsertId();
            $batch = $db->fetchOne("SELECT * FROM laundry_batches WHERE id = ?", [$batchId]);
        } else {
            $batchId = $batch['id'];
        }
    } else {
        $batch = $db->fetchOne("SELECT * FROM laundry_batches WHERE id = ?", [$batchId]);
    }

    $alreadyScanned = $db->fetchOne("SELECT id FROM laundry_items WHERE batch_id = ? AND cloth_id = ?", [$batchId, $cloth['id']]);
    if ($alreadyScanned) {
        echo json_encode([
            'success' => false,
            'already_scanned' => true,
            'sound' => 'warning',
            'message' => "Ez a ruha ({$cloth['name']}) MÁR BE LETT OLVASVA a mostani csomagba!",
            'cloth' => $cloth,
            'batch' => $batch
        ]);
        exit();
    }

    $db->beginTransaction();
    try {
        if ($direction === 'OUT') {
            $db->execute("UPDATE clothes SET status = 'IN_LAUNDRY', last_sent_to_laundry = NOW() WHERE id = ?", [$cloth['id']]);
            $statusMsg = "{$cloth['name']} elküldve mosodába (" . ($cloth['employee_name'] ?: 'Tartalék') . ")";
        } else {
            $newStatus = $cloth['employee_is_reserve'] ? 'RESERVE' : 'ACTIVE';
            $db->execute("UPDATE clothes SET status = ?, last_received_from_laundry = NOW() WHERE id = ?", [$newStatus, $cloth['id']]);
            $statusMsg = "{$cloth['name']} visszavételezve mosodából (" . ($cloth['employee_name'] ?: 'Tartalék') . ")";
        }

        $db->execute("
            INSERT INTO laundry_items (batch_id, cloth_id, barcode, direction, location_id, user_id, scanned_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ", [$batchId, $cloth['id'], $barcode, $direction, $locationId, $currentUser['id']]);

        $db->execute("UPDATE laundry_batches SET item_count = item_count + 1 WHERE id = ?", [$batchId]);

        $db->execute("
            INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
            VALUES (?, ?, ?, 'LAUNDRY', ?, ?, ?)
        ", [$currentUser['id'], $currentUser['username'], $direction === 'OUT' ? 'LAUNDRY_OUT' : 'LAUNDRY_IN', (string)$cloth['id'], $statusMsg, $locationId]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Hiba mentéskor: ' . $e->getMessage()]);
        exit();
    }

    $updatedBatch = $db->fetchOne("SELECT * FROM laundry_batches WHERE id = ?", [$batchId]);
    $updatedCloth = $db->fetchOne("SELECT c.*, e.full_name as employee_name, l.short_name as location_short FROM clothes c LEFT JOIN employees e ON c.employee_id = e.id LEFT JOIN locations l ON c.location_id = l.id WHERE c.id = ?", [$cloth['id']]);

    echo json_encode([
        'success' => true,
        'sound' => 'success',
        'message' => $statusMsg,
        'cloth' => $updatedCloth,
        'batch' => $updatedBatch
    ]);
    exit();
}

if ($action === 'finish_batch') {
    $batchId = intval($data['batch_id'] ?? 0);
    if ($batchId > 0) {
        $db->execute("UPDATE laundry_batches SET status = 'COMPLETED', completed_at = NOW() WHERE id = ?", [$batchId]);
        echo json_encode(['success' => true, 'message' => 'Csomag lezárva!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen csomag azonosító!']);
    }
    exit();
}

if ($action === 'get_batch_details') {
    $batchId = intval($data['batch_id'] ?? 0);
    $batch = $db->fetchOne("
        SELECT b.*, l.name as location_name, l.short_name as location_short, l.address as location_address, u.full_name as user_name
        FROM laundry_batches b
        LEFT JOIN locations l ON b.location_id = l.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ", [$batchId]);

    $items = $db->fetchAll("
        SELECT li.id as scan_id, li.scanned_at, c.barcode, c.name as cloth_name, c.category, c.color, c.size, e.full_name as employee_name, e.employee_code
        FROM laundry_items li
        JOIN clothes c ON li.cloth_id = c.id
        LEFT JOIN employees e ON c.employee_id = e.id
        WHERE li.batch_id = ?
        ORDER BY li.scanned_at ASC
    ", [$batchId]);

    $categoryCounts = [];
    foreach ($items as $item) {
        $cat = $item['category'] ?: 'Egyéb';
        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    }

    echo json_encode(['success' => true, 'batch' => $batch, 'items' => $items, 'categoryCounts' => $categoryCounts]);
    exit();
}
