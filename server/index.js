const express = require('express');
const cors = require('cors');
const path = require('path');
const os = require('os');
const { initSchema } = require('./db');

// Inicializálás
initSchema();

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Statikus fájlok
app.use(express.static(path.join(__dirname, '..', 'public')));

// API Útvonalak
app.use('/api/auth', require('./routes/auth'));
app.use('/api/locations', require('./routes/locations'));
app.use('/api/employees', require('./routes/employees'));
app.use('/api/clothes', require('./routes/clothes'));
app.use('/api/laundry', require('./routes/laundry'));
app.use('/api/inventory', require('./routes/inventory'));
app.use('/api/audit', require('./routes/audit'));

// SPA Fallback
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
});

// Szerver indítása és hálózati IP címek kiírása
app.listen(PORT, '0.0.0.0', () => {
  console.log('================================================================');
  console.log(`  HGA Biomed Munkaruha & Mosodai Rendszer elindult!`);
  console.log(`  Helyi elérés (ezen a gépen):  http://localhost:${PORT}`);

  const networkInterfaces = os.networkInterfaces();
  for (const name of Object.keys(networkInterfaces)) {
    for (const net of networkInterfaces[name]) {
      if (net.family === 'IPv4' && !net.internal) {
        console.log(`  Hálózati elérés (másik gépről/telephelyről): http://${net.address}:${PORT}`);
      }
    }
  }
  console.log('================================================================');
});
