<?php

/**
 * ASU-REMS
 * Research Helper Functions
 *
 * Helper functions used for displaying
 * research workflow information in the UI.
 */

require_once __DIR__ . '/research_statuses.php';


/**
 * Convert database status into
 * user-friendly text.
 *
 * Example:
 *
 * URRC_REVISION_REQUIRED
 *
 * becomes:
 *
 * URRC Revision Required
 */
function researchStatusLabel(string $status): string
{
    $labels = [

        // Initial
        ResearchStatus::DRAFT =>
            'Draft',

        ResearchStatus::SUBMITTED =>
            'Submitted',


        // Ethics
        ResearchStatus::FOR_ETHICS_REVIEW =>
            'For UREC/IACUC Review',

        ResearchStatus::ETHICS_COMPLETED =>
            'UREC/IACUC Review Completed',


        // Self-Initiated
        ResearchStatus::SELF_INITIATED_UPE_REQUIRED =>
            'UPE Certificates Required',

        ResearchStatus::SELF_INITIATED_UPE_SUBMITTED =>
            'UPE Certificates Submitted',


        // URRC
        ResearchStatus::FOR_URRC =>
            'For URRC Review',

        ResearchStatus::URRC_REVISION_REQUIRED =>
            'URRC Revision Required',

        ResearchStatus::URRC_REVISED_SUBMITTED =>
            'URRC Revised Manuscript Submitted',

        ResearchStatus::URRC_REJECTED =>
            'Rejected by URRC',


        // In-House
        ResearchStatus::FOR_INHOUSE =>
            'For In-House Review',

        ResearchStatus::INHOUSE_REVISION_REQUIRED =>
            'In-House Revision Required',

        ResearchStatus::INHOUSE_REVISED_SUBMITTED =>
            'In-House Revised Manuscript Submitted',

        ResearchStatus::INHOUSE_FAILED =>
            'Failed In-House Review',


        // Notice to Proceed
        ResearchStatus::READY_FOR_NTP =>
            'Ready for Notice to Proceed',

        ResearchStatus::NTP_ISSUED =>
            'Notice to Proceed Issued',


        // Research Completion
        ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED =>
            'Completed Manuscript Submitted',

        ResearchStatus::TERMINAL_REPORT_SUBMITTED =>
            'Terminal Report Submitted',


        // Permit / Publication
        ResearchStatus::PERMIT_ISSUED =>
            'Permit to Publish/Present Issued',

        ResearchStatus::COMPLETED =>
            'Research Completed',


        // Archived
        ResearchStatus::ARCHIVED_NO_REVISION =>
            'Archived - Revision Not Submitted'
    ];


    return $labels[$status] ?? $status;
}


/**
 * Return the major stage of the research.
 *
 * Useful later for:
 *
 * Research Tracking
 * Progress Bar
 * Dashboard
 */
function researchStageLabel(string $status): string
{
    $stages = [

        ResearchStatus::DRAFT =>
            'Research Submission',

        ResearchStatus::SUBMITTED =>
            'Research Submission',


        ResearchStatus::FOR_ETHICS_REVIEW =>
            'UREC/IACUC Review',

        ResearchStatus::ETHICS_COMPLETED =>
            'UREC/IACUC Review',


        ResearchStatus::SELF_INITIATED_UPE_REQUIRED =>
            'University Pool of Experts',

        ResearchStatus::SELF_INITIATED_UPE_SUBMITTED =>
            'University Pool of Experts',


        ResearchStatus::FOR_URRC =>
            'URRC Review',

        ResearchStatus::URRC_REVISION_REQUIRED =>
            'URRC Review',

        ResearchStatus::URRC_REVISED_SUBMITTED =>
            'URRC Review',

        ResearchStatus::URRC_REJECTED =>
            'URRC Review',


        ResearchStatus::FOR_INHOUSE =>
            'In-House Review',

        ResearchStatus::INHOUSE_REVISION_REQUIRED =>
            'In-House Review',

        ResearchStatus::INHOUSE_REVISED_SUBMITTED =>
            'In-House Review',

        ResearchStatus::INHOUSE_FAILED =>
            'In-House Review',


        ResearchStatus::READY_FOR_NTP =>
            'Notice to Proceed',

        ResearchStatus::NTP_ISSUED =>
            'Research Implementation',


        ResearchStatus::COMPLETED_MANUSCRIPT_SUBMITTED =>
            'Research Completion',

        ResearchStatus::TERMINAL_REPORT_SUBMITTED =>
            'Terminal Report',


        ResearchStatus::PERMIT_ISSUED =>
            'Publication / Presentation',

        ResearchStatus::COMPLETED =>
            'Completed',


        ResearchStatus::ARCHIVED_NO_REVISION =>
            'Archived'
    ];


    return $stages[$status] ?? 'Unknown Stage';
}


/**
 * Convert submission track into
 * readable text.
 */
function researchTrackLabel(string $track): string
{
    $labels = [

        'REGULAR' =>
            'Regular Research',

        'SELF_INITIATED' =>
            'Self-Initiated Research'
    ];

    return $labels[$track] ?? $track;
}


/**
 * Returns Bootstrap badge class
 * depending on the research status.
 *
 * Since gumagamit kayo ng Bootstrap,
 * magagamit natin ito later sa tables
 * at dashboard.
 */
function researchStatusBadgeClass(string $status): string
{
    if ($status === ResearchStatus::COMPLETED) {
        return 'bg-success';
    }


    if (
        $status === ResearchStatus::URRC_REJECTED ||
        $status === ResearchStatus::INHOUSE_FAILED
    ) {
        return 'bg-danger';
    }


    if (
        $status === ResearchStatus::URRC_REVISION_REQUIRED ||
        $status === ResearchStatus::INHOUSE_REVISION_REQUIRED
    ) {
        return 'bg-warning text-dark';
    }


    if ($status === ResearchStatus::ARCHIVED_NO_REVISION) {
        return 'bg-secondary';
    }


    if (
        $status === ResearchStatus::DRAFT
    ) {
        return 'bg-secondary';
    }


    return 'bg-primary';
}


/**
 * Safe HTML output.
 *
 * Example:
 *
 * <?= e($research['title']) ?>
 *
 * This prevents HTML/script content
 * from being directly rendered.
 */
function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}