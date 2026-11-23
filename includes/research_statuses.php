<?php

/**
 * ASU-REMS
 * Research Workflow Statuses
 *
 * Centralized list of all valid statuses
 * used by the Research Management workflow.
 */

final class ResearchStatus
{
    // =========================================================
    // INITIAL STAGE
    // =========================================================

    public const DRAFT = 'DRAFT';

    public const SUBMITTED = 'SUBMITTED';


    // =========================================================
    // UREC / IACUC ETHICS REVIEW
    // =========================================================

    public const FOR_ETHICS_REVIEW =
        'FOR_ETHICS_REVIEW';

    public const ETHICS_COMPLETED =
        'ETHICS_COMPLETED';


    // =========================================================
    // SELF-INITIATED RESEARCH
    // =========================================================

    public const SELF_INITIATED_UPE_REQUIRED =
        'SELF_INITIATED_UPE_REQUIRED';

    public const SELF_INITIATED_UPE_SUBMITTED =
        'SELF_INITIATED_UPE_SUBMITTED';


    // =========================================================
    // URRC REVIEW
    // =========================================================

    public const FOR_URRC =
        'FOR_URRC';

    public const URRC_REVISION_REQUIRED =
        'URRC_REVISION_REQUIRED';

    public const URRC_REVISED_SUBMITTED =
        'URRC_REVISED_SUBMITTED';

    public const URRC_REJECTED =
        'URRC_REJECTED';


    // =========================================================
    // IN-HOUSE REVIEW
    // =========================================================

    public const FOR_INHOUSE =
        'FOR_INHOUSE';

    public const INHOUSE_REVISION_REQUIRED =
        'INHOUSE_REVISION_REQUIRED';

    public const INHOUSE_REVISED_SUBMITTED =
        'INHOUSE_REVISED_SUBMITTED';

    public const INHOUSE_FAILED =
        'INHOUSE_FAILED';


    // =========================================================
    // NOTICE TO PROCEED
    // =========================================================

    public const READY_FOR_NTP =
        'READY_FOR_NTP';

    public const NTP_ISSUED =
        'NTP_ISSUED';


    // =========================================================
    // COMPLETED RESEARCH
    // =========================================================

    public const COMPLETED_MANUSCRIPT_SUBMITTED =
        'COMPLETED_MANUSCRIPT_SUBMITTED';

    public const TERMINAL_REPORT_SUBMITTED =
        'TERMINAL_REPORT_SUBMITTED';


    // =========================================================
    // PUBLICATION / PRESENTATION
    // =========================================================

    public const PERMIT_ISSUED =
        'PERMIT_ISSUED';

    public const COMPLETED =
        'COMPLETED';


    // =========================================================
    // ARCHIVED
    // =========================================================

    public const ARCHIVED_NO_REVISION =
        'ARCHIVED_NO_REVISION';


    /**
     * Return all valid research statuses.
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,

            self::FOR_ETHICS_REVIEW,
            self::ETHICS_COMPLETED,

            self::SELF_INITIATED_UPE_REQUIRED,
            self::SELF_INITIATED_UPE_SUBMITTED,

            self::FOR_URRC,
            self::URRC_REVISION_REQUIRED,
            self::URRC_REVISED_SUBMITTED,
            self::URRC_REJECTED,

            self::FOR_INHOUSE,
            self::INHOUSE_REVISION_REQUIRED,
            self::INHOUSE_REVISED_SUBMITTED,
            self::INHOUSE_FAILED,

            self::READY_FOR_NTP,
            self::NTP_ISSUED,

            self::COMPLETED_MANUSCRIPT_SUBMITTED,
            self::TERMINAL_REPORT_SUBMITTED,

            self::PERMIT_ISSUED,
            self::COMPLETED,

            self::ARCHIVED_NO_REVISION
        ];
    }


    /**
     * Check if a status is valid.
     */
    public static function isValid(string $status): bool
    {
        return in_array(
            $status,
            self::all(),
            true
        );
    }


    /**
     * Check if research is already in a final status.
     */
    public static function isFinal(string $status): bool
    {
        return in_array(
            $status,
            [
                self::COMPLETED,
                self::URRC_REJECTED,
                self::INHOUSE_FAILED,
                self::ARCHIVED_NO_REVISION
            ],
            true
        );
    }
}