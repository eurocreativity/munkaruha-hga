    </main>

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
      <div class="max-w-7xl mx-auto px-4 flex flex-wrap items-center justify-between gap-2">
        <div>
          &copy; <?php echo date('Y'); ?> <?php echo escape(defined('COMPANY_NAME') ? COMPANY_NAME : 'HGA Biomed Kft.'); ?> &bull; Munkaruha és Mosodai Nyilvántartó Rendszer &bull; Készítette: <strong>Euro-Creativity Kft.</strong>
        </div>
        <div class="flex items-center space-x-4">
          <button onclick="openInteractiveHelp()" class="text-brand-600 hover:text-brand-800 font-bold flex items-center space-x-1">
            <i data-lucide="help-circle" class="w-3.5 h-3.5"></i>
            <span>Interaktív Súgó</span>
          </button>
          <a href="user_guide.php" target="_blank" class="text-slate-600 hover:text-slate-900 flex items-center space-x-1">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
            <span>PDF Kézikönyv</span>
          </a>
        </div>
      </div>
    </footer>
  </div>

  <?php
  $helpUser = getCurrentUser();
  $helpRole = $helpUser['role'] ?? 'viewer';
  $isHelpAdmin = ($helpRole === 'admin');
  $isHelpOperator = ($helpRole === 'operator');
  $isHelpViewer = ($helpRole === 'viewer');
?>
  <!-- INTERAKTÍV SÚGÓ MODÁL -->
  <div id="interactive-help-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-4xl w-full p-6 md:p-8 space-y-6 my-auto max-h-[90vh] flex flex-col">
      
      <!-- MODÁL FEJLÉC -->
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 shrink-0">
        <div class="flex items-center space-x-3">
          <div class="p-3 bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 rounded-2xl">
            <i data-lucide="help-circle" class="w-6 h-6"></i>
          </div>
          <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Interaktív Rendszer Súgó & Útmutató</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Szerepkör: <strong class="capitalize text-brand-600 dark:text-brand-400"><?php echo escape($helpRole); ?></strong> &bull; Csak az Ön hozzáféréséhez tartozó funkciók leírása
            </p>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <a href="user_guide.php" target="_blank" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-all">
            <i data-lucide="file-text" class="w-3.5 h-3.5 text-brand-400"></i>
            <span>PDF Kézikönyv</span>
          </a>
          <button type="button" onclick="closeInteractiveHelp()" class="p-2 text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
      </div>

      <!-- MODÁL TÖRZS (FÜLEK ÉS TARTALOM) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 flex-1 overflow-y-auto pr-1">
        
        <!-- FÜLEK LISTÁJA -->
        <div class="space-y-1.5 text-xs font-semibold">
          <?php if (!$isHelpViewer): ?>
            <button onclick="switchHelpTab('scanner')" id="tab-btn-scanner" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
              <i data-lucide="scan-barcode" class="w-4 h-4 text-brand-600 shrink-0"></i>
              <span>1. Vonalkód Olvasó & Csomagok</span>
            </button>
          <?php else: ?>
            <button onclick="switchHelpTab('scanner')" id="tab-btn-scanner" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
              <i data-lucide="layout-dashboard" class="w-4 h-4 text-brand-600 shrink-0"></i>
              <span>1. Vezérlőpult & Kimutatások</span>
            </button>
          <?php endif; ?>

          <button onclick="switchHelpTab('clothes')" id="tab-btn-clothes" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
            <i data-lucide="tags" class="w-4 h-4 text-emerald-600 shrink-0"></i>
            <span>2. Munkaruhák & Mosási Számláló</span>
          </button>
          
          <button onclick="switchHelpTab('employees')" id="tab-btn-employees" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
            <i data-lucide="users" class="w-4 h-4 text-blue-600 shrink-0"></i>
            <span>3. Dolgozók & Átvételi Nyilatkozat</span>
          </button>
          
          <button onclick="switchHelpTab('batches')" id="tab-btn-batches" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
            <i data-lucide="truck" class="w-4 h-4 text-amber-600 shrink-0"></i>
            <span>4. Szállítólevelek & Történet</span>
          </button>
          
          <button onclick="switchHelpTab('roles')" id="tab-btn-roles" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
            <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600 shrink-0"></i>
            <span>5. Jogosultságok & Sötét Mód</span>
          </button>

          <?php if ($isHelpAdmin): ?>
            <button onclick="switchHelpTab('admin')" id="tab-btn-admin" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
              <i data-lucide="sliders" class="w-4 h-4 text-slate-700 dark:text-slate-300 shrink-0"></i>
              <span>6. Rendszergazda & Beállítások</span>
            </button>
          <?php endif; ?>
        </div>

        <!-- RÉSZLETES LEÍRÁS PANEL -->
        <div class="md:col-span-2 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-5 text-xs text-slate-700 dark:text-slate-200 space-y-4 overflow-y-auto max-h-[500px]">
          
          <!-- 1. TAB: SZKENNER VAGY VEZÉRLŐPULT -->
          <div id="help-pane-scanner" class="help-pane hidden space-y-3">
            <?php if (!$isHelpViewer): ?>
              <h4 class="font-bold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
                <i data-lucide="scan-barcode" class="w-4 h-4 text-brand-600"></i>
                <span>Gyors Vonalkód Olvasó & Mosodai Csomagkezelés</span>
              </h4>
              <div class="space-y-2">
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl space-y-1">
                  <p class="font-bold text-amber-900 dark:text-amber-300">1. MOSODÁBA KÜLDÉS (Kiolvasás - MOS-KI):</p>
                  <p class="text-amber-800 dark:text-amber-200">Szennyes ruhák átadása a mosodának. A beolvasott ruha státusza azonnal <strong>„Mosásban” (`IN_LAUNDRY`)</strong> állapotra vált és zárolódik.</p>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl space-y-1">
                  <p class="font-bold text-emerald-900 dark:text-emerald-300">2. VISSZAVÉTEL MOSÁSBÓL (Beolvasás - MOS-BE):</p>
                  <p class="text-emerald-800 dark:text-emerald-200">Tiszta ruhák visszavételezése. A ruha automatikusan visszakerül a dolgozóhoz (vagy tartalékba), és a rendszer növeli a <strong>mosási számlálóját (+1)</strong>.</p>
                </div>
                <p><strong>📱 Telefonos & Kamerás Beolvasás:</strong> A beviteli mező melletti kamera ikonra kattintva mobiltelefon kamerájával vagy webkamerával is azonnal leolvashatók a vonalkódok.</p>
                <p><strong>🎯 Vonalkód Tesztelő (Sandbox):</strong> A menüsorban elérhető Tesztelőben biztonságosan, a ruha státuszának megváltoztatása nélkül ellenőrizheti az új vonalkód olvasókat, a telefonos kamerát és a képernyős mintákat.</p>
                <p><strong>📋 Kézi rögzítés:</strong> Ha a vonalkód nem olvasható, a „Munkaruhák Kiválasztása Listából” gombbal név és kategória szerint választhatók ki a ruhák.</p>
                <p><strong>📄 Csomag lezárása:</strong> A művelet végén a „Csomag Lezárása & Szállítólevél” gombra kattintva azonnal kinyomtatható a futár által aláírandó átadóív.</p>
              </div>
            <?php else: ?>
              <h4 class="font-bold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
                <i data-lucide="layout-dashboard" class="w-4 h-4 text-brand-600"></i>
                <span>Vezérlőpult & Kimutatások (Megtekintő Mód)</span>
              </h4>
              <div class="space-y-2">
                <div class="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl space-y-1">
                  <p class="font-bold text-blue-900 dark:text-blue-300">🔍 Csak Olvasási Hozzáférés:</p>
                  <p class="text-blue-800 dark:text-blue-200">Az Ön fiókja vezetői/megtekintői jogosultsággal rendelkezik. Valós időben követheti a készleteket, a mosásban lévő ruhák számát és a dolgozói leosztásokat.</p>
                </div>
                <p><strong>📊 Statisztikák:</strong> A Vezérlőpulton azonnal látható az összes ruha darabszáma, a telephelyi megoszlás és a mosodában lévő textíliák mennyisége.</p>
                <p><strong>👕 Csereérett ruhák:</strong> A vezérlőpult tetején figyelmeztető sáv jelenik meg, ha valamelyik ruha elérte az ajánlott maximális mosásszámot.</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- 2. MUNKARUHÁK TAB -->
          <div id="help-pane-clothes" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
              <i data-lucide="tags" class="w-4 h-4 text-emerald-600"></i>
              <span>Munkaruhák Nyilvántartása & Mosási Ciklusszámláló</span>
            </h4>
            <div class="space-y-2">
              <p>A nyilvántartásban minden ruha egyedi vonalkóddal, mérettel, kategóriával és telephelyi hozzárendeléssel szerepel.</p>
              <div class="p-3 bg-slate-100 dark:bg-slate-800 rounded-xl space-y-1">
                <p class="font-bold text-slate-900 dark:text-white">🔄 Mosási Életciklusszámláló:</p>
                <p>Minden ruha mellett látható az eddigi mosások száma és az ajánlott maximum (pl. <strong>`14 / 50 mosás`</strong>). Amikor eléri a limitet, a rendszer <strong>„Csereérett”</strong> jelzéssel figyelmeztet az anyagfáradásra.</p>
              </div>
              <p><strong>🔒 Logikai zárolás:</strong> Ha egy ruha mosodában vagy nyitott csomagban van, a státusza védett az elírások megelőzésére.</p>
            </div>
          </div>

          <!-- 3. DOLGOZÓK TAB -->
          <div id="help-pane-employees" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
              <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
              <span>Dolgozók & Hivatalos Átadás-Átvételi Nyilatkozat</span>
            </h4>
            <div class="space-y-2">
              <p>A dolgozókhoz törzsszám, szekrényszám és telephely tartozik.</p>
              <div class="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl space-y-1">
                <p class="font-bold text-blue-900 dark:text-blue-300">📋 Átadás-Átvételi Nyilatkozat Nyomtatása:</p>
                <p class="text-blue-800 dark:text-blue-200">A dolgozó kártyáján lévő <strong>„Nyilatkozat”</strong> gombra kattintva azonnal kinyomtatható a munkavédelmi és jogi felelősségvállalási elismervény a dolgozó összes ruhájával és kétoldalú aláírási vonallal.</p>
              </div>
            </div>
          </div>

          <!-- 4. SZÁLLÍTÓLEVELEK TAB -->
          <div id="help-pane-batches" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
              <i data-lucide="truck" class="w-4 h-4 text-amber-600"></i>
              <span>Mosodai Csomagok & Szállítólevelek</span>
            </h4>
            <div class="space-y-2">
              <p>Itt visszakereshető az összes korábbi mosodai kiadás és visszavételezés.</p>
              <p><strong>🖨️ Újranyomtatás:</strong> Bármelyik korábbi szállítólevél bármikor újranyomtatható a futár vagy a könyvelés számára.</p>
              <p><strong>🛡️ Bizonylatvédelem:</strong> A már lezárt (kész) szállítólevelek hivatalos bizonylatnak minősülnek, utólag nem törölhetők a rendszerből a ruha-státuszok védelmében.</p>
            </div>
          </div>

          <!-- 5. JOGOSULTSÁGOK & SÖTÉT MÓD TAB -->
          <div id="help-pane-roles" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
              <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600"></i>
              <span>Jogosultságok & Sötét Mód</span>
            </h4>
            <div class="space-y-2">
              <p><strong>Az Ön jelenlegi szerepköre:</strong> <span class="px-2 py-0.5 rounded bg-brand-100 dark:bg-brand-900/50 text-brand-800 dark:text-brand-300 font-bold capitalize"><?php echo escape($helpRole); ?></span></p>
              <div class="p-3 bg-slate-100 dark:bg-slate-800 rounded-xl space-y-1">
                <p class="font-bold text-slate-900 dark:text-white">🌙 Éjszakai (Sötét) és Világos Mód:</p>
                <p>A felső menüsor jobb oldalán lévő <strong>Hold / Nap (🌙 / ☀️)</strong> ikonnal bármikor átválthat a szemkímélő sötét és a világos megjelenés között. A rendszer megjegyzi a beállítást.</p>
              </div>
              <p><strong>Szerepkörök összefoglalása:</strong></p>
              <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                <li><strong>Admin:</strong> Teljes rendszervezérlés, felhasználók, audit napló, beállítások.</li>
                <li><strong>Operátor:</strong> Napi raktári munka, vonalkódos olvasás, csomagok lezárása, nyilatkozat nyomtatás.</li>
                <li><strong>Viewer:</strong> Csak olvasási jogosultság a készletek és kimutatások ellenőrzésére.</li>
              </ul>
            </div>
          </div>

          <?php if ($isHelpAdmin): ?>
            <!-- 6. RENDSZERGAZDA TAB (KIZÁRÓLAG ADMIN JOGOSULTSÁGGAL) -->
            <div id="help-pane-admin" class="help-pane hidden space-y-3">
              <h4 class="font-bold text-slate-900 dark:text-white text-sm flex items-center space-x-2">
                <i data-lucide="sliders" class="w-4 h-4 text-slate-800 dark:text-slate-200"></i>
                <span>Rendszergazdai Funkciók & Karbantartás</span>
              </h4>
              <div class="space-y-2">
                <p><strong>🎯 Vonalkód Tesztelő & Hardver Diagnosztika:</strong> Kockázatmentes sandbox felület az USB/Bluetooth vonalkód olvasók tesztelésére, karakterhossz- és Enter-végjel vizsgálatára, valamint képernyőről leolvasható mintákkal az éles adatbázis megváltoztatása nélkül.</p>
                <p><strong>✉️ Valós idejű Email Ellenőrzés:</strong> Új felhasználó meghívásakor az űrlap gépelés közben ellenőrzi a szintaxist, megelőzve az elgépeléseket.</p>
                <p><strong>🖼️ Céglogó feltöltése:</strong> A Beállításokban feltöltött PNG/SVG logó automatikusan megjelenik a fejlécben, az emailekben és a szállítóleveleken.</p>
                <p><strong>📧 SMTP Levelező:</strong> Titkosított céges levelezőszerver konfigurálása és tesztelése jelszóvisszaállításhoz és meghívókhoz.</p>
                <p><strong>🔄 1-Kattintásos Frissítés:</strong> Verziófrissítés közvetlenül a GitHubról az új funkciók és javítások azonnali telepítéséhez.</p>
                <p><strong>💾 Adatbázis Mentés:</strong> Egykattintásos SQL dump készítés és visszaállítás.</p>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- LÁBLÉC -->
      <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
        <span class="text-[11px] text-slate-400 font-mono">HGA Biomed Munkaruha Rendszer <?php echo escape(getAppVersion()); ?></span>
        <button type="button" onclick="closeInteractiveHelp()" class="px-5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs transition-all">Bezárás</button>
      </div>

    </div>
  </div>

  <script src="js/audio.js"></script>
  <script>
    if (window.lucide) {
      lucide.createIcons();
    }

    function toggleDarkMode() {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      if (window.lucide) {
        lucide.createIcons();
      }
    }

    function openInteractiveHelp(preferredTab) {
      const modal = document.getElementById('interactive-help-modal');
      if (!modal) return;

      let targetTab = preferredTab;
      if (!targetTab) {
        const path = window.location.pathname;
        if (path.includes('scanner.php')) targetTab = 'scanner';
        else if (path.includes('clothes.php')) targetTab = 'clothes';
        else if (path.includes('employees.php')) targetTab = 'employees';
        else if (path.includes('batches.php') || path.includes('in_laundry.php')) targetTab = 'batches';
        else if (path.includes('users.php') || path.includes('settings.php') || path.includes('audit.php') || path.includes('update.php')) targetTab = 'admin';
        else targetTab = 'scanner';
      }

      switchHelpTab(targetTab);
      modal.classList.remove('hidden');
      if (window.lucide) lucide.createIcons();
    }

    function closeInteractiveHelp() {
      const modal = document.getElementById('interactive-help-modal');
      if (modal) modal.classList.add('hidden');
    }

    function switchHelpTab(tabName) {
      document.querySelectorAll('.help-pane').forEach(p => p.classList.add('hidden'));
      document.querySelectorAll('.help-tab-btn').forEach(b => {
        b.classList.remove('bg-brand-50', 'text-brand-900', 'font-bold', 'border-l-4', 'border-brand-600');
        b.classList.add('text-slate-600', 'hover:bg-slate-100');
      });

      const pane = document.getElementById(`help-pane-${tabName}`);
      const btn = document.getElementById(`tab-btn-${tabName}`);

      if (pane) pane.classList.remove('hidden');
      if (btn) {
        btn.classList.remove('text-slate-600', 'hover:bg-slate-100');
        btn.classList.add('bg-brand-50', 'text-brand-900', 'font-bold', 'border-l-4', 'border-brand-600');
      }
    }
  </script>
</body>
</html>
