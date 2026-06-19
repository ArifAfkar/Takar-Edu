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
    "msg"    => "Data pengajar tidak ditemukan."
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

    $response['msg'] = "ID pengajar tidak valid.";

    echo json_encode($response);
    exit;
}

/* =======================================================
    FETCH TEACHER DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT
        t.id,
        t.user_id,
        t.subject_id,
        t.gender,

        u.name,
        u.username,
        u.status

    FROM teachers t

    INNER JOIN users u
        ON t.user_id = u.id

    WHERE t.id = ?
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
            "id"         => (int) $data['id'],
            "user_id"    => (int) $data['user_id'],
            "name"       => $data['name'],
            "username"   => $data['username'],
            "subject_id" => !empty($data['subject_id'])
                ? (int) $data['subject_id']
                : "",
            "gender"     => $data['gender'],
            "status"     => (int) $data['status']
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