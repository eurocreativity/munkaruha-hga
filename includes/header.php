<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';

$currentUser = getCurrentUser();
$activeLocationId = getActiveLocationId();

$db = Database::getInstance();
$allLocations = $db->fetchAll("SELECT * FROM locations ORDER BY id ASC");

$settingsObj = new Settings();
$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');
$companyLogo = $settingsObj->get('company_logo', '');
?>
<!DOCTYPE html>
<html lang="hu" class="h-full bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo escape($companyName); ?> - Munkaruha és Mosodai Rendszer</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://unpkg.com/html5-qrcode"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: { 50: '#f0fdf4', 100: '#dcfce7', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' }
          }
        }
      }
    };
    // Sötét mód preferenciájának azonnali alkalmazása FOUC villogás nélkül
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  </script>
  <style>
    @media print {
      body * { visibility: hidden; }
      #printable-area, #printable-area * { visibility: visible; }
      #printable-area { position: absolute; left: 0; top: 0; width: 100%; }
    }

    /* Sötét mód átfogó dizájn-transzformáció és tökéletes kontraszt */
    .dark body { background-color: #0b1120 !important; color: #f8fafc !important; }
    .dark .bg-white { background-color: #111827 !important; border-color: #1f2937 !important; }
    .dark .bg-slate-50 { background-color: #1e293b !important; border-color: #334155 !important; }
    .dark .bg-slate-100 { background-color: #334155 !important; border-color: #475569 !important; }
    .dark .border-slate-200, .dark .border-slate-100 { border-color: #334155 !important; }
    
    /* Betűszínek kiemelkedő kontraszttal */
    .dark .text-slate-900 { color: #ffffff !important; }
    .dark .text-slate-800 { color: #f8fafc !important; }
    .dark .text-slate-700 { color: #e2e8f0 !important; }
    .dark .text-slate-600 { color: #cbd5e1 !important; }
    .dark .text-slate-500 { color: #94a3b8 !important; }
    .dark .text-slate-400 { color: #94a3b8 !important; }
    .dark .font-mono { color: #e2e8f0; }

    /* Beviteli mezők, lenyílók */
    .dark input, .dark select, .dark textarea {
      background-color: #1e293b !important;
      border-color: #475569 !important;
      color: #ffffff !important;
    }
    .dark input::placeholder, .dark textarea::placeholder {
      color: #64748b !important;
    }

    /* Táblázatok és elválasztók */
    .dark table thead, .dark thead th {
      background-color: #1e293b !important;
      color: #94a3b8 !important;
      border-color: #334155 !important;
    }
    .dark table tbody, .dark table td, .dark table tr {
      background-color: #111827 !important;
      border-color: #1f2937 !important;
    }
    .dark table tbody tr:hover, .dark table tbody tr:hover td {
      background-color: #1e293b !important;
    }
    .dark .divide-slate-100 > :not([hidden]) ~ :not([hidden]),
    .dark .divide-slate-200 > :not([hidden]) ~ :not([hidden]) {
      border-color: #1f2937 !important;
    }

    /* Gombok és linkek táblázatokban */
    .dark a.bg-slate-100, .dark button.bg-slate-100 {
      background-color: #1e293b !important;
      color: #e2e8f0 !important;
      border: 1px solid #475569 !important;
    }
    .dark a.bg-slate-100:hover, .dark button.bg-slate-100:hover {
      background-color: #334155 !important;
      color: #ffffff !important;
    }

    /* Színes jelvények és alert dobozok */
    .dark .bg-emerald-50, .dark .bg-emerald-100 {
      background-color: #064e3b70 !important;
      color: #6ee7b7 !important;
      border-color: #059669 !important;
    }
    .dark .text-emerald-800, .dark .text-emerald-900, .dark .text-emerald-700 {
      color: #6ee7b7 !important;
    }
    .dark .bg-amber-50, .dark .bg-amber-100 {
      background-color: #78350f50 !important;
      color: #fde68a !important;
      border-color: #b45309 !important;
    }
    .dark .text-amber-800, .dark .text-amber-900, .dark .text-amber-700 {
      color: #fde68a !important;
    }
    .dark .bg-blue-50, .dark .bg-blue-100 {
      background-color: #1e3a8a60 !important;
      color: #93c5fd !important;
      border-color: #2563eb !important;
    }
    .dark .text-blue-800, .dark .text-blue-900, .dark .text-blue-700 {
      color: #93c5fd !important;
    }
    .dark .bg-purple-50, .dark .bg-purple-100 {
      background-color: #581c8760 !important;
      color: #d8b4fe !important;
      border-color: #9333ea !important;
    }
    .dark .text-purple-700, .dark .text-purple-800 {
      color: #d8b4fe !important;
    }
    .dark .bg-red-50, .dark .bg-red-100 {
      background-color: #7f1d1d60 !important;
      color: #fca5a5 !important;
      border-color: #dc2626 !important;
    }
    .dark .text-red-700, .dark .text-red-800, .dark .text-red-900 {
      color: #fca5a5 !important;
    }
  </style>
</head>
<body class="h-full font-sans text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-950 antialiased flex flex-col transition-colors duration-200">

  <div class="flex-1 flex flex-col min-h-screen">
    <!-- FEJLÉC -->
    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 sticky top-0 z-30 shadow-xs transition-colors">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <a href="dashboard.php" class="flex items-center space-x-3 group">
            <?php if ($companyLogo && file_exists(__DIR__ . '/../' . strtok($companyLogo, '?'))): ?>
              <div class="flex items-center h-10 max-w-[170px]">
                <img src="<?php echo escape($companyLogo); ?>" alt="<?php echo escape($companyName); ?>" class="max-h-10 max-w-[170px] w-auto h-auto object-contain">
              </div>
              <span class="px-2 py-0.5 text-xs font-semibold bg-brand-100 text-brand-800 rounded-full"><?php echo escape(getAppVersion()); ?></span>
            <?php else: ?>
              <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-600 text-white shadow-md shadow-brand-600/20">
                <i data-lucide="shirt" class="w-6 h-6"></i>
              </div>
              <div>
                <div class="flex items-center space-x-2">
                  <span class="font-bold text-slate-900 dark:text-white text-lg leading-none"><?php echo escape($companyName); ?></span>
                  <span class="px-2 py-0.5 text-xs font-semibold bg-brand-100 text-brand-800 rounded-full"><?php echo escape(getAppVersion()); ?></span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Munkaruha & Mosoda Rendszer</p>
              </div>
            <?php endif; ?>
          </a>

          <!-- Telephely választó (Desktop és Tablet) -->
          <div class="hidden sm:flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 px-2 flex items-center">
              <i data-lucide="map-pin" class="w-3.5 h-3.5 mr-1 text-slate-400"></i> Telephely:
            </span>
            <select id="global-location-select" onchange="window.location.href='?location_id=' + this.value" class="text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-xs">
              <option value="" <?php echo $activeLocationId === '' ? 'selected' : ''; ?>>Mindkét telephely (Összes)</option>
              <?php foreach ($allLocations as $loc): ?>
                <option value="<?php echo $loc['id']; ?>" <?php echo $activeLocationId == $loc['id'] ? 'selected' : ''; ?>>
                  <?php echo escape($loc['code'] . ' - ' . ($loc['short_name'] ?: $loc['name'])); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Súgó & Sötét Mód & Profil & Kilépés -->
          <div class="flex items-center space-x-2 sm:space-x-2.5">
            <!-- Sötét / Világos Mód Váltó Gomb -->
            <button onclick="toggleDarkMode()" title="Sötét / Világos mód váltása" class="p-2 text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all border border-slate-200 dark:border-slate-700 shadow-2xs">
              <i data-lucide="moon" class="w-4 h-4 block dark:hidden text-slate-700"></i>
              <i data-lucide="sun" class="w-4 h-4 hidden dark:block text-amber-400"></i>
            </button>

            <!-- Súgó Gomb -->
            <button onclick="openInteractiveHelp()" title="Interaktív Rendszer Súgó" class="px-2.5 sm:px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-brand-50 dark:hover:bg-slate-700 hover:text-brand-700 dark:hover:text-brand-300 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-all flex items-center space-x-1 sm:space-x-1.5 border border-slate-200 dark:border-slate-700 shadow-2xs">
              <i data-lucide="help-circle" class="w-4 h-4 text-brand-600 dark:text-brand-400"></i>
              <span>Súgó</span>
            </button>

            <?php if ($currentUser): ?>
              <a href="profile.php" class="text-right hidden sm:block hover:opacity-80 transition-opacity">
                <p class="text-sm font-bold text-slate-800 dark:text-slate-100 leading-tight flex items-center justify-end space-x-1">
                  <span><?php echo escape($currentUser['full_name']); ?></span>
                  <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                </p>
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded capitalize"><?php echo escape($currentUser['role']); ?></span>
              </a>
              <a href="logout.php" title="Kijelentkezés" class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-xl transition-all">
                <i data-lucide="log-out" class="w-5 h-5"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- MOBIL TELEPHELY VÁLASZTÓ (Telefonon megjelenő kompakt sáv) -->
      <div class="sm:hidden bg-slate-100 border-t border-slate-200 px-4 py-2 flex items-center justify-between">
        <span class="text-xs font-bold text-slate-600 flex items-center">
          <i data-lucide="map-pin" class="w-3.5 h-3.5 mr-1 text-brand-600"></i> Telephely:
        </span>
        <select onchange="window.location.href='?location_id=' + this.value" class="text-xs font-bold text-slate-800 bg-white px-2.5 py-1 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
          <option value="" <?php echo $activeLocationId === '' ? 'selected' : ''; ?>>Mindkét telephely (Összes)</option>
          <?php foreach ($allLocations as $loc): ?>
            <option value="<?php echo $loc['id']; ?>" <?php echo $activeLocationId == $loc['id'] ? 'selected' : ''; ?>>
              <?php echo escape($loc['code'] . ' - ' . ($loc['short_name'] ?: $loc['name'])); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- FŐ MENÜSÁV -->
      <nav class="bg-slate-900 text-slate-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex space-x-1 overflow-x-auto py-1.5 scrollbar-none text-sm font-medium">
            <?php 
              $currentPage = basename($_SERVER['PHP_SELF']); 
              function navClass($page, $currentPage) {
                if ($page === $currentPage) {
                  return 'flex items-center space-x-2 px-3.5 py-2 rounded-lg text-white bg-brand-600 shadow-sm transition-all whitespace-nowrap font-bold';
                }
                return 'flex items-center space-x-2 px-3.5 py-2 rounded-lg hover:text-white hover:bg-slate-800 transition-all whitespace-nowrap text-slate-300';
              }
            ?>
            <a href="scanner.php" class="<?php echo navClass('scanner.php', $currentPage); ?>">
              <i data-lucide="scan-barcode" class="w-4 h-4 text-brand-200"></i>
              <span>Gyors Vonalkód Olvasó</span>
            </a>
            <a href="dashboard.php" class="<?php echo navClass('dashboard.php', $currentPage); ?>">
              <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
              <span>Vezérlőpult</span>
            </a>
            <a href="clothes.php" class="<?php echo navClass('clothes.php', $currentPage); ?>">
              <i data-lucide="tags" class="w-4 h-4"></i>
              <span>Munkaruhák</span>
            </a>
            <a href="employees.php" class="<?php echo navClass('employees.php', $currentPage); ?>">
              <i data-lucide="users" class="w-4 h-4"></i>
              <span>Dolgozók</span>
            </a>
            <a href="batches.php" class="<?php echo navClass('batches.php', $currentPage); ?>">
              <i data-lucide="truck" class="w-4 h-4"></i>
              <span>Mosodai Csomagok & Szállítólevelek</span>
            </a>
            <a href="in_laundry.php" class="<?php echo navClass('in_laundry.php', $currentPage); ?>">
              <i data-lucide="clock" class="w-4 h-4"></i>
              <span>Mosásban lévők</span>
            </a>
            <?php if (canEdit()): ?>
              <a href="csv_export.php" class="<?php echo navClass('csv_export.php', $currentPage); ?>">
                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                <span>Leltár CSV</span>
              </a>
            <?php endif; ?>

            <!-- KIZÁRÓLAG ADMIN JOGOSULTSÁGÚ MENÜPONTOK -->
            <?php if (isAdmin()): ?>
              <a href="scanner_test.php" class="<?php echo navClass('scanner_test.php', $currentPage); ?>" title="Vonalkód Olvasó & Hardver Diagnosztika">
                <i data-lucide="scan-line" class="w-4 h-4 text-indigo-400"></i>
                <span>Vonalkód Tesztelő</span>
              </a>
              <a href="audit.php" class="<?php echo navClass('audit.php', $currentPage); ?>">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                <span>Napló</span>
              </a>
              <a href="users.php" class="<?php echo navClass('users.php', $currentPage); ?>">
                <i data-lucide="user-check" class="w-4 h-4"></i>
                <span>Felhasználók</span>
              </a>
              <a href="settings.php" class="<?php echo navClass('settings.php', $currentPage); ?>">
                <i data-lucide="sliders" class="w-4 h-4"></i>
                <span>Beállítások</span>
              </a>
              <a href="update.php" class="<?php echo navClass('update.php', $currentPage); ?>">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                <span>Rendszerfrissítés</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </nav>
    </header>

    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
      <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="p-4 rounded-xl border flex items-center space-x-3 <?php echo $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : ($flash['type'] === 'warning' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-red-50 text-red-800 border-red-200'); ?>">
          <i data-lucide="<?php echo $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle'; ?>" class="w-5 h-5"></i>
          <span class="font-medium text-sm"><?php echo escape($flash['message']); ?></span>
        </div>
      </div>
    <?php endif; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
