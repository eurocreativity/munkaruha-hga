const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { db } = require('../db');
const { JWT_SECRET, authenticateToken, requireRole } = require('../middleware/auth');

// Bejelentkezés
router.post('/login', (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) {
    return res.status(400).json({ error: 'Felhasználónév és jelszó megadása kötelező' });
  }

  const user = db.prepare(`
    SELECT u.*, l.name as location_name, l.short_name as location_short
    FROM users u
    LEFT JOIN locations l ON u.default_location_id = l.id
    WHERE u.username = ? AND u.active = 1
  `).get(username.trim());

  if (!user || !bcrypt.compareSync(password, user.password_hash)) {
    return res.status(401).json({ error: 'Helytelen felhasználónév vagy jelszó' });
  }

  const token = jwt.sign(
    { id: user.id, username: user.username, role: user.role },
    JWT_SECRET,
    { expiresIn: '7d' }
  );

  // Naplózás
  db.prepare(`
    INSERT INTO audit_logs (user_id, username, action, entity_type, details, location_id)
    VALUES (?, ?, 'LOGIN', 'USER', 'Sikeres bejelentkezés', ?)
  `).run(user.id, user.username, user.default_location_id);

  res.json({
    token,
    user: {
      id: user.id,
      username: user.username,
      full_name: user.full_name,
      role: user.role,
      default_location_id: user.default_location_id,
      location_name: user.location_name,
      location_short: user.location_short
    }
  });
});

// Saját adatok lekérése
router.get('/me', authenticateToken, (req, res) => {
  const user = db.prepare(`
    SELECT u.id, u.username, u.full_name, u.role, u.default_location_id, l.name as location_name, l.short_name as location_short
    FROM users u
    LEFT JOIN locations l ON u.default_location_id = l.id
    WHERE u.id = ?
  `).get(req.user.id);

  res.json({ user });
});

// Felhasználók listája (csak admin)
router.get('/users', authenticateToken, requireRole('admin'), (req, res) => {
  const users = db.prepare(`
    SELECT u.id, u.username, u.full_name, u.role, u.default_location_id, u.active, u.created_at, l.short_name as location_short
    FROM users u
    LEFT JOIN locations l ON u.default_location_id = l.id
    ORDER BY u.id ASC
  `).all();

  res.json({ users });
});

// Új felhasználó létrehozása (csak admin)
router.post('/users', authenticateToken, requireRole('admin'), (req, res) => {
  const { username, password, full_name, role, default_location_id } = req.body;
  if (!username || !password || !full_name) {
    return res.status(400).json({ error: 'Minden kötelező mezőt töltsön ki' });
  }

  const existing = db.prepare('SELECT id FROM users WHERE username = ?').get(username.trim());
  if (existing) {
    return res.status(400).json({ error: 'Ez a felhasználónév már létezik' });
  }

  const salt = bcrypt.genSaltSync(10);
  const passwordHash = bcrypt.hashSync(password, salt);

  const result = db.prepare(`
    INSERT INTO users (username, password_hash, full_name, role, default_location_id)
    VALUES (?, ?, ?, ?, ?)
  `).run(username.trim(), passwordHash, full_name.trim(), role || 'operator', default_location_id || null);

  db.prepare(`
    INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details)
    VALUES (?, ?, 'CREATE_USER', 'USER', ?, ?)
  `).run(req.user.id, req.user.username, String(result.lastInsertRowid), `Új felhasználó létrehozva: ${username}`);

  res.json({ success: true, id: result.lastInsertRowid });
});

// Felhasználó módosítása (csak admin)
router.put('/users/:id', authenticateToken, requireRole('admin'), (req, res) => {
  const { id } = req.params;
  const { password, full_name, role, default_location_id, active } = req.body;

  let query = 'UPDATE users SET full_name = ?, role = ?, default_location_id = ?, active = ?';
  const params = [full_name, role, default_location_id || null, active !== undefined ? active : 1];

  if (password && password.trim()) {
    const salt = bcrypt.genSaltSync(10);
    const passwordHash = bcrypt.hashSync(password.trim(), salt);
    query += ', password_hash = ?';
    params.push(passwordHash);
  }

  query += ' WHERE id = ?';
  params.push(id);

  db.prepare(query).run(...params);
  res.json({ success: true });
});

module.exports = router;
