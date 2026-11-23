<?php
/* Role-scoped extension report view (same shared Reports file; no new file). */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile_avatar.php';
requireLogin();

/**
 * One-click completed-records PDF for authorized institutional officers.
 * Self-contained PDF writer: no Composer, Dompdf, or additional PHP files required.
 * Uses database records; does not attempt to download or combine external HTTPS files.
 */
function remsCompletedPdfText(string $value): string
{
    $value = trim((string)(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value));
    if (function_exists('iconv')) {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($encoded !== false) $value = $encoded;
    }
    $value = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', ' ', $value) ?? $value;
    return trim($value);
}

function remsCompletedPdfEscape(string $value): string
{
    return str_replace(["\\", '(', ')'], ["\\\\", '\(', '\)'], remsCompletedPdfText($value));
}

function remsCompletedPdfLine(string $text, float $x, float $y, float $size = 10, bool $bold = false, string $rgb = '0.14 0.20 0.16'): string
{
    return $rgb . " rg BT /" . ($bold ? 'F2' : 'F1') . ' ' . number_format($size, 1, '.', '')
        . ' Tf 1 0 0 1 ' . number_format($x, 1, '.', '') . ' ' . number_format($y, 1, '.', '')
        . ' Tm (' . remsCompletedPdfEscape($text) . ") Tj ET\n";
}

function remsCompletedPdfRule(float $y): string
{
    return "0.82 0.86 0.83 RG 0.5 w 44 {$y} m 551 {$y} l S\n";
}

/** Dynamically use real date and reference columns where available. */
function remsCompletedPdfRecords(PDO $pdo, array $spec): array
{
    $table = $spec['table'];
    $id = $spec['id'];
    $title = $spec['title'];
    $owner = $spec['owner'];
    $columns = array_column($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    foreach ([$id, $title, 'status', $owner] as $needed) {
        if (!in_array($needed, $columns, true)) {
            throw new RuntimeException('Missing required completed report column: ' . $table . '.' . $needed);
        }
    }
    $reference = 'NULL';
    foreach ($spec['refs'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $reference = 't.`' . $candidate . '`';
            break;
        }
    }
    $dateParts = [];
    foreach (['completed_at', 'completion_date', 'updated_at', 'report_received_at', 'submitted_at', 'created_at'] as $candidate) {
        if (in_array($candidate, $columns, true)) $dateParts[] = 't.`' . $candidate . '`';
    }
    $dateExpr = $dateParts ? 'COALESCE(' . implode(', ', $dateParts) . ')' : 'NULL';
    $stmt = $pdo->prepare(
        'SELECT t.`' . $id . '` AS item_id, t.`' . $title . '` AS item_title, '
        . $reference . ' AS reference_code, ' . $dateExpr . ' AS completed_date, '
        . 'u.first_name, u.last_name FROM `' . $table . '` t '
        . 'LEFT JOIN users u ON u.user_id = t.`' . $owner . '` '
        . "WHERE t.status = 'Completed' ORDER BY completed_date DESC, t.`" . $id . '` DESC'
    );
    $stmt->execute();
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $person = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        $date = trim((string)($row['completed_date'] ?? ''));
        $time = $date !== '' ? strtotime($date) : false;
        $out[] = [
            'title' => trim((string)($row['item_title'] ?? '')) ?: 'Untitled record',
            'code' => trim((string)($row['reference_code'] ?? '')) ?: ($spec['prefix'] . '-' . (int)$row['item_id']),
            'person' => $person !== '' ? $person : 'Not recorded',
            'date' => $time ? date('M d, Y', $time) : 'Not recorded',
            'year' => $time ? date('Y', $time) : '',
        ];
    }
    return $out;
}

/** Measured Helvetica widths, accurate for PDF text centering and wrapping. */
function remsCompletedPdfWidth(string $value, float $size, bool $bold = false): float
{
    static $metrics = [
        'regular' => [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584],
        'bold' => [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584],
    ];
    $text = remsCompletedPdfText($value);
    $weights = $metrics[$bold ? 'bold' : 'regular'];
    $width = 0;
    for ($i = 0, $n = strlen($text); $i < $n; $i++) {
        $num = ord($text[$i]);
        $width += ($num >= 32 && $num <= 126) ? $weights[$num - 32] : 556;
    }
    return $width * $size / 1000.0;
}

function remsCompletedPdfCenter(string $value, float $y, float $size, bool $bold = false, string $rgb = '0.14 0.20 0.16'): string
{
    $start = (595.0 - remsCompletedPdfWidth($value, $size, $bold)) / 2.0;
    return remsCompletedPdfLine($value, max(20.0, $start), $y, $size, $bold, $rgb);
}

/** Break a long table cell on *actual* Helvetica width. */
function remsCompletedPdfWrap(string $value, float $available, float $size, bool $bold = false): array
{
    $text = remsCompletedPdfText($value);
    if ($text === '') return ['-'];
    $words = explode(' ', $text);
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        if ($word === '') continue;
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (remsCompletedPdfWidth($candidate, $size, $bold) <= $available) {
            $line = $candidate;
            continue;
        }
        if ($line !== '') {
            $lines[] = $line;
            $line = '';
        }
        // Hard-break exceptional long codes / unbroken words.
        while (remsCompletedPdfWidth($word, $size, $bold) > $available && strlen($word) > 1) {
            $slice = '';
            for ($i = 0, $max = strlen($word); $i < $max; $i++) {
                $test = $slice . $word[$i];
                if (remsCompletedPdfWidth($test, $size, $bold) > $available && $slice !== '') break;
                $slice = $test;
            }
            $lines[] = $slice;
            $word = substr($word, strlen($slice));
        }
        $line = $word;
    }
    if ($line !== '') $lines[] = $line;
    return $lines ?: ['-'];
}

/** Restrict both the on-page preview and the PDF to the same authorized completed tables. */
function remsCompletedPdfSpecsForRole(string $role): array
{
    $specs = [];
    if (in_array($role, ['admin', 'rps'], true)) {
        $specs['research_projects'] = ['label' => 'Completed Research Projects', 'table' => 'research_proposals', 'id' => 'research_id', 'title' => 'research_title', 'owner' => 'researcher_id', 'refs' => ['research_code', 'manuscript_number', 'research_number', 'research_no'], 'prefix' => 'RES'];
    }
    if (in_array($role, ['admin', 'extension_service_office'], true)) {
        $specs['extension_programs'] = ['label' => 'Completed Extension Programs', 'table' => 'extension_programs', 'id' => 'extension_id', 'title' => 'program_title', 'owner' => 'coordinator_id', 'refs' => ['program_code', 'project_code', 'proposal_code'], 'prefix' => 'EXT'];
        $specs['monitoring_reports'] = ['label' => 'Completed Monitoring Reports', 'table' => 'extension_monitoring_requests', 'id' => 'request_id', 'title' => 'project_title', 'owner' => 'requester_id', 'refs' => ['request_code'], 'prefix' => 'MR'];
        $specs['services'] = ['label' => 'Completed Training and Technical Services', 'table' => 'extension_service_requests', 'id' => 'request_id', 'title' => 'service_title', 'owner' => 'requester_id', 'refs' => ['request_code'], 'prefix' => 'SR'];
    }
    return $specs;
}

/** One standardized table-based PDF (A4 portrait; repeated headers; automatic page breaks). */
function remsCompletedPdfRender(array $sections, string $scope, string $year = ''): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $generated = $now->format('M d, Y - h:i A');
    $pages = [];
    $index = -1;
    $y = 0.0;
    $left = 44.0;
    $usableWidth = 507.0;
    // Columns: # | title | reference | proponent | completed date (A4 width 507pt)
    $widths = [27.0, 179.0, 100.0, 128.0, 73.0];
    $headers = ['NO.', 'PROJECT / RECORD', 'REFERENCE', 'PROPONENT', 'COMPLETED'];
    $total = 0;
    foreach ($sections as $section) $total += count($section['records']);

    $fill = static function (float $x, float $bottom, float $w, float $h, string $rgb): string {
        return $rgb . ' rg ' . sprintf('%.2f %.2f %.2f %.2f re f', $x, $bottom, $w, $h) . "\n";
    };
    $rule = static function (float $x1, float $y1, float $x2, float $y2, string $rgb = '0.87 0.90 0.87'): string {
        return $rgb . ' RG 0.45 w ' . sprintf('%.2f %.2f m %.2f %.2f l S', $x1, $y1, $x2, $y2) . "\n";
    };
    $newPage = static function () use (&$pages, &$index, &$y, $scope, $generated, $year, $total, $left, $usableWidth, $rule): void {
        $index++;
        $pages[$index] = '';
        $pages[$index] .= remsCompletedPdfCenter('Republic of the Philippines', 803, 9.5);
        $pages[$index] .= remsCompletedPdfCenter('AKLAN STATE UNIVERSITY', 785, 13.2, true, '0.00 0.34 0.16');
        $pages[$index] .= remsCompletedPdfCenter('Banga, Aklan', 769, 9.5);
        $pages[$index] .= $rule($left, 753, $left + $usableWidth, 753);
        $pages[$index] .= remsCompletedPdfCenter('COMPLETED REPORTS SUMMARY', 727, 13.5, true);
        $pages[$index] .= remsCompletedPdfCenter('Coverage: ' . $scope . ($year !== '' ? ' | Year: ' . $year : ' | All Years'), 709, 8.6);
        $pages[$index] .= remsCompletedPdfCenter('Generated: ' . $generated, 694, 8.2, false, '0.39 0.44 0.40');
        $pages[$index] .= $rule($left, 682, $left + $usableWidth, 682);
        $pages[$index] .= remsCompletedPdfLine('Total completed records: ' . number_format($total), $left, 660, 9.5, true);
        $y = 639.0;
    };
    $newPage();

    $sectionHeader = static function (string $label, int $count, bool $continued = false) use (&$pages, &$index, &$y, $fill, $rule, $headers, $widths, $left, $usableWidth): void {
        $caption = $label . ' (' . $count . ')' . ($continued ? ' - continued' : '');
        $pages[$index] .= $fill($left, $y - 23, $usableWidth, 25, '0.92 0.96 0.93');
        $pages[$index] .= remsCompletedPdfLine($caption, $left + 9, $y - 14, 9.1, true, '0.02 0.33 0.17');
        $y -= 28;
        $pages[$index] .= $fill($left, $y - 23, $usableWidth, 24, '0.02 0.40 0.19');
        $xx = $left;
        foreach ($headers as $col => $header) {
            $pages[$index] .= remsCompletedPdfLine($header, $xx + 5, $y - 14, 7.4, true, '1 1 1');
            $xx += $widths[$col];
        }
        $y -= 24;
        $pages[$index] .= $rule($left, $y, $left + $usableWidth, $y);
    };

    $visible = array_values(array_filter($sections, static fn(array $section): bool => count($section['records']) > 0));
    if (!$visible) {
        $pages[$index] .= remsCompletedPdfCenter('No completed records available for the selected criteria.', 602, 9.2);
    }
    foreach ($visible as $section) {
        $records = $section['records'];
        // Keep a section caption, table column header and first data row together.
        if ($y < 150) $newPage();
        $sectionHeader($section['label'], count($records));
        foreach ($records as $row => $record) {
            $data = [
                (string)($row + 1),
                (string)($record['title'] ?? '-'),
                (string)($record['code'] ?? '-'),
                (string)($record['person'] ?? '-'),
                (string)($record['date'] ?? '-'),
            ];
            $fontSize = 8.1;
            $cells = [];
            $maxLines = 1;
            foreach ($data as $col => $cell) {
                $cells[$col] = remsCompletedPdfWrap($cell, $widths[$col] - 10, $fontSize, $col === 1);
                $maxLines = max($maxLines, count($cells[$col]));
            }
            $rowHeight = max(29.0, 12.0 + $maxLines * 11.0);
            if ($y - $rowHeight < 65) {
                $newPage();
                $sectionHeader($section['label'], count($records), true);
            }
            if ($row % 2 === 1) $pages[$index] .= $fill($left, $y - $rowHeight, $usableWidth, $rowHeight, '0.975 0.985 0.976');
            $xx = $left;
            foreach ($cells as $col => $lines) {
                foreach ($lines as $li => $line) {
                    $pages[$index] .= remsCompletedPdfLine($line, $xx + 5, $y - 14 - $li * 11, $fontSize, $col === 1);
                }
                $xx += $widths[$col];
            }
            $y -= $rowHeight;
            $pages[$index] .= $rule($left, $y, $left + $usableWidth, $y);
        }
        $y -= 17;
    }
    // Footer and page numbers are added after total pages is known.
    $totalPages = count($pages);
    foreach ($pages as $page => &$content) {
        $content .= $rule($left, 49, $left + $usableWidth, 49);
        $content .= remsCompletedPdfLine('ASU-REMS | Completed records | ' . $now->format('M d, Y'), $left, 34, 7.4, false, '0.42 0.47 0.43');
        $pageString = 'Page ' . ($page + 1) . ' of ' . $totalPages;
        $content .= remsCompletedPdfLine($pageString, $left + $usableWidth - remsCompletedPdfWidth($pageString, 7.4), 34, 7.4, false, '0.42 0.47 0.43');
    }
    unset($content);

    // Valid PDF 1.4: standard Helvetica fonts, no external PHP library required.
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = [];
    foreach ($pages as $i => $page) $kids[] = (5 + $i * 2) . ' 0 R';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    foreach ($pages as $i => $content) {
        $pageId = 5 + $i * 2;
        $streamId = $pageId + 1;
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
            . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $streamId . ' 0 R >>';
        $objects[$streamId] = '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "endstream";
    }
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    $pdf .= 'trailer' . "\n<< /Size " . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF\n";
    return $pdf;
}

if (($_GET['download_completed_pdf'] ?? '') === '1') {
    $pdfRole = (string)(currentUser()['role'] ?? '');
    if (!in_array($pdfRole, ['admin', 'rps', 'extension_service_office'], true)) {
        http_response_code(403);
        exit('You are not authorized to generate institutional completed reports.');
    }
    $pdfType = trim((string)($_GET['pdf_type'] ?? 'all'));
    $pdfYear = trim((string)($_GET['pdf_year'] ?? ''));
    $permitted = array_merge(['all'], array_keys(remsCompletedPdfSpecsForRole($pdfRole)));
    if (!in_array($pdfType, $permitted, true) ||
        ($pdfYear !== '' && (!preg_match('/^\d{4}$/', $pdfYear) || (int)$pdfYear < 1900 || (int)$pdfYear > 2100))) {
        http_response_code(400);
        exit('Invalid completed report selection.');
    }
    try {
        $specs = remsCompletedPdfSpecsForRole($pdfRole);
        $pdfSections = [];
        foreach ($specs as $key => $spec) {
            if ($pdfType !== 'all' && $pdfType !== $key) continue;
            $records = remsCompletedPdfRecords($pdo, $spec);
            if ($pdfYear !== '') {
                $records = array_values(array_filter($records, static fn(array $r): bool => ($r['year'] ?? '') === $pdfYear));
            }
            $pdfSections[] = ['label' => $spec['label'], 'records' => $records];
        }
        $pdfScope = match ($pdfRole) {
            'admin' => 'Research and Extension (Institution-wide)',
            'rps' => 'Research (Institution-wide)',
            default => 'Extension (Institution-wide)',
        };
        $pdfBytes = remsCompletedPdfRender($pdfSections, $pdfScope, $pdfYear);
    } catch (Throwable $exception) {
        error_log('Completed reports PDF generation failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Could not generate a complete PDF report. Please contact the system administrator.');
    }
    while (ob_get_level() > 0) ob_end_clean();
    $pdfFileName = 'ASU_REMS_Completed_Reports_' . ($pdfType === 'all' ? 'All' : $pdfType)
        . ($pdfYear !== '' ? '_' . $pdfYear : '') . '_' . date('Ymd') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFileName . '"');
    header('Content-Length: ' . strlen($pdfBytes));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $pdfBytes;
    exit;
}

$reportsSharedRole = (string)(currentUser()['role'] ?? '');
if (in_array($reportsSharedRole, ['extensionist', 'extension_coordinator', 'extension_service_office'], true)) {
/**
 * ASU-REMS — Extension Reports Center
 * Integrated extension-role view in: reports/index.php
 *
 * ESO: reports and submitted records from every Extensionist.
 * Extensionist/coordinator: only their own reports and records.
 * Read-only overview; the original proposal/service/monitoring workflows remain unchanged.
 */
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$user = currentUser();
$role = (string)($user['role'] ?? '');
$isOffice = $role === 'extension_service_office';
$isProponent = in_array($role, ['extensionist', 'extension_coordinator'], true);



$pageTitle = ($isOffice ? 'Extension Reports Overview' : 'My Extension Reports') . ' | ASU-REMS';
$activePage = 'reports';
$userId = (int)($user['user_id'] ?? 0);

function extReportEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Extension Reports badge colors follow Extension Management (extension/index.php).
 * This helper is intentionally limited to extension-role screens; research unchanged. */
function extReportStatusColorClass(string $status): string
{
    $normalized = strtolower(trim($status));
    if (str_contains($normalized, 'revision') || str_contains($normalized, 'rejected')) {
        return 'extension-reports-status-revision';
    }
    return match ($normalized) {
        'approved', 'completed', 'accepted' => 'extension-reports-status-approved',
        'draft', 'archived' => 'extension-reports-status-draft',
        'submitted', 'acknowledged', 'assigned', 'received',
        'under review', 'uerc technical review', 'university in-house review',
        'for endorsement', 'for ladco/adco approval', 'for bor notation',
        'for certification', 'for uerc technical review', 'for university in-house review',
        'for approval', 'report received', 'monitoring conducted'
            => 'extension-reports-status-review',
        'scheduled', 'ongoing', 'in progress', 'report submitted',
        'awaiting eso completion', 'notice to proceed'
            => 'extension-reports-status-ongoing',
        default => 'extension-reports-status-neutral',
    };
}

function extReportDate($value, string $format = 'M d, Y'): string
{
    if (!$value) return '—';
    $time = strtotime((string)$value);
    return $time === false ? '—' : date($format, $time);
}

function extReportHttps($value): ?string
{
    $url = trim((string)$value);
    if ($url === '') return null;
    $parts = parse_url($url);
    if (!$parts || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) return null;
    return $url;
}

function extReportName(array $row, string $fallback = 'Extensionist'): string
{
    $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    return $name !== '' ? $name : $fallback;
}

function extReportFirst(array $row, array $keys, string $fallback = ''): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') return trim((string)$row[$key]);
    }
    return $fallback;
}

function extReportColumns(PDO $pdo, string $table): array
{
    if (!preg_match('/^[a-z_]+$/', $table)) return [];
    $query = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    return array_column($query->fetchAll(PDO::FETCH_ASSOC), 'Field');
}

function extReportColumnName(array $available, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $available, true)) return $candidate;
    }
    return null;
}

function extReportSort(array &$rows): void
{
    usort($rows, static function (array $a, array $b): int {
        $aTime = strtotime((string)($a['sort_date'] ?? '')) ?: 0;
        $bTime = strtotime((string)($b['sort_date'] ?? '')) ?: 0;
        return $bTime <=> $aTime;
    });
}

$reportDocuments = [];
$programRecords = [];
$completedServices = [];
$loadingMessages = [];

/* Monitoring reports are the verified document-submission workflow in Section 4.3. */
try {
    $sql = "SELECT mr.request_id, mr.request_code, mr.project_title, mr.status,
                   mr.monitoring_report_url, mr.report_submitted_at, mr.updated_at,
                   mr.requester_id, u.first_name, u.last_name
            FROM extension_monitoring_requests mr
            LEFT JOIN users u ON u.user_id = mr.requester_id
            WHERE mr.monitoring_report_url IS NOT NULL
              AND TRIM(mr.monitoring_report_url) <> ''";
    $params = [];
    if (!$isOffice) {
        $sql .= ' AND mr.requester_id = ?';
        $params[] = $userId;
    }
    $sql .= ' ORDER BY COALESCE(mr.report_submitted_at, mr.updated_at) DESC, mr.request_id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $url = extReportHttps($item['monitoring_report_url']);
        if ($url === null) continue;
        $reportDocuments[] = [
            'type' => 'Monitoring Report',
            'title' => (string)($item['project_title'] ?: 'Monitoring Activity'),
            'code' => (string)($item['request_code'] ?: 'MR-' . $item['request_id']),
            'owner' => extReportName($item),
            'date' => $item['report_submitted_at'] ?: $item['updated_at'],
            'sort_date' => $item['report_submitted_at'] ?: $item['updated_at'],
            'status' => (string)$item['status'],
            'url' => $url,
            'record_url' => BASE_URL . '/extension/monitoring.php?section=' .
                ($isOffice ? 'monitoring_reports' : 'my_monitoring_requests') . '&view=' . (int)$item['request_id'],
        ];
    }
} catch (Throwable $e) {
    error_log('Extension Reports: monitoring reports: ' . $e->getMessage());
    $loadingMessages[] = 'Monitoring report records could not be loaded.';
}

/* The program/submission overview uses the same extension_programs table as the dashboard. */
try {
    $columns = extReportColumns($pdo, 'extension_programs');
    if ($columns && in_array('coordinator_id', $columns, true) && in_array('status', $columns, true)) {
        $dateColumn = extReportColumnName($columns, ['submitted_at', 'created_at', 'updated_at']);
        $idColumn = extReportColumnName($columns, ['program_id', 'extension_id', 'id']);
        $order = $dateColumn ? 'ep.`' . $dateColumn . '` DESC' : ($idColumn ? 'ep.`' . $idColumn . '` DESC' : 'ep.`status`');
        $sql = "SELECT ep.*, u.first_name, u.last_name
                FROM extension_programs ep
                LEFT JOIN users u ON u.user_id = ep.coordinator_id
                WHERE ep.status <> 'Draft'";
        $params = [];
        if (!$isOffice) {
            $sql .= ' AND ep.coordinator_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY ' . $order;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Discover real report-link columns only, rather than assuming a schema.
        $programReportColumns = array_values(array_filter($columns, static function ($column): bool {
            return (bool)preg_match('/report/i', (string)$column)
                && (bool)preg_match('/(?:url|link)/i', (string)$column);
        }));

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $title = extReportFirst($item, ['program_title', 'project_title', 'extension_program_title', 'extension_title', 'program_name', 'project_name', 'extension_program_name', 'title', 'proposal_title', 'name'], 'Extension Program');
            $code = extReportFirst($item, ['program_code', 'proposal_code', 'project_code', 'reference_no', 'extension_code', 'proposal_no', 'program_no'], '—');
            $date = extReportFirst($item, ['submitted_at', 'created_at', 'updated_at']);
            $programRecords[] = [
                'title' => $title,
                'code' => $code,
                'owner' => extReportName($item),
                'date' => $date,
                'status' => (string)$item['status'],
            ];
            foreach ($programReportColumns as $column) {
                $url = extReportHttps($item[$column] ?? null);
                if ($url === null) continue;
                $label = ucwords(str_replace('_', ' ', preg_replace('/(?:_url|_link)$/i', '', $column)));
                $reportDocuments[] = [
                    'type' => $label,
                    'title' => $title,
                    'code' => $code,
                    'owner' => extReportName($item),
                    'date' => extReportFirst($item, [str_replace(['_url', '_link'], '_submitted_at', $column), 'updated_at', 'submitted_at', 'created_at']),
                    'sort_date' => extReportFirst($item, ['updated_at', 'submitted_at', 'created_at']),
                    'status' => (string)$item['status'],
                    'url' => $url,
                    'record_url' => BASE_URL . '/extension/index.php',
                ];
            }
        }
    }
} catch (Throwable $e) {
    error_log('Extension Reports: program submissions: ' . $e->getMessage());
    $loadingMessages[] = 'Extension program records could not be loaded.';
}

/* Extension accomplishment/report documents recorded in extension_documents.
   A report is shown only if an actual HTTPS link is stored, and access remains role-scoped. */
try {
    $documentColumns = extReportColumns($pdo, 'extension_documents');
    $reportUrlField = extReportColumnName($documentColumns, [
        'document_url', 'document_link', 'file_url', 'file_link',
        'url', 'link', 'https_url', 'file_path'
    ]);
    $reportTypeField = extReportColumnName($documentColumns, ['document_type', 'type']);
    $documentDateField = extReportColumnName($documentColumns, ['uploaded_at', 'created_at', 'updated_at', 'submitted_at']);
    if ($reportUrlField !== null && $reportTypeField !== null) {
        $dateExpression = $documentDateField !== null ? 'ed.`' . $documentDateField . '`' : 'NULL';
        $sql = 'SELECT ep.extension_id, ep.program_title, ep.status, ep.coordinator_id, '
            . 'ed.`' . $reportUrlField . '` AS submitted_url, '
            . 'ed.`' . $reportTypeField . '` AS submitted_type, '
            . $dateExpression . ' AS report_date, '
            . 'u.first_name, u.last_name '
            . 'FROM extension_documents ed '
            . 'INNER JOIN extension_programs ep ON ep.extension_id = ed.extension_id '
            . 'LEFT JOIN users u ON u.user_id = ep.coordinator_id '
            . 'WHERE ed.`' . $reportTypeField . '` LIKE ? AND ep.status <> ?';
        $params = ['%Report%', 'Draft'];
        if (!$isOffice) {
            $sql .= ' AND ep.coordinator_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY ep.extension_id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $url = extReportHttps($item['submitted_url'] ?? null);
            if ($url === null) continue;
            $reportDocuments[] = [
                'type' => (string)($item['submitted_type'] ?: 'Extension Report'),
                'title' => (string)($item['program_title'] ?: 'Extension Program'),
                'code' => 'EXT-' . (int)$item['extension_id'],
                'owner' => extReportName($item),
                'date' => $item['report_date'],
                'sort_date' => $item['report_date'],
                'status' => (string)$item['status'],
                'url' => $url,
                'record_url' => BASE_URL . '/extension/index.php',
            ];
        }
    }
} catch (Throwable $e) {
    error_log('Extension Reports: uploaded report documents: ' . $e->getMessage());
    $loadingMessages[] = 'Some uploaded extension report documents could not be loaded.';
}

/* Completed 4.2 service requests are read-only reference records, not report uploads. */
try {
    $sql = "SELECT sr.request_id, sr.request_code, sr.service_title, sr.organization_name,
                   sr.status, sr.completed_at, sr.requester_id, u.first_name, u.last_name
            FROM extension_service_requests sr
            LEFT JOIN users u ON u.user_id = sr.requester_id
            WHERE sr.status = 'Completed'";
    $params = [];
    if (!$isOffice) {
        $sql .= ' AND sr.requester_id = ?';
        $params[] = $userId;
    }
    $sql .= ' ORDER BY sr.completed_at DESC, sr.request_id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $completedServices[] = [
            'id' => (int)$item['request_id'],
            'title' => (string)($item['service_title'] ?: 'Training / Technical Service'),
            'code' => (string)($item['request_code'] ?: 'SR-' . $item['request_id']),
            'organization' => (string)($item['organization_name'] ?: '—'),
            'owner' => extReportName($item),
            'date' => $item['completed_at'],
            'status' => 'Completed',
            'record_url' => BASE_URL . '/extension/services.php?section=' .
                ($isOffice ? 'completed_services' : 'my_service_requests') . '&view=' . (int)$item['request_id'],
        ];
    }
} catch (Throwable $e) {
    error_log('Extension Reports: completed services: ' . $e->getMessage());
    $loadingMessages[] = 'Completed service records could not be loaded.';
}

$distinctReports = [];
foreach ($reportDocuments as $report) {
    $key = hash('sha256', (string)$report['url'] . '|' . (string)$report['title'] . '|' . (string)$report['type']);
    $distinctReports[$key] = $report;
}
$reportDocuments = array_values($distinctReports);
extReportSort($reportDocuments);

$counts = [
    'reports' => count($reportDocuments),
    'proposals' => count($programRecords),
    'services' => count($completedServices),
    'completed' => count(array_filter($programRecords, static fn(array $r): bool => $r['status'] === 'Completed')),
];

$search = trim((string)($_GET['q'] ?? ''));
$filterType = trim((string)($_GET['type'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? ''));

$types = array_values(array_unique(array_column($reportDocuments, 'type')));
sort($types);
$statuses = array_values(array_unique(array_column($reportDocuments, 'status')));
sort($statuses);
if (!in_array($filterType, $types, true)) $filterType = '';
if (!in_array($filterStatus, $statuses, true)) $filterStatus = '';

$visibleReports = array_values(array_filter($reportDocuments, static function (array $item) use ($search, $filterType, $filterStatus): bool {
    if ($filterType !== '' && $item['type'] !== $filterType) return false;
    if ($filterStatus !== '' && $item['status'] !== $filterStatus) return false;
    if ($search !== '') {
        $text = implode(' ', [$item['title'], $item['code'], $item['owner'], $item['type']]);
        if (stripos($text, $search) === false) return false;
    }
    return true;
}));

$completedPreview = $isOffice && (($_GET['completed_report_preview'] ?? '') === '1');
$completedPreviewType = trim((string)($_GET['pdf_type'] ?? 'all'));
$completedPreviewYear = trim((string)($_GET['pdf_year'] ?? ''));
$completedPreviewSections = [];
$completedPreviewCount = 0;
if ($completedPreview) {
    $typesAvailable = remsCompletedPdfSpecsForRole('extension_service_office');
    if (!in_array($completedPreviewType, array_merge(['all'], array_keys($typesAvailable)), true)) {
        $completedPreviewType = 'all';
    }
    if ($completedPreviewYear !== '' && (!preg_match('/^\d{4}$/', $completedPreviewYear) || (int)$completedPreviewYear < 1900 || (int)$completedPreviewYear > 2100)) {
        $completedPreviewYear = '';
    }
    try {
        foreach ($typesAvailable as $typeKey => $typeSpec) {
            if ($completedPreviewType !== 'all' && $typeKey !== $completedPreviewType) continue;
            $recordsForPreview = remsCompletedPdfRecords($pdo, $typeSpec);
            if ($completedPreviewYear !== '') {
                $recordsForPreview = array_values(array_filter($recordsForPreview, static fn(array $row): bool => ($row['year'] ?? '') === $completedPreviewYear));
            }
            $completedPreviewCount += count($recordsForPreview);
            $completedPreviewSections[] = ['label' => $typeSpec['label'], 'records' => $recordsForPreview];
        }
    } catch (Throwable $e) {
        error_log('Extension completed report preview failed: ' . $e->getMessage());
        $loadingMessages[] = 'Could not load the completed report preview.';
        $completedPreviewSections = [];
    }
}

$initials = '';
if (!empty($user['first_name'])) $initials .= strtoupper(substr((string)$user['first_name'], 0, 1));
if (!empty($user['last_name'])) $initials .= strtoupper(substr((string)$user['last_name'], 0, 1));

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<style>
/* =========================================================
   TOPBAR PHOTO — MATCH DASHBOARD.PHP EXACTLY
   Same 46px circular avatar and single soft dashboard shadow;
   NO outer yellow ring, custom halo, scaling, or animation.
   All other page styles and interactive controls are unchanged.
   ========================================================= */
html body .main-page > header.asu-topbar .dashboard-avatar {
    box-sizing: border-box !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 46px !important;
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255, 213, 31, .25) !important;
    animation: none !important;
    transition: none !important;
    transform: none !important;
}
html body .main-page > header.asu-topbar .dashboard-avatar img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    max-height: none !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    object-fit: cover !important;
}
@media (max-width: 480px) {
    html body .main-page > header.asu-topbar .dashboard-avatar {
        flex: 0 0 40px !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
    }
}
</style>



<div class="main-page">
    <header class="asu-topbar extension-reports-topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
            <div class="extension-reports-brand"><div><strong>Research &amp; Extension Management System</strong><span>Aklan State University</span></div></div>
        </div>
        <div class="topbar-user"><div class="topbar-user-text"><strong><?= extReportEsc(fullName($user)); ?></strong><span><?= extReportEsc(roleLabel($role)); ?></span></div><?= remsProfileAvatarHtml($pdo, $user, $initials ?: 'U', 'dashboard-avatar'); ?></div>
    </header>
    <main class="page-content extension-reports-page">
        <div class="extension-reports-shell">
            <section class="extension-reports-hero">
                <div><span class="extension-reports-kicker">EXTENSION REPORTS</span>
                    <h1><?= $isOffice ? 'Extension Reports Overview' : 'My Extension Reports'; ?></h1>
                    <p><?= $isOffice
                        ? 'Institution-wide reports and submitted extension records from all Extensionists.'
                        : 'Your submitted extension reports, program submissions, and completed service records.'; ?></p>
                </div>
                <div class="extension-reports-hero-actions">
                    <a class="btn extension-reports-back" href="<?= BASE_URL; ?>/extension/index.php"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to Extension</a>
                </div>
            </section>

            <?php if ($isOffice): ?>
                <section class="extension-reports-generator" aria-labelledby="extCompletedPdfTitle">
                    <div class="extension-reports-generator-header">
                        <span class="extension-reports-panel-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span>
                        <h2 id="extCompletedPdfTitle">Generate Completed Reports</h2>
                    </div>
                    <form class="extension-reports-generator-form" method="get" action="<?= BASE_URL; ?>/reports/index.php">
                        <input type="hidden" name="completed_report_preview" value="1">
                        <div>
                            <label for="completedPdfType">Report Type</label>
                            <select class="form-select" id="completedPdfType" name="pdf_type">
                                <option value="all" <?= $completedPreviewType === 'all' ? 'selected' : ''; ?>>All Completed Extension Records</option>
                                <option value="extension_programs" <?= $completedPreviewType === 'extension_programs' ? 'selected' : ''; ?>>Completed Extension Programs</option>
                                <option value="monitoring_reports" <?= $completedPreviewType === 'monitoring_reports' ? 'selected' : ''; ?>>Completed Monitoring Reports</option>
                                <option value="services" <?= $completedPreviewType === 'services' ? 'selected' : ''; ?>>Completed Training &amp; Technical Services</option>
                            </select>
                        </div>
                        <div>
                            <label for="completedPdfYear">Year</label>
                            <select class="form-select" id="completedPdfYear" name="pdf_year">
                                <option value="" <?= $completedPreviewYear === '' ? 'selected' : ''; ?>>All Years</option>
                                <?php for ($pdfOptionYear = (int)date('Y') + 1; $pdfOptionYear >= 2015; $pdfOptionYear--): ?>
                                    <option value="<?= $pdfOptionYear; ?>" <?= $completedPreviewYear === (string)$pdfOptionYear ? 'selected' : ''; ?>><?= $pdfOptionYear; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="extension-reports-generator-actions">
                            <button class="btn extension-reports-generator-submit" type="submit"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Generate Report</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php foreach ($loadingMessages as $message): ?>
                <div class="alert alert-warning" role="status"><?= extReportEsc($message); ?></div>
            <?php endforeach; ?>

            <?php if ($completedPreview): ?>
                <section class="extension-reports-panel" id="completedReportPreview" aria-labelledby="completedPreviewTitle">
                    <div class="extension-reports-panel-head">
                        <div class="extension-reports-panel-title">
                            <span class="extension-reports-panel-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
                            <h2 id="completedPreviewTitle">Generated Completed Report</h2>
                        </div>
                        <div class="extension-reports-preview-actions">
                            <a class="btn extension-reports-reset" href="<?= BASE_URL; ?>/reports/index.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Reports</a>
                            <a class="btn extension-reports-generator-submit" href="<?= BASE_URL; ?>/reports/index.php?<?= extReportEsc(http_build_query(['download_completed_pdf' => '1', 'pdf_type' => $completedPreviewType, 'pdf_year' => $completedPreviewYear])); ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Download PDF</a>
                        </div>
                    </div>
                    <div class="extension-reports-preview-meta">
                        Completed records only &nbsp; | &nbsp; <?= $completedPreviewYear !== '' ? extReportEsc($completedPreviewYear) : 'All years'; ?> &nbsp; | &nbsp; Total: <strong><?= number_format($completedPreviewCount); ?></strong>
                    </div>
                    <?php if (!$completedPreviewCount): ?>
                        <div class="extension-reports-empty compact"><i class="bi bi-inbox" aria-hidden="true"></i><strong>No completed records found for your selection.</strong></div>
                    <?php else: ?>
                        <?php foreach ($completedPreviewSections as $previewSection): ?>
                            <?php if (!$previewSection['records']) continue; ?>
                            <h3 class="extension-reports-preview-section-heading"><?= extReportEsc($previewSection['label']); ?></h3>
                            <div class="extension-reports-table-wrap">
                                <table class="extension-reports-table extension-reports-completed-table">
                                    <thead><tr><th>No.</th><th>Project / Record</th><th>Reference</th><th>Extensionist</th><th>Date Completed</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($previewSection['records'] as $previewIndex => $previewRecord): ?>
                                            <tr>
                                                <td><?= $previewIndex + 1; ?></td>
                                                <td><strong><?= extReportEsc($previewRecord['title']); ?></strong></td>
                                                <td><?= extReportEsc($previewRecord['code']); ?></td>
                                                <td><?= extReportEsc($previewRecord['person']); ?></td>
                                                <td><?= extReportEsc($previewRecord['date']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            <?php else: ?>
            <section class="extension-reports-panel" aria-labelledby="extensionReportsListTitle">
                <div class="extension-reports-panel-head"><div class="extension-reports-panel-title"><span class="extension-reports-panel-icon"><i class="bi bi-file-earmark-check" aria-hidden="true"></i></span><h2 id="extensionReportsListTitle"><?= $isOffice ? 'Submitted Reports from Extensionists' : 'My Submitted Reports'; ?></h2></div></div>
                <form class="extension-reports-filters" method="get" action="<?= BASE_URL; ?>/reports/index.php">
                    <div class="extension-reports-filter-search"><label for="extensionReportSearch">Search reports</label><div class="extension-reports-search-wrap"><i class="bi bi-search" aria-hidden="true"></i><input id="extensionReportSearch" class="form-control" type="search" name="q" value="<?= extReportEsc($search); ?>" placeholder="Report, code<?= $isOffice ? ', Extensionist' : ''; ?>"></div></div>
                    <div><label for="extensionReportType">Report type</label><select id="extensionReportType" class="form-select" name="type"><option value="">All report types</option><?php foreach ($types as $type): ?><option value="<?= extReportEsc($type); ?>" <?= $filterType === $type ? 'selected' : ''; ?>><?= extReportEsc($type); ?></option><?php endforeach; ?></select></div>
                    <div><label for="extensionReportStatus">Status</label><select id="extensionReportStatus" class="form-select" name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= extReportEsc($status); ?>" <?= $filterStatus === $status ? 'selected' : ''; ?>><?= extReportEsc($status); ?></option><?php endforeach; ?></select></div>
                    <div class="extension-reports-filter-actions"><button class="btn extension-reports-filter-btn" type="submit"><i class="bi bi-funnel-fill" aria-hidden="true"></i>Filter</button><a class="btn extension-reports-reset" href="<?= BASE_URL; ?>/reports/index.php">Reset</a></div>
                </form>
                <?php if (!$visibleReports): ?>
                    <div class="extension-reports-empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>No submitted reports found.</strong><p><?= $search !== '' || $filterType !== '' || $filterStatus !== '' ? 'Try resetting your filters.' : ($isOffice ? 'Reports submitted by Extensionists will appear here.' : 'Your submitted monitoring or extension program reports will appear here.'); ?></p></div>
                <?php else: ?>
                    <div class="extension-reports-table-wrap"><table class="extension-reports-table extension-reports-submitted-table"><thead><tr><th>Report / Project</th><th>Report Type</th><?php if ($isOffice): ?><th>Extensionist</th><?php endif; ?><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
                        <?php foreach ($visibleReports as $item): ?><tr>
                            <td><strong><?= extReportEsc($item['title']); ?></strong><span class="extension-reports-ref"><?= extReportEsc($item['code']); ?></span></td>
                            <td><?= extReportEsc($item['type']); ?></td>
                            <?php if ($isOffice): ?><td><?= extReportEsc($item['owner']); ?></td><?php endif; ?>
                            <td><?= extReportEsc(extReportDate($item['date'])); ?></td>
                            <td><span class="extension-reports-status <?= extReportEsc(extReportStatusColorClass((string)$item['status'])); ?>"><?= extReportEsc($item['status']); ?></span></td>
                            <td><a class="btn extension-reports-eye" href="<?= extReportEsc($item['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open submitted report" title="Open submitted report"><i class="bi bi-eye" aria-hidden="true"></i></a></td>
                        </tr><?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </section>

            <section class="extension-reports-panel" aria-labelledby="extensionProgramRecordsTitle">
                <div class="extension-reports-panel-head"><div class="extension-reports-panel-title"><span class="extension-reports-panel-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span><h2 id="extensionProgramRecordsTitle"><?= $isOffice ? 'All Extension Program Submissions' : 'My Extension Program Submissions'; ?></h2></div><span class="extension-reports-count"><?= number_format($counts['proposals']); ?> records</span></div>
                <p class="extension-reports-section-note">Submitted program records are shown separately from uploaded report documents.</p>
                <?php if (!$programRecords): ?><div class="extension-reports-empty compact"><i class="bi bi-folder2-open" aria-hidden="true"></i><strong>No submitted extension programs.</strong></div><?php else: ?>
                    <div class="extension-reports-table-wrap"><table class="extension-reports-table extension-reports-programs-table"><thead><tr><th>Program / Project</th><?php if ($isOffice): ?><th>Extensionist</th><?php endif; ?><th>Date Submitted</th><th>Status</th></tr></thead><tbody>
                        <?php foreach ($programRecords as $item): ?><tr><td><strong><?= extReportEsc($item['title']); ?></strong><span class="extension-reports-ref"><?= extReportEsc($item['code']); ?></span></td><?php if ($isOffice): ?><td><?= extReportEsc($item['owner']); ?></td><?php endif; ?><td><?= extReportEsc(extReportDate($item['date'])); ?></td><td><span class="extension-reports-status <?= extReportEsc(extReportStatusColorClass((string)$item['status'])); ?>"><?= extReportEsc($item['status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </section>

            <section class="extension-reports-panel" aria-labelledby="extensionServiceRecordsTitle">
                <div class="extension-reports-panel-head"><div class="extension-reports-panel-title"><span class="extension-reports-panel-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></span><h2 id="extensionServiceRecordsTitle"><?= $isOffice ? 'Completed Services Across Extensionists' : 'My Completed Services'; ?></h2></div><span class="extension-reports-count"><?= number_format($counts['services']); ?> records</span></div>
                <?php if (!$completedServices): ?><div class="extension-reports-empty compact"><i class="bi bi-inbox" aria-hidden="true"></i><strong>No completed service records yet.</strong></div><?php else: ?>
                    <div class="extension-reports-table-wrap"><table class="extension-reports-table extension-reports-services-table"><thead><tr><th>Service Request</th><th>Organization</th><?php if ($isOffice): ?><th>Extensionist</th><?php endif; ?><th>Date Completed</th><th>Status</th><th>Action</th></tr></thead><tbody>
                    <?php foreach ($completedServices as $item): ?><tr><td><strong><?= extReportEsc($item['title']); ?></strong><span class="extension-reports-ref"><?= extReportEsc($item['code']); ?></span></td><td><?= extReportEsc($item['organization']); ?></td><?php if ($isOffice): ?><td><?= extReportEsc($item['owner']); ?></td><?php endif; ?><td><?= extReportEsc(extReportDate($item['date'])); ?></td><td><span class="extension-reports-status extension-reports-status-approved">Completed</span></td><td><a class="btn extension-reports-eye" href="<?= extReportEsc($item['record_url']); ?>" aria-label="View completed service" title="View completed service"><i class="bi bi-eye" aria-hidden="true"></i></a></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<style>
/* Aligned with extension/services.php and extension/monitoring.php. No animations. */
.extension-reports-page{padding:18px 28px 30px!important;background:radial-gradient(circle at top right,rgba(255,210,31,.10),transparent 34%),linear-gradient(180deg,#fafcf8 0%,#f4f8f1 100%)!important}
.extension-reports-shell{width:100%;font-family:"Century Gothic",Arial,sans-serif;color:#1f2922}
.extension-reports-topbar{min-height:76px!important;height:76px!important;padding:0 30px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;background:#fff!important;border-bottom:1px solid rgba(0,122,51,.12)!important}
.extension-reports-topbar .topbar-left,.extension-reports-topbar .topbar-user{display:flex;align-items:center;gap:14px}
.extension-reports-brand{display:flex;align-items:center;gap:14px}
.extension-reports-brand:before{content:"";width:3px;height:38px;border-radius:999px;background:#007a33}
.extension-reports-brand strong{display:block;font-weight:800;font-size:17px;color:#101810;line-height:1.18}
.extension-reports-brand span{display:block;font-size:13px;color:#657068;margin-top:4px}
.extension-reports-topbar .topbar-user-text{text-align:right}
.extension-reports-topbar .topbar-user-text strong{display:block;font-size:15px;color:#101810}
.extension-reports-topbar .topbar-user-text span{display:block;font-size:12px;color:#657068;margin-top:4px}
.extension-reports-topbar .extension-reports-avatar{display:flex!important;align-items:center;justify-content:center;width:46px!important;min-width:46px!important;height:46px!important;border-radius:50%;background:#ffd51f!important;color:#003f1a!important;font-weight:900!important;box-shadow:0 8px 18px rgba(255,213,31,.25)}
.extension-reports-hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px 28px;margin:0 0 18px;min-height:116px;border-radius:18px;background:linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%);color:#fff;box-shadow:0 18px 42px rgba(0,90,40,.18)}
.extension-reports-hero:before{content:"";position:absolute;inset:0 0 0 auto;width:29%;background:linear-gradient(135deg,rgba(255,213,31,.11),rgba(255,255,255,.025));clip-path:polygon(12% 0,100% 0,100% 100%,0 100%);pointer-events:none}
.extension-reports-hero>div,.extension-reports-back{position:relative;z-index:1}
.extension-reports-kicker{display:block;margin:0 0 5px;font-size:10px;font-weight:700;letter-spacing:.05em;color:rgba(255,255,255,.84)}
.extension-reports-hero h1{margin:0 0 6px;font-size:30px;font-weight:900;line-height:1.17;color:#fff!important}
.extension-reports-hero p{margin:0;font-size:11px;line-height:1.55;color:rgba(255,255,255,.85)}
.extension-reports-back{display:inline-flex;gap:7px;align-items:center;justify-content:center;flex:0 0 auto;min-height:42px;padding:0 15px;border-radius:10px;background:#ffd51f!important;border:1px solid #ffd51f!important;color:#003f1a!important;font-size:11px;font-weight:800;text-decoration:none;white-space:nowrap;box-shadow:none!important}
.extension-reports-back:hover{background:#f0bd00!important;border-color:#f0bd00!important;color:#003f1a!important}
.extension-reports-hero-actions{position:relative;z-index:1;display:flex;align-items:center;justify-content:flex-end;gap:9px;flex-wrap:wrap}
.extension-reports-pdf{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:42px;padding:0 15px;border-radius:10px!important;background:#fff!important;border:1px solid #e0efe4!important;color:#00672d!important;font-size:11px!important;font-weight:800!important;white-space:nowrap;box-shadow:none!important}
.extension-reports-pdf:hover{background:#eff7f1!important;border-color:#cde1d2!important;color:#005225!important}
@media(max-width:1000px){.extension-reports-hero-actions{width:100%;justify-content:flex-start}}
@media(max-width:760px){.extension-reports-hero-actions{flex-direction:column;align-items:stretch}.extension-reports-hero-actions .btn{width:100%;white-space:normal;text-align:center}}

.extension-reports-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 18px}
.extension-reports-stat{display:flex;align-items:center;gap:12px;min-width:0;padding:16px;background:#fff;border:1px solid rgba(0,107,46,.13);border-radius:16px;box-shadow:0 14px 34px rgba(16,24,16,.05)}
.extension-reports-stat-icon{display:flex;align-items:center;justify-content:center;flex:0 0 46px;width:46px;height:46px;font-size:21px;border-radius:12px}
.extension-reports-stat-icon.green{background:#007a33;color:#fff}.extension-reports-stat-icon.gold{background:#ffd51f;color:#003f1a}
.extension-reports-stat>span:last-child{display:flex;flex-direction:column;min-width:0}.extension-reports-stat strong{font-size:26px;font-weight:900;line-height:1;color:#101810}.extension-reports-stat b{font-size:11px;line-height:1.3;margin-top:5px;color:#203c2a}.extension-reports-stat small{font-size:9px;color:#657068;line-height:1.4;margin-top:3px}
.extension-reports-panel{background:#fff;border:1px solid rgba(0,107,46,.13);border-radius:18px;box-shadow:0 14px 34px rgba(16,24,16,.05);overflow:hidden;margin-bottom:18px}
.extension-reports-generator{background:#fff;border:1px solid rgba(0,107,46,.13);border-radius:18px;box-shadow:0 14px 34px rgba(16,24,16,.05);overflow:hidden;margin-bottom:18px}
.extension-reports-generator-header{display:flex;align-items:center;gap:10px;padding:14px 20px;border-bottom:1px solid #eaf0e9;min-height:60px}
.extension-reports-generator-header h2{font-size:15px;font-weight:800;color:#101810;margin:0}
.extension-reports-generator-form{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(0,.85fr) auto;align-items:end;gap:12px;padding:18px 20px}
.extension-reports-generator-form>div{min-width:0}
.extension-reports-generator-form label{display:block;margin-bottom:7px;font-size:10px;font-weight:700;color:#465149}
.extension-reports-generator-form .form-select{height:44px!important;min-height:44px!important;width:100%;min-width:0;border:1px solid #d8e1da!important;border-radius:10px!important;background-color:#fff;color:#1f2922;font-size:11.5px!important}
.extension-reports-generator-actions{display:flex;align-items:center}
.extension-reports-generator-submit{display:inline-flex!important;align-items:center;justify-content:center;gap:7px;min-height:44px;padding:0 15px;border-radius:10px!important;background:#006b30!important;border:1px solid #006b30!important;color:#fff!important;font-family:inherit;font-size:11px!important;font-weight:800!important;white-space:nowrap;box-shadow:none!important}
.extension-reports-generator-submit:hover{background:#005225!important;border-color:#005225!important;color:#fff!important}
.extension-reports-preview-actions{display:flex;align-items:center;justify-content:flex-end;gap:9px;flex-wrap:wrap}
.extension-reports-preview-actions .btn{display:inline-flex!important;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:0 12px;border-radius:10px!important;font-size:11px!important;font-weight:800!important;text-decoration:none!important}
.extension-reports-preview-meta{padding:13px 20px;color:#647168;font-size:11px;border-bottom:1px solid #edf0ed}
.extension-reports-preview-section-heading{margin:0;padding:13px 20px;background:#edf6ef;color:#00652e;font-size:13px;font-weight:800}
.extension-reports-completed-table{min-width:620px}
@media(max-width:550px){.extension-reports-preview-actions{width:100%}.extension-reports-preview-actions .btn{flex:1 1 auto}}

@media(max-width:900px){.extension-reports-generator-form{grid-template-columns:1fr 1fr}.extension-reports-generator-actions{grid-column:1/-1;justify-content:flex-end}}
@media(max-width:550px){.extension-reports-generator-form{grid-template-columns:1fr}.extension-reports-generator-actions{grid-column:auto}.extension-reports-generator-submit{width:100%}}

.extension-reports-panel-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:14px 20px;min-height:70px;border-bottom:1px solid #eaf0e9}
.extension-reports-panel-title{display:flex;align-items:center;min-width:0;gap:10px}.extension-reports-panel-icon{display:flex;align-items:center;justify-content:center;min-width:36px;width:36px;height:36px;border-radius:10px;background:#edf7ef;color:#007a33;font-size:17px}
.extension-reports-panel-title h2{font-size:15px;font-weight:800;margin:0;color:#101810;line-height:1.35}.extension-reports-count{border-radius:999px;border:1px solid #d5e8da;background:#f1f8f3;color:#43664d;font-size:10px;font-weight:700;padding:6px 12px;white-space:nowrap}
.extension-reports-section-note{padding:11px 20px 0;margin:0;font-size:10px;color:#718077;line-height:1.5}
.extension-reports-filters{display:grid;grid-template-columns:minmax(220px,2fr) minmax(155px,.95fr) minmax(155px,.95fr) auto;align-items:end;gap:12px;padding:16px 20px;border-bottom:1px solid #edf0ed}
.extension-reports-filters>div{min-width:0}.extension-reports-filters label{display:block;margin-bottom:7px;font-size:10px;font-weight:700;color:#465149}
.extension-reports-filters .form-control,.extension-reports-filters .form-select{height:44px!important;min-height:44px!important;width:100%;min-width:0;border:1px solid #d8e1da!important;border-radius:10px!important;background:#fff;color:#1f2922;font-family:inherit;font-size:11.5px!important}
.extension-reports-search-wrap{position:relative}.extension-reports-search-wrap>i{position:absolute;top:50%;left:15px;transform:translateY(-50%);color:#658071;font-size:14px;pointer-events:none}.extension-reports-search-wrap .form-control{padding-left:40px!important}
.extension-reports-filter-actions{display:flex;gap:9px;align-items:center}.extension-reports-filter-actions .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:44px;min-height:44px;min-width:89px;padding:0 12px;border-radius:10px;font-family:inherit;font-size:11px;font-weight:800;box-shadow:none!important;white-space:nowrap}
.extension-reports-filter-btn{background:#006b30!important;color:#fff!important;border:1px solid #006b30!important}.extension-reports-filter-btn:hover{background:#005225!important;border-color:#005225!important;color:#fff!important}
.extension-reports-reset{background:#fff!important;color:#006b30!important;border:1px solid #cde2d3!important}.extension-reports-reset:hover{background:#f2f8f3!important;color:#005225!important;border-color:#a8d2b4!important}
.extension-reports-table-wrap{width:100%;overflow-x:auto}.extension-reports-table{width:100%;min-width:640px;border-collapse:collapse;margin:0}.extension-reports-table th,.extension-reports-table td{padding:13px 15px;border-bottom:1px solid #edf0ed;vertical-align:middle}.extension-reports-table th{background:#f7faf7;color:#455349;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.035em;text-align:left;white-space:nowrap}.extension-reports-table td{color:#35443a;font-size:11px;line-height:1.5}.extension-reports-table tr:last-child td{border-bottom:0}.extension-reports-table th:last-child,.extension-reports-table td:last-child{text-align:center}.extension-reports-table td strong{display:block;color:#203c2a;font-weight:800}.extension-reports-ref{display:block;margin-top:3px;font-size:9.5px;color:#758078}
/* EXTENSION REPORTS — consistent header/cell alignment in every table.
   Text fields are left-aligned; dates, statuses and actions are centered.
   nth-last-child works for both ESO and extensionist column counts.
   Do not target generic last/second-last columns: Program Submissions
   has no Action column, whereas the other lists do. */
.extension-reports-page .extension-reports-table :is(th,td) {
    vertical-align: middle !important;
}
.extension-reports-page .extension-reports-table :is(th,td):first-child {
    text-align: left !important;
}
/* Submitted Reports and Completed Services: Date / Status / Action. */
.extension-reports-page :is(.extension-reports-submitted-table,.extension-reports-services-table)
    :is(th,td):nth-last-child(-n+3) {
    text-align: center !important;
}
/* Program Submissions: Date Submitted / Status, no Action column. */
.extension-reports-page .extension-reports-programs-table :is(th,td):nth-last-child(-n+2) {
    text-align: center !important;
}
/* Completed PDF preview table on the page (not the exported PDF). */
.extension-reports-page .extension-reports-completed-table :is(th,td):first-child,
.extension-reports-page .extension-reports-completed-table :is(th,td):last-child {
    text-align: center !important;
}
.extension-reports-page .extension-reports-completed-table :is(th,td):nth-child(2),
.extension-reports-page .extension-reports-completed-table :is(th,td):nth-child(3),
.extension-reports-page .extension-reports-completed-table :is(th,td):nth-child(4) {
    text-align: left !important;
}
/* Status pills and eye buttons remain on the same central axis as their headers. */
.extension-reports-page .extension-reports-table td .extension-reports-status,
.extension-reports-page .extension-reports-table td .extension-reports-eye {
    vertical-align: middle;
}

/* Extension-only report badges: mirror the soft status pills from Extension Management. */
.extension-reports-status{display:inline-flex;align-items:center;justify-content:center;padding:5px 11px;min-height:29px;border-radius:999px;border:1px solid #e0e7e1;background:#f2f4f2;color:#4b5750;font-size:9px;font-weight:700;line-height:1.25;white-space:nowrap}
.extension-reports-status.extension-reports-status-approved{background:#eaf6ee;border-color:#cce6d3;color:#136534}
.extension-reports-status.extension-reports-status-revision{background:#fff3e9;border-color:#ffd4b2;color:#99521c}
.extension-reports-status.extension-reports-status-draft{background:#f2f4f2;border-color:#e0e6e1;color:#4b5750}
.extension-reports-status.extension-reports-status-review{background:#e7f1ff;border-color:#cbdff6;color:#174b83}
.extension-reports-status.extension-reports-status-ongoing{background:#fff4cf;border-color:#efdfa7;color:#735500}
.extension-reports-status.extension-reports-status-neutral{background:#f1f3f2;border-color:#e1e6e2;color:#516056}
.extension-reports-eye{display:inline-flex!important;align-items:center;justify-content:center;width:38px;min-width:38px;height:36px;min-height:36px;padding:0!important;border:1px solid #cde2d3!important;border-radius:10px!important;background:#fff!important;color:#007a33!important;box-shadow:none!important}.extension-reports-eye:hover{background:#edf7f0!important;border-color:#a8d2b4!important;color:#005225!important}.extension-reports-eye i{font-size:14px}
.extension-reports-empty{min-height:185px;display:flex;flex-direction:column;gap:5px;align-items:center;justify-content:center;padding:30px 16px;text-align:center;color:#68756c;font-size:11px}.extension-reports-empty i{font-size:24px;margin-bottom:5px}.extension-reports-empty strong{color:#405549;font-size:13px}.extension-reports-empty p{margin:2px 0 0}.extension-reports-empty.compact{min-height:115px}
.extension-reports-page .alert{font-size:11px;border-radius:11px}
@media(max-width:1230px){.extension-reports-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.extension-reports-filters{grid-template-columns:minmax(200px,1.5fr) minmax(150px,1fr) minmax(150px,1fr)}.extension-reports-filter-actions{grid-column:1/-1;justify-content:flex-end}}
@media(max-width:760px){.extension-reports-page{padding:14px!important}.extension-reports-topbar{padding:0 16px!important}.extension-reports-topbar .topbar-user-text{display:none}.extension-reports-hero{flex-direction:column;align-items:stretch;padding:18px}.extension-reports-hero h1{font-size:25px}.extension-reports-back{width:100%}.extension-reports-filters{grid-template-columns:1fr}.extension-reports-filter-actions{grid-column:auto}.extension-reports-filter-actions .btn{flex:1}.extension-reports-stats{grid-template-columns:1fr}.extension-reports-brand strong{font-size:13px}.extension-reports-brand span{font-size:10px}}
.extension-reports-page *,.extension-reports-topbar *{animation:none!important;transition:none!important}

/* ================================================================
   ASU-REMS — EXTENSION REPORTS / DARK MODE ONLY
   Match the deep green Research and Extension records design.
   Presentation only: queries, role restrictions and PDF are unchanged.
   The downloaded PDF keeps its formal white print background.
   ================================================================ */
html[data-rems-theme="dark"] body:has(.extension-reports-page) .main-page,
html[data-rems-theme="dark"] .page-content.extension-reports-page {
    background: #0b1d15 !important;
    background-image: none !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] body:has(.extension-reports-page) .extension-reports-topbar {
    background: #10281c !important;
    border-bottom-color: #355843 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .extension-reports-topbar :is(.extension-reports-brand strong,.topbar-user-text strong) {
    color: #f0f7ef !important;
}
html[data-rems-theme="dark"] .extension-reports-topbar :is(.extension-reports-brand span,.topbar-user-text span) {
    color: #bdd3c2 !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-shell {
    color: #eaf4eb !important;
}

/* Every panel, including the generator, lists, and completion preview. */
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-stat,.extension-reports-panel,.extension-reports-generator
) {
    background: #162e22 !important;
    background-image: none !important;
    border-color: #395b46 !important;
    color: #eaf4eb !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-panel-head,.extension-reports-generator-header
) {
    background: #1d3c2d !important;
    border-bottom-color: #41604b !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-panel-title h2,.extension-reports-generator-header h2,
    .extension-reports-stat strong,.extension-reports-stat b,
    .extension-reports-empty strong
) {
    color: #eef7ef !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-stat small,.extension-reports-section-note,
    .extension-reports-ref,.extension-reports-preview-meta,
    .extension-reports-empty,.extension-reports-empty p
) {
    color: #bdd2c3 !important;
    background-image: none !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-section-note {
    background: transparent !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-panel-icon,.extension-reports-count
) {
    background: #244a36 !important;
    background-image: none !important;
    border: 1px solid #3f7052 !important;
    color: #22a458 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-count {
    color: #d8efdc !important;
}

/* Filters and generated-report form: no light inputs in dark mode. */
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-generator-form,.extension-reports-filters,
    .extension-reports-preview-meta
) {
    background: #162e22 !important;
    border-color: #395b46 !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-generator-form label,.extension-reports-filters label
) {
    color: #dcebdc !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-generator-form .form-select,
    .extension-reports-filters .form-control,
    .extension-reports-filters .form-select
) {
    background-color: #1c392b !important;
    border-color: #4c7158 !important;
    color: #f3f9f2 !important;
    box-shadow: none !important;
    color-scheme: dark;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-generator-form .form-select,
    .extension-reports-filters .form-control,
    .extension-reports-filters .form-select
):focus {
    border-color: #83c99d !important;
    box-shadow: 0 0 0 2px rgba(91,170,114,.18) !important;
}
html[data-rems-theme="dark"] .extension-reports-page .form-control::placeholder {
    color: #bbd0be !important;
    opacity: 1 !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-generator-form option,.extension-reports-filters option
) {
    background: #1c392b !important;
    color: #f3f9f2 !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-search-wrap > i {
    color: #b7cfbd !important;
}

/* Header, alternating rows and text match the Research records table.
   IMPORTANT: titles must have transparent backgrounds; filling <strong>
   individually creates visible rectangular patches on alternating rows. */
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table-wrap,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table {
    background: #162e22 !important;
    border-color: #395b46 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table :is(th,td) {
    border-bottom-color: #3c5d48 !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table th {
    background: #244332 !important;
    color: #dcebdc !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table tbody tr:nth-child(odd) td {
    background: #162e22 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table tbody tr:nth-child(even) td {
    background: #234432 !important;
    color: #eaf4eb !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table tbody tr:hover td {
    background: #2b503b !important;
    color: #f4faf3 !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table td :is(strong,.extension-reports-ref),
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table td strong {
    background: transparent !important;
    background-color: transparent !important;
    background-image: none !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table td strong {
    color: #eef7ef !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-table td .extension-reports-ref {
    color: #bdd2c3 !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-preview-section-heading {
    background: #244a36 !important;
    color: #eaf5e9 !important;
}

/* Status pills: retain meaning, never use pale/white chips on dark rows. */
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-status {
    background: #304537 !important;
    border-color: #617665 !important;
    color: #e5f1e8 !important;
    font-weight: 800 !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-status.extension-reports-status-approved {
    background: #1d4a31 !important;
    border-color: #4c8762 !important;
    color: #d4f6dc !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-status.extension-reports-status-revision {
    background: #523b2d !important;
    border-color: #997052 !important;
    color: #ffdfbd !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-status.extension-reports-status-review {
    background: #4e422b !important;
    border-color: #897345 !important;
    color: #f9e9b9 !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-status.extension-reports-status-ongoing {
    background: #4b4729 !important;
    border-color: #8a7c47 !important;
    color: #f5e8b3 !important;
}
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-status:is(
    .extension-reports-status-draft,.extension-reports-status-neutral
) {
    background: #304137 !important;
    border-color: #627568 !important;
    color: #e0ece3 !important;
}

/* Eye action and secondary buttons remain dark green, not white. */
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-reset,.extension-reports-eye
) {
    background: #234a35 !important;
    border-color: #527861 !important;
    color: #d7f0dc !important;
    box-shadow: none !important;
}
html[data-rems-theme="dark"] .extension-reports-page :is(
    .extension-reports-reset,.extension-reports-eye
):hover {
    background: #2d5b41 !important;
    border-color: #79b28c !important;
    color: #ffffff !important;
}
html[data-rems-theme="dark"] .extension-reports-page .alert-warning {
    background: #4c3a24 !important;
    border-color: #88672a !important;
    color: #ffe6aa !important;
}
</style>

<style id="rems-reports-visible-select-arrows">
/* Persistent CSS-drawn dropdown chevrons: no SVG background conflict in dark mode.
   Scoped to Reports select controls only; PHP/filters/exports remain unchanged. */
.reports-admin-page .filter-card .col-lg-3:has(> select.form-select),
.reports-admin-page .institutional-completed-form > div:has(> select.form-select),
.extension-reports-page .extension-reports-generator-form > div:has(> select.form-select),
.extension-reports-page .extension-reports-filters > div:has(> select.form-select) {
    position: relative !important;
}
.reports-admin-page .filter-card .col-lg-3:has(> select.form-select)::after,
.reports-admin-page .institutional-completed-form > div:has(> select.form-select)::after,
.extension-reports-page .extension-reports-generator-form > div:has(> select.form-select)::after,
.extension-reports-page .extension-reports-filters > div:has(> select.form-select)::after {
    content: "";
    position: absolute;
    right: 17px;
    bottom: 18px;
    z-index: 2;
    width: 9px;
    height: 9px;
    border-right: 2px solid #284b35;
    border-bottom: 2px solid #284b35;
    transform: rotate(45deg);
    pointer-events: none;
}
.reports-admin-page .filter-card .col-lg-3 > select.form-select,
.reports-admin-page .institutional-completed-form > div > select.form-select,
.extension-reports-page .extension-reports-generator-form > div > select.form-select,
.extension-reports-page .extension-reports-filters > div > select.form-select {
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    background-image: none !important;
    padding-right: 42px !important;
}
html[data-rems-theme="dark"] .reports-admin-page .filter-card .col-lg-3 > select.form-select,
html[data-rems-theme="dark"] .reports-admin-page .institutional-completed-form > div > select.form-select,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-generator-form > div > select.form-select,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-filters > div > select.form-select {
    background-image: none !important;
}
html[data-rems-theme="dark"] .reports-admin-page .filter-card .col-lg-3:has(> select.form-select)::after,
html[data-rems-theme="dark"] .reports-admin-page .institutional-completed-form > div:has(> select.form-select)::after,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-generator-form > div:has(> select.form-select)::after,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-filters > div:has(> select.form-select)::after {
    border-color: #f1f8ef;
}
</style>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php exit;
}
?>
<?php

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Reports & Analytics';
$activePage = 'reports';

$userRole =
    $user['role'] ?? '';


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    'admin',
    'faculty',
    'rps',
    'extension_coordinator'
];


if (
    !in_array(
        $userRole,
        $allowedRoles,
        true
    )
) {

    redirect(
        BASE_URL . '/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| DISPLAY ROLE
|--------------------------------------------------------------------------
*/

/* Completed-report preview is the same workflow used by ESO. */
$institutionalCompletedPreview = in_array($userRole, ['admin', 'rps'], true)
    && (string)($_GET['completed_report_preview'] ?? '') === '1';
$institutionalPdfType = trim((string)($_GET['pdf_type'] ?? 'all'));
$institutionalPdfYear = trim((string)($_GET['pdf_year'] ?? ''));
$institutionalPdfSpecs = remsCompletedPdfSpecsForRole((string)$userRole);
$institutionalPreviewSections = [];
$institutionalPreviewTotal = 0;
$institutionalPreviewError = '';
if ($institutionalCompletedPreview) {
    if (!in_array($institutionalPdfType, array_merge(['all'], array_keys($institutionalPdfSpecs)), true)) {
        $institutionalPdfType = 'all';
    }
    if ($institutionalPdfYear !== '' && (!preg_match('/^\d{4}$/', $institutionalPdfYear)
        || (int)$institutionalPdfYear < 1900 || (int)$institutionalPdfYear > 2100)) {
        $institutionalPdfYear = '';
    }
    try {
        foreach ($institutionalPdfSpecs as $key => $spec) {
            if ($institutionalPdfType !== 'all' && $institutionalPdfType !== $key) continue;
            // Both preview and exported PDF use the same completed-only query.
            $records = remsCompletedPdfRecords($pdo, $spec);
            if ($institutionalPdfYear !== '') {
                $records = array_values(array_filter($records,
                    static fn(array $row): bool => ($row['year'] ?? '') === $institutionalPdfYear));
            }
            $institutionalPreviewSections[] = ['label' => $spec['label'], 'records' => $records];
            $institutionalPreviewTotal += count($records);
        }
    } catch (Throwable $exception) {
        error_log('Research completed-report preview failed: ' . $exception->getMessage());
        $institutionalPreviewError = 'The completed-report preview could not be loaded. Please try again.';
        $institutionalPreviewSections = [];
        $institutionalPreviewTotal = 0;
    }
}

$displayRole =
    $userRole === 'rps'
        ? 'Research Processing Staff (RPS)'
        : roleLabel($userRole);


/*
|--------------------------------------------------------------------------
| REPORT CONFIGURATION
|--------------------------------------------------------------------------
*/

$validStatuses = [
    'Draft',
    'Submitted',
    'Under Review',
    'For Revision',
    'Approved',
    'Ongoing',
    'Completed',
    'Rejected',
    'Archived'
];


$reportCatalog = [

    'annual_summary' => [

        'label' =>
            'Annual Research and Extension Summary',

        'roles' => [
            'admin'
        ]
    ],


    'research_projects' => [

        'label' =>
            'Research Projects Report',

        'roles' => [
            'admin',
            'faculty',
            'rps'
        ]
    ],


    'research_status' => [

        'label' =>
            'Research Status Report',

        'roles' => [
            'admin',
            'faculty',
            'rps'
        ]
    ],


    'completed_research' => [

        'label' =>
            'Completed Research Report',

        'roles' => [
            'admin',
            'faculty',
            'rps'
        ]
    ],


    'extension_programs' => [

        'label' =>
            'Extension Programs Report',

        'roles' => [
            'admin',
            'extension_coordinator'
        ]
    ],


    'extension_participants' => [

        'label' =>
            'Extension Participant Report',

        'roles' => [
            'admin',
            'extension_coordinator'
        ]
    ],


    'extension_accomplishments' => [

        'label' =>
            'Extension Accomplishment Report',

        'roles' => [
            'admin',
            'extension_coordinator'
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| ALLOWED REPORT TYPES
|--------------------------------------------------------------------------
*/

$allowedReportTypes = [];


foreach (
    $reportCatalog
    as
    $reportKey =>
    $reportDefinition
) {

    if (
        in_array(
            $userRole,
            $reportDefinition['roles'],
            true
        )
    ) {

        $allowedReportTypes[
            $reportKey
        ] =
            $reportDefinition;
    }
}


/* For Admin and RPS, any older generated preview/CSV is also limited to
   completed-record lists. Other analytics remain read-only charts. */
if (in_array($userRole, ['admin', 'rps'], true)) {
    $onlyCompletedTypes = ['completed_research', 'extension_accomplishments'];
    $allowedReportTypes = array_intersect_key($allowedReportTypes, array_flip($onlyCompletedTypes));
}

/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$reportType =
    trim(
        $_GET['report_type']
        ?? ''
    );


$year =
    trim(
        $_GET['year']
        ?? ''
    );


$status =
    trim(
        $_GET['status']
        ?? ''
    );


$unit =
    trim(
        $_GET['unit']
        ?? ''
    );


if (
    $reportType !== ''
    &&
    !isset(
        $allowedReportTypes[
            $reportType
        ]
    )
) {

    $reportType = '';
}


if (
    $year !== ''
    &&
    !preg_match(
        '/^\d{4}$/',
        $year
    )
) {

    $year = '';
}


if (
    $status !== ''
    &&
    !in_array(
        $status,
        $validStatuses,
        true
    )
) {

    $status = '';
}


$unitType = '';

$unitId = 0;


if (
    preg_match(
        '/^(college|department):(\d+)$/',
        $unit,
        $unitMatch
    )
) {

    $unitType =
        $unitMatch[1];

    $unitId =
        (int)$unitMatch[2];

} else {

    $unit = '';
}


/*
|--------------------------------------------------------------------------
| RESEARCH ACCESS
|--------------------------------------------------------------------------
*/

function reportResearchAccess(
    array $user
): array {

    /*
     * Administrator:
     * all non-draft institutional research.
     */

    if (
        $user['role']
        === 'admin'
    ) {

        return [
            "rp.status <> 'Draft'",
            []
        ];
    }


    /*
     * RPS:
     * all non-draft institutional research.
     */

    if (
        $user['role']
        === 'rps'
    ) {

        return [
            "rp.status <> 'Draft'",
            []
        ];
    }


    /*
     * Faculty:
     * own research only.
     */

    if (
        $user['role']
        === 'faculty'
    ) {

        return [
            'rp.researcher_id = ?',
            [
                (int)$user['user_id']
            ]
        ];
    }


    /*
     * All other roles:
     * no research access.
     */

    return [
        '1 = 0',
        []
    ];
}


/*
|--------------------------------------------------------------------------
| EXTENSION ACCESS
|--------------------------------------------------------------------------
*/

function reportExtensionAccess(
    array $user
): array {

    if (
        $user['role']
        === 'admin'
    ) {

        return [
            "ep.status <> 'Draft'",
            []
        ];
    }


    if (
        $user['role']
        === 'extension_coordinator'
    ) {

        return [
            'ep.coordinator_id = ?',
            [
                (int)$user['user_id']
            ]
        ];
    }


    return [
        '1 = 0',
        []
    ];
}


/*
|--------------------------------------------------------------------------
| FILTER HELPER
|--------------------------------------------------------------------------
*/

function reportModuleFilters(
    string $alias,
    string $dateExpression,
    string $year,
    string $status,
    string $unitType,
    int $unitId,
    bool $includeStatus = true
): array {

    $sql = '';

    $params = [];


    if ($year !== '') {

        $sql .= "
            AND YEAR(
                {$dateExpression}
            ) = ?
        ";

        $params[] =
            (int)$year;
    }


    if (
        $includeStatus
        &&
        $status !== ''
    ) {

        $sql .= "
            AND {$alias}.status = ?
        ";

        $params[] =
            $status;
    }


    if (
        $unitType === 'college'
        &&
        $unitId > 0
    ) {

        $sql .= "
            AND {$alias}.college_id = ?
        ";

        $params[] =
            $unitId;
    }


    if (
        $unitType === 'department'
        &&
        $unitId > 0
    ) {

        $sql .= "
            AND {$alias}.department_id = ?
        ";

        $params[] =
            $unitId;
    }


    return [
        $sql,
        $params
    ];
}


/*
|--------------------------------------------------------------------------
| COUNT HELPER
|--------------------------------------------------------------------------
*/

function reportCount(
    PDO $pdo,
    string $table,
    string $alias,
    string $whereSql,
    array $params,
    string $extraSql = ''
): int {

    $sql = "
        SELECT COUNT(*)

        FROM {$table} {$alias}

        WHERE {$whereSql}

        {$extraSql}
    ";


    $stmt =
        $pdo->prepare($sql);


    $stmt->execute(
        $params
    );


    return
        (int)$stmt
            ->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| SUM HELPER
|--------------------------------------------------------------------------
*/

function reportSum(
    PDO $pdo,
    string $table,
    string $alias,
    string $column,
    string $whereSql,
    array $params,
    string $extraSql = ''
): int {

    $sql = "
        SELECT

            COALESCE(
                SUM({$column}),
                0
            )

        FROM {$table} {$alias}

        WHERE {$whereSql}

        {$extraSql}
    ";


    $stmt =
        $pdo->prepare($sql);


    $stmt->execute(
        $params
    );


    return
        (int)$stmt
            ->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| PERSON NAME
|--------------------------------------------------------------------------
*/

function reportPersonName(
    array $row,
    string $prefix = ''
): string {

    $first =
        trim(
            (string)(
                $row[
                    $prefix
                    . 'first_name'
                ]
                ?? ''
            )
        );


    $middle =
        trim(
            (string)(
                $row[
                    $prefix
                    . 'middle_name'
                ]
                ?? ''
            )
        );


    $last =
        trim(
            (string)(
                $row[
                    $prefix
                    . 'last_name'
                ]
                ?? ''
            )
        );


    return trim(
        $first
        .
        ' '
        .
        (
            $middle !== ''
                ? $middle . ' '
                : ''
        )
        .
        $last
    );
}


/*
|--------------------------------------------------------------------------
| STATUS BADGE
|--------------------------------------------------------------------------
*/

function reportStatusBadgeClass(
    string $status
): string {

    return match ($status) {

        'Submitted' =>
            'bg-primary-subtle text-primary-emphasis',

        'Under Review' =>
            'bg-warning-subtle text-warning-emphasis',

        'For Revision' =>
            'bg-warning-subtle text-warning-emphasis',

        'Approved' =>
            'bg-success-subtle text-success-emphasis',

        'Ongoing' =>
            'bg-success-subtle text-success-emphasis',

        'Completed' =>
            'bg-success text-white',

        'Rejected' =>
            'bg-danger-subtle text-danger-emphasis',

        'Archived' =>
            'bg-secondary-subtle text-secondary-emphasis',

        default =>
            'bg-light text-secondary'
    };
}


/*
|--------------------------------------------------------------------------
| BASE ACCESS
|--------------------------------------------------------------------------
*/

[
    $researchAccessSql,
    $researchAccessParams
] =
    reportResearchAccess(
        $user
    );


[
    $extensionAccessSql,
    $extensionAccessParams
] =
    reportExtensionAccess(
        $user
    );


$researchDateExpression =
    'COALESCE(
        rp.start_date,
        DATE(rp.submitted_at),
        DATE(rp.created_at)
    )';


$extensionDateExpression =
    'COALESCE(
        ep.start_date,
        DATE(ep.submitted_at),
        DATE(ep.created_at)
    )';


/*
|--------------------------------------------------------------------------
| COLLEGES
|--------------------------------------------------------------------------
*/

$colleges =
    $pdo
        ->query("
            SELECT
                college_id,
                college_name

            FROM colleges

            ORDER BY
                college_name ASC
        ")
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/*
|--------------------------------------------------------------------------
| DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments =
    $pdo
        ->query("
            SELECT
                d.department_id,
                d.department_name,
                c.college_name

            FROM departments d

            LEFT JOIN colleges c
                ON c.college_id =
                    d.college_id

            ORDER BY
                c.college_name ASC,
                d.department_name ASC
        ")
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/*
|--------------------------------------------------------------------------
| UNIT LABEL
|--------------------------------------------------------------------------
*/

$unitLabel =
    'All Colleges / Departments';


if (
    $unitType === 'college'
    &&
    $unitId > 0
) {

    foreach (
        $colleges
        as
        $college
    ) {

        if (
            (int)$college[
                'college_id'
            ]
            ===
            $unitId
        ) {

            $unitLabel =
                $college[
                    'college_name'
                ];

            break;
        }
    }
}


if (
    $unitType === 'department'
    &&
    $unitId > 0
) {

    foreach (
        $departments
        as
        $department
    ) {

        if (
            (int)$department[
                'department_id'
            ]
            ===
            $unitId
        ) {

            $unitLabel =
                $department[
                    'department_name'
                ];

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| AVAILABLE YEARS
|--------------------------------------------------------------------------
*/

$yearValues = [];


if (
    $researchAccessSql
    !== '1 = 0'
) {

    $stmt =
        $pdo->prepare("
            SELECT DISTINCT

                YEAR(
                    {$researchDateExpression}
                ) AS report_year

            FROM research_proposals rp

            WHERE {$researchAccessSql}

              AND {$researchDateExpression}
                  IS NOT NULL
        ");


    $stmt->execute(
        $researchAccessParams
    );


    foreach (
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
        as
        $availableYear
    ) {

        if ($availableYear) {

            $yearValues[] =
                (int)$availableYear;
        }
    }
}


if (
    $extensionAccessSql
    !== '1 = 0'
) {

    $stmt =
        $pdo->prepare("
            SELECT DISTINCT

                YEAR(
                    {$extensionDateExpression}
                ) AS report_year

            FROM extension_programs ep

            WHERE {$extensionAccessSql}

              AND {$extensionDateExpression}
                  IS NOT NULL
        ");


    $stmt->execute(
        $extensionAccessParams
    );


    foreach (
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
        as
        $availableYear
    ) {

        if ($availableYear) {

            $yearValues[] =
                (int)$availableYear;
        }
    }
}


$yearValues =
    array_values(
        array_unique(
            $yearValues
        )
    );


rsort(
    $yearValues,
    SORT_NUMERIC
);


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

[
    $researchFilterSql,
    $researchFilterParams
] =
    reportModuleFilters(
        'rp',
        $researchDateExpression,
        $year,
        $status,
        $unitType,
        $unitId
    );


[
    $extensionFilterSql,
    $extensionFilterParams
] =
    reportModuleFilters(
        'ep',
        $extensionDateExpression,
        $year,
        $status,
        $unitType,
        $unitId
    );


$researchWhereSql =
    $researchAccessSql
    .
    $researchFilterSql;


$researchWhereParams =
    array_merge(
        $researchAccessParams,
        $researchFilterParams
    );


$extensionWhereSql =
    $extensionAccessSql
    .
    $extensionFilterSql;


$extensionWhereParams =
    array_merge(
        $extensionAccessParams,
        $extensionFilterParams
    );


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalResearchProjects =
    reportCount(
        $pdo,
        'research_proposals',
        'rp',
        $researchWhereSql,
        $researchWhereParams
    );


$totalExtensionPrograms =
    reportCount(
        $pdo,
        'extension_programs',
        'ep',
        $extensionWhereSql,
        $extensionWhereParams
    );


$totalParticipants =
    reportSum(
        $pdo,
        'extension_programs',
        'ep',
        'ep.target_participants',
        $extensionWhereSql,
        $extensionWhereParams
    );


$totalCompletedResearch =
    reportCount(
        $pdo,
        'research_proposals',
        'rp',
        $researchWhereSql,
        $researchWhereParams,
        "AND rp.status = 'Completed'"
    );


$totalCompletedExtension =
    reportCount(
        $pdo,
        'extension_programs',
        'ep',
        $extensionWhereSql,
        $extensionWhereParams,
        "AND ep.status = 'Completed'"
    );


$totalCompletedProjects =
    $totalCompletedResearch
    +
    $totalCompletedExtension;


/*
|--------------------------------------------------------------------------
| REPORT CARD COUNTS
|--------------------------------------------------------------------------
*/

$researchReportCount =
    $totalResearchProjects;


$extensionReportCount =
    $totalExtensionPrograms;


$participantReportCount =
    $totalParticipants;


/*
|--------------------------------------------------------------------------
| ACCOMPLISHMENT COUNT
|--------------------------------------------------------------------------
*/

$accomplishmentSql = "
    SELECT

        COUNT(
            DISTINCT
            ep.extension_id
        )

    FROM extension_programs ep

    INNER JOIN extension_documents ed

        ON ed.extension_id =
            ep.extension_id

       AND ed.document_type =
            'Accomplishment Report'

    WHERE {$extensionWhereSql}
";


$accomplishmentStmt =
    $pdo->prepare(
        $accomplishmentSql
    );


$accomplishmentStmt->execute(
    $extensionWhereParams
);


$accomplishmentReportCount =
    (int)$accomplishmentStmt
        ->fetchColumn();


/*
|--------------------------------------------------------------------------
| MONTHLY CHART
|--------------------------------------------------------------------------
*/

$chartYear =
    $year !== ''
        ? (int)$year
        : (
            $yearValues[0]
            ??
            (int)date('Y')
        );


$monthlyResearch =
    array_fill(
        0,
        12,
        0
    );


$monthlyExtension =
    array_fill(
        0,
        12,
        0
    );


[
    $chartResearchFilterSql,
    $chartResearchFilterParams
] =
    reportModuleFilters(
        'rp',
        $researchDateExpression,
        (string)$chartYear,
        $status,
        $unitType,
        $unitId
    );


$chartResearchSql = "
    SELECT

        MONTH(
            {$researchDateExpression}
        ) AS activity_month,

        COUNT(*) AS activity_total

    FROM research_proposals rp

    WHERE {$researchAccessSql}

    {$chartResearchFilterSql}

    GROUP BY

        MONTH(
            {$researchDateExpression}
        )
";


$chartResearchStmt =
    $pdo->prepare(
        $chartResearchSql
    );


$chartResearchStmt->execute(
    array_merge(
        $researchAccessParams,
        $chartResearchFilterParams
    )
);


foreach (
    $chartResearchStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        )
    as
    $chartRow
) {

    $monthNumber =
        (int)$chartRow[
            'activity_month'
        ];


    if (
        $monthNumber >= 1
        &&
        $monthNumber <= 12
    ) {

        $monthlyResearch[
            $monthNumber - 1
        ] =
            (int)$chartRow[
                'activity_total'
            ];
    }
}


/*
|--------------------------------------------------------------------------
| EXTENSION CHART
|--------------------------------------------------------------------------
*/

[
    $chartExtensionFilterSql,
    $chartExtensionFilterParams
] =
    reportModuleFilters(
        'ep',
        $extensionDateExpression,
        (string)$chartYear,
        $status,
        $unitType,
        $unitId
    );


$chartExtensionSql = "
    SELECT

        MONTH(
            {$extensionDateExpression}
        ) AS activity_month,

        COUNT(*) AS activity_total

    FROM extension_programs ep

    WHERE {$extensionAccessSql}

    {$chartExtensionFilterSql}

    GROUP BY

        MONTH(
            {$extensionDateExpression}
        )
";


$chartExtensionStmt =
    $pdo->prepare(
        $chartExtensionSql
    );


$chartExtensionStmt->execute(
    array_merge(
        $extensionAccessParams,
        $chartExtensionFilterParams
    )
);


foreach (
    $chartExtensionStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        )
    as
    $chartRow
) {

    $monthNumber =
        (int)$chartRow[
            'activity_month'
        ];


    if (
        $monthNumber >= 1
        &&
        $monthNumber <= 12
    ) {

        $monthlyExtension[
            $monthNumber - 1
        ] =
            (int)$chartRow[
                'activity_total'
            ];
    }
}


/*
|--------------------------------------------------------------------------
| GENERATED REPORT
|--------------------------------------------------------------------------
*/

$reportGenerated =
    $reportType !== '';


$generatedReportTitle = '';

$reportColumns = [];

$reportRows = [];

$reportNote = '';


if ($reportGenerated) {

    $generatedReportTitle =
        $allowedReportTypes[
            $reportType
        ]['label'];


    /*
    |--------------------------------------------------------------------------
    | RESEARCH PROJECTS / COMPLETED RESEARCH
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $reportType,
            [
                'research_projects',
                'completed_research'
            ],
            true
        )
    ) {

        [
            $previewFilterSql,
            $previewFilterParams
        ] =
            reportModuleFilters(
                'rp',
                $researchDateExpression,
                $year,
                $status,
                $unitType,
                $unitId,
                $reportType
                !==
                'completed_research'
            );


        $extraStatusSql =
            $reportType
            ===
            'completed_research'

                ? "
                    AND rp.status =
                        'Completed'
                "

                : '';


        $sql = "
            SELECT

                rp.research_id,

                rp.research_title,

                rp.status,

                rp.progress_percentage,

                {$researchDateExpression}
                    AS report_date,

                u.first_name,

                u.middle_name,

                u.last_name,

                c.college_name,

                d.department_name

            FROM research_proposals rp

            INNER JOIN users u

                ON u.user_id =
                    rp.researcher_id

            LEFT JOIN colleges c

                ON c.college_id =
                    rp.college_id

            LEFT JOIN departments d

                ON d.department_id =
                    rp.department_id

            WHERE {$researchAccessSql}

            {$previewFilterSql}

            {$extraStatusSql}

            ORDER BY

                {$researchDateExpression}
                    DESC,

                rp.research_id DESC
        ";


        $stmt =
            $pdo->prepare($sql);


        $stmt->execute(
            array_merge(
                $researchAccessParams,
                $previewFilterParams
            )
        );


        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as
            $row
        ) {

            $reportRows[] = [

                'title' =>
                    $row[
                        'research_title'
                    ],

                'person' =>
                    reportPersonName(
                        $row
                    ),

                'unit' =>
                    trim(
                        (
                            $row[
                                'college_name'
                            ]
                            ?: 'Not assigned'
                        )
                        .
                        (
                            !empty(
                                $row[
                                    'department_name'
                                ]
                            )

                                ? ' / '
                                    .
                                    $row[
                                        'department_name'
                                    ]

                                : ''
                        )
                    ),

                'status' =>
                    $row['status'],

                'year' =>
                    !empty(
                        $row[
                            'report_date'
                        ]
                    )

                        ? date(
                            'Y',
                            strtotime(
                                $row[
                                    'report_date'
                                ]
                            )
                        )

                        : '—',

                'progress' =>
                    (int)$row[
                        'progress_percentage'
                    ]
                    .
                    '%'
            ];
        }


        $reportColumns = [

            'title' =>
                'Research Title',

            'person' =>
                'Researcher',

            'unit' =>
                'College / Department',

            'status' =>
                'Status',

            'year' =>
                'Year',

            'progress' =>
                'Progress'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | RESEARCH STATUS
    |--------------------------------------------------------------------------
    */

    if (
        $reportType
        ===
        'research_status'
    ) {

        $sql = "
            SELECT

                rp.status,

                COUNT(*)
                    AS total_records

            FROM research_proposals rp

            WHERE {$researchWhereSql}

            GROUP BY
                rp.status

            ORDER BY
                total_records DESC,
                rp.status ASC
        ";


        $stmt =
            $pdo->prepare($sql);


        $stmt->execute(
            $researchWhereParams
        );


        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as
            $row
        ) {

            $reportRows[] = [

                'status' =>
                    $row['status'],

                'records' =>
                    (int)$row[
                        'total_records'
                    ]
            ];
        }


        $reportColumns = [

            'status' =>
                'Research Status',

            'records' =>
                'Number of Records'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | EXTENSION PROGRAMS
    |--------------------------------------------------------------------------
    */

    if (
        $reportType
        ===
        'extension_programs'
    ) {

        $sql = "
            SELECT

                ep.extension_id,

                ep.program_title,

                ep.target_community,

                ep.target_participants,

                ep.status,

                ep.progress_percentage,

                {$extensionDateExpression}
                    AS report_date,

                u.first_name,

                u.middle_name,

                u.last_name,

                c.college_name,

                d.department_name

            FROM extension_programs ep

            INNER JOIN users u

                ON u.user_id =
                    ep.coordinator_id

            LEFT JOIN colleges c

                ON c.college_id =
                    ep.college_id

            LEFT JOIN departments d

                ON d.department_id =
                    ep.department_id

            WHERE {$extensionWhereSql}

            ORDER BY

                {$extensionDateExpression}
                    DESC,

                ep.extension_id DESC
        ";


        $stmt =
            $pdo->prepare($sql);


        $stmt->execute(
            $extensionWhereParams
        );


        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as
            $row
        ) {

            $reportRows[] = [

                'title' =>
                    $row[
                        'program_title'
                    ],

                'person' =>
                    reportPersonName(
                        $row
                    ),

                'community' =>
                    $row[
                        'target_community'
                    ]
                    ?: '—',

                'participants' =>
                    (int)(
                        $row[
                            'target_participants'
                        ]
                        ?? 0
                    ),

                'status' =>
                    $row['status'],

                'year' =>
                    !empty(
                        $row[
                            'report_date'
                        ]
                    )

                        ? date(
                            'Y',
                            strtotime(
                                $row[
                                    'report_date'
                                ]
                            )
                        )

                        : '—'
            ];
        }


        $reportColumns = [

            'title' =>
                'Extension Program',

            'person' =>
                'Coordinator',

            'community' =>
                'Target Community',

            'participants' =>
                'Target Participants',

            'status' =>
                'Status',

            'year' =>
                'Year'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | EXTENSION PARTICIPANTS
    |--------------------------------------------------------------------------
    */

    if (
        $reportType
        ===
        'extension_participants'
    ) {

        $sql = "
            SELECT

                ep.program_title,

                ep.target_community,

                ep.municipality,

                ep.target_participants,

                ep.status,

                {$extensionDateExpression}
                    AS report_date,

                c.college_name,

                d.department_name

            FROM extension_programs ep

            LEFT JOIN colleges c

                ON c.college_id =
                    ep.college_id

            LEFT JOIN departments d

                ON d.department_id =
                    ep.department_id

            WHERE {$extensionWhereSql}

            ORDER BY

                {$extensionDateExpression}
                    DESC,

                ep.extension_id DESC
        ";


        $stmt =
            $pdo->prepare($sql);


        $stmt->execute(
            $extensionWhereParams
        );


        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as
            $row
        ) {

            $reportRows[] = [

                'title' =>
                    $row[
                        'program_title'
                    ],

                'community' =>
                    trim(
                        (
                            $row[
                                'target_community'
                            ]
                            ?: '—'
                        )
                        .
                        (
                            !empty(
                                $row[
                                    'municipality'
                                ]
                            )

                                ? ', '
                                    .
                                    $row[
                                        'municipality'
                                    ]

                                : ''
                        )
                    ),

                'participants' =>
                    (int)(
                        $row[
                            'target_participants'
                        ]
                        ?? 0
                    ),

                'status' =>
                    $row['status'],

                'year' =>
                    !empty(
                        $row[
                            'report_date'
                        ]
                    )

                        ? date(
                            'Y',
                            strtotime(
                                $row[
                                    'report_date'
                                ]
                            )
                        )

                        : '—'
            ];
        }


        $reportColumns = [

            'title' =>
                'Extension Program',

            'community' =>
                'Community / Municipality',

            'participants' =>
                'Target Participants',

            'status' =>
                'Status',

            'year' =>
                'Year'
        ];


        $reportNote =
            'Participant attendance records are not yet stored in a dedicated participant table. This report uses actual target participant values already saved in extension records.';
    }


    /*
    |--------------------------------------------------------------------------
    | EXTENSION ACCOMPLISHMENTS
    |--------------------------------------------------------------------------
    */

    if (
        $reportType
        ===
        'extension_accomplishments'
    ) {

        [
            $previewFilterSql,
            $previewFilterParams
        ] =
            reportModuleFilters(
                'ep',
                $extensionDateExpression,
                $year,
                $status,
                $unitType,
                $unitId,
                false
            );


        $sql = "
            SELECT

                ep.extension_id,

                ep.program_title,

                ep.target_community,

                ep.status,

                ep.completed_at,

                {$extensionDateExpression}
                    AS report_date,

                COUNT(
                    CASE

                        WHEN
                            ed.document_type
                            =
                            'Accomplishment Report'

                        THEN
                            ed.document_id

                    END
                )
                AS accomplishment_documents

            FROM extension_programs ep

            LEFT JOIN extension_documents ed

                ON ed.extension_id =
                    ep.extension_id

            WHERE {$extensionAccessSql}

            {$previewFilterSql}

              AND ep.status =
                    'Completed'

            GROUP BY

                ep.extension_id,

                ep.program_title,

                ep.target_community,

                ep.status,

                ep.completed_at,

                {$extensionDateExpression}

            ORDER BY

                ep.completed_at DESC,

                ep.extension_id DESC
        ";


        $stmt =
            $pdo->prepare($sql);


        $stmt->execute(
            array_merge(
                $extensionAccessParams,
                $previewFilterParams
            )
        );


        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as
            $row
        ) {

            $reportRows[] = [

                'title' =>
                    $row[
                        'program_title'
                    ],

                'community' =>
                    $row[
                        'target_community'
                    ]
                    ?: '—',

                'status' =>
                    $row['status'],

                'completed' =>
                    !empty(
                        $row[
                            'completed_at'
                        ]
                    )

                        ? date(
                            'M d, Y',
                            strtotime(
                                $row[
                                    'completed_at'
                                ]
                            )
                        )

                        : '—',

                'documents' =>
                    (int)$row[
                        'accomplishment_documents'
                    ]
            ];
        }


        $reportColumns = [

            'title' =>
                'Extension Program',

            'community' =>
                'Target Community',

            'status' =>
                'Status',

            'completed' =>
                'Completed Date',

            'documents' =>
                'Accomplishment Files'
        ];


        $reportNote =
            'This report uses completed extension programs and actual uploaded accomplishment report files.';
    }


    /*
    |--------------------------------------------------------------------------
    | ANNUAL SUMMARY
    |--------------------------------------------------------------------------
    */

    if (
        $reportType
        ===
        'annual_summary'
    ) {

        $researchApproved =
            reportCount(
                $pdo,
                'research_proposals',
                'rp',
                $researchWhereSql,
                $researchWhereParams,
                "AND rp.status = 'Approved'"
            );


        $researchOngoing =
            reportCount(
                $pdo,
                'research_proposals',
                'rp',
                $researchWhereSql,
                $researchWhereParams,
                "AND rp.status = 'Ongoing'"
            );


        $extensionApproved =
            reportCount(
                $pdo,
                'extension_programs',
                'ep',
                $extensionWhereSql,
                $extensionWhereParams,
                "AND ep.status = 'Approved'"
            );


        $extensionOngoing =
            reportCount(
                $pdo,
                'extension_programs',
                'ep',
                $extensionWhereSql,
                $extensionWhereParams,
                "AND ep.status = 'Ongoing'"
            );


        $reportRows = [

            [

                'module' =>
                    'Research',

                'total' =>
                    $totalResearchProjects,

                'approved' =>
                    $researchApproved,

                'ongoing' =>
                    $researchOngoing,

                'completed' =>
                    $totalCompletedResearch,

                'participants' =>
                    '—'
            ],

            [

                'module' =>
                    'Extension',

                'total' =>
                    $totalExtensionPrograms,

                'approved' =>
                    $extensionApproved,

                'ongoing' =>
                    $extensionOngoing,

                'completed' =>
                    $totalCompletedExtension,

                'participants' =>
                    $totalParticipants
            ]
        ];


        $reportColumns = [

            'module' =>
                'Module',

            'total' =>
                'Total Records',

            'approved' =>
                'Approved',

            'ongoing' =>
                'Ongoing',

            'completed' =>
                'Completed',

            'participants' =>
                'Target Participants'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
*/

if (
    $reportGenerated
    &&
    (
        $_GET['export']
        ?? ''
    )
    ===
    'csv'
) {

    $safeFileName =
        preg_replace(
            '/[^a-z0-9]+/i',
            '_',
            strtolower(
                $generatedReportTitle
            )
        );


    $safeFileName =
        trim(
            $safeFileName,
            '_'
        );


    header(
        'Content-Type: text/csv; charset=UTF-8'
    );


    header(
        'Content-Disposition: attachment; filename="'
        .
        $safeFileName
        .
        '_'
        .
        date('Ymd_His')
        .
        '.csv"'
    );


    $output =
        fopen(
            'php://output',
            'w'
        );


    fwrite(
        $output,
        "\xEF\xBB\xBF"
    );


    fputcsv(
        $output,
        array_values(
            $reportColumns
        )
    );


    foreach (
        $reportRows
        as
        $row
    ) {

        $csvRow = [];


        foreach (
            array_keys(
                $reportColumns
            )
            as
            $columnKey
        ) {

            $csvRow[] =
                $row[$columnKey]
                ?? '';
        }


        fputcsv(
            $output,
            $csvRow
        );
    }


    fclose(
        $output
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$initials = '';


if (
    !empty(
        $user[
            'first_name'
        ]
    )
) {

    $initials .=
        strtoupper(
            substr(
                $user[
                    'first_name'
                ],
                0,
                1
            )
        );
}


if (
    !empty(
        $user[
            'last_name'
        ]
    )
) {

    $initials .=
        strtoupper(
            substr(
                $user[
                    'last_name'
                ],
                0,
                1
            )
        );
}


/*
|--------------------------------------------------------------------------
| SCOPE DESCRIPTION
|--------------------------------------------------------------------------
*/

$scopeText =
    match ($userRole) {

        'admin' =>
            'Institutional reports exclude private Draft records.',

        'rps' =>
            'Research reports include authorized institutional research records processed by the Research Office.',

        'faculty' =>
            'Reports are limited to your own research records.',

        'extension_coordinator' =>
            'Reports are limited to extension programs assigned to your account.',

        default =>
            'Reports are limited to authorized records.'
    };


/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../includes/header.php';

require __DIR__ . '/../includes/sidebar.php';

?>
<style>
/* =========================================================
   TOPBAR PHOTO — MATCH DASHBOARD.PHP EXACTLY
   Same 46px circular avatar and single soft dashboard shadow;
   NO outer yellow ring, custom halo, scaling, or animation.
   All other page styles and interactive controls are unchanged.
   ========================================================= */
html body .main-page > header.asu-topbar .dashboard-avatar {
    box-sizing: border-box !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex: 0 0 46px !important;
    width: 46px !important;
    height: 46px !important;
    min-width: 46px !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    background: #ffd51f !important;
    color: #003f1a !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    box-shadow: 0 8px 18px rgba(255, 213, 31, .25) !important;
    animation: none !important;
    transition: none !important;
    transform: none !important;
}
html body .main-page > header.asu-topbar .dashboard-avatar img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    max-height: none !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 50% !important;
    object-fit: cover !important;
}
@media (max-width: 480px) {
    html body .main-page > header.asu-topbar .dashboard-avatar {
        flex: 0 0 40px !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
    }
}
</style>



<style>

.reports-scope-note {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    color: #6f7a71;
    font-size: 10px;
}


.report-filter-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}


.report-chart-wrap {
    padding: 14px 18px 18px;
}


.report-chart-canvas {
    position: relative;

    height: 260px;
    max-height: 260px;
}


.report-chart-canvas canvas {
    width: 100% !important;
    height: 100% !important;
}


@media (
    max-width: 768px
) {

    .report-chart-canvas {

        height: 220px;
        max-height: 220px;
    }
}


.report-chart-meta {

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    margin-bottom: 12px;

    color: #778079;

    font-size: 10px;
}


.report-card-link {

    color: inherit;

    text-decoration: none;
}


.report-card-link:hover {

    color: inherit;
}


.report-card-clickable {

    transition:
        transform .16s ease,
        box-shadow .16s ease,
        border-color .16s ease;
}


.report-card-clickable:hover {

    transform:
        translateY(-1px);

    border-color:
        #cddbcf;

    box-shadow:
        0 7px 20px
        rgba(
            31,
            74,
            39,
            .07
        );
}


.report-card {

    display: flex;
    align-items: center;

    gap: 14px;
}


.report-card h6 {

    margin: 0 0 3px;

    font-size: 12px;
    font-weight: 700;
}


.report-card p {

    margin: 0;

    color: #7b847d;

    font-size: 9px;

    line-height: 1.5;
}


.report-preview-head {

    display: flex;

    align-items:
        flex-start;

    justify-content:
        space-between;

    gap: 18px;

    padding: 20px;

    border-bottom:
        1px solid
        #edf1ed;
}


.report-preview-head h3 {

    margin: 0 0 5px;

    color: #283029;

    font-size: 15px;

    font-weight: 700;
}


.report-preview-meta {

    display: flex;

    flex-wrap: wrap;

    gap:
        6px
        16px;

    color: #7b847d;

    font-size: 9px;
}


.report-preview-actions {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}


.report-note {

    margin:
        16px
        20px
        0;

    padding:
        11px
        13px;

    border-left:
        3px solid
        #d5aa13;

    border-radius: 6px;

    background:
        #fff9df;

    color:
        #6c6135;

    font-size: 9px;

    line-height: 1.6;
}


.report-table-wrap {

    overflow-x: auto;

    padding:
        18px
        20px
        20px;
}


.report-table {

    min-width: 760px;

    margin: 0;

    font-size: 10px;
}


.report-table
thead th {

    border-bottom-width:
        1px;

    color:
        #667067;

    font-size:
        9px;

    font-weight:
        700;

    text-transform:
        uppercase;

    letter-spacing:
        .035em;

    white-space:
        nowrap;
}


.report-table td {

    vertical-align:
        middle;

    color:
        #3e4740;
}


.report-table .badge {

    font-size: 8px;

    font-weight: 700;
}


.report-zero-state {

    padding:
        44px
        20px;

    text-align:
        center;

    color:
        #7f8981;
}


.report-zero-state i {

    display: block;

    margin-bottom:
        12px;

    color:
        #a3ada5;

    font-size:
        28px;
}


.report-zero-state h3 {

    margin-bottom:
        6px;

    color:
        #3d473f;

    font-size:
        14px;
}


@media print {

    .asu-sidebar,
    .asu-topbar,
    .page-heading,
    .filter-card,
    .stats-row,
    .reports-upper-row,
    .design-notice,
    .content-footer,
    .report-preview-actions,
    #sidebarToggle {

        display:
            none !important;
    }


    .main-page,
    .page-content {

        margin:
            0 !important;

        padding:
            0 !important;

        width:
            100% !important;

        max-width:
            none !important;
    }


    .asu-panel {

        border:
            0 !important;

        box-shadow:
            none !important;
    }


    .report-table-wrap {

        overflow:
            visible !important;
    }


    .report-table {

        min-width:
            0 !important;

        width:
            100% !important;
    }
}



/* Completed institutional report generator: same layout and PDF preview as ESO. */
.reports-admin-page .institutional-completed-card{background:#fff;border:1px solid rgba(0,107,46,.13);border-radius:18px;box-shadow:0 14px 34px rgba(16,24,16,.05);overflow:hidden;margin-bottom:18px;font-family:"Century Gothic",Arial,sans-serif}
.reports-admin-page .institutional-completed-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;min-height:62px;padding:14px 20px;border-bottom:1px solid #eaf0e9}
.reports-admin-page .institutional-completed-title{display:flex;align-items:center;gap:10px;min-width:0}
.reports-admin-page .institutional-completed-head>h2,.reports-admin-page .institutional-completed-title>h2{margin:0;color:#101810;font-size:15px;font-weight:800;line-height:1.35}
.reports-admin-page .institutional-completed-head:has(>h2){justify-content:flex-start;gap:10px}
.reports-admin-page .institutional-completed-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 36px;width:36px;height:36px;border-radius:10px;background:#edf7ef;color:#007a33;font-size:17px}
.reports-admin-page .institutional-completed-form{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(0,.85fr) auto;align-items:end;gap:12px;padding:18px 20px}
.reports-admin-page .institutional-completed-form>div{min-width:0}
.reports-admin-page .institutional-completed-form label{display:block;margin-bottom:7px;color:#465149;font-size:10px;font-weight:700}
.reports-admin-page .institutional-completed-form .form-select{box-sizing:border-box;width:100%;height:44px;min-height:44px;min-width:0;padding:0 35px 0 13px;border:1px solid #d8e1da;border-radius:10px;background-color:#fff;color:#1f2922;font-size:11.5px}
.reports-admin-page :is(.institutional-completed-form-actions,.institutional-completed-preview-actions){display:flex;align-items:center;justify-content:flex-end;gap:9px;flex-wrap:wrap}
.reports-admin-page :is(.institutional-completed-primary,.institutional-completed-secondary){display:inline-flex!important;align-items:center;justify-content:center;gap:7px;min-height:42px;margin:0;padding:0 14px;border-radius:10px!important;font-size:11px!important;font-weight:800!important;white-space:nowrap;text-decoration:none;box-shadow:none!important}
.reports-admin-page .institutional-completed-primary{background:#006b30!important;border:1px solid #006b30!important;color:#fff!important}
.reports-admin-page .institutional-completed-primary:hover{background:#005225!important;border-color:#005225!important;color:#fff!important}
.reports-admin-page .institutional-completed-secondary{background:#fff!important;border:1px solid #cde2d3!important;color:#006b30!important}
.reports-admin-page .institutional-completed-secondary:hover{background:#f2f8f3!important;color:#005225!important}
.reports-admin-page .institutional-completed-meta{margin:0;padding:13px 20px;border-bottom:1px solid #edf0ed;color:#647168;font-size:11px}
.reports-admin-page .institutional-completed-section{margin:0;padding:13px 20px;background:#edf6ef;color:#00652e;font-size:13px;font-weight:800}
.reports-admin-page .institutional-completed-table-wrap{width:100%;overflow-x:auto}
.reports-admin-page .institutional-completed-table{width:100%;min-width:620px;border-collapse:collapse}
.reports-admin-page .institutional-completed-table th,.reports-admin-page .institutional-completed-table td{padding:13px 15px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
.reports-admin-page .institutional-completed-table th{background:#f7faf7;color:#455349;font-size:9px;font-weight:800;letter-spacing:.035em;text-transform:uppercase;white-space:nowrap}
.reports-admin-page .institutional-completed-table td{color:#35443a;font-size:11px;line-height:1.5}
.reports-admin-page .institutional-completed-table th:first-child,.reports-admin-page .institutional-completed-table td:first-child{text-align:center;width:48px}
.reports-admin-page .institutional-completed-table td strong{font-weight:800;color:#203c2a}
.reports-admin-page .institutional-completed-table tbody tr:last-child td{border-bottom:none}
.reports-admin-page .institutional-completed-empty{display:flex;align-items:center;flex-direction:column;justify-content:center;gap:9px;min-height:135px;padding:24px;color:#68756c;font-size:12px}
.reports-admin-page .institutional-completed-empty i{font-size:24px}
@media(max-width:900px){.reports-admin-page .institutional-completed-form{grid-template-columns:1fr 1fr}.reports-admin-page .institutional-completed-form-actions{grid-column:1/-1}}
@media(max-width:600px){.reports-admin-page .institutional-completed-form{grid-template-columns:1fr}.reports-admin-page .institutional-completed-form-actions{grid-column:auto}.reports-admin-page .institutional-completed-form-actions .btn{width:100%}.reports-admin-page .institutional-completed-preview-actions{width:100%}.reports-admin-page .institutional-completed-preview-actions .btn{flex:1;white-space:normal}}
/* Direct, role-scoped PDF export button in the Research / Admin report header. */
.reports-admin-page .page-heading{justify-content:space-between!important;gap:16px!important;flex-wrap:wrap!important}
.reports-admin-page .reports-institutional-pdf-btn{position:relative;z-index:3;display:inline-flex!important;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:0 15px;border-radius:10px!important;background:#ffd51f!important;color:#003f1a!important;border:1px solid #ffd51f!important;font-size:11px!important;font-weight:800!important;text-decoration:none!important;white-space:nowrap;box-shadow:none!important}
.reports-admin-page .reports-institutional-pdf-btn:hover{background:#f0bd00!important;border-color:#f0bd00!important;color:#003f1a!important}
@media(max-width:650px){.reports-admin-page .reports-institutional-pdf-btn{width:100%;white-space:normal;text-align:center}}
</style>

<style>
/* =========================================================
   REPORTS & ANALYTICS — FINAL UNIFORM ADMIN DESIGN
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

.main-page {
    min-height: 100vh !important;
    background: linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%) !important;
}

.reports-admin-page {
    min-height: calc(100vh - 76px) !important;
    padding: 24px 28px 30px !important;
    background:
        radial-gradient(circle at 90% 5%, rgba(255,213,31,.10), transparent 25%),
        radial-gradient(circle at 8% 7%, rgba(0,122,51,.07), transparent 27%),
        linear-gradient(135deg,#dff1e3 0%,#e8f0e3 34%,#f1eee2 68%,#f4e6d5 100%) !important;
}

/* TOPBAR */
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

.system-name { display: none !important; }

.system-brand-copy {
    position: relative !important;
    padding-left: 18px !important;
    line-height: 1.15 !important;
}

.system-brand-copy::before {
    content: "" !important;
    position: absolute !important;
    left: 0 !important;
    top: 50% !important;
    width: 3px !important;
    height: 38px !important;
    transform: translateY(-50%) !important;
    border-radius: 999px !important;
    background: var(--asu-green) !important;
}

.system-brand-copy strong {
    display: block !important;
    color: var(--asu-ink) !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    letter-spacing: -.015em !important;
}

.system-brand-copy span {
    display: block !important;
    margin-top: 4px !important;
    color: var(--asu-muted) !important;
    font-size: 13px !important;
    font-weight: 400 !important;
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

/* SMALL HERO — SAME AS RESEARCH / EXTENSION RECORDS */
.page-heading {
    position: relative !important;
    overflow: hidden !important;
    min-height: 96px !important;
    margin: 0 0 18px !important;
    padding: 20px 26px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    border-radius: 18px !important;
    background: linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%) !important;
    box-shadow: 0 18px 42px rgba(0,90,40,.16) !important;
}

.page-heading::after {
    content: "" !important;
    position: absolute !important;
    right: -110px !important;
    top: -120px !important;
    width: 430px !important;
    height: 280px !important;
    transform: rotate(-9deg) !important;
    border-radius: 42px !important;
    background: linear-gradient(135deg,rgba(255,213,31,.16),rgba(255,255,255,.035)) !important;
    pointer-events: none !important;
}

.page-heading > * {
    position: relative !important;
    z-index: 2 !important;
}

.page-heading h1 {
    margin: 0 !important;
    color: #fff !important;
    font-size: 30px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.03em !important;
}

.page-heading p,
.reports-scope-note {
    display: none !important;
}

/* FILTER CARD */
.filter-card {
    margin-bottom: 18px !important;
    padding: 17px 18px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.96) !important;
    box-shadow: 0 14px 34px rgba(16,24,16,.05) !important;
}

.filter-card .form-label {
    margin-bottom: 6px !important;
    color: #414943 !important;
    font-size: 10px !important;
    font-weight: 650 !important;
}

.filter-card .form-select,
.filter-card .form-control {
    min-height: 44px !important;
    height: 44px !important;
    padding: 8px 12px !important;
    border: 1px solid #d7e0d8 !important;
    border-radius: 11px !important;
    background: #fff !important;
    color: #263229 !important;
    font-size: 11px !important;
    box-shadow: none !important;
}

/* Restore the dropdown chevron hidden by the white background override.
   Applies only to the four Reports filter selects, without changing their size. */
.reports-admin-page .filter-card select.form-select {
    -webkit-appearance: none !important;
    appearance: none !important;
    background-color: #fff !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m4 6 4 4 4-4' fill='none' stroke='%23657068' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 14px 14px !important;
    padding-right: 38px !important;
}

.filter-card .form-select:focus,
.filter-card .form-control:focus {
    border-color: #76ac82 !important;
    box-shadow: 0 0 0 3px rgba(8,117,44,.07) !important;
}

.report-filter-actions {
    margin-top: 2px !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
}

.report-filter-actions .btn {
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
}

.report-filter-actions .btn-asu {
    border: 0 !important;
    background: linear-gradient(135deg,var(--asu-green),var(--asu-green-dark)) !important;
    color: #fff !important;
    box-shadow: 0 8px 18px rgba(0,122,51,.12) !important;
}

.report-filter-actions .btn-outline-asu {
    border: 1px solid rgba(0,122,51,.24) !important;
    background: #fff !important;
    color: var(--asu-green) !important;
}

/* STAT CARDS */
.stats-row {
    display: grid !important;
    grid-template-columns: repeat(4,minmax(0,1fr)) !important;
    gap: 14px !important;
    margin-bottom: 18px !important;
}

.stat-card {
    position: relative !important;
    overflow: hidden !important;
    min-height: 112px !important;
    padding: 18px !important;
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.97) !important;
    box-shadow: 0 14px 32px rgba(16,24,16,.055) !important;
}

.stat-card::after {
    content: "" !important;
    position: absolute !important;
    right: -42px !important;
    bottom: -48px !important;
    width: 145px !important;
    height: 92px !important;
    border-radius: 50% !important;
    background: rgba(0,122,51,.055) !important;
    pointer-events: none !important;
}

.stat-card:nth-child(even)::after {
    background: rgba(255,213,31,.13) !important;
}

.stat-icon {
    position: relative !important;
    z-index: 1 !important;
    width: 48px !important;
    height: 48px !important;
    min-width: 48px !important;
    border-radius: 13px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 19px !important;
}

.stat-icon.green {
    background: #eaf6ed !important;
    color: var(--asu-green) !important;
}

.stat-icon.yellow {
    background: #fff6cf !important;
    color: #a66d00 !important;
}

.stat-content {
    position: relative !important;
    z-index: 1 !important;
}

.stat-content strong {
    display: block !important;
    margin-bottom: 3px !important;
    color: #142018 !important;
    font-size: 24px !important;
    line-height: 1 !important;
    font-weight: 850 !important;
}

.stat-content span {
    display: block !important;
    color: #6a756d !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 10px !important;
    line-height: 1.25 !important;
    font-weight: 700 !important;
}

/* MAIN PANELS */
.asu-panel {
    overflow: hidden !important;
    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;
    background: rgba(255,255,255,.97) !important;
    box-shadow: 0 16px 38px rgba(16,24,16,.055) !important;
}

.reports-upper-row {
    --bs-gutter-y: 18px !important;
}

.panel-header {
    min-height: 62px !important;
    padding: 14px 18px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    border-bottom: 1px solid rgba(0,122,51,.09) !important;
    background: rgba(255,255,255,.88) !important;
}

.panel-title {
    margin: 0 !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    color: #101810 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 14px !important;
    font-weight: 700 !important;
}

.panel-title-icon {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 10px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
}

.panel-body {
    padding: 16px 18px 18px !important;
}

/* AVAILABLE REPORT CARDS */
.report-card-link {
    display: block !important;
    color: inherit !important;
    text-decoration: none !important;
}

.report-card {
    position: relative !important;
    overflow: hidden !important;
    min-height: 82px !important;
    margin-bottom: 10px !important;
    padding: 14px 15px !important;
    display: flex !important;
    align-items: center !important;
    gap: 13px !important;
    border: 1px solid rgba(0,122,51,.11) !important;
    border-radius: 14px !important;
    background: #fff !important;
    box-shadow: 0 7px 18px rgba(16,24,16,.035) !important;
}

.report-card::after {
    content: "" !important;
    position: absolute !important;
    right: -30px !important;
    bottom: -38px !important;
    width: 110px !important;
    height: 72px !important;
    border-radius: 50% !important;
    background: rgba(0,122,51,.045) !important;
    pointer-events: none !important;
}

.report-card-clickable:hover {
    transform: translateY(-1px) !important;
    border-color: rgba(0,122,51,.23) !important;
    box-shadow: 0 11px 24px rgba(16,24,16,.065) !important;
}

.report-card-icon {
    position: relative !important;
    z-index: 1 !important;
    width: 42px !important;
    height: 42px !important;
    min-width: 42px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 12px !important;
    background: #edf7ef !important;
    color: var(--asu-green) !important;
    font-size: 17px !important;
}

.report-card .flex-grow-1,
.report-card .badge {
    position: relative !important;
    z-index: 1 !important;
}

.report-card h6 {
    margin: 0 0 3px !important;
    color: #1f2b22 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 11px !important;
    font-weight: 700 !important;
}

.report-card p {
    margin: 0 !important;
    color: #7b847d !important;
    font-size: 9px !important;
    line-height: 1.45 !important;
}

.report-card .badge {
    padding: 6px 9px !important;
    border-radius: 999px !important;
    background: #f2f7f3 !important;
    color: #647168 !important;
    font-size: 8.5px !important;
    font-weight: 800 !important;
}

/* CHART */
.report-chart-wrap { padding: 14px 18px 18px !important; }
.report-chart-meta {
    margin-bottom: 12px !important;
    color: #758078 !important;
    font-size: 9.5px !important;
}
.report-chart-canvas {
    height: 255px !important;
    max-height: 255px !important;
}

/* REPORT PREVIEW */
#reportPreview { margin-top: 0 !important; }

.report-preview-head {
    padding: 18px !important;
    border-bottom: 1px solid rgba(0,122,51,.08) !important;
}

.report-preview-head h3 {
    margin: 0 0 5px !important;
    color: #1f2b22 !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 14px !important;
    font-weight: 700 !important;
}

.report-preview-meta {
    color: #78827b !important;
    font-size: 9px !important;
}

.report-preview-actions .btn,
.panel-header .btn {
    min-height: 36px !important;
    padding: 0 11px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    border-radius: 9px !important;
    font-size: 9.5px !important;
    font-weight: 800 !important;
}

.report-table-wrap { padding: 16px 18px 18px !important; }

.report-table {
    min-width: 760px !important;
    font-size: 10px !important;
}

.report-table thead th {
    padding: 11px 12px !important;
    border-bottom: 1px solid rgba(0,122,51,.11) !important;
    background: #f8fbf8 !important;
    color: #35423a !important;
    font-family: "Century Gothic", sans-serif !important;
    font-size: 9px !important;
    font-weight: 700 !important;
}

.report-table td {
    padding: 11px 12px !important;
    border-bottom: 1px solid rgba(0,122,51,.07) !important;
    color: #344038 !important;
    font-size: 10px !important;
}

/* CLEANUP */
.design-notice { display: none !important; }

.content-footer {
    margin-top: 20px !important;
    padding-top: 12px !important;
    color: #7c857e !important;
    font-size: 9px !important;
    opacity: .8 !important;
}

@media (max-width: 1100px) {
    .stats-row {
        grid-template-columns: repeat(2,minmax(0,1fr)) !important;
    }
}

@media (max-width: 760px) {
    .asu-topbar { padding: 0 16px !important; }
    .system-brand-copy strong { font-size: 13px !important; }
    .system-brand-copy span { font-size: 10px !important; }
    .reports-admin-page { padding: 18px 14px 24px !important; }

    .page-heading {
        min-height: 82px !important;
        padding: 18px !important;
        border-radius: 16px !important;
    }

    .page-heading h1 { font-size: 27px !important; }
    .stats-row { grid-template-columns: 1fr !important; }
    .filter-card { padding: 14px !important; }

    .report-filter-actions {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .report-filter-actions .btn { width: 100% !important; }

    .report-preview-head {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .report-preview-actions { width: 100% !important; }
    .report-preview-actions .btn { flex: 1 1 auto !important; }
}
</style>

<style>
/* =========================================================
   REPORTS & ANALYTICS — CARDS MATCH USER MANAGEMENT
========================================================= */

/* Statistics grid stays 4 across on wide screens */
.reports-admin-page .stats-row {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 14px !important;
    margin: 0 0 20px !important;
}

/* Exact same card language as User Management stat cards */
.reports-admin-page .stat-card {
    position: relative !important;
    overflow: hidden !important;

    min-width: 0 !important;
    min-height: 132px !important;

    padding: 16px 15px 20px !important;

    display: grid !important;
    grid-template-columns: 52px minmax(0, 1fr) !important;
    align-items: center !important;
    gap: 13px !important;

    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;

    background: rgba(255,255,255,.95) !important;

    box-shadow: 0 14px 34px rgba(16,24,16,.055) !important;
}

/* Curved bottom accent */
.reports-admin-page .stat-card::after {
    content: "" !important;

    position: absolute !important;

    left: -5% !important;
    right: -5% !important;
    bottom: -36px !important;

    height: 54px !important;

    border-radius: 50% 50% 0 0 !important;

    opacity: .22 !important;

    pointer-events: none !important;
}

.reports-admin-page .stat-card:nth-child(odd)::after {
    background: #007a33 !important;
}

.reports-admin-page .stat-card:nth-child(even)::after {
    background: #ffd51f !important;
    opacity: .34 !important;
}

/* Same icon blocks */
.reports-admin-page .stat-icon {
    position: relative !important;
    z-index: 2 !important;

    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;

    border-radius: 15px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    font-size: 22px !important;
}

.reports-admin-page .stat-icon.green {
    background: linear-gradient(
        135deg,
        #007a33,
        #005c26
    ) !important;

    color: #ffffff !important;

    box-shadow: 0 10px 18px rgba(0,122,51,.20) !important;
}

.reports-admin-page .stat-icon.yellow {
    background: linear-gradient(
        135deg,
        #ffd51f,
        #f0bd00
    ) !important;

    color: #003f1a !important;

    box-shadow: 0 10px 18px rgba(255,213,31,.25) !important;
}

.reports-admin-page .stat-content {
    position: relative !important;
    z-index: 2 !important;
    min-width: 0 !important;
}

.reports-admin-page .stat-content strong {
    display: block !important;

    margin: 0 0 6px !important;

    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 28px !important;
    line-height: 1 !important;
    font-weight: 900 !important;
    letter-spacing: -.035em !important;
}

.reports-admin-page .stat-content span {
    display: block !important;

    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 12.5px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

/* =========================================================
   MAIN WHITE PANELS — SAME CARD FAMILY
========================================================= */

.reports-admin-page .asu-panel {
    overflow: hidden !important;

    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;

    background: rgba(255,255,255,.96) !important;

    box-shadow: 0 16px 38px rgba(16,24,16,.06) !important;
}

.reports-admin-page .panel-header {
    min-height: 66px !important;
    padding: 14px 18px !important;

    border-bottom: 1px solid rgba(0,122,51,.09) !important;

    background: rgba(255,255,255,.84) !important;
}

.reports-admin-page .panel-title {
    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
}

/* =========================================================
   AVAILABLE REPORT CARDS — SAME WHITE CARD FAMILY
========================================================= */

.reports-admin-page .report-card {
    position: relative !important;
    overflow: hidden !important;

    min-height: 92px !important;

    margin-bottom: 12px !important;
    padding: 15px 16px !important;

    display: grid !important;
    grid-template-columns: 52px minmax(0,1fr) auto !important;
    align-items: center !important;
    gap: 13px !important;

    border: 1px solid rgba(0,122,51,.13) !important;
    border-radius: 18px !important;

    background: rgba(255,255,255,.96) !important;

    box-shadow: 0 10px 26px rgba(16,24,16,.045) !important;
}

/* Same curved accent used by the cards */
.reports-admin-page .report-card::after {
    content: "" !important;

    position: absolute !important;

    left: -4% !important;
    right: -4% !important;
    bottom: -42px !important;

    height: 58px !important;

    border-radius: 50% 50% 0 0 !important;

    background: #007a33 !important;

    opacity: .08 !important;

    pointer-events: none !important;
}

.reports-admin-page .report-card:nth-child(even)::after {
    background: #ffd51f !important;
    opacity: .16 !important;
}

.reports-admin-page .report-card > * {
    position: relative !important;
    z-index: 2 !important;
}

.reports-admin-page .report-card-icon {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 15px !important;

    background: linear-gradient(
        135deg,
        #007a33,
        #005c26
    ) !important;

    color: #ffffff !important;

    font-size: 21px !important;

    box-shadow: 0 10px 18px rgba(0,122,51,.18) !important;
}

.reports-admin-page .report-card h6 {
    margin: 0 0 4px !important;

    color: #101810 !important;

    font-family: "Century Gothic", sans-serif !important;
    font-size: 12.5px !important;
    font-weight: 700 !important;
}

.reports-admin-page .report-card p {
    margin: 0 !important;

    color: #6f7972 !important;

    font-size: 9.5px !important;
    line-height: 1.45 !important;
}

.reports-admin-page .report-card .badge {
    padding: 7px 10px !important;

    border-radius: 999px !important;

    background: #f2f7f3 !important;
    color: #607267 !important;

    font-size: 9px !important;
    font-weight: 800 !important;
}

/* Last card should not keep extra bottom spacing */
.reports-admin-page .panel-body > .report-card-link:last-child .report-card {
    margin-bottom: 0 !important;
}

/* =========================================================
   REMOVE EXTRA PAGE COPYRIGHT
========================================================= */

.reports-admin-page .content-footer {
    display: none !important;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {
    .reports-admin-page .stats-row {
        grid-template-columns: repeat(2, minmax(0,1fr)) !important;
    }
}

@media (max-width: 760px) {
    .reports-admin-page .stats-row {
        grid-template-columns: 1fr !important;
    }

    .reports-admin-page .stat-card {
        min-height: 122px !important;
    }

    .reports-admin-page .report-card {
        grid-template-columns: 46px minmax(0,1fr) !important;
    }

    .reports-admin-page .report-card-icon {
        width: 46px !important;
        height: 46px !important;
        min-width: 46px !important;
    }

    .reports-admin-page .report-card .badge {
        grid-column: 2 !important;
        justify-self: start !important;
    }
}
</style>

<style>
/* =========================================================
   REPORTS STATS — EXACT SAME CARD SIZE AS USER MANAGEMENT
========================================================= */

/*
 * User Management uses FIVE equal columns.
 * Keep the same column width here even though Reports has four cards,
 * so the cards no longer stretch wider than the reference design.
 */
.reports-admin-page .stats-row {
    display: grid !important;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 14px !important;
    margin: 0 0 20px !important;
    align-items: stretch !important;
}

.reports-admin-page .stat-card {
    min-width: 0 !important;
    width: 100% !important;
    min-height: 132px !important;
    height: 132px !important;
    padding: 16px 15px 20px !important;

    display: grid !important;
    grid-template-columns: 52px minmax(0, 1fr) !important;
    align-items: center !important;
    gap: 13px !important;

    border-radius: 18px !important;
}

.reports-admin-page .stat-icon {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    min-height: 52px !important;
    border-radius: 15px !important;
    font-size: 22px !important;
}

.reports-admin-page .stat-content strong {
    margin: 0 0 6px !important;
    font-size: 28px !important;
    line-height: 1 !important;
}

.reports-admin-page .stat-content span {
    font-size: 12.5px !important;
    line-height: 1.18 !important;
    font-weight: 700 !important;
}

.reports-admin-page .stat-card::after {
    left: -5% !important;
    right: -5% !important;
    bottom: -36px !important;
    height: 54px !important;
    border-radius: 50% 50% 0 0 !important;
}

/* SAME responsive behavior as User Management */
@media (max-width: 1250px) {
    .reports-admin-page .stats-row {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 900px) {
    .reports-admin-page .stats-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 580px) {
    .reports-admin-page .stats-row {
        grid-template-columns: 1fr !important;
    }

    .reports-admin-page .stat-card {
        height: auto !important;
        min-height: 122px !important;
    }
}
</style>

<style>
/* =========================================================
   REPORTS STAT CARD CURVE — EXACT USER MANAGEMENT STYLE
   Keep current card size; fix only the bottom color curve.
========================================================= */

.reports-admin-page .stat-card::after {
    content: "" !important;

    position: absolute !important;

    /* Force the accent to span the full bottom width */
    left: -5% !important;
    right: -5% !important;
    width: auto !important;

    top: auto !important;
    bottom: -36px !important;

    height: 54px !important;

    border-radius: 50% 50% 0 0 !important;

    transform: none !important;

    pointer-events: none !important;
}

.reports-admin-page .stat-card:nth-child(odd)::after {
    background: #007a33 !important;
    opacity: .22 !important;
}

.reports-admin-page .stat-card:nth-child(even)::after {
    background: #ffd51f !important;
    opacity: .34 !important;
}
</style>

<style>
/* =========================================================
   REPORTS STATS — MAXIMIZE 4 CARDS ACROSS THE FULL ROW
   Keep the approved full-width bottom curve design.
========================================================= */

.reports-admin-page .stats-row {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 14px !important;
    width: 100% !important;
    margin: 0 0 20px !important;
    align-items: stretch !important;
}

.reports-admin-page .stat-card {
    width: 100% !important;
    max-width: none !important;
    min-width: 0 !important;

    min-height: 132px !important;
    height: 132px !important;

    padding: 16px 18px 20px !important;

    grid-template-columns: 52px minmax(0, 1fr) !important;
    gap: 15px !important;
}

/* Keep the color curve spanning the full bottom of every wider card */
.reports-admin-page .stat-card::after {
    left: -5% !important;
    right: -5% !important;
    width: auto !important;

    top: auto !important;
    bottom: -36px !important;

    height: 54px !important;

    border-radius: 50% 50% 0 0 !important;
    transform: none !important;
}

/* Responsive: still balanced when screen gets smaller */
@media (max-width: 1100px) {
    .reports-admin-page .stats-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 580px) {
    .reports-admin-page .stats-row {
        grid-template-columns: 1fr !important;
    }

    .reports-admin-page .stat-card {
        height: auto !important;
        min-height: 122px !important;
    }
}
</style>

<style>
/* =========================================================
   REPORTS & ANALYTICS — STEADY SCROLL ONLY
   Normal scrolling stays enabled.
   Prevents the whole page from pulling/bouncing past
   the top or bottom edge.
========================================================= */

html,
body {
    overscroll-behavior-x: none !important;
    overscroll-behavior-y: none !important;
}

.page-content.reports-admin-page {
    overscroll-behavior-y: contain !important;
}
</style>

<style id="asu-rems-reports-dark-mode">

/* ================================================================
   ASU-REMS RESEARCH / ADMIN REPORTS — DARK MODE ONLY
   RPS and Faculty analytics. The PDF is generated in PHP above and its
   printable white paper design is deliberately never modified here.
================================================================ */
html[data-rems-theme="dark"] body:has(.reports-admin-page) .main-page,
html[data-rems-theme="dark"] .page-content.reports-admin-page {
    background:#0b1d15 !important;
    color:#eaf4eb !important;
}
html[data-rems-theme="dark"] body:has(.reports-admin-page) .asu-topbar {
    background:#10281c !important;
    border-color:#355843 !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] body:has(.reports-admin-page) .asu-topbar :is(
    .system-brand-copy strong,.topbar-user-text strong
) {color:#f1f7ef !important;}
html[data-rems-theme="dark"] body:has(.reports-admin-page) .asu-topbar :is(
    .system-brand-copy span,.topbar-user-text span
) {color:#bad0be !important;}
/* The bright green ASU hero remains a brand element in both modes. */
html[data-rems-theme="dark"] .reports-admin-page .page-heading h1 {color:#ffffff !important;}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .filter-card,.asu-panel,.institutional-completed-card,.stat-card,.report-card
) {
    background:#132b20 !important;
    border-color:#355843 !important;
    color:#eaf4eb !important;
    box-shadow:none !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .panel-header,.institutional-completed-head,.report-preview-head
) {
    background:#1b392a !important;
    border-color:#3b5945 !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .stat-content strong,.stat-content span,
    .panel-title,.report-card h6,.report-preview-head h3,
    .institutional-completed-head h2,.report-zero-state h3
) {color:#f0f7ef !important;}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .report-card p,.report-preview-meta,.report-chart-meta,.report-note,
    .institutional-completed-meta,.report-zero-state,.report-zero-state p,
    .report-card .text-muted,.institutional-completed-empty
) {color:#bad0bf !important;}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .filter-card .form-label,.institutional-completed-form label
) {color:#dcebdc !important;}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .filter-card .form-control,.filter-card .form-select,
    .institutional-completed-form .form-select
) {
    background-color:#1c3a2a !important;
    border-color:#496b56 !important;
    color:#f2f8f0 !important;
    color-scheme:dark !important;
}
html[data-rems-theme="dark"] .reports-admin-page .filter-card input::placeholder {
    color:#c0d3c1 !important;
    opacity:1 !important;
}
/* Existing dropdown arrow is a dark SVG; replace it to avoid invisible icon. */
html[data-rems-theme="dark"] .reports-admin-page .filter-card select.form-select {
    background-color:#1c3a2a !important;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath d='m3 6 5 5 5-5' fill='none' stroke='%23e5f0e7' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
    background-repeat:no-repeat !important;
    background-position:right 14px center !important;
    background-size:14px 14px !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(.form-select option,.form-select optgroup) {
    background:#1c3a2a !important;
    color:#f2f8f0 !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .panel-title-icon,.institutional-completed-icon,.report-card-icon
) {
    background:#294e39 !important;
    border-color:#44684e !important;
    color:#c5e8c8 !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .report-card .badge,.stat-card .badge
) {border-color:#587760 !important;}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-form,.report-preview-meta,.report-card,.report-filter-actions
) {border-color:#3b5945 !important;}
html[data-rems-theme="dark"] .reports-admin-page .institutional-completed-section {
    background:#294a36 !important;
    color:#f1f7ef !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-table th,.institutional-completed-table td,
    .report-table th,.report-table td
) {border-color:#3d5946 !important;}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-table th,.report-table thead th
) {
    background:#254535 !important;
    color:#eef6ed !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-table td,.report-table td,
    .institutional-completed-table td strong
) {
    background:#132b20 !important;
    color:#e9f2e9 !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-table tbody tr:nth-child(even) td,
    .report-table tbody tr:nth-child(even) td
) {background:#1a3629 !important;}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-table tbody tr:hover td,
    .report-table tbody tr:hover td
) {background:#264635 !important;}
html[data-rems-theme="dark"] .reports-admin-page .report-chart-wrap,
html[data-rems-theme="dark"] .reports-admin-page .report-chart-canvas,
html[data-rems-theme="dark"] .reports-admin-page .panel-body {
    background:#132b20 !important;
    color:#eaf4eb !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-secondary,.report-filter-actions .btn-outline-asu,
    .panel-header .btn-outline-asu,.report-preview-actions .btn-outline-asu
) {
    background:#264b38 !important;
    border-color:#55745e !important;
    color:#edf7ed !important;
}
html[data-rems-theme="dark"] .reports-admin-page :is(
    .institutional-completed-secondary,.report-filter-actions .btn-outline-asu,
    .panel-header .btn-outline-asu,.report-preview-actions .btn-outline-asu
):hover {
    background:#365e47 !important;
    color:#ffffff !important;
}
html[data-rems-theme="dark"] .reports-admin-page .institutional-completed-empty strong {
    color:#eaf4eb !important;
}
html[data-rems-theme="dark"] .reports-admin-page .alert-warning {
    background:#4e3d1f !important;
    border-color:#88703c !important;
    color:#ffecbf !important;
}

</style>

<style id="rems-reports-visible-select-arrows">
/* Persistent CSS-drawn dropdown chevrons: no SVG background conflict in dark mode.
   Scoped to Reports select controls only; PHP/filters/exports remain unchanged. */
.reports-admin-page .filter-card .col-lg-3:has(> select.form-select),
.reports-admin-page .institutional-completed-form > div:has(> select.form-select),
.extension-reports-page .extension-reports-generator-form > div:has(> select.form-select),
.extension-reports-page .extension-reports-filters > div:has(> select.form-select) {
    position: relative !important;
}
.reports-admin-page .filter-card .col-lg-3:has(> select.form-select)::after,
.reports-admin-page .institutional-completed-form > div:has(> select.form-select)::after,
.extension-reports-page .extension-reports-generator-form > div:has(> select.form-select)::after,
.extension-reports-page .extension-reports-filters > div:has(> select.form-select)::after {
    content: "";
    position: absolute;
    right: 17px;
    bottom: 18px;
    z-index: 2;
    width: 9px;
    height: 9px;
    border-right: 2px solid #284b35;
    border-bottom: 2px solid #284b35;
    transform: rotate(45deg);
    pointer-events: none;
}
.reports-admin-page .filter-card .col-lg-3 > select.form-select,
.reports-admin-page .institutional-completed-form > div > select.form-select,
.extension-reports-page .extension-reports-generator-form > div > select.form-select,
.extension-reports-page .extension-reports-filters > div > select.form-select {
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    background-image: none !important;
    padding-right: 42px !important;
}
html[data-rems-theme="dark"] .reports-admin-page .filter-card .col-lg-3 > select.form-select,
html[data-rems-theme="dark"] .reports-admin-page .institutional-completed-form > div > select.form-select,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-generator-form > div > select.form-select,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-filters > div > select.form-select {
    background-image: none !important;
}
html[data-rems-theme="dark"] .reports-admin-page .filter-card .col-lg-3:has(> select.form-select)::after,
html[data-rems-theme="dark"] .reports-admin-page .institutional-completed-form > div:has(> select.form-select)::after,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-generator-form > div:has(> select.form-select)::after,
html[data-rems-theme="dark"] .extension-reports-page .extension-reports-filters > div:has(> select.form-select)::after {
    border-color: #f1f8ef;
}
</style>

<div class="main-page">


    <!-- TOPBAR -->

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
                <strong>Research &amp; Extension Management System</strong>
                <span>Aklan State University</span>
            </div>


        </div>



        <div class="topbar-user">


            <div class="topbar-user-text">


                <strong>

                    <?= e(
                        fullName($user)
                    ); ?>

                </strong>


                <span>

                    <?= e(
                        $displayRole
                    ); ?>

                </span>


            </div>



            <div class="dropdown">


                <button
                    type="button"
                    class="user-dropdown-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <div class="user-avatar dashboard-avatar">
                        <?php $remsHeaderAvatarPath = remsProfilePictureRelativePath($pdo, (int)($user['user_id'] ?? 0)); ?>
                        <?php if ($remsHeaderAvatarPath !== ''): ?>
                            <img class="dashboard-avatar-photo" src="<?= e(rtrim(BASE_URL, '/') . '/' . $remsHeaderAvatarPath); ?>" alt="">
                        <?php else: ?>
                            <?= e($initials); ?>
                        <?php endif; ?>
                    </div>

                </button>


                <ul
                    class="
                        dropdown-menu
                        dropdown-menu-end
                    "
                >


                    <li>

                        <div
                            class="
                                dropdown-item-text
                            "
                        >

                            <strong class="d-block">

                                <?= e(
                                    fullName($user)
                                ); ?>

                            </strong>


                            <small class="text-muted">

                                <?= e(
                                    $user['email']
                                ); ?>

                            </small>

                        </div>

                    </li>


                    <li>

                        <hr class="dropdown-divider">

                    </li>


                    <li>

                        <a
                            href="<?= BASE_URL; ?>/logout.php"
                            class="dropdown-item"
                        >

                            <i
                                class="
                                    bi
                                    bi-box-arrow-right
                                    me-2
                                "
                            ></i>

                            Logout

                        </a>

                    </li>


                </ul>


            </div>


        </div>


    </header>



    <!-- CONTENT -->

    <main class="page-content reports-admin-page">


        <?php if (
            !$reportGenerated
        ): ?>


            <!-- PAGE HEADING -->

            <div class="page-heading">


                <div>


                    <h1>

                        Reports & Analytics

                    </h1>


                    <p>

                        Generate descriptive research
                        and extension reports from
                        actual ASU-REMS records.

                    </p>


                    <span class="reports-scope-note">

                        <i class="bi bi-shield-check"></i>

                        <?= e(
                            $scopeText
                        ); ?>

                    </span>


                </div>

                    <?php if (in_array($userRole, ['admin', 'rps'], true)): ?>
                        <a class="btn reports-institutional-pdf-btn" href="#institutionalCompletedGenerator"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Generate Completed Reports</a>
                    <?php endif; ?>

            </div>



            <?php if (in_array($userRole, ['admin', 'rps'], true)): ?>
                <section class="institutional-completed-card" id="institutionalCompletedGenerator" aria-labelledby="institutionalCompletedTitle">
                    <div class="institutional-completed-head">
                        <span class="institutional-completed-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span>
                        <h2 id="institutionalCompletedTitle">Generate Completed Reports</h2>
                    </div>
                    <form class="institutional-completed-form" method="get" action="<?= BASE_URL; ?>/reports/index.php">
                        <input type="hidden" name="completed_report_preview" value="1">
                        <div>
                            <label for="researchCompletedType">Report Type</label>
                            <select class="form-select" id="researchCompletedType" name="pdf_type">
                                <option value="all" <?= $institutionalPdfType === 'all' ? 'selected' : ''; ?>><?= $userRole === 'rps' ? 'All Completed Research Records' : 'All Completed Research and Extension Records'; ?></option>
                                <?php foreach ($institutionalPdfSpecs as $specKey => $spec): ?>
                                    <option value="<?= e($specKey); ?>" <?= $institutionalPdfType === $specKey ? 'selected' : ''; ?>><?= e($spec['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="researchCompletedYear">Year</label>
                            <select class="form-select" id="researchCompletedYear" name="pdf_year">
                                <option value="" <?= $institutionalPdfYear === '' ? 'selected' : ''; ?>>All Years</option>
                                <?php for ($pdfYearOption = (int)date('Y') + 1; $pdfYearOption >= 2015; $pdfYearOption--): ?>
                                    <option value="<?= $pdfYearOption; ?>" <?= $institutionalPdfYear === (string)$pdfYearOption ? 'selected' : ''; ?>><?= $pdfYearOption; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="institutional-completed-form-actions">
                            <button type="submit" class="btn institutional-completed-primary"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Generate Report</button>
                        </div>
                    </form>
                </section>
                <?php if ($institutionalCompletedPreview): ?>
                    <section class="institutional-completed-card institutional-completed-preview" id="institutionalCompletedPreview" aria-labelledby="institutionalPreviewTitle">
                        <div class="institutional-completed-head institutional-preview-head">
                            <div class="institutional-completed-title">
                                <span class="institutional-completed-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
                                <h2 id="institutionalPreviewTitle">Generated Completed Report</h2>
                            </div>
                            <div class="institutional-completed-preview-actions">
                                <a class="btn institutional-completed-secondary" href="<?= BASE_URL; ?>/reports/index.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Reports</a>
                                <?php if ($institutionalPreviewError === ''): ?>
                                    <a class="btn institutional-completed-primary" href="<?= BASE_URL; ?>/reports/index.php?<?= e(http_build_query(['download_completed_pdf' => '1', 'pdf_type' => $institutionalPdfType, 'pdf_year' => $institutionalPdfYear])); ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Download PDF</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($institutionalPreviewError !== ''): ?>
                            <div class="alert alert-warning m-3" role="alert"><?= e($institutionalPreviewError); ?></div>
                        <?php else: ?>
                            <p class="institutional-completed-meta">Completed records only &nbsp; | &nbsp; <?= $institutionalPdfYear !== '' ? e($institutionalPdfYear) : 'All years'; ?> &nbsp; | &nbsp; Total: <strong><?= number_format($institutionalPreviewTotal); ?></strong></p>
                            <?php if (!$institutionalPreviewTotal): ?>
                                <div class="institutional-completed-empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>No completed records found for your selection.</strong></div>
                            <?php else: ?>
                                <?php foreach ($institutionalPreviewSections as $previewSection): ?>
                                    <?php if (!$previewSection['records']) continue; ?>
                                    <h3 class="institutional-completed-section"><?= e($previewSection['label']); ?></h3>
                                    <div class="institutional-completed-table-wrap">
                                        <table class="institutional-completed-table">
                                            <thead><tr><th>No.</th><th>Project / Record</th><th>Reference</th><th>Proponent</th><th>Date Completed</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($previewSection['records'] as $recordIndex => $record): ?>
                                                    <tr>
                                                        <td><?= $recordIndex + 1; ?></td>
                                                        <td><strong><?= e($record['title']); ?></strong></td>
                                                        <td><?= e($record['code']); ?></td>
                                                        <td><?= e($record['person']); ?></td>
                                                        <td><?= e($record['date']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!$institutionalCompletedPreview): ?>

            <?php if (!in_array($userRole, ['admin', 'rps'], true)): ?>
            <!-- FILTERS -->

            <section class="filter-card">


                <form
                    method="get"
                    action=""
                    id="reportFilterForm"
                >


                    <div class="row g-3">


                        <!-- REPORT TYPE -->

                        <div class="col-lg-3">


                            <label
                                for="reportType"
                                class="form-label small"
                            >

                                Report Type

                            </label>


                            <select
                                id="reportType"
                                name="report_type"
                                class="form-select"
                                required
                            >


                                <option value="">

                                    Select Report Type

                                </option>


                                <?php foreach (
                                    $allowedReportTypes
                                    as
                                    $key =>
                                    $definition
                                ): ?>


                                    <option
                                        value="<?= e($key); ?>"
                                        <?= $reportType === $key
                                            ? 'selected'
                                            : ''; ?>
                                    >

                                        <?= e(
                                            $definition[
                                                'label'
                                            ]
                                        ); ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>



                        <!-- YEAR -->

                        <div class="col-lg-3">


                            <label
                                for="reportYear"
                                class="form-label small"
                            >

                                Year

                            </label>


                            <select
                                id="reportYear"
                                name="year"
                                class="form-select"
                            >


                                <option value="">

                                    All Years

                                </option>


                                <?php foreach (
                                    $yearValues
                                    as
                                    $availableYear
                                ): ?>


                                    <option
                                        value="<?= (int)$availableYear; ?>"
                                        <?= $year ===
                                            (string)$availableYear
                                            ? 'selected'
                                            : ''; ?>
                                    >

                                        <?= (int)$availableYear; ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>



                        <!-- STATUS -->

                        <div class="col-lg-3">


                            <label
                                for="reportStatus"
                                class="form-label small"
                            >

                                Status

                            </label>


                            <select
                                id="reportStatus"
                                name="status"
                                class="form-select"
                            >


                                <option value="">

                                    All Status

                                </option>


                                <?php foreach (
                                    $validStatuses
                                    as
                                    $availableStatus
                                ): ?>


                                    <?php

                                    if (
                                        in_array(
                                            $userRole,
                                            [
                                                'admin',
                                                'rps'
                                            ],
                                            true
                                        )
                                        &&
                                        $availableStatus
                                        ===
                                        'Draft'
                                    ) {

                                        continue;
                                    }

                                    ?>


                                    <option
                                        value="<?= e(
                                            $availableStatus
                                        ); ?>"
                                        <?= $status ===
                                            $availableStatus
                                            ? 'selected'
                                            : ''; ?>
                                    >

                                        <?= e(
                                            $availableStatus
                                        ); ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>



                        <!-- UNIT -->

                        <div class="col-lg-3">


                            <label
                                for="reportUnit"
                                class="form-label small"
                            >

                                College / Department

                            </label>


                            <select
                                id="reportUnit"
                                name="unit"
                                class="form-select"
                            >


                                <option value="">

                                    All Colleges / Departments

                                </option>


                                <?php if ($colleges): ?>


                                    <optgroup label="Colleges">


                                        <?php foreach (
                                            $colleges
                                            as
                                            $college
                                        ): ?>


                                            <?php

                                            $value =
                                                'college:'
                                                .
                                                (int)$college[
                                                    'college_id'
                                                ];

                                            ?>


                                            <option
                                                value="<?= e($value); ?>"
                                                <?= $unit === $value
                                                    ? 'selected'
                                                    : ''; ?>
                                            >

                                                <?= e(
                                                    $college[
                                                        'college_name'
                                                    ]
                                                ); ?>

                                            </option>


                                        <?php endforeach; ?>


                                    </optgroup>


                                <?php endif; ?>



                                <?php if ($departments): ?>


                                    <optgroup label="Departments">


                                        <?php foreach (
                                            $departments
                                            as
                                            $department
                                        ): ?>


                                            <?php

                                            $value =
                                                'department:'
                                                .
                                                (int)$department[
                                                    'department_id'
                                                ];

                                            ?>


                                            <option
                                                value="<?= e($value); ?>"
                                                <?= $unit === $value
                                                    ? 'selected'
                                                    : ''; ?>
                                            >

                                                <?= e(
                                                    $department[
                                                        'department_name'
                                                    ]
                                                    .
                                                    (
                                                        !empty(
                                                            $department[
                                                                'college_name'
                                                            ]
                                                        )

                                                            ? ' — '
                                                                .
                                                                $department[
                                                                    'college_name'
                                                                ]

                                                            : ''
                                                    )
                                                ); ?>

                                            </option>


                                        <?php endforeach; ?>


                                    </optgroup>


                                <?php endif; ?>


                            </select>


                        </div>



                        <!-- BUTTONS -->

                        <div class="col-12">


                            <div class="report-filter-actions">


                                <button
                                    id="generateReportButton"
                                    type="submit"
                                    class="btn btn-asu"
                                    <?= $reportType === ''
                                        ? 'disabled'
                                        : ''; ?>
                                >

                                    <i
                                        class="
                                            bi
                                            bi-bar-chart
                                            me-1
                                        "
                                    ></i>

                                    Generate Report

                                </button>


                                <a
                                    href="<?= BASE_URL; ?>/reports/index.php"
                                    class="
                                        btn
                                        btn-outline-asu
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-arrow-counterclockwise
                                            me-1
                                        "
                                    ></i>

                                    Reset Filters

                                </a>


                            </div>


                        </div>


                    </div>


                </form>


            </section>
            <?php endif; ?>



            





            <!-- CHART -->

            <div
                class="
                    row
                    g-4
                    reports-upper-row
                "
            >


                <div class="col-12">


                    <section class="asu-panel">


                        <div class="panel-header">


                            <h2 class="panel-title">

                                <span class="panel-title-icon">

                                    <i class="bi bi-graph-up"></i>

                                </span>

                                Monthly Research & Extension Activities

                            </h2>


                        </div>


                        <div class="report-chart-wrap">


                            <div class="report-chart-meta">

                                <span>

                                    Activity year:

                                    <strong>

                                        <?= (int)$chartYear; ?>

                                    </strong>

                                </span>


                                <span>

                                    <?= e(
                                        $unitLabel
                                    ); ?>

                                </span>

                            </div>


                            <div class="report-chart-canvas">

                                <canvas
                                    id="monthlyActivityChart"
                                ></canvas>

                            </div>


                        </div>


                    </section>


                </div>



                <?php if (!in_array($userRole, ['admin', 'rps'], true)): ?>
                <!-- AVAILABLE REPORTS -->

                <div class="col-12">


                    <section class="asu-panel">


                        <div class="panel-header">


                            <h2 class="panel-title">

                                <span class="panel-title-icon">

                                    <i
                                        class="
                                            bi
                                            bi-file-earmark-bar-graph
                                        "
                                    ></i>

                                </span>

                                Available Reports

                            </h2>


                        </div>



                        <div class="panel-body">


                            <?php if (
                                isset(
                                    $allowedReportTypes[
                                        'research_projects'
                                    ]
                                )
                            ): ?>


                                <a
                                    class="report-card-link"
                                    href="?<?= e(
                                        http_build_query([
                                            'report_type' =>
                                                'research_projects',
                                            'year' =>
                                                $year,
                                            'status' =>
                                                $status,
                                            'unit' =>
                                                $unit
                                        ])
                                    ); ?>#reportPreview"
                                >


                                    <div
                                        class="
                                            report-card
                                            report-card-clickable
                                            mb-3
                                        "
                                    >


                                        <div class="report-card-icon">

                                            <i class="bi bi-journal-text"></i>

                                        </div>


                                        <div class="flex-grow-1">

                                            <h6>
                                                Research Projects Report
                                            </h6>

                                            <p>
                                                Summary of authorized research records.
                                            </p>

                                        </div>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:#eef3ef;
                                                color:#607267;
                                            "
                                        >

                                            <?= number_format(
                                                $researchReportCount
                                            ); ?>

                                            records

                                        </span>


                                    </div>


                                </a>


                            <?php endif; ?>



                            <?php if (
                                isset(
                                    $allowedReportTypes[
                                        'research_status'
                                    ]
                                )
                            ): ?>


                                <a
                                    class="report-card-link"
                                    href="?<?= e(
                                        http_build_query([
                                            'report_type' =>
                                                'research_status',
                                            'year' =>
                                                $year,
                                            'status' =>
                                                $status,
                                            'unit' =>
                                                $unit
                                        ])
                                    ); ?>#reportPreview"
                                >


                                    <div
                                        class="
                                            report-card
                                            report-card-clickable
                                            mb-3
                                        "
                                    >


                                        <div class="report-card-icon">

                                            <i class="bi bi-list-check"></i>

                                        </div>


                                        <div class="flex-grow-1">

                                            <h6>
                                                Research Status Report
                                            </h6>

                                            <p>
                                                Summary of research records grouped by status.
                                            </p>

                                        </div>


                                    </div>


                                </a>


                            <?php endif; ?>



                            <?php if (
                                isset(
                                    $allowedReportTypes[
                                        'completed_research'
                                    ]
                                )
                            ): ?>


                                <a
                                    class="report-card-link"
                                    href="?<?= e(
                                        http_build_query([
                                            'report_type' =>
                                                'completed_research',
                                            'year' =>
                                                $year,
                                            'status' =>
                                                '',
                                            'unit' =>
                                                $unit
                                        ])
                                    ); ?>#reportPreview"
                                >


                                    <div
                                        class="
                                            report-card
                                            report-card-clickable
                                            mb-3
                                        "
                                    >


                                        <div class="report-card-icon">

                                            <i
                                                class="
                                                    bi
                                                    bi-check-circle
                                                "
                                            ></i>

                                        </div>


                                        <div class="flex-grow-1">

                                            <h6>
                                                Completed Research Report
                                            </h6>

                                            <p>
                                                Completed research records available to your role.
                                            </p>

                                        </div>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:#eef3ef;
                                                color:#607267;
                                            "
                                        >

                                            <?= number_format(
                                                $totalCompletedResearch
                                            ); ?>

                                            records

                                        </span>


                                    </div>


                                </a>


                            <?php endif; ?>



                            <?php if (
                                isset(
                                    $allowedReportTypes[
                                        'extension_programs'
                                    ]
                                )
                            ): ?>


                                <a
                                    class="report-card-link"
                                    href="?<?= e(
                                        http_build_query([
                                            'report_type' =>
                                                'extension_programs',
                                            'year' =>
                                                $year,
                                            'status' =>
                                                $status,
                                            'unit' =>
                                                $unit
                                        ])
                                    ); ?>#reportPreview"
                                >


                                    <div
                                        class="
                                            report-card
                                            report-card-clickable
                                            mb-3
                                        "
                                    >


                                        <div class="report-card-icon">

                                            <i class="bi bi-megaphone"></i>

                                        </div>


                                        <div class="flex-grow-1">

                                            <h6>
                                                Extension Programs Report
                                            </h6>

                                            <p>
                                                Summary of extension implementation records.
                                            </p>

                                        </div>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:#eef3ef;
                                                color:#607267;
                                            "
                                        >

                                            <?= number_format(
                                                $extensionReportCount
                                            ); ?>

                                            records

                                        </span>


                                    </div>


                                </a>


                                <a
                                    class="report-card-link"
                                    href="?<?= e(
                                        http_build_query([
                                            'report_type' =>
                                                'extension_participants',
                                            'year' =>
                                                $year,
                                            'status' =>
                                                $status,
                                            'unit' =>
                                                $unit
                                        ])
                                    ); ?>#reportPreview"
                                >


                                    <div
                                        class="
                                            report-card
                                            report-card-clickable
                                            mb-3
                                        "
                                    >


                                        <div class="report-card-icon">

                                            <i class="bi bi-people"></i>

                                        </div>


                                        <div class="flex-grow-1">

                                            <h6>
                                                Extension Participant Report
                                            </h6>

                                            <p>
                                                Aggregate target participant values.
                                            </p>

                                        </div>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:#eef3ef;
                                                color:#607267;
                                            "
                                        >

                                            <?= number_format(
                                                $participantReportCount
                                            ); ?>

                                            target

                                        </span>


                                    </div>


                                </a>


                                <a
                                    class="report-card-link"
                                    href="?<?= e(
                                        http_build_query([
                                            'report_type' =>
                                                'extension_accomplishments',
                                            'year' =>
                                                $year,
                                            'status' =>
                                                $status,
                                            'unit' =>
                                                $unit
                                        ])
                                    ); ?>#reportPreview"
                                >


                                    <div
                                        class="
                                            report-card
                                            report-card-clickable
                                            mb-3
                                        "
                                    >


                                        <div class="report-card-icon">

                                            <i class="bi bi-check2-square"></i>

                                        </div>


                                        <div class="flex-grow-1">

                                            <h6>
                                                Extension Accomplishment Report
                                            </h6>

                                            <p>
                                                Completed programs with uploaded accomplishment files.
                                            </p>

                                        </div>


                                        <span
                                            class="
                                                badge
                                                rounded-pill
                                            "
                                            style="
                                                background:#eef3ef;
                                                color:#607267;
                                            "
                                        >

                                            <?= number_format(
                                                $accomplishmentReportCount
                                            ); ?>

                                            with files

                                        </span>


                                    </div>


                                </a>


                            <?php endif; ?>



                            <?php if (
                                isset(
                                    $allowedReportTypes[
                                        'annual_summary'
                                    ]
                                )
                            ): ?>


                                <a
                                    class="report-card-link"
                                    href="?<?= e(
                                        http_build_query([
                                            'report_type' =>
                                                'annual_summary',
                                            'year' =>
                                                $year,
                                            'status' =>
                                                $status,
                                            'unit' =>
                                                $unit
                                        ])
                                    ); ?>#reportPreview"
                                >


                                    <div
                                        class="
                                            report-card
                                            report-card-clickable
                                        "
                                    >


                                        <div class="report-card-icon">

                                            <i class="bi bi-calendar3"></i>

                                        </div>


                                        <div class="flex-grow-1">

                                            <h6>
                                                Annual Summary
                                            </h6>

                                            <p>
                                                Combined institutional research and extension summary.
                                            </p>

                                        </div>


                                    </div>


                                </a>


                            <?php endif; ?>


                        </div>


                    </section>


                </div>
                <?php endif; ?>

            </div>

            <?php endif; /* Keep the ordinary Research Analytics panels out of completed-preview mode. */ ?>

        <?php endif; ?>



        <!-- REPORT PREVIEW -->

        <?php if (
            $reportGenerated
        ): ?>


            <section
                class="
                    asu-panel
                    mt-4
                "
                id="reportPreview"
            >


                <div class="panel-header">


                    <h2 class="panel-title">

                        <span class="panel-title-icon">

                            <i
                                class="
                                    bi
                                    bi-file-earmark-text
                                "
                            ></i>

                        </span>

                        <?= e(
                            $generatedReportTitle
                        ); ?>

                    </h2>


                    <a
                        href="<?= BASE_URL; ?>/reports/index.php"
                        class="
                            btn
                            btn-outline-asu
                            btn-sm
                        "
                    >

                        <i
                            class="
                                bi
                                bi-arrow-left
                                me-1
                            "
                        ></i>

                        Back to Reports

                    </a>


                </div>



                <div class="report-preview-head">


                    <div>


                        <h3>

                            <?= e(
                                $generatedReportTitle
                            ); ?>

                        </h3>


                        <div class="report-preview-meta">


                            <span>

                                <i
                                    class="
                                        bi
                                        bi-calendar3
                                        me-1
                                    "
                                ></i>

                                Year:

                                <?= $year !== ''
                                    ? e($year)
                                    : 'All Years'; ?>

                            </span>


                            <span>

                                <i
                                    class="
                                        bi
                                        bi-funnel
                                        me-1
                                    "
                                ></i>

                                Status:

                                <?= $status !== ''
                                    ? e($status)
                                    : 'All Status'; ?>

                            </span>


                            <span>

                                <i
                                    class="
                                        bi
                                        bi-building
                                        me-1
                                    "
                                ></i>

                                <?= e(
                                    $unitLabel
                                ); ?>

                            </span>


                            <span>

                                <i
                                    class="
                                        bi
                                        bi-clock
                                        me-1
                                    "
                                ></i>

                                Generated:

                                <?= e(
                                    date(
                                        'M d, Y h:i A'
                                    )
                                ); ?>

                            </span>


                        </div>


                    </div>



                    <div class="report-preview-actions">
                        <?php if (in_array($userRole, ['admin', 'rps'], true)): ?>
                            <a class="btn btn-outline-asu btn-sm" href="<?= BASE_URL; ?>/reports/index.php?completed_report_preview=1&amp;pdf_type=all"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Completed Reports Preview</a>
                        <?php endif; ?>



                        <?php if (!in_array($userRole, ['admin', 'rps'], true)): ?>
                        <button
                            type="button"
                            class="
                                btn
                                btn-outline-asu
                                btn-sm
                            "
                            onclick="window.print()"
                        >

                            <i
                                class="
                                    bi
                                    bi-printer
                                    me-1
                                "
                            ></i>

                            Print / Save PDF

                        </button>
                        <?php endif; ?>


                        <a
                            href="?<?= e(
                                http_build_query([
                                    'report_type' =>
                                        $reportType,
                                    'year' =>
                                        $year,
                                    'status' =>
                                        $status,
                                    'unit' =>
                                        $unit,
                                    'export' =>
                                        'csv'
                                ])
                            ); ?>"
                            class="
                                btn
                                btn-asu
                                btn-sm
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-file-earmark-spreadsheet
                                    me-1
                                "
                            ></i>

                            Export CSV

                        </a>


                    </div>


                </div>



                <?php if (
                    $reportNote !== ''
                ): ?>


                    <div class="report-note">

                        <i
                            class="
                                bi
                                bi-info-circle
                                me-1
                            "
                        ></i>

                        <?= e(
                            $reportNote
                        ); ?>

                    </div>


                <?php endif; ?>



                <?php if (
                    !$reportRows
                ): ?>


                    <div class="report-zero-state">


                        <i class="bi bi-inbox"></i>


                        <h3>

                            No records found

                        </h3>


                        <p class="mb-0">

                            No authorized records matched the selected report criteria.

                        </p>


                    </div>


                <?php else: ?>


                    <div class="report-table-wrap">


                        <table
                            class="
                                table
                                table-hover
                                report-table
                            "
                        >


                            <thead>


                                <tr>


                                    <?php foreach (
                                        $reportColumns
                                        as
                                        $columnLabel
                                    ): ?>


                                        <th>

                                            <?= e(
                                                $columnLabel
                                            ); ?>

                                        </th>


                                    <?php endforeach; ?>


                                </tr>


                            </thead>



                            <tbody>


                                <?php foreach (
                                    $reportRows
                                    as
                                    $row
                                ): ?>


                                    <tr>


                                        <?php foreach (
                                            $reportColumns
                                            as
                                            $columnKey =>
                                            $columnLabel
                                        ): ?>


                                            <td>


                                                <?php if (
                                                    $columnKey
                                                    ===
                                                    'status'
                                                ): ?>


                                                    <span
                                                        class="
                                                            badge
                                                            <?= e(
                                                                reportStatusBadgeClass(
                                                                    (string)(
                                                                        $row[
                                                                            $columnKey
                                                                        ]
                                                                        ?? ''
                                                                    )
                                                                )
                                                            ); ?>
                                                        "
                                                    >

                                                        <?= e(
                                                            (string)(
                                                                $row[
                                                                    $columnKey
                                                                ]
                                                                ?? '—'
                                                            )
                                                        ); ?>

                                                    </span>


                                                <?php else: ?>


                                                    <?= e(
                                                        (string)(
                                                            $row[
                                                                $columnKey
                                                            ]
                                                            ?? '—'
                                                        )
                                                    ); ?>


                                                <?php endif; ?>


                                            </td>


                                        <?php endforeach; ?>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php endif; ?>


            </section>


        <?php endif; ?>



        <?php if (
            !$reportGenerated
        ): ?>


            <div class="design-notice">


                <strong>

                    Reports Module:

                </strong>

                Statistics, filters and descriptive
                charts use actual MySQL records
                available to the logged-in user.


            </div>


        <?php endif; ?>
</main>


</div>










<script
    src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
></script>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | REPORT BUTTON
        |--------------------------------------------------------------------------
        */

        const reportType =
            document.getElementById(
                'reportType'
            );


        const generateButton =
            document.getElementById(
                'generateReportButton'
            );


        if (
            reportType
            &&
            generateButton
        ) {

            const updateGenerateButton =
                function () {

                    generateButton.disabled =
                        reportType.value === '';
                };


            reportType.addEventListener(
                'change',
                updateGenerateButton
            );


            updateGenerateButton();
        }


        /*
        |--------------------------------------------------------------------------
        | CHART
        |--------------------------------------------------------------------------
        */

        const chartCanvas =
            document.getElementById(
                'monthlyActivityChart'
            );


        if (
            chartCanvas
            &&
            typeof Chart
                !== 'undefined'
        ) {

            const remsActivityChart = new Chart(
                chartCanvas,
                {

                    type: 'line',

                    data: {

                        labels: [

                            'Jan',
                            'Feb',
                            'Mar',
                            'Apr',
                            'May',
                            'Jun',
                            'Jul',
                            'Aug',
                            'Sep',
                            'Oct',
                            'Nov',
                            'Dec'
                        ],

                        datasets: [

                            {

                                label:
                                    'Research',

                                data:
                                    <?= json_encode(
                                        $monthlyResearch
                                    ); ?>,

                                borderColor:
                                    'rgba(8, 117, 44, 1)',

                                backgroundColor:
                                    'rgba(8, 117, 44, 0.10)',

                                borderWidth:
                                    3,

                                pointRadius:
                                    4,

                                tension:
                                    0.35,

                                fill:
                                    false
                            },

                            {

                                label:
                                    'Extension',

                                data:
                                    <?= json_encode(
                                        $monthlyExtension
                                    ); ?>,

                                borderColor:
                                    'rgba(220, 169, 0, 1)',

                                backgroundColor:
                                    'rgba(255, 205, 30, 0.10)',

                                borderWidth:
                                    3,

                                pointRadius:
                                    4,

                                tension:
                                    0.35,

                                fill:
                                    false
                            }
                        ]
                    },


                    options: {

                        responsive:
                            true,

                        maintainAspectRatio:
                            false,

                        animation: false,

                        interaction: {

                            mode:
                                'index',

                            intersect:
                                false
                        },


                        plugins: {

                            legend: {

                                position:
                                    'bottom'
                            }
                        },


                        scales: {

                            y: {

                                beginAtZero:
                                    true,

                                ticks: {

                                    precision:
                                        0,

                                    stepSize:
                                        1
                                }
                            }
                        }
                    }
                }
            );

            // Presentation-only chart theming; data and report filters are unchanged.
            function remsSyncReportChartTheme() {
                const isDark = document.documentElement.getAttribute('data-rems-theme') === 'dark';
                const labelColor = isDark ? '#dcebdc' : '#566359';
                const gridColor = isDark ? 'rgba(187,216,191,.16)' : 'rgba(56,91,62,.12)';
                if (remsActivityChart.options.plugins.legend) {
                    remsActivityChart.options.plugins.legend.labels = {
                        ...(remsActivityChart.options.plugins.legend.labels || {}),
                        color: labelColor
                    };
                }
                for (const axis of ['x', 'y']) {
                    if (!remsActivityChart.options.scales[axis]) continue;
                    remsActivityChart.options.scales[axis].ticks.color = labelColor;
                    remsActivityChart.options.scales[axis].grid.color = gridColor;
                }
                remsActivityChart.update('none');
            }
            remsSyncReportChartTheme();
            new MutationObserver(remsSyncReportChartTheme).observe(
                document.documentElement,
                {attributes:true,attributeFilter:['data-rems-theme']}
            );
        }

    }
);

</script>


<?php

require __DIR__ . '/../includes/footer.php';

?>