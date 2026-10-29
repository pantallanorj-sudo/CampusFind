
<?php

// 1. Load authentication helpers, session, and database setup.
require_once __DIR__ . '/includes/auth.php';

// 2. If the user is already logged in, redirect to their dashboard.
$user = current_user();

if ($user) {
    redirect($user['role'] . '/dashboard.php');
}

// 3. Initialize the error message.
$error = '';

// 4. Process the form only when it is submitted using POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 5. Verify the CSRF token to help prevent forged requests.
    verify_csrf();

    // 6. Get the submitted email and password.
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    // 7. Find the user account by email using a prepared statement.
    $sql = '
        SELECT
            id,
            full_name,
            email,
            password_hash,
            role,
            is_active
        FROM users
        WHERE email = ?
        LIMIT 1
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);

    // 8. Retrieve the matching user, if one exists.
    $user = $stmt->fetch();

    // 9. Verify the account status and submitted password.
    if (
        $user &&
        (int) $user['is_active'] === 1 &&
        password_verify($password, $user['password_hash'])
    ) {

        // 10. Regenerate the session ID after successful login.
        session_regenerate_id(true);

        // 11. Store the required user details in the session.
        $_SESSION['user'] = [
            'id'        => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ];

        // 12. Redirect the user according to their assigned role.
        redirect($user['role'] . '/dashboard.php');

    } else {

        // 13. Display a generic error message.
        $error = 'Incorrect email or password, or the account is inactive.';
    }
}

// 14. Set the page title and load the shared header.
$pageTitle = 'Log in';

require __DIR__ . '/includes/header.php';

?>

<!-- 15. Display the login form. -->
<section class="form-wrap">

    <h1>Welcome back</h1>

    <p>Log in to manage reports and claims.</p>

    <!-- 16. Display an error if login fails. -->
    <?php if ($error !== ''): ?>
        <div class="alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- 17. Submit the login form using POST. -->
    <form method="post" class="panel">

        <!-- CSRF protection token -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <!-- Email field -->
        <label for="email">Email address</label>
        <input
            type="email"
            id="email"
            name="email"
            required
            autocomplete="username"
            value="<?= e($email ?? '') ?>"
        >

        <!-- Password field -->
        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            autocomplete="current-password"
        >

        <!-- Submit button -->
        <button class="btn primary" type="submit">
            Log in
        </button>

    </form>

    <!-- Registration link -->
    <p>
        New to CampusFind?
        <a href="<?= e(url('register.php')) ?>">Create an account</a>
    </p>

</section>

<?php

// 18. Load the shared footer.
require __DIR__ . '/includes/footer.php';

?>
