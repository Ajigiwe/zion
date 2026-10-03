<?php
/** Staff sign-in for the Zion Groups of Companies back office. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';

if (is_admin()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $user     = db_one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);

    if ($user === null || $user['role'] !== 'admin') {
        $error = 'Those credentials are not valid for the back office.';
    } elseif (!password_verify($password, (string) $user['password_hash'])) {
        $error = 'Those credentials are not valid for the back office.';
    } else {
        login_user($user);
        flash_set('success', 'Welcome back, ' . strtok((string) $user['name'], ' ') . '.');
        header('Location: ' . url('admin/index.php'));
        exit;
    }
}

set_title('Back Office Sign-in | Zion Groups');
    set_meta(null, null, true);  // back office is never indexed
require __DIR__ . '/../includes/head.php';
?>
<div class="min-h-screen flex items-center justify-center px-margin bg-surface-container">
  <div class="w-full max-w-md bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
    <div class="flex justify-center">
      <img alt="Zion Group of Companies" class="h-36 w-auto" src="<?= e(url('assets/logo.svg')) ?>"/>
    </div>
    <div class="flex justify-center mb-4">
      <span class="font-label-tag text-label-tag uppercase tracking-[0.3em] text-secondary">Back Office</span>
    </div>

    <?php if ($error !== ''): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-error-container text-on-error-container font-body-sm text-body-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-base">error</span><?= e($error) ?>
      </div>
    <?php endif; ?>
    <?php foreach (flash_all() as $f): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-primary-fixed text-primary font-body-sm text-body-sm"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" class="flex flex-col gap-space-sm">
      <?= csrf_field() ?>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Staff email</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               type="email" name="email" required value="<?= e($_POST['email'] ?? 'admin@ziongroups.com.gh') ?>" autocomplete="username"/>
      </label>
      <div class="flex flex-col gap-1">
        <label for="admin-password" class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Password</label>
        <div class="relative flex items-center">
          <input id="admin-password" class="h-11 w-full pl-3 pr-10 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                 type="password" name="password" required autocomplete="current-password"/>
          <button type="button" class="absolute right-0 top-0 bottom-0 px-3 flex items-center text-on-surface-variant hover:text-on-surface focus:outline-none focus-visible:text-primary transition-colors cursor-pointer"
                  onclick="togglePasswordVisibility(this)" data-toggle-password aria-label="Show password" title="Toggle password visibility">
            <span class="material-symbols-outlined text-xl select-none">visibility</span>
          </button>
        </div>
      </div>
      <button class="mt-1 w-full py-3.5 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-widest rounded-lg shadow-md hover:bg-primary transition-colors"
              type="submit">Sign In</button>
    </form>

    <p class="mt-4 font-body-sm text-body-sm text-on-surface-variant text-center">
      <a class="text-primary underline" href="<?= e(url('index.php')) ?>">&larr; Return to the storefront</a>
    </p>
  </div>
</div>
<script>
function togglePasswordVisibility(btn) {
  if (!btn) return;
  var wrapper = btn.closest('.relative') || btn.parentElement;
  var input = wrapper ? wrapper.querySelector('input') : null;
  if (!input) return;
  var isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
  var icon = btn.querySelector('.material-symbols-outlined');
  if (icon) {
    icon.textContent = isPassword ? 'visibility_off' : 'visibility';
  }
}
</script>
<script src="<?= e(url('assets/ajax.js')) ?>"></script>
</body>
</html>
