<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$db = Database::getInstance();
$settingsObj = new Settings();
$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');
$companyLogo = $settingsObj->get('company_logo', '');

$rawToken = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$message = '';
$msgType = 'danger';
$validToken = false;
$user = null;

if (!empty($rawToken)) {
    $tokenHash = hash('sha256', $rawToken);
    $user = $db->fetchOne("
        SELECT * FROM users 
        WHERE verification_token_hash = ?
        LIMIT 1
    ", [$tokenHash]);

    if ($user) {
        $validToken = true;
    } else {
        $message = "A megadott aktiváló link érvénytelen vagy már felhasználták!";
    }
} else {
    $message = "Hiányzó vagy érvénytelen aktiváló token!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['password_confirm'] ?? '';

        if (strlen($p1) < 6) {
            $message = "A jelszónak legalább 6 karakter hosszúnak kell lennie!";
        } elseif ($p1 !== $p2) {
            $message = "A megadott jelszavak nem egyeznek meg!";
        } else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $db->execute("
                UPDATE users 
                SET password_hash = ?, verification_token_hash = NULL, active = 1, email_verified = 1 
                WHERE id = ?
            ", [$hash, $user['id']]);

            $db->execute("
                INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details)
                VALUES (?, ?, 'USER_ACTIVATED', 'USER', ?, 'Felhasználói fiók sikeresen aktiválva és jelszó beállítva')
            ", [$user['id'], $user['username'], $user['id']]);

            setFlashMessage('success', 'Fiókja sikeresen aktiválva! Most már bejelentkezhet a megadott jelszavával.');
            redirect('login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu" class="h-full bg-slate-900">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fiók Aktiválása - <?php echo escape($companyName); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' }
          }
        }
      }
    }
  </script>
</head>
<body class="h-full flex items-center justify-center p-4 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 font-sans text-slate-100">

  <div class="max-w-md w-full space-y-8 bg-white/5 backdrop-blur-xl p-8 rounded-3xl border border-white/10 shadow-2xl">
    <!-- Logó & Fejléc -->
    <div class="text-center space-y-3">
      <?php if ($companyLogo && file_exists(__DIR__ . '/' . strtok($companyLogo, '?'))): ?>
        <div class="flex justify-center mb-4">
          <img src="<?php echo escape($companyLogo); ?>" alt="<?php echo escape($companyName); ?>" class="max-h-12 max-w-[200px] object-contain">
        </div>
      <?php else: ?>
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-600/20 text-brand-400 border border-brand-500/30 mb-2">
          <i data-lucide="user-check" class="w-8 h-8"></i>
        </div>
      <?php endif; ?>

      <h1 class="text-2xl font-black text-white tracking-tight">Fiók Aktiválása</h1>
      <?php if ($user): ?>
        <p class="text-xs text-slate-400">Üdvözöljük, <strong class="text-white"><?php echo escape($user['full_name']); ?></strong>!<br>Felhasználónév: <span class="font-mono text-brand-400 font-bold"><?php echo escape($user['username']); ?></span></p>
      <?php endif; ?>
    </div>

    <!-- Hibaüzenet -->
    <?php if ($message): ?>
      <div class="p-4 rounded-xl text-xs font-medium border bg-red-950/60 border-red-500/40 text-red-300">
        <div class="flex items-start space-x-2">
          <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i>
          <span><?php echo escape($message); ?></span>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($validToken): ?>
      <!-- ŰRLAP -->
      <form method="POST" action="activate_account.php" class="space-y-4 text-sm">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
        <input type="hidden" name="token" value="<?php echo escape($rawToken); ?>">

        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1.5">Adja meg új jelszavát (min. 6 karakter)</label>
          <div class="relative">
            <i data-lucide="lock" class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="password" name="password" required minlength="6" autofocus placeholder="••••••••"
              class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1.5">Jelszó Megerősítése</label>
          <div class="relative">
            <i data-lucide="lock-check" class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="password" name="password_confirm" required minlength="6" placeholder="••••••••"
              class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
          </div>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-xl shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center space-x-2">
          <i data-lucide="check-circle" class="w-4 h-4"></i>
          <span>Fiók Aktiválása & Belépés</span>
        </button>
      </form>
    <?php endif; ?>

    <div class="text-center pt-2 border-t border-white/10">
      <a href="login.php" class="inline-flex items-center space-x-1.5 text-xs font-semibold text-slate-400 hover:text-white transition-colors">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
        <span>Vissza a bejelentkezéshez</span>
      </a>
    </div>
  </div>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
