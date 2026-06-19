<?php
/* =======================================================
   SAVE QUESTION AJAX
   File: ajax/question/save_question.php
   FINAL VERSION TAKAREDU (MASTER REFERENCE TABLE SYSTEM)

   SUPPORT:
   - Likert / Angket Positif-Negatif
   - Multiple Choice
   - Reasoned Multiple Choice
   - Short Answer
   - Essay
   - Visual Reference Table Builder
   - Wacana
   - PHET
   - Teacher/Admin Validation
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   HELPER RESPONSE
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
   INPUT
======================================================= */
$id            = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$quizId        = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
$question      = trim($_POST['question'] ?? '');
$questionType  = trim($_POST['question_type'] ?? '');
$statementType = trim($_POST['statement_type'] ?? '');
$points        = isset($_POST['points']) ? max(1, (int)$_POST['points']) : 1;

$wacanaId      = !empty($_POST['wacana_id']) ? (int)$_POST['wacana_id'] : null;
$phetId        = !empty($_POST['phet_id']) ? (int)$_POST['phet_id'] : null;

$textAnswer = trim($_POST['answer_key_text'] ?? '');
$textAnswer = ($textAnswer !== '') ? $textAnswer : null;

$useRubric = isset($_POST['use_rubric']) && $_POST['use_rubric'] == '1';

$rubricText = trim($_POST['rubric_text'] ?? '');

if ($questionType === 'essay' && $useRubric && $rubricText !== '') {
    $rubricText = $rubricText;
} else {
    $rubricText = null;
}

$answerTableConfigRaw = trim($_POST['answer_table_config'] ?? '');
$answerTableConfig = null;

if ($questionType === 'essay' && $answerTableConfigRaw !== '') {

    $decodedTableConfig = json_decode($answerTableConfigRaw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedTableConfig)) {
        jsonResponse(0, "Konfigurasi tabel tidak valid.");
    }

    $tableMode = $decodedTableConfig['mode'] ?? 'info';

    if (!in_array($tableMode, ['info', 'key_based', 'rubric_based'], true)) {
        jsonResponse(0, "Mode tabel tidak valid.");
    }

    $inputCells = $decodedTableConfig['input_cells'] ?? [];

    if (!is_array($inputCells)) {
        $inputCells = [];
    }

    if ($tableMode === 'key_based') {

        $validKeyCells = [];

        foreach ($inputCells as $cell) {
            $row = isset($cell['row']) ? (int)$cell['row'] : -1;
            $col = isset($cell['col']) ? (int)$cell['col'] : -1;
            $answer = trim($cell['answer'] ?? '');

            if ($row >= 0 && $col >= 0 && $answer !== '') {
                $validKeyCells[] = [
                    'row' => $row,
                    'col' => $col,
                    'answer' => $answer
                ];
            }
        }

        if (count($validKeyCells) <= 0) {
            jsonResponse(0, "Mode tabel berbasis kunci wajib memiliki minimal 1 kunci jawaban tabel.");
        }

        $decodedTableConfig['input_cells'] = $validKeyCells;
    }

    if ($tableMode === 'rubric_based') {

        $validRubricCells = [];

        foreach ($inputCells as $cell) {
            $row = isset($cell['row']) ? (int)$cell['row'] : -1;
            $col = isset($cell['col']) ? (int)$cell['col'] : -1;

            if ($row >= 0 && $col >= 0) {
                $validRubricCells[] = [
                    'row' => $row,
                    'col' => $col
                ];
            }
        }

        if (count($validRubricCells) <= 0) {
            jsonResponse(0, "Mode tabel berbasis rubrik wajib memiliki minimal 1 sel input siswa.");
        }

        if ($rubricText === null) {
            jsonResponse(0, "Mode tabel berbasis rubrik wajib menggunakan rubrik penilaian.");
        }

        $decodedTableConfig['input_cells'] = $validRubricCells;
    }

    $answerTableConfig = json_encode(
        $decodedTableConfig,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

$options       = $_POST['options'] ?? [];
$correctAnswer = isset($_POST['correct_answer']) ? (int)$_POST['correct_answer'] : null;

/* =======================================================
   VALID TYPES
======================================================= */
$allowedTypes = [
    'likert',
    'multiple_choice',
    'reasoned_multiple_choice',
    'short_answer',
    'essay'
];

if (
    $quizId <= 0 ||
    empty($question) ||
    !in_array($questionType, $allowedTypes)
) {
    jsonResponse(0, "Data soal tidak valid.");
}

/* =======================================================
   STATEMENT TYPE NORMALIZATION
   Only Likert / Angket uses statement_type
======================================================= */
if ($questionType === 'likert') {

    if (!in_array($statementType, ['positive', 'negative'])) {
        $statementType = 'positive';
    }

} else {

    $statementType = null;

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
        jsonResponse(0, "Data pengajar tidak ditemukan.");
    }

    $teacherData = $teacherResult->fetch_assoc();
    $teacherId   = (int)$teacherData['id'];

    $teacherStmt->close();
}

/* =======================================================
   QUIZ OWNERSHIP VALIDATION
======================================================= */
if ($isAdmin) {

    $quizStmt = $conn->prepare("
        SELECT id
        FROM quiz_list
        WHERE id = ?
        LIMIT 1
    ");

    $quizStmt->bind_param("i", $quizId);

} else {

    $quizStmt = $conn->prepare("
        SELECT id
        FROM quiz_list
        WHERE id = ?
        AND created_by = ?
        LIMIT 1
    ");

    $quizStmt->bind_param(
        "ii",
        $quizId,
        $userId
    );
}

$quizStmt->execute();
$quizResult = $quizStmt->get_result();

if (
    !$quizResult ||
    $quizResult->num_rows <= 0
) {
    jsonResponse(
        0,
        "Kuis tidak ditemukan atau akses ditolak."
    );
}

$quizStmt->close();

/* =======================================================
   OPTION VALIDATION
======================================================= */
$cleanOptions = [];

if (
    in_array(
        $questionType,
        ['multiple_choice', 'reasoned_multiple_choice']
    )
) {

    foreach ($options as $opt) {

        $opt = trim($opt);

        if (!empty($opt)) {
            $cleanOptions[] = $opt;
        }
    }

    if (count($cleanOptions) < 2) {
        jsonResponse(
            0,
            "Minimal 2 opsi jawaban diperlukan."
        );
    }

    if (
        $correctAnswer === null ||
        !isset($cleanOptions[$correctAnswer])
    ) {
        jsonResponse(
            0,
            "Jawaban benar tidak valid."
        );
    }
}

/* =======================================================
   LIKERT DEFAULT CONFIG
   Opsi angket tidak disimpan ke question_opt
======================================================= */
if ($questionType === 'likert') {
    $cleanOptions = [];
    $correctAnswer = null;
    $points = 4;
}

/* =======================================================
   TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    /* =======================================================
       INSERT NEW QUESTION
    ======================================================= */
    if ($id <= 0) {

        $orderStmt = $conn->prepare("
            SELECT COALESCE(MAX(order_by), 0) + 1 AS next_order
            FROM questions
            WHERE quiz_id = ?
        ");

        $orderStmt->bind_param("i", $quizId);
        $orderStmt->execute();

        $orderResult = $orderStmt->get_result();
        $orderData   = $orderResult->fetch_assoc();

        $nextOrder = (int)$orderData['next_order'];

        $orderStmt->close();

        $stmt = $conn->prepare("
            INSERT INTO questions (
                quiz_id,
                question,
                question_type,
                statement_type,
                points,
                answer_key_text,
                rubric_text,
                answer_table_config,
                wacana_id,
                phet_id,
                order_by,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");

        $stmt->bind_param(
            "isssisssiii",
            $quizId,
            $question,
            $questionType,
            $statementType,
            $points,
            $textAnswer,
            $rubricText,
            $answerTableConfig,
            $wacanaId,
            $phetId,
            $nextOrder
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Gagal menambahkan soal."
            );
        }

        $questionId = $stmt->insert_id;

        $stmt->close();
    }

    /* =======================================================
       UPDATE EXISTING QUESTION
    ======================================================= */
    else {

        if ($isAdmin) {

            $checkStmt = $conn->prepare("
                SELECT id
                FROM questions
                WHERE id = ?
                AND quiz_id = ?
                LIMIT 1
            ");

            $checkStmt->bind_param(
                "ii",
                $id,
                $quizId
            );

        } else {

            $checkStmt = $conn->prepare("
                SELECT q.id
                FROM questions q
                INNER JOIN quiz_list ql
                    ON q.quiz_id = ql.id
                WHERE q.id = ?
                AND q.quiz_id = ?
                AND ql.created_by = ?
                LIMIT 1
            ");

            $checkStmt->bind_param(
                "iii",
                $id,
                $quizId,
                $userId
            );
        }

        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();

        if (
            !$checkResult ||
            $checkResult->num_rows <= 0
        ) {
            throw new Exception(
                "Soal tidak ditemukan."
            );
        }

        $checkStmt->close();

        $stmt = $conn->prepare("
            UPDATE questions
            SET
                question = ?,
                question_type = ?,
                statement_type = ?,
                points = ?,
                answer_key_text = ?,
                rubric_text = ?,
                answer_table_config = ?,
                wacana_id = ?,
                phet_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssisssiii",
            $question,
            $questionType,
            $statementType,
            $points,
            $textAnswer,
            $rubricText,
            $answerTableConfig,
            $wacanaId,
            $phetId,
            $id
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "Gagal memperbarui soal."
            );
        }

        $stmt->close();

        $questionId = $id;

        /* DELETE OLD OPTIONS */
        $deleteStmt = $conn->prepare("
            DELETE FROM question_opt
            WHERE question_id = ?
        ");

        $deleteStmt->bind_param(
            "i",
            $questionId
        );

        if (!$deleteStmt->execute()) {
            throw new Exception(
                "Gagal menghapus opsi lama."
            );
        }

        $deleteStmt->close();
    }

    /* =======================================================
       SAVE OPTIONS
    ======================================================= */
    if (
        in_array(
            $questionType,
            [
                'multiple_choice',
                'reasoned_multiple_choice'
            ]
        )
    ) {

        $optStmt = $conn->prepare("
            INSERT INTO question_opt (
                question_id,
                order_by,
                option_text,
                is_right,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?, NOW(), NOW()
            )
        ");

        foreach (
            $cleanOptions as $index => $optionText
        ) {

            $isRight = 0;

            if (
                $questionType !== 'likert' &&
                $index === $correctAnswer
            ) {
                $isRight = 1;
            }

            $orderBy = $index + 1;

            $optStmt->bind_param(
                "iisi",
                $questionId,
                $orderBy,
                $optionText,
                $isRight
            );

            if (!$optStmt->execute()) {
                throw new Exception(
                    "Gagal menyimpan opsi jawaban."
                );
            }
        }

        $optStmt->close();
    }

    /* =======================================================
       COMMIT
    ======================================================= */
    $conn->commit();

    jsonResponse(
        1,
        ($id > 0)
            ? "Soal berhasil diperbarui."
            : "Soal berhasil ditambahkan."
    );

} catch (Exception $e) {

    $conn->rollback();

    jsonResponse(
        0,
        $e->getMessage()
    );
}
?>