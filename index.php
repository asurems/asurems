<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';


$errors = [];


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'admin'
");

$adminExists =
    $stmt->fetchColumn() > 0;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($adminExists) {

        die('An administrator account already exists.');

    }


    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $errors[] =
            'Invalid form request. Please refresh and try again.';
    }


    $firstName =
        trim($_POST['first_name'] ?? '');

    $lastName =
        trim($_POST['last_name'] ?? '');

    $username =
        trim($_POST['username'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    if ($firstName === '') {

        $errors[] =
            'First name is required.';
    }


    if ($lastName === '') {

        $errors[] =
            'Last name is required.';
    }


    if ($username === '') {

        $errors[] =
            'Username is required.';
    }


    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errors[] =
            'Please enter a valid email address.';
    }


    if (strlen($password) < 8) {

        $errors[] =
            'Password must contain at least 8 characters.';
    }


    if ($password !== $confirmPassword) {

        $errors[] =
            'Passwords do not match.';
    }


    if (!$errors) {

        $check = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ");

        $check->execute([
            $username,
            $email
        ]);


        if ($check->fetch()) {

            $errors[] =
                'Username or email already exists.';

        } else {

            $hashedPassword =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    first_name,
                    last_name,
                    username,
                    email,
                    password,
                    role,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, 'admin', 'Active')
            ");


            $stmt->execute([
                $firstName,
                $lastName,
                $username,
                $email,
                $hashedPassword
            ]);


            setFlash(
                'success',
                'Administrator account created successfully. You may now log in.'
            );


            redirect(
                BASE_URL . '/login.php'
            );
        }
    }
}


$pageTitle =
    'Administrator Setup';

require __DIR__ . '/includes/header.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-6">

            <div class="card shadow">

                <div class="card-body p-4">

                    <h3 class="login-brand">

                        Initial Administrator Setup

                    </h3>

                    <p class="text-muted">

                        Create the first ASU-REMS administrator account.

                    </p>


                    <?php if ($adminExists): ?>

                        <div class="alert alert-warning">

                            Administrator account already exists.

                        </div>

                        <a
                            href="<?= BASE_URL; ?>/login.php"
                            class="btn btn-asu"
                        >

                            Go to Login

                        </a>

                    <?php else: ?>


                        <?php foreach ($errors as $error): ?>

                            <div class="alert alert-danger">

                                <?= e($error); ?>

                            </div>

                        <?php endforeach; ?>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(generateCsrfToken()); ?>"
                            >


                            <div class="mb-3">

                                <label class="form-label">

                                    First Name

                                </label>

                                <input
                                    type="text"
                                    name="first_name"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label class="form-label">

                                    Last Name

                                </label>

                                <input
                                    type="text"
                                    name="last_name"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label class="form-label">

                                    Username

                                </label>

                                <input
                                    type="text"
                                    name="username"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label class="form-label">

                                    Email

                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label class="form-label">

                                    Password

                                </label>

                                <input
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    minlength="8"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label class="form-label">

                                    Confirm Password

                                </label>

                                <input
                                    type="password"
                                    name="confirm_password"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-asu w-100"
                            >

                                Create Administrator

                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>