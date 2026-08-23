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
<html lang="hu" class="h-full bg-slate-50">
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
      theme: {
        extend: {
          colors: {
            brand: { 50: '#f0fdf4', 100: '#dcfce7', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' }
          }
        }
      }
    }
  </script>
  <style>
    @media print {
      body * { visibility: hidden; }
      #printable-area, #printable-area * { visibility: visible; }
      #printable-area { position: absolute; left: 0; top: 0; width: 100%; }
    }
  </style>
</head>
<body class="h-full font-sans text-slate-800 antialiased flex flex-col">

  <div class="flex-1 flex flex-col min-h-screen">
    <!-- FEJLÉC -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
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
                  <span class="font-bold text-slate-900 text-lg leading-none"><?php echo escape($companyName); ?></span>
                  <span class="px-2 py-0.5 text-xs font-semibold bg-brand-100 text-brand-800 rounded-full"><?php echo escape(getAppVersion()); ?></span>
                </div>
                <p class="text-xs text-slate-500 font-medium">Munkaruha & Mosoda Rendszer</p>
              </div>
            <?php endif; ?>
          </a>

          <!-- Telephely választó -->
          <div class="hidden md:flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 px-2 flex items-center">
              <i data-lucide="map-pin" class="w-3.5 h-3.5 mr-1 text-slate-400"></i> Telephely:
            </span>
            <select id="global-location-select" onchange="window.location.href='?location_id=' + this.value" class="text-sm font-semibold text-slate-700 bg-white px-3 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-xs">
              <option value="" <?php echo $activeLocationId === '' ? 'selected' : ''; ?>>Mindkét telephely (Összes)</option>
              <?php foreach ($allLocations as $loc): ?>
                <option value="<?php echo $loc['id']; ?>" <?php echo $activeLocationId == $loc['id'] ? 'selected' : ''; ?>>
                  <?php echo escape($loc['code'] . ' - ' . ($loc['short_name'] ?: $loc['name'])); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Profil & Kilépés -->
          <div class="flex items-center space-x-3">
            <?php if ($currentUser): ?>
              <a href="profile.php" class="text-right hidden sm:block hover:opacity-80 transition-opacity">
                <p class="text-sm font-bold text-slate-800 leading-tight flex items-center justify-end space-x-1">
                  <span><?php echo escape($currentUser['full_name']); ?></span>
                  <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                </p>
                <span class="text-xs font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded capitalize"><?php echo escape($currentUser['role']); ?></span>
              </a>
              <a href="logout.php" title="Kijelentkezés" class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                <i data-lucide="log-out" class="w-5 h-5"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
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
