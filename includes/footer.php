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

  <!-- INTERAKTÍV SÚGÓ MODÁL -->
  <div id="interactive-help-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-xs hidden p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-4xl w-full p-6 md:p-8 space-y-6 my-auto max-h-[90vh] flex flex-col">
      
      <!-- MODÁL FEJLÉC -->
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 shrink-0">
        <div class="flex items-center space-x-3">
          <div class="p-3 bg-brand-50 text-brand-600 rounded-2xl">
            <i data-lucide="help-circle" class="w-6 h-6"></i>
          </div>
          <div>
            <h3 class="text-lg font-bold text-slate-900">Interaktív Rendszer Súgó & Útmutató</h3>
            <p class="text-xs text-slate-500">Gyors segítség és működési leírás minden funkcióhoz</p>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <a href="user_guide.php" target="_blank" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-all">
            <i data-lucide="file-text" class="w-3.5 h-3.5 text-brand-400"></i>
            <span>PDF Kézikönyv</span>
          </a>
          <button onclick="closeInteractiveHelp()" class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition-all">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
      </div>

      <!-- TARTALMI ELRENDEZÉS (Bal oldali tabok + Jobb oldali leírás) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 flex-1 overflow-y-auto pr-1">
        
        <!-- FÜLEK LISTÁJA -->
        <div class="space-y-1.5 text-xs font-semibold">
          <button onclick="switchHelpTab('scanner')" id="tab-btn-scanner" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
            <i data-lucide="scan-barcode" class="w-4 h-4 text-brand-600 shrink-0"></i>
            <span>1. Vonalkód Olvasó & Csomagok</span>
          </button>
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
            <span>5. Jogosultságok & Szerepkörök</span>
          </button>
          <button onclick="switchHelpTab('admin')" id="tab-btn-admin" class="help-tab-btn w-full p-3 rounded-xl text-left flex items-center space-x-2.5 transition-all">
            <i data-lucide="sliders" class="w-4 h-4 text-slate-700 shrink-0"></i>
            <span>6. Rendszergazda & Beállítások</span>
          </button>
        </div>

        <!-- RÉSZLETES LEÍRÁS PANEL -->
        <div class="md:col-span-2 bg-slate-50 border border-slate-200 rounded-2xl p-5 text-xs text-slate-700 space-y-4 overflow-y-auto max-h-[500px]">
          
          <!-- 1. VONALKÓD OLVASÓ TAB -->
          <div id="help-pane-scanner" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <i data-lucide="scan-barcode" class="w-4 h-4 text-brand-600"></i>
              <span>Gyors Vonalkód Olvasó & Mosodai Csomagkezelés</span>
            </h4>
            <div class="space-y-2">
              <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl space-y-1">
                <p class="font-bold text-amber-900">1. MOSODÁBA KÜLDÉS (Kiolvasás - MOS-KI):</p>
                <p class="text-amber-800">Szennyes ruhák átadása a mosodának. A beolvasott ruha státusza azonnal <strong>„Mosásban” (`IN_LAUNDRY`)</strong> állapotra vált és zárolódik.</p>
              </div>
              <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl space-y-1">
                <p class="font-bold text-emerald-900">2. VISSZAVÉTEL MOSÁSBÓL (Beolvasás - MOS-BE):</p>
                <p class="text-emerald-800">Tiszta ruhák visszavételezése. A ruha automatikusan visszakerül a dolgozóhoz (vagy tartalékba), és a rendszer növeli a <strong>mosási számlálóját (+1)</strong>.</p>
              </div>
              <p><strong>📋 Kézi rögzítés:</strong> Ha a vonalkód nem olvasható, a „Munkaruhák Kiválasztása Listából” gombbal név és kategória szerint választhatók ki a ruhák.</p>
              <p><strong>📄 Csomag lezárása:</strong> A művelet végén a „Csomag Lezárása & Szállítólevél” gombra kattintva azonnal kinyomtatható a futár által aláírandó átadóív.</p>
            </div>
          </div>

          <!-- 2. MUNKARUHÁK TAB -->
          <div id="help-pane-clothes" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <i data-lucide="tags" class="w-4 h-4 text-emerald-600"></i>
              <span>Munkaruhák Nyilvántartása & Mosási Ciklusszámláló</span>
            </h4>
            <div class="space-y-2">
              <p>A nyilvántartásban minden ruha egyedi vonalkóddal, mérettel, kategóriával és telephelyi hozzárendeléssel szerepel.</p>
              <div class="p-3 bg-slate-100 rounded-xl space-y-1">
                <p class="font-bold text-slate-900">🔄 Mosási Életciklusszámláló:</p>
                <p>Minden ruha mellett látható az eddigi mosások száma és az ajánlott maximum (pl. <strong>`14 / 50 mosás`</strong>). Amikor eléri a limitet, a rendszer <strong>„Csereérett”</strong> jelzéssel figyelmeztet az anyagfáradásra.</p>
              </div>
              <p><strong>🔒 Logikai zárolás:</strong> Ha egy ruha mosodában vagy nyitott csomagban van, a státusza és dolgozói hozzárendelése védett, nem írható felül véletlenül.</p>
            </div>
          </div>

          <!-- 3. DOLGOZÓK TAB -->
          <div id="help-pane-employees" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
              <span>Dolgozók & Hivatalos Átadás-Átvételi Nyilatkozat</span>
            </h4>
            <div class="space-y-2">
              <p>A dolgozókhoz törzsszám, szekrényszám és telephely rendelhető.</p>
              <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl space-y-1">
                <p class="font-bold text-blue-900">📋 Átadás-Átvételi Nyilatkozat Nyomtatása:</p>
                <p class="text-blue-800">A dolgozó kártyáján lévő <strong>„Nyilatkozat”</strong> gombra kattintva azonnal kinyomtatható a munkavédelmi és jogi felelősségvállalási elismervény a dolgozó összes ruhájával és aláírási vonallal.</p>
              </div>
            </div>
          </div>

          <!-- 4. SZÁLLÍTÓLEVELEK TAB -->
          <div id="help-pane-batches" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <i data-lucide="truck" class="w-4 h-4 text-amber-600"></i>
              <span>Mosodai Csomagok & Szállítólevelek</span>
            </h4>
            <div class="space-y-2">
              <p>Itt visszakereshető az összes korábbi mosodai kiadás és visszavételezés.</p>
              <p><strong>🖨️ Újranyomtatás:</strong> Bármelyik korábbi szállítólevél bármikor újranyomtatható.</p>
              <p><strong>🗑️ Sztornózás (Csak Admin):</strong> Ha egy csomagot tévesen zártak le, a Rendszergazda a piros kuka gombbal sztornózhatja, ami a ruhák eredeti státuszát azonnal helyreállítja.</p>
            </div>
          </div>

          <!-- 5. JOGOSULTSÁGOK TAB -->
          <div id="help-pane-roles" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600"></i>
              <span>Szerepkörök & Hozzáférések</span>
            </h4>
            <div class="space-y-2">
              <p><strong>1. Rendszergazda (Admin):</strong> Minden funkcióhoz, beállításhoz, felhasználóhoz, jelszóhoz és audit naplóhoz hozzáfér.</p>
              <p><strong>2. Raktáros (Operátor):</strong> Napi operatív munka (olvasás, csomagküldés, új ruha/dolgozó felvétel, nyilatkozat nyomtatás).</p>
              <p><strong>3. Megtekintő (Viewer / Vezető):</strong> Szigorúan <em>Csak Olvasási jog</em>. Megtekintheti a készleteket, mosásban lévőket és nyilatkozatokat, de nem szerkeszthet és nem küldhet csomagot.</p>
            </div>
          </div>

          <!-- 6. RENDSZERGAZDA TAB -->
          <div id="help-pane-admin" class="help-pane hidden space-y-3">
            <h4 class="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <i data-lucide="sliders" class="w-4 h-4 text-slate-800"></i>
              <span>Rendszergazdai Funkciók & Beállítások</span>
            </h4>
            <div class="space-y-2">
              <p><strong>🖼️ Céglogó feltöltése:</strong> A Beállításokban feltöltött PNG/SVG logó automatikusan megjelenik a fejlécben, az emailekben és a szállítóleveleken.</p>
              <p><strong>📧 Email & SMTP Beállítások:</strong> Céges levelezőszerver konfigurálása és tesztelése elfelejtett jelszó és új munkatársi meghívók küldéséhez.</p>
              <p><strong>🔄 Rendszerfrissítés:</strong> 1 kattintásos frissítés a legújabb funkciókra közvetlenül a GitHubról.</p>
            </div>
          </div>

        </div>
      </div>

      <!-- LÁBLÉC -->
      <div class="pt-3 border-t border-slate-100 flex items-center justify-between shrink-0">
        <span class="text-[11px] text-slate-400 font-mono">HGA Biomed Munkaruha Rendszer v<?php echo escape(getAppVersion()); ?></span>
        <button type="button" onclick="closeInteractiveHelp()" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-all">Bezárás</button>
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
