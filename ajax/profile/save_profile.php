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
    UPLOAD DIRECTORY CONFIGURATION
======================================================= */

$uploadDir = '../../uploads/profile/';

if (!is_dir($uploadDir)) {

    mkdir($uploadDir, 0755, true);

}


/* =======================================================
    FILE INPUT VALIDATION
======================================================= */

$file = $_FILES['profile_image'] ?? null;

if (
    !$file ||
    !isset($file['error']) ||
    $file['error'] !== UPLOAD_ERR_OK
) {

    $_SESSION['profile_message'] = 'Terjadi kesalahan upload.';
    $_SESSION['profile_message_type'] = 'error';

    header("Location: ../../pages/profile.php");
    exit;

}


/* =======================================================
    MIME TYPE VALIDATION
======================================================= */

$allowedTypes = [
    'image/jpeg',
    'image/png'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($realType, $allowedTypes)) {

    $_SESSION['profile_message'] = 'Format file harus JPG, JPEG, atau PNG.';
    $_SESSION['profile_message_type'] = 'error';

    header("Location: ../../pages/profile.php");
    exit;

}


/* =======================================================
    FILE SIZE VALIDATION
======================================================= */

$maxSize = 2 * 1024 * 1024;

if ($file['size'] > $maxSize) {

    $_SESSION['profile_message'] = 'Ukuran file maksimal 2MB.';
    $_SESSION['profile_message_type'] = 'error';

    header("Location: ../../pages/profile.php");
    exit;

}


/* =======================================================
    RETRIEVE OLD PROFILE IMAGE
======================================================= */

$stmtOld = $conn->prepare("
    SELECT profile_image
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmtOld->bind_param("i", $login_id);
$stmtOld->execute();

$resultOld = $stmtOld->get_result();
$oldUser = $resultOld->fetch_assoc();

$stmtOld->close();

$oldFilePath = null;

if (!empty($oldUser['profile_image'])) {

    $oldFilePath = '../../' . $oldUser['profile_image'];

}


/* =======================================================
    NEW FILE GENERATION
======================================================= */

$extension = strtolower(
    pathinfo(
        $file['name'],
        PATHINFO_EXTENSION
    )
);

$newFileName =
    'profile_' .
    $login_id .
    '_' .
    time() .
    '.' .
    $extension;

$targetPath = $uploadDir . $newFileName;
$dbPath = 'uploads/profile/' . $newFileName;


/* =======================================================
    STORE NEW FILE
======================================================= */

if (!move_uploaded_file(
    $file['tmp_name'],
    $targetPath
)) {

    $_SESSION['profile_message'] = 'Upload file gagal.';
    $_SESSION['profile_message_type'] = 'error';

    header("Location: ../../pages/profile.php");
    exit;

}


/* =======================================================
    DATABASE UPDATE
======================================================= */

$stmt = $conn->prepare("
    UPDATE users
    SET profile_image = ?
    WHERE id = ?
");

$stmt->bind_param(
    "si",
    $dbPath,
    $login_id
);


/* =======================================================
    EXECUTION RESULT
======================================================= */

if ($stmt->execute()) {

    /* =======================================================
        DELETE OLD FILE AFTER SUCCESS
    ======================================================= */
    if (
        !empty($oldFilePath) &&
        file_exists($oldFilePath) &&
        is_file($oldFilePath)
    ) {
        unlink($oldFilePath);
    }

    $_SESSION['profile_message'] = 'Foto profil berhasil diperbarui.';
    $_SESSION['profile_message_type'] = 'success';

} else {

    /* =======================================================
        ROLLBACK NEW FILE
    ======================================================= */
    if (
        file_exists($targetPath) &&
        is_file($targetPath)
    ) {
        unlink($targetPath);
    }

    $_SESSION['profile_message'] = 'Gagal menyimpan ke database.';
    $_SESSION['profile_message_type'] = 'error';

}

$stmt->close();


/* =======================================================
    REDIRECT BACK
======================================================= */

header("Location: ../../pages/profile.php");
exit;
?>