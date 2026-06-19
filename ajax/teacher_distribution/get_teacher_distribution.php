<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   RESPONSE HELPER
======================================================= */
function jsonResponse($status, $msg = '', $extra = [])
{
    echo json_encode(array_merge([
        'status' => $status,
        'msg'    => $msg
    ], $extra));
    exit;
}

/* =======================================================
   ACCESS CONTROL
======================================================= */
if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type'])
) {
    jsonResponse(0, 'Akses ditolak.');
}

$userType = (int) $_SESSION['login_user_type'];

if ($userType !== 1) {
    jsonResponse(0, 'Anda tidak memiliki izin.');
}

/* =======================================================
   INPUT
======================================================= */
$teacherId = isset($_GET['teacher_id'])
    ? (int) $_GET['teacher_id']
    : 0;

if ($teacherId <= 0) {
    jsonResponse(0, 'Pengajar tidak valid.');
}

/* =======================================================
   TEACHER DETAIL
======================================================= */
$teacherQuery = $conn->query("
    SELECT
        t.id,
        t.subject_id,
        u.name AS teacher_name,
        s.subject_name
    FROM teachers t

    INNER JOIN users u
        ON t.user_id = u.id

    INNER JOIN subjects s
        ON t.subject_id = s.id

    WHERE t.id = {$teacherId}
    LIMIT 1
");

if (!$teacherQuery || $teacherQuery->num_rows <= 0) {
    jsonResponse(0, 'Data pengajar tidak ditemukan.');
}

$teacherData = $teacherQuery->fetch_assoc();

$subjectId = (int) $teacherData['subject_id'];

if ($subjectId <= 0) {
    jsonResponse(0, 'Pengajar belum memiliki mata pelajaran.');
}

/* =======================================================
   USED CLASSES BY SUBJECT
======================================================= */
$usedClasses = [];

$usedQuery = $conn->query("
    SELECT class_id
    FROM teacher_class_assignments
    WHERE subject_id = {$subjectId}
    AND status = 1
");

if ($usedQuery) {
    while ($row = $usedQuery->fetch_assoc()) {
        $usedClasses[] = (int) $row['class_id'];
    }
}

/* =======================================================
   RESPONSE
======================================================= */
jsonResponse(1, 'Data kelas terpakai berhasil diambil.', [
    'teacher' => [
        'teacher_id'   => (int) $teacherData['id'],
        'teacher_name' => $teacherData['teacher_name'],
        'subject_id'   => $subjectId,
        'subject_name' => $teacherData['subject_name']
    ],
    'used_classes' => $usedClasses
]);