const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');

const dbPath = path.join(__dirname, '..', 'data', 'munkaruha.db');
const dbDir = path.dirname(dbPath);
if (!fs.existsSync(dbDir)) {
  fs.mkdirSync(dbDir, { recursive: true });
}

const db = new Database(dbPath);
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

function initSchema() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS locations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      code TEXT UNIQUE NOT NULL,
      name TEXT NOT NULL,
      short_name TEXT,
      address TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT UNIQUE NOT NULL,
      password_hash TEXT NOT NULL,
      full_name TEXT NOT NULL,
      role TEXT NOT NULL DEFAULT 'operator', -- 'admin', 'operator', 'viewer'
      default_location_id INTEGER,
      active INTEGER DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (default_location_id) REFERENCES locations(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS employees (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      employee_code TEXT NOT NULL,
      last_name TEXT NOT NULL,
      first_name TEXT,
      full_name TEXT NOT NULL,
      location_id INTEGER,
      is_reserve INTEGER DEFAULT 0,
      locker_number TEXT,
      active INTEGER DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS clothes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      barcode TEXT UNIQUE NOT NULL,
      item_code TEXT,
      name TEXT NOT NULL,
      category TEXT DEFAULT 'Egyéb', -- 'Póló', 'Köpeny', 'Nadrág', 'Kazak', 'Egyéb'
      color TEXT,
      size TEXT,
      employee_id INTEGER,
      location_id INTEGER,
      status TEXT DEFAULT 'ACTIVE', -- 'ACTIVE', 'IN_LAUNDRY', 'RESERVE', 'SCRAPPED', 'LOST'
      variant TEXT,
      logo TEXT,
      notes TEXT,
      net_value REAL DEFAULT 0,
      last_sent_to_laundry DATETIME,
      last_received_from_laundry DATETIME,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
      FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
    );

    CREATE INDEX IF NOT EXISTS idx_clothes_barcode ON clothes(barcode);
    CREATE INDEX IF NOT EXISTS idx_clothes_employee ON clothes(employee_id);
    CREATE INDEX IF NOT EXISTS idx_clothes_location ON clothes(location_id);
    CREATE INDEX IF NOT EXISTS idx_clothes_status ON clothes(status);

    CREATE TABLE IF NOT EXISTS laundry_batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      batch_number TEXT UNIQUE NOT NULL,
      direction TEXT NOT NULL, -- 'OUT' (Mosodába küldés / Kiadás), 'IN' (Mosodából visszavétel / Bevétel)
      location_id INTEGER,
      user_id INTEGER,
      status TEXT DEFAULT 'COMPLETED', -- 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'
      notes TEXT,
      item_count INTEGER DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      completed_at DATETIME,
      FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS laundry_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      batch_id INTEGER NOT NULL,
      cloth_id INTEGER NOT NULL,
      barcode TEXT NOT NULL,
      direction TEXT NOT NULL,
      location_id INTEGER,
      user_id INTEGER,
      scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      notes TEXT,
      FOREIGN KEY (batch_id) REFERENCES laundry_batches(id) ON DELETE CASCADE,
      FOREIGN KEY (cloth_id) REFERENCES clothes(id) ON DELETE CASCADE,
      FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE INDEX IF NOT EXISTS idx_laundry_items_batch ON laundry_items(batch_id);
    CREATE INDEX IF NOT EXISTS idx_laundry_items_cloth ON laundry_items(cloth_id);
    CREATE INDEX IF NOT EXISTS idx_laundry_items_scanned ON laundry_items(scanned_at);

    CREATE TABLE IF NOT EXISTS audit_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER,
      username TEXT,
      action TEXT NOT NULL,
      entity_type TEXT NOT NULL,
      entity_id TEXT,
      details TEXT,
      location_id INTEGER,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);
  `);
}

initSchema();

module.exports = {
  db,
  initSchema
};
