<?php

/**
 * ASU-REMS
 * CSRF Protection
 *
 * Ginagamit para protektahan ang POST forms
 * laban sa unauthorized requests.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * Gumagawa ng CSRF token.
 *
 * Kapag wala pang token sa session,
 * automatic itong gagawa.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/**
 * Vine-verify kung valid ang token
 * na galing sa submitted form.
 */
function verify_csrf_token(?string $token): bool
{
    if (
        empty($token) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/**
 * Required check para sa POST actions.
 *
 * Kapag invalid ang token,
 * ititigil ang request.
 */
function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($token)) {

        http_response_code(403);

        exit(
            'Invalid request. Please refresh the page and try again.'
        );
    }
}