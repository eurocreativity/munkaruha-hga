const express = require('express');
const router = express.Router();
const { db } = require('../db');
const { authenticateToken, requireRole } = require('../middleware/auth');

// Telephelyek listája statisztikával
router.get('/', authenticateToken, (req, res) => {
  const locations = db.prepare(`
    SELECT 
      l.*,
      (SELECT COUNT(*) FROM employees e WHERE e.location_id = l.id AND e.active = 1 AND e.is_reserve = 0) as employee_count,
      (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id) as total_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'ACTIVE') as active_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'IN_LAUNDRY') as in_laundry_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'RESERVE') as reserve_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.location_id = l.id AND c.status = 'LOST') as lost_clothes
    FROM locations l
    ORDER BY l.id ASC
  `).all();

  res.json({ locations });
});

// Új telephely (Admin)
router.post('/', authenticateToken, requireRole('admin'), (req, res) => {
  const { code, name, short_name, address } = req.body;
  if (!code || !name) {
    return res.status(400).json({ error: 'Kód és név megadása kötelező' });
  }

  const result = db.prepare(`
    INSERT INTO locations (code, name, short_name, address)
    VALUES (?, ?, ?, ?)
  `).run(code.trim(), name.trim(), short_name ? short_name.trim() : name.trim(), address ? address.trim() : '');

  res.json({ success: true, id: result.lastInsertRowid });
});

// Telephely módosítás (Admin)
router.put('/:id', authenticateToken, requireRole('admin'), (req, res) => {
  const { id } = req.params;
  const { code, name, short_name, address } = req.body;

  db.prepare(`
    UPDATE locations SET code = ?, name = ?, short_name = ?, address = ?
    WHERE id = ?
  `).run(code, name, short_name, address, id);

  res.json({ success: true });
});

module.exports = router;
