# HGA Biomed - Munkaruha Rendszer Telepítési Útmutató (Synology NAS)

Ez az útmutató bemutatja, hogyan telepíthető a Munkaruha és Mosodai Nyilvántartó Rendszer egy **Synology NAS** szerverre, amelyet a két telephely VPN-en vagy helyi hálózaton keresztül ér el.

A program forráskódján és felületén **semmit sem kell változtatni**, közvetlenül futtatható a Synology beépített Web Station és MariaDB csomagjaival.

---

## 1. Szükséges csomagok telepítése a Synology Csomagkezelőből (Package Center)

Nyissa meg a Synology DSM felületet és a **Package Center (Csomagkezelési központ)** menüben telepítse fel az alábbi ingyenes csomagokat:
1. **Web Station**
2. **Apache HTTP Server 2.4** (vagy Nginx)
3. **PHP 8.0** vagy **PHP 8.2**
   - *A Web Station -> Script Language Settings -> PHP profilban pipálja be a szükséges kiterjesztéseket:* `pdo_mysql`, `curl`, `zip`, `openssl`.
4. **MariaDB 10** (MySQL adatbázis szerver)
5. **phpMyAdmin** (grafikus adatbázis-kezelő)
6. *(Opcionális)* **VPN Server** vagy **Tailscale** (a távoli telephely biztonságos eléréséhez)

---

## 2. Adatbázis létrehozása és séma importálása

1. Nyissa meg a **phpMyAdmin** felületet a Synology-n (`http://<NAS_IP>/phpmyadmin`).
2. Jelentkezzen be `root` felhasználóként (a MariaDB telepítésekor megadott jelszóval).
3. Hozzon létre egy új adatbázist (pl. `munkaruha_db`, kódolás: `utf8mb4_unicode_ci`).
4. Kattintson az **Importálás** fülre, és tallózza be a rendszer **`schema_telepites.sql`** fájlját.
5. Futtassa le az importot. Ezzel létrejönnek a táblák és betöltődik a **82 tételes kezdő leltár**, a dolgozók és az alapértelmezett felhasználók.

---

## 3. Weboldal és fájlok elhelyezése

1. A Synology **File Station** alkalmazásában nyissa meg a `web` mappát.
2. Hozzon létre egy `munkaruha` nevű mappát: `/web/munkaruha/`
3. Másolja be a GitHub repó tartalmát ebbe a mappába.
4. Másolja le a `config.local.example.php` fájlt `config.local.php` néven:
5. Nyissa meg és állítsa be a MariaDB kapcsolatot:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', '3307'); // Synology MariaDB 10 alapértelmezett portja 3307 vagy 3306
   define('DB_NAME', 'munkaruha_db');
   define('DB_USER', 'root');
   define('DB_PASS', 'AzOnMariaDBJelszava');
   ```

---

## 4. Web Station webszolgáltatás beállítása

1. Nyissa meg a **Web Station**-t a DSM-ben.
2. A **Web Service (Webszolgáltatás)** menüben hozzon létre egy natív szkript szolgáltatást:
   - Név: `Munkaruha`
   - Dokumentum gyökérkönyvtára: `/web/munkaruha`
   - HTTP-kiszolgáló: `Apache 2.4`
   - PHP: a beállított PHP profil
3. A **Web Portal (Webportál)** menüben rendelhet hozzá portot (pl. `http://<NAS_IP>:8080`) vagy aldomaint.

---

## 5. Elérés VPN-en keresztül (Jutai út & Nagygát u.)

A telephelyek közötti biztonságos működéshez:
- A Synology-n beállított **OpenVPN / L2TP / Tailscale VPN** kapcsolat aktiválása után a kliens gépek és vonalkódolvasók a belső IP címen érik el a felületet:
  ```
  http://192.168.x.x/munkaruha/
  ```
- Belépés az alapértelmezett felhasználókkal (`admin` / `admin123`, `jutai_operator` / `jutai123`, `nagygat_operator` / `nagygat123`, `vezeto` / `vezeto123`).
