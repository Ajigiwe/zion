<?php
/** Inbox for messages sent from the storefront contact page. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    $msgId = (int) ($_POST['id'] ?? 0);
    $row = $msgId > 0 ? db_one('SELECT id FROM contact_messages WHERE id = ?', [$msgId]) : null;

    if ($row === null) {
        flash_set('error', 'That message no longer exists.');
    } elseif ($act === 'read') {
        db_exec('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$msgId]);
        flash_set('success', 'Marked as read.');
    } elseif ($act === 'unread') {
        db_exec('UPDATE contact_messages SET is_read = 0 WHERE id = ?', [$msgId]);
        flash_set('info', 'Marked as unread.');
    } elseif ($act === 'delete') {
        db_exec('DELETE FROM contact_messages WHERE id = ?', [$msgId]);
        flash_set('success', 'Message deleted.');
        $id = 0;
    } elseif ($act === 'read_all') {
        db_exec('UPDATE contact_messages SET is_read = 1');
        flash_set('success', 'All messages marked as read.');
    }

    header('Location: ' . url('admin/messages.php' . ($id > 0 ? '?id=' . $id : '')));
    exit;
}

$unread = (int) db_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0', [], 0);

admin_head('Messages', 'messages');
?>
<div class="max-w-5xl">
  <nav class="font-label-nav text-label-nav text-on-surface-variant mb-3 flex items-center gap-2">
    <a class="hover:text-primary" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
    <span class="text-outline-variant">/</span>
    <span class="text-primary font-semibold">Messages</span>
  </nav>

  <div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
    <div>
      <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Contact inbox</span>
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Messages</h1>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
        <?= e(plural($unread, 'unread message', 'unread messages')) ?> of <?= e(plural((int) db_val('SELECT COUNT(*) FROM contact_messages', [], 0), 'message', 'messages')) ?> total.
      </p>
    </div>
    <?php if ($unread > 0): ?>
      <form data-ajax method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="read_all"/>
        <button class="px-4 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container-lowest"
                type="submit">Mark all as read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($id > 0): ?>
    <?php
    $msg = db_one('SELECT * FROM contact_messages WHERE id = ?', [$id]);
    if ($msg === null) {
        flash_set('error', 'That message no longer exists.');
        header('Location: ' . url('admin/messages.php'));
        exit;
    }
    if ((int) $msg['is_read'] === 0) {
        db_exec('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$id]);
    }
    ?>
    <a class="inline-flex items-center gap-1.5 font-label-nav text-label-nav uppercase text-on-surface-variant hover:text-primary mb-space-sm"
       href="<?= e(url('admin/messages.php')) ?>">
      <span class="material-symbols-outlined text-base">arrow_back</span> Back to inbox
    </a>

    <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex flex-wrap items-start justify-between gap-3 border-b border-outline-variant/60 pb-space-sm mb-space-sm">
        <div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold"><?= e($msg['subject']) ?></h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            From <strong class="text-on-surface"><?= e($msg['name']) ?></strong>
            &middot; <a class="text-primary underline" href="<?= e('mailto:' . $msg['email']) ?>"><?= e($msg['email']) ?></a>
            <?php if (($msg['phone'] ?? '') !== ''): ?>
              &middot; <a class="text-primary underline" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $msg['phone'])) ?>"><?= e($msg['phone']) ?></a>
            <?php endif; ?>
          </p>
          <p class="font-body-sm text-body-sm text-on-surface-variant"><?= e(date('D, d M Y - H:i', strtotime((string) $msg['created_at']))) ?></p>
        </div>
        <div class="flex gap-2">
          <a class="px-4 py-2 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav uppercase hover:bg-primary"
             href="<?= e('mailto:' . $msg['email'] . '?subject=' . rawurlencode('Re: ' . $msg['subject'])) ?>">Reply by email</a>
        </div>
      </div>

      <p class="font-body-md text-body-md text-on-surface whitespace-pre-line"><?= e($msg['message']) ?></p>

      <div class="flex flex-wrap gap-3 mt-space-md pt-space-sm border-t border-outline-variant/60">
        <form data-ajax method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="<?= (int) $msg['is_read'] === 1 ? 'unread' : 'read' ?>"/>
          <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>"/>
          <button class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container"
                  type="submit"><?= (int) $msg['is_read'] === 1 ? 'Mark unread' : 'Mark read' ?></button>
        </form>
        <form data-ajax method="post" onsubmit="return confirm('Delete this message?');">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="delete"/>
          <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>"/>
          <button class="px-4 py-2 border border-error-container text-error rounded-lg font-label-nav text-label-nav uppercase hover:bg-error-container/40"
                  type="submit">Delete</button>
        </form>
      </div>
    </div>

  <?php else: ?>
    <?php $rows = db_all('SELECT * FROM contact_messages ORDER BY created_at DESC'); ?>
    <div class="bg-surface-container-lowest rounded-xl shadow-xs overflow-hidden">
      <?php if ($rows === []): ?>
        <div class="p-12 text-center">
          <span class="material-symbols-outlined text-4xl text-outline">inbox</span>
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mt-3">No messages yet</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Messages sent from the contact page will land here.</p>
          <a class="inline-block mt-4 px-5 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant"
             target="_blank" rel="noopener" href="<?= e(url('page.php?slug=contact')) ?>">Open the contact page</a>
        </div>
      <?php else: ?>
        <ul class="divide-y divide-outline-variant/60">
          <?php foreach ($rows as $m): ?>
            <li>
              <a class="flex items-start gap-3 px-4 py-3 hover:bg-surface-container transition-colors <?= (int) $m['is_read'] === 0 ? 'bg-primary-fixed/30' : '' ?>"
                 href="<?= e(url('admin/messages.php?id=' . (int) $m['id'])) ?>">
                <span class="mt-0.5 w-2 h-2 rounded-full shrink-0 <?= (int) $m['is_read'] === 0 ? 'bg-primary' : 'bg-outline-variant' ?>"></span>
                <span class="min-w-0 flex-1">
                  <span class="flex flex-wrap items-baseline gap-2">
                    <span class="font-body-sm text-body-sm font-bold text-on-surface"><?= e($m['name']) ?></span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant truncate"><?= e($m['email']) ?></span>
                    <span class="ml-auto font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap"><?= e(date('d M Y', strtotime((string) $m['created_at']))) ?></span>
                  </span>
                  <span class="font-body-sm text-body-sm <?= (int) $m['is_read'] === 0 ? 'text-on-surface font-semibold' : 'text-on-surface-variant' ?> block truncate">
                    <?= e($m['subject']) ?> &mdash; <?= e(mb_strimwidth((string) $m['message'], 0, 90, '...')) ?>
                  </span>
                </span>
                <span class="material-symbols-outlined text-lg text-outline self-center">chevron_right</span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php admin_foot(); ?>
