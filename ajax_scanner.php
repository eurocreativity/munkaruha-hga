<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve!']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_POST['action'] ?? '');

$modifyingActions = ['scan', 'manual_add_items', 'remove_item_from_batch', 'cancel_batch', 'complete_batch'];
if (in_array($action, $modifyingActions) && !canEdit()) {
    echo json_encode(['success' => false, 'message' => 'Megtekintő (Viewer) jogosultságú fiókkal nem hajtható végre módosítás!']);
    exit();
}

$db = Database::getInstance();
$currentUser = getCurrentUser();

// 0. Nyitott (folyamatban lévő) csomag lekérése oldalbetöltéskor
if ($action === 'get_current_batch') {
    $direction = strtoupper($data['direction'] ?? 'OUT');
    $locationId = intval($data['location_id'] ?? $currentUser['location_id'] ?: 1);

    $batch = $db->fetchOne("
        SELECT * FROM laundry_batches 
        WHERE direction = ? AND location_id = ? AND status = 'IN_PROGRESS'
        ORDER BY id DESC LIMIT 1
    ", [$direction, $locationId]);

    $items = [];
    if ($batch) {
        $items = $db->fetchAll("
            SELECT li.*, c.name as cloth_name, c.category, c.color, c.size, c.item_code,
                   e.full_name as employee_name, e.employee_code, l.short_name as location_short
            FROM laundry_items li
            JOIN clothes c ON li.cloth_id = c.id
            LEFT JOIN employees e ON c.employee_id = e.id
            LEFT JOIN locations l ON li.location_id = l.id
            WHERE li.batch_id = ?
            ORDER BY li.id DESC
        ", [$batch['id']]);
    }

    echo json_encode([
        'success' => true,
        'has_batch' => !empty($batch),
        'batch' => $batch,
        'items' => $items
    ]);
    exit();
}

// 1. Elérhető ruhák listája kézi kiválasztáshoz
if ($action === 'get_available_clothes') {
    $direction = strtoupper($data['direction'] ?? 'OUT');
    $locationId = !empty($data['location_id']) ? intval($data['location_id']) : null;
    $search = trim($data['search'] ?? '');
    $category = $data['category'] ?? '';

    $where = ["c.status != 'SCRAPPED'"];
    $params = [];

    if ($direction === 'OUT') {
        $where[] = "c.status != 'IN_LAUNDRY'";
    } else {
        $where[] = "c.status = 'IN_LAUNDRY'";
    }

    if ($locationId) {
        $where[] = "c.location_id = ?";
        $params[] = $locationId;
    }

    if ($category) {
        $where[] = "c.category = ?";
        $params[] = $category;
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
        ORDER BY e.is_reserve ASC, e.full_name ASC, c.name ASC
    ", $params);

    echo json_encode(['success' => true, 'clothes' => $clothes]);
    exit();
}

// 2. Több ruha kézi hozzáadása a csomaghoz egyszerre
if ($action === 'manual_add_items') {
    $clothIds = $data['cloth_ids'] ?? [];
    $direction = strtoupper($data['direction'] ?? 'OUT');
    $locationId = intval($data['location_id'] ?? $currentUser['location_id'] ?: 1);
    $batchId = !empty($data['batch_id']) ? intval($data['batch_id']) : null;

    if (empty($clothIds) || !is_array($clothIds)) {
        echo json_encode(['success' => false, 'message' => 'Nincs kiválasztva egyetlen ruha sem!']);
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
        } else {
            $batchId = $batch['id'];
        }
    }

    $addedCount = 0;
    $db->beginTransaction();
    try {
        foreach ($clothIds as $cid) {
            $cid = intval($cid);
            $cloth = $db->fetchOne("
                SELECT c.*, e.full_name as employee_name, e.is_reserve as employee_is_reserve
                FROM clothes c
                LEFT JOIN employees e ON c.employee_id = e.id
                WHERE c.id = ?
            ", [$cid]);

            if (!$cloth) continue;

            $already = $db->fetchOne("SELECT id FROM laundry_items WHERE batch_id = ? AND cloth_id = ?", [$batchId, $cid]);
            if ($already) continue;

            if ($direction === 'OUT') {
                $db->execute("UPDATE clothes SET status = 'IN_LAUNDRY', last_sent_to_laundry = NOW() WHERE id = ?", [$cid]);
            } else {
                $newStatus = $cloth['employee_is_reserve'] ? 'RESERVE' : 'ACTIVE';
                $db->execute("UPDATE clothes SET status = ?, last_received_from_laundry = NOW() WHERE id = ?", [$newStatus, $cid]);
            }

            $db->execute("
                INSERT INTO laundry_items (batch_id, cloth_id, barcode, direction, location_id, user_id, scanned_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [$batchId, $cid, $cloth['barcode'], $direction, $locationId, $currentUser['id']]);

            $addedCount++;
        }

        $db->execute("UPDATE laundry_batches SET item_count = item_count + ? WHERE id = ?", [$addedCount, $batchId]);

        $db->execute("
            INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
            VALUES (?, ?, 'MANUAL_BATCH_ADD', 'BATCH', ?, ?, ?)
        ", [$currentUser['id'], $currentUser['username'], $batchId, "Kézi hozzáadás a csomaghoz: {$addedCount} db ruha", $locationId]);

        $db->commit();

        $batch = $db->fetchOne("SELECT * FROM laundry_batches WHERE id = ?", [$batchId]);
        $items = $db->fetchAll("
            SELECT li.*, c.name as cloth_name, c.category, c.color, c.size, c.item_code,
                   e.full_name as employee_name, e.employee_code, l.short_name as location_short
            FROM laundry_items li
            JOIN clothes c ON li.cloth_id = c.id
            LEFT JOIN employees e ON c.employee_id = e.id
            LEFT JOIN locations l ON li.location_id = l.id
            WHERE li.batch_id = ?
            ORDER BY li.id DESC
        ", [$batchId]);

        echo json_encode([
            'success' => true,
            'message' => "{$addedCount} db ruha sikeresen hozzáadva a csomaghoz!",
            'batch' => $batch,
            'items' => $items
        ]);
        exit();
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Hiba a mentés során: ' . $e->getMessage()]);
        exit();
    }
}

// 3. Tétel eltávolítása az aktuális csomagból (Visszavonás / Törlés)
if ($action === 'remove_item_from_batch') {
    $clothId = intval($data['cloth_id'] ?? 0);
    $batchId = intval($data['batch_id'] ?? 0);

    if (!$clothId || !$batchId) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó azonosító!']);
        exit();
    }

    $item = $db->fetchOne("SELECT * FROM laundry_items WHERE batch_id = ? AND cloth_id = ?", [$batchId, $clothId]);
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'A tétel nem található a csomagban!']);
        exit();
    }

    $cloth = $db->fetchOne("
        SELECT c.*, e.is_reserve as employee_is_reserve 
        FROM clothes c 
        LEFT JOIN employees e ON c.employee_id = e.id 
        WHERE c.id = ?
    ", [$clothId]);

    $db->beginTransaction();
    try {
        // Státusz visszaállítása az előző állapotra
        if ($item['direction'] === 'OUT') {
            $prevStatus = ($cloth && $cloth['employee_is_reserve']) ? 'RESERVE' : 'ACTIVE';
            $db->execute("UPDATE clothes SET status = ? WHERE id = ?", [$prevStatus, $clothId]);
        } else {
            $db->execute("UPDATE clothes SET status = 'IN_LAUNDRY' WHERE id = ?", [$clothId]);
        }

        // Tétel törlése a csomagból
        $db->execute("DELETE FROM laundry_items WHERE id = ?", [$item['id']]);
        $db->execute("UPDATE laundry_batches SET item_count = GREATEST(0, item_count - 1) WHERE id = ?", [$batchId]);

        $db->execute("
            INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
            VALUES (?, ?, 'REMOVE_ITEM', 'BATCH', ?, ?, ?)
        ", [$currentUser['id'], $currentUser['username'], $batchId, "Tétel visszavonva a csomagból: {$cloth['name']} ({$cloth['barcode']})", $item['location_id']]);

        $db->commit();

        $items = $db->fetchAll("
            SELECT li.*, c.name as cloth_name, c.category, c.color, c.size, c.item_code,
                   e.full_name as employee_name, e.employee_code, l.short_name as location_short
            FROM laundry_items li
            JOIN clothes c ON li.cloth_id = c.id
            LEFT JOIN employees e ON c.employee_id = e.id
            LEFT JOIN locations l ON li.location_id = l.id
            WHERE li.batch_id = ?
            ORDER BY li.id DESC
        ", [$batchId]);

        // Ha a csomag kiürült (0 tétel maradt benne), automatikusan töröljük a csomag fejlécet is!
        if (empty($items)) {
            $db->execute("DELETE FROM laundry_batches WHERE id = ?", [$batchId]);
            $batch = null;
        } else {
            $batch = $db->fetchOne("SELECT * FROM laundry_batches WHERE id = ?", [$batchId]);
        }

        echo json_encode([
            'success' => true,
            'message' => "Ruha ({$cloth['name']}) sikeresen eltávolítva a csomagból!",
            'batch' => $batch,
            'items' => $items
        ]);
        exit();
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Hiba a törlés során: ' . $e->getMessage()]);
        exit();
    }
}

// 4. Teljes aktuális csomag visszavonása / törlése
if ($action === 'cancel_batch') {
    $batchId = intval($data['batch_id'] ?? 0);

    if (!$batchId) {
        echo json_encode(['success' => false, 'message' => 'Hiányzó csomag azonosító!']);
        exit();
    }

    $items = $db->fetchAll("
        SELECT li.*, c.employee_id, e.is_reserve as employee_is_reserve 
        FROM laundry_items li
        JOIN clothes c ON li.cloth_id = c.id
        LEFT JOIN employees e ON c.employee_id = e.id
        WHERE li.batch_id = ?
    ", [$batchId]);

    $db->beginTransaction();
    try {
        foreach ($items as $item) {
            if ($item['direction'] === 'OUT') {
                $prevStatus = $item['employee_is_reserve'] ? 'RESERVE' : 'ACTIVE';
                $db->execute("UPDATE clothes SET status = ? WHERE id = ?", [$prevStatus, $item['cloth_id']]);
            } else {
                $db->execute("UPDATE clothes SET status = 'IN_LAUNDRY' WHERE id = ?", [$item['cloth_id']]);
            }
        }

        $db->execute("DELETE FROM laundry_items WHERE batch_id = ?", [$batchId]);
        $db->execute("DELETE FROM laundry_batches WHERE id = ?", [$batchId]);

        $db->execute("
            INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details)
            VALUES (?, ?, 'CANCEL_BATCH', 'BATCH', ?, 'Folyamatban lévő csomag teljes törlése és tételek visszaállítása')
        ", [$currentUser['id'], $currentUser['username'], $batchId]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'A csomag sikeresen törölve, az összes ruha státusza visszaállt!'
        ]);
        exit();
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Hiba a csomag törlésekor: ' . $e->getMessage()]);
        exit();
    }
}

// 5. Egyedi Vonalkód beolvasás
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

    // Szigorú Logikai Állapotgép (State Machine) Ellenőrzés
    if ($direction === 'OUT') {
        if ($cloth['status'] === 'IN_LAUNDRY') {
            echo json_encode([
                'success' => false,
                'sound' => 'warning',
                'message' => "Ez a munkaruha ({$cloth['name']}) MÁR MOSODÁBAN VAN! Nem küldhető el újra."
            ]);
            exit();
        }
        if ($cloth['status'] === 'SCRAPPED') {
            echo json_encode([
                'success' => false,
                'sound' => 'error',
                'message' => "Ez a munkaruha ({$cloth['name']}) SELEJTEZVE VAN! Nem adható át mosodának."
            ]);
            exit();
        }
    } else {
        // IN irány (Visszavétel)
        if ($cloth['status'] !== 'IN_LAUNDRY') {
            $stLabels = ['ACTIVE' => 'Aktív (Dolgozónál)', 'RESERVE' => 'Tartalék', 'LOST' => 'Elveszett', 'SCRAPPED' => 'Selejtezve'];
            $stText = $stLabels[$cloth['status']] ?? $cloth['status'];
            echo json_encode([
                'success' => false,
                'sound' => 'warning',
                'message' => "Ez a ruha ({$cloth['name']}) NINCS MOSODÁBAN! (Jelenlegi státusza: {$stText}). Csak mosásban lévő ruha vehető vissza."
            ]);
            exit();
        }
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
            VALUES (?, ?, 'SCAN', 'CLOTH', ?, ?, ?)
        ", [$currentUser['id'], $currentUser['username'], $cloth['id'], $statusMsg, $locationId]);

        $db->commit();

        $updatedCloth = $db->fetchOne("SELECT * FROM clothes WHERE id = ?", [$cloth['id']]);
        $batch = $db->fetchOne("SELECT * FROM laundry_batches WHERE id = ?", [$batchId]);

        echo json_encode([
            'success' => true,
            'sound' => 'success',
            'message' => $statusMsg,
            'cloth' => $cloth,
            'batch' => $batch
        ]);
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'sound' => 'error', 'message' => 'Hiba az adatbázis művelet során: ' . $e->getMessage()]);
    }
    exit();
}

// 6. Csomag lezárása
if ($action === 'finish_batch') {
    $batchId = intval($data['batch_id'] ?? 0);
    $notes = trim($data['notes'] ?? '');

    if (!$batchId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Hiányzó csomag azonosító!']);
        exit();
    }

    $db->execute("
        UPDATE laundry_batches 
        SET status = 'COMPLETED', notes = ?, completed_at = NOW() 
        WHERE id = ?
    ", [$notes, $batchId]);

    $batch = $db->fetchOne("
        SELECT b.*, u.full_name as user_full_name, l.name as location_name, l.address as location_address
        FROM laundry_batches b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN locations l ON b.location_id = l.id
        WHERE b.id = ?
    ", [$batchId]);

    $items = $db->fetchAll("
        SELECT li.*, c.name as cloth_name, c.category, c.color, c.size, c.item_code,
               e.full_name as employee_name, e.employee_code
        FROM laundry_items li
        JOIN clothes c ON li.cloth_id = c.id
        LEFT JOIN employees e ON c.employee_id = e.id
        WHERE li.batch_id = ?
        ORDER BY li.id ASC
    ", [$batchId]);

    echo json_encode([
        'success' => true,
        'message' => "Csomag sikeresen lezárva ({$batch['batch_number']})!",
        'batch' => $batch,
        'items' => $items
    ]);
    exit();
}

// 7. Csomag részletei
if ($action === 'get_batch_details') {
    $batchId = intval($data['batch_id'] ?? 0);

    $batch = $db->fetchOne("
        SELECT b.*, u.full_name as user_full_name, l.name as location_name, l.address as location_address
        FROM laundry_batches b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN locations l ON b.location_id = l.id
        WHERE b.id = ?
    ", [$batchId]);

    if (!$batch) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'A csomag nem található!']);
        exit();
    }

    $items = $db->fetchAll("
        SELECT li.*, c.name as cloth_name, c.category, c.color, c.size, c.item_code,
               e.full_name as employee_name, e.employee_code
        FROM laundry_items li
        JOIN clothes c ON li.cloth_id = c.id
        LEFT JOIN employees e ON c.employee_id = e.id
        WHERE li.batch_id = ?
        ORDER BY li.id ASC
    ", [$batchId]);

    echo json_encode([
        'success' => true,
        'batch' => $batch,
        'items' => $items
    ]);
    exit();
}
