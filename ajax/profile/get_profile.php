<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';


/* =======================================================
    JSON RESPONSE HEADER
======================================================= */

header('Content-Type: application/json');


/* =======================================================
    SESSION VALIDATION
======================================================= */

$login_id = intval($_SESSION['login_id'] ?? 0);

if (!$login_id) {

    echo json_encode([
        "status" => 0,
        "msg" => "Sesi tidak valid. Silakan login kembali."
    ]);

    exit;

}


/* =======================================================
    PROFILE DATA QUERY
======================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        username,
        profile_image,
        status,
        user_type
    FROM users
    WHERE id = ?
    LIMIT 1
");


/* =======================================================
    QUERY VALIDATION
======================================================= */

if (!$stmt) {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal menyiapkan query database."
    ]);

    exit;

}


/* =======================================================
    EXECUTE QUERY
======================================================= */

$stmt->bind_param("i", $login_id);

if (!$stmt->execute()) {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal mengambil data profil."
    ]);

    $stmt->close();
    exit;

}

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();


/* =======================================================
    USER VALIDATION
======================================================= */

if (!$user) {

    echo json_encode([
        "status" => 0,
        "msg" => "Data pengguna tidak ditemukan."
    ]);

    exit;

}


/* =======================================================
    PROFILE IMAGE SANITIZATION
======================================================= */

if (!empty($user['profile_image'])) {

    $fullPath = '../../' . $user['profile_image'];

    if (
        !file_exists($fullPath) ||
        !is_file($fullPath)
    ) {

        // Reset invalid image path
        $user['profile_image'] = null;

    }

}


/* =======================================================
    SUCCESS RESPONSE
======================================================= */

echo json_encode([
    "status" => 1,
    "user" => [
        "id" => intval($user['id']),
        "name" => $user['name'],
        "username" => $user['username'],
        "profile_image" => $user['profile_image'],
        "status" => intval($user['status']),
        "user_type" => intval($user['user_type'])
    ]
]);

exit;
?>