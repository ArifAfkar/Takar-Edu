<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

function jsonResponse($status, $message, $extra = [])
{
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
        'msg' => $message
    ], $extra));
    exit;
}

function normalizeShortAnswer($text)
{
    $text = strtolower(trim((string) $text));

    // Samakan desimal Indonesia dan internasional
    $text = str_replace(',', '.', $text);

    // Hapus tanda baca umum di awal/akhir jawaban
    $text = trim($text, " \t\n\r\0\x0B.?!;:");

    // Rapikan spasi berlebih
    $text = preg_replace('/\s+/', ' ', $text);

    return $text;
}

if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type']) ||
    (int) $_SESSION['login_user_type'] !== 3
) {
    jsonResponse(0, 'Akses ditolak.');
}

$userId = (int) $_SESSION['login_id'];

$quizId        = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : 0;
$studentId     = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
$quizStudentId = isset($_POST['quiz_student_id']) ? (int) $_POST['quiz_student_id'] : 0;
$autoSubmit    = isset($_POST['auto_submit']) ? (int) $_POST['auto_submit'] : 0;
$violationCount = isset($_POST['violation_count'])
    ? (int) $_POST['violation_count']
    : 0;

$submitReason = isset($_POST['submit_reason'])
    ? trim($_POST['submit_reason'])
    : 'manual';

$allowedReasons = ['manual', 'time_expired', 'violation_limit'];

if (!in_array($submitReason, $allowedReasons, true)) {
    $submitReason = 'manual';
}

if ($quizId <= 0 || $studentId <= 0 || $quizStudentId <= 0) {
    jsonResponse(0, 'Data ujian tidak valid.');
}

if (!isset($_POST['question_id']) || !is_array($_POST['question_id'])) {
    jsonResponse(0, 'Tidak ada jawaban yang dikirim.');
}

/* =======================================================
   VALIDASI ASSIGNMENT SISWA
======================================================= */
$assignStmt = $conn->prepare("
    SELECT qsl.id
    FROM quiz_student_list qsl
    INNER JOIN students s
        ON qsl.student_id = s.id
    WHERE qsl.id = ?
    AND qsl.quiz_id = ?
    AND qsl.student_id = ?
    AND s.user_id = ?
    AND qsl.status IN (1, 2)
    LIMIT 1
");

$assignStmt->bind_param(
    "iiii",
    $quizStudentId,
    $quizId,
    $studentId,
    $userId
);

$assignStmt->execute();
$assignResult = $assignStmt->get_result();

if (!$assignResult || $assignResult->num_rows <= 0) {
    jsonResponse(0, 'Akses ujian tidak valid.');
}

$assignStmt->close();

/* =======================================================
   CEK DUPLIKAT HISTORY
======================================================= */
$historyCheck = $conn->prepare("
    SELECT id
    FROM history
    WHERE quiz_student_id = ?
    LIMIT 1
");

$historyCheck->bind_param("i", $quizStudentId);
$historyCheck->execute();
$historyResult = $historyCheck->get_result();

if ($historyResult && $historyResult->num_rows > 0) {
    jsonResponse(0, 'Ujian sudah pernah dikumpulkan.');
}

$historyCheck->close();

$totalScoreAchieved = 0;
$totalPossiblePoints = 0;
$totalLikertScore = 0;
$totalLikertPoints = 0;

$conn->begin_transaction();

try {

    foreach ($_POST['question_id'] as $questionId => $dummyValue) {

        $questionId = (int) $questionId;

        if ($questionId <= 0) {
            continue;
        }

        $questionStmt = $conn->prepare("
            SELECT
                id,
                quiz_id,
                question_type,
                statement_type,
                points,
                answer_key_text
            FROM questions
            WHERE id = ?
            AND quiz_id = ?
            LIMIT 1
        ");

        $questionStmt->bind_param("ii", $questionId, $quizId);
        $questionStmt->execute();
        $questionResult = $questionStmt->get_result();

        if (!$questionResult || $questionResult->num_rows <= 0) {
            $questionStmt->close();
            continue;
        }

        $question = $questionResult->fetch_assoc();
        $questionStmt->close();

        $questionType = strtolower(trim($question['question_type']));
        $points = (float) $question['points'];

        if ($questionType === 'likert') {
            $totalLikertPoints += $points;
        } else {
            $totalPossiblePoints += $points;
        }

        $optionId = isset($_POST['option_id'][$questionId])
            ? (int) $_POST['option_id'][$questionId]
            : null;

        $answerText = isset($_POST['answer_text'][$questionId])
            ? trim($_POST['answer_text'][$questionId])
            : '';

        $isDoubtful = isset($_POST['is_doubtful'][$questionId])
            ? (int) $_POST['is_doubtful'][$questionId]
            : 0;

        $achievedScore = 0;
        $isRight = null;

        if ($questionType === 'likert') {

            $statementType = strtolower(trim($question['statement_type'] ?? 'positive'));
            $answerKey = strtolower(trim($answerText));

            $p = (float) $question['points'];

            $scoreMapPositive = [
                'sangat_setuju' => $p,
                'setuju' => max(0, $p - 1),
                'tidak_setuju' => max(0, $p - 2),
                'sangat_tidak_setuju' => max(0, $p - 3)
            ];

            $scoreMapNegative = [
                'sangat_setuju' => max(0, $p - 3),
                'setuju' => max(0, $p - 2),
                'tidak_setuju' => max(0, $p - 1),
                'sangat_tidak_setuju' => $p
            ];

            $scoreMap = $statementType === 'negative'
                ? $scoreMapNegative
                : $scoreMapPositive;

            if (isset($scoreMap[$answerKey])) {
                $achievedScore = $scoreMap[$answerKey];
            }

            $isRight = null;
            $optionId = null;
        }

        if (
            in_array($questionType, [
                'multiple_choice',
                'reasoned_multiple_choice'
            ]) &&
            $optionId
        ) {
            $isRight = 0;
            $optStmt = $conn->prepare("
                SELECT is_right, order_by
                FROM question_opt
                WHERE id = ?
                AND question_id = ?
                LIMIT 1
            ");

            $optStmt->bind_param("ii", $optionId, $questionId);
            $optStmt->execute();
            $optResult = $optStmt->get_result();

            if ($optResult && $optResult->num_rows > 0) {
                $opt = $optResult->fetch_assoc();

                if ($questionType === 'multiple_choice') {
                    if ((int) $opt['is_right'] === 1) {
                        $achievedScore = $points;
                        $isRight = 1;
                    }
                }

                if ($questionType === 'reasoned_multiple_choice') {
                    $isRight = (int) $opt['is_right'];
                    $achievedScore = 0;
                }
            }

            $optStmt->close();
        }

        if ($questionType === 'short_answer') {

            $correctAnswer = normalizeShortAnswer(
                $question['answer_key_text'] ?? ''
            );

            $studentAnswer = normalizeShortAnswer(
                $answerText
            );

            if (
                $correctAnswer !== '' &&
                $studentAnswer === $correctAnswer
            ) {
                $achievedScore = $points;
                $isRight = 1;
            } else {
                $achievedScore = 0;
                $isRight = 0;
            }
        }

        if ($questionType === 'essay') {
            $achievedScore = 0;
            $isRight = null;
        }

        if ($questionType === 'likert') {
            $totalLikertScore += $achievedScore;
        } else {
            $totalScoreAchieved += $achievedScore;
        }

        if (in_array($questionType, ['likert', 'essay'])) {
            $isRight = null;
        }

        $insertAnswer = $conn->prepare("
            INSERT INTO answers (
                student_id,
                quiz_id,
                question_id,
                option_id,
                answer_text,
                is_right,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $insertAnswer->bind_param(
            "iiiisi",
            $studentId,
            $quizId,
            $questionId,
            $optionId,
            $answerText,
            $isRight
        );

        if (!$insertAnswer->execute()) {
            throw new Exception('Gagal menyimpan jawaban: ' . $insertAnswer->error);
        }

        $insertAnswer->close();
    }

    $historyStmt = $conn->prepare("
        INSERT INTO history (
            quiz_student_id,
            final_score,
            max_score,
            submitted_at
        ) VALUES (
            ?, ?, ?, NOW()
        )
    ");

    $finalScoreForHistory = $totalPossiblePoints > 0
        ? $totalScoreAchieved
        : null;

    $maxScoreForHistory = $totalPossiblePoints > 0
        ? $totalPossiblePoints
        : null;

    $historyStmt->bind_param(
        "idd",
        $quizStudentId,
        $finalScoreForHistory,
        $maxScoreForHistory
    );

    if (!$historyStmt->execute()) {
        throw new Exception('Gagal menyimpan riwayat: ' . $historyStmt->error);
    }

    $historyStmt->close();

    $updateAssignment = $conn->prepare("
        UPDATE quiz_student_list
        SET
            status = 3,
            completed_at = NOW(),
            violation_count = ?,
            auto_submit = ?,
            submit_reason = ?,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");

    $updateAssignment->bind_param(
        "iisi",
        $violationCount,
        $autoSubmit,
        $submitReason,
        $quizStudentId
    );

    if (!$updateAssignment->execute()) {
        throw new Exception('Gagal memperbarui status ujian.');
    }

    $updateAssignment->close();

    $conn->commit();

    $pythonPath = 'python';
    $graderPath = realpath(__DIR__ . '/../../ai/ai_grader.py');

    if ($graderPath) {
        $cmd = escapeshellcmd($pythonPath) . ' ' .
            escapeshellarg($graderPath) . ' ' .
            escapeshellarg($studentId) . ' ' .
            escapeshellarg($quizId) .
            ' > ai_grader_output.txt 2>&1 &';

        exec($cmd);
    }

    jsonResponse(1, 'Jawaban berhasil dikumpulkan.', [
        'score' => $totalScoreAchieved . '/' . $totalPossiblePoints
    ]);

} catch (Exception $e) {

    $conn->rollback();

    jsonResponse(0, $e->getMessage());
}
?>