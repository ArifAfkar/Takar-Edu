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
if (!isset($_SESSION['login_user_type']) || $_SESSION['login_user_type'] != 1) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Akses ditolak."
    ]);
    exit;
}

/* =======================================================
    VALIDATE INPUT
======================================================= */
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "ID kelas tidak valid."
    ]);
    exit;
}

/* =======================================================
    FETCH CLASS DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT id, class_name
    FROM classes
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Data kelas tidak ditemukan."
    ]);
    exit;
}

$class = $result->fetch_assoc();

$class_id   = (int) $class['id'];
$class_name = $class['class_name'];

$stmt->close();

/* =======================================================
    RELATION CHECK - STUDENTS
======================================================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM students
    WHERE class_id = ?
");

$stmt->bind_param("i", $class_id);
$stmt->execute();

$studentCount = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();

if ($studentCount > 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Kelas tidak dapat dihapus karena masih digunakan oleh siswa."
    ]);
    exit;
}

/* =======================================================
    RELATION CHECK - TEACHER CLASS ASSIGNMENTS
======================================================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM teacher_class_assignments
    WHERE class_id = ?
");

$stmt->bind_param("i", $class_id);
$stmt->execute();

$assignmentCount = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();

if ($assignmentCount > 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Kelas tidak dapat dihapus karena masih terhubung dengan pengajar."
    ]);
    exit;
}

/* =======================================================
    DELETE CLASS
======================================================= */
$stmt = $conn->prepare("
    DELETE FROM classes
    WHERE id = ?
");

$stmt->bind_param("i", $class_id);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Gagal menghapus data kelas."
    ]);
    exit;
}

$stmt->close();

/* =======================================================
    SUCCESS RESPONSE
======================================================= */
echo json_encode([
    "status"     => 1,
    "msg"        => "Data kelas berhasil dihapus.",
    "class_name" => $class_name
]);
exit;
?>