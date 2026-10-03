<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: ' . url('account.php'));
    exit;
}

$next = (string) ($_GET['next'] ?? '');
if ($next === '' || str_contains($next, '://') || str_starts_with($next, '//')) {
    $next = (string) ($_SESSION['login_next'] ?? 'account.php');
}
$_SESSION['login_next'] = $next;

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = db_one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        $error = 'That email and password combination does not match our records.';
    } elseif ($user['role'] === 'admin') {
        login_user($user);
        flash_set('success', 'Welcome back, ' . $user['name'] . '.');
        header('Location: ' . url('admin/index.php'));
        exit;
    } else {
        login_user($user);
        flash_set('success', 'Welcome back, ' . $user['name'] . '.');
        $target = $next !== '' && !str_starts_with($next, 'admin/') ? $next : 'account.php';
        header('Location: ' . (str_starts_with($target, '/') ? $target : url($target)));
        exit;
    }
}

set_title('Sign In | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex

$artSide = 'left';  // desktop: art left, form right
render_head();
?>
<main class="relative w-full">
  <?php require __DIR__ . '/includes/auth_art.php'; ?>

  <div class="relative flex min-h-screen w-full lg:w-1/2 lg:ml-auto items-center justify-center px-5 py-24 lg:py-16">
    <div class="w-full max-w-md flex flex-col gap-4">
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em]">Zion Club</span>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mt-1">Sign in to your account</h1>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Track deliveries, save addresses and keep your wishlist in sync.</p>

        <?php if ($error !== ''): ?>
          <div class="mt-4 px-4 py-3 rounded-lg bg-error-container text-on-error-container font-body-sm text-body-sm flex items-start gap-2">
            <span class="material-symbols-outlined text-base mt-0.5">error</span><span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" class="mt-5 flex flex-col gap-space-sm">
          <?= csrf_field() ?>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Email</span>
            <input class="h-11 px-3 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email"/>
          </label>
          <div class="flex flex-col gap-1">
            <label for="login-password" class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Password</label>
            <div class="relative flex items-center">
              <input id="login-password" class="h-11 w-full pl-3 pr-10 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                     type="password" name="password" required autocomplete="current-password"/>
              <button type="button" class="absolute right-0 top-0 bottom-0 px-3 flex items-center text-on-surface-variant hover:text-on-surface focus:outline-none focus-visible:text-primary transition-colors cursor-pointer"
                      onclick="togglePasswordVisibility(this)" data-toggle-password aria-label="Show password" title="Toggle password visibility">
                <span class="material-symbols-outlined text-xl select-none">visibility</span>
              </button>
            </div>
          </div>
          <button class="mt-2 w-full py-3.5 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-widest rounded-lg shadow-md hover:bg-primary transition-colors"
                  type="submit">Sign In</button>
        </form>

        <p class="mt-4 font-body-sm text-body-sm text-on-surface-variant text-center">
          New to Zion Groups? <a class="text-primary font-semibold underline" href="<?= e(url('register.php')) ?>">Create an account</a>
        </p>
      </div>

      <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
        <p class="font-label-tag text-label-tag uppercase tracking-wider text-secondary mb-1">Demo credentials</p>
        <p class="font-body-sm text-body-sm text-on-surface-variant">
          Customer &mdash; <span class="font-semibold text-on-surface">kwame.mensah@ziongroups.com.gh</span> / <span class="font-semibold text-on-surface">Customer123!</span><br/>
          Admin &mdash; <span class="font-semibold text-on-surface">admin@ziongroups.com.gh</span> / <span class="font-semibold text-on-surface">Admin123!</span>
        </p>
      </div>
    </div>
  </div>
</main>
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
<script src="<?= e(url('assets/auth-slider.js')) ?>"></script>
<?php render_foot(); ?>
