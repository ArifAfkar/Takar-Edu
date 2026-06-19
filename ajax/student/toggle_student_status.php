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
    "msg"    => "Terjadi kesalahan sistem."
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
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {

    $response['msg'] = "ID siswa tidak valid.";

    echo json_encode($response);
    exit;
}

/* =======================================================
    FETCH STUDENT USER
======================================================= */
$stmt = $conn->prepare("
    SELECT
        s.id,
        s.user_id,
        u.status,
        u.name
    FROM students s
    INNER JOIN users u
        ON s.user_id = u.id
    WHERE s.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $response['msg'] = "Data siswa tidak ditemukan.";

    echo json_encode($response);
    exit;
}

$student = $result->fetch_assoc();

$user_id        = (int) $student['user_id'];
$currentStatus  = (int) $student['status'];
$newStatus      = $currentStatus === 1 ? 0 : 1;
$studentName    = $student['name'];

$stmt->close();

/* =======================================================
    UPDATE STATUS
======================================================= */
$stmt = $conn->prepare("
    UPDATE users
    SET
        status = ?,
        updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param(
    "ii",
    $newStatus,
    $user_id
);

if ($stmt->execute()) {

    $response = [
        "status"      => 1,
        "msg"         => $newStatus === 1
            ? "Akun siswa berhasil diaktifkan."
            : "Akun siswa berhasil dinonaktifkan.",
        "new_status"  => $newStatus,
        "student_name"=> $studentName
    ];

} else {

    $response = [
        "status" => 0,
        "msg"    => "Gagal memperbarui status siswa."
    ];
}

$stmt->close();

/* =======================================================
    OUTPUT
======================================================= */
echo json_encode($response);
exit;
?>