<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   ACCESS CONTROL
======================================================= */
if (!isset($_SESSION['login_id'])) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Akses ditolak.'
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

/* =======================================================
   INPUT
======================================================= */
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'ID distribusi tidak valid.'
    ]);
    exit;
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
        echo json_encode([
            'status' => 0,
            'msg' => 'Data pengajar tidak ditemukan.'
        ]);
        exit;
    }

    $teacherData = $teacherQuery->fetch_assoc();
    $teacherId   = (int) $teacherData['id'];
}

/* =======================================================
   VALIDATE TARGET DISTRIBUTION
======================================================= */
$where = ["qsl.id = {$id}"];

if ($isTeacher) {
    $where[] = "
        ql.created_by = {$userId}
    ";
}

$whereClause = implode(" AND ", $where);

$checkQuery = $conn->query("
    SELECT
        qsl.id,
        qsl.student_id,
        qsl.quiz_id,
        qsl.started_at,
        qsl.completed_at
    FROM quiz_student_list qsl

    INNER JOIN quiz_list ql
        ON qsl.quiz_id = ql.id

    INNER JOIN students s
        ON qsl.student_id = s.id

    WHERE {$whereClause}
    LIMIT 1
");

if (!$checkQuery || $checkQuery->num_rows <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Distribusi tidak ditemukan atau akses ditolak.'
    ]);
    exit;
}

$distribution = $checkQuery->fetch_assoc();

if (
    !empty($distribution['started_at']) ||
    !empty($distribution['completed_at'])
) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Distribusi tidak dapat dihapus karena siswa sudah mulai atau menyelesaikan kuis. Gunakan Nonaktifkan saja.'
    ]);
    exit;
}

/* =======================================================
   DELETE DISTRIBUTION
======================================================= */
$conn->begin_transaction();

try {

    $delete = $conn->query("
        DELETE FROM quiz_student_list
        WHERE id = {$id}
        LIMIT 1
    ");

    if (!$delete) {
        throw new Exception("Gagal menghapus distribusi.");
    }

    /* =======================================================
       OPTIONAL: HAPUS JAWABAN SISWA JIKA ADA
       (aktifkan jika tabel hasil siswa sudah tersedia)
    ======================================================= */
    /*
    $conn->query("
        DELETE FROM student_quiz_answers
        WHERE quiz_id = {$distribution['quiz_id']}
        AND student_id = {$distribution['student_id']}
    ");
    */

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'msg' => 'Distribusi siswa berhasil dihapus.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 0,
        'msg' => $e->getMessage()
    ]);
}