<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

function jsonResponse($status, $msg)
{
    echo json_encode([
        'status' => $status,
        'msg'    => $msg
    ]);
    exit;
}

if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type'])
) {
    jsonResponse(0, 'Sesi tidak valid.');
}

$userType = (int) $_SESSION['login_user_type'];

if ($userType !== 1) {
    jsonResponse(0, 'Hanya administrator yang dapat mengubah status distribusi pengajar.');
}

$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = isset($_POST['status']) ? (int) $_POST['status'] : -1;

if ($id <= 0 || !in_array($status, [0, 1])) {
    jsonResponse(0, 'Data tidak valid.');
}

$check = $conn->query("
    SELECT id
    FROM teacher_class_assignments
    WHERE id = {$id}
    LIMIT 1
");

if (!$check || $check->num_rows <= 0) {
    jsonResponse(0, 'Distribusi tidak ditemukan.');
}

$update = $conn->query("
    UPDATE teacher_class_assignments
    SET
        status = {$status},
        updated_at = NOW()
    WHERE id = {$id}
    LIMIT 1
");

if (!$update) {
    jsonResponse(0, 'Gagal memperbarui status distribusi.');
}

jsonResponse(
    1,
    $status === 1
        ? 'Distribusi pengajar berhasil diaktifkan.'
        : 'Distribusi pengajar berhasil dinonaktifkan.'
);