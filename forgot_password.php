<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';
require_once __DIR__ . '/classes/Mailer.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$db = Database::getInstance();
$settingsObj = new Settings();
$companyName = $settingsObj->get('company_name', 'HGA Biomed Kft.');
$companyLogo = $settingsObj->get('company_logo', '');

$message = '';
$msgType = 'info';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $input = trim($_POST['email_or_user'] ?? '');
        
        if (!empty($input)) {
            $user = $db->fetchOne("
                SELECT * FROM users 
                WHERE (email = ? OR username = ?) AND active = 1
                LIMIT 1
            ", [$input, $input]);

            if ($user && !empty($user['email'])) {
                // Biztonságos 32 byte-os véletlen token
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                // 1 órás érvényesség mentése
                $db->execute("
                    UPDATE users 
                    SET reset_token_hash = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                    WHERE id = ?
                ", [$tokenHash, $user['id']]);

                // Bázis URL összeállítása
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || $_SERVER['SERVER_PORT'] == 8443) ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $resetUrl = "{$protocol}{$host}{$basePath}/reset_password.php?token={$rawToken}";

                // Email kiküldése
                $mailer = new Mailer();
                $ok = $mailer->sendPasswordResetEmail($user, $resetUrl);

                if ($ok) {
                    $sent = true;
                    $message = "A jelszó-visszaállító linket sikeresen elküldtük a regisztrált email címre ({$user['email']})!";
                    $msgType = 'success';

                    $db->execute("
                        INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details)
                        VALUES (?, ?, 'PASSWORD_RESET_REQUEST', 'USER', ?, 'Jelszó-visszaállítási kérelem elküldve emailben')
                    ", [$user['id'], $user['username'], $user['id']]);
                } else {
                    $message = "Nem sikerült elküldeni az emailt: " . $mailer->getLastError();
                    $msgType = 'danger';
                }
            } else {
                // Biztonsági okokból általános üzenet, hogy ne lehessen email címeket tesztelni
                $sent = true;
                $message = "Amennyiben a megadott felhasználónév vagy email cím szerepel a rendszerben, a visszaállító linket elküldtük!";
                $msgType = 'success';
            }
        } else {
            $message = "Kérjük adja meg az email címét vagy felhasználónevét!";
            $msgType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu" class="h-full bg-slate-900">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Elfelejtett Jelszó - <?php echo escape($companyName); ?></title>
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
          <i data-lucide="key-round" class="w-8 h-8"></i>
        </div>
      <?php endif; ?>

      <h1 class="text-2xl font-black text-white tracking-tight">Elfelejtett Jelszó</h1>
      <p class="text-xs text-slate-400">Adja meg regisztrált email címét vagy felhasználónevét a jelszó visszaállításához</p>
    </div>

    <!-- Értesítések -->
    <?php if ($message): ?>
      <div class="p-4 rounded-xl text-xs font-medium border <?php echo $msgType === 'success' ? 'bg-emerald-950/60 border-emerald-500/40 text-emerald-300' : 'bg-red-950/60 border-red-500/40 text-red-300'; ?>">
        <div class="flex items-start space-x-2">
          <i data-lucide="<?php echo $msgType === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-4 h-4 mt-0.5 shrink-0"></i>
          <span><?php echo escape($message); ?></span>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$sent): ?>
      <!-- ŰRLAP -->
      <form method="POST" action="forgot_password.php" class="space-y-4 text-sm">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1.5">Email cím vagy Felhasználónév</label>
          <div class="relative">
            <i data-lucide="mail" class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="email_or_user" required autofocus placeholder="pl. kiss.anna@ceg.hu vagy admin"
              class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
          </div>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-xl shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center space-x-2">
          <i data-lucide="send" class="w-4 h-4"></i>
          <span>Visszaállító Link Küldése</span>
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
