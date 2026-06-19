<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type'])
) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Sesi tidak valid.'
    ]);
    exit;
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);

if (!$isAdmin && !$isTeacher) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Anda tidak memiliki izin.'
    ]);
    exit;
}

$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = isset($_POST['status']) ? (int) $_POST['status'] : -1;

if ($id <= 0 || !in_array($status, [0, 1])) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Data tidak valid.'
    ]);
    exit;
}

$where = "qsl.id = {$id}";

if ($isTeacher) {
    $where .= " AND q.created_by = {$userId}";
}

$check = $conn->query("
    SELECT qsl.id
    FROM quiz_student_list qsl
    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id
    WHERE {$where}
    LIMIT 1
");

if (!$check || $check->num_rows <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Akses ditolak atau data tidak ditemukan.'
    ]);
    exit;
}

$update = $conn->query("
    UPDATE quiz_student_list
    SET status = {$status}
    WHERE id = {$id}
");

if ($update) {
    echo json_encode([
        'status' => 1,
        'msg' => $status === 1
            ? 'Distribusi berhasil diaktifkan.'
            : 'Distribusi berhasil dinonaktifkan.'
    ]);
    exit;
}

echo json_encode([
    'status' => 0,
    'msg' => 'Gagal memperbarui status.'
]);
exit;