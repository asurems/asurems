
<?php
/**
 * ASU REMS - Extension Module
 *
 * File: extension/includes/extension_roles.php
 *
 * Extension-only role and permission management.
 * Does not modify Research Module permissions.
 */

/*
|--------------------------------------------------------------------------
| EXTENSION PROPONENT ROLES
|--------------------------------------------------------------------------
|
| Faculty, Extensionist, and Extension Coordinator
| have the same permissions as Extension Proponents.
|
*/

function extensionProponentRoles(): array
{
    return [
        'faculty',
        'extensionist',
        'extension_coordinator'
    ];
}

/*
|--------------------------------------------------------------------------
| EXTENSION SERVICE OFFICE ROLE
|--------------------------------------------------------------------------
|
| ESO manages the Extension proposal workflow,
| similar to how RPS manages Research submissions.
|
| IMPORTANT:
| The role value below must match users.role
| in the database.
|
*/

function extensionOfficeRoles(): array
{
    return [
        'extension_service_office'
    ];
}

/*
|--------------------------------------------------------------------------
| CURRENT USER ROLE
|--------------------------------------------------------------------------
*/

function extensionCurrentRole(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

/*
|--------------------------------------------------------------------------
| CHECK PROPONENT
|--------------------------------------------------------------------------
*/

function extensionIsProponent(?string $role = null): bool
{
    if ($role === null) {
        $role = extensionCurrentRole();
    }

    return in_array(
        $role,
        extensionProponentRoles(),
        true
    );
}

/*
|--------------------------------------------------------------------------
| CHECK EXTENSION SERVICE OFFICE
|--------------------------------------------------------------------------
*/

/** Only an Extension Coordinator acts on proposal submissions and endorsements.
 * Generic extensionIsProponent() is retained for existing service workflows.
 */
function extensionIsCoordinator(?string $role = null): bool
{
    return ($role ?? extensionCurrentRole()) === 'extension_coordinator';
}

function extensionRequireCoordinator(): void
{
    requireLogin();
    if (!extensionIsCoordinator()) {
        http_response_code(403);
        exit('Access denied. Extension Coordinator only.');
    }
}

function extensionIsOffice(?string $role = null): bool
{
    if ($role === null) {
        $role = extensionCurrentRole();
    }

    return in_array(
        $role,
        extensionOfficeRoles(),
        true
    );
}

/*
|--------------------------------------------------------------------------
| EXTENSION MODULE ACCESS
|--------------------------------------------------------------------------
*/

function extensionCanAccess(?string $role = null): bool
{
    return extensionIsProponent($role)
        || extensionIsOffice($role);
}

/*
|--------------------------------------------------------------------------
| PROPONENT PERMISSIONS
|--------------------------------------------------------------------------
*/

function extensionCanSubmitProposal(?string $role = null): bool
{
    return extensionIsCoordinator($role);
}

function extensionCanReviseProposal(?string $role = null): bool
{
    return extensionIsCoordinator($role);
}

/*
|--------------------------------------------------------------------------
| ESO PERMISSIONS
|--------------------------------------------------------------------------
*/

function extensionCanManageCalls(?string $role = null): bool
{
    return extensionIsOffice($role);
}

function extensionCanManageReviews(?string $role = null): bool
{
    return extensionIsOffice($role);
}

function extensionCanManageEndorsements(?string $role = null): bool
{
    return extensionIsOffice($role);
}

function extensionCanManageApprovals(?string $role = null): bool
{
    return extensionIsOffice($role);
}

function extensionCanManageCertifications(?string $role = null): bool
{
    return extensionIsOffice($role);
}

/*
|--------------------------------------------------------------------------
| REQUIRE EXTENSION ACCESS
|--------------------------------------------------------------------------
|
| Authentication functions come from
| the existing ASU REMS includes/auth.php.
|
*/

function extensionRequireAccess(): void
{
    requireLogin();

    if (!extensionCanAccess()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE PROPONENT ACCESS
|--------------------------------------------------------------------------
*/

function extensionRequireProponent(): void
{
    requireLogin();

    if (!extensionIsProponent()) {
        http_response_code(403);

        exit(
            'Access denied. Extension Proponent only.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE ESO ACCESS
|--------------------------------------------------------------------------
*/

function extensionRequireOffice(): void
{
    requireLogin();

    if (!extensionIsOffice()) {
        http_response_code(403);

        exit(
            'Access denied. Extension Service Office only.'
        );
    }
}
