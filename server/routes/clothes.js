const express = require('express');
const router = express.Router();
const { db } = require('../db');
const { authenticateToken, requireRole } = require('../middleware/auth');

// Ruhák szűrt listája
router.get('/', authenticateToken, (req, res) => {
  const { location_id, employee_id, status, category, color, search, limit, offset } = req.query;

  let query = `
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
    WHERE 1=1
  `;
  const params = [];

  if (location_id) {
    query += ' AND c.location_id = ?';
    params.push(location_id);
  }

  if (employee_id) {
    query += ' AND c.employee_id = ?';
    params.push(employee_id);
  }

  if (status) {
    query += ' AND c.status = ?';
    params.push(status);
  }

  if (category) {
    query += ' AND c.category = ?';
    params.push(category);
  }

  if (color) {
    query += ' AND c.color = ?';
    params.push(color);
  }

  if (search && search.trim()) {
    const s = `%${search.trim()}%`;
    query += ' AND (c.barcode LIKE ? OR c.item_code LIKE ? OR c.name LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ?)';
    params.push(s, s, s, s, s);
  }

  query += ' ORDER BY c.updated_at DESC, c.id DESC';

  if (limit) {
    query += ' LIMIT ? OFFSET ?';
    params.push(parseInt(limit, 10), parseInt(offset || 0, 10));
  }

  const clothes = db.prepare(query).all(...params);

  // Összes találat száma szűréshez
  let countQuery = 'SELECT COUNT(*) as total FROM clothes c LEFT JOIN employees e ON c.employee_id = e.id WHERE 1=1';
  const countParams = [];
  if (location_id) { countQuery += ' AND c.location_id = ?'; countParams.push(location_id); }
  if (employee_id) { countQuery += ' AND c.employee_id = ?'; countParams.push(employee_id); }
  if (status) { countQuery += ' AND c.status = ?'; countParams.push(status); }
  if (category) { countQuery += ' AND c.category = ?'; countParams.push(category); }
  if (color) { countQuery += ' AND c.color = ?'; countParams.push(color); }
  if (search && search.trim()) {
    const s = `%${search.trim()}%`;
    countQuery += ' AND (c.barcode LIKE ? OR c.item_code LIKE ? OR c.name LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ?)';
    countParams.push(s, s, s, s, s);
  }

  const { total } = db.prepare(countQuery).get(...countParams);

  res.json({ clothes, total });
});

// Vonalkód szerinti közvetlen keresés (gyors adatlap)
router.get('/by-barcode/:barcode', authenticateToken, (req, res) => {
  const barcode = req.params.barcode.trim();

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
  `).get(barcode);

  if (!cloth) {
    return res.status(404).json({ error: 'Nincs találat erre a vonalkódra' });
  }

  // Mosási előzmények lekérése
  const history = db.prepare(`
    SELECT li.*, u.full_name as user_name, l.short_name as location_short, b.batch_number
    FROM laundry_items li
    LEFT JOIN users u ON li.user_id = u.id
    LEFT JOIN locations l ON li.location_id = l.id
    LEFT JOIN laundry_batches b ON li.batch_id = b.id
    WHERE li.cloth_id = ?
    ORDER BY li.scanned_at DESC
    LIMIT 20
  `).all(cloth.id);

  res.json({ cloth, history });
});

// Új ruha felvétele
router.post('/', authenticateToken, requireRole(['admin', 'operator']), (req, res) => {
  const { barcode, item_code, name, category, color, size, employee_id, location_id, status, notes, net_value } = req.body;
  if (!barcode || !name || !location_id) {
    return res.status(400).json({ error: 'Vonalkód, megnevezés és telephely kötelező' });
  }

  const existing = db.prepare('SELECT id FROM clothes WHERE barcode = ?').get(barcode.trim());
  if (existing) {
    return res.status(400).json({ error: 'Ez a vonalkód már szerepel a rendszerben!' });
  }

  const result = db.prepare(`
    INSERT INTO clothes (
      barcode, item_code, name, category, color, size, employee_id, location_id,
      status, notes, net_value, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
  `).run(
    barcode.trim(),
    item_code ? item_code.trim() : '',
    name.trim(),
    category || 'Egyéb',
    color || 'Egyéb',
    size || '',
    employee_id || null,
    location_id,
    status || 'ACTIVE',
    notes || '',
    net_value ? parseFloat(net_value) : 0
  );

  db.prepare(`
    INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
    VALUES (?, ?, 'CREATE_CLOTH', 'CLOTH', ?, ?, ?)
  `).run(req.user.id, req.user.username, String(result.lastInsertRowid), `Új ruha felvéve: ${name} (${barcode})`, location_id);

  res.json({ success: true, id: result.lastInsertRowid });
});

// Ruha szerkesztése / áthelyezése / státusz váltás
router.put('/:id', authenticateToken, requireRole(['admin', 'operator']), (req, res) => {
  const { id } = req.params;
  const { barcode, item_code, name, category, color, size, employee_id, location_id, status, notes, net_value } = req.body;

  db.prepare(`
    UPDATE clothes 
    SET barcode = ?, item_code = ?, name = ?, category = ?, color = ?, size = ?,
        employee_id = ?, location_id = ?, status = ?, notes = ?, net_value = ?, updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
  `).run(
    barcode.trim(),
    item_code,
    name,
    category,
    color,
    size,
    employee_id || null,
    location_id,
    status,
    notes,
    net_value ? parseFloat(net_value) : 0,
    id
  );

  db.prepare(`
    INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
    VALUES (?, ?, 'UPDATE_CLOTH', 'CLOTH', ?, ?, ?)
  `).run(req.user.id, req.user.username, String(id), `Ruha módosítva: ${barcode}`, location_id);

  res.json({ success: true });
});

module.exports = router;
