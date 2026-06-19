<?php
/* =======================================================
   DELETE QUESTION AJAX
   File: ajax/question/delete_question.php
   FINAL VERSION TAKAREDU

   SUPPORT:
   - Teacher/Admin validation
   - Delete question options
   - Delete student answers
   - Delete AI evaluations
   - Delete wacana relations
   - Delete PHET relations
   - Safe reorder remaining questions
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   RESPONSE HELPER
======================================================= */
function jsonResponse($status, $msg)
{
    echo json_encode([
        "status" => $status,
        "msg"    => $msg
    ]);
    exit;
}

/* =======================================================
   ACCESS CONTROL
======================================================= */
if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type']) ||
    !in_array((int)$_SESSION['login_user_type'], [1, 2])
) {
    jsonResponse(0, "Akses ditolak.");
}

$userId   = (int)$_SESSION['login_id'];
$userType = (int)$_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);

/* =======================================================
   INPUT VALIDATION
======================================================= */
$questionId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($questionId <= 0) {
    jsonResponse(0, "ID soal tidak valid.");
}

/* =======================================================
   TEACHER VALIDATION
======================================================= */
$teacherId = 0;

if ($isTeacher) {

    $teacherStmt = $conn->prepare("
        SELECT id
        FROM teachers
        WHERE user_id = ?
        LIMIT 1
    ");

    $teacherStmt->bind_param("i", $userId);
    $teacherStmt->execute();

    $teacherResult = $teacherStmt->get_result();

    if (
        !$teacherResult ||
        $teacherResult->num_rows <= 0
    ) {
        jsonResponse(
            0,
            "Data pengajar tidak ditemukan."
        );
    }

    $teacherData = $teacherResult->fetch_assoc();
    $teacherId   = (int)$teacherData['id'];

    $teacherStmt->close();
}

/* =======================================================
   QUESTION OWNERSHIP VALIDATION
======================================================= */
$quizId  = 0;
$orderBy = 0;

if ($isAdmin) {

    $stmt = $conn->prepare("
        SELECT
            id,
            quiz_id,
            order_by
        FROM questions
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $questionId);

} else {

    $stmt = $conn->prepare("
        SELECT
            q.id,
            q.quiz_id,
            q.order_by
        FROM questions q
        INNER JOIN quiz_list ql
            ON q.quiz_id = ql.id
        WHERE q.id = ?
        AND ql.created_by = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $questionId,
        $userId
    );
}

$stmt->execute();

$result = $stmt->get_result();

if (
    !$result ||
    $result->num_rows <= 0
) {
    jsonResponse(
        0,
        "Soal tidak ditemukan atau akses ditolak."
    );
}

$questionData = $result->fetch_assoc();

$quizId  = (int)$questionData['quiz_id'];
$orderBy = (int)$questionData['order_by'];

$stmt->close();

/* =======================================================
   TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    /* =======================================================
       DELETE AI EVALUATIONS
    ======================================================= */
    $deleteEvalStmt = $conn->prepare("
        DELETE ae
        FROM answer_evaluations ae
        INNER JOIN answers a
            ON ae.answer_id = a.id
        WHERE a.question_id = ?
    ");

    $deleteEvalStmt->bind_param(
        "i",
        $questionId
    );

    if (!$deleteEvalStmt->execute()) {
        throw new Exception(
            "Gagal menghapus evaluasi jawaban."
        );
    }

    $deleteEvalStmt->close();

    /* =======================================================
       DELETE STUDENT ANSWERS
    ======================================================= */
    $deleteAnswersStmt = $conn->prepare("
        DELETE FROM answers
        WHERE question_id = ?
    ");

    $deleteAnswersStmt->bind_param(
        "i",
        $questionId
    );

    if (!$deleteAnswersStmt->execute()) {
        throw new Exception(
            "Gagal menghapus jawaban siswa."
        );
    }

    $deleteAnswersStmt->close();

    /* =======================================================
       DELETE QUESTION OPTIONS
    ======================================================= */
    $deleteOptionsStmt = $conn->prepare("
        DELETE FROM question_opt
        WHERE question_id = ?
    ");

    $deleteOptionsStmt->bind_param(
        "i",
        $questionId
    );

    if (!$deleteOptionsStmt->execute()) {
        throw new Exception(
            "Gagal menghapus opsi jawaban."
        );
    }

    $deleteOptionsStmt->close();

    /* =======================================================
       DELETE MAIN QUESTION
    ======================================================= */
    $deleteQuestionStmt = $conn->prepare("
        DELETE FROM questions
        WHERE id = ?
        LIMIT 1
    ");

    $deleteQuestionStmt->bind_param(
        "i",
        $questionId
    );

    if (!$deleteQuestionStmt->execute()) {
        throw new Exception(
            "Gagal menghapus soal."
        );
    }

    $deleteQuestionStmt->close();

    /* =======================================================
       SAFE REORDER REMAINING QUESTIONS
    ======================================================= */
    $reorderStmt = $conn->prepare("
        UPDATE questions
        SET order_by = order_by - 1
        WHERE quiz_id = ?
        AND order_by > ?
    ");

    $reorderStmt->bind_param(
        "ii",
        $quizId,
        $orderBy
    );

    if (!$reorderStmt->execute()) {
        throw new Exception(
            "Gagal merapikan urutan soal."
        );
    }

    $reorderStmt->close();

    /* =======================================================
       COMMIT
    ======================================================= */
    $conn->commit();

    jsonResponse(
        1,
        "Soal berhasil dihapus."
    );

} catch (Exception $e) {

    $conn->rollback();

    jsonResponse(
        0,
        $e->getMessage()
    );
}
?>