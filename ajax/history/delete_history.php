<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (
    !isset($_SESSION['login_user_type']) ||
    (int)$_SESSION['login_user_type'] !== 1
) {
    echo json_encode([
        'status' => 0,
        'msg'    => 'Akses ditolak.'
    ]);
    exit;
}

/* =======================================================
    VALIDATE ID
======================================================= */
$historyId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($historyId <= 0) {

    echo json_encode([
        'status' => 0,
        'msg'    => 'ID riwayat tidak valid.'
    ]);

    exit;
}

/* =======================================================
    FETCH HISTORY
======================================================= */
$historyStmt = $conn->prepare("
    SELECT
        id,
        quiz_student_id
    FROM history
    WHERE id = ?
    LIMIT 1
");

$historyStmt->bind_param(
    "i",
    $historyId
);

$historyStmt->execute();

$historyResult =
    $historyStmt->get_result();

if (
    !$historyResult ||
    $historyResult->num_rows <= 0
) {

    echo json_encode([
        'status' => 0,
        'msg'    => 'Data riwayat tidak ditemukan.'
    ]);

    exit;
}

$history =
    $historyResult->fetch_assoc();

$quizStudentId =
    (int)$history['quiz_student_id'];

$historyStmt->close();

/* =======================================================
    TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    /* ---------------------------------------
       DELETE ANSWERS
    --------------------------------------- */
    $deleteAnswers =
        $conn->prepare("
            DELETE a
            FROM answers a
            INNER JOIN quiz_student_list qsl
                ON a.quiz_id = qsl.quiz_id
                AND a.student_id = qsl.student_id
            WHERE qsl.id = ?
        ");

    $deleteAnswers->bind_param(
        "i",
        $quizStudentId
    );

    if (
        !$deleteAnswers->execute()
    ) {
        throw new Exception(
            'Gagal menghapus jawaban.'
        );
    }

    $deleteAnswers->close();

    /* ---------------------------------------
    DELETE ANSWER EVALUATIONS
    --------------------------------------- */
    $deleteEvaluations = $conn->prepare("
        DELETE ae
        FROM answer_evaluations ae
        INNER JOIN answers a
            ON ae.answer_id = a.id
        INNER JOIN quiz_student_list qsl
            ON a.quiz_id = qsl.quiz_id
            AND a.student_id = qsl.student_id
        WHERE qsl.id = ?
    ");

    $deleteEvaluations->bind_param("i", $quizStudentId);

    if (!$deleteEvaluations->execute()) {
        throw new Exception('Gagal menghapus evaluasi jawaban.');
    }

    $deleteEvaluations->close();

    /* ---------------------------------------
       DELETE HISTORY
    --------------------------------------- */
    $deleteHistory =
        $conn->prepare("
            DELETE
            FROM history
            WHERE id = ?
        ");

    $deleteHistory->bind_param(
        "i",
        $historyId
    );

    if (
        !$deleteHistory->execute()
    ) {
        throw new Exception(
            'Gagal menghapus riwayat.'
        );
    }

    $deleteHistory->close();

    /* ---------------------------------------
       RESET QUIZ STATUS
    --------------------------------------- */
    $resetQuiz =
        $conn->prepare("
            UPDATE quiz_student_list
            SET
                status = 1,
                started_at = NULL,
                completed_at = NULL,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");

    $resetQuiz->bind_param(
        "i",
        $quizStudentId
    );

    if (
        !$resetQuiz->execute()
    ) {
        throw new Exception(
            'Gagal memperbarui status kuis.'
        );
    }

    $resetQuiz->close();

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'msg'    => 'Riwayat berhasil dihapus.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 0,
        'msg'    => $e->getMessage()
    ]);
}

exit;