const { db, initSchema } = require('./db');
const bcrypt = require('bcryptjs');
const fs = require('fs');
const path = require('path');

function parseCategory(name, itemCode) {
  const n = (name || '').toLowerCase();
  const c = (itemCode || '').toUpperCase();
  if (n.includes('póló') || c.startsWith('TSA')) return 'Póló';
  if (n.includes('köp') || n.includes('köpeny') || c.startsWith('01F') || c.startsWith('02F') || c.startsWith('W2S')) return 'Köpeny';
  if (n.includes('nadr') || n.includes('nadrág') || c.startsWith('04F') || c.startsWith('15F') || c.startsWith('W4S') || c.startsWith('W5S')) return 'Nadrág';
  if (n.includes('kazak') || c.startsWith('W3S')) return 'Kazak';
  return 'Egyéb';
}

function parseColor(name) {
  const n = (name || '').toLowerCase();
  if (n.includes('fehér') || n.includes('whitel')) return 'Fehér';
  if (n.includes('bottlezöld')) return 'Bottlezöld';
  if (n.includes('zöld')) return 'Zöld';
  if (n.includes('kék')) return 'Kék';
  return 'Egyéb';
}

function parseCurrency(val) {
  if (!val) return 0;
  const num = val.replace(/[^0-9]/g, '');
  return num ? parseFloat(num) : 0;
}

function parseDate(val) {
  if (!val || !val.trim()) return null;
  // Format: 26.10.2023 or 2023.10.26
  const parts = val.trim().split('.');
  if (parts.length >= 3) {
    if (parts[0].length === 4) {
      return `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')} 12:00:00`;
    } else {
      return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')} 12:00:00`;
    }
  }
  return null;
}

async function seed() {
  initSchema();
  console.log('--- Kezdő adatok feltöltése ---');

  // 1. Telephelyek
  const insertLocation = db.prepare(`
    INSERT OR IGNORE INTO locations (id, code, name, short_name, address)
    VALUES (?, ?, ?, ?, ?)
  `);

  insertLocation.run(1, '1', 'HGA Biomed, Kap, Jutai 50.', 'Jutai út 50.', '7400 Kaposvár, Jutai út 50.');
  insertLocation.run(2, '2', 'HGA Biomed, Kap, Nagygát utca 1', 'Nagygát u. 1.', '7400 Kaposvár, Nagygát utca 1.');
  console.log('Telephelyek ellenőrizve/létrehozva.');

  // 2. Felhasználók
  const salt = bcrypt.genSaltSync(10);
  const insertUser = db.prepare(`
    INSERT OR IGNORE INTO users (username, password_hash, full_name, role, default_location_id)
    VALUES (?, ?, ?, ?, ?)
  `);

  insertUser.run('admin', bcrypt.hashSync('admin123', salt), 'Rendszergazda', 'admin', 1);
  insertUser.run('jutai_operator', bcrypt.hashSync('jutai123', salt), 'Jutai úti Raktáros', 'operator', 1);
  insertUser.run('nagygat_operator', bcrypt.hashSync('nagygat123', salt), 'Nagygát úti Raktáros', 'operator', 2);
  insertUser.run('vezeto', bcrypt.hashSync('vezeto123', salt), 'Hanna (Vezető)', 'viewer', 1);
  console.log('Alapértelmezett felhasználók létrehozva.');

  // 3. Kezdő CSV leltár beolvasása
  const csvFile = path.join(__dirname, '..', 'data', 'initial_leltar.csv');
  if (fs.existsSync(csvFile)) {
    const rawContent = fs.readFileSync(csvFile, 'utf8');
    const lines = rawContent.split(/\r?\n/);
    
    // Előkészített SQL lekérdezések
    const findEmployee = db.prepare('SELECT id FROM employees WHERE employee_code = ? AND location_id = ?');
    const insertEmployee = db.prepare(`
      INSERT INTO employees (employee_code, last_name, first_name, full_name, location_id, is_reserve, locker_number)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `);

    const insertCloth = db.prepare(`
      INSERT OR REPLACE INTO clothes (
        barcode, item_code, name, category, color, size, employee_id, location_id,
        status, variant, logo, notes, net_value, last_sent_to_laundry, last_received_from_laundry, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    `);

    let importedCount = 0;
    const insertMany = db.transaction(() => {
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
        const sentDateStr = (cols[18] || '').trim();
        const receivedDateStr = (cols[19] || '').trim();
        const netValueStr = (cols[20] || '').trim();

        if (!barcode) continue;

        // Dolgozó azonosítása vagy létrehozása
        let isReserve = 0;
        let fullName = `${lastName} ${firstName}`.trim();
        if (lastName.toLowerCase().includes('tartalék') || !fullName) {
          isReserve = 1;
          fullName = `Tartalék (${locCode === '1' ? 'Jutai út' : 'Nagygát u.'})`;
        }

        let emp = findEmployee.get(empCode || (isReserve ? 'RESERVE_' + locCode : 'UNKNOWN'), locationId);
        let empId = null;

        if (!emp) {
          const res = insertEmployee.run(
            empCode || (isReserve ? 'RESERVE_' + locCode : 'UNKNOWN'),
            lastName || 'Tartalék',
            firstName || '',
            fullName,
            locationId,
            isReserve,
            ''
          );
          empId = res.lastInsertRowid;
        } else {
          empId = emp.id;
        }

        const category = parseCategory(itemName, itemCode);
        const color = parseColor(itemName);
        const netValue = parseCurrency(netValueStr);
        const sentDate = parseDate(sentDateStr);
        const receivedDate = parseDate(receivedDateStr);

        let status = 'ACTIVE';
        if (notes.toLowerCase().includes('nincs meg') || notes.toLowerCase().includes('elveszett')) {
          status = 'LOST';
        } else if (isReserve) {
          status = 'RESERVE';
        }

        insertCloth.run(
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
          netValue,
          sentDate,
          receivedDate
        );
        importedCount++;
      }
    });

    insertMany();
    console.log(`Sikeresen importálva ${importedCount} db munkaruha tételes leltár!`);
  }

  console.log('--- Seeding kész! ---');
}

seed();
