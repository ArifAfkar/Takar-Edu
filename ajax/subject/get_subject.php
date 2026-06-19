<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if ($_SESSION['login_user_type'] != 1) {
    echo json_encode([
        "status" => 0,
        "msg" => "Akses ditolak."
    ]);
    exit;
}

/* =======================================================
    VALIDATION
======================================================= */
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => 0,
        "msg" => "ID tidak valid."
    ]);
    exit;
}

/* =======================================================
    GET DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT
        id,
        subject_name,
        subject_code,
        description
    FROM subjects
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    echo json_encode([
        "status" => 0,
        "msg" => "Data tidak ditemukan."
    ]);

    exit;
}

$data = $result->fetch_assoc();

echo json_encode([
    "status" => 1,
    "data" => $data
]);
?>