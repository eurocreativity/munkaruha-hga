const express = require('express');
const router = express.Router();
const { db } = require('../db');
const { authenticateToken, requireRole } = require('../middleware/auth');

// Dolgozók listája
router.get('/', authenticateToken, (req, res) => {
  const { location_id, search, include_reserve } = req.query;

  let query = `
    SELECT 
      e.*,
      l.short_name as location_short,
      l.name as location_name,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id) as total_clothes,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'IN_LAUNDRY') as in_laundry_count,
      (SELECT COUNT(*) FROM clothes c WHERE c.employee_id = e.id AND c.status = 'ACTIVE') as active_count
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE e.active = 1
  `;
  const params = [];

  if (location_id) {
    query += ' AND e.location_id = ?';
    params.push(location_id);
  }

  if (include_reserve !== 'true' && include_reserve !== '1') {
    query += ' AND e.is_reserve = 0';
  }

  if (search && search.trim()) {
    query += ' AND (e.full_name LIKE ? OR e.employee_code LIKE ?)';
    params.push(`%${search.trim()}%`, `%${search.trim()}%`);
  }

  query += ' ORDER BY e.is_reserve ASC, e.last_name ASC, e.first_name ASC';

  const employees = db.prepare(query).all(...params);
  res.json({ employees });
});

// Egy dolgozó részletei és ruhái
router.get('/:id', authenticateToken, (req, res) => {
  const { id } = req.params;

  const employee = db.prepare(`
    SELECT e.*, l.name as location_name, l.short_name as location_short
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE e.id = ?
  `).get(id);

  if (!employee) {
    return res.status(404).json({ error: 'Dolgozó nem található' });
  }

  const clothes = db.prepare(`
    SELECT c.*, l.short_name as location_short
    FROM clothes c
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE c.employee_id = ?
    ORDER BY c.category ASC, c.name ASC
  `).all(id);

  res.json({ employee, clothes });
});

// Új dolgozó létrehozása
router.post('/', authenticateToken, requireRole(['admin', 'operator']), (req, res) => {
  const { employee_code, last_name, first_name, location_id, locker_number } = req.body;
  if (!employee_code || !last_name || !location_id) {
    return res.status(400).json({ error: 'Törzsszám, vezetéknév és telephely megadása kötelező' });
  }

  const fullName = `${last_name.trim()} ${(first_name || '').trim()}`.trim();

  const result = db.prepare(`
    INSERT INTO employees (employee_code, last_name, first_name, full_name, location_id, locker_number, is_reserve)
    VALUES (?, ?, ?, ?, ?, ?, 0)
  `).run(employee_code.trim(), last_name.trim(), (first_name || '').trim(), fullName, location_id, locker_number || '');

  db.prepare(`
    INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, location_id)
    VALUES (?, ?, 'CREATE_EMPLOYEE', 'EMPLOYEE', ?, ?, ?)
  `).run(req.user.id, req.user.username, String(result.lastInsertRowid), `Új dolgozó: ${fullName} (${employee_code})`, location_id);

  res.json({ success: true, id: result.lastInsertRowid });
});

// Dolgozó szerkesztése
router.put('/:id', authenticateToken, requireRole(['admin', 'operator']), (req, res) => {
  const { id } = req.params;
  const { employee_code, last_name, first_name, location_id, locker_number, active } = req.body;

  const fullName = `${(last_name || '').trim()} ${(first_name || '').trim()}`.trim();

  db.prepare(`
    UPDATE employees 
    SET employee_code = ?, last_name = ?, first_name = ?, full_name = ?, location_id = ?, locker_number = ?, active = ?
    WHERE id = ?
  `).run(employee_code, last_name, first_name, fullName, location_id, locker_number, active !== undefined ? active : 1, id);

  res.json({ success: true });
});

module.exports = router;
