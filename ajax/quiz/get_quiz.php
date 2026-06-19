<?php
/* =======================================================
    FILE: ajax/quiz/get_quiz.php
    FINAL VERSION
    - Quiz berbasis teacher distribution
    - Tidak pakai subject/class manual
    - Support admin & teacher
    - Compatible with edit modal
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
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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
    FETCH QUIZ
======================================================= */
$quizQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_title,
        q.description,
        q.created_by,
        q.quiz_duration,
        q.status,
        q.open_at,
        q.due_date
    FROM quiz_list q
    {$whereClause}
    LIMIT 1
");

if (!$quizQuery || $quizQuery->num_rows === 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Data kuis tidak ditemukan."
    ]);
    exit;
}

$data = $quizQuery->fetch_assoc();

/* =======================================================
    FETCH ASSIGNED TEACHER + CLASS TARGETS
======================================================= */
$teacherClassIds = [];

$teacherClassQuery = $conn->query("
    SELECT
        teacher_id,
        class_id
    FROM quiz_teacher_list
    WHERE quiz_id = {$id}
");

if (!$teacherClassQuery) {

    echo json_encode([
        "status" => 0,
        "msg"    => "Gagal membaca distribusi pengajar."
    ]);
    exit;

}

while ($row = $teacherClassQuery->fetch_assoc()) {

    $teacherClassIds[] =
        (int)$row['teacher_id']
        . '|'
        . (int)$row['class_id'];

}

/* =======================================================
    RESPONSE
======================================================= */
echo json_encode([
    "status" => 1,
    "data"   => [
        "id"            => (int) $data['id'],
        "quiz_title"    => $data['quiz_title'],
        "description"   => $data['description'],
        "created_by"    => (int) $data['created_by'],
        "teacher_class_ids" => $teacherClassIds,
        "quiz_duration" => (int) $data['quiz_duration'],
        "status"        => (int) $data['status'],
        "open_at"       => $data['open_at'],
        "due_date"      => $data['due_date']
    ]
]);
?>