<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Settings.php';

$settingsObj = new Settings();
$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');
$companyLogo = $settingsObj->get('company_logo', '');
$version = getAppVersion();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo escape($companyName); ?> - Felhasználói Kézikönyv</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    @media print {
      body { background-color: #ffffff !important; color: #000000 !important; }
      .no-print { display: none !important; }
      .page-break { page-break-before: always; }
      @page { size: A4; margin: 18mm 15mm; }
    }
  </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased p-4 md:p-10">

  <!-- Lebegő Nyomtatás / PDF Fejléc (Képernyőn látszik, nyomtatáskor rejtett) -->
  <div class="max-w-4xl mx-auto mb-6 bg-white p-4 rounded-2xl border border-slate-200 shadow-md flex items-center justify-between no-print sticky top-4 z-50">
    <div class="flex items-center space-x-3">
      <a href="dashboard.php" class="p-2 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition-all">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
      </a>
      <div>
        <h2 class="font-bold text-slate-900 text-sm">Hivatalos Felhasználói Kézikönyv</h2>
        <p class="text-xs text-slate-500">Munkaruha & Mosodai Nyilvántartó Rendszer &bull; Verzió: <?php echo escape($version); ?></p>
      </div>
    </div>
    <div class="flex items-center space-x-3">
      <button onclick="window.print()" class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center space-x-2">
        <i data-lucide="printer" class="w-4 h-4"></i>
        <span>Nyomtatás / Mentés PDF-ként</span>
      </button>
    </div>
  </div>

  <!-- A4-es FORMÁTUMÚ DOKUMENTUM TÖRZS -->
  <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-xl border border-slate-200 p-8 md:p-16 space-y-12">
    
    <!-- CÍMLAP -->
    <div class="text-center space-y-6 pb-12 border-b-2 border-slate-900">
      <div class="flex justify-center mb-6">
        <?php if ($companyLogo && file_exists(__DIR__ . '/' . strtok($companyLogo, '?'))): ?>
          <img src="<?php echo escape($companyLogo); ?>" alt="Céglogó" class="max-h-20 max-w-[280px] object-contain">
        <?php else: ?>
          <div class="w-20 h-20 rounded-2xl bg-brand-600 text-white flex items-center justify-center shadow-xl mx-auto">
            <i data-lucide="shirt" class="w-10 h-10"></i>
          </div>
        <?php endif; ?>
      </div>

      <div class="space-y-2">
        <span class="text-xs font-bold uppercase tracking-widest text-brand-700 bg-brand-50 px-3 py-1 rounded-full border border-brand-200">Hivatalos Rendszerdokumentáció</span>
        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Munkaruha és Mosodai Nyilvántartó Rendszer</h1>
        <p class="text-lg text-slate-600 font-medium"><?php echo escape($companyName); ?></p>
      </div>

      <div class="pt-8 text-xs text-slate-500 space-y-1 border-t border-slate-100 max-w-md mx-auto">
        <p><strong>Verziószám:</strong> <?php echo escape($version); ?></p>
        <p><strong>Kiadás Dátuma:</strong> <?php echo date('Y. F'); ?></p>
        <p><strong>Készítette:</strong> Euro-Creativity Kft. szoftverfejlesztő csapata</p>
      </div>
    </div>

    <!-- TARTALOMJEGYZÉK -->
    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 space-y-3">
      <h3 class="font-bold text-sm text-slate-900 uppercase tracking-wider flex items-center space-x-2">
        <i data-lucide="list" class="w-4 h-4 text-brand-600"></i>
        <span>Tartalomjegyzék</span>
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs font-medium text-slate-700">
        <a href="#section-1" class="hover:text-brand-600 py-1 flex items-center justify-between border-b border-slate-200"><span>1. Rendszer Áttekintés & Szerepkörök</span> <span class="font-mono text-slate-400">1. fejezet</span></a>
        <a href="#section-2" class="hover:text-brand-600 py-1 flex items-center justify-between border-b border-slate-200"><span>2. Gyors Vonalkód Olvasás & Mosodai Csomagok</span> <span class="font-mono text-slate-400">2. fejezet</span></a>
        <a href="#section-3" class="hover:text-brand-600 py-1 flex items-center justify-between border-b border-slate-200"><span>3. Munkaruhák & Mosási Életciklusszámláló</span> <span class="font-mono text-slate-400">3. fejezet</span></a>
        <a href="#section-4" class="hover:text-brand-600 py-1 flex items-center justify-between border-b border-slate-200"><span>4. Dolgozók & Átadás-Átvételi Nyilatkozatok</span> <span class="font-mono text-slate-400">4. fejezet</span></a>
        <a href="#section-5" class="hover:text-brand-600 py-1 flex items-center justify-between border-b border-slate-200"><span>5. Felhasználókezelés, Jelszavak & Email Értesítések</span> <span class="font-mono text-slate-400">5. fejezet</span></a>
        <a href="#section-6" class="hover:text-brand-600 py-1 flex items-center justify-between border-b border-slate-200"><span>6. Rendszerbeállítások, Logó & Frissítések</span> <span class="font-mono text-slate-400">6. fejezet</span></a>
      </div>
    </div>

    <!-- 1. FEJEZET -->
    <div id="section-1" class="space-y-4 pt-6">
      <div class="flex items-center space-x-3 pb-2 border-b border-slate-200">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-sm">1</div>
        <h2 class="text-xl font-bold text-slate-900">Rendszer Áttekintés és Felhasználói Szerepkörök</h2>
      </div>
      <p class="text-sm text-slate-600 leading-relaxed">
        A rendszer célja a <strong><?php echo escape($companyName); ?></strong> két telephelyén (<em>1 - Jutai út 50.</em> és <em>2 - Nagygát u. 1.</em>) lévő munkaruhák teljes életciklusának, mosodai kiadásának/bevételének, valamint a dolgozói ruha-kiosztásoknak a precíz, vonalkódos és naplózott nyilvántartása.
      </p>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
          <div class="flex items-center space-x-2">
            <span class="px-2 py-0.5 text-xs font-bold bg-red-100 text-red-800 rounded">Adminisztrátor</span>
          </div>
          <p class="text-xs text-slate-600">Teljes körű hozzáférés: Felhasználók kezelése, jelszócserék, Audit Napló, Rendszerfrissítés, Céglogó és SMTP beállítások.</p>
        </div>
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
          <div class="flex items-center space-x-2">
            <span class="px-2 py-0.5 text-xs font-bold bg-slate-200 text-slate-800 rounded">Raktáros (Operátor)</span>
          </div>
          <p class="text-xs text-slate-600">Napi operatív munka: Vonalkód olvasás, mosodai csomagok összeállítása/lezárása, új ruhák és dolgozók rögzítése, szállítólevelek nyomtatása.</p>
        </div>
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
          <div class="flex items-center space-x-2">
            <span class="px-2 py-0.5 text-xs font-bold bg-blue-100 text-blue-800 rounded">Megtekintő (Vezető)</span>
          </div>
          <p class="text-xs text-slate-600">Csak olvasási (Read-Only) jog: Statisztikák, mosásban lévők és készletek megtekintése, szállítólevelek és nyilatkozatok megtekintése.</p>
        </div>
      </div>
    </div>

    <!-- 2. FEJEZET -->
    <div id="section-2" class="space-y-4 pt-6 page-break">
      <div class="flex items-center space-x-3 pb-2 border-b border-slate-200">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-sm">2</div>
        <h2 class="text-xl font-bold text-slate-900">Gyors Vonalkód Olvasás és Mosodai Csomagküldés</h2>
      </div>
      <p class="text-sm text-slate-600 leading-relaxed">
        A <strong>Gyors Vonalkód Olvasó (`scanner.php`)</strong> a rendszer legfontosabb munkafelülete. Két fő üzemmódban működik:
      </p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 bg-amber-50/60 border border-amber-200 rounded-xl space-y-2">
          <h4 class="font-bold text-sm text-amber-900 flex items-center space-x-1.5">
            <i data-lucide="log-out" class="w-4 h-4 text-amber-700 rotate-90"></i>
            <span>1. MOSODÁBA KÜLDÉS (Kiolvasás - MOS-KI)</span>
          </h4>
          <ul class="text-xs text-amber-800 space-y-1 list-disc list-inside">
            <li>Szennyes ruhák átadása a külső mosodának.</li>
            <li>A beolvasott ruha státusza azonnal <strong>„Mosásban” (`IN_LAUNDRY`)</strong> állapotra vált.</li>
            <li>A ruha adatai automatikusan zárolódnak az elírások elkerülésére.</li>
          </ul>
        </div>

        <div class="p-4 bg-emerald-50/60 border border-emerald-200 rounded-xl space-y-2">
          <h4 class="font-bold text-sm text-emerald-900 flex items-center space-x-1.5">
            <i data-lucide="log-in" class="w-4 h-4 text-emerald-700 -rotate-90"></i>
            <span>2. VISSZAVÉTEL MOSÁSBÓL (Beolvasás - MOS-BE)</span>
          </h4>
          <ul class="text-xs text-emerald-800 space-y-1 list-disc list-inside">
            <li>Tiszta ruhák beérkezése a mosodából.</li>
            <li>A ruha státusza automatikusan visszavált <strong>„Aktív” (Dolgozónál)</strong> vagy <strong>„Tartalék”</strong> állapotra.</li>
            <li>A rendszer automatikusan növeli a ruha <strong>mosási ciklusszámát (+1)</strong>.</li>
          </ul>
        </div>
      </div>

      <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs text-slate-700 space-y-2">
        <p class="font-bold text-slate-900">💡 Kézi Rögzítés (Ha még nincs vonalkód vagy sérült a címke):</p>
        <p>A <strong>„📋 Munkaruhák Kiválasztása Listából”</strong> gombra kattintva dolgozó vagy kategória szerint, pipálós listából egyszerre több ruha is hozzáadható a csomaghoz.</p>
        <p class="font-bold text-slate-900 mt-2">📄 Csomag Lezárása & Szállítólevél:</p>
        <p>A <strong>„Csomag Lezárása & Szállítólevél”</strong> gomb megnyomásával a rendszer legenerálja a hivatalos átadás-átvételi bizonylatot, amit a mosodai futárral két példányban alá kell íratni.</p>
        <p class="font-bold text-slate-900 mt-2">🛡️ Hivatalos Bizonylatvédelem:</p>
        <p class="text-slate-600">A lezárt szállítólevelek hivatalos audit bizonylatnak minősülnek, utólag nem törölhetők a rendszerből a ruha-státuszok és a mosási előzmények védelme érdekében. A korábbi bizonylatok bármikor visszakereshetők és újranyomtathatók.</p>
      </div>
    </div>

    <!-- 3. FEJEZET -->
    <div id="section-3" class="space-y-4 pt-6">
      <div class="flex items-center space-x-3 pb-2 border-b border-slate-200">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-sm">3</div>
        <h2 class="text-xl font-bold text-slate-900">Munkaruhák Nyilvántartása & Mosási Életciklusszámláló</h2>
      </div>
      <p class="text-sm text-slate-600 leading-relaxed">
        A <strong>Munkaruhák (`clothes.php`)</strong> menüpontban található a vállalat teljes textilkészlete, kereshető vonalkód, cikkszám, név, méret és hozzárendelt dolgozó szerint.
      </p>

      <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3 text-xs">
        <h4 class="font-bold text-slate-900 text-sm">🔄 Mosási Ciklusszámláló & Csereérettség:</h4>
        <p class="text-slate-600 leading-relaxed">
          Minden ruha rendelkezik egy mosási számlálóval (pl. <strong>`14 / 50 mosás`</strong>).
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1 font-semibold">
          <div class="p-2 bg-emerald-50 text-emerald-800 rounded-lg border border-emerald-200">🟢 0 - 74%: Újszerű / Kiváló állapot</div>
          <div class="p-2 bg-amber-50 text-amber-800 rounded-lg border border-amber-200">🟡 75 - 99%: Fokozottan használt</div>
          <div class="p-2 bg-red-50 text-red-800 rounded-lg border border-red-200">🔴 100%+: <strong>Csereérett / Anyagfáradt</strong></div>
        </div>
        <p class="text-slate-500 text-[11px]">
          Amikor a ruha eléri a maximális mosási számot, a szkennerben figyelmeztető hangjelzés és felugró üzenet figyelmezteti a kezelőt a ruha állapotának ellenőrzésére.
        </p>
      </div>
    </div>

    <!-- 4. FEJEZET -->
    <div id="section-4" class="space-y-4 pt-6 page-break">
      <div class="flex items-center space-x-3 pb-2 border-b border-slate-200">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-sm">4</div>
        <h2 class="text-xl font-bold text-slate-900">Dolgozói Készletek és Átadás-Átvételi Nyilatkozatok</h2>
      </div>
      <p class="text-sm text-slate-600 leading-relaxed">
        A <strong>Dolgozók (`employees.php`)</strong> felületen a munkavállalók törzsszáma, szekrényszáma és a náluk lévő ruhák darabszáma látható.
      </p>

      <div class="p-5 bg-slate-50 border border-slate-200 rounded-2xl space-y-3 text-xs text-slate-700">
        <h4 class="font-bold text-slate-900 text-sm flex items-center space-x-2">
          <i data-lucide="file-text" class="w-4 h-4 text-brand-600"></i>
          <span>📋 Hivatalos Dolgozói Átadás-Átvételi Nyilatkozat Nyomtatása</span>
        </h4>
        <p>
          Bármelyik dolgozó kártyáján a <strong>„Nyilatkozat”</strong> gombra kattintva azonnal legenerálható a munkajogi szempontból hiteles elismervény.
        </p>
        <ul class="list-disc list-inside space-y-1 text-slate-600">
          <li>Tartalmazza a dolgozóhoz rendelt összes ruha adatait (vonalkód, név, méret, mosásszám).</li>
          <li>Felelősségvállalási záradék a ruhák megóvásáról és kilépéskori elszámolásról.</li>
          <li>Kétoldalú aláírási vonal: <em>Kiadó (Raktáros)</em> és <em>Átvevő (Munkavállaló)</em>.</li>
        </ul>
      </div>
    </div>

    <!-- 5. FEJEZET -->
    <div id="section-5" class="space-y-4 pt-6">
      <div class="flex items-center space-x-3 pb-2 border-b border-slate-200">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-sm">5</div>
        <h2 class="text-xl font-bold text-slate-900">Felhasználókezelés, Jelszavak és Email Hitelesítés</h2>
      </div>
      <p class="text-sm text-slate-600 leading-relaxed">
        A rendszer szigorú biztonsági architektúrával védi a hozzáféréseket:
      </p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
          <p class="font-bold text-slate-900">✉️ Új Munkatárs Meghívása Emailben:</p>
          <p class="text-slate-600">Az Admin felviszi az új felhasználó email címét, és a rendszer egyedi aktiváló linket küld neki. A munkatárs a linkre kattintva maga adja meg első jelszavát.</p>
        </div>
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
          <p class="font-bold text-slate-900">⚡ Valós Idejű Email Szintaktikai Ellenőrzés:</p>
          <p class="text-slate-600">Az űrlap gépelés közben valós időben ellenőrzi az email helyességét (RFC 822 szabvány), megelőzve az elgépeléseket és megvédve a felugró ablak tartalmát az elvesztéstől.</p>
        </div>
      </div>
    </div>

    <!-- 6. FEJEZET -->
    <div id="section-6" class="space-y-4 pt-6 border-t border-slate-200">
      <div class="flex items-center space-x-3 pb-2 border-b border-slate-200">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-sm">6</div>
        <h2 class="text-xl font-bold text-slate-900">Rendszerbeállítások, Sötét Mód, Logó és Frissítések</h2>
      </div>
      <p class="text-sm text-slate-600 leading-relaxed">
        A <strong>Beállítások (`settings.php`)</strong> és a fejléc felületein elérhető központi funkciók:
      </p>
      <ul class="text-xs text-slate-700 space-y-2 list-disc list-inside bg-slate-50 p-4 rounded-xl border border-slate-200">
        <li><strong>🌙 Éjszakai (Sötét) és Világos Mód:</strong> A felső fejléc Hold/Nap gombjával egy kattintással átkapcsolható a teljes képernyős szemkímélő sötét mód, melyet a böngésző automatikusan megjegyez.</li>
        <li><strong>🖼️ Céglogó feltöltése:</strong> PNG, JPG vagy SVG formátumú logó, mely automatikusan megjelenik a belső fejlécben, az emailekben és a szállítóleveleken.</li>
        <li><strong>📧 SMTP Levelező Szerver:</strong> Titkosított TLS/SSL kapcsolat (Host, Port, User, Jelszó) beépített Teszt Email gombbal.</li>
        <li><strong>🏷️ Szemantikus Verziószámozás (`v1.34`):</strong> A rendszer fejlécében jól láthatóan követhető az éppen futó verziószám.</li>
        <li><strong>🔄 Automatikus GitHub Rendszerfrissítés (`update.php`):</strong> 1 kattintásos verziófrissítés a hivatalos repóból előzetes automatikus biztonsági mentéssel.</li>
      </ul>
    </div>

    <!-- ZÁRÓ LÁBLÉC -->
    <div class="pt-8 border-t-2 border-slate-900 flex flex-wrap items-center justify-between text-xs text-slate-500">
      <p>&copy; <?php echo date('Y'); ?> <?php echo escape($companyName); ?> &bull; Munkaruha és Mosodai Rendszer</p>
      <p>Készítette: <strong>Euro-Creativity Kft.</strong></p>
    </div>

  </div>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
