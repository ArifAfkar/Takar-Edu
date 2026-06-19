<?php
/* =======================================================
   GET QUESTION AJAX
   File: ajax/question/get_question.php
   FINAL VERSION TAKAREDU (MASTER REFERENCE TABLE SYSTEM)

   SUPPORT:
   - Likert / Angket
   - Multiple Choice
   - Reasoned Multiple Choice
   - Short Answer
   - Essay
   - Visual Reference Table Builder
   - Wacana
   - PHET
   - Teacher Access Control
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   RESPONSE HELPER
======================================================= */
function jsonResponse($status, $msg, $data = null)
{
    echo json_encode([
        "status" => $status,
        "msg"    => $msg,
        "data"   => $data
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
   VALIDATE INPUT
======================================================= */
$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
   MAIN QUESTION QUERY
======================================================= */
if ($isAdmin) {

    $stmt = $conn->prepare("
        SELECT
            q.id,
            q.quiz_id,
            q.question,
            q.question_type,
            q.statement_type,
            q.points,
            q.answer_key_text,
            q.rubric_text,
            q.answer_table_config,
            q.wacana_id,
            q.phet_id,
            q.order_by,
            q.created_at,
            q.updated_at
        FROM questions q
        WHERE q.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $questionId);

} else {

    $stmt = $conn->prepare("
        SELECT
            q.id,
            q.quiz_id,
            q.question,
            q.question_type,
            q.statement_type,
            q.points,
            q.answer_key_text,
            q.rubric_text,
            q.answer_table_config,
            q.wacana_id,
            q.phet_id,
            q.order_by,
            q.created_at,
            q.updated_at
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

$question = $result->fetch_assoc();

$stmt->close();

/* =======================================================
   OPTIONS
======================================================= */
$options = [];
$correctAnswerIndex = null;

if (
    in_array(
        $question['question_type'],
        [
            'multiple_choice',
            'reasoned_multiple_choice'
        ]
    )
) {

    $optStmt = $conn->prepare("
        SELECT
            id,
            option_text,
            is_right,
            order_by
        FROM question_opt
        WHERE question_id = ?
        ORDER BY order_by ASC, id ASC
    ");

    $optStmt->bind_param(
        "i",
        $questionId
    );

    $optStmt->execute();

    $optResult = $optStmt->get_result();

    $index = 0;

    while ($row = $optResult->fetch_assoc()) {

        $options[] = [
            "id"          => (int)$row['id'],
            "option_text" => $row['option_text'],
            "is_right"    => (int)$row['is_right'],
            "order_by"    => (int)$row['order_by']
        ];

        if ((int)$row['is_right'] === 1) {
            $correctAnswerIndex = $index;
        }

        $index++;
    }

    $optStmt->close();
}

if ($question['question_type'] === 'likert') {
    $options = [
        [
            "id" => null,
            "option_text" => "Sangat Setuju",
            "is_right" => 0,
            "order_by" => 1
        ],
        [
            "id" => null,
            "option_text" => "Setuju",
            "is_right" => 0,
            "order_by" => 2
        ],
        [
            "id" => null,
            "option_text" => "Tidak Setuju",
            "is_right" => 0,
            "order_by" => 3
        ],
        [
            "id" => null,
            "option_text" => "Sangat Tidak Setuju",
            "is_right" => 0,
            "order_by" => 4
        ]
    ];

    $correctAnswerIndex = null;
}

/* =======================================================
   WACANA TABLE EXTRACTION
======================================================= */
$wacanaTables = [];

if (!empty($question['wacana_id'])) {

    $wacanaStmt = $conn->prepare("
        SELECT description
        FROM wacana
        WHERE id = ?
        LIMIT 1
    ");

    $wacanaStmt->bind_param(
        "i",
        $question['wacana_id']
    );

    $wacanaStmt->execute();

    $wacanaResult = $wacanaStmt->get_result();

    if ($wacanaResult && $wacanaResult->num_rows > 0) {

        $wacanaData = $wacanaResult->fetch_assoc();
        $description = $wacanaData['description'] ?? '';

        if (!empty($description)) {

            preg_match_all(
                '/<table\b[^>]*>.*?<\/table>/is',
                $description,
                $matches
            );

            if (!empty($matches[0])) {
                $wacanaTables = $matches[0];
            }

        }

    }

    $wacanaStmt->close();
}

/* =======================================================
   SCORE SYSTEM CONFIG
======================================================= */
$defaultScoring = [];

switch ($question['question_type']) {

    case 'likert':

        if (
            $question['statement_type'] === 'negative'
        ) {

            $defaultScoring = [
                "sangat_setuju"       => 1,
                "setuju"              => 2,
                "tidak_setuju"        => 3,
                "sangat_tidak_setuju" => 4
            ];

        } else {

            $defaultScoring = [
                "sangat_setuju"       => 4,
                "setuju"              => 3,
                "tidak_setuju"        => 2,
                "sangat_tidak_setuju" => 1
            ];
        }

        break;

    case 'reasoned_multiple_choice':

        $defaultScoring = [
            "wrong_option" => 0,
            "reasoning" => [
                "salah"  => 1,
                "kurang" => 2,
                "benar"  => 3
            ]
        ];

        break;

    case 'essay':

        $defaultScoring = [
            "salah"  => 0,
            "kurang" => 1,
            "cukup"  => 2,
            "benar"  => 3
        ];

        break;

    default:

        $defaultScoring = [
            "correct" => (int)$question['points'],
            "wrong"   => 0
        ];

        break;
}

/* =======================================================
   FINAL RESPONSE
======================================================= */
jsonResponse(
    1,
    "Data soal berhasil diambil.",
    [
        "id"                   => (int)$question['id'],
        "quiz_id"              => (int)$question['quiz_id'],
        "question"             => $question['question'],
        "question_type"        => $question['question_type'],
        "statement_type"       => $question['statement_type'] ?? '',
        "points"               => (int)$question['points'],
        "answer_key_text"      => $question['answer_key_text'] ?? '',
        "rubric_text"          => $question['rubric_text'] ?? '',
        "answer_table_config"  => $question['answer_table_config'] ?? '',
        "wacana_tables"        => $wacanaTables,
        "wacana_id"            => !empty($question['wacana_id'])
                                    ? (int)$question['wacana_id']
                                    : "",
        "phet_id"              => !empty($question['phet_id'])
                                    ? (int)$question['phet_id']
                                    : "",
        "order_by"             => (int)$question['order_by'],
        "options"              => $options,
        "correct_answer_index" => $correctAnswerIndex,
        "default_scoring"      => $defaultScoring,
        "created_at"           => $question['created_at'],
        "updated_at"           => $question['updated_at']
    ]
);
?>