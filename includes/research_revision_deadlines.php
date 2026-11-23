<?php
/**
 * ASU-REMS: URRC Accepted-with-Revision 21-day deadline.
 *
 * Original records and document links remain in the existing tables.
 * Expiration changes only the research record's workflow status, writes a
 * status-history audit row and, when available, sends a notification.
 *
 * The deadline is counted from the LAST recorded URRC Accepted with Revision
 * decision. Merely editing the proposal does not reset the deadline.
 * All expiry comparisons are made using MySQL's current time, consistently
 * with the MySQL decision timestamps.
 */

/*
 * SHORT-DEADLINE TEST SETTINGS
 * Set REMS_URRC_TEST_RESEARCH_ID to the ID of ONE NEW TEST proposal.
 * A value of 0 DISABLES short-deadline testing for every research record.
 * All other proposals keep their original 21-day deadline.
 * Never configure this for a real faculty proposal.
 */
const REMS_URRC_TEST_RESEARCH_ID = 0; // TEST mode disabled; standard 21-day deadline for all records
const REMS_URRC_TEST_MINUTES = 2;
const REMS_URRC_STANDARD_MINUTES = 21 * 24 * 60;

if (!function_exists('remsUrrcRevisionIsTestRecord')) {
    function remsUrrcRevisionIsTestRecord(int $researchId): bool
    {
        return REMS_URRC_TEST_RESEARCH_ID > 0 && $researchId === REMS_URRC_TEST_RESEARCH_ID;
    }
}

if (!function_exists('remsUrrcRevisionIntervalSql')) {
    function remsUrrcRevisionIntervalSql(): string
    {
        // Integer constants only; expression used by BOTH the display and auto-archiver.
        if (REMS_URRC_TEST_RESEARCH_ID <= 0) {
            return (string) REMS_URRC_STANDARD_MINUTES;
        }
        return 'IF(rp.research_id = ' . (int) REMS_URRC_TEST_RESEARCH_ID
            . ', ' . (int) REMS_URRC_TEST_MINUTES
            . ', ' . (int) REMS_URRC_STANDARD_MINUTES . ')';
    }
}

if (!function_exists('remsUrrcRevisionPeriodLabel')) {
    function remsUrrcRevisionPeriodLabel(int $researchId): string
    {
        return remsUrrcRevisionIsTestRecord($researchId)
            ? REMS_URRC_TEST_MINUTES . '-Minute TEST Deadline'
            : '21-Day Deadline';
    }
}

if (!function_exists('remsUrrcRevisionPeriodDescription')) {
    function remsUrrcRevisionPeriodDescription(int $researchId): string
    {
        return remsUrrcRevisionIsTestRecord($researchId)
            ? REMS_URRC_TEST_MINUTES . ' minutes (TEST ONLY)'
            : '21 days';
    }
}

if (!function_exists('remsIsNewUrrcPath')) {
    function remsIsNewUrrcPath(PDO $pdo, int $researchId): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM research_status_history
            WHERE research_id = ?
              AND remarks = 'Faculty selected the For URRC pathway after proposal submission.'
            LIMIT 1");
        $stmt->execute([$researchId]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('remsUrrcRevisionDeadline')) {
    function remsUrrcRevisionDeadline(PDO $pdo, int $researchId): ?array
    {
        $intervalMinutes = remsUrrcRevisionIntervalSql();
        $stmt = $pdo->prepare("
            SELECT
                rp.research_id,
                rp.status,
                rp.workflow_status,
                rp.is_archived,
                COALESCE(
                    (SELECT MAX(h.changed_at)
                     FROM research_status_history h
                     WHERE h.research_id = rp.research_id
                       AND h.remarks LIKE 'URRC result recorded: Accepted with Revision.%'),
                    rp.workflow_updated_at
                ) AS decision_at,
                DATE_ADD(
                    COALESCE(
                        (SELECT MAX(h.changed_at)
                         FROM research_status_history h
                         WHERE h.research_id = rp.research_id
                           AND h.remarks LIKE 'URRC result recorded: Accepted with Revision.%'),
                        rp.workflow_updated_at
                    ), INTERVAL {$intervalMinutes} MINUTE
                ) AS deadline_at,
                CASE WHEN DATE_ADD(
                    COALESCE(
                        (SELECT MAX(h.changed_at)
                         FROM research_status_history h
                         WHERE h.research_id = rp.research_id
                           AND h.remarks LIKE 'URRC result recorded: Accepted with Revision.%'),
                        rp.workflow_updated_at
                    ), INTERVAL {$intervalMinutes} MINUTE
                ) < NOW() THEN 1 ELSE 0 END AS expired
            FROM research_proposals rp
            WHERE rp.research_id = ?
            LIMIT 1
        ");
        $stmt->execute([$researchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['deadline_at'])) {
            return null;
        }
        $row['period_label'] = remsUrrcRevisionPeriodLabel($researchId);
        $row['period_description'] = remsUrrcRevisionPeriodDescription($researchId);
        $row['is_test_deadline'] = remsUrrcRevisionIsTestRecord($researchId);
        return $row;
    }
}

if (!function_exists('remsArchiveExpiredUrrcRevisions')) {
    /**
     * Archive URRC revisions only after their 21-day window has elapsed.
     * Optionally restrict processing to a single research_id.
     * Safe to run from page loads and independently from a scheduled CLI task.
     */
    function remsArchiveExpiredUrrcRevisions(PDO $pdo, ?int $onlyResearchId = null): int
    {
        $intervalMinutes = remsUrrcRevisionIntervalSql();
        $where = "rp.workflow_status = 'URRC_REVISION_REQUIRED'
               AND rp.is_archived = 0
               AND EXISTS (
                   SELECT 1 FROM research_status_history marker
                   WHERE marker.research_id = rp.research_id
                     AND marker.remarks = 'Faculty selected the For URRC pathway after proposal submission.'
               )
               AND DATE_ADD(
                 COALESCE(
                   (SELECT MAX(h.changed_at)
                    FROM research_status_history h
                    WHERE h.research_id = rp.research_id
                      AND h.remarks LIKE 'URRC result recorded: Accepted with Revision.%'),
                   rp.workflow_updated_at
                 ), INTERVAL {$intervalMinutes} MINUTE
               ) < NOW()";
        $params = [];
        if ($onlyResearchId !== null) {
            $where .= ' AND rp.research_id = ?';
            $params[] = $onlyResearchId;
        }
        $candidates = $pdo->prepare(
            "SELECT rp.research_id FROM research_proposals rp WHERE {$where} ORDER BY rp.research_id ASC"
        );
        $candidates->execute($params);
        $ids = array_map('intval', $candidates->fetchAll(PDO::FETCH_COLUMN));
        $archivedCount = 0;

        foreach ($ids as $id) {
            try {
                $pdo->beginTransaction();
                // Serializes expiry with edits/resubmissions of this record.
                $locked = $pdo->prepare("
                    SELECT research_id, researcher_id, research_title, status,
                           workflow_status, is_archived
                    FROM research_proposals
                    WHERE research_id = ? FOR UPDATE
                ");
                $locked->execute([$id]);
                $record = $locked->fetch(PDO::FETCH_ASSOC);
                if (!$record || $record['workflow_status'] !== 'URRC_REVISION_REQUIRED'
                    || (int)$record['is_archived'] !== 0
                    || !remsIsNewUrrcPath($pdo, $id)) {
                    $pdo->rollBack();
                    continue;
                }

                // Recheck against database time AFTER obtaining the row lock.
                $deadline = remsUrrcRevisionDeadline($pdo, $id);
                if (!$deadline || !(int)$deadline['expired']) {
                    $pdo->rollBack();
                    continue;
                }

                $periodLabel = remsUrrcRevisionPeriodLabel($id);
                $periodDescription = remsUrrcRevisionPeriodDescription($id);
                $reason = 'Did Not Revise: ' . $periodLabel
                    . ' elapsed without a resubmitted URRC revision.';
                $update = $pdo->prepare("
                    UPDATE research_proposals SET
                        status = 'Archived',
                        workflow_status = 'URRC_DID_NOT_REVISE',
                        workflow_stage = 'ARCHIVED',
                        is_archived = 1,
                        archived_reason = ?,
                        archived_at = NOW(),
                        workflow_updated_at = NOW()
                    WHERE research_id = ?
                      AND workflow_status = 'URRC_REVISION_REQUIRED'
                      AND is_archived = 0
                ");
                $update->execute([$reason, $id]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Concurrent revision prevented deadline archive.');
                }
                $audit = $pdo->prepare("
                    INSERT INTO research_status_history
                        (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, ?, 'Archived', ?)
                ");
                // Automated action; the researcher is associated for required non-null changed_by.
                $audit->execute([
                    $id,
                    (int)$record['researcher_id'],
                    (string)$record['status'],
                    'System automatically archived this research after its ' . $periodLabel
                        . ' (Did Not Revise). Deadline: ' . $deadline['deadline_at'] . '.'
                ]);
                $pdo->commit();
                $archivedCount++;

                // Notification failures should never roll back the saved archive.
                try {
                    if (function_exists('createNotification')) {
                        createNotification(
                            $pdo,
                            (int)$record['researcher_id'],
                            'URRC revision deadline expired',
                            'Your research "' . $record['research_title']
                                . '" was archived because the URRC revision was not resubmitted within '
                                . $periodDescription . '. You may create a new proposal.',
                            'research',
                            $id,
                            BASE_URL . '/research/view.php?id=' . $id
                        );
                    }
                } catch (Throwable $notifyError) {
                    error_log('URRC revision archive notification error: ' . $notifyError->getMessage());
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('URRC 21-day automatic archive error for research #' . $id . ': ' . $e->getMessage());
            }
        }
        return $archivedCount;
    }
}


/*
 * IN-HOUSE PASSED WITH REVISION: 180 calendar days.
 * This does not modify the existing URRC 21-day deadline or test settings.
 * Date/time is based on the saved In-House decision in research_status_history.
 */
if (!function_exists('remsInhouseRevisionDeadline')) {
    function remsInhouseRevisionDeadline(PDO $pdo, int $researchId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                rp.research_id,
                rp.status,
                rp.workflow_status,
                rp.is_archived,
                (SELECT MAX(h.changed_at)
                 FROM research_status_history h
                 WHERE h.research_id = rp.research_id
                   AND h.remarks LIKE 'In-House result recorded: Passed with Revision.%') AS decision_at
            FROM research_proposals rp
            WHERE rp.research_id = ?
            LIMIT 1
        ");
        $stmt->execute([$researchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['decision_at'])) {
            return null;
        }
        $deadlineStmt = $pdo->prepare("
            SELECT
                DATE_ADD(?, INTERVAL 180 DAY) AS deadline_at,
                CASE WHEN DATE_ADD(?, INTERVAL 180 DAY) < NOW()
                     THEN 1 ELSE 0 END AS expired
        ");
        $deadlineStmt->execute([$row['decision_at'], $row['decision_at']]);
        $period = $deadlineStmt->fetch(PDO::FETCH_ASSOC);
        if (!$period) {
            return null;
        }
        return array_merge($row, $period, [
            'period_label' => '180-Day Deadline',
            'period_description' => '180 days',
        ]);
    }
}

if (!function_exists('remsArchiveExpiredInhouseRevisions')) {
    /** Archive only overdue In-House revision requests, preserving files and audit history. */
    function remsArchiveExpiredInhouseRevisions(PDO $pdo, ?int $onlyResearchId = null): int
    {
        $where = "rp.workflow_status = 'INHOUSE_REVISION_REQUIRED'
            AND rp.status = 'For Revision'
            AND rp.is_archived = 0
            AND EXISTS (
                SELECT 1 FROM research_status_history h
                WHERE h.research_id = rp.research_id
                  AND h.remarks LIKE 'In-House result recorded: Passed with Revision.%'
                GROUP BY h.research_id
                HAVING DATE_ADD(MAX(h.changed_at), INTERVAL 180 DAY) < NOW()
            )";
        $params = [];
        if ($onlyResearchId !== null) {
            $where .= ' AND rp.research_id = ?';
            $params[] = $onlyResearchId;
        }
        $candidateStmt = $pdo->prepare(
            "SELECT rp.research_id FROM research_proposals rp WHERE {$where} ORDER BY rp.research_id ASC"
        );
        $candidateStmt->execute($params);
        $ids = array_map('intval', $candidateStmt->fetchAll(PDO::FETCH_COLUMN));
        $archivedCount = 0;

        foreach ($ids as $id) {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare("
                    SELECT research_id, researcher_id, research_title, status,
                           workflow_status, is_archived
                    FROM research_proposals
                    WHERE research_id = ? FOR UPDATE
                ");
                $lock->execute([$id]);
                $record = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$record || $record['workflow_status'] !== 'INHOUSE_REVISION_REQUIRED'
                    || $record['status'] !== 'For Revision'
                    || (int)$record['is_archived'] !== 0) {
                    $pdo->rollBack();
                    continue;
                }

                // Recheck the exact last In-House decision while the record is locked.
                $deadline = remsInhouseRevisionDeadline($pdo, $id);
                if (!$deadline || (int)$deadline['expired'] !== 1) {
                    $pdo->rollBack();
                    continue;
                }

                $reason = 'Did Not Revise: 180-day In-House revision deadline elapsed.';
                $update = $pdo->prepare("
                    UPDATE research_proposals SET
                        status = 'Archived',
                        workflow_status = 'INHOUSE_DID_NOT_REVISE',
                        workflow_stage = 'ARCHIVED',
                        is_archived = 1,
                        archived_reason = ?,
                        archived_at = NOW(),
                        workflow_updated_at = NOW()
                    WHERE research_id = ?
                      AND workflow_status = 'INHOUSE_REVISION_REQUIRED'
                      AND status = 'For Revision'
                      AND is_archived = 0
                ");
                $update->execute([$reason, $id]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Concurrent In-House revision prevented archive.');
                }
                $audit = $pdo->prepare("
                    INSERT INTO research_status_history
                        (research_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, ?, ?, 'Archived', ?)
                ");
                // The history table requires an associated user ID. Remarks
                // explicitly identify this as a system-triggered action.
                $audit->execute([
                    $id,
                    (int)$record['researcher_id'],
                    (string)$record['status'],
                    'System automatically archived this research after the 180-day '
                        . 'In-House revision deadline. Decision deadline: '
                        . $deadline['deadline_at'] . '.'
                ]);
                $pdo->commit();
                $archivedCount++;

                try {
                    if (function_exists('createNotification')) {
                        createNotification(
                            $pdo,
                            (int)$record['researcher_id'],
                            'In-House revision deadline expired',
                            'Your research "' . $record['research_title']
                                . '" was archived because the In-House revision was not '
                                . 'resubmitted or switched to Self-Initiated within 180 days.',
                            'research',
                            $id,
                            BASE_URL . '/research/view.php?id=' . $id
                        );
                    }
                } catch (Throwable $notifyError) {
                    error_log('In-House archive notification error: ' . $notifyError->getMessage());
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('In-House 180-day archive error for #' . $id . ': ' . $e->getMessage());
            }
        }
        return $archivedCount;
    }
}
