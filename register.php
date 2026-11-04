<?php
require_once __DIR__ . '/includes/auth.php';
$existing = current_user();
if ($existing) {
    redirect(home_path($existing['role']));
}
$errors = array();
$name = '';
$email = '';
if (is_post()) {
    verify_csrf();
    $name = field($_POST, 'full_name');
    $email = strtolower(field($_POST, 'email'));
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $confirm = (isset($_POST['password_confirm']) && is_string($_POST['password_confirm'])) ? $_POST['password_confirm'] : '';
    $errors = validate_person_name($name);
    if (!valid_email($email)) {
        $errors[] = 'Enter a valid email address.';
    }
    $errors = array_merge($errors, validate_password($password, $confirm));
    if (count($errors) === 0) {
        // The role is fixed here on purpose: public registration can only ever create students.
        $result = UserService::insert($name, $email, $password, 'student');
        if ($result['ok']) {
            flash('success', 'Account created. You can now log in.');
            redirect('login.php');
        }
        $errors = $result['errors'];
    }
}
$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="form-wrap">
  <h1>Create your account</h1>
  <p>Public registration creates a student account only.</p>
  <?php foreach ($errors as $error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" action="<?= e(url('register.php')) ?>" class="panel">
    <?= csrf_field() ?>
    <label>Full name<input name="full_name" maxlength="100" required autocomplete="name" value="<?= e($name) ?>"></label>
    <label>Email address<input type="email" name="email" maxlength="190" required autocomplete="username" value="<?= e($email) ?>"></label>
    <label>Password (at least 8 characters)<input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"></label>
    <label>Confirm password<input type="password" name="password_confirm" minlength="8" maxlength="72" required autocomplete="new-password"></label>
    <button class="btn primary" type="submit">Register</button>
  </form>
  <p>Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
