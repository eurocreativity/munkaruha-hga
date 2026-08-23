const express = require('express');
const router = express.Router();
const { db } = require('../db');
const { authenticateToken, requireRole } = require('../middleware/auth');

// Tevékenységi napló lekérése
router.get('/', authenticateToken, requireRole('admin'), (req, res) => {
  const { limit, action, search } = req.query;

  let query = `
    SELECT a.*, l.short_name as location_short
    FROM audit_logs a
    LEFT JOIN locations l ON a.location_id = l.id
    WHERE 1=1
  `;
  const params = [];

  if (action) {
    query += ' AND a.action = ?';
    params.push(action);
  }

  if (search && search.trim()) {
    query += ' AND (a.username LIKE ? OR a.details LIKE ? OR a.entity_id LIKE ?)';
    const s = `%${search.trim()}%`;
    params.push(s, s, s);
  }

  query += ' ORDER BY a.created_at DESC';

  if (limit) {
    query += ' LIMIT ?';
    params.push(parseInt(limit, 10));
  } else {
    query += ' LIMIT 100';
  }

  const logs = db.prepare(query).all(...params);
  res.json({ logs });
});

module.exports = router;
