<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Mailer.php';

if (!isAdmin()) {
    setFlashMessage('danger', 'Csak adminisztrátorok kezelhetik a felhasználókat!');
    redirect('dashboard.php');
}

$db = Database::getInstance();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validateCsrfToken($csrf)) {
        $action = $_POST['user_action'] ?? 'create';

        if ($action === 'create') {
            $u = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $full = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'operator';
            $locId = !empty($_POST['default_location_id']) ? intval($_POST['default_location_id']) : null;
            $sendInvite = isset($_POST['send_invitation']);
            $p = $_POST['password'] ?? '';

            if (empty($u) || empty($full)) {
                setFlashMessage('danger', 'A felhasználónév és teljes név megadása kötelező!');
            } else {
                $exists = $db->fetchOne("SELECT id FROM users WHERE username = ? OR (email = ? AND email != '')", [$u, $email]);
                if ($exists) {
                    setFlashMessage('danger', "Ez a felhasználónév ({$u}) vagy email cím már foglalt!");
                } else {
                    if ($sendInvite && !empty($email)) {
                        // Meghívó email küldése: véletlen jelszó ideiglenesen + aktiváló token
                        $rawToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $rawToken);
                        $tempHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                        $db->execute("
                            INSERT INTO users (username, email, password_hash, full_name, role, default_location_id, active, email_verified, verification_token_hash)
                            VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)
                        ", [$u, $email, $tempHash, $full, $role, $locId, $tokenHash]);
                        $newId = $db->lastInsertId();

                        $newUser = ['id' => $newId, 'username' => $u, 'full_name' => $full, 'email' => $email, 'role' => $role];

                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || $_SERVER['SERVER_PORT'] == 8443) ? "https://" : "http://";
                        $host = $_SERVER['HTTP_HOST'];
                        $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                        $activationUrl = "{$protocol}{$host}{$basePath}/activate_account.php?token={$rawToken}";

                        $mailer = new Mailer();
                        $mailOk = $mailer->sendUserInvitationEmail($newUser, $activationUrl);

                        $db->execute("
                            INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details)
                            VALUES (?, ?, 'USER_INVITE', 'USER', ?, ?)
                        ", [$currentUser['id'], $currentUser['username'], $newId, "Új munkatárs meghívva emailben: {$u} ({$email})"]);

                        if ($mailOk) {
                            setFlashMessage('success', "Felhasználó létrehozva! A meghívó és aktiváló linket sikeresen elküldtük az email címére ({$email}).");
                        } else {
                            setFlashMessage('warning', "Felhasználó létrehozva, de az email küldése sikertelen: " . $mailer->getLastError());
                        }
                    } else {
                        // Közvetlen jelszó megadása az admin által
                        if (empty($p)) {
                            setFlashMessage('danger', 'Kérjük adjon meg egy jelszót, vagy jelölje be a Meghívó email küldése opciót!');
                        } else {
                            $hash = password_hash($p, PASSWORD_DEFAULT);
                            $db->execute("
                                INSERT INTO users (username, email, password_hash, full_name, role, default_location_id, active, email_verified)
                                VALUES (?, ?, ?, ?, ?, ?, 1, 1)
                            ", [$u, $email ?: null, $hash, $full, $role, $locId]);
                            $newId = $db->lastInsertId();

                            $db->execute("
                                INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details)
                                VALUES (?, ?, 'USER_CREATE', 'USER', ?, ?)
                            ", [$currentUser['id'], $currentUser['username'], $newId, "Új felhasználó közvetlenül létrehozva: {$u} ({$full})"]);

                            setFlashMessage('success', "Felhasználó sikeresen létrehozva: {$u}");
                        }
                    }
                }
            }
        } elseif ($action === 'update') {
            $userId = intval($_POST['user_id'] ?? 0);
            $full = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'operator';
            $locId = !empty($_POST['default_location_id']) ? intval($_POST['default_location_id']) : null;
            $newPassword = $_POST['new_password'] ?? '';
            $active = isset($_POST['active']) ? 1 : 0;

            $targetUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if ($targetUser) {
                if (!empty($newPassword)) {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->execute("
                        UPDATE users 
                        SET full_name = ?, email = ?, role = ?, default_location_id = ?, active = ?, password_hash = ?
                        WHERE id = ?
                    ", [$full, $email ?: null, $role, $locId, $active, $hash, $userId]);
                    $details = "Felhasználó módosítva: {$targetUser['username']} (Új jelszó beállítva, szerepkör: {$role})";
                } else {
                    $db->execute("
                        UPDATE users 
                        SET full_name = ?, email = ?, role = ?, default_location_id = ?, active = ?
                        WHERE id = ?
                    ", [$full, $email ?: null, $role, $locId, $active, $userId]);
                    $details = "Felhasználó módosítva: {$targetUser['username']} (email: {$email}, szerepkör: {$role}, aktív: {$active})";
                }

                $db->execute("
                    INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details)
                    VALUES (?, ?, 'USER_UPDATE', 'USER', ?, ?)
                ", [$currentUser['id'], $currentUser['username'], $userId, $details]);

                setFlashMessage('success', "Felhasználó adatai sikeresen módosítva: {$targetUser['username']}");
            }
        }
        redirect('users.php');
    }
}

$users = $db->fetchAll("
    SELECT u.*, l.short_name as location_short
    FROM users u
    LEFT JOIN locations l ON u.default_location_id = l.id
    ORDER BY u.id ASC
");

$locations = $db->fetchAll("SELECT * FROM locations ORDER BY id ASC");

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold text-slate-900">Rendszerfelhasználók Kezelése</h2>
      <p class="text-xs text-slate-500">Felhasználói fiókok, email hitelesítés és jogosultságok kezelése (Csak Rendszergazda)</p>
    </div>
    <button onclick="openNewUserModal()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm rounded-xl transition-all flex items-center space-x-2 shadow-xs">
      <i data-lucide="user-plus" class="w-4 h-4"></i>
      <span>Új Felhasználó Létrehozása</span>
    </button>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs font-bold uppercase text-left">
          <tr>
            <th class="px-6 py-3.5">Felhasználónév</th>
            <th class="px-6 py-3.5">Teljes Név</th>
            <th class="px-6 py-3.5">Email Cím</th>
            <th class="px-6 py-3.5">Szerepkör</th>
            <th class="px-6 py-3.5">Alapértelmezett Telephely</th>
            <th class="px-6 py-3.5">Fiók Státusz</th>
            <th class="px-6 py-3.5 text-right">Művelet</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <?php foreach ($users as $u): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-6 py-3.5 font-mono font-bold text-slate-900"><?php echo escape($u['username']); ?></td>
              <td class="px-6 py-3.5 font-medium text-slate-900"><?php echo escape($u['full_name']); ?></td>
              <td class="px-6 py-3.5 text-slate-600 font-mono text-xs">
                <?php if ($u['email']): ?>
                  <div class="flex items-center space-x-1.5">
                    <span><?php echo escape($u['email']); ?></span>
                    <?php if ($u['email_verified']): ?>
                      <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-500" title="Hitelesített email"></i>
                    <?php else: ?>
                      <span class="px-1.5 py-0.2 text-[10px] bg-amber-100 text-amber-800 rounded font-sans">Aktiválásra vár</span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <span class="text-slate-400">-</span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-3.5">
                <?php
                  $roleBadge = 'bg-slate-100 text-slate-800';
                  $roleName = 'Operátor';
                  if ($u['role'] === 'admin') {
                    $roleBadge = 'bg-red-100 text-red-800 font-bold';
                    $roleName = 'Rendszergazda';
                  } elseif ($u['role'] === 'viewer') {
                    $roleBadge = 'bg-blue-100 text-blue-800';
                    $roleName = 'Megtekintő (Vezető)';
                  }
                ?>
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full <?php echo $roleBadge; ?>">
                  <?php echo $roleName; ?>
                </span>
              </td>
              <td class="px-6 py-3.5 text-slate-600"><?php echo escape($u['location_short'] ?: 'Összes telephely'); ?></td>
              <td class="px-6 py-3.5">
                <?php if ($u['active']): ?>
                  <span class="px-2 py-0.5 text-xs font-bold text-emerald-800 bg-emerald-100 rounded-full">Aktív</span>
                <?php else: ?>
                  <span class="px-2 py-0.5 text-xs font-bold text-amber-800 bg-amber-100 rounded-full">Meghívva (Inaktív)</span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-3.5 text-right">
                <button onclick='openEditUserModal(<?php echo json_encode($u); ?>)' class="p-1.5 text-slate-500 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-all" title="Szerkesztés & Jelszó">
                  <i data-lucide="edit-3" class="w-4 h-4"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Felhasználó Létrehozás / Szerkesztés Modál -->
<div id="user-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden p-4">
  <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 id="user-modal-title" class="text-lg font-bold text-slate-900">Rendszerfelhasználó</h3>
      <button onclick="document.getElementById('user-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>

    <form method="POST" action="users.php" class="space-y-3 text-sm">
      <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
      <input type="hidden" name="user_action" id="user-form-action" value="create">
      <input type="hidden" name="user_id" id="user-form-id" value="">

      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Felhasználónév *</label>
        <input type="text" name="username" id="user-form-username" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 font-mono">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Email Cím (Értesítésekhez & Jelszóvisszaállításhoz)</label>
        <input type="email" name="email" id="user-form-email" placeholder="pl. kiss.anna@hgabiomed.hu" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">Teljes Név *</label>
        <input type="text" name="full_name" id="user-form-fullname" required placeholder="pl. Kiss Anna" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>

      <!-- Új fióknál: Meghívó küldése checkbox -->
      <div id="invite-option-group" class="p-3 bg-brand-50/70 border border-brand-200 rounded-xl space-y-2">
        <label class="flex items-center space-x-2 text-xs font-bold text-brand-900 cursor-pointer">
          <input type="checkbox" name="send_invitation" id="send-invitation-cb" value="1" checked onchange="toggleInvitationMode()" class="w-4 h-4 text-brand-600 rounded">
          <span>✉️ Meghívó & Aktiváló link küldése emailben</span>
        </label>
        <p class="text-[11px] text-brand-700 leading-tight">A munkatárs emailben kap egyedi linket, ahol maga állítja be a jelszavát és aktiválja a fiókját.</p>
      </div>

      <div id="password-group-create" class="hidden">
        <label class="block text-xs font-bold text-slate-600 mb-1">Jelszó (Közvetlen admin beállítás)</label>
        <input type="password" name="password" id="user-form-password" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>

      <div id="password-group-edit" class="hidden">
        <label class="block text-xs font-bold text-slate-600 mb-1">Új Jelszó beállítása (opcionális)</label>
        <input type="password" name="new_password" id="user-form-new-password" placeholder="Csak ha módosítani kívánja..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Szerepkör</label>
          <select name="role" id="user-form-role" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="operator">Operátor (Raktáros)</option>
            <option value="admin">Adminisztrátor</option>
            <option value="viewer">Megtekintő (Vezető)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Alapért. Telephely</label>
          <select name="default_location_id" id="user-form-location" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <option value="">Összes telephely</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?php echo $loc['id']; ?>"><?php echo escape($loc['code'] . ' - ' . ($loc['short_name'] ?: $loc['name'])); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="active-checkbox-group" class="hidden pt-1">
        <label class="flex items-center space-x-2 text-xs font-bold text-slate-700 cursor-pointer">
          <input type="checkbox" name="active" id="user-form-active" value="1" class="w-4 h-4 text-brand-600 rounded">
          <span>Felhasználói fiók aktív (Bejelentkezhet)</span>
        </label>
      </div>

      <div class="pt-4 flex justify-end space-x-3 border-t border-slate-100">
        <button type="button" onclick="document.getElementById('user-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl">Mégse</button>
        <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-sm">Mentés</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleInvitationMode() {
  const cb = document.getElementById('send-invitation-cb');
  const passGroup = document.getElementById('password-group-create');
  const passInput = document.getElementById('user-form-password');

  if (cb.checked) {
    passGroup.classList.add('hidden');
    passInput.required = false;
    document.getElementById('user-form-email').required = true;
  } else {
    passGroup.classList.remove('hidden');
    passInput.required = true;
    document.getElementById('user-form-email').required = false;
  }
}

function openNewUserModal() {
  document.getElementById('user-modal-title').textContent = 'Új Rendszerfelhasználó Létrehozása';
  document.getElementById('user-form-action').value = 'create';
  document.getElementById('user-form-id').value = '';
  document.getElementById('user-form-username').value = '';
  document.getElementById('user-form-username').readOnly = false;
  document.getElementById('user-form-email').value = '';
  document.getElementById('user-form-fullname').value = '';
  document.getElementById('invite-option-group').classList.remove('hidden');
  document.getElementById('send-invitation-cb').checked = true;
  toggleInvitationMode();
  document.getElementById('password-group-edit').classList.add('hidden');
  document.getElementById('active-checkbox-group').classList.add('hidden');
  document.getElementById('user-modal').classList.remove('hidden');
}

function openEditUserModal(u) {
  document.getElementById('user-modal-title').textContent = 'Felhasználó Módosítása & Jelszó';
  document.getElementById('user-form-action').value = 'update';
  document.getElementById('user-form-id').value = u.id;
  document.getElementById('user-form-username').value = u.username;
  document.getElementById('user-form-username').readOnly = true;
  document.getElementById('user-form-email').value = u.email || '';
  document.getElementById('user-form-fullname').value = u.full_name;
  document.getElementById('user-form-role').value = u.role;
  document.getElementById('user-form-location').value = u.default_location_id || '';
  document.getElementById('invite-option-group').classList.add('hidden');
  document.getElementById('password-group-create').classList.add('hidden');
  document.getElementById('user-form-password').required = false;
  document.getElementById('password-group-edit').classList.remove('hidden');
  document.getElementById('user-form-new-password').value = '';
  document.getElementById('active-checkbox-group').classList.remove('hidden');
  document.getElementById('user-form-active').checked = (u.active == 1);
  document.getElementById('user-modal').classList.remove('hidden');
  if (window.lucide) lucide.createIcons();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
