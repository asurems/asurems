<?php

/*
|--------------------------------------------------------------------------
| RESEARCH WORKFLOW BRIDGE
|--------------------------------------------------------------------------
|
| Connects the existing research_proposals module
| to the new detailed RPS workflow.
|
*/


/*
|--------------------------------------------------------------------------
| ENSURE MANUSCRIPT NUMBER
|--------------------------------------------------------------------------
|
| Must be called INSIDE an active database transaction.
|
*/

function ensureResearchManuscriptNumber(
    PDO $pdo,
    int $researchId
): string {

    /*
    |--------------------------------------------------------------------------
    | LOCK RESEARCH RECORD
    |--------------------------------------------------------------------------
    */

    $researchStmt =
        $pdo->prepare("
            SELECT
                manuscript_no

            FROM research_proposals

            WHERE research_id = ?

            FOR UPDATE
        ");

    $researchStmt->execute([
        $researchId
    ]);

    $research =
        $researchStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$research) {

        throw new RuntimeException(
            'Research record not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RETURN EXISTING NUMBER
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $research['manuscript_no']
        )
    ) {

        return
            $research['manuscript_no'];
    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT YEAR
    |--------------------------------------------------------------------------
    */

    $year =
        (int)date('Y');


    /*
    |--------------------------------------------------------------------------
    | MAKE SURE YEAR SEQUENCE EXISTS
    |--------------------------------------------------------------------------
    */

    $insertSequence =
        $pdo->prepare("
            INSERT INTO manuscript_sequences
            (
                sequence_year,
                last_number
            )
            VALUES
            (
                ?,
                0
            )

            ON DUPLICATE KEY UPDATE
                sequence_year = sequence_year
        ");

    $insertSequence->execute([
        $year
    ]);


    /*
    |--------------------------------------------------------------------------
    | LOCK YEAR SEQUENCE
    |--------------------------------------------------------------------------
    */

    $sequenceStmt =
        $pdo->prepare("
            SELECT
                last_number

            FROM manuscript_sequences

            WHERE sequence_year = ?

            FOR UPDATE
        ");

    $sequenceStmt->execute([
        $year
    ]);

    $lastNumber =
        (int)$sequenceStmt
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | NEXT NUMBER
    |--------------------------------------------------------------------------
    */

    $nextNumber =
        $lastNumber + 1;


    $updateSequence =
        $pdo->prepare("
            UPDATE manuscript_sequences

            SET last_number = ?

            WHERE sequence_year = ?
        ");

    $updateSequence->execute([
        $nextNumber,
        $year
    ]);


    /*
    |--------------------------------------------------------------------------
    | FORMAT
    |--------------------------------------------------------------------------
    */

    $manuscriptNo =
        sprintf(
            'ASU-RES-%04d-%04d',
            $year,
            $nextNumber
        );


    /*
    |--------------------------------------------------------------------------
    | SAVE TO RESEARCH
    |--------------------------------------------------------------------------
    */

    $updateResearch =
        $pdo->prepare("
            UPDATE research_proposals

            SET manuscript_no = ?

            WHERE research_id = ?
              AND manuscript_no IS NULL
        ");

    $updateResearch->execute([
        $manuscriptNo,
        $researchId
    ]);


    return
        $manuscriptNo;
}