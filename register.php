<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| REDIRECT AUTHENTICATED USERS
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    redirect(
        BASE_URL . '/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| REGISTER PROCESS
|--------------------------------------------------------------------------
*/

$pageTitle = 'Sign Up';

$errors = [];

$form = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'role' => '',
    'college_id' => ''
];


$collegeStmt =
    $pdo->query("
        SELECT
            college_id,
            college_name
        FROM colleges
        WHERE status = 'Active'
        ORDER BY college_name
    ");

$colleges =
    $collegeStmt->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $errors[] =
            'Invalid request. Please refresh and try again.';
    }

    $form['first_name'] =
        trim($_POST['first_name'] ?? '');

    $form['middle_name'] =
        trim($_POST['middle_name'] ?? '');

    $form['last_name'] =
        trim($_POST['last_name'] ?? '');

    $form['username'] =
        trim($_POST['username'] ?? '');

    $form['email'] =
        trim($_POST['email'] ?? '');

    $form['role'] =
        trim($_POST['role'] ?? '');

    $form['college_id'] =
        trim($_POST['college_id'] ?? '');

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';

    $collegeId =
        $form['college_id'] !== ''
            ? (int)$form['college_id']
            : null;


    /*
    |--------------------------------------------------------------------------
    | Public sign up should not create admin accounts.
    |--------------------------------------------------------------------------
    */

    $validRoles = [
        'faculty',
        'extension_coordinator'
    ];


    if ($form['first_name'] === '') {
        $errors[] = 'First name is required.';
    }

    if ($form['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }

    if ($form['username'] === '') {
        $errors[] = 'Username is required.';
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9._-]{4,50}$/',
            $form['username']
        )
    ) {

        $errors[] =
            'Username must be 4 to 50 characters and may contain letters, numbers, dot, underscore, or dash only.';
    }

    if (
        !filter_var(
            $form['email'],
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errors[] =
            'Valid email is required.';
    }

    if (
        !in_array(
            $form['role'],
            $validRoles,
            true
        )
    ) {

        $errors[] =
            'Please select a valid account type.';
    }

    if (strlen($password) < 8) {

        $errors[] =
            'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {

        $errors[] =
            'Password confirmation does not match.';
    }


    if (!$errors) {

        $check =
            $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE username = ?
                   OR email = ?
                LIMIT 1
            ");

        $check->execute([
            $form['username'],
            $form['email']
        ]);

        if ($check->fetch()) {

            $errors[] =
                'Username or email already exists.';

        } else {

            $hash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $stmt =
                $pdo->prepare("
                    INSERT INTO users
                    (
                        first_name,
                        middle_name,
                        last_name,
                        username,
                        email,
                        password,
                        role,
                        college_id,
                        status
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                ");

            $stmt->execute([
                $form['first_name'],
                $form['middle_name'] !== ''
                    ? $form['middle_name']
                    : null,
                $form['last_name'],
                $form['username'],
                $form['email'],
                $hash,
                $form['role'],
                $collegeId
            ]);

            setFlash(
                'success',
                'Account created successfully. You may now log in.'
            );

            redirect(
                BASE_URL . '/login.php'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD HEADER
|--------------------------------------------------------------------------
*/

require __DIR__ . '/includes/header.php';

?>


<style>
:root {
    --asu-green-950: #022b13;
    --asu-green-900: #043b1c;
    --asu-green-800: #075a29;
    --asu-green-700: #087b37;
    --asu-green-600: #0a9242;
    --asu-yellow: #f4ca24;
    --asu-ink: #17201a;
    --asu-muted: #738078;
    --asu-border: #dfe7e1;
    --asu-soft: #f4f8f5;
    --asu-surface: #ffffff;
}

* {
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    min-height: 100%;
    margin: 0;
}

body {
    min-height: 100vh;
    overflow: hidden;
    background: #f7faf7;
    color: var(--asu-ink);
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
}

button,
input,
select {
    font: inherit;
}

/* =========================================================
   PAGE SHELL
========================================================= */

.auth-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    background:
        radial-gradient(circle at 82% 12%, rgba(8, 123, 55, .07), transparent 24%),
        #f8faf8;
}

.auth-shell {
    width: 100vw;
    height: 100vh;
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(420px, 44%) 1fr;
    overflow: hidden;
    background: var(--asu-surface);
}

/* =========================================================
   LEFT / BRAND PANEL
========================================================= */

.auth-left {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    background:
        radial-gradient(circle at 22% 18%, rgba(255,255,255,.12), transparent 28%),
        linear-gradient(145deg, var(--asu-green-950) 0%, var(--asu-green-800) 58%, var(--asu-green-700) 100%);
}

.auth-left::before {
    content: "";
    position: absolute;
    z-index: -3;
    inset: -18% -18% auto -35%;
    height: 46%;
    transform: rotate(-39deg);
    border-radius: 48px;
    background: linear-gradient(90deg, rgba(255,255,255,.04), rgba(255,255,255,.16));
    box-shadow: 0 36px 80px rgba(0,0,0,.14);
}

.auth-left::after {
    content: "";
    position: absolute;
    z-index: -4;
    width: 560px;
    height: 560px;
    right: -330px;
    bottom: -245px;
    border: 82px solid rgba(255,255,255,.045);
    border-radius: 50%;
}

.auth-ribbon {
    position: absolute;
    z-index: -2;
    height: 122px;
    border-radius: 28px;
    transform: rotate(-39deg);
    pointer-events: none;
}

.auth-ribbon-one {
    width: 680px;
    left: -245px;
    top: 36%;
    background: linear-gradient(90deg, rgba(244,202,36,.20), rgba(255,255,255,.11));
}

.auth-ribbon-two {
    width: 620px;
    left: -95px;
    top: 53%;
    background: linear-gradient(90deg, rgba(255,255,255,.07), rgba(244,202,36,.10));
}

.auth-ribbon-accent {
    position: absolute;
    z-index: -1;
    width: 5px;
    height: 180px;
    left: 72px;
    top: 45%;
    transform: rotate(-39deg);
    border-radius: 999px;
    background: linear-gradient(180deg, transparent, var(--asu-yellow), transparent);
    opacity: .9;
}

.auth-left-content {
    position: relative;
    z-index: 4;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: clamp(38px, 5.2vw, 74px) clamp(42px, 5.4vw, 84px);
}

.brand-row {
    display: flex;
    align-items: center;
    gap: 15px;
}

.brand-mark {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    display: grid;
    place-items: center;
    border-radius: 18px;
    background: var(--asu-yellow);
    color: var(--asu-green-950);
    font-size: 27px;
    box-shadow: 0 16px 34px rgba(0,0,0,.18);
}

.brand-copy strong {
    display: block;
    color: #fff;
    font-size: 18px;
    font-weight: 850;
    letter-spacing: -.015em;
}

.brand-copy span {
    display: block;
    margin-top: 4px;
    color: rgba(255,255,255,.66);
    font-size: 11px;
}

.brand-hero {
    width: min(490px, 88%);
    margin-top: auto;
    margin-bottom: auto;
}

.brand-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid rgba(255,255,255,.13);
    border-radius: 999px;
    background: rgba(255,255,255,.07);
    color: #f8df67;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    backdrop-filter: blur(8px);
}

.brand-hero h1 {
    margin: 20px 0 0;
    color: #fff;
    font-size: clamp(38px, 4.2vw, 62px);
    line-height: .98;
    letter-spacing: -.055em;
    font-weight: 900;
}

.brand-hero h1 span {
    display: block;
    color: var(--asu-yellow);
}

.brand-hero p {
    max-width: 440px;
    margin: 20px 0 0;
    color: rgba(255,255,255,.70);
    font-size: 13px;
    line-height: 1.75;
}

.brand-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255,255,255,.46);
    font-size: 10px;
}

.brand-footer i {
    color: rgba(244,202,36,.9);
}

/* =========================================================
   INSPIRED SIDE SWITCH
========================================================= */

.auth-switch {
    position: absolute;
    z-index: 10;
    top: 50%;
    right: 0;
    width: 145px;
    transform: translateY(-50%);
    display: grid;
    gap: 16px;
    justify-items: end;
}

.auth-switch-link {
    position: relative;
    min-width: 118px;
    height: 54px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: rgba(255,255,255,.75);
    text-decoration: none;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .08em;
    text-transform: uppercase;
    transition: transform .2s ease, background .2s ease, color .2s ease, box-shadow .2s ease;
}

.auth-switch-link:hover {
    transform: translateX(-4px);
    color: #fff;
    background: rgba(255,255,255,.07);
}

.auth-switch-link.active {
    min-width: 132px;
    margin-right: -1px;
    border-radius: 999px 0 0 999px;
    background: #fff;
    color: var(--asu-green-900);
    box-shadow: -18px 18px 42px rgba(0,0,0,.16);
}

.auth-switch-link.active::before,
.auth-switch-link.active::after {
    content: "";
    position: absolute;
    right: 0;
    width: 26px;
    height: 26px;
    background: transparent;
}

.auth-switch-link.active::before {
    top: -26px;
    border-bottom-right-radius: 26px;
    box-shadow: 10px 10px 0 #fff;
}

.auth-switch-link.active::after {
    bottom: -26px;
    border-top-right-radius: 26px;
    box-shadow: 10px -10px 0 #fff;
}

/* =========================================================
   RIGHT / FORM PANEL
========================================================= */

.auth-right {
    position: relative;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 44px clamp(38px, 6vw, 92px);
    background:
        radial-gradient(circle at 88% 8%, rgba(8,123,55,.055), transparent 24%),
        linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
}

.auth-right::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 1px;
    height: 100%;
    background: linear-gradient(180deg, transparent, rgba(6,80,34,.12), transparent);
}

.auth-form-wrap {
    width: 100%;
    max-width: 470px;
    margin: auto;
}

.form-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 26px;
}

.form-icon {
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    border-radius: 18px;
    background: linear-gradient(145deg, var(--asu-green-700), var(--asu-green-900));
    color: var(--asu-yellow);
    font-size: 25px;
    box-shadow: 0 15px 32px rgba(8,123,55,.18);
}

.form-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 11px;
    border: 1px solid #dfe9e2;
    border-radius: 999px;
    background: rgba(248,251,249,.9);
    color: #69756d;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.form-status i {
    color: var(--asu-green-700);
}

.form-heading {
    margin-bottom: 26px;
}

.form-heading .eyebrow {
    margin: 0 0 8px;
    color: var(--asu-green-700);
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.form-heading h2 {
    margin: 0;
    color: var(--asu-ink);
    font-size: clamp(28px, 3vw, 38px);
    line-height: 1.08;
    letter-spacing: -.04em;
    font-weight: 900;
}

.form-heading p {
    margin: 10px 0 0;
    color: var(--asu-muted);
    font-size: 12px;
    line-height: 1.65;
}

.login-alert {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin: 0 0 10px;
    padding: 10px 12px;
    border-radius: 12px;
    font-size: 10px;
    line-height: 1.45;
}

.login-alert-danger {
    border: 1px solid #f0caca;
    background: #fff7f7;
    color: #8e3b3b;
}

.login-alert-success {
    border: 1px solid #cae6d1;
    background: #f3fbf5;
    color: #2d7141;
}

.field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.field-group {
    margin-bottom: 15px;
}

.field-label {
    display: block;
    margin: 0 0 7px 2px;
    color: #344039;
    font-size: 10px;
    font-weight: 800;
}

.field-control {
    position: relative;
}

.field-control > i {
    position: absolute;
    z-index: 2;
    top: 50%;
    left: 16px;
    transform: translateY(-50%);
    color: #8b9790;
    font-size: 15px;
    pointer-events: none;
    transition: color .18s ease;
}

.field-control input,
.field-control select {
    width: 100%;
    height: 52px;
    padding: 0 44px 0 44px;
    border: 1px solid var(--asu-border);
    border-radius: 15px;
    outline: none;
    background: #fff;
    color: #1d2720;
    font-size: 12px;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease, transform .18s ease;
}

.field-control select {
    padding-right: 34px;
}

.field-control input::placeholder {
    color: #a1aaa4;
}

.field-control:focus-within > i {
    color: var(--asu-green-700);
}

.field-control input:focus,
.field-control select:focus {
    border-color: rgba(8,123,55,.55);
    background: #fcfffd;
    box-shadow: 0 0 0 4px rgba(8,123,55,.09), 0 12px 30px rgba(6,78,32,.06);
}

.password-toggle {
    position: absolute;
    z-index: 3;
    top: 50%;
    right: 10px;
    width: 34px;
    height: 34px;
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: #86928a;
    cursor: pointer;
    transition: background .18s ease, color .18s ease;
}

.password-toggle:hover {
    background: var(--asu-soft);
    color: var(--asu-green-700);
}

.form-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    margin: -1px 2px 18px;
}

.forgot-link,
.form-meta-note {
    color: #7b867f;
    font-size: 10px;
}

.forgot-link {
    cursor: help;
}

.submit-btn {
    width: 100%;
    min-height: 52px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border: 0;
    border-radius: 15px;
    background: linear-gradient(135deg, var(--asu-green-700), var(--asu-green-900));
    color: #fff;
    font-size: 12px;
    font-weight: 850;
    letter-spacing: .02em;
    cursor: pointer;
    box-shadow: 0 14px 28px rgba(8,123,55,.17);
    transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
}

.submit-btn:hover {
    transform: translateY(-2px);
    filter: saturate(1.08);
    box-shadow: 0 18px 36px rgba(8,123,55,.23);
}

.submit-btn:active {
    transform: translateY(0);
}

.submit-btn i {
    font-size: 15px;
}

.form-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0 16px;
    color: #98a29c;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: .08em;
}

.form-divider::before,
.form-divider::after {
    content: "";
    height: 1px;
    flex: 1;
    background: #e8eee9;
}

.alt-action {
    width: 100%;
    min-height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid #dfe7e1;
    border-radius: 15px;
    background: #fbfdfb;
    color: var(--asu-green-800);
    text-decoration: none;
    font-size: 11px;
    font-weight: 820;
    transition: border-color .18s ease, background .18s ease, transform .18s ease;
}

.alt-action:hover {
    transform: translateY(-1px);
    border-color: #bfd2c4;
    background: #f3f8f4;
}

.security-note {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 19px;
    padding: 13px 0 0;
    border-top: 1px solid #e9eeea;
    color: #8a948e;
    font-size: 9px;
    line-height: 1.5;
}

.security-note i {
    margin-top: 1px;
    color: var(--asu-green-700);
}

.form-footer {
    margin-top: 15px;
    text-align: center;
    color: #a0a9a3;
    font-size: 9px;
}

/* compact register variant */
.auth-form-wrap.register-mode {
    max-width: 620px;
}

.register-mode .form-topline {
    margin-bottom: 18px;
}

.register-mode .form-heading {
    margin-bottom: 18px;
}

.register-mode .form-heading h2 {
    font-size: clamp(27px, 2.6vw, 35px);
}

.register-mode .field-group {
    margin-bottom: 11px;
}

.register-mode .field-control input,
.register-mode .field-control select {
    height: 48px;
}

.register-mode .field-label {
    margin-bottom: 5px;
}

.register-mode .submit-btn {
    min-height: 49px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1080px) {
    .auth-shell {
        grid-template-columns: minmax(360px, 42%) 1fr;
    }

    .auth-left-content {
        padding-left: 50px;
        padding-right: 50px;
    }

    .auth-switch {
        width: 130px;
    }
}

@media (max-width: 860px) {
    body {
        overflow: auto;
    }

    .auth-page,
    .auth-shell {
        height: auto;
        min-height: 100vh;
    }

    .auth-shell {
        grid-template-columns: 1fr;
    }

    .auth-left {
        min-height: 220px;
    }

    .auth-left-content {
        min-height: 220px;
        padding: 30px 34px;
    }

    .brand-hero,
    .brand-footer {
        display: none;
    }

    .auth-switch {
        top: auto;
        right: 28px;
        bottom: 22px;
        width: auto;
        transform: none;
        display: flex;
        gap: 8px;
    }

    .auth-switch-link,
    .auth-switch-link.active {
        min-width: 96px;
        height: 40px;
        margin: 0;
        border-radius: 999px;
    }

    .auth-switch-link.active::before,
    .auth-switch-link.active::after {
        display: none;
    }

    .auth-right {
        min-height: calc(100vh - 220px);
        padding: 38px 26px 48px;
    }

    .auth-right::before {
        display: none;
    }
}

@media (max-width: 640px) {
    .auth-left {
        min-height: 175px;
    }

    .auth-left-content {
        min-height: 175px;
        padding: 24px 22px;
    }

    .brand-mark {
        width: 50px;
        height: 50px;
        flex-basis: 50px;
        border-radius: 15px;
        font-size: 23px;
    }

    .brand-copy strong {
        font-size: 16px;
    }

    .auth-switch {
        right: 20px;
        bottom: 18px;
    }

    .auth-switch-link,
    .auth-switch-link.active {
        min-width: 84px;
        height: 36px;
        font-size: 9px;
    }

    .auth-right {
        min-height: calc(100vh - 175px);
        padding: 30px 18px 40px;
    }

    .form-topline {
        margin-bottom: 20px;
    }

    .form-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        font-size: 23px;
    }

    .field-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .register-mode .field-group {
        margin-bottom: 12px;
    }
}
</style>

<div class="auth-page">
    <main class="auth-shell">
        <section class="auth-left" aria-label="ASU-REMS branding">
            <div class="auth-ribbon auth-ribbon-one"></div>
            <div class="auth-ribbon auth-ribbon-two"></div>
            <div class="auth-ribbon-accent"></div>

            <nav class="auth-switch" aria-label="Authentication pages">
                <a href="<?= BASE_URL; ?>/login.php" class="auth-switch-link">Log In</a>
                <a href="<?= BASE_URL; ?>/register.php" class="auth-switch-link active" aria-current="page">Sign Up</a>
            </nav>

            <div class="auth-left-content">
                <div class="brand-row">
                    <div class="brand-mark" aria-hidden="true">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <div class="brand-copy">
                        <strong>ASU-REMS</strong>
                        <span>Aklan State University</span>
                    </div>
                </div>

                <div class="brand-hero">
                    <div class="brand-kicker">
                        <i class="bi bi-shield-check"></i>
                        Institutional Management System
                    </div>

                    <h1>
                        Research &amp;
                        <span>Extension</span>
                        Management
                    </h1>

                    <p>
                        Create an authorized account for secure participation in university
                        research and extension workflows.
                    </p>
                </div>

                <div class="brand-footer">
                    <i class="bi bi-stars"></i>
                    <span>Professional. Secure. Built for institutional workflows.</span>
                </div>
            </div>
        </section>

        <section class="auth-right">
            <div class="auth-form-wrap register-mode">
                <div class="form-topline">
                    <div class="form-icon" aria-hidden="true">
                        <i class="bi bi-person-plus"></i>
                    </div>

                    <div class="form-status">
                        <i class="bi bi-patch-check"></i>
                        Authorized registration
                    </div>
                </div>

                <div class="form-heading">
                    <p class="eyebrow">Create account</p>
                    <h2>Join ASU-REMS.</h2>
                    <p>Enter your university details to create your authorized account.</p>
                </div>

                <?php if ($errors): ?>
                    <div class="login-alert login-alert-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>Please check the form and try again.</span>
                    </div>

                    <?php foreach ($errors as $error): ?>
                        <div class="login-alert login-alert-danger">
                            <i class="bi bi-dot"></i>
                            <span><?= e($error); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>">

                    <div class="field-grid">
                        <div class="field-group">
                            <label class="field-label">First Name</label>
                            <div class="field-control">
                                <i class="bi bi-person"></i>
                                <input type="text" name="first_name" placeholder="First name" value="<?= e($form['first_name']); ?>" required>
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Last Name</label>
                            <div class="field-control">
                                <i class="bi bi-person"></i>
                                <input type="text" name="last_name" placeholder="Last name" value="<?= e($form['last_name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field-group">
                            <label class="field-label">Middle Name</label>
                            <div class="field-control">
                                <i class="bi bi-person-lines-fill"></i>
                                <input type="text" name="middle_name" placeholder="Optional" value="<?= e($form['middle_name']); ?>">
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Username</label>
                            <div class="field-control">
                                <i class="bi bi-at"></i>
                                <input type="text" name="username" placeholder="Choose a username" value="<?= e($form['username']); ?>" minlength="4" maxlength="50" required>
                            </div>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label">Email Address</label>
                        <div class="field-control">
                            <i class="bi bi-envelope"></i>
                            <input type="email" name="email" placeholder="University email address" value="<?= e($form['email']); ?>" required>
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field-group">
                            <label class="field-label">Account Type</label>
                            <div class="field-control">
                                <i class="bi bi-person-badge"></i>
                                <select name="role" required>
                                    <option value="">Select account type</option>
                                    <option value="faculty" <?= $form['role'] === 'faculty' ? 'selected' : ''; ?>>Faculty / Researcher</option>
                                    <option value="extension_coordinator" <?= $form['role'] === 'extension_coordinator' ? 'selected' : ''; ?>>Extension Coordinator</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-label">College</label>
                            <div class="field-control">
                                <i class="bi bi-building"></i>
                                <select name="college_id">
                                    <option value="">Select college</option>
                                    <?php foreach ($colleges as $college): ?>
                                        <option
                                            value="<?= (int)$college['college_id']; ?>"
                                            <?= (string)$form['college_id'] === (string)$college['college_id'] ? 'selected' : ''; ?>
                                        >
                                            <?= e($college['college_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field-group">
                            <label class="field-label" for="password">Password</label>
                            <div class="field-control">
                                <i class="bi bi-lock"></i>
                                <input type="password" id="password" name="password" placeholder="At least 8 characters" minlength="8" required>
                                <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" title="Show password">
                                    <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-label" for="confirm_password">Confirm Password</label>
                            <div class="field-control">
                                <i class="bi bi-lock-fill"></i>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" minlength="8" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <span>Create Account</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>

                    <div class="security-note">
                        <i class="bi bi-shield-check"></i>
                        <span>Public sign up is limited to approved non-administrator roles.</span>
                    </div>
                </form>

                <div class="form-footer">Research &amp; Extension Management System</div>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('password');
    const toggle = document.getElementById('passwordToggle');
    const icon = document.getElementById('passwordToggleIcon');

    if (!password || !toggle || !icon) return;

    toggle.addEventListener('click', function () {
        const isHidden = password.type === 'password';
        password.type = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        toggle.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
    });
});
</script>

<?php

require __DIR__ . '/includes/footer.php';

?>
