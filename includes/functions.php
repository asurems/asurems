
<?php

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function redirect($location)
{
    header('Location: ' . $location);
    exit;
}


function setFlash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}


function getFlash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];

    unset($_SESSION['flash']);

    return $flash;
}


function generateCsrfToken()
{
    if (
        empty($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


function verifyCsrfToken($token)
{
    if (
        empty($_SESSION['csrf_token']) ||
        empty($token)
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


function fullName($user)
{
    $parts = [];

    if (!empty($user['first_name'])) {
        $parts[] = $user['first_name'];
    }

    if (!empty($user['middle_name'])) {
        $parts[] = $user['middle_name'];
    }

    if (!empty($user['last_name'])) {
        $parts[] = $user['last_name'];
    }

    return implode(' ', $parts);
}


function roleLabel($role)
{
    switch ($role) {

        case 'admin':
            return 'Administrator';

        case 'faculty':
            return 'Faculty / Researcher';

        case 'extension_coordinator':
            return 'Extension Coordinator';

        case 'rps':
            return 'Research Processing Staff (RPS)';

        case 'extension_service_office':
            return 'Extension Service Office';

        default:
            return 'Unknown';
    }
}
