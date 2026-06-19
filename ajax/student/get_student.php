<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
    DEFAULT RESPONSE
======================================================= */
$response = [
    "status" => 0,
    "msg"    => "Data siswa tidak ditemukan."
];

/* =======================================================
    ACCESS CONTROL
======================================================= */
if ($_SESSION['login_user_type'] != 1) {

    $response['msg'] = "Akses ditolak.";

    echo json_encode($response);
    exit;
}

/* =======================================================
    VALIDATE ID
======================================================= */
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {

    $response['msg'] = "ID siswa tidak valid.";

    echo json_encode($response);
    exit;
}

/* =======================================================
    FETCH STUDENT DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT
        s.id,
        s.user_id,
        s.class_id,
        s.gender,

        u.name,
        u.username,
        u.status,
        u.profile_image

    FROM students s

    INNER JOIN users u
        ON s.user_id = u.id

    WHERE s.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

/* =======================================================
    RESPONSE
======================================================= */
if ($result->num_rows > 0) {

    $data = $result->fetch_assoc();

    $response = [
        "status" => 1,
        "msg"    => "Data ditemukan.",
        "data"   => [
            "id"            => (int) $data['id'],
            "user_id"       => (int) $data['user_id'],
            "name"          => $data['name'],
            "username"      => $data['username'],
            "class_id"      => !empty($data['class_id'])
                ? (int) $data['class_id']
                : "",
            "gender"        => $data['gender'],
            "status"        => (int) $data['status'],
            "profile_image" => $data['profile_image'] ?? ""
        ]
    ];
}

$stmt->close();

/* =======================================================
    OUTPUT
======================================================= */
echo json_encode($response);
exit;
?>