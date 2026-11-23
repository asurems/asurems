<?php
/**
 * ASU-REMS — Role-scoped account directory.
 * File: profiles.php in the project ROOT, beside profile.php.
 * RPS may view Faculty/Researcher profiles only.
 * ESO may view Extensionist/Extension Coordinator profiles only.
 * Scoped account deletion with CSRF checks, confirmation, and dependency safeguards.
 * No editing of profile details or exposure of private registration fields.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile_avatar.php';
requireLogin();

$viewer = currentUser();
$viewerRole = (string)($viewer['role'] ?? '');
$allowedByOfficer = [
    'rps' => ['faculty', 'researcher'],
    'extension_service_office' => ['extension_coordinator', 'extensionist'],
];
if (!isset($allowedByOfficer[$viewerRole])) {
    http_response_code(403);
    exit('You do not have permission to view this directory.');
}
$allowedRoles = $allowedByOfficer[$viewerRole];
$isResearch = $viewerRole === 'rps';
$directoryTitle = $isResearch ? 'Researcher Profiles' : 'Extensionist Profiles';
$directoryDescription = $isResearch
    ? 'View the official account profiles of faculty and researchers.'
    : 'View the official account profiles of extension coordinators and extensionists.';
$pageTitle = $directoryTitle;
$activePage = 'profile_directory';
$base = rtrim(BASE_URL, '/');
$esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/* Only RPS and ESO can reach this page. Each action is checked server-side. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['rems_profiles_csrf'])) {
    $_SESSION['rems_profiles_csrf'] = bin2hex(random_bytes(32));
}
$directoryCsrfToken = (string)$_SESSION['rems_profiles_csrf'];

/*
 * Permanent deletion is allowed only for an eligible account without linked
 * system records. This protects submissions, approvals, and audit history.
 * Foreign-key relationships are discovered rather than assumed.
 */
function remsDirectoryHasLinkedRecords(PDO $pdo, int $accountId): bool
{
    $relationships = [];

    // All actual foreign keys pointing to users.user_id, even ON DELETE CASCADE.
    $foreignKeys = $pdo->query("
        SELECT DISTINCT TABLE_NAME, COLUMN_NAME
          FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND REFERENCED_TABLE_SCHEMA = DATABASE()
           AND REFERENCED_TABLE_NAME = 'users'
           AND REFERENCED_COLUMN_NAME = 'user_id'
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($foreignKeys as $item) {
        $relationships[$item['TABLE_NAME'] . '.' . $item['COLUMN_NAME']] =
            [$item['TABLE_NAME'], $item['COLUMN_NAME']];
    }

    // Guard core project tables even if the local schema has no foreign keys.
    $coreLinks = $pdo->query("
        SELECT TABLE_NAME, COLUMN_NAME
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND ((TABLE_NAME = 'research_proposals' AND COLUMN_NAME = 'researcher_id')
             OR (TABLE_NAME = 'extension_programs' AND COLUMN_NAME = 'coordinator_id'))
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($coreLinks as $item) {
        $relationships[$item['TABLE_NAME'] . '.' . $item['COLUMN_NAME']] =
            [$item['TABLE_NAME'], $item['COLUMN_NAME']];
    }

    foreach ($relationships as [$table, $column]) {
        // Information-schema names must still be quoted safely as identifiers.
        $qTable = '`' . str_replace('`', '``', (string)$table) . '`';
        $qColumn = '`' . str_replace('`', '``', (string)$column) . '`';
        $stmt = $pdo->prepare('SELECT 1 FROM ' . $qTable . ' WHERE ' . $qColumn . ' = ? LIMIT 1');
        $stmt->execute([$accountId]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
    }
    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($directoryCsrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }

    $deleteIdText = (string)($_POST['delete_user_id'] ?? '');
    $deleteId = ctype_digit($deleteIdText) ? (int)$deleteIdText : 0;
    $viewerId = (int)($viewer['user_id'] ?? 0);
    $flashType = 'error';
    $flashText = 'The account could not be deleted.';

    if (($_POST['action'] ?? '') === 'delete_account' && $deleteId > 0 && $deleteId !== $viewerId) {
        try {
            $pdo->beginTransaction();
            $rolesSql = implode(', ', array_fill(0, count($allowedRoles), '?'));
            // Re-check the target role while locking the account row.
            $check = $pdo->prepare('SELECT user_id, role FROM users WHERE user_id = ? AND role IN ('
                . $rolesSql . ') FOR UPDATE');
            $check->execute(array_merge([$deleteId], $allowedRoles));
            $target = $check->fetch(PDO::FETCH_ASSOC);

            if ($target) {
                if (remsDirectoryHasLinkedRecords($pdo, $deleteId)) {
                    $flashText = 'Cannot delete this user because linked system records must be preserved. Contact the administrator.';
                } else {
                    $remove = $pdo->prepare('DELETE FROM users WHERE user_id = ? AND role IN ('
                        . $rolesSql . ')');
                    $remove->execute(array_merge([$deleteId], $allowedRoles));
                    if ($remove->rowCount() === 1) {
                        $flashType = 'success';
                        $flashText = 'User account deleted successfully.';
                    }
                }
            } else {
                $flashText = 'Account not found or not authorized for this office.';
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ASU-REMS profile deletion: ' . $error->getMessage());
            $flashText = 'Unable to delete this account. It may be linked to protected records.';
        }
    }

    $_SESSION['rems_directory_flash'] = ['type' => $flashType, 'text' => $flashText];
    $returnQuery = [];
    if (isset($_POST['q']) && is_string($_POST['q']) && $_POST['q'] !== '') {
        $returnQuery['q'] = substr($_POST['q'], 0, 100);
    }
    $returnPage = filter_var($_POST['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    if ($returnPage !== false && $returnPage !== null) {
        $returnQuery['page'] = $returnPage;
    }
    $targetUrl = $base . '/profiles.php';
    if ($returnQuery) {
        $targetUrl .= '?' . http_build_query($returnQuery);
    }
    header('Location: ' . $targetUrl, true, 303);
    exit;
}

$directoryFlash = $_SESSION['rems_directory_flash'] ?? null;
unset($_SESSION['rems_directory_flash']);

// Only list specifically approved, non-sensitive user columns. Do not SELECT *.
try {
    $available = array_column($pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('user_id', $available, true) || !in_array('role', $available, true)) {
        throw new RuntimeException('User directory schema missing required fields.');
    }
    $wanted = ['user_id', 'role', 'first_name', 'middle_name', 'last_name', 'username',
               'email', 'status', 'created_at', 'profile_photo'];
    $parts = [];
    foreach ($wanted as $key) {
        $parts[] = in_array($key, $available, true)
            ? 'u.`' . $key . '` AS `' . $key . '`'
            : 'NULL AS `' . $key . '`';
    }
    $selection = implode(', ', $parts);
} catch (Throwable $error) {
    error_log('Profiles directory schema: ' . $error->getMessage());
    http_response_code(500);
    exit('Could not load the profile directory. Contact the administrator.');
}

$roleConditions = implode(',', array_fill(0, count($allowedRoles), '?'));
$rolePredicate = 'u.`role` IN (' . $roleConditions . ')';
$viewRaw = (string)($_GET['id'] ?? '');
$viewId = ctype_digit($viewRaw) ? (int)$viewRaw : 0;
$isDetail = $viewRaw !== '';
$selected = null;
$rows = [];
$total = 0;
$search = trim((string)($_GET['q'] ?? ''));
$search = substr($search, 0, 100);
$page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
$pageSize = 12;
$pages = 1;

try {
    if ($isDetail) {
        // Exact role check happens inside the SQL query, not just in the UI.
        $stmt = $pdo->prepare('SELECT ' . $selection . ' FROM `users` u WHERE u.`user_id` = ? AND '
            . $rolePredicate . ' LIMIT 1');
        $stmt->execute(array_merge([$viewId], $allowedRoles));
        $selected = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$selected) http_response_code(404);
    } else {
        $where = $rolePredicate;
        $parameters = $allowedRoles;
        if ($search !== '') {
            $likeColumns = array_values(array_intersect(['first_name', 'middle_name', 'last_name', 'email', 'username'], $available));
            if ($likeColumns) {
                $or = [];
                foreach ($likeColumns as $column) {
                    $or[] = 'u.`' . $column . '` LIKE ?';
                    $parameters[] = '%' . $search . '%';
                }
                $where .= ' AND (' . implode(' OR ', $or) . ')';
            }
        }
        $counter = $pdo->prepare('SELECT COUNT(*) FROM `users` u WHERE ' . $where);
        $counter->execute($parameters);
        $total = (int)$counter->fetchColumn();
        $pages = max(1, (int)ceil($total / $pageSize));
        $page = min($page, $pages);
        $offset = ($page - 1) * $pageSize;
        $order = in_array('last_name', $available, true) ? 'u.`last_name`, u.`first_name`, u.`user_id`'
            : 'u.`user_id` DESC';
        $stmt = $pdo->prepare('SELECT ' . $selection . ' FROM `users` u WHERE ' . $where
            . ' ORDER BY ' . $order . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset);
        $stmt->execute($parameters);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $error) {
    error_log('Profiles directory read failed: ' . $error->getMessage());
    http_response_code(500);
    exit('Could not load profiles. Please try again or contact the administrator.');
}

function remsDirectoryName(array $person): string
{
    $name = trim(implode(' ', array_filter([
        trim((string)($person['first_name'] ?? '')),
        trim((string)($person['middle_name'] ?? '')),
        trim((string)($person['last_name'] ?? '')),
    ], static fn($v) => $v !== '')));
    return $name !== '' ? $name : ((string)($person['username'] ?? '') ?: 'ASU-REMS User');
}
function remsDirectoryRole(string $role): string
{
    return match ($role) {
        'faculty' => 'Faculty / Researcher',
        'researcher' => 'Researcher',
        'extension_coordinator', 'extensionist' => 'Extensionist',
        default => 'Account',
    };
}
function remsDirectoryInitials(array $person): string
{
    $a = substr((string)($person['first_name'] ?? ''), 0, 1);
    $b = substr((string)($person['last_name'] ?? ''), 0, 1);
    return strtoupper($a . $b) ?: 'U';
}
function remsDirectoryPhoto(array $person, string $base): string
{
    $relative = (string)($person['profile_photo'] ?? '');
    if (preg_match('~\Auploads/profile_pictures/[a-f0-9]{32}\.(?:jpg|png|webp)\z~D', $relative)
        && is_file(__DIR__ . '/' . $relative)) {
        return $base . '/' . $relative;
    }
    return '';
}
function remsDirectoryAvatar(array $person, string $base, string $extraClass = ''): string
{
    $safe = static fn($text): string => htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $photo = remsDirectoryPhoto($person, $base);
    if ($photo !== '') {
        return '<span class="directory-avatar ' . $safe($extraClass) . '"><img src="' . $safe($photo)
            . '" alt="Profile picture of ' . $safe(remsDirectoryName($person)) . '" loading="lazy"></span>';
    }
    return '<span class="directory-avatar ' . $safe($extraClass) . '" aria-label="No profile picture">'
        . $safe(remsDirectoryInitials($person)) . '</span>';
}

$viewerInitials = remsDirectoryInitials($viewer);
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<style>
/* ASU-REMS profile directory — consistent with the Dashboard and My Profile. */
.directory-page,.directory-page *,.directory-topbar *{font-family:"Century Gothic","CenturyGothic",Arial,sans-serif!important}
.directory-page{padding:28px 32px 36px!important;min-height:calc(100vh - 76px);background:radial-gradient(circle at 90% 5%,rgba(255,213,31,.10),transparent 25%),radial-gradient(circle at 8% 7%,rgba(0,122,51,.07),transparent 27%),linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%)!important;color:#192c20}
.directory-shell{max-width:none;margin:0 auto}
.directory-topbar{display:flex!important;min-height:76px!important;height:76px!important;align-items:center!important;justify-content:space-between!important;padding:0 30px!important;background:white!important;border-bottom:1px solid #e2ebe3!important}
.directory-brandline,.directory-viewer{display:flex;align-items:center;gap:14px}.directory-brandcopy{padding-left:18px;border-left:3px solid #007a33}.directory-brandcopy strong{display:block;font-size:17px;font-weight:800;color:#101810}.directory-brandcopy span{display:block;font-size:12px;color:#657068;margin-top:4px}
.directory-viewer .topbar-user-text{text-align:right}.directory-viewer .user-avatar{width:46px!important;height:46px!important;min-width:46px!important;border-radius:50%!important;background:#ffd51f!important;color:#003f1a!important}
.directory-hero{position:relative;overflow:hidden;min-height:140px;margin-bottom:18px;padding:26px 32px;display:flex;align-items:center;justify-content:space-between;gap:18px;border-radius:18px;background:linear-gradient(112deg,#005c26 0%,#007a33 67%,#289638 100%);box-shadow:0 16px 32px rgba(0,90,40,.13);color:#fff}
.directory-hero::after{content:"";position:absolute;right:-30px;top:-110px;width:330px;height:275px;border-radius:48px;transform:rotate(-15deg);background:linear-gradient(135deg,rgba(255,213,31,.14),rgba(255,255,255,.05));pointer-events:none}.directory-hero>*{position:relative;z-index:1}
.directory-kicker{font-size:10px;font-weight:800;letter-spacing:.09em;color:#fce991}.directory-hero h1{margin:7px 0 6px;color:#fff!important;font-size:29px;font-weight:900}.directory-hero p{font-size:12px;color:rgba(255,255,255,.88);margin:0}
.directory-yellow,.directory-primary,.directory-outline,.directory-view,.directory-pagination a{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:0 15px;border-radius:10px;font-size:11px;font-weight:800;text-decoration:none!important;white-space:nowrap}
.directory-yellow{background:#ffd51f;color:#003f1a!important;border:1px solid #ffd51f}.directory-primary{border:1px solid #006b30;background:#006b30;color:#fff;cursor:pointer}.directory-primary:hover{background:#005225;color:#fff}.directory-outline{border:1px solid #cde2d3;background:#fff;color:#006b30!important}.directory-outline:hover{background:#f1f8f3}
.directory-panel{background:#fff;border:1px solid #dce8df;border-top:3px solid #007a33;border-radius:18px;overflow:hidden;box-shadow:0 15px 33px rgba(17,56,29,.055)}
.directory-panel-title{display:flex;align-items:center;gap:12px;min-height:82px;padding:16px 24px;border-bottom:1px solid #e8efe9}.directory-icon{width:40px;height:40px;min-width:40px;display:flex;align-items:center;justify-content:center;border-radius:11px;color:#007a33;background:#edf6ef;font-size:19px}.directory-panel-title h2{margin:0;color:#193323;font-size:15px;font-weight:800}.directory-panel-title p{margin:4px 0 0;color:#7a857d;font-size:11px}.directory-count{margin-left:auto;display:inline-flex;border:1px solid #d5e8da;background:#f1f8f3;color:#496e50;border-radius:99px;padding:6px 13px;font-size:10px;font-weight:800}
.directory-search{padding:18px 24px;border-bottom:1px solid #e8efe9}.directory-search label{display:block;font-size:10px;font-weight:800;margin-bottom:7px;color:#4f6557}.directory-search-row{display:flex;align-items:center;gap:10px}.directory-search-field{display:flex;align-items:center;gap:10px;flex:1;min-width:0;height:44px;padding:0 13px;border:1px solid #d7e3d9;border-radius:11px;color:#76897b}.directory-search-field input{width:100%;height:100%;border:0;outline:0;color:#203729;font-size:12px;background:transparent}.directory-search-field:focus-within{border-color:#73ac83;box-shadow:0 0 0 3px rgba(0,122,51,.07)}
.directory-table-wrap{width:100%;overflow-x:auto}.directory-table{width:100%;min-width:660px;border-collapse:collapse}.directory-table th{text-align:left;padding:15px 18px;font-size:9.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;background:#f7faf7;color:#526253}.directory-table th:last-child,.directory-table td:last-child{text-align:center}.directory-table td{border-top:1px solid #edf1ed;padding:15px 18px;font-size:11px;color:#415448;vertical-align:middle}.directory-table td:nth-child(3){overflow-wrap:anywhere}.directory-person{display:flex;align-items:center;gap:13px}.directory-person strong{font-size:12px;color:#173824}
.directory-avatar{display:inline-flex;align-items:center;justify-content:center;flex:0 0 42px;width:42px;height:42px;border-radius:50%;background:#ffd51f;color:#003f1a;font-weight:800;font-size:13px;overflow:hidden;border:2px solid #f1f7f1}.directory-avatar img{width:100%;height:100%;display:block;object-fit:cover}.directory-avatar-large{width:132px;height:132px;flex:0 0 132px;font-size:36px;border:5px solid #fff;outline:1px solid #dce9df;box-shadow:0 6px 19px rgba(0,63,26,.08)}
.directory-status{display:inline-flex;align-items:center;min-height:26px;padding:5px 11px;border:1px solid #dfe5e2;border-radius:99px;background:#f1f3f2;color:#607168;font-size:10px;font-weight:800}.directory-status.active{border-color:#cae6d0;background:#e4f3e8;color:#166335}.directory-view{min-height:34px;padding:0 12px;border:1px solid #cde2d3;background:#fff;color:#007a33!important}.directory-view{width:34px;height:34px;min-height:34px;padding:0;font-size:15px}.directory-view:hover{background:#eef7ef}.directory-view:focus-visible{outline:2px solid #007a33;outline-offset:2px}
.directory-pagination{display:flex;align-items:center;justify-content:flex-end;gap:12px;padding:16px 22px;border-top:1px solid #edf1ed;font-size:11px;color:#69766d}.directory-pagination a{min-height:34px;border:1px solid #cde2d3;color:#006b30!important}
.directory-actions{display:flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap}
.directory-delete-form{display:inline-flex;margin:0;padding:0}
.directory-delete{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid #f1cccc;border-radius:10px;background:#fff;color:#b4232c;cursor:pointer;font-size:15px}
.directory-delete:hover,.directory-delete:focus-visible{background:#fff0f0;border-color:#d96b72;color:#951a24}
.directory-delete:focus-visible{outline:2px solid #c03542;outline-offset:2px}
.directory-alert{display:flex;align-items:center;gap:10px;padding:13px 17px;margin-bottom:15px;border-radius:12px;border:1px solid #e7cccc;font-size:12px;font-weight:700;background:#fff3f3;color:#9d1d29}
.directory-alert-success{background:#ecf8ef;border-color:#bbdfc3;color:#185c30}

.directory-detail{display:flex;align-items:center;gap:25px;margin:25px 26px 0;padding:28px;border:1px solid #e4ede6;border-radius:14px;background:#f8fbf8}.directory-detail-copy h3{font-weight:800;font-size:22px;color:#123d25;margin:0 0 11px}.directory-detail-copy p{margin:11px 0 0;color:#738177;font-size:11px}.directory-role{display:inline-flex;align-items:center;gap:6px;border:1px solid #d8e8dc;border-radius:99px;background:#e9f4ec;padding:6px 12px;color:#17643b;font-size:11px;font-weight:800}.directory-readonly{margin-left:auto;display:inline-flex;align-items:center;gap:6px;color:#607967;font-size:10px;font-weight:800;white-space:nowrap}
.directory-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 22px;padding:26px}.directory-fact{padding:16px 17px;border:1px solid #e8efe9;border-radius:12px}.directory-fact span{display:block;margin-bottom:6px;color:#7b887e;font-size:10px}.directory-fact strong{display:block;overflow-wrap:anywhere;color:#1a3525;font-size:12px}.directory-privacy{display:flex;gap:9px;align-items:flex-start;margin:0 26px 25px;padding:13px 15px;border-radius:11px;background:#f6f9f6;color:#657769;font-size:10.5px;line-height:1.6}.directory-privacy i{color:#007a33;font-size:15px}
.directory-empty{display:flex;flex-direction:column;justify-content:center;align-items:center;gap:6px;min-height:205px;text-align:center;padding:24px}.directory-empty i{font-size:28px;color:#7ba18a}.directory-empty h2{margin:0;color:#284331;font-size:15px;font-weight:800}.directory-empty p{margin:4px 0 12px;color:#7b887e;font-size:11px}
.directory-page *{animation:none!important;transition:none!important}
@media(max-width:760px){.directory-page{padding:18px 14px 24px!important}.directory-topbar{padding:0 16px!important}.directory-brandcopy strong{font-size:13px}.directory-brandcopy span{font-size:10px}.directory-viewer .topbar-user-text{display:none}.directory-hero{min-height:110px;padding:21px;flex-wrap:wrap}.directory-hero h1{font-size:24px}.directory-search-row{flex-wrap:wrap}.directory-search-field{flex:1 1 100%}.directory-detail{margin:16px;padding:18px;flex-direction:column;align-items:flex-start}.directory-facts{padding:16px;grid-template-columns:1fr}.directory-readonly{margin-left:0}.directory-privacy{margin:0 16px 16px}.directory-panel-title{padding:15px 16px}.directory-search{padding:16px}}
/* Match the ASU-REMS Notifications deletion confirmation. */
.directory-confirm-overlay[hidden] { display: none !important; }
.directory-confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(22, 57, 33, .39);
    -webkit-backdrop-filter: blur(5px);
    backdrop-filter: blur(5px);
    font-family: "Century Gothic", "CenturyGothic", Arial, sans-serif;
}
.directory-confirm-dialog {
    box-sizing: border-box;
    width: min(100%, 476px);
    padding: 26px 26px 25px;
    border: 1px solid #e4ece6;
    border-radius: 19px;
    background: #fff;
    box-shadow: 0 18px 46px rgba(0, 48, 22, .12);
    color: #202c24;
    text-align: center;
}
.directory-confirm-symbol {
    width: 62px;
    height: 62px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    border-radius: 16px;
    background: #edf8f0;
    color: #007a33;
    font-size: 25px;
}
.directory-confirm-dialog h2 {
    margin: 0 0 8px;
    font-size: 18px;
    line-height: 1.35;
    font-weight: 800;
    color: #202e25;
}
.directory-confirm-dialog p {
    margin: 0 0 28px;
    color: #7c8980;
    font-size: 11px;
    line-height: 1.6;
}
.directory-confirm-dialog p strong { color: #637a6a; font-weight: 800; }
.directory-confirm-actions {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 10px;
}
.directory-confirm-actions button {
    width: 100%;
    height: 47px;
    border-radius: 11px;
    font-family: inherit;
    font-weight: 800;
    font-size: 11px;
    cursor: pointer;
}
.directory-confirm-cancel {
    color: #53675b;
    background: #fff;
    border: 1px solid #d8e5dc;
}
.directory-confirm-cancel:hover { background: #f4faf6; }
.directory-confirm-submit {
    color: #fff;
    background: #006b30;
    border: 1px solid #006b30;
    box-shadow: 0 9px 17px rgba(0, 107, 46, .12);
}
.directory-confirm-submit:hover { background: #005225; border-color: #005225; }
.directory-confirm-submit:disabled { opacity: .65; cursor: wait; }
.directory-confirm-actions button:focus-visible { outline: 3px solid #ffd51f; outline-offset: 2px; }
@media (max-width: 420px) {
    .directory-confirm-dialog { padding: 23px 18px; }
    .directory-confirm-actions { gap: 8px; }
}

/* ============================================================
   RPS / ESO PROFILES — ASU-REMS FOREST GREEN DARK MODE
   Visual changes only; account access/deletion and CSRF unchanged.
   ============================================================ */
html[data-rems-theme="dark"] body .main-page {
    background: #0b1d15 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] body .main-page .directory-page {
    background: linear-gradient(145deg, #0b1d15, #10271b 68%, #142f21) !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] body .main-page .directory-topbar {
    background: #11291e !important;
    border-bottom-color: #365843 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .directory-topbar .directory-brandcopy strong,
html[data-rems-theme="dark"] .directory-topbar .topbar-user-text strong {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] .directory-topbar .directory-brandcopy span,
html[data-rems-theme="dark"] .directory-topbar .topbar-user-text span {
    color: #bfd1c1 !important;
}
html[data-rems-theme="dark"] .directory-topbar .sidebar-toggle {
    color: #eaf4eb !important;
}
/* Green banner and yellow Back to Profiles button remain unchanged. */
html[data-rems-theme="dark"] .directory-page .directory-hero h1 {
    color: #ffffff !important;
}
html[data-rems-theme="dark"] .directory-page :is(.directory-panel,.directory-empty.directory-panel) {
    background: #142e22 !important;
    border-color: #41654e !important;
    border-top-color: #26a35e !important;
    box-shadow: 0 12px 28px rgba(0,0,0,.14) !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] .directory-page .directory-panel-title {
    background: #1b3b2b !important;
    border-bottom-color: #42644f !important;
}
html[data-rems-theme="dark"] .directory-page :is(.directory-panel-title h2,.directory-empty h2) {
    color: #f0f8ef !important;
}
html[data-rems-theme="dark"] .directory-page :is(.directory-panel-title p,.directory-empty p) {
    color: #bed1c2 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-icon {
    background: #2b563c !important;
    color: #b8ecc4 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-count,
html[data-rems-theme="dark"] .directory-page .directory-readonly {
    background: #274b35 !important;
    border-color: #54785d !important;
    color: #e0f1e1 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-search {
    background: #142e22 !important;
    border-bottom-color: #3b5c48 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-search label {
    color: #cbdece !important;
}
html[data-rems-theme="dark"] .directory-page .directory-search-field {
    background: #203e2e !important;
    border-color: #52745b !important;
    color: #b8dbbf !important;
}
html[data-rems-theme="dark"] .directory-page .directory-search-field input {
    background: transparent !important;
    color: #f0f8ef !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] .directory-page .directory-search-field input::placeholder {
    color: #b5cdbb !important;
    opacity: 1;
}
html[data-rems-theme="dark"] .directory-page .directory-outline,
html[data-rems-theme="dark"] .directory-page .directory-pagination a,
html[data-rems-theme="dark"] .directory-page .directory-view {
    background: #284937 !important;
    color: #e3f3e6 !important;
    border-color: #597961 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-outline:hover,
html[data-rems-theme="dark"] .directory-page .directory-pagination a:hover,
html[data-rems-theme="dark"] .directory-page .directory-view:hover {
    background: #345c42 !important;
    color: #fff !important;
}
html[data-rems-theme="dark"] .directory-page .directory-table {
    background: #142e22 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-table th {
    background: #224333 !important;
    color: #d8e8db !important;
    border-bottom-color: #4a6e54 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-table td {
    background: #142e22 !important;
    border-top-color: #3a5946 !important;
    color: #e0ece2 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-table tbody tr:nth-child(even) td {
    background: #193628 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-person strong {
    color: #f0f8ee !important;
}
html[data-rems-theme="dark"] .directory-page .directory-status {
    background: #284435 !important;
    border-color: #5b7864 !important;
    color: #d6e8d8 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-status.active {
    background: #205239 !important;
    border-color: #569b6b !important;
    color: #d6f5df !important;
}
html[data-rems-theme="dark"] .directory-page .directory-delete {
    background: #3d272a !important;
    border-color: #965964 !important;
    color: #ffc1c5 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-delete:hover {
    background: #543037 !important;
    color: #ffebec !important;
}
html[data-rems-theme="dark"] .directory-page .directory-pagination {
    border-top-color: #3c6149 !important;
    color: #c3d5c7 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-detail,
html[data-rems-theme="dark"] .directory-page .directory-fact {
    background: #1c382a !important;
    border-color: #3b5e48 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-detail-copy h3,
html[data-rems-theme="dark"] .directory-page .directory-fact strong {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] .directory-page :is(.directory-detail-copy p,.directory-fact span) {
    color: #bdcfbf !important;
}
html[data-rems-theme="dark"] .directory-page .directory-role {
    background: #26513a !important;
    border-color: #4d825c !important;
    color: #d6f6de !important;
}
html[data-rems-theme="dark"] .directory-page .directory-privacy {
    background: #203c2d !important;
    color: #c4d7c6 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-privacy i {
    color: #91dca3 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-alert-success {
    background: #1e4931 !important;
    border-color: #5a9970 !important;
    color: #dff4e2 !important;
}
html[data-rems-theme="dark"] .directory-page .directory-alert-error {
    background: #492b31 !important;
    border-color: #975665 !important;
    color: #ffdadf !important;
}
/* Confirmation is OUTSIDE .directory-page; scope to the theme root. */
html[data-rems-theme="dark"] .directory-confirm-overlay {
    background: rgba(0, 13, 7, .65) !important;
}
html[data-rems-theme="dark"] .directory-confirm-dialog {
    background: #173327 !important;
    border-color: #4a6b53 !important;
    box-shadow: 0 22px 55px rgba(0,0,0,.32) !important;
    color: #e9f3e9 !important;
}
html[data-rems-theme="dark"] .directory-confirm-dialog h2 {
    color: #f2f8f1 !important;
}
html[data-rems-theme="dark"] .directory-confirm-dialog p {
    color: #c1d2c5 !important;
}
html[data-rems-theme="dark"] .directory-confirm-dialog p strong {
    color: #e5f4e5 !important;
}
html[data-rems-theme="dark"] .directory-confirm-symbol {
    background: #2a5239 !important;
    color: #beecc8 !important;
}
html[data-rems-theme="dark"] .directory-confirm-cancel {
    background: #294737 !important;
    border-color: #5b7e62 !important;
    color: #eaf5eb !important;
}
html[data-rems-theme="dark"] .directory-confirm-cancel:hover {
    background: #375d45 !important;
}


/* Use the SAME topbar photo class and styling as dashboard.php.
   Dashboard uses the dashboard-avatar helper class and its original single
   soft shadow; do not add a yellow outline or a separate halo. */
.directory-topbar .directory-viewer .dashboard-avatar {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 46px !important;
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    padding: 0 !important;
    overflow: hidden !important;
    border: 0 !important;
    outline: none !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255, 213, 31, .25) !important;
    animation: none !important;
    transition: none !important;
}
.directory-topbar .directory-viewer .dashboard-avatar img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    border: 0 !important;
    outline: none !important;
    border-radius: 50% !important;
}
@media (max-width: 480px) {
    .directory-topbar .directory-viewer .dashboard-avatar {
        flex-basis: 40px !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
    }
}

</style>


<div class="main-page">
    <header class="asu-topbar directory-topbar">
        <div class="topbar-left directory-brandline">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
            <div class="directory-brandcopy"><strong>Research &amp; Extension Management System</strong><span>Aklan State University</span></div>
        </div>
        <div class="topbar-user directory-viewer">
            <div class="topbar-user-text"><strong><?= $esc(fullName($viewer)); ?></strong><span><?= $esc(roleLabel($viewerRole)); ?></span></div>
            <?= remsProfileAvatarHtml($pdo, $viewer, $viewerInitials, 'dashboard-avatar'); ?>
        </div>
    </header>
    <main class="page-content directory-page">
        <div class="directory-shell">
            <?php if (is_array($directoryFlash) && isset($directoryFlash['text'])): ?>
                <div class="directory-alert <?= ($directoryFlash['type'] ?? '') === 'success' ? 'directory-alert-success' : 'directory-alert-error'; ?>" role="alert">
                    <i class="bi <?= ($directoryFlash['type'] ?? '') === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?>" aria-hidden="true"></i>
                    <span><?= $esc($directoryFlash['text']); ?></span>
                </div>
            <?php endif; ?>
            <section class="directory-hero">
                <div>
                    <span class="directory-kicker">ASU-REMS &nbsp; / &nbsp; ACCOUNT DIRECTORY</span>
                    <h1><?= $esc($directoryTitle); ?></h1>
                    <p><?= $esc($directoryDescription); ?></p>
                </div>
                <?php if ($isDetail): ?>
                    <a class="directory-yellow" href="<?= $esc($base); ?>/profiles.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Profiles</a>
                <?php endif; ?>
            </section>

            <?php if ($isDetail): ?>
                <?php if (!$selected): ?>
                    <section class="directory-panel directory-empty" role="status">
                        <i class="bi bi-person-x" aria-hidden="true"></i>
                        <h2>Profile not available</h2>
                        <p>This account is not available to your office or cannot be found.</p>
                        <a class="directory-outline" href="<?= $esc($base); ?>/profiles.php">Back to directory</a>
                    </section>
                <?php else: ?>
                    <section class="directory-panel" aria-labelledby="directoryProfileTitle">
                        <div class="directory-panel-title">
                            <span class="directory-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                            <div><h2 id="directoryProfileTitle">Profile Information</h2><p>Read-only account information</p></div>
                            <span class="directory-readonly"><i class="bi bi-eye" aria-hidden="true"></i> View only</span>
                        </div>
                        <div class="directory-detail">
                            <?= remsDirectoryAvatar($selected, $base, 'directory-avatar-large'); ?>
                            <div class="directory-detail-copy">
                                <h3><?= $esc(remsDirectoryName($selected)); ?></h3>
                                <span class="directory-role"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= $esc(remsDirectoryRole((string)$selected['role'])); ?></span>
                                <p><?= $esc($isResearch ? 'Research faculty account' : 'Extension personnel account'); ?></p>
                            </div>
                        </div>
                        <div class="directory-facts">
                            <div class="directory-fact"><span>Email Address</span><strong><?= $esc($selected['email'] ?: 'Not provided'); ?></strong></div>
                            <div class="directory-fact"><span>Account Status</span><strong><?= $esc($selected['status'] ?: 'Not specified'); ?></strong></div>
                            <div class="directory-fact"><span>Role</span><strong><?= $esc(remsDirectoryRole((string)$selected['role'])); ?></strong></div>
                            <div class="directory-fact"><span>Account Created</span><strong><?= $esc(!empty($selected['created_at']) && strtotime((string)$selected['created_at']) ? date('M d, Y', strtotime((string)$selected['created_at'])) : 'Not available'); ?></strong></div>
                        </div>
                        <p class="directory-privacy"><i class="bi bi-shield-lock" aria-hidden="true"></i> Only professional account details are displayed. Passwords, home addresses, birth dates, and other private registration information are not accessible here.</p>
                    </section>
                <?php endif; ?>
            <?php else: ?>
                <section class="directory-panel" aria-labelledby="directoryListTitle">
                    <div class="directory-panel-title">
                        <span class="directory-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                        <div><h2 id="directoryListTitle"><?= $esc($isResearch ? 'Faculty / Researcher Accounts' : 'Extensionist Accounts'); ?></h2><p>Search and view account profiles</p></div>
                        <span class="directory-count"><?= number_format($total); ?> record<?= $total === 1 ? '' : 's'; ?></span>
                    </div>
                    <form class="directory-search" method="get" action="<?= $esc($base); ?>/profiles.php">
                        <label for="directoryQ">Search Profiles</label>
                        <div class="directory-search-row">
                            <div class="directory-search-field"><i class="bi bi-search" aria-hidden="true"></i><input id="directoryQ" name="q" type="search" placeholder="Search by name or email" value="<?= $esc($search); ?>" maxlength="100"></div>
                            <button class="directory-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Search</button>
                            <a class="directory-outline" href="<?= $esc($base); ?>/profiles.php">Reset</a>
                        </div>
                    </form>
                    <?php if (!$rows): ?>
                        <div class="directory-empty"><i class="bi bi-inbox" aria-hidden="true"></i><h2>No profiles found</h2><p><?= $search !== '' ? 'Try another name or clear the search.' : 'Eligible profiles will appear here once accounts are created.'; ?></p></div>
                    <?php else: ?>
                        <div class="directory-table-wrap"><table class="directory-table">
                            <thead><tr><th>Profile</th><th>Role</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $person): ?>
                                <tr>
                                    <td><div class="directory-person"><?= remsDirectoryAvatar($person, $base); ?><strong><?= $esc(remsDirectoryName($person)); ?></strong></div></td>
                                    <td><?= $esc(remsDirectoryRole((string)$person['role'])); ?></td>
                                    <td><?= $esc($person['email'] ?: '—'); ?></td>
                                    <td><span class="directory-status <?= strcasecmp((string)($person['status'] ?? ''), 'Active') === 0 ? 'active' : ''; ?>"><?= $esc($person['status'] ?: 'Not specified'); ?></span></td>
                                    <td>
                                        <div class="directory-actions">
                                            <a class="directory-view" href="<?= $esc($base); ?>/profiles.php?id=<?= (int)$person['user_id']; ?>" title="View profile" aria-label="View profile of <?= $esc(remsDirectoryName($person)); ?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                            <form class="directory-delete-form" method="post" action="<?= $esc($base); ?>/profiles.php"
                                                  data-person-name="<?= $esc(remsDirectoryName($person)); ?>">
                                                <input type="hidden" name="action" value="delete_account">
                                                <input type="hidden" name="delete_user_id" value="<?= (int)$person['user_id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $esc($directoryCsrfToken); ?>">
                                                <input type="hidden" name="q" value="<?= $esc($search); ?>">
                                                <input type="hidden" name="page" value="<?= $page; ?>">
                                                <button type="submit" class="directory-delete" title="Delete user"
                                                        aria-label="Delete account of <?= $esc(remsDirectoryName($person)); ?>">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                        <?php if ($pages > 1): ?>
                            <nav class="directory-pagination" aria-label="Profiles pages">
                                <?php if ($page > 1): ?><a href="?<?= $esc(http_build_query(['q' => $search, 'page' => $page - 1])); ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i> Previous</a><?php endif; ?>
                                <span>Page <?= $page; ?> of <?= $pages; ?></span>
                                <?php if ($page < $pages): ?><a href="?<?= $esc(http_build_query(['q' => $search, 'page' => $page + 1])); ?>">Next <i class="bi bi-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<!-- In-page confirmation for RPS/ESO account deletion. -->
<div class="directory-confirm-overlay" id="directoryDeleteDialog" hidden>
    <div class="directory-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="directoryDeleteTitle" aria-describedby="directoryDeleteMessage" tabindex="-1">
        <div class="directory-confirm-symbol" aria-hidden="true"><i class="bi bi-trash3"></i></div>
        <h2 id="directoryDeleteTitle">Delete this user?</h2>
        <p id="directoryDeleteMessage">The account for <strong id="directoryDeleteName">this user</strong> will be permanently removed if there are no linked records.</p>
        <div class="directory-confirm-actions">
            <button type="button" class="directory-confirm-cancel" id="directoryDeleteCancel">Cancel</button>
            <button type="button" class="directory-confirm-submit" id="directoryDeleteProceed">Delete</button>
        </div>
    </div>
</div>
<script>
/* Custom, accessible page dialog; original POST + CSRF/role safeguards remain intact. */
(function () {
    'use strict';
    const modal = document.getElementById('directoryDeleteDialog');
    const cancelButton = document.getElementById('directoryDeleteCancel');
    const deleteButton = document.getElementById('directoryDeleteProceed');
    const nameText = document.getElementById('directoryDeleteName');
    if (!modal || !cancelButton || !deleteButton || !nameText) return;

    let pendingForm = null;
    let previousFocus = null;

    function closeDialog() {
        modal.hidden = true;
        pendingForm = null;
        deleteButton.disabled = false;
        document.body.style.overflow = '';
        if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('directory-delete-form')) return;
        if (form.dataset.deleteConfirmed === 'yes') return;
        event.preventDefault();
        pendingForm = form;
        previousFocus = document.activeElement;
        nameText.textContent = form.dataset.personName || 'this user';
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        cancelButton.focus();
    });

    cancelButton.addEventListener('click', closeDialog);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeDialog();
    });
    document.addEventListener('keydown', function (event) {
        if (modal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
        }
        if (event.key === 'Tab') {
            const backward = event.shiftKey;
            if (backward && document.activeElement === cancelButton) {
                event.preventDefault();
                deleteButton.focus();
            } else if (!backward && document.activeElement === deleteButton) {
                event.preventDefault();
                cancelButton.focus();
            }
        }
    });

    deleteButton.addEventListener('click', function () {
        if (!pendingForm) return;
        const form = pendingForm;
        form.dataset.deleteConfirmed = 'yes';
        deleteButton.disabled = true;
        form.requestSubmit();
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
