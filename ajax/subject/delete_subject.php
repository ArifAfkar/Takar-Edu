<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

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
        "msg"    => "ID mata pelajaran tidak valid."
    ]);
    exit;
}

/* =======================================================
    FETCH SUBJECT DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT id, subject_name
    FROM subjects
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Data mata pelajaran tidak ditemukan."
    ]);
    exit;
}

$subject = $result->fetch_assoc();

$subject_id   = (int) $subject['id'];
$subject_name = $subject['subject_name'];

$stmt->close();

/* =======================================================
    RELATION CHECK - TEACHERS
======================================================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM teachers
    WHERE subject_id = ?
");

$stmt->bind_param("i", $subject_id);
$stmt->execute();

$teacherCount = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();

if ($teacherCount > 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Mata pelajaran tidak dapat dihapus karena masih terhubung dengan pengajar."
    ]);
    exit;
}

/* =======================================================
    DELETE SUBJECT
======================================================= */
$stmt = $conn->prepare("
    DELETE FROM subjects
    WHERE id = ?
");

$stmt->bind_param("i", $subject_id);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Gagal menghapus data mata pelajaran."
    ]);
    exit;
}

$stmt->close();

/* =======================================================
    SUCCESS RESPONSE
======================================================= */
echo json_encode([
    "status"       => 1,
    "msg"          => "Data mata pelajaran berhasil dihapus.",
    "subject_name" => $subject_name
]);
exit;
?>