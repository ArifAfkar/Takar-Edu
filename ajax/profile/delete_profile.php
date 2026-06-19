<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';


/* =======================================================
    SESSION VALIDATION
======================================================= */

$login_id = intval($_SESSION['login_id'] ?? 0);

if (!$login_id) {

    header("Location: ../../index.php");
    exit;

}


/* =======================================================
    REQUEST VALIDATION
======================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: ../../pages/profile.php");
    exit;

}


/* =======================================================
    RETRIEVE CURRENT PROFILE IMAGE
======================================================= */

$stmt = $conn->prepare("
    SELECT profile_image
    FROM users
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    $_SESSION['profile_message'] = 'Gagal menyiapkan query database.';
    $_SESSION['profile_message_type'] = 'error';

    header("Location: ../../pages/profile.php");
    exit;

}

$stmt->bind_param("i", $login_id);

if (!$stmt->execute()) {

    $_SESSION['profile_message'] = 'Gagal mengambil data profil.';
    $_SESSION['profile_message_type'] = 'error';

    $stmt->close();

    header("Location: ../../pages/profile.php");
    exit;

}

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();


/* =======================================================
    VALIDATE PROFILE IMAGE EXISTENCE
======================================================= */

if (
    !$user ||
    empty($user['profile_image'])
) {

    $_SESSION['profile_message'] = 'Foto profil tidak ditemukan.';
    $_SESSION['profile_message_type'] = 'error';

    header("Location: ../../pages/profile.php");
    exit;

}


/* =======================================================
    FILE PATH PREPARATION
======================================================= */

$filePath = '../../' . $user['profile_image'];


/* =======================================================
    DATABASE UPDATE (REMOVE IMAGE REFERENCE)
======================================================= */

$stmt = $conn->prepare("
    UPDATE users
    SET profile_image = NULL
    WHERE id = ?
");

if (!$stmt) {

    $_SESSION['profile_message'] = 'Gagal menyiapkan update database.';
    $_SESSION['profile_message_type'] = 'error';

    header("Location: ../../pages/profile.php");
    exit;

}

$stmt->bind_param("i", $login_id);


/* =======================================================
    EXECUTION RESULT
======================================================= */

if ($stmt->execute()) {

    /* =======================================================
        DELETE PHYSICAL FILE
    ======================================================= */
    if (
        file_exists($filePath) &&
        is_file($filePath)
    ) {
        unlink($filePath);
    }

    $_SESSION['profile_message'] = 'Foto profil berhasil dihapus.';
    $_SESSION['profile_message_type'] = 'success';

} else {

    $_SESSION['profile_message'] = 'Gagal menghapus foto profil.';
    $_SESSION['profile_message_type'] = 'error';

}

$stmt->close();


/* =======================================================
    REDIRECT BACK
======================================================= */

header("Location: ../../pages/profile.php");
exit;
?>