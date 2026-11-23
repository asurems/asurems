<?php



require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');



$user = currentUser();

$pageTitle = 'Master Data';

$activePage = 'master_data';

$errors = [];



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {

        $errors[] = 'Invalid request. Please refresh the page and try again.';

    }



    $action = $_POST['action'] ?? '';



    if (!$errors && $action === 'save_college') {

        $collegeId = (int)($_POST['college_id'] ?? 0);

        $name = trim($_POST['college_name'] ?? '');

        $code = trim($_POST['college_code'] ?? '');

        if ($name === '') $errors[] = 'College name is required.';

        if (!$errors) {

            if ($collegeId > 0) {

                $stmt = $pdo->prepare("UPDATE colleges SET college_name = ?, college_code = ? WHERE college_id = ?");

                $stmt->execute([$name, $code !== '' ? $code : null, $collegeId]);

                setFlash('success', 'College updated successfully.');

            } else {

                $stmt = $pdo->prepare("INSERT INTO colleges (college_name, college_code) VALUES (?, ?)");

                $stmt->execute([$name, $code !== '' ? $code : null]);

                setFlash('success', 'College added successfully.');

            }

            redirect(BASE_URL . '/admin/master_data/index.php');

        }

    }



    if (!$errors && $action === 'toggle_college') {

        $collegeId = (int)($_POST['college_id'] ?? 0);

        $newStatus = ($_POST['new_status'] ?? '') === 'Inactive' ? 'Inactive' : 'Active';

        $stmt = $pdo->prepare("UPDATE colleges SET status = ? WHERE college_id = ?");

        $stmt->execute([$newStatus, $collegeId]);

        setFlash('success', 'College status changed to ' . $newStatus . '.');

        redirect(BASE_URL . '/admin/master_data/index.php');

    }



    if (!$errors && $action === 'save_department') {

        $departmentId = (int)($_POST['department_id'] ?? 0);

        $collegeId = (int)($_POST['college_id'] ?? 0);

        $name = trim($_POST['department_name'] ?? '');

        $code = trim($_POST['department_code'] ?? '');

        if ($collegeId <= 0) $errors[] = 'Please select a college.';

        if ($name === '') $errors[] = 'Department name is required.';

        if (!$errors) {

            if ($departmentId > 0) {

                $stmt = $pdo->prepare("UPDATE departments SET college_id = ?, department_name = ?, department_code = ? WHERE department_id = ?");

                $stmt->execute([$collegeId, $name, $code !== '' ? $code : null, $departmentId]);

                setFlash('success', 'Department updated successfully.');

            } else {

                $stmt = $pdo->prepare("INSERT INTO departments (college_id, department_name, department_code) VALUES (?, ?, ?)");

                $stmt->execute([$collegeId, $name, $code !== '' ? $code : null]);

                setFlash('success', 'Department added successfully.');

            }

            redirect(BASE_URL . '/admin/master_data/index.php');

        }

    }



    if (!$errors && $action === 'toggle_department') {

        $departmentId = (int)($_POST['department_id'] ?? 0);

        $newStatus = ($_POST['new_status'] ?? '') === 'Inactive' ? 'Inactive' : 'Active';

        $stmt = $pdo->prepare("UPDATE departments SET status = ? WHERE department_id = ?");

        $stmt->execute([$newStatus, $departmentId]);

        setFlash('success', 'Department status changed to ' . $newStatus . '.');

        redirect(BASE_URL . '/admin/master_data/index.php');

    }

}



$editCollege = null;

if (!empty($_GET['edit_college'])) {

    $stmt = $pdo->prepare("SELECT * FROM colleges WHERE college_id = ?");

    $stmt->execute([(int)$_GET['edit_college']]);

    $editCollege = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

}



$editDepartment = null;

if (!empty($_GET['edit_department'])) {

    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ?");

    $stmt->execute([(int)$_GET['edit_department']]);

    $editDepartment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

}



$colleges = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM departments d WHERE d.college_id = c.college_id) AS department_count FROM colleges c ORDER BY c.status = 'Active' DESC, c.college_name")->fetchAll(PDO::FETCH_ASSOC);

$departments = $pdo->query("SELECT d.*, c.college_name FROM departments d INNER JOIN colleges c ON c.college_id = d.college_id ORDER BY d.status = 'Active' DESC, c.college_name, d.department_name")->fetchAll(PDO::FETCH_ASSOC);

$flash = getFlash();



$statusFlashClass = '';



if ($flash && !empty($flash['message'])) {



    if (stripos($flash['message'], 'Inactive') !== false) {

        $statusFlashClass = 'status-flash-inactive';

    } elseif (stripos($flash['message'], 'Active') !== false) {

        $statusFlashClass = 'status-flash-active';

    }

}



$initials = '';

if (!empty($user['first_name'])) $initials .= strtoupper(substr($user['first_name'], 0, 1));

if (!empty($user['last_name'])) $initials .= strtoupper(substr($user['last_name'], 0, 1));



require __DIR__ . '/../../includes/header.php';

require __DIR__ . '/../../includes/sidebar.php';

?>

<style>

.master-page{max-width:1280px;margin:0 auto}.master-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:20px}.master-heading h1{margin:0 0 5px;font-size:25px;font-weight:800;color:#1f2922}.master-heading p{margin:0;color:#78817a;font-size:11px}.master-grid{display:grid;grid-template-columns:360px minmax(0,1fr);gap:18px;margin-bottom:20px}.master-card{border:1px solid #e0e8e2;border-radius:16px;background:#fff;overflow:hidden}.master-card-head{padding:16px 18px;border-bottom:1px solid #edf1ee}.master-card-head h2{margin:0;font-size:13px;font-weight:800;color:#233027}.master-card-body{padding:18px}.master-form label{font-size:9px;font-weight:800;color:#657068;text-transform:uppercase;letter-spacing:.04em}.master-form .form-control,.master-form .form-select{min-height:42px;border-radius:10px;font-size:11px}.master-table{width:100%;border-collapse:collapse}.master-table th{padding:11px 14px;background:#f8faf8;border-bottom:1px solid #e7ece8;color:#69736b;font-size:9px;text-transform:uppercase}.master-table td{padding:12px 14px;border-bottom:1px solid #eef2ef;color:#364139;font-size:10px;vertical-align:middle}.master-table tr:last-child td{border-bottom:0}.status-pill{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:800}.status-pill.active{background:#eef8f0;color:#08752c}.status-pill.inactive{background:#f1f2f1;color:#777}.mini-actions{display:flex;gap:6px;justify-content:flex-end}.mini-actions .btn{padding:6px 9px;border-radius:8px;font-size:9px}.master-section-title{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid #edf1ee}.master-section-title h2{margin:0;font-size:13px;font-weight:800}.alert-compact{padding:10px 13px;border-radius:10px;font-size:10px}.table-wrap{overflow:auto}@media(max-width:950px){.master-grid{grid-template-columns:1fr}}

</style>

<div class="main-page">

<header class="asu-topbar"><div class="topbar-left"><button id="sidebarToggle" type="button" class="sidebar-toggle"><i class="bi bi-list"></i></button><div class="system-brand-copy">

    <strong>Research &amp; Extension Management System</strong>

    <span>Aklan State University</span>

</div></div><div class="topbar-user"><div class="topbar-user-text"><strong><?= e(fullName($user)); ?></strong><span>System Administrator</span></div><div class="user-avatar"><?= e($initials ?: 'A'); ?></div></div></header>

<main class="page-content"><div class="master-page">



<div class="master-heading">

    <div class="master-heading-copy">

        <h1>Master Data</h1>

        <p>Manage colleges and departments used across ASU-REMS.</p>

    </div>

</div>

<?php if ($flash): ?><div class="alert alert-<?= e($flash['type']); ?> alert-compact <?= e($statusFlashClass); ?>"><?= e($flash['message']); ?></div><?php endif; ?>

<?php if ($errors): ?><div class="alert alert-danger alert-compact"><?= e(implode(' ', $errors)); ?></div><?php endif; ?>





<section id="colleges-section" class="master-data-section">

    <div class="master-data-section-head">

        <div>

            <h2>Colleges</h2>

            <p>Manage institutional colleges used across ASU-REMS.</p>

        </div>

        <span class="record-count"><?= number_format(count($colleges)); ?> records</span>

    </div>



    <div class="master-inline-form">

        <div class="master-inline-form-title">

            <i class="bi bi-building"></i>

            <span><?= $editCollege ? 'Edit College' : 'Add College'; ?></span>

        </div>



        <form method="post" class="master-form master-form-horizontal">

            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>"><input type="hidden" name="action" value="save_college"><input type="hidden" name="college_id" value="<?= (int)($editCollege['college_id'] ?? 0); ?>">

<div class="mb-3"><label>College Name</label><input class="form-control" name="college_name" required value="<?= e($editCollege['college_name'] ?? ''); ?>"></div>

<div class="mb-3"><label>College Code</label><input class="form-control" name="college_code" value="<?= e($editCollege['college_code'] ?? ''); ?>" placeholder="Optional"></div>

<div class="d-flex gap-2"><button class="btn btn-asu flex-grow-1" type="submit"><i class="bi bi-check2 me-1"></i><?= $editCollege ? 'Save Changes' : 'Add College'; ?></button><?php if ($editCollege): ?><a class="btn btn-outline-asu" href="<?= BASE_URL; ?>/admin/master_data/index.php">Cancel</a><?php endif; ?></div>



        </form>

    </div>



    <div class="master-table-card">

        <div class="table-wrap">

            <table class="master-table"><thead><tr><th>College</th><th>Code</th><th>Departments</th><th>Status</th><th></th></tr></thead><tbody>

<?php foreach ($colleges as $college): ?><tr><td><strong><?= e($college['college_name']); ?></strong></td><td><?= e($college['college_code'] ?: '—'); ?></td><td><?= number_format((int)$college['department_count']); ?></td><td><span class="status-pill <?= strtolower($college['status']); ?>"><?= e($college['status']); ?></span></td><td><div class="mini-actions"><a class="btn btn-light border" href="?edit_college=<?= (int)$college['college_id']; ?>" title="Edit College"><i class="bi bi-pencil"></i></a><form method="post"><input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>"><input type="hidden" name="action" value="toggle_college"><input type="hidden" name="college_id" value="<?= (int)$college['college_id']; ?>"><input type="hidden" name="new_status" value="<?= $college['status'] === 'Active' ? 'Inactive' : 'Active'; ?>"><button class="btn btn-light border" type="submit" title="<?= $college['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>"><i class="bi <?= $college['status'] === 'Active' ? 'bi-pause-circle' : 'bi-check-circle'; ?>"></i></button></form></div></td></tr><?php endforeach; ?>

</tbody></table>

        </div>

    </div>

</section>







<section class="master-data-section">

    <div class="master-data-section-head">

        <div>

            <h2>Departments</h2>

            <p>Manage departments and their assigned colleges.</p>

        </div>

        <span class="record-count"><?= number_format(count($departments)); ?> records</span>

    </div>



    <div class="master-inline-form">

        <div class="master-inline-form-title">

            <i class="bi bi-diagram-3"></i>

            <span><?= $editDepartment ? 'Edit Department' : 'Add Department'; ?></span>

        </div>



        <form method="post" class="master-form master-form-horizontal">

            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>"><input type="hidden" name="action" value="save_department"><input type="hidden" name="department_id" value="<?= (int)($editDepartment['department_id'] ?? 0); ?>">

<div class="mb-3"><label>College</label><select class="form-select" name="college_id" required><option value="">Select college</option><?php foreach ($colleges as $college): ?><option value="<?= (int)$college['college_id']; ?>" <?= (int)($editDepartment['college_id'] ?? 0) === (int)$college['college_id'] ? 'selected' : ''; ?>><?= e($college['college_name']); ?></option><?php endforeach; ?></select></div>

<div class="mb-3"><label>Department Name</label><input class="form-control" name="department_name" required value="<?= e($editDepartment['department_name'] ?? ''); ?>"></div><div class="mb-3"><label>Department Code</label><input class="form-control" name="department_code" value="<?= e($editDepartment['department_code'] ?? ''); ?>" placeholder="Optional"></div>

<div class="d-flex gap-2"><button class="btn btn-asu flex-grow-1" type="submit"><i class="bi bi-check2 me-1"></i><?= $editDepartment ? 'Save Changes' : 'Add Department'; ?></button><?php if ($editDepartment): ?><a class="btn btn-outline-asu" href="<?= BASE_URL; ?>/admin/master_data/index.php">Cancel</a><?php endif; ?></div>



        </form>

    </div>



    <div class="master-table-card">

        <div class="table-wrap">

            <table class="master-table"><thead><tr><th>Department</th><th>College</th><th>Code</th><th>Status</th><th></th></tr></thead><tbody>

<?php foreach ($departments as $department): ?><tr><td><strong><?= e($department['department_name']); ?></strong></td><td><?= e($department['college_name']); ?></td><td><?= e($department['department_code'] ?: '—'); ?></td><td><span class="status-pill <?= strtolower($department['status']); ?>"><?= e($department['status']); ?></span></td><td><div class="mini-actions"><a class="btn btn-light border" href="?edit_department=<?= (int)$department['department_id']; ?>" title="Edit Department"><i class="bi bi-pencil"></i></a><form method="post"><input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()); ?>"><input type="hidden" name="action" value="toggle_department"><input type="hidden" name="department_id" value="<?= (int)$department['department_id']; ?>"><input type="hidden" name="new_status" value="<?= $department['status'] === 'Active' ? 'Inactive' : 'Active'; ?>"><button class="btn btn-light border" type="submit" title="<?= $department['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>"><i class="bi <?= $department['status'] === 'Active' ? 'bi-pause-circle' : 'bi-check-circle'; ?>"></i></button></form></div></td></tr><?php endforeach; ?>

</tbody></table>

        </div>

    </div>

</section>



</div></main></div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>





<style>

/* =========================================================

   MASTER DATA — FINAL UNIFORM ADMIN INTERFACE

========================================================= */



:root {

    --asu-green: #007a33;

    --asu-green-dark: #005c26;

    --asu-green-deep: #003f1a;

    --asu-yellow: #ffd51f;

    --asu-ink: #101810;

    --asu-muted: #657068;

}



.main-page,

.main-page button,

.main-page input,

.main-page select,

.main-page textarea,

.main-page a,

.main-page table {

    font-family: "Century Gothic", sans-serif !important;

}



/* =========================================================

   PAGE BACKGROUND

========================================================= */



.main-page {

    min-height: 100vh !important;

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

    min-height: calc(100vh - 76px) !important;

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

   TOPBAR — SAME AS USERS / ADMIN DASHBOARD

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

    background: var(--asu-green);

}



.system-brand-copy strong {

    display: block;

    color: var(--asu-ink);

    font-size: 17px;

    font-weight: 800;

    letter-spacing: -.015em;

}



.system-brand-copy span {

    display: block;

    margin-top: 4px;

    color: var(--asu-muted);

    font-size: 13px;

    font-weight: 400;

}



.topbar-user-text strong {

    font-size: 15px !important;

    line-height: 1.1 !important;

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

    background: var(--asu-yellow) !important;

    color: var(--asu-green-deep) !important;

    font-size: 15px !important;

    font-weight: 900 !important;

    box-shadow: 0 8px 18px rgba(255,213,31,.25) !important;

}



/* =========================================================

   PAGE WIDTH / HEADING

========================================================= */



.master-page {

    max-width: 1320px !important;

    margin: 0 auto !important;

}



.master-heading {

    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    gap: 18px !important;

    margin-bottom: 18px !important;

}



.master-heading h1 {

    margin: 0 0 3px !important;

    color: var(--asu-ink) !important;

    font-size: 26px !important;

    line-height: 1.05 !important;

    font-weight: 800 !important;

    letter-spacing: -.025em !important;

}



.master-heading p {

    margin: 0 !important;

    color: var(--asu-muted) !important;

    font-size: 11px !important;

    line-height: 1.4 !important;

}



/* =========================================================

   NOTIFICATIONS — SAME AS USERS

========================================================= */



.master-page .alert {

    width: 100% !important;

    max-width: none !important;

    min-height: 44px !important;

    margin: 0 0 14px 0 !important;

    padding: 11px 14px !important;

    display: flex !important;

    align-items: center !important;

    border-radius: 11px !important;

    font-size: 11.5px !important;

    line-height: 1.4 !important;

    font-weight: 650 !important;

    box-shadow: 0 7px 18px rgba(16,24,16,.045) !important;

}



.master-page .alert.status-flash-active {

    border: 1px solid rgba(0,122,51,.20) !important;

    background: #f2f8f3 !important;

    color: #08752c !important;

}



.master-page .alert.status-flash-inactive {

    border: 1px solid rgba(201,143,0,.24) !important;

    background: #fff9e8 !important;

    color: #b77900 !important;

}



.master-page .alert-danger {

    border: 1px solid rgba(190,45,45,.18) !important;

    background: #fff5f5 !important;

    color: #8a3030 !important;

}



/* =========================================================

   MASTER DATA LAYOUT

========================================================= */



.master-grid {

    display: grid !important;

    grid-template-columns: 340px minmax(0, 1fr) !important;

    gap: 18px !important;

    margin-bottom: 18px !important;

    align-items: start !important;

}



/* =========================================================

   CARDS

========================================================= */



.master-card {

    overflow: hidden !important;

    border: 1px solid rgba(0,122,51,.13) !important;

    border-radius: 18px !important;

    background: rgba(255,255,255,.96) !important;

    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;

}



.master-card-head,

.master-section-title {

    min-height: 62px !important;

    padding: 14px 18px !important;

    border-bottom: 1px solid rgba(0,122,51,.09) !important;

    background: rgba(255,255,255,.84) !important;

}



.master-card-head {

    display: flex !important;

    align-items: center !important;

}



.master-card-head h2,

.master-section-title h2 {

    margin: 0 !important;

    color: var(--asu-ink) !important;

    font-family: "Century Gothic", sans-serif !important;

    font-size: 14px !important;

    line-height: 1.15 !important;

    font-weight: 700 !important;

}



.master-section-title {

    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    gap: 12px !important;

}



.master-section-title .small {

    font-size: 10px !important;

}



.master-card-body {

    padding: 18px !important;

}



/* =========================================================

   FORM BOXES — SAME SMOOTH STYLE

========================================================= */



.master-form label {

    display: block !important;

    margin-bottom: 6px !important;

    color: #414943 !important;

    font-size: 10.5px !important;

    font-weight: 600 !important;

    text-transform: none !important;

    letter-spacing: 0 !important;

}



.master-form .form-control,

.master-form .form-select {

    min-height: 44px !important;

    height: 44px !important;

    padding: 8px 13px !important;

    border: 1px solid #d7e0d8 !important;

    border-radius: 11px !important;

    background: #fbfcfb !important;

    color: #232923 !important;

    font-size: 11.5px !important;

    box-shadow: none !important;

}



.master-form .form-control:focus,

.master-form .form-select:focus {

    background: #ffffff !important;

    border-color: #76ac82 !important;

    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;

}



.master-form .form-control::placeholder {

    color: #8a948d !important;

}



/* =========================================================

   ADD / SAVE / CANCEL BUTTONS

========================================================= */



.master-form .d-flex.gap-2 {

    align-items: center !important;

    gap: 8px !important;

}



.master-form .btn {

    min-height: 42px !important;

    height: 42px !important;

    padding: 0 14px !important;

    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;

    gap: 6px !important;

    border-radius: 11px !important;

    font-size: 10.5px !important;

    font-weight: 800 !important;

    line-height: 1 !important;

}



.master-form .btn i {

    margin: 0 !important;

    line-height: 1 !important;

}



.master-form .btn-asu {

    border: 0 !important;

    background: linear-gradient(

        135deg,

        var(--asu-green),

        var(--asu-green-dark)

    ) !important;

    color: #fff !important;

    box-shadow: 0 8px 18px rgba(0,122,51,.14) !important;

}



.master-form .btn-outline-asu {

    min-width: 88px !important;

    border: 1px solid #cfd8d1 !important;

    background: #ffffff !important;

    color: #59635c !important;

    box-shadow: none !important;

}



.master-form .btn-outline-asu:hover {

    border-color: var(--asu-green) !important;

    background: #f2f8f3 !important;

    color: var(--asu-green) !important;

}



/* =========================================================

   TABLES

========================================================= */



.table-wrap {

    overflow: auto !important;

}



.master-table {

    width: 100% !important;

    margin: 0 !important;

    border-collapse: collapse !important;

    background: transparent !important;

}



.master-table th {

    padding: 12px 14px !important;

    border-bottom: 1px solid rgba(0,122,51,.12) !important;

    background: #f8fbf8 !important;

    color: #35423a !important;

    font-family: "Century Gothic", sans-serif !important;

    font-size: 10px !important;

    font-weight: 700 !important;

    letter-spacing: .025em !important;

    text-transform: uppercase !important;

    white-space: nowrap !important;

}



.master-table td {

    padding: 12px 14px !important;

    border-bottom: 1px solid rgba(0,122,51,.07) !important;

    color: #263229 !important;

    font-size: 11px !important;

    vertical-align: middle !important;

}



.master-table tbody tr:hover td {

    background: rgba(0,122,51,.025) !important;

}



.master-table tr:last-child td {

    border-bottom: 0 !important;

}



/* =========================================================

   STATUS PILLS

========================================================= */



.status-pill {

    min-width: 64px !important;

    min-height: 26px !important;

    padding: 5px 9px !important;

    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;

    border-radius: 999px !important;

    font-size: 9px !important;

    font-weight: 800 !important;

    line-height: 1 !important;

}



.status-pill.active {

    background: #eaf7ed !important;

    color: #08752c !important;

}



.status-pill.inactive {

    background: #fff5d8 !important;

    color: #ad7300 !important;

}



/* =========================================================

   TABLE ACTION BUTTONS — EQUAL SIZE

========================================================= */



.mini-actions {

    display: flex !important;

    align-items: center !important;

    justify-content: flex-end !important;

    gap: 7px !important;

}



.mini-actions form {

    margin: 0 !important;

}



.mini-actions .btn {

    width: 42px !important;

    min-width: 42px !important;

    max-width: 42px !important;



    height: 42px !important;

    min-height: 42px !important;

    max-height: 42px !important;



    padding: 0 !important;



    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;



    border-radius: 10px !important;



    background: #ffffff !important;

    box-sizing: border-box !important;



    font-size: 13px !important;

    line-height: 1 !important;



    box-shadow: none !important;

}



.mini-actions .btn i {

    margin: 0 !important;

    line-height: 1 !important;

}



/* Edit */

.mini-actions a.btn {

    border: 1px solid rgba(0,122,51,.28) !important;

    color: var(--asu-green) !important;

}



.mini-actions a.btn:hover {

    background: #f2f8f3 !important;

}



/* Active record => deactivate action */

.status-pill.active ~ * .btn,

.mini-actions form button[title="Deactivate"] {

    border: 1px solid rgba(196,45,56,.32) !important;

    color: #c52f3d !important;

}



.mini-actions form button[title="Deactivate"]:hover {

    background: #fff3f4 !important;

}



/* Inactive record => activate action */

.mini-actions form button[title="Activate"] {

    border: 1px solid rgba(0,122,51,.28) !important;

    color: var(--asu-green) !important;

}



.mini-actions form button[title="Activate"]:hover {

    background: #f2f8f3 !important;

}



/* =========================================================

   RESPONSIVE

========================================================= */



@media (max-width: 980px) {

    .master-grid {

        grid-template-columns: 1fr !important;

    }

}



@media (max-width: 760px) {

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



    .master-heading {

        align-items: flex-start !important;

    }

}

</style>





<style>

/* =========================================================

   MASTER DATA — REBALANCED PLACEMENT

   Full-width sections with horizontal form + table

========================================================= */



.master-page {

    max-width: 1320px !important;

}



.master-data-section {

    margin-bottom: 20px !important;

    overflow: hidden !important;

    border: 1px solid rgba(0,122,51,.13) !important;

    border-radius: 18px !important;

    background: rgba(255,255,255,.96) !important;

    box-shadow: 0 16px 38px rgba(16,24,16,.055) !important;

}



.master-data-section:last-child {

    margin-bottom: 0 !important;

}



.master-data-section-head {

    min-height: 66px !important;

    padding: 14px 18px !important;



    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    gap: 16px !important;



    border-bottom: 1px solid rgba(0,122,51,.09) !important;

    background: rgba(255,255,255,.86) !important;

}



.master-data-section-head h2 {

    margin: 0 0 3px !important;

    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;

    font-size: 15px !important;

    font-weight: 700 !important;

}



.master-data-section-head p {

    margin: 0 !important;

    color: #748078 !important;

    font-size: 10px !important;

    line-height: 1.35 !important;

}



.record-count {

    flex: 0 0 auto !important;

    padding: 6px 10px !important;

    border-radius: 999px !important;

    background: #f2f7f3 !important;

    color: #647168 !important;

    font-size: 9.5px !important;

    font-weight: 700 !important;

    white-space: nowrap !important;

}



/* Compact form strip above table */

.master-inline-form {

    padding: 15px 18px 16px !important;

    border-bottom: 1px solid rgba(0,122,51,.08) !important;

    background: #fbfdfb !important;

}



.master-inline-form-title {

    margin-bottom: 10px !important;

    display: flex !important;

    align-items: center !important;

    gap: 7px !important;

    color: #2c382f !important;

    font-family: "Century Gothic", sans-serif !important;

    font-size: 11px !important;

    font-weight: 700 !important;

}



.master-inline-form-title i {

    color: #007a33 !important;

    font-size: 14px !important;

}



/* Horizontal form placement */

.master-form-horizontal {

    display: grid !important;

    grid-template-columns: minmax(0, 1.7fr) minmax(150px, .8fr) auto !important;

    gap: 12px !important;

    align-items: end !important;

}



/* Department form has one more field */

.master-data-section:nth-of-type(2) .master-form-horizontal {

    grid-template-columns:

        minmax(180px, 1fr)

        minmax(0, 1.35fr)

        minmax(150px, .7fr)

        auto !important;

}



/* The original mb-3 blocks should not add vertical whitespace */

.master-form-horizontal > .mb-3 {

    margin-bottom: 0 !important;

}



/* Action row becomes compact and aligned with inputs */

.master-form-horizontal > .d-flex.gap-2 {

    margin: 0 !important;

    align-self: end !important;

    white-space: nowrap !important;

}



.master-form-horizontal > .d-flex.gap-2 .btn {

    min-width: 116px !important;

}



.master-form-horizontal > .d-flex.gap-2 .btn-outline-asu {

    min-width: 82px !important;

}



/* Table becomes part of same section rather than a separate floating card */

.master-table-card {

    background: #ffffff !important;

}



.master-table-card .master-table th {

    padding-top: 11px !important;

    padding-bottom: 11px !important;

}



.master-table-card .master-table td {

    padding-top: 11px !important;

    padding-bottom: 11px !important;

}



/* Neutralize old 2-column wrapper rules if any are still present */

.master-grid {

    display: block !important;

    margin: 0 !important;

}



@media (max-width: 1080px) {

    .master-form-horizontal,

    .master-data-section:nth-of-type(2) .master-form-horizontal {

        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;

    }



    .master-form-horizontal > .d-flex.gap-2 {

        grid-column: 1 / -1 !important;

        justify-content: flex-end !important;

    }

}



@media (max-width: 700px) {

    .master-data-section-head {

        align-items: flex-start !important;

    }



    .master-form-horizontal,

    .master-data-section:nth-of-type(2) .master-form-horizontal {

        grid-template-columns: 1fr !important;

    }



    .master-form-horizontal > .d-flex.gap-2 {

        grid-column: auto !important;

        width: 100% !important;

    }



    .master-form-horizontal > .d-flex.gap-2 .btn {

        flex: 1 1 auto !important;

    }



    .master-inline-form {

        padding: 14px !important;

    }

}

</style>





<style>

/* =========================================================

   MASTER DATA HERO — MATCH USER MANAGEMENT STYLE

========================================================= */



.master-heading {

    position: relative !important;

    overflow: hidden !important;

    min-height: 150px !important;

    margin: 0 0 20px !important;

    padding: 30px 34px !important;

    border-radius: 24px !important;



    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    gap: 24px !important;



    background:

        linear-gradient(

            112deg,

            #005c26 0%,

            #007a33 64%,

            #2c9a39 100%

        ) !important;



    box-shadow: 0 18px 42px rgba(0,90,40,.16) !important;

}



.master-heading::after {

    content: "" !important;

    position: absolute !important;

    right: -75px !important;

    top: -90px !important;

    width: 500px !important;

    height: 330px !important;

    transform: rotate(-9deg) !important;

    border-radius: 42px !important;

    background:

        linear-gradient(

            135deg,

            rgba(255,213,31,.16),

            rgba(255,255,255,.035)

        ) !important;

    pointer-events: none !important;

}



.master-heading > * {

    position: relative !important;

    z-index: 2 !important;

}



.master-heading-copy {

    min-width: 0 !important;

}



.master-heading h1 {

    margin: 0 !important;

    color: #ffffff !important;

    font-size: clamp(34px, 3vw, 44px) !important;

    line-height: 1 !important;

    font-weight: 900 !important;

    letter-spacing: -.04em !important;

}



.master-heading p {

    display: none !important;

}



/* Yellow action button, same family as User Management */

.master-hero-btn {

    min-width: 170px !important;

    min-height: 48px !important;

    padding: 0 20px !important;



    display: inline-flex !important;

    align-items: center !important;

    justify-content: center !important;

    gap: 8px !important;



    border: 0 !important;

    border-radius: 14px !important;



    background: #ffd51f !important;

    color: #003f1a !important;

    text-decoration: none !important;



    font-size: 12px !important;

    font-weight: 850 !important;

    line-height: 1 !important;



    box-shadow: 0 12px 26px rgba(255,213,31,.23) !important;

    transition: transform .18s ease, *background* .18s ease !important;

}



.master-hero-btn:hover {

    transform: translateY(-2px) !important;

    background: #ffdb36 !important;

    color: #003f1a !important;

}



.master-hero-btn i {

    margin: 0 !important;

    font-size: 14px !important;

    line-height: 1 !important;

}



/* Mobile */

@media (max-width: 760px) {

    .master-heading {

        min-height: auto !important;

        align-items: flex-start !important;

        flex-direction: column !important;

        padding: 24px 20px !important;

        border-radius: 18px !important;

    }



    .master-heading h1 {

        font-size: 31px !important;

    }



    .master-hero-btn {

        width: 100% !important;

    }

}

</style>





<style>

/* Master Data hero without action button */

.master-heading {

    justify-content: flex-start !important;

}

</style>





<style>

/* =========================================================

   MASTER DATA HERO — SAME EXACT SIZE AS OTHER ADMIN PAGES

========================================================= */



.master-heading {

    min-height: 126px !important;

    margin: 0 0 20px !important;

    padding: 24px 30px !important;

    border-radius: 20px !important;



    display: flex !important;

    align-items: center !important;

    justify-content: flex-start !important;

    gap: 18px !important;

}



.master-heading::after {

    right: -75px !important;

    top: -90px !important;

    width: 500px !important;

    height: 330px !important;

}



.master-heading h1 {

    margin: 0 !important;

    font-size: 31px !important;

    line-height: 1.05 !important;

    font-weight: 900 !important;

    letter-spacing: -.035em !important;

}



.master-heading p {

    display: none !important;

}



@media (max-width: 760px) {

    .master-heading {

        min-height: auto !important;

        padding: 22px 18px !important;

        border-radius: 18px !important;

    }



    .master-heading h1 {

        font-size: 27px !important;

    }

}

</style>





<style>

/* =========================================================

   MASTER DATA HERO — EXACT MATCH TO RESEARCH RECORDS HERO

========================================================= */



.master-heading {

    min-height: 150px !important;

    margin: 0 0 20px !important;

    padding: 30px 34px !important;

    border-radius: 24px !important;



    display: flex !important;

    align-items: center !important;

    justify-content: flex-start !important;

    gap: 24px !important;

}



.master-heading::after {

    right: -75px !important;

    top: -90px !important;

    width: 500px !important;

    height: 330px !important;

}



.master-heading h1 {

    margin: 0 !important;

    font-size: clamp(34px, 3vw, 44px) !important;

    line-height: 1 !important;

    font-weight: 900 !important;

    letter-spacing: -.04em !important;

}



.master-heading p {

    display: none !important;

}



@media (max-width: 760px) {

    .master-heading {

        min-height: auto !important;

        padding: 24px 20px !important;

        border-radius: 18px !important;

    }



    .master-heading h1 {

        font-size: 31px !important;

    }

}

</style>





<style>

/* =========================================================

   MASTER DATA — EXACT SAME BANNER SIZE AS RESEARCH RECORDS

========================================================= */



.master-heading {

    min-height: 96px !important;

    margin: 0 0 18px !important;

    padding: 20px 26px !important;

    border-radius: 18px !important;



    display: flex !important;

    align-items: center !important;

    justify-content: flex-start !important;

}



.master-heading::after {

    right: -110px !important;

    top: -120px !important;

    width: 430px !important;

    height: 280px !important;

}



.master-heading h1 {

    margin: 0 !important;

    font-size: 30px !important;

    line-height: 1 !important;

    font-weight: 900 !important;

    letter-spacing: -.03em !important;

}



.master-heading p {

    display: none !important;

}



@media (max-width: 760px) {

    .master-heading {

        min-height: 82px !important;

        padding: 18px 18px !important;

        border-radius: 16px !important;

    }



    .master-heading h1 {

        font-size: 27px !important;

    }

}

</style>
