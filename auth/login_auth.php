<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */

require_once '../config/db_connect.php';

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

session_start();

/* =======================================================
    RESPONSE CODES
======================================================= */
/*
    1 = Login berhasil
    2 = Username/password salah atau input kosong
    3 = Akun nonaktif
    4 = Error sistem
    5 = CSRF token tidak valid
    6 = Akun terkunci sementara (1 menit - development)
*/

/* =======================================================
    CSRF VALIDATION
======================================================= */

if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    echo 5;
    exit;
}

/* =======================================================
    INPUT VALIDATION
======================================================= */

// Username sanitization
$username = isset($_POST['username'])
    ? trim($_POST['username'])
    : '';

// Password sanitization
$password = isset($_POST['password'])
    ? trim($_POST['password'])
    : '';

// Empty input validation
if ($username === '' || $password === '') {
    echo 2;
    exit;
}

/* =======================================================
    AUTHENTICATION PROCESS
======================================================= */

try {

    /* =======================================================
        PREPARE USER QUERY
    ======================================================= */
    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            username,
            password,
            user_type,
            status,
            profile_image,
            failed_login_attempts,
            locked_until
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    // Query preparation failed
    if (!$stmt) {
        echo 4;
        exit;
    }

    /* =======================================================
        EXECUTE USER QUERY
    ======================================================= */

    // Bind username parameter
    $stmt->bind_param("s", $username);

    // Execute query
    $stmt->execute();

    // Fetch result
    $result = $stmt->get_result();

    /* =======================================================
        USER EXISTENCE CHECK
    ======================================================= */

    if (!$result || $result->num_rows === 0) {
        echo 2;
        exit;
    }

    /* =======================================================
        FETCH USER DATA
    ======================================================= */

    $user = $result->fetch_assoc();

    /* =======================================================
        RATE LIMITING CHECK
    ======================================================= */

    if (!empty($user['locked_until'])) {

        // Account still locked
        if (strtotime($user['locked_until']) > time()) {
            echo 6;
            exit;
        }

        // Lock expired
        else {

            /* ---------- Reset lock status ---------- */
            $resetLock = $conn->prepare("
                UPDATE users
                SET
                    failed_login_attempts = 0,
                    locked_until = NULL
                WHERE id = ?
            ");

            if (!$resetLock) {
                echo 4;
                exit;
            }

            $resetLock->bind_param("i", $user['id']);
            $resetLock->execute();

            // Sync runtime data
            $user['failed_login_attempts'] = 0;
            $user['locked_until'] = null;
        }
    }

    /* =======================================================
        PASSWORD VALIDATION
    ======================================================= */

    if (!password_verify($password, $user['password'])) {

        // Increase failed attempts
        $attempts = (int)$user['failed_login_attempts'] + 1;

        /* =======================================================
            ACCOUNT LOCK THRESHOLD
        ======================================================= */

        // Lock after 5 failures
        if ($attempts >= 5) {

            $lockStmt = $conn->prepare("
                UPDATE users
                SET
                    failed_login_attempts = ?,
                    locked_until = DATE_ADD(NOW(), INTERVAL 1 MINUTE)
                WHERE id = ?
            ");

            if (!$lockStmt) {
                echo 4;
                exit;
            }

            $lockStmt->bind_param("ii", $attempts, $user['id']);
            $lockStmt->execute();
        }

        // Normal failed login increment
        else {

            $failStmt = $conn->prepare("
                UPDATE users
                SET failed_login_attempts = ?
                WHERE id = ?
            ");

            if (!$failStmt) {
                echo 4;
                exit;
            }

            $failStmt->bind_param("ii", $attempts, $user['id']);
            $failStmt->execute();
        }

        echo 2;
        exit;
    }

    /* =======================================================
        ACCOUNT STATUS CHECK
    ======================================================= */

    if ((int)$user['status'] !== 1) {
        echo 3;
        exit;
    }

    /* =======================================================
        SESSION SECURITY HARDENING
    ======================================================= */

    // Prevent session fixation
    session_regenerate_id(true);

    // Regenerate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    /* =======================================================
        RESET LOGIN FAILURES
    ======================================================= */

    $resetStmt = $conn->prepare("
        UPDATE users
        SET
            failed_login_attempts = 0,
            locked_until = NULL,
            last_login = NOW()
        WHERE id = ?
    ");

    if (!$resetStmt) {
        echo 4;
        exit;
    }

    $resetStmt->bind_param("i", $user['id']);
    $resetStmt->execute();

    /* =======================================================
        STORE AUTH SESSION DATA
    ======================================================= */

    $_SESSION['login_id']            = (int)$user['id'];
    $_SESSION['login_name']          = $user['name'];
    $_SESSION['login_username']      = $user['username'];
    $_SESSION['login_user_type']     = (int)$user['user_type'];
    $_SESSION['login_profile_image'] = $user['profile_image'] ?? '';
    $_SESSION['login_status']        = (int)$user['status'];
    $_SESSION['login_time']          = time();

    /* =======================================================
        CLEANUP RESOURCES
    ======================================================= */

    $stmt->close();
    $conn->close();

    /* =======================================================
        SUCCESS RESPONSE
    ======================================================= */

    echo 1;
    exit;

} catch (Exception $e) {

    /* =======================================================
        SYSTEM ERROR HANDLING
    ======================================================= */

    // Optional development logging:
    error_log($e->getMessage());

    echo 4;
    exit;
}
?>