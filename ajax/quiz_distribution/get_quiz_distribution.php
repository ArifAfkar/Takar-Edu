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

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);

if (!$isAdmin && !$isTeacher) {
    jsonResponse(0, 'Anda tidak memiliki izin.');
}

/* =======================================================
   INPUT
======================================================= */
$quizId = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;

if ($quizId <= 0) {
    jsonResponse(0, 'ID kuis tidak valid.');
}

/* =======================================================
   TEACHER VALIDATION
======================================================= */
$teacherId = 0;

if ($isTeacher) {

    $teacherQuery = $conn->query("
        SELECT id
        FROM teachers
        WHERE user_id = {$userId}
        LIMIT 1
    ");

    if (!$teacherQuery || $teacherQuery->num_rows <= 0) {
        jsonResponse(0, 'Data pengajar tidak ditemukan.');
    }

    $teacherData = $teacherQuery->fetch_assoc();
    $teacherId   = (int) $teacherData['id'];

}

/* =======================================================
   QUIZ ACCESS VALIDATION
======================================================= */
$quizAccessWhere = "q.id = {$quizId}";

if ($isTeacher) {
    $quizAccessWhere .= "
        AND EXISTS (
            SELECT 1
            FROM quiz_teacher_list qtl
            WHERE qtl.quiz_id = q.id
            AND qtl.teacher_id = {$teacherId}
        )
    ";
}

$quizAccess = $conn->query("
    SELECT q.id
    FROM quiz_list q
    WHERE {$quizAccessWhere}
    LIMIT 1
");

if (!$quizAccess || $quizAccess->num_rows <= 0) {
    jsonResponse(0, 'Kuis tidak ditemukan atau akses ditolak.');
}

/* =======================================================
   USED STUDENTS
======================================================= */
$usedStudents = [];

$where = ["qsl.quiz_id = {$quizId}"];

if ($isTeacher) {
    $where[] = "
        s.class_id IN (
            SELECT class_id
            FROM teacher_class_assignments
            WHERE teacher_id = {$teacherId}
            AND status = 1
        )
    ";
}

$whereClause = implode(" AND ", $where);

$query = $conn->query("
    SELECT qsl.student_id
    FROM quiz_student_list qsl
    INNER JOIN students s
        ON qsl.student_id = s.id
    WHERE {$whereClause}
");

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $usedStudents[] = (int) $row['student_id'];
    }
}

/* =======================================================
   RESPONSE
======================================================= */
jsonResponse(1, 'Data siswa terpakai berhasil diambil.', [
    'used_students' => $usedStudents
]);
?>