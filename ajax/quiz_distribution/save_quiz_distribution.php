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
$id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$quizId     = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : 0;
$status     = isset($_POST['status']) ? (int) $_POST['status'] : 1;
$studentIds = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];

/* =======================================================
   VALIDATION
======================================================= */
if ($quizId <= 0) {
    echo json_encode([
        'status' => 2,
        'msg' => 'ID kuis tidak valid.'
    ]);
    exit;
}

if (empty($studentIds) || !is_array($studentIds)) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Minimal satu siswa wajib dipilih.'
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
   QUIZ ACCESS VALIDATION
======================================================= */
$quizCheckSql = "
    SELECT id, created_by
    FROM quiz_list
    WHERE id = {$quizId}
";

if ($isTeacher) {
    $quizCheckSql .= "
        AND created_by = {$userId}
    ";
}

$quizCheck = $conn->query($quizCheckSql);

if (!$quizCheck || $quizCheck->num_rows <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Kuis tidak ditemukan atau akses ditolak.'
    ]);
    exit;
}

/* =======================================================
   PREPARE STUDENT IDS
======================================================= */
$studentIds = array_map('intval', $studentIds);
$studentIds = array_unique($studentIds);

/* =======================================================
   STUDENT VALIDATION
======================================================= */
foreach ($studentIds as $studentId) {

    $studentCheckSql = "
        SELECT s.id
        FROM students s
        WHERE s.id = {$studentId}
    ";

    if ($isTeacher) {
        $studentCheckSql .= "
            AND s.class_id IN (
                SELECT class_id
                FROM teacher_class_assignments
                WHERE teacher_id = {$teacherId}
                AND status = 1
            )
        ";
    }

    $studentCheck = $conn->query($studentCheckSql);

    if (!$studentCheck || $studentCheck->num_rows <= 0) {
        echo json_encode([
            'status' => 2,
            'msg' => 'Siswa tidak valid atau di luar akses pengajar.'
        ]);
        exit;
    }
}

/* =======================================================
   CREATE MODE
======================================================= */
$conn->begin_transaction();

try {

    foreach ($studentIds as $studentId) {

        $duplicateCheck = $conn->query("
            SELECT id
            FROM quiz_student_list
            WHERE quiz_id = {$quizId}
            AND student_id = {$studentId}
            LIMIT 1
        ");

        if ($duplicateCheck && $duplicateCheck->num_rows > 0) {
            continue;
        }

        $insert = $conn->query("
            INSERT INTO quiz_student_list (
                quiz_id,
                student_id,
                status,
                assigned_at,
                updated_at
            ) VALUES (
                {$quizId},
                {$studentId},
                {$status},
                NOW(),
                NOW()
            )
        ");

        if (!$insert) {
            throw new Exception("Gagal menyimpan distribusi.");
        }
    }

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'msg' => 'Distribusi siswa berhasil disimpan.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 0,
        'msg' => $e->getMessage()
    ]);
}