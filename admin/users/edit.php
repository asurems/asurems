<?php

require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');

$user = currentUser();

$pageTitle = 'Edit User';
$activePage = 'users';

$errors = [];

/*
|--------------------------------------------------------------------------
| CURRENT ADMIN INITIALS
|--------------------------------------------------------------------------
*/

$initials = '';

if (!empty($user['first_name'])) {
    $initials .= strtoupper(substr($user['first_name'], 0, 1));
}

if (!empty($user['last_name'])) {
    $initials .= strtoupper(substr($user['last_name'], 0, 1));
}

/*
|--------------------------------------------------------------------------
| USER ID FROM EDIT BUTTON
|--------------------------------------------------------------------------
|
| User Management already sends:
| edit.php?id=<user_id>
|
*/

$editUserId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$editUserId) {
    setFlash('error', 'Invalid user account.');
    redirect(BASE_URL . '/admin/users/index.php');
}

/*
|--------------------------------------------------------------------------
| ACTIVE COLLEGES
|--------------------------------------------------------------------------
*/

$collegeStmt = $pdo->query("
    SELECT college_id, college_name
    FROM colleges
    WHERE status = 'Active'
    ORDER BY college_name
");

$colleges = $collegeStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| LOAD CURRENT USER INFORMATION
|--------------------------------------------------------------------------
|
| This makes the edit form automatically show the most recently saved
| information for the selected account.
|
*/

$loadStmt = $pdo->prepare("
    SELECT
        user_id,
        first_name,
        middle_name,
        last_name,
        username,
        email,
        role,
        college_id,
        status
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$loadStmt->execute([$editUserId]);

$account = $loadStmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    setFlash('error', 'User account not found.');
    redirect(BASE_URL . '/admin/users/index.php');
}

$form = [
    'first_name' => $account['first_name'] ?? '',
    'middle_name' => $account['middle_name'] ?? '',
    'last_name' => $account['last_name'] ?? '',
    'username' => $account['username'] ?? '',
    'email' => $account['email'] ?? '',
    'role' => $account['role'] ?? '',
    'college_id' => $account['college_id'] ?? ''
];

/*
|--------------------------------------------------------------------------
| SAVE CHANGES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    }

    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['middle_name'] = trim($_POST['middle_name'] ?? '');
    $form['last_name'] = trim($_POST['last_name'] ?? '');
    $form['username'] = trim($_POST['username'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['role'] = trim($_POST['role'] ?? '');
    $form['college_id'] = trim($_POST['college_id'] ?? '');

    $newPassword = $_POST['password'] ?? '';

    $validRoles = [
        'admin',
        'faculty',
        'rps',
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

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if (!in_array($form['role'], $validRoles, true)) {
        $errors[] = 'Invalid user role.';
    }

    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | COLLEGE RULE
    |--------------------------------------------------------------------------
    |
    | Administrator and RPS accounts do not require a college.
    |
    */

    if (
        $form['role'] === 'admin'
        ||
        $form['role'] === 'rps'
    ) {
        $collegeId = null;
        $form['college_id'] = '';
    } else {
        $collegeId =
            $form['college_id'] !== ''
                ? (int)$form['college_id']
                : null;
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE CHECK
    |--------------------------------------------------------------------------
    |
    | Exclude the account currently being edited.
    |
    */

    if (!$errors) {

        $check = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE
                (
                    username = ?
                    OR email = ?
                )
                AND user_id <> ?
            LIMIT 1
        ");

        $check->execute([
            $form['username'],
            $form['email'],
            $editUserId
        ]);

        if ($check->fetch()) {
            $errors[] = 'Username or email already belongs to another account.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE USER
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        if ($newPassword !== '') {

            $hash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $updateStmt = $pdo->prepare("
                UPDATE users
                SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    username = ?,
                    email = ?,
                    password = ?,
                    role = ?,
                    college_id = ?
                WHERE user_id = ?
            ");

            $updateStmt->execute([
                $form['first_name'],
                $form['middle_name'] !== ''
                    ? $form['middle_name']
                    : null,
                $form['last_name'],
                $form['username'],
                $form['email'],
                $hash,
                $form['role'],
                $collegeId,
                $editUserId
            ]);

        } else {

            $updateStmt = $pdo->prepare("
                UPDATE users
                SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    username = ?,
                    email = ?,
                    role = ?,
                    college_id = ?
                WHERE user_id = ?
            ");

            $updateStmt->execute([
                $form['first_name'],
                $form['middle_name'] !== ''
                    ? $form['middle_name']
                    : null,
                $form['last_name'],
                $form['username'],
                $form['email'],
                $form['role'],
                $collegeId,
                $editUserId
            ]);
        }

        setFlash(
            'success',
            'User account updated successfully.'
        );

        redirect(BASE_URL . '/admin/users/index.php');
    }
}

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/sidebar.php';

?>

<style>
.user-form-page {
    max-width: 1100px;
    margin: 0 auto;
}

.user-form-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 22px;
}

.user-form-heading h1 {
    margin: 0 0 5px;
    color: #1f2a22;
    font-size: 26px;
    font-weight: 800;
}

.user-form-heading p {
    margin: 0;
    color: var(--asu-muted);
    font-size: 12px;
}

.user-form-card {
    overflow: hidden;
    border: 1px solid var(--asu-border);
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(31, 74, 39, .06);
}

.user-form-card-header {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 20px 24px;
    border-bottom: 1px solid #edf1ed;
    background: linear-gradient(180deg, #ffffff, #fbfdfb);
}

.user-form-card-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #edf7ef;
    color: var(--asu-green);
    font-size: 19px;
}

.user-form-card-header h2 {
    margin: 0 0 3px;
    color: #263028;
    font-size: 16px;
    font-weight: 800;
}

.user-form-card-header span {
    color: #7b857e;
    font-size: 10px;
}

.user-form-card-body {
    padding: 24px;
}

.user-form-section {
    margin-bottom: 24px;
}

.user-form-section:last-child {
    margin-bottom: 0;
}

.user-form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    color: #263028;
    font-size: 12px;
    font-weight: 800;
}

.user-form-section-title i {
    color: var(--asu-green);
}

.user-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px 18px;
}

.user-form-grid.two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.user-form-card .form-label {
    margin-bottom: 6px;
    color: #37433a;
    font-size: 11px;
    font-weight: 700;
}

.user-form-card .form-control,
.user-form-card .form-select {
    min-height: 48px;
    border-color: #dfe8e1;
    border-radius: 12px;
    color: #2c352e;
    font-size: 12px;
}

.user-form-card .form-control:focus,
.user-form-card .form-select:focus {
    border-color: var(--asu-green);
    box-shadow: 0 0 0 .18rem rgba(8, 117, 44, .12);
}

.user-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 26px;
    padding-top: 20px;
    border-top: 1px solid #edf1ed;
}

.user-form-actions .btn {
    min-width: 135px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.user-form-actions .btn-cancel-clean {
    border: 1px solid #cfd8d1;
    background: #ffffff;
    color: #59635c;
}

.user-form-actions .btn-cancel-clean:hover {
    border-color: #08752c;
    background: #f2f8f3;
    color: #08752c;
}

.user-error-box {
    margin-bottom: 18px;
    padding: 14px 17px;
    border: 1px solid #edcccc;
    border-radius: 13px;
    background: #fff6f6;
    color: #873b3b;
    font-size: 11px;
}

.password-note {
    margin-top: 7px;
    color: #7e8981;
    font-size: 9px;
}

@media (max-width: 991px) {
    .user-form-grid,
    .user-form-grid.two {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .user-form-heading {
        flex-direction: column;
    }

    .user-form-card-body {
        padding: 18px;
    }

    .user-form-actions {
        flex-direction: column-reverse;
    }

    .user-form-actions .btn {
        width: 100%;
    }
}
</style>

<div class="main-page">

    <header class="asu-topbar">

        <div class="topbar-left">

            <button
                id="sidebarToggle"
                type="button"
                class="sidebar-toggle"
                aria-label="Open navigation"
            >
                <i class="bi bi-list"></i>
            </button>

            <div class="system-brand-copy">
                <strong>Research &amp; Extension Management System</strong>
                <span>Aklan State University</span>
            </div>

        </div>

        <div class="topbar-user">

            <div class="topbar-user-text">
                <strong>
                    <?= e(fullName($user)); ?>
                </strong>
                <span>
                    <?= e(roleLabel($user['role'])); ?>
                </span>
            </div>

            <div class="user-avatar">
                <?= e($initials); ?>
            </div>

        </div>

    </header>

    <main class="page-content">

        <div class="user-form-page">

            <div class="user-form-heading">

                <div>
                    <h1>
                        Edit User
                    </h1>
                    <p>Review and update the current account information.</p>
                </div>

                <a
                    href="<?= BASE_URL; ?>/admin/users/index.php"
                    class="btn btn-outline-asu"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Users
                </a>

            </div>

            <?php if ($errors): ?>

                <div class="user-error-box">

                    <strong>
                        Unable to save changes.
                    </strong>

                    <ul class="mt-2 mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li>
                                <?= e($error); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>

            <section class="user-form-card">

                <div class="user-form-card-header">

                    <div class="user-form-card-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>

                    <div>
                        <h2>
                            Current Account Information
                        </h2>
                    </div>

                </div>

                <div class="user-form-card-body">

                    <form method="POST" autocomplete="off">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(generateCsrfToken()); ?>"
                        >

                        <div class="user-form-section">

                            <div class="user-form-section-title">
                                <i class="bi bi-person"></i>
                                Personal Details
                            </div>

                            <div class="user-form-grid">

                                <div>
                                    <label class="form-label">
                                        First Name
                                    </label>
                                    <input
                                        type="text"
                                        name="first_name"
                                        class="form-control"
                                        value="<?= e($form['first_name']); ?>"
                                        required
                                    >
                                </div>

                                <div>
                                    <label class="form-label">
                                        Middle Name
                                    </label>
                                    <input
                                        type="text"
                                        name="middle_name"
                                        class="form-control"
                                        value="<?= e($form['middle_name']); ?>"
                                    >
                                </div>

                                <div>
                                    <label class="form-label">
                                        Last Name
                                    </label>
                                    <input
                                        type="text"
                                        name="last_name"
                                        class="form-control"
                                        value="<?= e($form['last_name']); ?>"
                                        required
                                    >
                                </div>

                            </div>

                        </div>

                        <div class="user-form-section">

                            <div class="user-form-section-title">
                                <i class="bi bi-key"></i>
                                Login Credentials
                            </div>

                            <div class="user-form-grid">

                                <div>
                                    <label class="form-label">
                                        Username
                                    </label>
                                    <input
                                        type="text"
                                        name="username"
                                        class="form-control"
                                        value="<?= e($form['username']); ?>"
                                        required
                                    >
                                </div>

                                <div>
                                    <label class="form-label">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        name="email"
                                        class="form-control"
                                        value="<?= e($form['email']); ?>"
                                        required
                                    >
                                </div>

                                <div>
                                    <label class="form-label">
                                        New Password
                                    </label>
                                    <input
                                        type="password"
                                        name="password"
                                        class="form-control"
                                        minlength="8"
                                        placeholder="Leave blank to keep current password"
                                    >

                                </div>

                            </div>

                        </div>

                        <div class="user-form-section">

                            <div class="user-form-section-title">
                                <i class="bi bi-shield-check"></i>
                                Role Assignment
                            </div>

                            <div class="user-form-grid two">

                                <div>
                                    <label class="form-label">
                                        Role
                                    </label>
                                    <select
                                        name="role"
                                        class="form-select"
                                        required
                                    >
                                        <option value="">
                                            Select Role
                                        </option>
                                        <option
                                            value="admin"
                                            <?= $form['role'] === 'admin' ? 'selected' : ''; ?>
                                        >
                                            Administrator
                                        </option>
                                        <option
                                            value="faculty"
                                            <?= $form['role'] === 'faculty' ? 'selected' : ''; ?>
                                        >
                                            Faculty / Researcher
                                        </option>
                                        <option
                                            value="rps"
                                            <?= $form['role'] === 'rps' ? 'selected' : ''; ?>
                                        >
                                            Research Processing Staff (RPS)
                                        </option>
                                        <option
                                            value="extension_coordinator"
                                            <?= $form['role'] === 'extension_coordinator' ? 'selected' : ''; ?>
                                        >
                                            Extension Coordinator
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">
                                        College
                                    </label>
                                    <select
                                        name="college_id"
                                        class="form-select"
                                    >
                                        <option value="">
                                            Not Assigned
                                        </option>

                                        <?php foreach ($colleges as $college): ?>

                                            <option
                                                value="<?= (int)$college['college_id']; ?>"
                                                <?= (string)$form['college_id'] === (string)$college['college_id']
                                                    ? 'selected'
                                                    : ''; ?>
                                            >
                                                <?= e($college['college_name']); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>
                                </div>

                            </div>

                        </div>

                        <div class="user-form-actions">

                            <a
                                href="<?= BASE_URL; ?>/admin/users/index.php"
                                class="btn btn-cancel-clean"
                            >
                                <i class="bi bi-x-circle"></i>
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-asu"
                            >
                                <i class="bi bi-check2-circle"></i>
                                Save Changes
                            </button>

                        </div>

                    </form>

                </div>

            </section>

        </div>
</main>

</div>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const role = document.querySelector('select[name="role"]');
    const college = document.querySelector('select[name="college_id"]');

    if (!role || !college) return;

    function syncCollegeField() {
        const noCollege =
            role.value === 'admin'
            || role.value === 'rps';

        college.disabled = noCollege;

        if (noCollege) {
            college.value = '';
        }
    }

    role.addEventListener('change', syncCollegeField);
    syncCollegeField();
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>


<style>
/* =========================================================
   ADD USER — FINAL UNIFORM ADMIN DESIGN
========================================================= */

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a {
    font-family: "Century Gothic", sans-serif !important;
}

/* Background */
.main-page {
    background:
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

.page-content {
    min-height: calc(100vh - 76px);
    padding: 24px 28px 30px !important;
    background:
        radial-gradient(circle at 90% 5%, rgba(255,213,31,.10), transparent 25%),
        radial-gradient(circle at 8% 7%, rgba(0,122,51,.07), transparent 27%),
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

/* =========================================================
   TOPBAR
========================================================= */

.asu-topbar {
    min-height: 76px !important;
    padding: 0 30px !important;
    background: rgba(255,255,255,.97) !important;
    border-bottom: 1px solid rgba(0,122,51,.12) !important;
    box-shadow: 0 10px 26px rgba(16,24,16,.035) !important;
}

.topbar-left {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
}

.system-name {
    display: none !important;
}

.system-brand-copy {
    position: relative;
    padding-left: 18px;
    line-height: 1.15;
}

.system-brand-copy::before {
    content: "";
    position: absolute;
    left: 0;
    top: 50%;
    width: 3px;
    height: 38px;
    transform: translateY(-50%);
    border-radius: 999px;
    background: #007a33;
}

.system-brand-copy strong {
    display: block;
    color: #101810;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -.015em;
}

.system-brand-copy span {
    display: block;
    margin-top: 4px;
    color: #657068;
    font-size: 13px;
}

.topbar-user-text strong {
    font-size: 15px !important;
}

.topbar-user-text span {
    margin-top: 4px !important;
    font-size: 12px !important;
}

.asu-topbar .user-avatar {
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;
}

/* =========================================================
   PAGE WIDTH + HEADING
========================================================= */

.user-form-page {
    max-width: 1180px !important;
    margin: 0 auto !important;
}

.user-form-heading {
    align-items: center !important;
    margin-bottom: 18px !important;
}

.user-form-heading h1 {
    margin: 0 0 3px !important;
    color: #101810 !important;
    font-size: 26px !important;
    line-height: 1.05 !important;
    font-weight: 800 !important;
    letter-spacing: -.025em !important;
}

.user-form-heading p {
    margin: 0 !important;
    color: #657068 !important;
    font-size: 11px !important;
    line-height: 1.4 !important;
}

/* Back to Users */
.user-form-heading .btn-outline-asu {
    height: 40px !important;
    min-height: 40px !important;
    padding: 0 13px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    border: 1px solid rgba(0,122,51,.18) !important;
    border-radius: 10px !important;
    background: rgba(255,255,255,.72) !important;
    color: #00692c !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
    box-shadow: none !important;
}

.user-form-heading .btn-outline-asu i {
    margin: 0 !important;
}

/* =========================================================
   FORM CARD
========================================================= */

.user-form-card {
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;
}

.user-form-card-header {
    min-height: 66px !important;
    padding: 14px 18px !important;
    gap: 11px !important;
    border-bottom: 1px solid rgba(0,122,51,.09) !important;
    background: rgba(255,255,255,.84) !important;
}

.user-form-card-icon {
    width: 38px !important;
    height: 38px !important;
    flex: 0 0 38px !important;
    border-radius: 11px !important;
    background: rgba(0,122,51,.09) !important;
    color: #007a33 !important;
    font-size: 16px !important;
}

.user-form-card-header h2 {
    margin: 0 !important;
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    line-height: 1.15 !important;
    font-weight: 700 !important;
}

.user-form-card-header span {
    display: none !important;
}

.user-form-card-body {
    padding: 22px !important;
}

/* =========================================================
   SECTIONS
========================================================= */

.user-form-section {
    margin-bottom: 24px !important;
}

.user-form-section-title {
    margin-bottom: 14px !important;
    padding-bottom: 10px !important;
    border-bottom: 1px solid #eef2ed;
    color: #273029 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 11px !important;
    line-height: 1.2 !important;
    font-weight: 700 !important;
    letter-spacing: .035em !important;
}

.user-form-section-title i {
    color: #007a33 !important;
    font-size: 14px !important;
}

/* =========================================================
   BOXES / FORM CONTROLS
========================================================= */

.user-form-card .form-label {
    margin-bottom: 6px !important;
    color: #414943 !important;
    font-size: 11px !important;
    font-weight: 600 !important;
}

.user-form-card .form-control,
.user-form-card .form-select {
    min-height: 44px !important;
    height: 44px !important;
    padding: 8px 13px !important;
    border: 1px solid #d7e0d8 !important;
    border-radius: 11px !important;
    background: #fbfcfb !important;
    color: #232923 !important;
    font-size: 12px !important;
    box-shadow: none !important;
}

.user-form-card .form-control:focus,
.user-form-card .form-select:focus {
    background: #ffffff !important;
    border-color: #76ac82 !important;
    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;
}

.user-form-card .form-control::placeholder {
    color: #8a948d !important;
}

/* password helper kept subtle */
.password-note {
    margin-top: 5px !important;
    color: #8a948d !important;
    font-size: 9px !important;
}

/* =========================================================
   ACTION BUTTON
========================================================= */

.user-form-actions {
    justify-content: flex-end !important;
    gap: 9px !important;
    margin-top: 22px !important;
    padding-top: 16px !important;
}

.user-form-actions .btn {
    min-width: 118px !important;
    height: 42px !important;
    min-height: 42px !important;
    padding: 0 15px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    border-radius: 11px !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
}

.user-form-actions .btn i {
    margin: 0 !important;
}

/* Hide old cancel styling if it remains */
.btn-cancel-clean {
    display: none !important;
}

/* =========================================================
   ERROR BOX
========================================================= */

.user-error-box {
    margin-bottom: 18px !important;
    padding: 13px 15px !important;
    border-radius: 11px !important;
    font-size: 10px !important;
}

/* Duplicate footer removed */
.content-footer {
    display: none !important;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {
    .system-brand-copy strong {
        font-size: 15px !important;
    }

    .system-brand-copy span {
        font-size: 11px !important;
    }
}

@media (max-width: 700px) {
    .asu-topbar {
        padding: 0 16px !important;
    }

    .system-brand-copy strong {
        font-size: 13px !important;
    }

    .system-brand-copy span {
        font-size: 10px !important;
    }

    .page-content {
        padding: 18px 14px 24px !important;
    }

    .user-form-heading {
        align-items: flex-start !important;
    }

    .user-form-heading .btn-outline-asu {
        width: 100% !important;
    }

    .user-form-card-body {
        padding: 18px !important;
    }
}
</style>


<style>
/* =========================================================
   FINAL FORM ACTIONS — CANCEL + SAVE CHANGES
========================================================= */

.user-form-actions {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    gap: 9px !important;
}

.user-form-actions .btn {
    min-width: 118px !important;
    height: 42px !important;
    min-height: 42px !important;
    padding: 0 15px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    border-radius: 11px !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
}

.user-form-actions .btn i {
    margin: 0 !important;
    line-height: 1 !important;
}

.user-form-actions .btn-cancel-clean {
    display: inline-flex !important;
    border: 1px solid #cfd8d1 !important;
    background: #ffffff !important;
    color: #59635c !important;
}

.user-form-actions .btn-cancel-clean:hover {
    border-color: #08752c !important;
    background: #f2f8f3 !important;
    color: #08752c !important;
}

@media (max-width: 700px) {
    .user-form-actions {
        flex-direction: column-reverse !important;
    }

    .user-form-actions .btn {
        width: 100% !important;
    }
}
</style>


<style>
/* Ensure Cancel is visible beside Save Changes */
.user-form-actions .btn-cancel-clean {
    display: inline-flex !important;
    visibility: visible !important;
    opacity: 1 !important;

    min-width: 118px !important;
    height: 42px !important;
    min-height: 42px !important;
    padding: 0 15px !important;

    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;

    border: 1px solid #cfd8d1 !important;
    border-radius: 11px !important;

    background: #ffffff !important;
    color: #59635c !important;

    font-size: 11px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
    text-decoration: none !important;
}

.user-form-actions .btn-cancel-clean:hover {
    border-color: #08752c !important;
    background: #f2f8f3 !important;
    color: #08752c !important;
}

.user-form-actions .btn-cancel-clean i {
    margin: 0 !important;
    line-height: 1 !important;
}
</style>
