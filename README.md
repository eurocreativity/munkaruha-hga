# HGA Biomed - Munkaruha és Mosodai Nyilvántartó Rendszer (ISPConfig / PHP + MySQL)

Professzionális, valós idejű vonalkódos munkaruha és mosodai átadás-átvételi nyilvántartó rendszer a **HGA Biomed Kft.** részére (**Jutai út 50.** és **Nagygát utca 1.** telephelyek).

A rendszer a `munkalap-app` és `Ajanlat` rendszerekkel teljesen azonos **ISPConfig PHP + MySQL** architektúrára, biztonságos munkamenet-kezelésre és automatikus GitHub frissítési mechanizmusra épül.

---

## Főbb Funkciók

1. **Gyors Vonalkód Olvasó (`scanner.php`)**:
   - **Kiadás mosodába** (szennyes ruhák átadása) és **Visszavétel mosásból** (tiszta ruhák visszaérkezése) üzemmódok.
   - **Azonnali hangvisszajelzés** Web Audio API-val (Sikeres csipogás, Figyelmeztetés dupla olvasáskor, Hiba ismeretlen kódnál).
   - **Automatikus bizonylatszámozás & nyomtatható szállítólevél** (`Átadás-átvételi jegyzék`) cégfejléccel, tételes összesítéssel és aláírási résszel.
   - USB/Bluetooth hardveres vonalkódolvasó és beépített kamerás olvasó (Html5Qrcode) támogatás.

2. **Központi Munkaruha Nyilvántartás (`clothes.php`)**:
   - 82 induló munkaruha tétel (pólók, köpenyek, nadrágok, kazakok fehér, zöld, bottlezöld és kék színekben, méretekkel és nettó amortizációs értékekkel).
   - Szűrés kategóriára, színre, státuszra (Dolgozónál aktív, Mosásban, Tartalék, Elveszett/Hiányzó, Selejtezett) és telephelyre.

3. **Dolgozói Nyilvántartás (`employees.php`)**:
   - Személyekhez rendelt kódok (pl. 0002, 0005, 0082, stb.), szekrény/fakk számok és hozzárendelt ruhamennyiségek.

4. **Mosásban Lévők & Hiánylista (`in_laundry.php`)**:
   - Mióta van elküldve a tétel, vizuális figyelmeztetés a késő tételeknél (>7 nap, >14 nap).

5. **Leltár Import / Export (`csv_export.php`, `csv_import.php`)**:
   - Pontosvesszővel tagolt, UTF-8 BOM kódolású Excel-kompatibilis CSV export és import a HGA Biomed eredeti mezőstruktúrájával.

6. **Többfelhasználós Jogosultságkezelés (`users.php`)**:
   - Szerepkörök: **Adminisztrátor**, **Operátor (Raktáros)**, **Megtekintő (Vezető / Hanna / Peti)** telephelyi hozzáféréssel.

7. **Automatikus Rendszerfrissítés (`update.php`, `ajax_update.php`)**:
   - Egykattintásos GitHub frissítés letöltés és telepítés közvetlenül a `https://github.com/eurocreativity/munkaruha-hga` repóból, biztonsági SQL mentés készítésével.

---

## Telepítés ISPConfig Webszerveren

1. **Adatbázis létrehozása**:
   - ISPConfigban hozzon létre egy MySQL adatbázist és felhasználót.
   - Importálja a `schema_telepites.sql` fájlt (pl. phpMyAdmin-on keresztül).

2. **Fájlok feltöltése**:
   - Töltse fel a repó tartalmát a weboldal `web/` könyvtárába.
   - Másolja le a `config.local.example.php` fájlt `config.local.php` néven:
     ```bash
     cp config.local.example.php config.local.php
     ```
   - Állítsa be az adatbázis hozzáférést a `config.local.php` fájlban:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'c1_munkaruha');
     define('DB_USER', 'c1_munkaruha_u');
     define('DB_PASS', 'Jelszo123!');
     ```

3. **Alapértelmezett belépési adatok**:
   - **Adminisztrátor**: `admin` / `admin123`
   - **Jutai úti Raktáros**: `jutai_operator` / `jutai123`
   - **Nagygát úti Raktáros**: `nagygat_operator` / `nagygat123`
   - **Vezető**: `vezeto` / `vezeto123`

---

## Git Repository
- GitHub URL: `https://github.com/eurocreativity/munkaruha-hga`
