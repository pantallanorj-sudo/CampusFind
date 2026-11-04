<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$f = array('q' => get_str('q', 80), 'role' => get_enum('role', UserService::roles(), ''), 'active' => get_enum('active', array('0', '1'), ''));
$result = UserService::search($f, max(1, get_int('page', 1)), SettingsService::perPage());
$pg = $result['pg'];
$old = take_old();
$roleLabels = array('student' => 'Student', 'staff' => 'Staff', 'admin' => 'Administrator');
$filtered = ($f['q'] !== '' || $f['role'] !== '' || $f['active'] !== '');
$pageTitle = 'Manage users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Users &amp; roles</h1><p>Only administrators can create accounts, change roles or deactivate users. Accounts with history are deactivated, never deleted.</p></div></div>
<details class="panel"><summary class="btn primary small">Add a user</summary>
  <form method="post" action="<?= e(url('actions/save-user.php')) ?>" data-submit-guard><?= csrf_field() ?>
    <div class="form-grid">
      <label>Full name<input name="full_name" maxlength="100" required value="<?= e(ov($old, 'full_name')) ?>"></label>
      <label>Email<input type="email" name="email" maxlength="190" required value="<?= e(ov($old, 'email')) ?>"></label>
      <label>Role<select name="role" required><?= select_options($roleLabels, ov($old, 'role', 'staff')) ?></select></label>
      <label>Temporary password<input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"></label>
    </div>
    <button class="btn primary" type="submit">Create account</button>
  </form>
</details>
<form method="get" class="filters" action="<?= e(url('admin/manage-users.php')) ?>">
  <label class="grow">Search<input type="search" name="q" maxlength="80" value="<?= e($f['q']) ?>" placeholder="Name or email"></label>
  <label>Role<select name="role"><?= select_options($roleLabels, $f['role'], 'All roles') ?></select></label>
  <label>Status<select name="active"><?= select_options(array('1' => 'Active', '0' => 'Inactive'), $f['active'], 'Any status') ?></select></label>
  <button class="btn primary" type="submit">Filter</button>
  <?php if ($filtered): ?><a class="btn ghost" href="<?= e(url('admin/manage-users.php')) ?>">Clear filters</a><?php endif; ?>
</form>
<?php if (count($result['rows']) === 0): ?><?= empty_state('No users match', 'Try a different search.') ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Activity</th><th>Actions</th></tr></thead>
  <tbody><?php foreach ($result['rows'] as $u): $self = ((int) $u['id'] === (int) $me['id']); ?>
    <tr>
      <td><strong><?= e($u['full_name']) ?></strong><?= $self ? ' <span class="pill">you</span>' : '' ?><br><small class="muted"><?= e($u['email']) ?></small></td>
      <td><?= e($roleLabels[$u['role']]) ?></td>
      <td><?= (int) $u['is_active'] === 1 ? '<span class="badge badge-approved">Active</span>' : '<span class="badge badge-rejected">Inactive</span>' ?></td>
      <td><?= e(fmt_dt($u['created_at'], false)) ?></td>
      <td><small><?= e(plural($u['report_count'], 'report')) ?>, <?= e(plural($u['claim_count'], 'claim')) ?></small></td>
      <td class="table-actions">
        <?php if ($self): ?><small class="muted">Your own account is protected from changes here.</small>
        <?php else: ?>
          <form method="post" action="<?= e(url('actions/update-user.php')) ?>" class="inline-form button-row">
            <?= csrf_field() ?><?= hidden_field('op', 'set_role') ?><?= hidden_field('user_id', $u['id']) ?>
            <label class="sr-only" for="role-<?= (int) $u['id'] ?>">Role for <?= e($u['full_name']) ?></label>
            <select id="role-<?= (int) $u['id'] ?>" name="role"><?= select_options($roleLabels, $u['role']) ?></select>
            <button class="btn small secondary" type="submit" data-confirm="Change the role of <?= e($u['full_name']) ?>?">Save role</button>
          </form>
          <form method="post" action="<?= e(url('actions/update-user.php')) ?>" class="inline-form">
            <?= csrf_field() ?><?= hidden_field('op', 'set_active') ?><?= hidden_field('user_id', $u['id']) ?><?= hidden_field('active', (int) $u['is_active'] === 1 ? '0' : '1') ?>
            <button class="btn small <?= (int) $u['is_active'] === 1 ? 'danger' : 'primary' ?>" type="submit" <?= (int) $u['is_active'] === 1 ? 'data-confirm="Deactivate ' . e($u['full_name']) . '? They will no longer be able to log in."' : '' ?>><?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <details class="inline-details"><summary class="btn small ghost">Reset password</summary>
            <form method="post" action="<?= e(url('actions/update-user.php')) ?>" class="inline-form button-row">
              <?= csrf_field() ?><?= hidden_field('op', 'reset_password') ?><?= hidden_field('user_id', $u['id']) ?>
              <label class="sr-only" for="pw-<?= (int) $u['id'] ?>">New password</label><input id="pw-<?= (int) $u['id'] ?>" type="password" name="new_password" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="New password">
              <button class="btn small secondary" type="submit">Set</button>
            </form></details>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?= pager_html($pg, 'admin/manage-users.php', $f) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
