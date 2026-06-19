<?php
/* =======================================================
    FILE: ajax/quiz/delete_quiz.php
    FINAL VERSION
    - Safe delete quiz
    - Admin & teacher compatible
    - Removes teacher distribution
    - Removes student distribution
    - Removes related questions
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
    TEACHER ACCESS VALIDATION
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
    CHECK QUIZ EXISTENCE
======================================================= */
$quizCheck = $conn->query("
    SELECT q.id
    FROM quiz_list q
    {$whereClause}
    LIMIT 1
");

if (!$quizCheck || $quizCheck->num_rows === 0) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Data kuis tidak ditemukan atau akses ditolak."
    ]);
    exit;
}

/* =======================================================
    TRANSACTION START
======================================================= */
$conn->begin_transaction();

try {

    /* =======================================================
        GET RELATED QUESTION IDS
    ======================================================= */
    $questionIds = [];

    $questionQuery = $conn->query("
        SELECT id
        FROM questions
        WHERE quiz_id = {$id}
    ");

    if ($questionQuery && $questionQuery->num_rows > 0) {
        while ($question = $questionQuery->fetch_assoc()) {
            $questionIds[] = (int) $question['id'];
        }
    }

    /* =======================================================
        DELETE QUESTION RELATIONS
    ======================================================= */
    if (!empty($questionIds)) {

        $questionIdList = implode(',', $questionIds);

        $conn->query("
            DELETE FROM question_opt
            WHERE question_id IN ({$questionIdList})
        ");

        $conn->query("
            DELETE FROM answers
            WHERE question_id IN ({$questionIdList})
        ");
    }

    /* =======================================================
        DELETE QUESTIONS
    ======================================================= */
    $deleteQuestions = $conn->query("
        DELETE FROM questions
        WHERE quiz_id = {$id}
    ");

    if (!$deleteQuestions) {
        throw new Exception("Gagal menghapus soal kuis.");
    }

    /* =======================================================
        DELETE STUDENT DISTRIBUTION
    ======================================================= */
    $deleteStudents = $conn->query("
        DELETE FROM quiz_student_list
        WHERE quiz_id = {$id}
    ");

    if (!$deleteStudents) {
        throw new Exception("Gagal menghapus distribusi siswa.");
    }

    /* =======================================================
        DELETE TEACHER DISTRIBUTION
    ======================================================= */
    $deleteTeachers = $conn->query("
        DELETE FROM quiz_teacher_list
        WHERE quiz_id = {$id}
    ");

    if (!$deleteTeachers) {
        throw new Exception("Gagal menghapus distribusi pengajar.");
    }

    /* =======================================================
        DELETE QUIZ
    ======================================================= */
    $deleteQuiz = $conn->query("
        DELETE FROM quiz_list
        WHERE id = {$id}
    ");

    if (!$deleteQuiz) {
        throw new Exception("Gagal menghapus kuis.");
    }

    /* =======================================================
        COMMIT
    ======================================================= */
    $conn->commit();

    echo json_encode([
        "status" => 1,
        "msg"    => "Kuis berhasil dihapus."
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => 0,
        "msg"    => $e->getMessage()
    ]);
}
?>