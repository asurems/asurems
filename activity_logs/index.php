<?php
/**
 * ASU-REMS — LOGIN HISTORY ONLY.
 * Roles and scope are enforced on every SQL query, including PDF export.
 * Admin: admin accounts only.
 * RPS: research users (RPS, faculty/researchers).
 * ESO: extension users (ESO, extensionists, extension coordinators).
 * NO workflow-history records are retrieved or displayed on this page.
 * Uses the existing login_activity_logs table and users.last_login
 * as a latest-known-login fallback for accounts with no recorded audit events.
 */
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/profile_avatar.php';
requireLogin();
$user = currentUser();
$currentRole = (string)($user['role'] ?? '');
$roleScopes = [
    'admin' => ['admin'],
    'rps' => ['rps', 'faculty'],
    'extension_service_office' => ['extension_service_office', 'extensionist', 'extension_coordinator'],
];
if (!isset($roleScopes[$currentRole])) {
    http_response_code(403);
    exit('Access Denied. Only Admin, RPS, or ESO accounts can view login history.');
}
$allowedRoles = $roleScopes[$currentRole];
$scopeName = match ($currentRole) {
    'admin' => 'Administrator Accounts',
    'rps' => 'Research Office Users',
    default => 'Extension Office Users',
};
$pageTitle = 'Activity Logs';
$activePage = 'activity';
function remsLoginEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function remsLoginRoleLabel(string $role): string {
    return match ($role) {
        'admin' => 'Administrator',
        'rps' => 'Research Processing Staff (RPS)',
        'faculty' => 'Faculty / Researcher',
        'extension_service_office' => 'Extension Services Office (ESO)',
        'extensionist' => 'Extensionist',
        'extension_coordinator' => 'Extension Coordinator',
        default => ucwords(str_replace('_', ' ', $role)),
    };
}
function remsLoginPdfRoleLabel(string $role): string {
    return match ($role) {
        'admin' => 'Admin',
        'rps' => 'RPS',
        'faculty' => 'Faculty / Researcher',
        'extension_service_office' => 'ESO',
        'extensionist' => 'Extensionist',
        'extension_coordinator' => 'Extension Coordinator',
        default => 'Authorized User',
    };
}
function remsLoginDate($value): string {
    $ts = is_string($value) ? strtotime($value) : false;
    return $ts === false ? '—' : date('M d, Y h:i A', $ts);
}
function remsLoginSelectDate(string $raw): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    return ($date && $date->format('Y-m-d') === $raw) ? $raw : '';
}
// Filters: the default is ALL TIME, not the current day. The date window is
// applied to BOTH the display query and the report download, server-side.
$search = substr(trim((string)($_GET['search'] ?? '')), 0, 100);
$filterRole = trim((string)($_GET['role'] ?? ''));
if (!in_array($filterRole, $allowedRoles, true)) $filterRole = '';
$filterPeriod = trim((string)($_GET['period'] ?? 'all'));
if (!in_array($filterPeriod, ['all', 'day', 'year'], true)) $filterPeriod = 'all';
$filterDay = remsLoginSelectDate(trim((string)($_GET['date'] ?? '')));
$filterYear = trim((string)($_GET['year'] ?? ''));
if ($filterYear !== '' && (!preg_match('/^\d{4}$/', $filterYear) || (int)$filterYear < 1900 || (int)$filterYear > 2100)) $filterYear = '';
$filterSort = (string)($_GET['sort'] ?? 'oldest');
if (!in_array($filterSort, ['newest', 'oldest'], true)) $filterSort = 'newest';
// The preview and exported PDF use the SAME scope and time-range criteria.
$showPreview = (string)($_GET['login_report_preview'] ?? '') === '1';
$downloadPdf = (string)($_GET['download_login_pdf'] ?? '') === '1';
$isReport = $showPreview || $downloadPdf;
$reportPeriod = trim((string)($_GET['pdf_period'] ?? 'all'));
if (!in_array($reportPeriod, ['all', 'day', 'year'], true)) $reportPeriod = 'all';
$reportDay = remsLoginSelectDate(trim((string)($_GET['pdf_day'] ?? '')));
$reportYear = trim((string)($_GET['pdf_year'] ?? ''));
if ($reportYear !== '' && (!preg_match('/^\d{4}$/', $reportYear) || (int)$reportYear < 1900 || (int)$reportYear > 2100)) $reportYear = '';
$reportRole = trim((string)($_GET['pdf_role'] ?? ''));
if (!in_array($reportRole, $allowedRoles, true)) $reportRole = '';
$reportSort = (string)($_GET['pdf_sort'] ?? 'oldest');
if (!in_array($reportSort, ['newest', 'oldest'], true)) $reportSort = 'oldest';
// Each row in login_activity_logs represents one successful login. Existing
// users.last_login is retained ONLY as an explicitly marked latest-only fallback
// for users with NO recorded login events. It cannot reconstruct older logins.
$placeholders = implode(', ', array_fill(0, count($allowedRoles), '?'));
$baseFromSql = " FROM (
    SELECT logs.log_id, logs.user_id,
        CONVERT(logs.full_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS full_name,
        CONVERT(logs.email USING utf8mb4) COLLATE utf8mb4_unicode_ci AS email,
        CONVERT(logs.user_role USING utf8mb4) COLLATE utf8mb4_unicode_ci AS user_role,
        logs.login_at, 'recorded' AS record_source
    FROM login_activity_logs logs
    INNER JOIN users u ON u.user_id = logs.user_id
    WHERE u.role IN ({$placeholders})
      AND logs.user_role IN ({$placeholders})
    UNION ALL
    SELECT 0 AS log_id, u.user_id,
        CONVERT(TRIM(CONCAT_WS(' ', NULLIF(TRIM(u.first_name), ''),
            NULLIF(TRIM(u.middle_name), ''), NULLIF(TRIM(u.last_name), ''))) USING utf8mb4)
            COLLATE utf8mb4_unicode_ci AS full_name,
        CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_unicode_ci AS email,
        CONVERT(u.role USING utf8mb4) COLLATE utf8mb4_unicode_ci AS user_role,
        u.last_login AS login_at,
        'latest_only' AS record_source
    FROM users u
    WHERE u.role IN ({$placeholders})
      AND u.last_login IS NOT NULL
      AND u.last_login > '2000-01-01 00:00:00'
      AND NOT EXISTS (
          SELECT 1 FROM login_activity_logs saved
          WHERE saved.user_id = u.user_id
      )
) audit WHERE 1=1";
$baseParams = array_merge($allowedRoles, $allowedRoles, $allowedRoles);
$fromSql = $baseFromSql;
$queryParams = $baseParams;
$selectedPeriod = $isReport ? $reportPeriod : $filterPeriod;
$selectedDay = $isReport ? $reportDay : $filterDay;
$selectedYear = $isReport ? $reportYear : $filterYear;
$selectedRole = $isReport ? $reportRole : $filterRole;
$selectedSort = $isReport ? $reportSort : $filterSort;
$filterError = '';
if ($selectedRole !== '') {
    $fromSql .= ' AND audit.user_role = ?';
    $queryParams[] = $selectedRole;
}
if ($selectedPeriod === 'day') {
    if ($selectedDay === '') {
        $filterError = 'Please select a valid specific day.';
        $fromSql .= ' AND 1=0';
    } else {
        $fromSql .= ' AND audit.login_at >= ? AND audit.login_at < ?';
        $queryParams[] = $selectedDay . ' 00:00:00';
        $queryParams[] = (new DateTimeImmutable($selectedDay))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }
} elseif ($selectedPeriod === 'year') {
    if ($selectedYear === '') {
        $filterError = 'Please select a valid whole year.';
        $fromSql .= ' AND 1=0';
    } else {
        $fromSql .= ' AND audit.login_at >= ? AND audit.login_at < ?';
        $queryParams[] = $selectedYear . '-01-01 00:00:00';
        $queryParams[] = ((int)$selectedYear + 1) . '-01-01 00:00:00';
    }
}
if (!$isReport && $search !== '') {
    $term = '%' . $search . '%';
    $fromSql .= ' AND (audit.full_name LIKE ? OR audit.email LIKE ?)';
    $queryParams[] = $term;
    $queryParams[] = $term;
}
$sortDirection = $selectedSort === 'oldest' ? 'ASC' : 'DESC';
$selectSql = 'SELECT audit.log_id, audit.user_id, audit.full_name, audit.email,
    audit.user_role, audit.login_at, audit.record_source '
    . $fromSql . " ORDER BY audit.login_at {$sortDirection}, audit.log_id {$sortDirection}";
// Generate the whole-year dropdown from actual scoped history, not a fixed
// date cutoff; this also allows earlier records to be selected.
$availableYears = [(int)date('Y')];
try {
    $yearStmt = $pdo->prepare('SELECT DISTINCT YEAR(audit.login_at) AS login_year '
        . $baseFromSql . ' AND audit.login_at IS NOT NULL ORDER BY login_year DESC');
    $yearStmt->execute($baseParams);
    foreach ($yearStmt->fetchAll(PDO::FETCH_COLUMN) as $savedYear) {
        $savedYear = (int)$savedYear;
        if ($savedYear >= 1900 && $savedYear <= 2100) $availableYears[] = $savedYear;
    }
} catch (Throwable $e) {
    error_log('Login year options failed: ' . $e->getMessage());
}
if ($filterYear !== '') $availableYears[] = (int)$filterYear;
if ($reportYear !== '') $availableYears[] = (int)$reportYear;
// Offer any intervening calendar year, even if it contains zero logins.
$firstYear = min(min($availableYears), 2015);
for ($yr = (int)date('Y'); $yr >= $firstYear; $yr--) $availableYears[] = $yr;
$availableYears = array_values(array_unique($availableYears));
rsort($availableYears, SORT_NUMERIC);
function remsLoginPeriodLabel(string $period, string $day, string $year): string {
    return match ($period) {
        'day' => 'Specific day: ' . $day,
        'year' => 'Whole year: ' . $year,
        default => 'All Time (since first recorded login)',
    };
}
$reportCoverage = remsLoginPeriodLabel($reportPeriod, $reportDay, $reportYear);
$listingCoverage = remsLoginPeriodLabel($filterPeriod, $filterDay, $filterYear);
// Standalone, library-free PDF. Export fetches ALL authorized matching logins;
// the browser is limited to 300 rows for performance only.
if ($downloadPdf) {
    final class RemsOneFileActivityPdf {
        private array $pages = [];
        private int $page = -1;
        private float $cursor = 395;
        private string $heading;
        private string $summary;
        private array $columns;
        private array $positions;
        private int $rowCounter = 0;
        public function __construct(string $heading, string $summary, array $columns, array $positions) {
            $this->heading = $heading;
            $this->summary = $summary;
            $this->columns = $columns;
            $this->positions = $positions;
            $this->nextPage();
        }
        private static function encode(string $s): string {
            $t = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
            if ($t === false) $t = preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '';
            return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', ' ', ' '], $t);
        }
        private static function clip(string $s, int $chars): string {
            $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
            $length = @iconv_strlen($s, 'UTF-8');
            if ($length !== false && $length <= $chars) return $s;
            $cut = @iconv_substr($s, 0, max(0, $chars - 3), 'UTF-8');
            return ($cut === false ? substr($s, 0, $chars - 3) : $cut) . '...';
        }
        private function command(string $c): void { $this->pages[$this->page] .= $c . "\n"; }
        private function text(float $x, float $y, string $s, float $size = 9, bool $bold = false): void {
            $this->command('0 g BT /F' . ($bold ? '2' : '1') . ' ' . $size
                . ' Tf 1 0 0 1 ' . $x . ' ' . $y . ' Tm (' . self::encode($s) . ') Tj ET');
        }
        private function center(float $y, string $s, float $size = 10, bool $bold = false): void {
            $w = strlen(self::encode($s)) * $size * ($bold ? 0.535 : 0.49);
            $this->text(max(36, (842 - $w) / 2), $y, $s, $size, $bold);
        }
        private function line(float $x1, float $y1, float $x2, float $y2, float $w = .5): void {
            $this->command('0.75 G ' . $w . ' w ' . $x1 . ' ' . $y1 . ' m '
                . $x2 . ' ' . $y2 . ' l S 0 G');
        }
        private function rect(float $y, float $h, float $gray): void {
            $this->command($gray . ' g 36 ' . $y . ' 770 ' . $h . ' re f 0 g');
        }
        private function nextPage(): void {
            $this->pages[] = '';
            $this->page++;
            $this->center(556, 'Republic of the Philippines', 11);
            $this->center(536, 'AKLAN STATE UNIVERSITY', 15, true);
            $this->center(518, 'Banga, Aklan', 10);
            $this->line(36, 507, 806, 507, 1);
            $this->center(480, $this->heading, 13, true);
            $this->text(39, 454, self::clip($this->summary, 132), 8.8);
            $this->text(39, 438, 'Generated: ' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('F d, Y h:i A') . ' (PHT)', 8.8);
            $this->rect(404, 25, .91);
            foreach ($this->columns as $i => $c) {
                $this->text($this->positions[$i], 412, $c, 8.7, true);
            }
            $this->line(36, 404, 806, 404);
            $this->cursor = 404;
        }
        public function add(array $cells): void {
            if ($this->cursor < 78) $this->nextPage();
            $this->rowCounter++;
            $bottom = $this->cursor - 24;
            if ($this->rowCounter % 2 === 0) $this->rect($bottom, 24, .98);
            foreach ($cells as $i => $cell) {
                $limit = $this->positions[$i + 1] ?? 803;
                $space = $limit - $this->positions[$i] - 9;
                $chars = max(7, (int)floor($space / 4.7));
                $this->text($this->positions[$i], $bottom + 9, self::clip((string)$cell, $chars), 8.7);
            }
            $this->line(36, $bottom, 806, $bottom, .3);
            $this->cursor = $bottom;
        }
        public function bytes(): string {
            if ($this->rowCounter === 0) $this->text(40, 379, 'No records match the selected filters.', 10);
            $objects = [
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '',
                3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
                4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            ];
            $pageRefs = [];
            $total = count($this->pages);
            foreach ($this->pages as $i => $stream) {
                $stream .= '0.7 G 0.5 w 36 44 m 806 44 l S 0 G' . "\n";
                $stream .= '0 g BT /F1 8 Tf 1 0 0 1 38 30 Tm (ASU-REMS  |  System-generated activity report) Tj ET' . "\n";
                $stream .= '0 g BT /F1 8 Tf 1 0 0 1 728 30 Tm (Page ' . ($i+1) . ' of ' . $total . ') Tj ET' . "\n";
                $pageId = 5 + $i*2;
                $contentId = $pageId + 1;
                $pageRefs[] = $pageId . ' 0 R';
                $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] '
                    . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
                $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
            }
            $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . $total . ' >>';
            ksort($objects);
            $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
            $offs = [0];
            foreach ($objects as $id => $value) {
                $offs[$id] = strlen($out);
                $out .= $id . " 0 obj\n" . $value . "\nendobj\n";
            }
            $xref = strlen($out);
            $out .= 'xref' . "\n0 " . (count($objects)+1) . "\n0000000000 65535 f \n";
            for ($id=1; $id<=count($objects); $id++) $out .= sprintf('%010d 00000 n ', $offs[$id]) . "\n";
            $out .= "trailer\n<< /Size " . (count($objects)+1) . " /Root 1 0 R >>\nstartxref\n"
                . $xref . "\n%%EOF\n";
            return $out;
        }
    }
    try {
        if ($filterError !== '') throw new InvalidArgumentException($filterError);
        $pdf = new RemsOneFileActivityPdf('USER LOGIN ACTIVITY REPORT',
            'Scope: ' . $scopeName . ' | ' . $reportCoverage
            . ' | Role: ' . ($reportRole !== '' ? remsLoginRoleLabel($reportRole) : 'All authorized users'),
            ['NO.', 'LOGIN DATE / TIME', 'FULL NAME', 'EMAIL', 'ROLE'],
            [46, 91, 240, 440, 678]);
        $stmt = $pdo->prepare($selectSql);
        $stmt->execute($queryParams);
        $rowNumber = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowNumber++;
            $pdf->add([
                (string)$rowNumber,
                remsLoginDate($row['login_at'] ?? null),
                (string)($row['full_name'] ?: 'User #' . (int)$row['user_id']),
                (string)$row['email'],
                remsLoginPdfRoleLabel((string)$row['user_role'])
                    . (($row['record_source'] ?? '') === 'latest_only' ? ' (Latest only)' : ''),
            ]);
        }
        $bytes = $pdf->bytes();
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="ASU_REMS_Login_Activity_'
            . $currentRole . '_' . date('Ymd') . '.pdf"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
    } catch (Throwable $e) {
        error_log('Scoped login PDF failed: ' . $e->getMessage());
        http_response_code(500);
        echo 'Unable to generate login activity PDF. Please verify the login_activity_logs table.';
    }
    exit;
}
$loginRows = [];
$loginCount = 0;
$loadError = '';
$recordsPerPage = 100;
$currentPage = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT,
    ['options' => ['default' => 1, 'min_range' => 1, 'max_range' => 1000000]]);
$currentPage = $currentPage ?: 1;
$totalPages = 1;
try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $fromSql);
    $countStmt->execute($queryParams);
    $loginCount = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($loginCount / $recordsPerPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $recordsPerPage;
    // No 300-record history cutoff: page through every recorded event.
    $stmt = $pdo->prepare($selectSql . " LIMIT {$recordsPerPage} OFFSET {$offset}");
    $stmt->execute($queryParams);
    $loginRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Scoped login history retrieval failed: ' . $e->getMessage());
    $loadError = 'Login history could not be loaded. Check that login_activity_logs exists and your login logger is connected.';
}
$pagerParams = $isReport
    ? ['login_report_preview' => '1', 'pdf_role' => $reportRole, 'pdf_period' => $reportPeriod,
       'pdf_day' => $reportDay, 'pdf_year' => $reportYear, 'pdf_sort' => $reportSort]
    : ['search' => $search, 'role' => $filterRole, 'period' => $filterPeriod,
       'date' => $filterDay, 'year' => $filterYear, 'sort' => $filterSort];
function remsLoginPageHref(array $params, int $number): string {
    return BASE_URL . '/activity_logs/index.php?' . http_build_query($params + ['page' => $number]);
}
$initials = '';
if (!empty($user['first_name'])) $initials .= strtoupper(substr((string)$user['first_name'], 0, 1));
if (!empty($user['last_name'])) $initials .= strtoupper(substr((string)$user['last_name'], 0, 1));
$roleName = $currentRole === 'rps' ? 'Research Processing Staff (RPS)' : remsLoginRoleLabel($currentRole);
$pdfUrl = BASE_URL . '/activity_logs/index.php?' . http_build_query([
    'download_login_pdf' => '1', 'pdf_period' => $reportPeriod, 'pdf_day' => $reportDay,
    'pdf_year' => $reportYear, 'pdf_role' => $reportRole, 'pdf_sort' => $reportSort,
]);
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<style>
/* Match the exact white/light visual direction of reports/index.php. */
.main-page,.main-page button,.main-page input,.main-page select,.main-page a,.main-page table{font-family:"Century Gothic",Arial,sans-serif!important}
.main-page{min-height:100vh;background:#fff!important}
.login-logs-page{min-height:calc(100vh - 76px);padding:24px 28px 30px!important;background:#fafcf8!important}
.login-logs-shell{width:100%;font-family:"Century Gothic",Arial,sans-serif;color:#1f2922}
.login-logs-topbar{height:76px!important;min-height:76px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;background:#fff!important;padding:0 30px!important;border-bottom:1px solid rgba(0,122,51,.12)!important}
.login-logs-topbar .topbar-left,.login-logs-topbar .topbar-user{display:flex;align-items:center;gap:14px}
.login-logs-topbar .system-brand-copy{position:relative;padding-left:18px;line-height:1.15}
.login-logs-topbar .system-brand-copy:before{content:"";position:absolute;left:0;top:50%;transform:translateY(-50%);height:38px;width:3px;border-radius:999px;background:#007a33}
.login-logs-topbar .system-brand-copy strong{display:block;color:#101810;font-size:17px;font-weight:800;line-height:1.18}
.login-logs-topbar .system-brand-copy span{display:block;color:#657068;font-size:13px;margin-top:4px}
.login-logs-topbar .topbar-user-text{text-align:right}
.login-logs-topbar .topbar-user-text strong{display:block;color:#101810;font-size:15px}
.login-logs-topbar .topbar-user-text span{display:block;color:#657068;font-size:12px;margin-top:4px}
.login-logs-topbar .login-logs-avatar{display:flex!important;align-items:center;justify-content:center;width:46px!important;height:46px!important;min-width:46px!important;border-radius:50%!important;background:#ffd51f!important;color:#003f1a!important;font-weight:900!important}
.login-logs-hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px 26px;margin-bottom:18px;min-height:96px;border-radius:18px;background:linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%);box-shadow:0 18px 42px rgba(0,90,40,.16);color:#fff}
.login-logs-hero:after{content:"";position:absolute;right:-110px;top:-120px;width:430px;height:280px;transform:rotate(-9deg);border-radius:42px;background:linear-gradient(135deg,rgba(255,213,31,.16),rgba(255,255,255,.035));pointer-events:none}
.login-logs-hero>*{position:relative;z-index:1}
.login-logs-hero h1{margin:0;color:#fff!important;font-size:30px;font-weight:900;line-height:1.1}
.login-logs-hero p{display:none}
.login-logs-generator,.login-logs-panel{background:#fff;border:1px solid rgba(0,107,46,.13);border-radius:18px;box-shadow:0 14px 34px rgba(16,24,16,.05);overflow:hidden;margin-bottom:18px}
.login-logs-panel-head{display:flex;align-items:center;gap:10px;padding:14px 20px;min-height:62px;border-bottom:1px solid #eaf0e9}
.login-logs-panel-head.between{justify-content:space-between;flex-wrap:wrap}
.login-logs-panel-title{display:flex;gap:10px;align-items:center}
.login-logs-panel-head h2{font-size:15px;font-weight:800;color:#101810;margin:0}
.login-logs-panel-icon{display:flex;align-items:center;justify-content:center;width:36px;height:36px;min-width:36px;border-radius:10px;background:#edf7ef;color:#007a33;font-size:17px}
.login-logs-generator-form{display:grid;grid-template-columns:minmax(155px,1.25fr) minmax(145px,.95fr) minmax(185px,.95fr) auto;align-items:end;gap:12px;padding:18px 20px}
.login-logs-generator-form>div,.login-logs-filter-form>div{min-width:0}
.login-logs-page label{display:block;margin-bottom:7px;color:#465149;font-size:10px;font-weight:700}
.login-logs-page .form-select,.login-logs-page .form-control{height:44px!important;min-height:44px!important;width:100%;min-width:0;border:1px solid #d8e1da!important;border-radius:10px!important;background-color:#fff!important;color:#1f2922;font-size:11.5px!important;box-shadow:none!important}
.login-logs-page .form-select{padding:0 35px 0 13px!important}
.login-logs-page .form-select:focus,.login-logs-page .form-control:focus{border-color:#76ac82!important;box-shadow:0 0 0 3px rgba(8,117,44,.07)!important}
.login-logs-generator-actions{display:flex;justify-content:flex-end}
.login-logs-primary,.login-logs-secondary{display:inline-flex!important;align-items:center;justify-content:center;gap:7px;min-height:44px;padding:0 15px;border-radius:10px!important;white-space:nowrap;text-decoration:none!important;font-size:11px!important;font-weight:800!important;box-shadow:none!important}
.login-logs-primary{background:#006b30!important;border:1px solid #006b30!important;color:#fff!important}
.login-logs-primary:hover{background:#005225!important;border-color:#005225!important;color:#fff!important}
.login-logs-secondary{background:#fff!important;border:1px solid #cde2d3!important;color:#006b30!important}
.login-logs-secondary:hover{background:#f2f8f3!important;border-color:#a8d2b4!important;color:#005225!important}
.login-logs-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.login-logs-meta{margin:0;padding:13px 20px;border-bottom:1px solid #edf0ed;color:#647168;font-size:11px}
.login-logs-filter-form{display:grid;grid-template-columns:minmax(145px,1.25fr) minmax(130px,.9fr) minmax(130px,.85fr) minmax(145px,.85fr) minmax(120px,.8fr) auto;align-items:end;gap:10px;padding:16px 20px;border-bottom:1px solid #edf0ed}
.login-logs-search-field{position:relative}.login-logs-search-field>i{position:absolute;left:15px;top:50%;transform:translateY(-50%);font-size:14px;color:#658071;pointer-events:none}
.login-logs-search-field input{padding-left:40px!important}
.login-logs-table-wrap{overflow-x:auto;width:100%}
.login-logs-table{width:100%;min-width:680px;border-collapse:collapse;margin:0}
.login-logs-table th,.login-logs-table td{padding:13px 15px;border-bottom:1px solid #edf0ed;vertical-align:middle;text-align:left}
.login-logs-table th{background:#f7faf7;color:#455349;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.035em;white-space:nowrap}
.login-logs-table td{color:#35443a;font-size:11px;line-height:1.5}
.login-logs-table td strong{color:#203c2a;font-weight:800}
.login-logs-table tr:last-child td{border-bottom:none}
.login-logs-table td:first-child,.login-logs-table th:first-child{text-align:center;width:52px}
.login-logs-empty{min-height:155px;display:flex;flex-direction:column;gap:7px;align-items:center;justify-content:center;padding:28px 16px;text-align:center;color:#68756c;font-size:11px}
.login-logs-empty i{font-size:24px}.login-logs-empty strong{font-size:13px;color:#405549}.login-logs-empty p{margin:0}
.login-logs-legacy{display:block;margin-top:3px;color:#7b827c;font-size:9px}.login-logs-legacy-explain{margin:0;padding:9px 20px;color:#69756d;font-size:10px;background:#fbfdfb;border-bottom:1px solid #edf0ed}.login-logs-page *,.login-logs-topbar *{animation:none!important;transition:none!important}
@media(max-width:1350px){.login-logs-filter-form{grid-template-columns:repeat(3,minmax(0,1fr))}.login-logs-filter-form .login-logs-actions{justify-content:flex-end;grid-column:1/-1}}
@media(max-width:1100px){.login-logs-generator-form{grid-template-columns:repeat(2,minmax(0,1fr))}.login-logs-generator-actions{grid-column:1/-1}}
@media(max-width:760px){.login-logs-page{padding:14px!important}.login-logs-topbar{padding:0 16px!important}.login-logs-topbar .topbar-user-text{display:none}.login-logs-topbar .system-brand-copy strong{font-size:13px}.login-logs-topbar .system-brand-copy span{font-size:10px}.login-logs-hero{flex-direction:column;align-items:stretch;padding:18px;min-height:88px}.login-logs-hero h1{font-size:25px}.login-logs-filter-form,.login-logs-generator-form{grid-template-columns:1fr}.login-logs-filter-form .login-logs-actions,.login-logs-generator-actions{grid-column:auto}.login-logs-actions .btn,.login-logs-generator-actions .btn{flex:1}}
.login-logs-coverage-display{height:44px;display:flex;align-items:center;padding:0 12px;border:1px solid #e5eee7;border-radius:10px;background:#f7faf7;color:#56705e;font-size:11px;white-space:nowrap}
.login-logs-pagination{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;border-top:1px solid #eaf0e9;padding:12px 20px;color:#647168;font-size:11px}
.login-logs-pagination>div{display:flex;gap:6px;flex-wrap:wrap}
.login-logs-pagination .btn{min-height:34px;padding:0 11px;font-size:10px!important}
[data-period-day][hidden],[data-period-year][hidden],[data-period-all][hidden]{display:none!important}
@media(max-width:760px){.login-logs-pagination>div{width:100%}.login-logs-pagination .btn{flex:1}}
/* ============================================================
   ACTIVITY LOGS — ASU-REMS FOREST GREEN DARK MODE
   Visual changes only; login/PDF access and filters are untouched.
   ============================================================ */
html[data-rems-theme="dark"] body .main-page {
    background: #0b1d15 !important;
    color: #e9f3eb !important;
}
html[data-rems-theme="dark"] body .main-page .login-logs-page {
    background: linear-gradient(145deg, #0b1d15, #10271b 70%, #132e21) !important;
    color: #e9f3eb !important;
}
html[data-rems-theme="dark"] body .main-page .login-logs-topbar {
    background: #11291e !important;
    border-bottom-color: #365843 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .login-logs-topbar .system-brand-copy strong,
html[data-rems-theme="dark"] .login-logs-topbar .topbar-user-text strong {
    color: #f1f7ef !important;
}
html[data-rems-theme="dark"] .login-logs-topbar .system-brand-copy span,
html[data-rems-theme="dark"] .login-logs-topbar .topbar-user-text span {
    color: #bfd1c1 !important;
}
html[data-rems-theme="dark"] .login-logs-topbar .sidebar-toggle {
    color: #e8f3eb !important;
}
/* Keep the existing official green hero and its white title. */
html[data-rems-theme="dark"] .login-logs-hero,
html[data-rems-theme="dark"] .login-logs-hero h1 {
    color: #fff !important;
}
html[data-rems-theme="dark"] .login-logs-page :is(.login-logs-generator,.login-logs-panel) {
    background: #142e22 !important;
    border-color: #3a5d47 !important;
    box-shadow: 0 12px 28px rgba(0,0,0,.14) !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-panel-head {
    background: #1b3a2b !important;
    border-bottom-color: #42654d !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-panel-head h2,
html[data-rems-theme="dark"] .login-logs-page .login-logs-empty strong {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-panel-icon {
    background: #29503a !important;
    color: #b2eac0 !important;
}
html[data-rems-theme="dark"] .login-logs-page label {
    color: #ccddcf !important;
}
html[data-rems-theme="dark"] .login-logs-page :is(.form-control,.form-select) {
    background-color: #203e2e !important;
    border-color: #52705a !important;
    color: #f2f9f1 !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] .login-logs-page .form-control::placeholder {
    color: #b9cfbf !important;
    opacity: 1 !important;
}
html[data-rems-theme="dark"] .login-logs-page .form-select {
    appearance: none !important;
    -webkit-appearance: none !important;
    padding-right: 38px !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23d5ead8' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 13px center !important;
    background-size: 15px 15px !important;
}
html[data-rems-theme="dark"] .login-logs-page select option {
    background: #203e2e !important;
    color: #f2f9f1 !important;
}
html[data-rems-theme="dark"] .login-logs-page input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) !important;
    cursor: pointer;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-search-field > i {
    color: #b5ddbf !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-coverage-display {
    background: #203c2d !important;
    border-color: #42684f !important;
    color: #d5e8d7 !important;
}
html[data-rems-theme="dark"] .login-logs-page :is(.login-logs-filter-form,.login-logs-meta,.login-logs-pagination) {
    border-color: #3a5946 !important;
    color: #c6d9ca !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-legacy-explain {
    background: #1a3729 !important;
    border-bottom-color: #3a5946 !important;
    color: #c7d8ca !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-legacy {
    color: #bdcebF !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-table {
    background: #142e22 !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-table th {
    background: #224333 !important;
    color: #d9e9db !important;
    border-bottom-color: #496b53 !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-table td {
    background: #142e22 !important;
    color: #e5f0e6 !important;
    border-bottom-color: #365842 !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-table tbody tr:nth-child(even) td {
    background: #183628 !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-table td strong {
    color: #f2f8f0 !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-empty {
    color: #c2d3c5 !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-secondary {
    background: #244533 !important;
    color: #dff4e2 !important;
    border-color: #63856a !important;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-secondary:hover {
    background: #305b40 !important;
    border-color: #81a98a !important;
    color: #fff !important;
}
/* Generated PDF remains untouched: this stylesheet affects only the HTML view. */
/* =========================================================
   ACTIVITY LOGS — VISIBLE DROPDOWN CHEVRONS
   Use an independent overlay: theme background shorthands can
   erase background-image arrows on native Bootstrap selects.
   No JS, form, filters, PDF or server-side logic is changed.
   ========================================================= */
.login-logs-page .login-logs-select-wrap {
    position: relative;
    display: block;
    width: 100%;
    min-width: 0;
}
.login-logs-page .login-logs-select-wrap > select.form-select {
    display: block;
    width: 100%;
    padding-right: 44px !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    background-image: none !important;
}
.login-logs-page .login-logs-select-wrap::after {
    content: "";
    position: absolute;
    z-index: 2;
    right: 17px;
    top: 50%;
    width: 9px;
    height: 9px;
    border-right: 2px solid #376447;
    border-bottom: 2px solid #376447;
    transform: translateY(-75%) rotate(45deg);
    pointer-events: none;
}
html[data-rems-theme="dark"] .login-logs-page .login-logs-select-wrap::after {
    border-right-color: #c8f0d3;
    border-bottom-color: #c8f0d3;
}

/* Activity Logs — place Filter and Reset in the open space beside Sort.
   Visual layout only. The form action and its controls are unchanged. */
@media (min-width: 761px) {
    .login-logs-page .login-logs-filter-form {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    .login-logs-page .login-logs-filter-form > .login-logs-actions {
        grid-column: 3 !important;
        grid-row: 2 !important;
        align-self: end !important;
        justify-content: flex-start !important;
    }
}
@media (max-width: 760px) {
    .login-logs-page .login-logs-filter-form > .login-logs-actions {
        grid-column: auto !important;
        grid-row: auto !important;
    }
}

</style>
<style>

/* Activity Logs avatar: mirror the working Profiles page top-right avatar.

   Same class, same 46px sizing, same one soft Dashboard shadow.

   No extra ring, hover animation, or layout movement. */

.login-logs-topbar .topbar-user .dashboard-avatar {

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

.login-logs-topbar .topbar-user .dashboard-avatar img {

    display: block !important;

    width: 100% !important;

    height: 100% !important;

    object-fit: cover !important;

    border: 0 !important;

    outline: none !important;

    border-radius: 50% !important;

}

@media (max-width: 480px) {

    .login-logs-topbar .topbar-user .dashboard-avatar {

        flex-basis: 40px !important;

        width: 40px !important;

        height: 40px !important;

        min-width: 40px !important;

    }

}

</style>

<div class="main-page">
<header class="asu-topbar login-logs-topbar">
    <div class="topbar-left">
        <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
        <div class="system-brand-copy"><strong>Research &amp; Extension Management System</strong><span>Aklan State University</span></div>
    </div>
    <div class="topbar-user">
        <div class="topbar-user-text"><strong><?= remsLoginEscape(fullName($user)); ?></strong><span><?= remsLoginEscape($roleName); ?></span></div>
        <?= remsProfileAvatarHtml($pdo, $user, $initials ?: 'U', 'dashboard-avatar'); ?>
    </div>
</header>
<main class="page-content login-logs-page">
<div class="login-logs-shell">
    <section class="login-logs-hero">
        <div><h1>Activity Logs</h1><p>Successful login history for <?= remsLoginEscape($scopeName); ?>.</p></div>
    </section>
    <section class="login-logs-generator" id="loginReportGenerator" aria-labelledby="loginGenerateTitle">
        <div class="login-logs-panel-head"><span class="login-logs-panel-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span><h2 id="loginGenerateTitle">Generate Login Report</h2></div>
        <form class="login-logs-generator-form" data-period-form method="get" action="<?= BASE_URL; ?>/activity_logs/index.php">
            <input type="hidden" name="login_report_preview" value="1">
            <input type="hidden" name="pdf_sort" value="oldest">
            <div><label for="loginReportRole">User Type</label>
                <span class="login-logs-select-wrap"><select class="form-select" id="loginReportRole" name="pdf_role">
                    <option value="">All <?= remsLoginEscape($scopeName); ?></option>
                    <?php foreach ($allowedRoles as $visibleRole): ?>
                        <option value="<?= remsLoginEscape($visibleRole); ?>" <?= $reportRole === $visibleRole ? 'selected' : ''; ?>><?= remsLoginEscape(remsLoginRoleLabel($visibleRole)); ?></option>
                    <?php endforeach; ?>
                </select></span>
            </div>
            <div><label for="reportPeriod">Date Coverage</label>
                <span class="login-logs-select-wrap"><select class="form-select" id="reportPeriod" name="pdf_period" data-period-select>
                    <option value="all" <?= $reportPeriod === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="day" <?= $reportPeriod === 'day' ? 'selected' : ''; ?>>Specific Day</option>
                    <option value="year" <?= $reportPeriod === 'year' ? 'selected' : ''; ?>>Whole Year</option>
                </select></span>
            </div>
            <div data-period-all><label>Coverage</label><div class="login-logs-coverage-display">From the first saved login</div></div>
            <div data-period-day hidden><label for="reportDay">Specific Day</label>
                <input class="form-control" type="date" id="reportDay" name="pdf_day" value="<?= remsLoginEscape($reportDay); ?>" disabled>
            </div>
            <div data-period-year hidden><label for="loginReportYear">Whole Year</label>
                <span class="login-logs-select-wrap"><select class="form-select" id="loginReportYear" name="pdf_year" disabled>
                    <option value="">Select Year</option>
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?= $yr; ?>" <?= $reportYear === (string)$yr ? 'selected' : ''; ?>><?= $yr; ?></option>
                    <?php endforeach; ?>
                </select></span>
            </div>
            <div class="login-logs-generator-actions"><button class="btn login-logs-primary" type="submit"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Generate Report</button></div>
        </form>
    </section>
    <?php if ($showPreview): ?>
    <section class="login-logs-panel" aria-labelledby="loginReportPreviewTitle">
        <div class="login-logs-panel-head between">
            <div class="login-logs-panel-title"><span class="login-logs-panel-icon"><i class="bi bi-table" aria-hidden="true"></i></span><h2 id="loginReportPreviewTitle">Generated Login Report</h2></div>
            <div class="login-logs-actions">
                <a class="btn login-logs-secondary" href="<?= BASE_URL; ?>/activity_logs/index.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Activity Logs</a>
                <?php if ($loadError === ''): ?>
                    <a class="btn login-logs-primary" href="<?= remsLoginEscape($pdfUrl); ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Download PDF</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="login-logs-meta">Scope: <strong><?= remsLoginEscape($scopeName); ?></strong> &nbsp; | &nbsp; Coverage: <?= remsLoginEscape($reportCoverage); ?> &nbsp; | &nbsp; Total: <strong><?= number_format($loginCount); ?></strong></div>
        <p class="login-logs-legacy-explain">Displays all saved successful login events from the first recorded event. Accounts that predate logging may show only their last known login, clearly labeled.</p>
        <?php if ($filterError !== ''): ?><div class="alert alert-warning m-3" role="alert"><?= remsLoginEscape($filterError); ?></div><?php endif; ?>
        <?php if ($loadError !== ''): ?>
            <div class="alert alert-warning m-3" role="alert"><?= remsLoginEscape($loadError); ?></div>
        <?php elseif (!$loginRows): ?>
            <div class="login-logs-empty"><i class="bi bi-inbox"></i><strong>No login records found for this selection.</strong></div>
        <?php else: ?>
            <div class="login-logs-table-wrap"><table class="login-logs-table">
                <thead><tr><th>No.</th><th>Full Name</th><th>Email</th><th>User Type</th><th>Login Date / Time</th></tr></thead>
                <tbody><?php foreach ($loginRows as $n => $entry): ?><tr>
                    <td><?= ($currentPage - 1) * $recordsPerPage + $n + 1; ?></td>
                    <td><strong><?= remsLoginEscape($entry['full_name'] ?: 'User #' . (int)$entry['user_id']); ?></strong></td>
                    <td><?= remsLoginEscape($entry['email']); ?></td>
                    <td><?= remsLoginEscape(remsLoginRoleLabel((string)$entry['user_role'])); ?></td>
                    <td><?= remsLoginEscape(remsLoginDate($entry['login_at'])); ?><?php if (($entry['record_source'] ?? '') === 'latest_only'): ?><small class="login-logs-legacy">Latest known login only</small><?php endif; ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php if ($totalPages > 1): ?>
                <nav class="login-logs-pagination" aria-label="Login history pages">
                    <span>Page <?= number_format($currentPage); ?> of <?= number_format($totalPages); ?> · <?= number_format($loginCount); ?> matching logins</span>
                    <div>
                        <?php if ($currentPage > 1): ?><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, 1)); ?>">First</a><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, $currentPage - 1)); ?>">Previous</a><?php endif; ?>
                        <?php if ($currentPage < $totalPages): ?><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, $currentPage + 1)); ?>">Next</a><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, $totalPages)); ?>">Last</a><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="login-logs-panel" aria-labelledby="loginHistoryTitle">
        <div class="login-logs-panel-head"><span class="login-logs-panel-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span><h2 id="loginHistoryTitle">Login History</h2></div>
        <form class="login-logs-filter-form" data-period-form method="get" action="<?= BASE_URL; ?>/activity_logs/index.php">
            <div class="login-logs-search"><label for="loginSearch">Search Users</label><div class="login-logs-search-field"><i class="bi bi-search" aria-hidden="true"></i><input id="loginSearch" class="form-control" type="search" name="search" placeholder="Name or email" value="<?= remsLoginEscape($search); ?>"></div></div>
            <div><label for="loginRole">User Type</label><span class="login-logs-select-wrap"><select class="form-select" id="loginRole" name="role"><option value="">All authorized users</option>
                <?php foreach ($allowedRoles as $visibleRole): ?><option value="<?= remsLoginEscape($visibleRole); ?>" <?= $filterRole === $visibleRole ? 'selected' : ''; ?>><?= remsLoginEscape(remsLoginRoleLabel($visibleRole)); ?></option><?php endforeach; ?>
            </select></span></div>
            <div><label for="listingPeriod">Date Coverage</label><span class="login-logs-select-wrap"><select class="form-select" id="listingPeriod" name="period" data-period-select>
                <option value="all" <?= $filterPeriod === 'all' ? 'selected' : ''; ?>>All Time</option>
                <option value="day" <?= $filterPeriod === 'day' ? 'selected' : ''; ?>>Specific Day</option>
                <option value="year" <?= $filterPeriod === 'year' ? 'selected' : ''; ?>>Whole Year</option>
            </select></span></div>
            <div data-period-all><label>Coverage</label><div class="login-logs-coverage-display">All saved logins</div></div>
            <div data-period-day hidden><label for="loginDay">Specific Day</label><input type="date" class="form-control" id="loginDay" name="date" value="<?= remsLoginEscape($filterDay); ?>" disabled></div>
            <div data-period-year hidden><label for="loginYear">Whole Year</label><span class="login-logs-select-wrap"><select class="form-select" id="loginYear" name="year" disabled><option value="">Select Year</option>
                <?php foreach ($availableYears as $yr): ?><option value="<?= $yr; ?>" <?= $filterYear === (string)$yr ? 'selected' : ''; ?>><?= $yr; ?></option><?php endforeach; ?>
            </select></span></div>
            <div><label for="loginSort">Sort</label><span class="login-logs-select-wrap"><select class="form-select" id="loginSort" name="sort"><option value="newest" <?= $filterSort === 'newest' ? 'selected' : ''; ?>>Newest first</option><option value="oldest" <?= $filterSort === 'oldest' ? 'selected' : ''; ?>>Oldest first</option></select></span></div>
            <div class="login-logs-actions"><button type="submit" class="btn login-logs-primary"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Filter</button><a class="btn login-logs-secondary" href="<?= BASE_URL; ?>/activity_logs/index.php">Reset</a></div>
        </form>
        <p class="login-logs-legacy-explain">Displays all saved successful login events from the first recorded event. Accounts that predate logging may show only their last known login, clearly labeled.</p>
        <?php if ($filterError !== ''): ?><div class="alert alert-warning m-3" role="alert"><?= remsLoginEscape($filterError); ?></div><?php endif; ?>
        <p class="login-logs-meta">Coverage: <?= remsLoginEscape($listingCoverage); ?> &nbsp; | &nbsp; Total: <strong><?= number_format($loginCount); ?></strong></p>
        <?php if ($loadError !== ''): ?><div class="alert alert-warning m-3" role="alert"><?= remsLoginEscape($loadError); ?></div>
        <?php elseif (!$loginRows): ?><div class="login-logs-empty"><i class="bi bi-inbox"></i><strong>No successful logins found.</strong><p>Only successful logins by users in your assigned area appear here.</p></div>
        <?php else: ?>
            <div class="login-logs-table-wrap"><table class="login-logs-table">
                <thead><tr><th>No.</th><th>Full Name</th><th>Email</th><th>User Type</th><th>Login Date / Time</th></tr></thead>
                <tbody><?php foreach ($loginRows as $n => $entry): ?><tr>
                    <td><?= ($currentPage - 1) * $recordsPerPage + $n + 1; ?></td>
                    <td><strong><?= remsLoginEscape($entry['full_name'] ?: 'User #' . (int)$entry['user_id']); ?></strong></td>
                    <td><?= remsLoginEscape($entry['email']); ?></td>
                    <td><?= remsLoginEscape(remsLoginRoleLabel((string)$entry['user_role'])); ?></td>
                    <td><?= remsLoginEscape(remsLoginDate($entry['login_at'])); ?><?php if (($entry['record_source'] ?? '') === 'latest_only'): ?><small class="login-logs-legacy">Latest known login only</small><?php endif; ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php if ($totalPages > 1): ?>
                <nav class="login-logs-pagination" aria-label="Login history pages">
                    <span>Page <?= number_format($currentPage); ?> of <?= number_format($totalPages); ?> · <?= number_format($loginCount); ?> matching logins</span>
                    <div>
                        <?php if ($currentPage > 1): ?><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, 1)); ?>">First</a><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, $currentPage - 1)); ?>">Previous</a><?php endif; ?>
                        <?php if ($currentPage < $totalPages): ?><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, $currentPage + 1)); ?>">Next</a><a class="btn login-logs-secondary" href="<?= remsLoginEscape(remsLoginPageHref($pagerParams, $totalPages)); ?>">Last</a><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
</main>
</div>
<script>
document.querySelectorAll('[data-period-form]').forEach(function(form) {
    const selector = form.querySelector('[data-period-select]');
    function syncPeriod() {
        const value = selector.value;
        ['all', 'day', 'year'].forEach(function(kind) {
            const panel = form.querySelector('[data-period-' + kind + ']');
            if (!panel) return;
            const shown = value === kind;
            panel.hidden = !shown;
            panel.querySelectorAll('input,select').forEach(function(control) {
                control.disabled = !shown;
                control.required = shown && kind !== 'all';
            });
        });
    }
    selector.addEventListener('change', syncPeriod);
    syncPeriod();
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
