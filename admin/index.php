<?php



require_once __DIR__ . '/../includes/auth.php';



requireRole('admin');



$user = currentUser();



$pageTitle = 'Administrator Dashboard';

$activePage = 'admin_dashboard';





/*

|--------------------------------------------------------------------------

| COUNT HELPER

|--------------------------------------------------------------------------

*/



function adminCount(

    PDO $pdo,

    string $sql,

    array $params = []

): int {



    $stmt =

        $pdo->prepare($sql);



    $stmt->execute($params);



    return

        (int)$stmt->fetchColumn();

}





/*

|--------------------------------------------------------------------------

| ADMIN DASHBOARD COUNTS

|--------------------------------------------------------------------------

*/



$totalUsers =

    adminCount(

        $pdo,

        "SELECT COUNT(*) FROM users"

    );



$activeUsers =

    adminCount(

        $pdo,

        "SELECT COUNT(*) FROM users WHERE status = 'Active'"

    );



$totalResearch =

    adminCount(

        $pdo,

        "

            SELECT COUNT(*)

            FROM research_proposals

            WHERE COALESCE(is_archived, 0) = 0

        "

    );



$completedResearch =

    adminCount(

        $pdo,

        "

            SELECT COUNT(*)

            FROM research_proposals

            WHERE status = 'Completed'

              AND COALESCE(is_archived, 0) = 0

        "

    );



$totalExtension =

    adminCount(

        $pdo,

        "

            SELECT COUNT(*)

            FROM extension_programs

            WHERE status <> 'Draft'

        "

    );



$completedExtension =

    adminCount(

        $pdo,

        "

            SELECT COUNT(*)

            FROM extension_programs

            WHERE status = 'Completed'

        "

    );





/*

|--------------------------------------------------------------------------

| RECENT WORKFLOW ACTIVITY

|--------------------------------------------------------------------------

*/



$recentSql = "

    SELECT *

    FROM

    (

        SELECT

            'Research' AS module_name,

            rsh.research_id AS record_id,

            rp.research_title AS record_title,

            rp.manuscript_no AS reference_no,

            rsh.old_status,

            rsh.new_status,

            rsh.remarks,

            rsh.changed_at,

            u.first_name,

            u.middle_name,

            u.last_name



        FROM research_status_history rsh



        INNER JOIN research_proposals rp

            ON rp.research_id = rsh.research_id



        LEFT JOIN users u

            ON u.user_id = rsh.changed_by





        UNION ALL





        SELECT

            'Extension' AS module_name,

            esh.extension_id AS record_id,

            ep.program_title AS record_title,

            NULL AS reference_no,

            esh.old_status,

            esh.new_status,

            esh.remarks,

            esh.changed_at,

            u.first_name,

            u.middle_name,

            u.last_name



        FROM extension_status_history esh



        INNER JOIN extension_programs ep

            ON ep.extension_id = esh.extension_id



        LEFT JOIN users u

            ON u.user_id = esh.changed_by



    ) activity



    ORDER BY changed_at DESC



    LIMIT 8

";





$recentActivities = [];



try {



    $recentActivities =

        $pdo

            ->query($recentSql)

            ->fetchAll(PDO::FETCH_ASSOC);



} catch (Throwable $e) {



    $recentActivities = [];



    error_log(

        'Admin Dashboard Recent Activity Error: '

        . $e->getMessage()

    );

}





/*

|--------------------------------------------------------------------------

| USER DISPLAY

|--------------------------------------------------------------------------

*/



$firstName =

    $user['first_name']

    ?? 'Administrator';





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





/*

|--------------------------------------------------------------------------

| ADMIN TIME AGO

|--------------------------------------------------------------------------

*/



function adminDashboardTimeAgo(

    ?string $dateTime

): string {



    if (!$dateTime) {

        return '';

    }



    $timestamp =

        strtotime($dateTime);



    if (!$timestamp) {

        return '';

    }



    $seconds =

        time() - $timestamp;



    if ($seconds < 60) {

        return 'Just now';

    }



    if ($seconds < 3600) {

        return

            floor($seconds / 60)

            . ' mins ago';

    }



    if ($seconds < 86400) {

        return

            floor($seconds / 3600)

            . ' hours ago';

    }



    if ($seconds < 172800) {

        return '1 day ago';

    }



    return

        date(

            'M d, Y',

            $timestamp

        );

}





/*

|--------------------------------------------------------------------------

| STAT CARDS

|--------------------------------------------------------------------------

*/



$adminStatCards = [

    [

        'label' => 'Total Users',

        'sub' =>

            number_format($activeUsers)

            . ' active accounts',

        'count' => $totalUsers,

        'icon' => 'bi-people',

        'tone' => 'green',

        'url' =>

            BASE_URL

            . '/admin/users/index.php',

    ],

    [

        'label' => 'Research Records',

        'sub' =>

            number_format($completedResearch)

            . ' completed',

        'count' => $totalResearch,

        'icon' => 'bi-journal-text',

        'tone' => 'yellow',

        'url' =>

            BASE_URL

            . '/admin/records/research.php',

    ],

    [

        'label' => 'Extension Records',

        'sub' =>

            number_format($completedExtension)

            . ' completed',

        'count' => $totalExtension,

        'icon' => 'bi-megaphone',

        'tone' => 'green',

        'url' =>

            BASE_URL

            . '/admin/records/extension.php',

    ],

    [

        'label' => 'System Status',

        'sub' => 'Database-backed system',

        'count_text' => 'Online',

        'icon' => 'bi-database-check',

        'tone' => 'yellow',

        'url' =>

            BASE_URL

            . '/admin/maintenance/index.php',

    ],

];





/*

|--------------------------------------------------------------------------

| ADMINISTRATION MODULES

|--------------------------------------------------------------------------

*/



$adminModules = [

    [

        'label' => 'User Management',

        'sub' => 'Create, edit and activate accounts.',

        'icon' => 'bi-person-gear',

        'tone' => 'green',

        'url' =>

            BASE_URL

            . '/admin/users/index.php',

    ],

    [

        'label' => 'Master Data',

        'sub' => 'Manage colleges and departments.',

        'icon' => 'bi-diagram-3',

        'tone' => 'yellow',

        'url' =>

            BASE_URL

            . '/admin/master_data/index.php',

    ],

    [

        'label' => 'Research Records',

        'sub' => 'Institutional view-only research records.',

        'icon' => 'bi-journal-check',

        'tone' => 'green',

        'url' =>

            BASE_URL

            . '/admin/records/research.php',

    ],

    [

        'label' => 'Extension Records',

        'sub' => 'Institutional view-only extension records.',

        'icon' => 'bi-collection',

        'tone' => 'yellow',

        'url' =>

            BASE_URL

            . '/admin/records/extension.php',

    ],

    [

        'label' => 'Reports & Analytics',

        'sub' => 'Generate institutional summaries.',

        'icon' => 'bi-file-earmark-bar-graph',

        'tone' => 'green',

        'url' =>

            BASE_URL

            . '/reports/index.php',

    ],

    [

        'label' => 'Activity Logs',

        'sub' => 'Review research and extension workflow history.',

        'icon' => 'bi-clock-history',

        'tone' => 'yellow',

        'url' =>

            BASE_URL

            . '/activity_logs/index.php',

    ],

    [

        'label' => 'System Settings',

        'sub' => 'Maintain institutional system profile.',

        'icon' => 'bi-gear',

        'tone' => 'green',

        'url' =>

            BASE_URL

            . '/admin/settings/index.php',

    ],

    [

        'label' => 'Backup & Maintenance',

        'sub' => 'Check system health and download SQL backups.',

        'icon' => 'bi-database-down',

        'tone' => 'yellow',

        'url' =>

            BASE_URL

            . '/admin/maintenance/index.php',

    ],

    [

        'label' => 'Notifications',

        'sub' => 'Review system notifications.',

        'icon' => 'bi-bell',

        'tone' => 'green',

        'url' =>

            BASE_URL

            . '/notifications/index.php',

    ],

];





require __DIR__ . '/../includes/header.php';

require __DIR__ . '/../includes/sidebar.php';



?>



<style>



:root {

    --asu-green: #007a33;

    --asu-green-dark: #005c26;

    --asu-green-deep: #003f1a;

    --asu-yellow: #ffd51f;

    --asu-orange: #ff6a00;

    --asu-white: #ffffff;

    --asu-black: #101810;

    --asu-muted: #657068;

    --asu-line: rgba(0, 122, 51, .16);

    --asu-soft: #f5faf6;

}





/* =========================================================

   PAGE

========================================================= */



.main-page {

    background:

        linear-gradient(

            135deg,

            #dff1e3 0%,

            #e8f0e3 34%,

            #f1eee2 68%,

            #f4e6d5 100%

        );

}



.page-content.admin-dashboard-page {

    padding: 24px 28px 28px;

    background:

        radial-gradient(

            circle at 88% 4%,

            rgba(255, 213, 31, .10),

            transparent 26%

        ),

        radial-gradient(

            circle at 10% 8%,

            rgba(0, 122, 51, .08),

            transparent 28%

        ),

        linear-gradient(

            135deg,

            #dff1e3 0%,

            #e8f0e3 34%,

            #f1eee2 68%,

            #f4e6d5 100%

        );

}



.admin-dashboard-shell {

    max-width: 1320px;

    margin: 0 auto;

}





/* =========================================================

   TOP BAR

========================================================= */



.asu-dashboard-topbar {

    min-height: 76px;

    padding: 0 30px;

    border-bottom:

        1px solid

        rgba(0, 122, 51, .12);

    background: #ffffff;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    box-shadow:

        0 10px 26px

        rgba(16, 24, 16, .035);

}



.asu-dashboard-brandline {

    display: flex;

    align-items: center;

    gap: 14px;

}



.asu-dashboard-brandline::before {

    content: '';

    width: 3px;

    height: 38px;

    border-radius: 999px;

    background: var(--asu-green);

}



.asu-dashboard-brandline strong {

    display: block;

    color: var(--asu-black);

    font-size: 17px;

    font-weight: 800;

    letter-spacing: -.015em;

}



.asu-dashboard-brandline span {

    display: block;

    margin-top: 2px;

    color: var(--asu-muted);

    font-size: 13px;

}



.asu-dashboard-actions {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 12px;

}



.dashboard-user-mini {

    display: flex;

    align-items: center;

    gap: 12px;

}



.dashboard-user-copy {

    text-align: right;

}



.dashboard-user-copy strong {

    display: block;

    color: var(--asu-black);

    font-size: 15px;

    line-height: 1.1;

}



.dashboard-user-copy span {

    display: block;

    margin-top: 4px;

    color: var(--asu-muted);

    font-size: 12px;

}



.dashboard-avatar {

    width: 46px;

    height: 46px;

    border-radius: 50%;

    background: var(--asu-yellow);

    color: var(--asu-green-deep);

    display: inline-flex;

    align-items: center;

    justify-content: center;

    font-weight: 900;

    box-shadow:

        0 8px 18px

        rgba(255, 213, 31, .25);

}





/* =========================================================

   HERO

========================================================= */



.dashboard-hero {

    position: relative;

    overflow: hidden;

    min-height: 140px;

    padding: 25px 32px;

    border-radius: 18px;

    background:

        linear-gradient(

            112deg,

            rgba(0, 64, 28, .98),

            rgba(0, 122, 51, .96)

        ),

        radial-gradient(

            circle at 80% 0%,

            rgba(255, 213, 31, .25),

            transparent 26%

        );

    color: #ffffff;

    box-shadow:

        0 18px 42px

        rgba(0, 90, 40, .18);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 22px;

}



.dashboard-hero::after {

    content: '';

    position: absolute;

    right: -90px;

    top: -80px;

    width: 420px;

    height: 280px;

    border-radius: 42px;

    transform: rotate(-10deg);

    background:

        linear-gradient(

            135deg,

            rgba(255, 213, 31, .16),

            rgba(255, 255, 255, .035)

        );

}



.hero-copy,

.hero-mark {

    position: relative;

    z-index: 1;

}



.hero-copy .greeting {

    margin: 0 0 6px;

    font-size: 21px;

    font-weight: 500;

    opacity: .95;

}



.hero-copy h1 {

    margin: 0 0 7px;

    font-size: 34px;

    font-weight: 900;

    letter-spacing: -.035em;

}



.hero-copy .role {

    margin: 0;

    font-size: 16px;

    opacity: .96;

}



.hero-copy .tagline {

    margin: 0;

    font-size: 14px;

    opacity: .82;

}



.hero-mark {

    min-width: 330px;

    text-align: right;

}



.hero-mark strong {

    display: block;

    color: #ffffff;

    font-size: 13px;

    font-weight: 600;

    letter-spacing: .42em;

}



.hero-mark span {

    display: inline-block;

    margin-top: 10px;

    padding-top: 9px;

    border-top:

        2px solid

        var(--asu-yellow);

    color:

        rgba(255, 255, 255, .82);

    font-size: 11px;

    letter-spacing: .42em;

}





/* =========================================================

   STAT CARDS

========================================================= */



.dashboard-stat-grid {

    display: grid;

    grid-template-columns:

        repeat(4, minmax(0, 1fr));

    gap: 16px;

    margin-top: 20px;

    align-items: stretch;

}



.dashboard-stat-card {

    position: relative;

    overflow: hidden;

    min-height: 145px;

    padding: 18px 18px 24px;

    border:

        1px solid

        rgba(0, 122, 51, .14);

    border-radius: 16px;

    background: #ffffff;

    color: var(--asu-black);

    text-decoration: none;

    display: grid;

    grid-template-columns:

        58px minmax(0, 1fr);

    align-items: center;

    gap: 16px;

    box-shadow:

        0 14px 35px

        rgba(16, 24, 16, .055);

    transition:

        transform .20s ease,

        box-shadow .20s ease,

        border-color .20s ease;

}



.dashboard-stat-card:hover {

    transform: translateY(-3px);

    border-color:

        rgba(0, 122, 51, .24);

    box-shadow:

        0 20px 42px

        rgba(16, 24, 16, .085);

}



.dashboard-stat-card::after {

    content: '';

    position: absolute;

    z-index: 0;

    left: 0;

    right: 0;

    bottom: -34px;

    height: 54px;

    border-radius: 50% 50% 0 0;

    opacity: .20;

    pointer-events: none;

}



.dashboard-stat-card.tone-green::after {

    background: var(--asu-green);

}



.dashboard-stat-card.tone-yellow::after {

    background: var(--asu-yellow);

    opacity: .32;

}



.stat-badge-icon,

.stat-number-wrap {

    position: relative;

    z-index: 2;

}



.stat-badge-icon {

    width: 58px;

    height: 58px;

    border-radius: 16px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;

}



.tone-green .stat-badge-icon {

    background:

        linear-gradient(

            135deg,

            var(--asu-green),

            var(--asu-green-dark)

        );

    color: #ffffff;

    box-shadow:

        0 12px 20px

        rgba(0, 122, 51, .25);

}



.tone-yellow .stat-badge-icon {

    background:

        linear-gradient(

            135deg,

            var(--asu-yellow),

            #f0bd00

        );

    color: var(--asu-green-deep);

    box-shadow:

        0 12px 20px

        rgba(255, 213, 31, .30);

}



.stat-number-wrap {

    min-width: 0;

    padding-bottom: 8px;

}



.stat-number-wrap strong {

    display: block;

    margin-bottom: 6px;

    color: var(--asu-black);

    font-size:

        clamp(28px, 2.2vw, 34px);

    line-height: .95;

    font-weight: 900;

    letter-spacing: -.04em;

}



.stat-number-wrap span {

    display: block;

    max-width: 100%;

    color: var(--asu-black);

    font-size:

        clamp(13px, 1.05vw, 15px);

    line-height: 1.2;

    font-weight: 850;

    overflow-wrap: break-word;

}



.stat-number-wrap small {

    display: block;

    max-width: 100%;

    margin-top: 6px;

    padding-right: 2px;

    color: #4e5f54;

    font-size:

        clamp(11px, .9vw, 12px);

    line-height: 1.25;

    overflow-wrap: break-word;

}





/* =========================================================

   TWO-COLUMN PANELS

========================================================= */



.dashboard-two-column {

    display: grid;

    grid-template-columns:

        minmax(0, 1.18fr)

        minmax(380px, .82fr);

    gap: 20px;

    margin-top: 20px;

    align-items: start;

}



.dashboard-panel {

    overflow: hidden;

    min-width: 0;

    height: 100%;

    border:

        1px solid

        rgba(0, 122, 51, .13);

    border-radius: 18px;

    background: #ffffff;

    box-shadow:

        0 14px 35px

        rgba(16, 24, 16, .055);

}



.dashboard-panel-header {

    min-height: 72px;

    padding: 15px 20px;

    border-bottom: 1px solid rgba(0, 122, 51, .08);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

}



.dashboard-panel-title {

    min-width: 0;

    display: flex;

    align-items: center;

    gap: 11px;

    margin: 0;

    color: var(--asu-black);

    font-size: 17px;

    line-height: 1.2;

    font-weight: 900;

    letter-spacing: -.02em;

    white-space: nowrap;

}



.panel-icon-soft {

    width: 40px;

    height: 40px;

    flex: 0 0 40px;

    border-radius: 13px;

    background:

        rgba(0, 122, 51, .09);

    color: var(--asu-green);

    display: inline-flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

}



.panel-link-modern {

    flex: 0 0 auto;

    padding: 8px 11px;

    border:

        1px solid

        rgba(0, 122, 51, .13);

    border-radius: 11px;

    color: var(--asu-green-dark);

    background: #fbfefc;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 800;

    white-space: nowrap;

}



.panel-link-modern:hover {

    color: var(--asu-green-dark);

    border-color:

        rgba(0, 122, 51, .24);

    background:

        rgba(0, 122, 51, .05);

}







.admin-panel-caption {

    flex: 0 0 auto;

    color: #7b877f;

    font-size: 10px;

    font-weight: 750;

    letter-spacing: .04em;

    text-transform: uppercase;

}



/* =========================================================

   ADMIN MODULE GRID

========================================================= */



.admin-module-grid {

    display: grid;

    grid-template-columns:

        repeat(3, minmax(0, 1fr));

    gap: 12px;

    padding: 18px 20px 20px;

}



.admin-module-card {

    min-width: 0;

    min-height: 126px;

    display: flex;

    flex-direction: column;

    align-items: flex-start;

    justify-content: flex-start;

    gap: 10px;

    padding: 15px 14px;

    border:

        1px solid

        rgba(0, 122, 51, .10);

    border-radius: 15px;

    background:

        linear-gradient(

            180deg,

            #ffffff 0%,

            #fbfdfb 100%

        );

    color: var(--asu-black);

    text-decoration: none;

    transition:

        transform .18s ease,

        box-shadow .18s ease,

        border-color .18s ease,

        background .18s ease;

}



.admin-module-card:hover {

    transform: translateY(-2px);

    color: var(--asu-black);

    border-color:

        rgba(0, 122, 51, .24);

    background: #ffffff;

    box-shadow:

        0 12px 26px

        rgba(16, 24, 16, .06);

}



.module-icon {

    width: 42px;

    height: 42px;

    flex: 0 0 42px;

    border-radius: 13px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

}



.module-icon.tone-green {

    background:

        rgba(0, 122, 51, .10);

    color: var(--asu-green);

}



.module-icon.tone-yellow {

    background:

        rgba(255, 213, 31, .22);

    color: #997500;

}



.module-copy {

    min-width: 0;

}



.module-copy strong {

    display: block;

    color: var(--asu-black);

    font-size: 12.5px;

    line-height: 1.25;

    font-weight: 850;

}



.module-copy span {

    display: block;

    margin-top: 4px;

    color: var(--asu-muted);

    font-size: 10.5px;

    line-height: 1.35;

}



.module-arrow {

    display: none;

}





/* =========================================================

   RECENT ACTIVITY

========================================================= */



.activity-list-modern {

    padding: 6px 20px 18px;

}



.activity-row-modern {

    display: grid;

    grid-template-columns:

        14px minmax(0, 1fr) auto;

    gap: 12px;

    padding: 14px 0;

    border-top:

        1px solid

        rgba(0, 122, 51, .10);

    align-items: start;

}



.activity-row-modern:first-child {

    border-top: 0;

}



.activity-dot-modern {

    width: 12px;

    height: 12px;

    margin-top: 4px;

    border-radius: 50%;

    background: var(--asu-green);

}



.activity-dot-modern.yellow {

    background: var(--asu-yellow);

    box-shadow:

        inset 0 0 0 1px

        rgba(0, 0, 0, .07);

}



.activity-copy-modern {

    min-width: 0;

}



.activity-copy-modern strong {

    display: block;

    color: var(--asu-black);

    font-size: 13px;

    font-weight: 850;

}



.activity-copy-modern span {

    display: block;

    margin-top: 3px;

    color: var(--asu-muted);

    font-size: 11px;

    line-height: 1.4;

    overflow-wrap: anywhere;

}



.activity-time-modern {

    white-space: nowrap;

    color: var(--asu-muted);

    font-size: 11px;

}





/* =========================================================

   EMPTY STATE

========================================================= */



.dashboard-empty-modern {

    min-height: 322px;

    padding: 34px 24px;

    color: var(--asu-muted);

    text-align: center;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

}



.dashboard-empty-modern i {

    display: inline-flex;

    width: 48px;

    height: 48px;

    align-items: center;

    justify-content: center;

    margin-bottom: 10px;

    border-radius: 50%;

    background:

        rgba(0, 122, 51, .08);

    color: var(--asu-green);

    font-size: 22px;

}





/* =========================================================

   ADMIN NOTE

========================================================= */



.admin-role-note {

    margin-top: 18px;

    padding: 15px 18px;

    border:

        1px solid

        rgba(0, 122, 51, .13);

    border-radius: 14px;

    background: #ffffff;

    color: var(--asu-muted);

    font-size: 11px;

    line-height: 1.6;

    box-shadow:

        0 10px 26px

        rgba(16, 24, 16, .035);

}



.admin-role-note strong {

    color: var(--asu-black);

}





/* =========================================================

   FOOTER

========================================================= */



.dashboard-footer-clean {

    padding: 20px 0 0;

    color:

        rgba(16, 24, 16, .55);

    text-align: center;

    font-size: 12px;

}





/* =========================================================

   MOTION

========================================================= */



@keyframes asuFadeUp {



    from {

        opacity: 0;

        transform:

            translateY(8px);

    }



    to {

        opacity: 1;

        transform:

            translateY(0);

    }

}



@media (

    prefers-reduced-motion:

    no-preference

) {



    .dashboard-hero,

    .dashboard-stat-card,

    .dashboard-panel {

        animation:

            asuFadeUp

            .36s ease both;

    }



    .dashboard-stat-card:nth-child(2) {

        animation-delay: .04s;

    }



    .dashboard-stat-card:nth-child(3) {

        animation-delay: .08s;

    }



    .dashboard-stat-card:nth-child(4) {

        animation-delay: .12s;

    }

}





/* =========================================================

   RESPONSIVE

========================================================= */



@media (max-width: 1280px) {



    .admin-module-grid {

        grid-template-columns:

            repeat(2, minmax(0, 1fr));

    }

}





@media (max-width: 1180px) {



    .dashboard-stat-grid {

        grid-template-columns:

            repeat(

                2,

                minmax(0, 1fr)

            );

    }



    .dashboard-two-column {

        grid-template-columns: 1fr;

    }



    .hero-mark {

        display: none;

    }

}





@media (max-width: 760px) {



    .page-content.admin-dashboard-page {

        padding: 18px 14px;

    }



    .asu-dashboard-topbar {

        padding: 0 16px;

    }



    .dashboard-user-copy {

        display: none;

    }



    .dashboard-hero {

        padding: 24px 20px;

    }



    .hero-copy h1 {

        font-size: 27px;

    }



    .admin-module-grid {

        grid-template-columns: 1fr;

    }



    .dashboard-panel-title {

        white-space: normal;

    }



    .activity-row-modern {

        grid-template-columns:

            14px minmax(0, 1fr);

    }



    .activity-time-modern {

        grid-column: 2;

    }

}





@media (max-width: 640px) {



    .dashboard-stat-grid {

        grid-template-columns: 1fr;

    }

}







/* =========================================================

   ADMIN DASHBOARD FINAL POLISH

========================================================= */



.dashboard-stat-card,

.dashboard-panel {

    backdrop-filter: blur(2px);

}



.dashboard-panel-header {

    background: rgba(255, 255, 255, .72);

}



.admin-module-card {

    background:

        linear-gradient(

            180deg,

            rgba(255,255,255,.96) 0%,

            rgba(250,252,250,.92) 100%

        );

}



@media (max-width: 1180px) {

    .dashboard-two-column {

        grid-template-columns: 1fr;

        gap: 18px;

    }

}



@media (max-width: 760px) {

    .page-content.admin-dashboard-page {

        padding: 18px 14px 24px;

    }



    .dashboard-two-column {

        margin-top: 16px;

    }

}



</style>





<div class="main-page">





    <!-- =====================================================

         TOPBAR

    ====================================================== -->



    <header class="asu-dashboard-topbar">



        <div class="asu-dashboard-brandline">



            <div>



                <strong>

                    Research &amp; Extension Management System

                </strong>



                <span>

                    Aklan State University

                </span>



            </div>



        </div>





        <div class="asu-dashboard-actions">



            <div class="dashboard-user-mini">



                <div class="dashboard-user-copy">



                    <strong>

                        <?= e(fullName($user)); ?>

                    </strong>



                    <span>

                        System Administrator

                    </span>



                </div>





                <div class="dashboard-avatar">

                    <?= e($initials ?: 'A'); ?>

                </div>



            </div>



        </div>



    </header>





    <!-- =====================================================

         CONTENT

    ====================================================== -->



    <main class="page-content admin-dashboard-page">



        <div class="admin-dashboard-shell">





            <!-- =================================================

                 HERO

            ================================================== -->



            <section class="dashboard-hero">



                <div class="hero-copy">



                    <p class="greeting">

                        Welcome,

                    </p>



                    <h1>

                        <?= e($firstName); ?>!

                    </h1>



                    <p class="role">

                        System Administrator

                    </p>



                </div>





                <div class="hero-mark">



                    <strong>

                        AKLAN STATE UNIVERSITY

                    </strong>



                    <span>

                        TRUTH · SERVICE · EXCELLENCE

                    </span>



                </div>



            </section>





            <!-- =================================================

                 STAT CARDS

            ================================================== -->



            <section class="dashboard-stat-grid">



                <?php foreach ($adminStatCards as $card): ?>



                    <a

                        href="<?= e($card['url']); ?>"

                        class="dashboard-stat-card tone-<?= e($card['tone']); ?><?= isset($card['count_text']) ? ' is-text-value' : ''; ?>"

                    >



                        <div class="stat-badge-icon">



                            <i class="bi <?= e($card['icon']); ?>"></i>



                        </div>





                        <div class="stat-number-wrap">



                            <strong>

                                <?php if (isset($card['count_text'])): ?>



                                    <?= e($card['count_text']); ?>



                                <?php else: ?>



                                    <?= number_format((int)$card['count']); ?>



                                <?php endif; ?>

                            </strong>



                            <span>

                                <?= e($card['label']); ?>

                            </span>



                            <small>

                                <?= e($card['sub']); ?>

                            </small>



                        </div>



                    </a>



                <?php endforeach; ?>



            </section>





            <!-- =================================================

                 ADMIN MODULES + ACTIVITY

            ================================================== -->



            <section class="dashboard-two-column">





                <!-- ADMIN MODULES -->



                <div class="dashboard-panel">



                    <div class="dashboard-panel-header">



                        <h2 class="dashboard-panel-title">



                            <span class="panel-icon-soft">



                                <i class="bi bi-grid"></i>



                            </span>



                            Administration Modules



                        </h2>





                        <span class="admin-panel-caption">

                            Quick access

                        </span>



                    </div>





                    <div class="admin-module-grid">



                        <?php foreach ($adminModules as $module): ?>



                            <a

                                href="<?= e($module['url']); ?>"

                                class="admin-module-card"

                            >



                                <span

                                    class="module-icon tone-<?= e($module['tone']); ?>"

                                >



                                    <i class="bi <?= e($module['icon']); ?>"></i>



                                </span>





                                <span class="module-copy">



                                    <strong>

                                        <?= e($module['label']); ?>

                                    </strong>



                                    <span>

                                        <?= e($module['sub']); ?>

                                    </span>



                                </span>





                                <span class="module-arrow">



                                    <i class="bi bi-chevron-right"></i>



                                </span>



                            </a>



                        <?php endforeach; ?>



                    </div>



                </div>





                <!-- RECENT ACTIVITY -->



                <div class="dashboard-panel">



                    <div class="dashboard-panel-header">



                        <h2 class="dashboard-panel-title">



                            <span class="panel-icon-soft">



                                <i class="bi bi-clock-history"></i>



                            </span>



                            Recent Workflow Activity



                        </h2>





                        <a

                            href="<?= BASE_URL; ?>/activity_logs/index.php"

                            class="panel-link-modern"

                        >

                            View All



                            <i class="bi bi-arrow-right"></i>

                        </a>



                    </div>





                    <?php if (!empty($recentActivities)): ?>



                        <div class="activity-list-modern">



                            <?php foreach ($recentActivities as $activity): ?>



                                <?php



                                $actor =

                                    trim(

                                        (string)($activity['first_name'] ?? '')

                                        . ' '

                                        . (string)($activity['middle_name'] ?? '')

                                        . ' '

                                        . (string)($activity['last_name'] ?? '')

                                    );



                                if ($actor === '') {

                                    $actor = 'System user';

                                }



                                $moduleName =

                                    (string)(

                                        $activity['module_name']

                                        ?? 'System'

                                    );



                                $reference =

                                    trim(

                                        (string)(

                                            $activity['reference_no']

                                            ?? ''

                                        )

                                    );



                                if ($reference === '') {

                                    $reference =

                                        (string)(

                                            $activity['record_title']

                                            ?? 'Record'

                                        );

                                }



                                $dotClass =

                                    $moduleName === 'Extension'

                                        ? 'yellow'

                                        : '';



                                ?>



                                <div class="activity-row-modern">



                                    <span

                                        class="activity-dot-modern <?= e($dotClass); ?>"

                                    ></span>





                                    <span class="activity-copy-modern">



                                        <strong>

                                            <?= e($moduleName); ?>

                                            ·

                                            <?= e(

                                                (string)(

                                                    $activity['new_status']

                                                    ?? 'Updated'

                                                )

                                            ); ?>

                                        </strong>



                                        <span>

                                            <?= e($actor); ?>

                                            ·

                                            <?= e($reference); ?>

                                        </span>



                                    </span>





                                    <span class="activity-time-modern">



                                        <?= e(

                                            adminDashboardTimeAgo(

                                                $activity['changed_at']

                                                ?? null

                                            )

                                        ); ?>



                                    </span>



                                </div>



                            <?php endforeach; ?>



                        </div>



                    <?php else: ?>



                        <div class="dashboard-empty-modern">



                            <i class="bi bi-inbox"></i>



                            <p class="mb-0">

                                No workflow activity has been recorded yet.

                            </p>



                        </div>



                    <?php endif; ?>



                </div>



            </section>

</div>



    </main>



</div>





<?php



require __DIR__ . '/../includes/footer.php';



?>





<style>

/* =========================================================

   ASU-REMS TYPOGRAPHY

   Primary: Century Gothic

   Fallback: sans-serif

========================================================= */



.main-page,

.main-page button,

.main-page input,

.main-page select,

.main-page textarea,

.main-page a {

    font-family: "Century Gothic", sans-serif !important;

}

</style>





<style>

/* =========================================================

   LANDSCAPE ADMIN DASHBOARD SECTIONS

   Administration Modules: 3 cards per row

   Recent Workflow Activity: full width below

========================================================= */



.dashboard-two-column {

    display: grid !important;

    grid-template-columns: 1fr !important;

    gap: 20px !important;

    margin-top: 20px !important;

    align-items: stretch !important;

}



.dashboard-panel {

    width: 100% !important;

}



/* Administration Modules — 3 x 3 on desktop */

.admin-module-grid {

    display: grid !important;

    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;

    gap: 14px !important;

    padding: 20px !important;

}



.admin-module-card {

    min-height: 118px !important;

    padding: 17px 16px !important;

}



/* Recent Workflow — full landscape width */

.activity-list-modern {

    padding: 6px 22px 18px !important;

}



.activity-row-modern {

    grid-template-columns: 14px minmax(0, 1fr) auto !important;

    gap: 14px !important;

    padding: 15px 0 !important;

}



/* Tablet */

@media (max-width: 980px) {

    .admin-module-grid {

        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;

    }

}



/* Mobile */

@media (max-width: 640px) {

    .admin-module-grid {

        grid-template-columns: 1fr !important;

        padding: 14px !important;

    }



    .activity-row-modern {

        grid-template-columns: 14px minmax(0, 1fr) !important;

    }



    .activity-time-modern {

        grid-column: 2 !important;

    }

}

</style>





<style>

/* =========================================================

   UNIFORM STAT CARD TYPOGRAPHY

========================================================= */



/* Count / value: 4, 29, 2, Online */

.stat-number-wrap strong {

    font-family: "Century Gothic", sans-serif !important;

    font-size: 32px !important;

    line-height: 1 !important;

    font-weight: 900 !important;

    letter-spacing: -.035em !important;

    margin-bottom: 7px !important;

    white-space: nowrap !important;

}



/* Card headings: Total Users, Research Records, etc. */

.stat-number-wrap span {

    font-family: "Century Gothic", sans-serif !important;

    font-size: 16px !important;

    line-height: 1.15 !important;

    font-weight: 700 !important;

    letter-spacing: -.01em !important;

}



/* Supporting text */

.stat-number-wrap small {

    font-family: "Century Gothic", sans-serif !important;

    font-size: 12px !important;

    line-height: 1.3 !important;

    margin-top: 7px !important;

}



/* Keep all stat cards visually equal */

.dashboard-stat-card {

    min-height: 145px !important;

}



.stat-number-wrap {

    display: flex !important;

    flex-direction: column !important;

    justify-content: center !important;

    min-height: 94px !important;

}



/* Slight reduction on narrower laptop screens only */

@media (max-width: 1180px) {

    .stat-number-wrap strong {

        font-size: 30px !important;

    }



    .stat-number-wrap span {

        font-size: 15px !important;

    }

}



@media (max-width: 640px) {

    .stat-number-wrap strong {

        font-size: 29px !important;

    }



    .stat-number-wrap span {

        font-size: 15px !important;

    }

}

</style>





<style>

/* =========================================================

   FINAL STAT VALUE SIZE — ALL EXACTLY THE SAME

========================================================= */



.dashboard-stat-card .stat-number-wrap > strong {

    font-family: "Century Gothic", sans-serif !important;

    font-size: 30px !important;

    line-height: 1 !important;

    font-weight: 900 !important;

    letter-spacing: -.03em !important;

    white-space: nowrap !important;

    margin-bottom: 7px !important;

}



/* Explicitly prevent text values such as "Online" from appearing larger */

.dashboard-stat-card .stat-number-wrap > strong * {

    font-size: inherit !important;

    line-height: inherit !important;

}



@media (max-width: 1180px) {

    .dashboard-stat-card .stat-number-wrap > strong {

        font-size: 29px !important;

    }

}



@media (max-width: 640px) {

    .dashboard-stat-card .stat-number-wrap > strong {

        font-size: 28px !important;

    }

}

</style>





<style>

/* =========================================================

   SYSTEM STATUS TEXT VALUE

   "Online" matches the System Status label style

========================================================= */



.dashboard-stat-card.is-text-value .stat-number-wrap > strong {

    font-family: "Century Gothic", sans-serif !important;

    font-size: 16px !important;

    line-height: 1.15 !important;

    font-weight: 700 !important;

    letter-spacing: -.01em !important;

    margin-bottom: 6px !important;

    white-space: nowrap !important;

}



/* Keep it matched with the card label on responsive screens */

@media (max-width: 1180px) {

    .dashboard-stat-card.is-text-value .stat-number-wrap > strong {

        font-size: 15px !important;

    }

}



@media (max-width: 640px) {

    .dashboard-stat-card.is-text-value .stat-number-wrap > strong {

        font-size: 15px !important;

    }

}

</style>
