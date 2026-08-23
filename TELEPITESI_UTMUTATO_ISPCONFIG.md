# HGA Biomed - Munkaruha Rendszer Telepítési Útmutató (ISPConfig / MySQL)

## 1. Lépés: Adatbázis létrehozása ISPConfig-ban
1. Lépjen be az **ISPConfig** felületre.
2. A **Sites -> Database Users** menüben hozzon létre egy új adatbázis felhasználót (pl. `c1_munkaruha_u`).
3. A **Sites -> Databases** menüben hozzon létre egy új adatbázist (pl. `c1_munkaruha`), és rendelje hozzá a felhasználót.
4. Nyissa meg a **phpMyAdmin** felületet, és importálja be a `schema_telepites.sql` fájlt.

## 2. Lépés: Fájlok feltöltése a webszerverre
1. Töltse fel a repó teljes tartalmát az ISPConfig weboldal `web/` könyvtárába.
2. Másolja le a `config.local.example.php` fájlt `config.local.php` néven:
   ```bash
   cp config.local.example.php config.local.php
   ```
3. Nyissa meg a `config.local.php` fájlt és adja meg a MySQL adatbázis hozzáféréseket:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'c1_munkaruha');
   define('DB_USER', 'c1_munkaruha_u');
   define('DB_PASS', 'AzOnAdatbazisJelszava');
   ```

## 3. Lépés: Belépés és Használat
- Nyissa meg az oldalt a böngészőben: `https://munkaruha.on-domainje.hu`
- Alapértelmezett belépési adatok:
  - **Adminisztrátor**: `admin` / `admin123`
  - **Jutai úti operátor**: `jutai_operator` / `jutai123`
  - **Nagygát úti operátor**: `nagygat_operator` / `nagygat123`
  - **Vezető**: `vezeto` / `vezeto123`

## 4. Lépés: Automatikus Rendszerfrissítés GitHub-ról
Az adminisztrátor a **Rendszerfrissítés** menüpontban egy kattintással ellenőrizheti és telepítheti a legújabb verziót a `eurocreativity/munkaruha-hga` GitHub repóból.
