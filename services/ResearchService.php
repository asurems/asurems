<?php

/**
 * ============================================================
 * ASU-REMS
 * RESEARCH SERVICE
 * ============================================================
 *
 * Main backend service for the Research Workflow.
 *
 * Handles:
 * - Research creation
 * - Research retrieval
 * - Status transitions
 * - Manuscript number generation
 * - Status history
 * - UREC / IACUC review
 * - URRC review
 * - In-House review
 * - Revision rounds
 * - Archive handling
 * - Completion handling
 */


/* ============================================================
   REQUIRED FILES
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/research_statuses.php';
require_once __DIR__ . '/../includes/research_workflow.php';


class ResearchService
{
    private PDO $pdo;


    /* ========================================================
       CONSTRUCTOR
       ======================================================== */

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    /* ========================================================
       CREATE RESEARCH
       ======================================================== */

    public function createResearch(
        int $researcherId,
        string $title,
        ?string $fundingSource,
        string $submissionTrack = 'REGULAR'
    ): int {

        $title = trim($title);

        $fundingSource =
            trim((string) $fundingSource);

        $submissionTrack =
            strtoupper(trim($submissionTrack));


        if ($researcherId <= 0) {

            throw new Exception(
                'Invalid researcher ID.'
            );
        }


        if ($title === '') {

            throw new Exception(
                'Research title is required.'
            );
        }


        if (strlen($title) > 255) {

            throw new Exception(
                'Research title must not exceed 255 characters.'
            );
        }


        $allowedTracks = [
            'REGULAR',
            'SELF_INITIATED'
        ];


        if (
            !in_array(
                $submissionTrack,
                $allowedTracks,
                true
            )
        ) {

            throw new Exception(
                'Invalid research submission track.'
            );
        }


        try {

            $this->pdo->beginTransaction();


            $sql = "
                INSERT INTO research_projects
                (
                    researcher_id,
                    title,
                    funding_source,
                    submission_track,
                    current_status,
                    current_stage
                )
                VALUES
                (
                    :researcher_id,
                    :title,
                    :funding_source,
                    :submission_track,
                    :current_status,
                    :current_stage
                )
            ";


            $stmt =
                $this->pdo->prepare($sql);


            $stmt->execute([

                ':researcher_id' =>
                    $researcherId,

                ':title' =>
                    $title,

                ':funding_source' =>
                    $fundingSource !== ''
                        ? $fundingSource
                        : null,

                ':submission_track' =>
                    $submissionTrack,

                ':current_status' =>
                    ResearchStatus::DRAFT,

                ':current_stage' =>
                    'SUBMISSION'
            ]);


            $researchId =
                (int) $this->pdo->lastInsertId();


            /*
             * Save initial history.
             */

            $this->insertStatusHistory(
                $researchId,
                null,
                ResearchStatus::DRAFT,
                'CREATE_RESEARCH',
                'Research record created.',
                $researcherId
            );


            $this->pdo->commit();


            return $researchId;


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {

                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    /* ========================================================
       GET RESEARCH BY ID
       ======================================================== */

    public function getResearchById(
        int $researchId
    ): ?array {

        $sql = "
            SELECT *
            FROM research_projects
            WHERE id = :id
            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':id' => $researchId
        ]);


        $research =
            $stmt->fetch();


        return $research ?: null;
    }


    /* ========================================================
       GET RESEARCHES OF RESEARCHER
       ======================================================== */

    public function getResearchByResearcher(
        int $researcherId
    ): array {

        $sql = "
            SELECT *
            FROM research_projects
            WHERE researcher_id = :researcher_id
            ORDER BY created_at DESC
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':researcher_id' =>
                $researcherId
        ]);


        return $stmt->fetchAll();
    }


    /* ========================================================
       GET ALL ACTIVE RESEARCH
       ======================================================== */

    public function getActiveResearch(): array
    {
        $sql = "
            SELECT *
            FROM research_projects
            WHERE is_archived = 0
            ORDER BY created_at DESC
        ";


        $stmt =
            $this->pdo->query($sql);


        return $stmt->fetchAll();
    }


    /* ========================================================
       GET ARCHIVED RESEARCH
       ======================================================== */

    public function getArchivedResearch(): array
    {
        $sql = "
            SELECT *
            FROM research_projects
            WHERE is_archived = 1
            ORDER BY archived_at DESC
        ";


        $stmt =
            $this->pdo->query($sql);


        return $stmt->fetchAll();
    }


    /* ========================================================
       GET RESEARCH BY STATUS
       ======================================================== */

    public function getResearchByStatus(
        string $status
    ): array {

        $status =
            strtoupper(trim($status));


        if (!ResearchStatus::isValid($status)) {

            throw new Exception(
                'Invalid research status.'
            );
        }


        $sql = "
            SELECT *
            FROM research_projects
            WHERE
                current_status = :status
                AND is_archived = 0
            ORDER BY updated_at ASC
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':status' =>
                $status
        ]);


        return $stmt->fetchAll();
    }


    /* ========================================================
       STATUS HISTORY
       ======================================================== */

    public function getStatusHistory(
        int $researchId
    ): array {

        $sql = "
            SELECT *
            FROM research_status_history
            WHERE research_id = :research_id
            ORDER BY created_at ASC, id ASC
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':research_id' =>
                $researchId
        ]);


        return $stmt->fetchAll();
    }


    /* ========================================================
       GET REVIEWS
       ======================================================== */

    public function getReviews(
        int $researchId
    ): array {

        $sql = "
            SELECT *
            FROM research_reviews
            WHERE research_id = :research_id
            ORDER BY reviewed_at ASC, id ASC
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':research_id' =>
                $researchId
        ]);


        return $stmt->fetchAll();
    }


    /* ========================================================
       GENERAL STATUS CHANGE
       ======================================================== */

    public function changeStatus(
        int $researchId,
        string $newStatus,
        int $changedBy,
        ?string $actionCode = null,
        ?string $remarks = null
    ): array {

        $newStatus =
            strtoupper(trim($newStatus));

        $remarks =
            trim((string) $remarks);


        if ($researchId <= 0) {

            throw new Exception(
                'Invalid research ID.'
            );
        }


        if ($changedBy <= 0) {

            throw new Exception(
                'Invalid user ID.'
            );
        }


        if (
            !ResearchStatus::isValid(
                $newStatus
            )
        ) {

            throw new Exception(
                'Invalid research status.'
            );
        }


        try {

            $this->pdo->beginTransaction();


            $research =
                $this->getResearchForUpdate(
                    $researchId
                );


            if (!$research) {

                throw new Exception(
                    'Research record not found.'
                );
            }


            if (
                (int) $research['is_archived']
                === 1
            ) {

                throw new Exception(
                    'Archived research can no longer continue in the workflow.'
                );
            }


            $updatedResearch =
                $this->transitionResearch(
                    $research,
                    $newStatus,
                    $changedBy,
                    $actionCode,
                    $remarks
                );


            $this->pdo->commit();


            return $updatedResearch;


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {

                $this->pdo->rollBack();
            }


            throw $e;
        }
    }


    /* ========================================================
       SAVE UREC / IACUC RESULT
       ======================================================== */

    public function saveEthicsResult(
        int $researchId,
        string $result,
        ?string $remarks,
        int $reviewedBy
    ): array {

        $result =
            strtoupper(trim($result));

        $remarks =
            trim((string) $remarks);


        $allowedResults = [
            'EXEMPT',
            'EXPEDITED',
            'FULL_BOARD'
        ];


        if (
            !in_array(
                $result,
                $allowedResults,
                true
            )
        ) {

            throw new Exception(
                'Invalid UREC/IACUC result.'
            );
        }


        if ($reviewedBy <= 0) {

            throw new Exception(
                'Invalid reviewer ID.'
            );
        }


        try {

            $this->pdo->beginTransaction();


            $research =
                $this->getResearchForUpdate(
                    $researchId
                );


            if (!$research) {

                throw new Exception(
                    'Research record not found.'
                );
            }


            if (
                $research['current_status']
                !== ResearchStatus::FOR_ETHICS_REVIEW
            ) {

                throw new Exception(
                    'This research is not currently for UREC/IACUC review.'
                );
            }


            if (
                (int) $research['is_archived']
                === 1
            ) {

                throw new Exception(
                    'Archived research can no longer be reviewed.'
                );
            }


            /*
             * Save review result.
             */

            $this->insertReview(
                $researchId,
                'ETHICS',
                $result,
                $remarks,
                $reviewedBy
            );


            /*
             * Move to ETHICS_COMPLETED.
             */

            $updatedResearch =
                $this->transitionResearch(
                    $research,
                    ResearchStatus::ETHICS_COMPLETED,
                    $reviewedBy,
                    'SAVE_ETHICS_RESULT',
                    'UREC/IACUC result: '
                    . $result
                    . (
                        $remarks !== ''
                            ? '. ' . $remarks
                            : ''
                    )
                );


            $this->pdo->commit();


            return $updatedResearch;


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {

                $this->pdo->rollBack();
            }


            throw $e;
        }
    }


    /* ========================================================
       SAVE URRC RESULT
       ======================================================== */

    public function saveUrrcResult(
        int $researchId,
        string $result,
        ?string $remarks,
        int $reviewedBy
    ): array {

        $result =
            strtoupper(trim($result));

        $remarks =
            trim((string) $remarks);


        $allowedResults = [
            'ACCEPTED',
            'ACCEPTED_WITH_REVISION',
            'REJECTED'
        ];


        if (
            !in_array(
                $result,
                $allowedResults,
                true
            )
        ) {

            throw new Exception(
                'Invalid URRC result.'
            );
        }


        if ($reviewedBy <= 0) {

            throw new Exception(
                'Invalid reviewer ID.'
            );
        }


        try {

            $this->pdo->beginTransaction();


            $research =
                $this->getResearchForUpdate(
                    $researchId
                );


            if (!$research) {

                throw new Exception(
                    'Research record not found.'
                );
            }


            if (
                (int) $research['is_archived']
                === 1
            ) {

                throw new Exception(
                    'Archived research can no longer be reviewed.'
                );
            }


            /*
             * URRC may review:
             *
             * 1. Initial submission
             * 2. Revised manuscript
             */

            $allowedCurrentStatuses = [

                ResearchStatus::FOR_URRC,

                ResearchStatus::URRC_REVISED_SUBMITTED
            ];


            if (
                !in_array(
                    $research['current_status'],
                    $allowedCurrentStatuses,
                    true
                )
            ) {

                throw new Exception(
                    'This research is not currently ready for URRC review.'
                );
            }


            /*
             * Determine next status.
             */

            switch ($result) {

                case 'ACCEPTED':

                    $nextStatus =
                        ResearchStatus::FOR_INHOUSE;

                    break;


                case 'ACCEPTED_WITH_REVISION':

                    $nextStatus =
                        ResearchStatus::URRC_REVISION_REQUIRED;

                    break;


                case 'REJECTED':

                    $nextStatus =
                        ResearchStatus::URRC_REJECTED;

                    break;


                default:

                    throw new Exception(
                        'Invalid URRC result.'
                    );
            }


            /*
             * Save review.
             */

            $this->insertReview(
                $researchId,
                'URRC',
                $result,
                $remarks,
                $reviewedBy
            );


            /*
             * Update status.
             */

            $updatedResearch =
                $this->transitionResearch(
                    $research,
                    $nextStatus,
                    $reviewedBy,
                    'SAVE_URRC_RESULT',
                    'URRC result: '
                    . $result
                    . (
                        $remarks !== ''
                            ? '. ' . $remarks
                            : ''
                    )
                );


            $this->pdo->commit();


            return $updatedResearch;


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {

                $this->pdo->rollBack();
            }


            throw $e;
        }
    }


    /* ========================================================
       SAVE IN-HOUSE RESULT
       ======================================================== */

    public function saveInhouseResult(
        int $researchId,
        string $result,
        ?string $remarks,
        int $reviewedBy
    ): array {

        $result =
            strtoupper(trim($result));

        $remarks =
            trim((string) $remarks);


        $allowedResults = [
            'PASSED',
            'PASSED_WITH_REVISION',
            'FAILED'
        ];


        if (
            !in_array(
                $result,
                $allowedResults,
                true
            )
        ) {

            throw new Exception(
                'Invalid In-House result.'
            );
        }


        if ($reviewedBy <= 0) {

            throw new Exception(
                'Invalid reviewer ID.'
            );
        }


        try {

            $this->pdo->beginTransaction();


            $research =
                $this->getResearchForUpdate(
                    $researchId
                );


            if (!$research) {

                throw new Exception(
                    'Research record not found.'
                );
            }


            if (
                (int) $research['is_archived']
                === 1
            ) {

                throw new Exception(
                    'Archived research can no longer be reviewed.'
                );
            }


            /*
             * In-House may review:
             *
             * 1. Initial manuscript
             * 2. Revised manuscript
             */

            $allowedCurrentStatuses = [

                ResearchStatus::FOR_INHOUSE,

                ResearchStatus::INHOUSE_REVISED_SUBMITTED
            ];


            if (
                !in_array(
                    $research['current_status'],
                    $allowedCurrentStatuses,
                    true
                )
            ) {

                throw new Exception(
                    'This research is not currently ready for In-House review.'
                );
            }


            /*
             * Determine next status.
             */

            switch ($result) {

                case 'PASSED':

                    $nextStatus =
                        ResearchStatus::READY_FOR_NTP;

                    break;


                case 'PASSED_WITH_REVISION':

                    $nextStatus =
                        ResearchStatus::INHOUSE_REVISION_REQUIRED;

                    break;


                case 'FAILED':

                    $nextStatus =
                        ResearchStatus::INHOUSE_FAILED;

                    break;


                default:

                    throw new Exception(
                        'Invalid In-House result.'
                    );
            }


            /*
             * Save review result.
             */

            $this->insertReview(
                $researchId,
                'INHOUSE',
                $result,
                $remarks,
                $reviewedBy
            );


            /*
             * Update status.
             */

            $updatedResearch =
                $this->transitionResearch(
                    $research,
                    $nextStatus,
                    $reviewedBy,
                    'SAVE_INHOUSE_RESULT',
                    'In-House result: '
                    . $result
                    . (
                        $remarks !== ''
                            ? '. ' . $remarks
                            : ''
                    )
                );


            $this->pdo->commit();


            return $updatedResearch;


        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {

                $this->pdo->rollBack();
            }


            throw $e;
        }
    }


    /* ========================================================
       INTERNAL STATUS TRANSITION
       ======================================================== */

    private function transitionResearch(
        array $research,
        string $newStatus,
        int $changedBy,
        ?string $actionCode,
        ?string $remarks
    ): array {

        $researchId =
            (int) $research['id'];


        $currentStatus =
            (string) $research['current_status'];


        $submissionTrack =
            (string) $research['submission_track'];


        /*
         * Validate status.
         */

        if (
            !ResearchStatus::isValid(
                $newStatus
            )
        ) {

            throw new Exception(
                'Invalid research status.'
            );
        }


        /*
         * Check workflow transition.
         */

        if (
            !ResearchWorkflow::canTransition(
                $currentStatus,
                $newStatus
            )
        ) {

            throw new Exception(
                'Invalid research workflow transition: '
                . $currentStatus
                . ' → '
                . $newStatus
            );
        }


        /* ====================================================
           REGULAR / SELF-INITIATED ROUTING
           ==================================================== */

        if (
            $currentStatus
            === ResearchStatus::ETHICS_COMPLETED
        ) {

            /*
             * Regular research:
             *
             * ETHICS_COMPLETED
             * ↓
             * FOR_URRC
             */

            if (
                $submissionTrack === 'REGULAR'
                &&
                $newStatus
                !== ResearchStatus::FOR_URRC
            ) {

                throw new Exception(
                    'Regular research must proceed to URRC review.'
                );
            }


            /*
             * Self-Initiated:
             *
             * ETHICS_COMPLETED
             * ↓
             * UPE REQUIRED
             */

            if (
                $submissionTrack === 'SELF_INITIATED'
                &&
                $newStatus
                !== ResearchStatus::SELF_INITIATED_UPE_REQUIRED
            ) {

                throw new Exception(
                    'Self-initiated research must proceed to UPE certificate submission.'
                );
            }
        }


        /*
         * Self-initiated research should not
         * enter the regular URRC workflow.
         */

        if (
            $submissionTrack === 'SELF_INITIATED'
            &&
            $newStatus === ResearchStatus::FOR_URRC
        ) {

            throw new Exception(
                'Self-initiated research cannot enter the regular URRC workflow.'
            );
        }


        /* ====================================================
           MANUSCRIPT NUMBER
           ==================================================== */

        $manuscriptNo =
            $research['manuscript_no'];


        /*
         * Generate manuscript number only
         * when DRAFT becomes SUBMITTED.
         */

        if (
            $currentStatus === ResearchStatus::DRAFT
            &&
            $newStatus === ResearchStatus::SUBMITTED
            &&
            empty($manuscriptNo)
        ) {

            $manuscriptNo =
                $this->generateManuscriptNumber();
        }


        /* ====================================================
           CURRENT STAGE
           ==================================================== */

        $currentStage =
            $this->stageForStatus(
                $newStatus
            );


        /* ====================================================
           SUBMISSION DATE
           ==================================================== */

        $submittedAt =
            $research['submitted_at'];


        if (
            $newStatus === ResearchStatus::SUBMITTED
            &&
            empty($submittedAt)
        ) {

            $submittedAt =
                date('Y-m-d H:i:s');
        }


        /* ====================================================
           ARCHIVE
           ==================================================== */

        $archiveStatuses = [

            ResearchStatus::URRC_REJECTED,

            ResearchStatus::INHOUSE_FAILED,

            ResearchStatus::ARCHIVED_NO_REVISION
        ];


        $isArchived =
            (int) $research['is_archived'];


        $archiveReason =
            $research['archive_reason'];


        $archivedAt =
            $research['archived_at'];


        if (
            in_array(
                $newStatus,
                $archiveStatuses,
                true
            )
        ) {

            $isArchived = 1;


            $archiveReason =
                !empty($remarks)
                    ? $remarks
                    : $this->defaultArchiveReason(
                        $newStatus
                    );


            $archivedAt =
                date('Y-m-d H:i:s');
        }


        /* ====================================================
           COMPLETION DATE
           ==================================================== */

        $completedAt =
            $research['completed_at'];


        if (
            $newStatus === ResearchStatus::COMPLETED
            &&
            empty($completedAt)
        ) {

            $completedAt =
                date('Y-m-d H:i:s');
        }


        /* ====================================================
           UPDATE RESEARCH PROJECT
           ==================================================== */

        $sql = "
            UPDATE research_projects

            SET
                manuscript_no =
                    :manuscript_no,

                current_status =
                    :current_status,

                current_stage =
                    :current_stage,

                is_archived =
                    :is_archived,

                archive_reason =
                    :archive_reason,

                archived_at =
                    :archived_at,

                submitted_at =
                    :submitted_at,

                completed_at =
                    :completed_at

            WHERE id = :id
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            ':manuscript_no' =>
                $manuscriptNo,

            ':current_status' =>
                $newStatus,

            ':current_stage' =>
                $currentStage,

            ':is_archived' =>
                $isArchived,

            ':archive_reason' =>
                $archiveReason,

            ':archived_at' =>
                $archivedAt,

            ':submitted_at' =>
                $submittedAt,

            ':completed_at' =>
                $completedAt,

            ':id' =>
                $researchId
        ]);


        /* ====================================================
           STATUS HISTORY
           ==================================================== */

        $this->insertStatusHistory(
            $researchId,
            $currentStatus,
            $newStatus,
            $actionCode,
            $remarks,
            $changedBy
        );


        /*
         * Update returned array.
         */

        $research['manuscript_no'] =
            $manuscriptNo;

        $research['current_status'] =
            $newStatus;

        $research['current_stage'] =
            $currentStage;

        $research['is_archived'] =
            $isArchived;

        $research['archive_reason'] =
            $archiveReason;

        $research['archived_at'] =
            $archivedAt;

        $research['submitted_at'] =
            $submittedAt;

        $research['completed_at'] =
            $completedAt;


        return $research;
    }


    /* ========================================================
       MANUSCRIPT NUMBER GENERATOR
       ======================================================== */

    private function generateManuscriptNumber(): string
    {
        $year =
            (int) date('Y');


        /*
         * Make sure sequence row exists.
         */

        $sql = "
            INSERT INTO manuscript_sequences
            (
                sequence_year,
                last_number
            )

            VALUES
            (
                :sequence_year,
                0
            )

            ON DUPLICATE KEY UPDATE

                sequence_year =
                    sequence_year
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':sequence_year' =>
                $year
        ]);


        /*
         * Lock the sequence row.
         */

        $sql = "
            SELECT last_number

            FROM manuscript_sequences

            WHERE sequence_year =
                :sequence_year

            FOR UPDATE
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':sequence_year' =>
                $year
        ]);


        $row =
            $stmt->fetch();


        if (!$row) {

            throw new Exception(
                'Unable to generate manuscript number.'
            );
        }


        $nextNumber =
            ((int) $row['last_number']) + 1;


        /*
         * Save next number.
         */

        $sql = "
            UPDATE manuscript_sequences

            SET last_number =
                :last_number

            WHERE sequence_year =
                :sequence_year
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            ':last_number' =>
                $nextNumber,

            ':sequence_year' =>
                $year
        ]);


        /*
         * Example:
         *
         * ASU-RES-2026-0001
         */

        return sprintf(
            'ASU-RES-%d-%04d',
            $year,
            $nextNumber
        );
    }


    /* ========================================================
       INSERT STATUS HISTORY
       ======================================================== */

    private function insertStatusHistory(
        int $researchId,
        ?string $fromStatus,
        string $toStatus,
        ?string $actionCode,
        ?string $remarks,
        ?int $changedBy
    ): void {

        $sql = "
            INSERT INTO research_status_history
            (
                research_id,
                from_status,
                to_status,
                action_code,
                remarks,
                changed_by
            )

            VALUES
            (
                :research_id,
                :from_status,
                :to_status,
                :action_code,
                :remarks,
                :changed_by
            )
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            ':research_id' =>
                $researchId,

            ':from_status' =>
                $fromStatus,

            ':to_status' =>
                $toStatus,

            ':action_code' =>
                !empty($actionCode)
                    ? $actionCode
                    : null,

            ':remarks' =>
                !empty($remarks)
                    ? $remarks
                    : null,

            ':changed_by' =>
                $changedBy
        ]);
    }


    /* ========================================================
       INSERT REVIEW
       ======================================================== */

    private function insertReview(
        int $researchId,
        string $reviewType,
        string $result,
        ?string $remarks,
        int $reviewedBy
    ): void {

        /*
         * Automatically determine review round.
         */

        $round =
            $this->getNextReviewRound(
                $researchId,
                $reviewType
            );


        $sql = "
            INSERT INTO research_reviews
            (
                research_id,
                review_type,
                review_round,
                result,
                remarks,
                reviewed_by
            )

            VALUES
            (
                :research_id,
                :review_type,
                :review_round,
                :result,
                :remarks,
                :reviewed_by
            )
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            ':research_id' =>
                $researchId,

            ':review_type' =>
                $reviewType,

            ':review_round' =>
                $round,

            ':result' =>
                $result,

            ':remarks' =>
                !empty($remarks)
                    ? $remarks
                    : null,

            ':reviewed_by' =>
                $reviewedBy
        ]);
    }


    /* ========================================================
       NEXT REVIEW ROUND
       ======================================================== */

    private function getNextReviewRound(
        int $researchId,
        string $reviewType
    ): int {

        $sql = "
            SELECT

                COALESCE(
                    MAX(review_round),
                    0
                ) AS last_round

            FROM research_reviews

            WHERE
                research_id =
                    :research_id

                AND review_type =
                    :review_type
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            ':research_id' =>
                $researchId,

            ':review_type' =>
                $reviewType
        ]);


        $row =
            $stmt->fetch();


        return
            ((int) $row['last_round']) + 1;
    }


    /* ========================================================
       GET RESEARCH WITH LOCK
       ======================================================== */

    private function getResearchForUpdate(
        int $researchId
    ): ?array {

        $sql = "
            SELECT *

            FROM research_projects

            WHERE id = :id

            LIMIT 1

            FOR UPDATE
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            ':id' =>
                $researchId
        ]);


        $research =
            $stmt->fetch();


        return $research ?: null;
    }


    /* ========================================================
       STATUS TO STAGE
       ======================================================== */

    private function stageForStatus(
        string $status
    ): string {

        $map = [

            /* Submission */

            ResearchStatus::DRAFT =>
                'SUBMISSION',

            ResearchStatus::SUBMITTED =>
                'SUBMISSION',


            /* Ethics */

            ResearchStatus::FOR_ETHICS_REVIEW =>
                'ETHICS',

            ResearchStatus::ETHICS_COMPLETED =>
                'ETHICS',


            /* UPE */

            ResearchStatus::SELF_INITIATED_UPE_REQUIRED =>
                'UPE',

            ResearchStatus::SELF_INITIATED_UPE_SUBMITTED =>
                'UPE',


            /* URRC */

            ResearchStatus::FOR_URRC =>
                'URRC',

            ResearchStatus::URRC_REVISION_REQUIRED =>
                'URRC',

            ResearchStatus::URRC_REVISED_SUBMITTED =>
                'URRC',

            ResearchStatus::URRC_REJECTED =>
                'ARCHIVED',


            /* In-House */

            ResearchStatus::FOR_INHOUSE =>
                'INHOUSE',

            ResearchStatus::INHOUSE_REVISION_REQUIRED =>
                'INHOUSE',

            ResearchStatus::INHOUSE_REVISED_SUBMITTED =>
                'INHOUSE',

            ResearchStatus::INHOUSE_FAILED =>
                'ARCHIVED',


            /* Notice to Proceed */

            ResearchStatus::READY_FOR_NTP =>
                'NOTICE_TO_PROCEED',

            ResearchStatus::NTP_ISSUED =>
                'IMPLEMENTATION',


            /* Completed Research */

            ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED =>
                'COMPLETION',

            ResearchStatus::TERMINAL_REPORT_SUBMITTED =>
                'TERMINAL_REPORT',


            /* Publication */

            ResearchStatus::PERMIT_ISSUED =>
                'PUBLICATION_PRESENTATION',

            ResearchStatus::COMPLETED =>
                'COMPLETED',


            /* Archive */

            ResearchStatus::ARCHIVED_NO_REVISION =>
                'ARCHIVED'
        ];


        return
            $map[$status] ?? 'UNKNOWN';
    }


    /* ========================================================
       DEFAULT ARCHIVE REASON
       ======================================================== */

    private function defaultArchiveReason(
        string $status
    ): string {

        switch ($status) {

            case ResearchStatus::URRC_REJECTED:

                return
                    'Research rejected during URRC review.';


            case ResearchStatus::INHOUSE_FAILED:

                return
                    'Research failed during In-House review.';


            case ResearchStatus::ARCHIVED_NO_REVISION:

                return
                    'Required revision was not submitted.';


            default:

                return
                    'Research archived.';
        }
    }
}


/* ============================================================
   READY-TO-USE INSTANCE
   ============================================================ */

/*
 * config/database.php already creates:
 *
 * $pdo
 *
 * Therefore every action file can simply use:
 *
 * $researchService
 */

$researchService =
    new ResearchService($pdo);