const jwt = require('jsonwebtoken');
const { db } = require('../db');

const JWT_SECRET = process.env.JWT_SECRET || 'hga-biomed-munkaruha-secret-key-2026';

function authenticateToken(req, res, next) {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];

  if (!token) {
    return res.status(401).json({ error: 'Nincs bejelentkezve (Hiányzó token)' });
  }

  jwt.verify(token, JWT_SECRET, (err, decoded) => {
    if (err) {
      return res.status(403).json({ error: 'Érvénytelen vagy lejárt munkamenet' });
    }
    
    // Friss felhasználó lekérése az adatbázisból
    const user = db.prepare('SELECT id, username, full_name, role, default_location_id, active FROM users WHERE id = ?').get(decoded.id);
    if (!user || !user.active) {
      return res.status(403).json({ error: 'A felhasználó inaktív vagy nem található' });
    }

    req.user = user;
    next();
  });
}

function requireRole(roles) {
  return (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ error: 'Nincs hitelesítve' });
    }
    if (typeof roles === 'string') roles = [roles];
    if (!roles.includes(req.user.role) && req.user.role !== 'admin') {
      return res.status(403).json({ error: 'Nincs jogosultsága ehhez a művelethez' });
    }
    next();
  };
}

module.exports = {
  JWT_SECRET,
  authenticateToken,
  requireRole
};
