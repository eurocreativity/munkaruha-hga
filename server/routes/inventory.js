const express = require('express');
const router = express.Router();
const multer = require('multer');
const { db } = require('../db');
const { authenticateToken, requireRole } = require('../middleware/auth');

const upload = multer({ storage: multer.memoryStorage() });

// Vezérlőpult összesítő statisztikák
router.get('/stats', authenticateToken, (req, res) => {
  const { location_id } = req.query;

  let whereLoc = '';
  const params = [];
  if (location_id) {
    whereLoc = ' WHERE location_id = ?';
    params.push(location_id);
  }

  const totalClothes = db.prepare(`SELECT COUNT(*) as count FROM clothes${whereLoc}`).get(...params).count;
  
  const inLaundryQuery = location_id ? ' WHERE status = ? AND location_id = ?' : ' WHERE status = ?';
  const inLaundryParams = location_id ? ['IN_LAUNDRY', location_id] : ['IN_LAUNDRY'];
  const inLaundry = db.prepare(`SELECT COUNT(*) as count FROM clothes${inLaundryQuery}`).get(...inLaundryParams).count;

  const activeQuery = location_id ? ' WHERE status = ? AND location_id = ?' : ' WHERE status = ?';
  const activeParams = location_id ? ['ACTIVE', location_id] : ['ACTIVE'];
  const active = db.prepare(`SELECT COUNT(*) as count FROM clothes${activeQuery}`).get(...activeParams).count;

  const reserveQuery = location_id ? ' WHERE status = ? AND location_id = ?' : ' WHERE status = ?';
  const reserveParams = location_id ? ['RESERVE', location_id] : ['RESERVE'];
  const reserve = db.prepare(`SELECT COUNT(*) as count FROM clothes${reserveQuery}`).get(...reserveParams).count;

  const lostQuery = location_id ? ' WHERE status = ? AND location_id = ?' : ' WHERE status = ?';
  const lostParams = location_id ? ['LOST', location_id] : ['LOST'];
  const lost = db.prepare(`SELECT COUNT(*) as count FROM clothes${lostQuery}`).get(...lostParams).count;

  const totalValueQuery = location_id ? 'SELECT SUM(net_value) as sum FROM clothes WHERE location_id = ?' : 'SELECT SUM(net_value) as sum FROM clothes';
  const totalValue = db.prepare(totalValueQuery).get(...(location_id ? [location_id] : [])).sum || 0;

  // Kategóriák szerinti megoszlás
  let catQuery = 'SELECT category, COUNT(*) as count FROM clothes';
  if (location_id) catQuery += ' WHERE location_id = ?';
  catQuery += ' GROUP BY category';
  const categories = db.prepare(catQuery).all(...params);

  // Színek szerinti megoszlás
  let colorQuery = 'SELECT color, COUNT(*) as count FROM clothes';
  if (location_id) colorQuery += ' WHERE location_id = ?';
  colorQuery += ' GROUP BY color';
  const colors = db.prepare(colorQuery).all(...params);

  // Legutóbbi 10 mozgás
  let recentQuery = `
    SELECT li.*, c.name as cloth_name, c.barcode, e.full_name as employee_name, u.full_name as user_name, l.short_name as location_short
    FROM laundry_items li
    JOIN clothes c ON li.cloth_id = c.id
    LEFT JOIN employees e ON c.employee_id = e.id
    LEFT JOIN users u ON li.user_id = u.id
    LEFT JOIN locations l ON li.location_id = l.id
  `;
  if (location_id) recentQuery += ' WHERE li.location_id = ?';
  recentQuery += ' ORDER BY li.scanned_at DESC LIMIT 10';
  const recentActivity = db.prepare(recentQuery).all(...(location_id ? [location_id] : []));

  res.json({
    totalClothes,
    inLaundry,
    active,
    reserve,
    lost,
    totalValue,
    categories,
    colors,
    recentActivity
  });
});

// CSV Export (HGA formátum)
router.get('/export-csv', authenticateToken, (req, res) => {
  const { location_id } = req.query;

  let query = `
    SELECT 
      c.*,
      e.employee_code,
      e.last_name,
      e.first_name,
      e.is_reserve,
      l.code as location_code,
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
  query += ' ORDER BY l.code ASC, e.employee_code ASC, c.id ASC';

  const rows = db.prepare(query).all(...params);

  let csv = 'Költséghely;SzC-megnevezés;Költséghely-megnevezés;Szekrény/fakk;Dolgozó;Vezetéknév;Keresztnév;Cikksz.;Megnevezés;Méret;Vonalkód;Óra/darab?;StátuszMegnev;Változat;bevonás;NévCímke;Logó;Módosítások;Kiolvasás 1;Beolvasás 1; Aktuális nettó amortizációs érték ;\n';

  rows.forEach(r => {
    const locCode = r.location_code || '1';
    const locName = r.location_name || '';
    const locker = '';
    const empCode = r.is_reserve ? '0082' : (r.employee_code || '');
    const lastName = r.is_reserve ? 'Tartalék' : (r.last_name || '');
    const firstName = r.is_reserve ? '' : (r.first_name || '');
    const itemCode = r.item_code || '';
    const itemName = r.name || '';
    const size = r.size || '';
    const barcode = r.barcode || '';
    const unit = 'Q';
    const status = r.status === 'ACTIVE' ? 'aktív' : (r.status === 'IN_LAUNDRY' ? 'mosásban' : (r.status === 'LOST' ? 'elveszett' : 'tartalék'));
    const variant = r.variant || '-';
    const bevonas = '';
    const label = '';
    const logo = r.logo || '';
    const notes = r.notes || '';
    const sentDate = r.last_sent_to_laundry ? r.last_sent_to_laundry.slice(0, 10).split('-').reverse().join('.') : '';
    const recvDate = r.last_received_from_laundry ? r.last_received_from_laundry.slice(0, 10).split('-').reverse().join('.') : '';
    const netVal = r.net_value ? ` ${Math.round(r.net_value).toLocaleString('hu-HU')} Ft ` : '';

    csv += `${locCode};${locName};${locName};${locker};${empCode};${lastName};${firstName};${itemCode};${itemName};${size};${barcode};${unit};${status};${variant};${bevonas};${label};${logo};${notes};${sentDate};${recvDate};${netVal};\n`;
  });

  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', `attachment; filename=hga_munkaruha_leltar_${new Date().toISOString().slice(0, 10)}.csv`);
  // Add UTF-8 BOM so Excel opens it with Hungarian accents correctly
  res.send('\uFEFF' + csv);
});

// CSV Import funkció (új/frissített leltárak betöltésére)
router.post('/import-csv', authenticateToken, requireRole(['admin', 'operator']), upload.single('file'), (req, res) => {
  if (!req.file) {
    return res.status(400).json({ error: 'Kérjük, töltsön fel egy CSV fájlt!' });
  }

  try {
    const rawContent = req.file.buffer.toString('utf8');
    const lines = rawContent.split(/\r?\n/);

    const findEmployee = db.prepare('SELECT id FROM employees WHERE employee_code = ? AND location_id = ?');
    const insertEmployee = db.prepare(`
      INSERT INTO employees (employee_code, last_name, first_name, full_name, location_id, is_reserve, locker_number)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `);
    const upsertCloth = db.prepare(`
      INSERT INTO clothes (
        barcode, item_code, name, category, color, size, employee_id, location_id,
        status, variant, logo, notes, net_value, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
      ON CONFLICT(barcode) DO UPDATE SET
        item_code = excluded.item_code,
        name = excluded.name,
        category = excluded.category,
        color = excluded.color,
        size = excluded.size,
        employee_id = excluded.employee_id,
        location_id = excluded.location_id,
        variant = excluded.variant,
        logo = excluded.logo,
        notes = excluded.notes,
        net_value = excluded.net_value,
        updated_at = CURRENT_TIMESTAMP
    `);

    let importedCount = 0;

    const processImport = db.transaction(() => {
      for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;
        const cols = line.split(';');
        if (cols.length < 11) continue;

        const locCode = (cols[0] || '').trim();
        if (!locCode || isNaN(parseInt(locCode, 10))) continue;
        const locationId = parseInt(locCode, 10);

        const empCode = (cols[4] || '').trim();
        const lastName = (cols[5] || '').trim();
        const firstName = (cols[6] || '').trim();
        const itemCode = (cols[7] || '').trim();
        const itemName = (cols[8] || '').trim();
        const size = (cols[9] || '').trim();
        const barcode = (cols[10] || '').trim();
        const variant = (cols[13] || '').trim();
        const logo = (cols[16] || '').trim();
        const notes = (cols[17] || cols[21] || '').trim();
        const netValueStr = (cols[20] || '').trim();

        if (!barcode) continue;

        let isReserve = 0;
        let fullName = `${lastName} ${firstName}`.trim();
        if (lastName.toLowerCase().includes('tartalék') || !fullName) {
          isReserve = 1;
          fullName = `Tartalék (${locCode === '1' ? 'Jutai út' : 'Nagygát u.'})`;
        }

        let emp = findEmployee.get(empCode || (isReserve ? 'RESERVE_' + locCode : 'UNKNOWN'), locationId);
        let empId = null;

        if (!emp) {
          const resEmp = insertEmployee.run(
            empCode || (isReserve ? 'RESERVE_' + locCode : 'UNKNOWN'),
            lastName || 'Tartalék',
            firstName || '',
            fullName,
            locationId,
            isReserve,
            ''
          );
          empId = resEmp.lastInsertRowid;
        } else {
          empId = emp.id;
        }

        // Kategória és szín felismerése
        const n = itemName.toLowerCase();
        const c = itemCode.toUpperCase();
        let category = 'Egyéb';
        if (n.includes('póló') || c.startsWith('TSA')) category = 'Póló';
        else if (n.includes('köp') || n.includes('köpeny') || c.startsWith('01F') || c.startsWith('02F') || c.startsWith('W2S')) category = 'Köpeny';
        else if (n.includes('nadr') || n.includes('nadrág') || c.startsWith('04F') || c.startsWith('15F') || c.startsWith('W4S') || c.startsWith('W5S')) category = 'Nadrág';
        else if (n.includes('kazak') || c.startsWith('W3S')) category = 'Kazak';

        let color = 'Egyéb';
        if (n.includes('fehér') || n.includes('whitel')) color = 'Fehér';
        else if (n.includes('bottlezöld')) color = 'Bottlezöld';
        else if (n.includes('zöld')) color = 'Zöld';
        else if (n.includes('kék')) color = 'Kék';

        const numVal = netValueStr.replace(/[^0-9]/g, '');
        const netValue = numVal ? parseFloat(numVal) : 0;

        let status = isReserve ? 'RESERVE' : 'ACTIVE';
        if (notes.toLowerCase().includes('nincs meg') || notes.toLowerCase().includes('elveszett')) {
          status = 'LOST';
        }

        upsertCloth.run(
          barcode,
          itemCode,
          itemName,
          category,
          color,
          size,
          empId,
          locationId,
          status,
          variant,
          logo,
          notes,
          netValue
        );
        importedCount++;
      }
    });

    processImport();

    db.prepare(`
      INSERT INTO audit_logs (user_id, username, action, entity_type, details)
      VALUES (?, ?, 'IMPORT_CSV', 'INVENTORY', ?)
    `).run(req.user.id, req.user.username, `CSV Leltár importálva: ${importedCount} db tétel`);

    res.json({ success: true, count: importedCount, message: `Sikeresen feldolgozva és frissítve ${importedCount} db munkaruha!` });
  } catch (err) {
    console.error('Import hiba:', err);
    res.status(500).json({ error: 'Hiba történt a CSV feldolgozása közben: ' + err.message });
  }
});

module.exports = router;
