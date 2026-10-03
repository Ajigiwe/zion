<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: ' . url('account.php'));
    exit;
}

$next = (string) ($_REQUEST['next'] ?? '');
if ($next === '' || str_contains($next, '://') || str_starts_with($next, '//') || str_starts_with($next, 'admin/')) {
    $next = 'account.php';
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone    = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } elseif (db_one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
        $error = 'An account already exists for that email address.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_exec(
            'INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,?)',
            [$name, $email, $phone !== '' ? $phone : null, $hash, 'customer']
        );
        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        login_user($user);
        flash_set('success', 'Welcome to the Zion Club, ' . strtok($name, ' ') . '.');
        header('Location: ' . url($next));
        exit;
    }
}

set_title('Create Account | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex

$artSide = 'right';  // desktop: form left, art right
render_head();
?>
<main class="relative w-full">
  <?php require __DIR__ . '/includes/auth_art.php'; ?>

  <div class="relative flex min-h-screen w-full lg:w-1/2 lg:mr-auto items-center justify-center px-5 py-24 lg:py-16">
    <div class="w-full max-w-md">
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em]">Zion Club</span>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mt-1">Create your account</h1>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Private checkout, discreet delivery tracking and VIP vouchers.</p>

        <?php if ($error !== ''): ?>
          <div class="mt-4 px-4 py-3 rounded-lg bg-error-container text-on-error-container font-body-sm text-body-sm flex items-start gap-2">
            <span class="material-symbols-outlined text-base mt-0.5">error</span><span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" class="mt-5 flex flex-col gap-space-sm">
          <?= csrf_field() ?>
          <input type="hidden" name="next" value="<?= e($next) ?>"/>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Full Name <span class="text-error">*</span></span>
            <input class="h-11 px-3 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="name" required value="<?= e($_POST['name'] ?? '') ?>" autocomplete="name"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Email <span class="text-error">*</span></span>
            <input class="h-11 px-3 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Phone (for MoMo &amp; delivery)</span>
            <div class="flex">
              <span class="h-11 px-3 flex items-center bg-surface-container border border-outline-variant border-r-0 rounded-l font-body-sm text-body-sm text-on-surface-variant">+233</span>
              <input class="h-11 flex-1 px-3 bg-surface-container-lowest border border-outline-variant rounded-r font-body-sm text-body-sm focus:border-primary outline-none"
                     name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="24 492 8812" autocomplete="tel"/>
            </div>
          </label>
          <div class="flex flex-col gap-1">
            <label for="reg-password" class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Password <span class="text-error">*</span></label>
            <div class="relative flex items-center">
              <input id="reg-password" class="h-11 w-full pl-3 pr-10 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                     type="password" name="password" required minlength="8" autocomplete="new-password"/>
              <button type="button" class="absolute right-0 top-0 bottom-0 px-3 flex items-center text-on-surface-variant hover:text-on-surface focus:outline-none focus-visible:text-primary transition-colors cursor-pointer"
                      onclick="togglePasswordVisibility(this)" data-toggle-password aria-label="Show password" title="Toggle password visibility">
                <span class="material-symbols-outlined text-xl select-none">visibility</span>
              </button>
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label for="reg-password-confirm" class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Confirm Password <span class="text-error">*</span></label>
            <div class="relative flex items-center">
              <input id="reg-password-confirm" class="h-11 w-full pl-3 pr-10 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                     type="password" name="password_confirm" required minlength="8" autocomplete="new-password"/>
              <button type="button" class="absolute right-0 top-0 bottom-0 px-3 flex items-center text-on-surface-variant hover:text-on-surface focus:outline-none focus-visible:text-primary transition-colors cursor-pointer"
                      onclick="togglePasswordVisibility(this)" data-toggle-password aria-label="Show password" title="Toggle password visibility">
                <span class="material-symbols-outlined text-xl select-none">visibility</span>
              </button>
            </div>
          </div>
          <button class="mt-2 w-full py-3.5 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-widest rounded-lg shadow-md hover:bg-primary transition-colors"
                  type="submit">Create Account</button>
        </form>

        <p class="mt-4 font-body-sm text-body-sm text-on-surface-variant text-center">
          Already a member? <a class="text-primary font-semibold underline" href="<?= e(url('login.php?next=' . urlencode($next))) ?>">Sign in</a>
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
