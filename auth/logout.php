<?php
/* =======================================================
    SECURE SESSION CONFIGURATION
======================================================= */

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

/* =======================================================
    SESSION INITIALIZATION
======================================================= */

// Start secure session
session_start();

/* =======================================================
    SESSION DATA CLEANUP
======================================================= */

// Clear all session variables
$_SESSION = [];

/* =======================================================
    SESSION COOKIE INVALIDATION
======================================================= */

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* =======================================================
    SESSION DESTRUCTION
======================================================= */

// Destroy active session
session_destroy();

/* =======================================================
    SECURITY CACHE CONTROL
======================================================= */

// Prevent browser caching authenticated pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

/* =======================================================
    SAFE REDIRECTION
======================================================= */

// Redirect user to login page
header("Location: ../pages/login.php");

/* =======================================================
    TERMINATE EXECUTION
======================================================= */

exit;
?>