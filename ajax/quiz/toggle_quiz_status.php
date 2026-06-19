<?php
/* =======================================================
    FILE: ajax/quiz/toggle_quiz_status.php
    FINAL VERSION
    - Toggle status aktif / arsip
    - Admin & teacher compatible
    - Teacher only assigned quiz
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (!isset($_SESSION['login_id'])) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Unauthorized access."
    ]);
    exit;
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);

if (!$isAdmin && !$isTeacher) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Akses ditolak."
    ]);
    exit;
}

/* =======================================================
    VALIDATE INPUT
======================================================= */
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "ID kuis tidak valid."
    ]);
    exit;
}

/* =======================================================
    ROLE FILTER
======================================================= */
$whereClause = "WHERE q.id = {$id}";

if ($isTeacher) {

    $teacherQuery = $conn->query("
        SELECT id
        FROM teachers
        WHERE user_id = {$userId}
        LIMIT 1
    ");

    if (!$teacherQuery || $teacherQuery->num_rows === 0) {
        echo json_encode([
            "status" => 0,
            "msg"    => "Data pengajar tidak ditemukan."
        ]);
        exit;
    }

    $teacherData = $teacherQuery->fetch_assoc();
    $teacherId   = (int) $teacherData['id'];

    $whereClause .= "
        AND q.created_by = {$userId}
    ";
}

/* =======================================================
    GET CURRENT QUIZ STATUS
======================================================= */
$quizQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_title,
        q.status
    FROM quiz_list q
    {$whereClause}
    LIMIT 1
");

if (!$quizQuery || $quizQuery->num_rows === 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Kuis tidak ditemukan atau akses ditolak."
    ]);
    exit;
}

$quiz = $quizQuery->fetch_assoc();

$currentStatus = (int) $quiz['status'];

$currentStatus = (int) $quiz['status'];

$newStatus = isset($_POST['status'])
    ? (int) $_POST['status']
    : (($currentStatus === 1) ? 0 : 1);

if (!in_array($newStatus, [0, 1])) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Status kuis tidak valid."
    ]);
    exit;
}

$statusLabel = ($newStatus === 1)
    ? 'diaktifkan'
    : 'diarsipkan';

/* =======================================================
    UPDATE STATUS
======================================================= */
$update = $conn->query("
    UPDATE quiz_list
    SET
        status = {$newStatus},
        updated_at = NOW()
    WHERE id = {$id}
");

if (!$update) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Gagal memperbarui status kuis."
    ]);
    exit;
}

/* =======================================================
    RESPONSE
======================================================= */
echo json_encode([
    "status"      => 1,
    "msg"         => "Kuis berhasil {$statusLabel}.",
    "new_status"  => $newStatus,
    "quiz_title"  => $quiz['quiz_title']
]);
?>