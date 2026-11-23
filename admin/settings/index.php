<?php

require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$user = currentUser();
$pageTitle = 'System Settings';
$activePage = 'settings';
$errors = [];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key varchar(100) NOT NULL,
        setting_value text DEFAULT NULL,
        updated_by int(10) UNSIGNED DEFAULT NULL,
        updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (setting_key),
        KEY idx_system_settings_updated_by (updated_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    $errors[] = 'System settings storage could not be initialized. Please verify database permissions.';
}

$defaults = [
    'institution_name' => 'Aklan State University',
    'system_name' => 'ASU-REMS',
    'office_name' => 'Research & Extension Office',
    'support_email' => '',
    'academic_year' => date('Y') . '-' . (date('Y') + 1),
    'maintenance_notice' => ''
];

if (!$errors) {
    foreach ($defaults as $key => $value) {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO system_settings
            (setting_key, setting_value, updated_by)
            VALUES (?, ?, ?)"
        );

        $stmt->execute([
            $key,
            $value,
            (int)$user['user_id']
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {

        $errors[] =
            'Invalid request. Please refresh the page and try again.';

    } else {

        $values = [];

        foreach (array_keys($defaults) as $key) {
            $values[$key] =
                trim(
                    (string)(
                        $_POST[$key]
                        ?? ''
                    )
                );
        }

        if ($values['institution_name'] === '') {
            $errors[] = 'Institution name is required.';
        }

        if ($values['system_name'] === '') {
            $errors[] = 'System name is required.';
        }

        if ($values['office_name'] === '') {
            $errors[] = 'Office name is required.';
        }

        if (
            $values['support_email'] !== ''
            &&
            !filter_var(
                $values['support_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $errors[] = 'Support email is not valid.';
        }

        if (!$errors) {

            $stmt = $pdo->prepare(
                "INSERT INTO system_settings
                (setting_key, setting_value, updated_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by)"
            );

            foreach ($values as $key => $value) {

                $stmt->execute([
                    $key,
                    $value,
                    (int)$user['user_id']
                ]);
            }

            setFlash(
                'success',
                'System settings saved successfully.'
            );

            redirect(
                BASE_URL
                . '/admin/settings/index.php'
            );
        }
    }
}

$settings = $defaults;

if (!$errors) {

    foreach (
        $pdo
            ->query("
                SELECT
                    setting_key,
                    setting_value
                FROM system_settings
            ")
            ->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        if (
            array_key_exists(
                $row['setting_key'],
                $settings
            )
        ) {
            $settings[
                $row['setting_key']
            ] =
                (string)$row[
                    'setting_value'
                ];
        }
    }
}

$flash = getFlash();

$initials = '';

if (!empty($user['first_name'])) {
    $initials .=
        strtoupper(
            substr(
                $user['first_name'],
                0,
                1
            )
        );
}

if (!empty($user['last_name'])) {
    $initials .=
        strtoupper(
            substr(
                $user['last_name'],
                0,
                1
            )
        );
}

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/sidebar.php';

?>

<style>
:root {
    --asu-green: #007a33;
    --asu-green-dark: #005c26;
    --asu-green-deep: #003f1a;
    --asu-yellow: #ffd51f;
    --asu-ink: #101810;
    --asu-muted: #657068;
}

/* =========================================================
   GLOBAL / LAYOUT
========================================================= */

.main-page,
.main-page button,
.main-page input,
.main-page select,
.main-page textarea,
.main-page a {
    font-family: "Century Gothic", sans-serif !important;
}

.main-page {
    height: 100vh !important;
    min-height: 100vh !important;
    overflow: hidden !important;

    background:
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

/*
 * IMPORTANT:
 * Right/main content becomes its own scroll area.
 * The topbar stays visible while this section scrolls.
 */
.page-content {
    height: calc(100vh - 76px) !important;
    min-height: 0 !important;

    overflow-y: auto !important;
    overflow-x: hidden !important;

    padding: 24px 28px 30px !important;

    scrollbar-gutter: stable;

    background:
        radial-gradient(
            circle at 90% 5%,
            rgba(255,213,31,.10),
            transparent 25%
        ),
        radial-gradient(
            circle at 8% 7%,
            rgba(0,122,51,.07),
            transparent 27%
        ),
        linear-gradient(
            135deg,
            #dff1e3 0%,
            #e8f0e3 34%,
            #f1eee2 68%,
            #f4e6d5 100%
        ) !important;
}

.page-content::-webkit-scrollbar {
    width: 8px;
}

.page-content::-webkit-scrollbar-track {
    background: transparent;
}

.page-content::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(0,122,51,.22);
}

.page-content::-webkit-scrollbar-thumb:hover {
    background: rgba(0,122,51,.34);
}

.settings-page {
    max-width: 1120px !important;
    margin: 0 auto !important;
}

/* =========================================================
   TOPBAR
========================================================= */

.asu-topbar {
    position: relative !important;
    z-index: 20 !important;

    min-height: 76px !important;
    height: 76px !important;

    padding: 0 30px !important;

    background: rgba(255,255,255,.97) !important;

    border-bottom:
        1px solid
        rgba(0,122,51,.12) !important;

    box-shadow:
        0 10px 26px
        rgba(16,24,16,.035) !important;
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

    transform:
        translateY(-50%);

    border-radius: 999px;

    background:
        var(--asu-green);
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

    background:
        var(--asu-yellow) !important;

    color:
        var(--asu-green-deep) !important;

    font-size: 15px !important;
    font-weight: 900 !important;

    box-shadow:
        0 8px 18px
        rgba(255,213,31,.25) !important;
}

/* =========================================================
   SMALL HERO — SAME AS OTHER MAIN ADMIN INTERFACES
========================================================= */

.settings-heading {
    position: relative !important;
    overflow: hidden !important;

    min-height: 96px !important;

    margin: 0 0 18px !important;
    padding: 20px 26px !important;

    display: flex !important;
    align-items: center !important;

    border-radius: 18px !important;

    background:
        linear-gradient(
            112deg,
            #005c26 0%,
            #007a33 64%,
            #2c9a39 100%
        ) !important;

    box-shadow:
        0 18px 42px
        rgba(0,90,40,.16) !important;
}

.settings-heading::after {
    content: "";

    position: absolute;

    right: -110px;
    top: -120px;

    width: 430px;
    height: 280px;

    transform: rotate(-9deg);

    border-radius: 42px;

    background:
        linear-gradient(
            135deg,
            rgba(255,213,31,.16),
            rgba(255,255,255,.035)
        );

    pointer-events: none;
}

.settings-heading > * {
    position: relative;
    z-index: 2;
}

.settings-heading h1 {
    margin: 0 !important;

    color: #ffffff !important;

    font-size: 30px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.03em !important;
}

.settings-heading p {
    display: none !important;
}

/* =========================================================
   FLASH / ERROR ALERTS
========================================================= */

.settings-page .alert {
    width: 100% !important;

    min-height: 44px !important;

    margin: 0 0 14px !important;
    padding: 11px 14px !important;

    display: flex !important;
    align-items: center !important;

    border-radius: 11px !important;

    font-size: 11.5px !important;
    line-height: 1.4 !important;
    font-weight: 650 !important;

    box-shadow:
        0 7px 18px
        rgba(16,24,16,.045) !important;
}

.settings-page .alert-success {
    border:
        1px solid
        rgba(0,122,51,.20) !important;

    background:
        #f2f8f3 !important;

    color:
        #08752c !important;
}

.settings-page .alert-danger {
    border:
        1px solid
        rgba(190,45,45,.18) !important;

    background:
        #fff5f5 !important;

    color:
        #8a3030 !important;
}

/* =========================================================
   SETTINGS CARD
========================================================= */

.settings-card {
    overflow: hidden !important;

    border:
        1px solid
        rgba(0,122,51,.13) !important;

    border-radius: 18px !important;

    background:
        rgba(255,255,255,.96) !important;

    box-shadow:
        0 16px 38px
        rgba(16,24,16,.06) !important;
}

.settings-card-head {
    min-height: 66px !important;

    padding: 14px 18px !important;

    display: flex !important;
    align-items: center !important;

    border-bottom:
        1px solid
        rgba(0,122,51,.09) !important;

    background:
        rgba(255,255,255,.86) !important;
}

.settings-card-head h2 {
    margin: 0 !important;

    color: #101810 !important;

    font-family:
        "Century Gothic",
        sans-serif !important;

    font-size: 15px !important;
    font-weight: 700 !important;
}

.settings-body {
    padding: 18px !important;
}

/* =========================================================
   FORM GRID
========================================================= */

.settings-grid {
    display: grid !important;

    grid-template-columns:
        repeat(2,minmax(0,1fr)) !important;

    gap: 15px 16px !important;
}

.settings-field.full {
    grid-column:
        1 / -1 !important;
}

.settings-field label {
    display: block !important;

    margin-bottom: 6px !important;

    color: #414943 !important;

    font-size: 10.5px !important;
    font-weight: 600 !important;

    text-transform: none !important;
    letter-spacing: 0 !important;
}

.settings-field .form-control {
    min-height: 44px !important;
    height: 44px !important;

    padding: 8px 13px !important;

    border:
        1px solid
        #d7e0d8 !important;

    border-radius: 11px !important;

    background:
        #fbfcfb !important;

    color:
        #232923 !important;

    font-size:
        11.5px !important;

    box-shadow:
        none !important;
}

.settings-field .form-control:focus {
    background:
        #ffffff !important;

    border-color:
        #76ac82 !important;

    box-shadow:
        0 0 0 3px
        rgba(8,117,44,.07) !important;
}

.settings-field .form-control::placeholder {
    color:
        #8a948d !important;
}

.settings-field textarea.form-control {
    min-height: 98px !important;
    height: 98px !important;

    resize: vertical !important;
}

/* =========================================================
   ACTIONS
========================================================= */

.settings-actions {
    display: flex !important;
    justify-content: flex-end !important;

    margin-top: 20px !important;
    padding-top: 18px !important;

    border-top:
        1px solid
        rgba(0,122,51,.09) !important;
}

.settings-actions .btn {
    min-height: 42px !important;
    height: 42px !important;

    padding: 0 15px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    gap: 6px !important;

    border-radius: 11px !important;

    font-size: 10.5px !important;
    font-weight: 800 !important;
}

.settings-actions .btn-asu {
    border: 0 !important;

    background:
        linear-gradient(
            135deg,
            var(--asu-green),
            var(--asu-green-dark)
        ) !important;

    color: #ffffff !important;

    box-shadow:
        0 8px 18px
        rgba(0,122,51,.14) !important;
}

/* =========================================================
   ADMINISTRATIVE NOTE
========================================================= */

.settings-note {
    margin-top: 15px !important;

    padding: 12px 14px !important;

    border:
        1px solid
        rgba(0,122,51,.11) !important;

    border-radius: 12px !important;

    background:
        #f8fbf8 !important;

    color:
        #68736b !important;

    font-size:
        10px !important;

    line-height:
        1.55 !important;
}

.settings-note strong {
    color:
        #35423a !important;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 760px) {

    .asu-topbar {
        padding:
            0 16px !important;
    }

    .system-brand-copy strong {
        font-size:
            13px !important;
    }

    .system-brand-copy span {
        font-size:
            10px !important;
    }

    .page-content {
        padding:
            18px 14px 24px !important;
    }

    .settings-heading {
        min-height:
            82px !important;

        padding:
            18px !important;

        border-radius:
            16px !important;
    }

    .settings-heading h1 {
        font-size:
            27px !important;
    }

    .settings-grid {
        grid-template-columns:
            1fr !important;
    }

    .settings-field.full {
        grid-column:
            auto !important;
    }

    .settings-actions .btn {
        width:
            100% !important;
    }
}
</style>

<div class="main-page">

    <header class="asu-topbar">

        <div class="topbar-left">

            <button
                id="sidebarToggle"
                class="sidebar-toggle"
                type="button"
                aria-label="Open navigation"
            >
                <i class="bi bi-list"></i>
            </button>

            <div class="system-brand-copy">

                <strong>
                    Research &amp; Extension Management System
                </strong>

                <span>
                    Aklan State University
                </span>

            </div>

        </div>

        <div class="topbar-user">

            <div class="topbar-user-text">

                <strong>
                    <?= e(fullName($user)); ?>
                </strong>

                <span>
                    System Administrator
                </span>

            </div>

            <div class="user-avatar">
                <?= e($initials ?: 'A'); ?>
            </div>

        </div>

    </header>

    <main class="page-content">

        <div class="settings-page">

            <div class="settings-heading">

                <div>

                    <h1>
                        System Settings
                    </h1>

                    <p>
                        Maintain the institutional profile and administrative configuration used by ASU-REMS.
                    </p>

                </div>

            </div>

            <?php if ($flash): ?>

                <div
                    class="alert alert-<?= e($flash['type']); ?>"
                >
                    <?= e($flash['message']); ?>
                </div>

            <?php endif; ?>

            <?php if ($errors): ?>

                <div class="alert alert-danger">
                    <?= e(
                        implode(
                            ' ',
                            $errors
                        )
                    ); ?>
                </div>

            <?php endif; ?>

            <section class="settings-card">

                <div class="settings-card-head">

                    <h2>
                        Institutional Profile
                    </h2>

                </div>

                <div class="settings-body">

                    <form method="post">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(generateCsrfToken()); ?>"
                        >

                        <div class="settings-grid">

                            <div class="settings-field">

                                <label>
                                    Institution Name
                                </label>

                                <input
                                    class="form-control"
                                    name="institution_name"
                                    required
                                    value="<?= e(
                                        $settings[
                                            'institution_name'
                                        ]
                                    ); ?>"
                                >

                            </div>

                            <div class="settings-field">

                                <label>
                                    System Name
                                </label>

                                <input
                                    class="form-control"
                                    name="system_name"
                                    required
                                    value="<?= e(
                                        $settings[
                                            'system_name'
                                        ]
                                    ); ?>"
                                >

                            </div>

                            <div class="settings-field">

                                <label>
                                    Office Name
                                </label>

                                <input
                                    class="form-control"
                                    name="office_name"
                                    required
                                    value="<?= e(
                                        $settings[
                                            'office_name'
                                        ]
                                    ); ?>"
                                >

                            </div>

                            <div class="settings-field">

                                <label>
                                    Academic Year
                                </label>

                                <input
                                    class="form-control"
                                    name="academic_year"
                                    value="<?= e(
                                        $settings[
                                            'academic_year'
                                        ]
                                    ); ?>"
                                    placeholder="2026-2027"
                                >

                            </div>

                            <div class="settings-field full">

                                <label>
                                    Support Email
                                </label>

                                <input
                                    class="form-control"
                                    type="email"
                                    name="support_email"
                                    value="<?= e(
                                        $settings[
                                            'support_email'
                                        ]
                                    ); ?>"
                                    placeholder="Optional"
                                >

                            </div>

                            <div class="settings-field full">

                                <label>
                                    Maintenance / Administrative Notice
                                </label>

                                <textarea
                                    class="form-control"
                                    name="maintenance_notice"
                                    placeholder="Optional internal notice"
                                ><?= e(
                                    $settings[
                                        'maintenance_notice'
                                    ]
                                ); ?></textarea>

                            </div>

                        </div>

                        <div class="settings-actions">

                            <button
                                class="btn btn-asu"
                                type="submit"
                            >
                                <i class="bi bi-check2-circle"></i>
                                Save Settings
                            </button>

                        </div>

                    </form>

                    <div class="settings-note">

                        <strong>
                            Scope:
                        </strong>

                        These settings are administrative system profile values.
                        Workflow decisions and research review actions remain
                        outside the Administrator role.

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>

<?php
require __DIR__ . '/../../includes/footer.php';
?>
