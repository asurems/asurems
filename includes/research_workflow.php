<?php

/**
 * ASU-REMS
 * Research Workflow Rules
 *
 * This file controls which research status
 * can move to another status.
 */

require_once __DIR__ . '/research_statuses.php';


final class ResearchWorkflow
{
    /**
     * Allowed status transitions.
     *
     * Example:
     *
     * DRAFT
     *   ↓
     * SUBMITTED
     *
     * A research cannot skip directly
     * from DRAFT to COMPLETED.
     */
    private static array $transitions = [

        // =====================================================
        // INITIAL SUBMISSION
        // =====================================================

        ResearchStatus::DRAFT => [
            ResearchStatus::SUBMITTED
        ],


        // =====================================================
        // UREC / IACUC
        // =====================================================

        ResearchStatus::SUBMITTED => [
            ResearchStatus::FOR_ETHICS_REVIEW
        ],

        ResearchStatus::FOR_ETHICS_REVIEW => [
            ResearchStatus::ETHICS_COMPLETED
        ],


        // =====================================================
        // AFTER ETHICS REVIEW
        // =====================================================

        ResearchStatus::ETHICS_COMPLETED => [

            // Regular Research
            ResearchStatus::FOR_URRC,

            // Self-Initiated Research
            ResearchStatus::SELF_INITIATED_UPE_REQUIRED
        ],


        // =====================================================
        // SELF-INITIATED RESEARCH
        // =====================================================

        ResearchStatus::SELF_INITIATED_UPE_REQUIRED => [
            ResearchStatus::SELF_INITIATED_UPE_SUBMITTED
        ],

        ResearchStatus::SELF_INITIATED_UPE_SUBMITTED => [
            ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED
        ],


        // =====================================================
        // URRC REVIEW
        // =====================================================

        ResearchStatus::FOR_URRC => [

            // Accepted
            ResearchStatus::FOR_INHOUSE,

            // Accepted with Revision
            ResearchStatus::URRC_REVISION_REQUIRED,

            // Rejected
            ResearchStatus::URRC_REJECTED
        ],


        // =====================================================
        // URRC REVISION
        // =====================================================

        ResearchStatus::URRC_REVISION_REQUIRED => [

            // Researcher submitted revision
            ResearchStatus::URRC_REVISED_SUBMITTED,

            // Researcher did not revise
            ResearchStatus::ARCHIVED_NO_REVISION
        ],

        ResearchStatus::URRC_REVISED_SUBMITTED => [

            // Proceed to In-House
            ResearchStatus::FOR_INHOUSE,

            // Another revision may be requested
            ResearchStatus::URRC_REVISION_REQUIRED
        ],


        // =====================================================
        // IN-HOUSE REVIEW
        // =====================================================

        ResearchStatus::FOR_INHOUSE => [

            // Passed
            ResearchStatus::READY_FOR_NTP,

            // Passed with Revision
            ResearchStatus::INHOUSE_REVISION_REQUIRED,

            // Failed
            ResearchStatus::INHOUSE_FAILED
        ],


        // =====================================================
        // IN-HOUSE REVISION
        // =====================================================

        ResearchStatus::INHOUSE_REVISION_REQUIRED => [

            // Revised manuscript submitted
            ResearchStatus::INHOUSE_REVISED_SUBMITTED,

            // Researcher did not revise
            ResearchStatus::ARCHIVED_NO_REVISION
        ],

        ResearchStatus::INHOUSE_REVISED_SUBMITTED => [

            // Revision accepted
            ResearchStatus::READY_FOR_NTP,

            // Another revision required
            ResearchStatus::INHOUSE_REVISION_REQUIRED
        ],


        // =====================================================
        // NOTICE TO PROCEED
        // =====================================================

        ResearchStatus::READY_FOR_NTP => [
            ResearchStatus::NTP_ISSUED
        ],


        // =====================================================
        // RESEARCH IMPLEMENTATION / COMPLETION
        // =====================================================

        ResearchStatus::NTP_ISSUED => [
            ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED
        ],

        ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED => [
            ResearchStatus::TERMINAL_REPORT_SUBMITTED
        ],


        // =====================================================
        // PERMIT TO PUBLISH / PRESENT
        // =====================================================

        ResearchStatus::TERMINAL_REPORT_SUBMITTED => [
            ResearchStatus::PERMIT_ISSUED
        ],


        // =====================================================
        // PROOF OF PUBLICATION / PRESENTATION
        // =====================================================

        ResearchStatus::PERMIT_ISSUED => [
            ResearchStatus::COMPLETED
        ]

    ];


    /**
     * Check if a transition is allowed.
     *
     * Example:
     *
     * ResearchWorkflow::canTransition(
     *     'DRAFT',
     *     'SUBMITTED'
     * );
     *
     * Returns:
     * true
     */
    public static function canTransition(
        string $currentStatus,
        string $newStatus
    ): bool {

        // Validate both statuses first
        if (
            !ResearchStatus::isValid($currentStatus) ||
            !ResearchStatus::isValid($newStatus)
        ) {
            return false;
        }


        // Final statuses cannot continue
        if (ResearchStatus::isFinal($currentStatus)) {
            return false;
        }


        // Check if current status exists
        if (!isset(self::$transitions[$currentStatus])) {
            return false;
        }


        // Check if requested next status is allowed
        return in_array(
            $newStatus,
            self::$transitions[$currentStatus],
            true
        );
    }


    /**
     * Return all possible next statuses
     * for a given current status.
     */
    public static function getNextStatuses(
        string $currentStatus
    ): array {

        if (!ResearchStatus::isValid($currentStatus)) {
            return [];
        }

        if (ResearchStatus::isFinal($currentStatus)) {
            return [];
        }

        return self::$transitions[$currentStatus] ?? [];
    }


    /**
     * Check if the research already
     * reached a final workflow state.
     */
    public static function isFinished(
        string $status
    ): bool {

        return ResearchStatus::isFinal($status);
    }
}