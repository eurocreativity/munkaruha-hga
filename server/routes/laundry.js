const express = require('express');
const router = express.Router();
const { db } = require('../db');
const { authenticateToken, requireRole } = require('../middleware/auth');

// Új tétel beolvasása (Gyors vonalkódos mosodai kezelő)
router.post('/scan', authenticateToken, requireRole(['admin', 'operator']), (req, res) => {
  const { barcode, direction, location_id, batch_id } = req.body;
  if (!barcode || !direction) {
    return res.status(400).json({ error: 'Vonalkód és irány (OUT/IN) megadása kötelező' });
  }

  const cleanBarcode = barcode.trim();
  const dir = direction.toUpperCase(); // 'OUT' (Mosodába) vagy 'IN' (Visszavétel)
  const locId = location_id || req.user.default_location_id || 1;

  // 1. Ruha megkeresése
  const cloth = db.prepare(`
    SELECT 
      c.*,
      e.full_name as employee_name,
      e.employee_code,
      e.is_reserve as employee_is_reserve,
      l.short_name as location_short,
      l.name as location_name
    FROM clothes c
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE c.barcode = ?
  `).get(cleanBarcode);

  if (!cloth) {
    return res.status(404).json({
      success: false,
      sound: 'error',
      message: `Ismeretlen vonalkód: ${cleanBarcode}! Nincs ilyen ruha a rendszerben.`
    });
  }

  // 2. Csomag / Batch kezelése
  let currentBatchId = batch_id;
  if (!currentBatchId) {
    // Keresünk egy mai nyitott csomagot az adott irányhoz és telephelyhez, vagy csinálunk újat
    const today = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const prefix = dir === 'OUT' ? 'MOS-KI' : 'MOS-BE';
    
    let batch = db.prepare(`
      SELECT * FROM laundry_batches 
      WHERE direction = ? AND location_id = ? AND status = 'IN_PROGRESS'
      ORDER BY id DESC LIMIT 1
    `).get(dir, locId);

    if (!batch) {
      const batchNumber = `${prefix}-${today}-${Math.floor(1000 + Math.random() * 9000)}`;
      const result = db.prepare(`
        INSERT INTO laundry_batches (batch_number, direction, location_id, user_id, status, item_count, created_at)
        VALUES (?, ?, ?, ?, 'IN_PROGRESS', 0, CURRENT_TIMESTAMP)
      `).run(batchNumber, dir, locId, req.user.id);
      currentBatchId = result.lastInsertRowid;
    } else {
      currentBatchId = batch.id;
    }
  }

  // 3. Ellenőrzés: szerepel-e már ebben a csomagban
  const existingInBatch = db.prepare(`
    SELECT id FROM laundry_items WHERE batch_id = ? AND cloth_id = ?
  `).get(currentBatchId, cloth.id);

  if (existingInBatch) {
    return res.json({
      success: false,
      sound: 'warning',
      already_scanned: true,
      message: `Ez a ruha (${cloth.name}) MÁR BE LETT OLVASVA a mostani csomagba!`,
      cloth,
      batch_id: currentBatchId
    });
  }

  // 4. Státusz frissítés és naplózás tranzakcióban
  let soundType = 'success';
  let statusMessage = '';

  const processScan = db.transaction(() => {
    let newStatus = cloth.status;
    if (dir === 'OUT') {
      newStatus = 'IN_LAUNDRY';
      statusMessage = `${cloth.name} elküldve mosodába (${cloth.employee_name || 'Tartalék'})`;
      db.prepare(`
        UPDATE clothes 
        SET status = 'IN_LAUNDRY', last_sent_to_laundry = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
      `).run(cloth.id);
    } else {
      // IN: Mosodából visszaérkezett
      newStatus = cloth.employee_is_reserve ? 'RESERVE' : 'ACTIVE';
      statusMessage = `${cloth.name} visszavételezve mosodából (${cloth.employee_name || 'Tartalék'})`;
      db.prepare(`
        UPDATE clothes 
        SET status = ?, last_received_from_laundry = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
      `).run(newStatus, cloth.id);
    }

    // Tétel hozzáadása a csomaghoz
    db.prepare(`
      INSERT INTO laundry_items (batch_id, cloth_id, barcode, direction, location_id, user_id, scanned_at)
      VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    `).run(currentBatchId, cloth.id, cleanBarcode, dir, locId, req.user.id);

    // Csomag darabszámának növelése
    db.prepare(`
      UPDATE laundry_batches 
      SET item_count = item_count + 1 
      WHERE id = ?
    `).run(currentBatchId);

    // Audit napló
    db.prepare(`
      INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
      VALUES (?, ?, ?, 'LAUNDRY', ?, ?, ?)
    `).run(req.user.id, req.user.username, dir === 'OUT' ? 'LAUNDRY_OUT' : 'LAUNDRY_IN', String(cloth.id), statusMessage, locId);
  });

  processScan();

  // Friss csomag adatok lekérése
  const batchInfo = db.prepare('SELECT * FROM laundry_batches WHERE id = ?').get(currentBatchId);

  res.json({
    success: true,
    sound: soundType,
    message: statusMessage,
    cloth: {
      ...cloth,
      status: dir === 'OUT' ? 'IN_LAUNDRY' : (cloth.employee_is_reserve ? 'RESERVE' : 'ACTIVE')
    },
    batch: batchInfo
  });
});

// Csomag lezárása / véglegesítése
router.post('/batch/finish', authenticateToken, requireRole(['admin', 'operator']), (req, res) => {
  const { batch_id, notes } = req.body;
  if (!batch_id) {
    return res.status(400).json({ error: 'Csomag azonosító szükséges' });
  }

  db.prepare(`
    UPDATE laundry_batches 
    SET status = 'COMPLETED', completed_at = CURRENT_TIMESTAMP, notes = COALESCE(?, notes)
    WHERE id = ?
  `).run(notes || null, batch_id);

  res.json({ success: true, message: 'Csomag sikeresen lezárva!' });
});

// Új üres csomag indítása manuálisan
router.post('/batch/create', authenticateToken, requireRole(['admin', 'operator']), (req, res) => {
  const { direction, location_id, notes } = req.body;
  const dir = (direction || 'OUT').toUpperCase();
  const locId = location_id || req.user.default_location_id || 1;
  const today = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const prefix = dir === 'OUT' ? 'MOS-KI' : 'MOS-BE';
  const batchNumber = `${prefix}-${today}-${Math.floor(1000 + Math.random() * 9000)}`;

  const result = db.prepare(`
    INSERT INTO laundry_batches (batch_number, direction, location_id, user_id, status, notes, item_count, created_at)
    VALUES (?, ?, ?, ?, 'IN_PROGRESS', ?, 0, CURRENT_TIMESTAMP)
  `).run(batchNumber, dir, locId, req.user.id, notes || '');

  const batch = db.prepare('SELECT * FROM laundry_batches WHERE id = ?').get(result.lastInsertRowid);
  res.json({ success: true, batch });
});

// Csomagok listája
router.get('/batches', authenticateToken, (req, res) => {
  const { location_id, direction, limit } = req.query;

  let query = `
    SELECT 
      b.*,
      l.short_name as location_short,
      l.name as location_name,
      u.full_name as user_name
    FROM laundry_batches b
    LEFT JOIN locations l ON b.location_id = l.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE 1=1
  `;
  const params = [];

  if (location_id) {
    query += ' AND b.location_id = ?';
    params.push(location_id);
  }

  if (direction) {
    query += ' AND b.direction = ?';
    params.push(direction);
  }

  query += ' ORDER BY b.created_at DESC';

  if (limit) {
    query += ' LIMIT ?';
    params.push(parseInt(limit, 10));
  }

  const batches = db.prepare(query).all(...params);
  res.json({ batches });
});

// Egy csomag részletei a beolvasott ruhák tételes listájával (szállítólevél / átadás-átvételi ív)
router.get('/batch/:id', authenticateToken, (req, res) => {
  const { id } = req.params;

  const batch = db.prepare(`
    SELECT 
      b.*,
      l.short_name as location_short,
      l.name as location_name,
      l.address as location_address,
      u.full_name as user_name
    FROM laundry_batches b
    LEFT JOIN locations l ON b.location_id = l.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
  `).get(id);

  if (!batch) {
    return res.status(404).json({ error: 'Csomag nem található' });
  }

  const items = db.prepare(`
    SELECT 
      li.id as scan_id,
      li.scanned_at,
      c.id as cloth_id,
      c.barcode,
      c.item_code,
      c.name as cloth_name,
      c.category,
      c.color,
      c.size,
      c.net_value,
      e.full_name as employee_name,
      e.employee_code
    FROM laundry_items li
    JOIN clothes c ON li.cloth_id = c.id
    LEFT JOIN employees e ON c.employee_id = e.id
    WHERE li.batch_id = ?
    ORDER BY li.scanned_at ASC
  `).all(id);

  // Kategóriánkénti összesítő statisztika (pl. 5 db póló, 4 db nadrág)
  const categoryCounts = {};
  items.forEach(item => {
    categoryCounts[item.category] = (categoryCounts[item.category] || 0) + 1;
  });

  res.json({ batch, items, categoryCounts });
});

// Jelenleg mosodában lévő összes ruha (hiánylista)
router.get('/in-laundry', authenticateToken, (req, res) => {
  const { location_id } = req.query;

  let query = `
    SELECT 
      c.*,
      e.full_name as employee_name,
      e.employee_code,
      l.short_name as location_short,
      l.name as location_name,
      CAST((julianday('now') - julianday(c.last_sent_to_laundry)) AS INTEGER) as days_in_laundry
    FROM clothes c
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE c.status = 'IN_LAUNDRY'
  `;
  const params = [];

  if (location_id) {
    query += ' AND c.location_id = ?';
    params.push(location_id);
  }

  query += ' ORDER BY c.last_sent_to_laundry ASC';

  const inLaundry = db.prepare(query).all(...params);
  res.json({ inLaundry, total: inLaundry.length });
});

module.exports = router;
