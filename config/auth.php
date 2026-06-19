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
    DATABASE CONNECTION
======================================================= */

// Load database connection
require_once 'db_connect.php';

/* =======================================================
    LOGIN SESSION VALIDATION
======================================================= */

// Verify active login session
if (!isset($_SESSION['login_id'])) {

    header("Location: ../pages/login.php");
    exit;
}

/* =======================================================
    CURRENT SESSION USER
======================================================= */

// Get authenticated user ID
$userId = (int) $_SESSION['login_id'];

/* =======================================================
    VERIFY USER STILL EXISTS
======================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        username,
        user_type,
        status,
        profile_image
    FROM users
    WHERE id = ?
    LIMIT 1
");

/* ---------- Statement preparation failure ---------- */
if (!$stmt) {

    error_log("Auth Error: Failed to prepare user verification query.");

    session_unset();
    session_destroy();

    header("Location: ../pages/login.php");
    exit;
}

/* =======================================================
    EXECUTE USER VERIFICATION
======================================================= */

// Bind user ID
$stmt->bind_param("i", $userId);

// Execute query
$stmt->execute();

// Get result
$result = $stmt->get_result();

/* ---------- User deleted or invalid ---------- */
if (!$result || $result->num_rows === 0) {

    error_log("Auth Warning: Session user not found. ID: " . $userId);

    session_unset();
    session_destroy();

    header("Location: ../pages/login.php");
    exit;
}

/* =======================================================
    FETCH USER DATA
======================================================= */

// Fetch latest user data
$user = $result->fetch_assoc();

/* =======================================================
    ACCOUNT STATUS CHECK
======================================================= */

// If account disabled while logged in
if ((int)$user['status'] !== 1) {

    error_log("Auth Warning: Disabled account attempted session access. ID: " . $userId);

    session_unset();
    session_destroy();

    header("Location: ../pages/login.php?inactive=1");
    exit;
}

/* =======================================================
    REFRESH SESSION DATA
======================================================= */

// Keep session synced with database
$_SESSION['login_name']          = $user['name'];
$_SESSION['login_username']      = $user['username'];
$_SESSION['login_user_type']     = (int)$user['user_type'];
$_SESSION['login_profile_image'] = $user['profile_image'] ?? '';
$_SESSION['login_status']        = (int)$user['status'];

/* =======================================================
    GLOBAL USER VARIABLES
======================================================= */

// Common reusable variables
$name         = $user['name'];
$username     = $user['username'];
$userType     = (int)$user['user_type'];
$profileImage = $user['profile_image'] ?? '';

/* =======================================================
    CLEANUP
======================================================= */

// Close statement
$stmt->close();
?>