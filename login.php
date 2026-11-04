<?php
require_once __DIR__ . '/includes/auth.php';
$existing = current_user();
if ($existing) {
    redirect(home_path($existing['role']));
}
$error = '';
$email = '';
if (is_post()) {
    verify_csrf();
    $email = field($_POST, 'email');
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $result = authenticate($email, $password);
    if ($result[0]) {
        login_user($result[0]);
        $intended = isset($_SESSION['intended']) ? safe_return_path($_SESSION['intended'], '') : '';
        unset($_SESSION['intended']);
        redirect($intended !== '' ? $intended : home_path($result[0]['role']));
    }
    $error = $result[1];
}
$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="form-wrap">
  <h1>Welcome back</h1>
  <p>Log in to manage reports and claims.</p>
  <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('login.php')) ?>" class="panel">
    <?= csrf_field() ?>
    <label>Email address<input type="email" name="email" required autocomplete="username" maxlength="190" value="<?= e($email) ?>"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn primary" type="submit">Log in</button>
  </form>
  <p>New to CampusFind? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
