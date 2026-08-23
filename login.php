<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

if (isLoggedIn()) {
    redirect('scanner.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $error = 'Érvénytelen biztonsági token (CSRF)!';
    } elseif (empty($username) || empty($password)) {
        $error = 'Kérjük adja meg a felhasználónevet és jelszót!';
    } else {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM users WHERE username = ? AND active = 1", [$username]);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['location_id'] = $user['default_location_id'];

            $db->execute(
                "INSERT INTO audit_logs (user_id, username, action, entity_type, details, location_id) VALUES (?, ?, 'LOGIN', 'USER', 'Sikeres bejelentkezés', ?)",
                [$user['id'], $user['username'], $user['default_location_id']]
            );

            redirect('scanner.php');
        } else {
            $error = 'Helytelen felhasználónév vagy jelszó!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu" class="h-full bg-slate-900">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bejelentkezés - HGA Biomed Munkaruha Rendszer</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
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
</head>
<body class="h-full flex items-center justify-center p-4">

  <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 border border-slate-100">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-50 text-brand-600 mb-4 shadow-inner">
        <i data-lucide="shirt" class="w-8 h-8"></i>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">HGA Biomed Kft.</h1>
      <p class="text-sm text-slate-500 mt-1 font-medium">Munkaruha és Mosodai Nyilvántartó</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-5 p-3.5 text-sm text-red-700 bg-red-50 rounded-xl border border-red-200 font-medium flex items-center space-x-2">
        <i data-lucide="alert-circle" class="w-4 h-4 text-red-500"></i>
        <span><?php echo escape($error); ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Felhasználónév</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
            <i data-lucide="user" class="w-5 h-5"></i>
          </span>
          <input type="text" name="username" required autocomplete="username" autofocus
            class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none transition-all"
            placeholder="pl. admin vagy jutai_operator">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Jelszó</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
            <i data-lucide="lock" class="w-5 h-5"></i>
          </span>
          <input type="password" name="password" required autocomplete="current-password"
            class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white focus:outline-none transition-all"
            placeholder="••••••••">
        </div>
      </div>

      <button type="submit"
        class="w-full py-3.5 px-4 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold rounded-xl shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center space-x-2">
        <span>Bejelentkezés</span>
        <i data-lucide="arrow-right" class="w-4 h-4"></i>
      </button>
    </form>

    <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-slate-400 text-center space-y-1">
      <p>Alapértelmezett fiókok: <span class="font-mono text-slate-600 font-semibold">admin / admin123</span></p>
      <p><span class="font-mono text-slate-600 font-semibold">jutai_operator / jutai123</span> | <span class="font-mono text-slate-600 font-semibold">nagygat_operator / nagygat123</span></p>
    </div>
  </div>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
