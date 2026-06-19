<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */

/* ---------- Required Files ---------- */
require_once '../config/auth.php';
require_once '../config/db_connect.php';

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type'])
) {
    header('Location: ../index.php');
    exit;
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);
$isStudent = ($userType === 3);

if (!$isAdmin && !$isTeacher && !$isStudent) {
    header('Location: home.php');
    exit;
}

/* =======================================================
    REQUEST VALIDATION
======================================================= */
$quizId = isset($_GET['quiz_id'])
    ? (int) $_GET['quiz_id']
    : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

$targetStudentId = isset($_GET['student_id'])
    ? (int) $_GET['student_id']
    : 0;

if ($quizId <= 0) {
    header('Location: history.php');
    exit;
}

/* =======================================================
    STUDENT INFORMATION
======================================================= */
if ($isStudent) {

    $studentStmt = $conn->prepare("
        SELECT
            s.id AS student_id,
            s.class_id,
            c.class_name,
            u.name AS student_name
        FROM students s
        INNER JOIN users u
            ON s.user_id = u.id
        LEFT JOIN classes c
            ON s.class_id = c.id
        WHERE s.user_id = ?
        LIMIT 1
    ");

    $studentStmt->bind_param("i", $userId);

} else {

    if ($targetStudentId <= 0) {
        header('Location: history.php');
        exit;
    }

    $studentStmt = $conn->prepare("
        SELECT
            s.id AS student_id,
            s.class_id,
            c.class_name,
            u.name AS student_name
        FROM students s
        INNER JOIN users u
            ON s.user_id = u.id
        LEFT JOIN classes c
            ON s.class_id = c.id
        WHERE s.id = ?
        LIMIT 1
    ");

    $studentStmt->bind_param("i", $targetStudentId);
}

$studentStmt->execute();
$studentResult = $studentStmt->get_result();

if (!$studentResult || $studentResult->num_rows <= 0) {
    header('Location: history.php');
    exit;
}

$student = $studentResult->fetch_assoc();
$studentId = (int) $student['student_id'];

/* =======================================================
    QUIZ RESULT INFORMATION
======================================================= */
$dataStmt = $conn->prepare("
    SELECT
        q.id AS quiz_id,
        q.quiz_title,
        q.description,
        q.created_by,

        (
            SELECT GROUP_CONCAT(
                DISTINCT s.subject_name
                ORDER BY s.subject_name ASC
                SEPARATOR ', '
            )
            FROM quiz_teacher_list qtl
            INNER JOIN teachers t
                ON qtl.teacher_id = t.id
            INNER JOIN subjects s
                ON t.subject_id = s.id
            WHERE qtl.quiz_id = q.id
        ) AS subject_name,

        qsl.id AS quiz_student_id,
        qsl.started_at,
        qsl.completed_at,
        qsl.violation_count,
        qsl.submit_reason,

        h.final_score,
        h.max_score,
        h.submitted_at

    FROM quiz_student_list qsl

    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id

    INNER JOIN history h
        ON h.quiz_student_id = qsl.id

    WHERE q.id = ?
    AND qsl.student_id = ?
    LIMIT 1
");


$dataStmt->bind_param("ii", $quizId, $studentId);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();

/* =======================================================
    ACCESS VALIDATION
======================================================= */
$accessError = null;
$errorTitle  = 'Informasi';
$errorIcon   = 'warning';

if (!$dataResult || $dataResult->num_rows <= 0) {
    $accessError = 'Riwayat pengerjaan kuis tidak ditemukan.';
} else {
    $resultData = $dataResult->fetch_assoc();
    $quizStudentId = (int) $resultData['quiz_student_id'];

    if (
        $isTeacher &&
        (int)$resultData['created_by'] !== $userId
    ) {
        $accessError = 'Anda tidak memiliki akses ke hasil kuis ini.';
        $errorTitle  = 'Akses Ditolak';
        $errorIcon   = 'error';
    }
}

/* =======================================================
    SCORE CALCULATION
======================================================= */

$totalPointEarned = (float) ($resultData['final_score'] ?? 0);
$totalPointMax    = (float) ($resultData['max_score'] ?? 0);

$finalGrade = $totalPointMax > 0
    ? round(($totalPointEarned / $totalPointMax) * 100, 2)
    : 0;

$isOnlyQuestionnaire = empty($resultData['max_score']);
$canSeeQuestionnaireScore = $isAdmin || $isTeacher;

/* =======================================================
    DISPLAY HELPERS
======================================================= */

/* ---------- HTML Escape ---------- */
function e($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/* ---------- Smart Number Format ---------- */
function formatSmartNumber($number)
{
    if ($number === null || $number === '') {
        return '-';
    }

    return rtrim(rtrim(number_format((float)$number, 2, '.', ''), '0'), '.');
}

/* ---------- Math Text Normalization ---------- */
function normalizeMathText($text)
{
    if (empty($text)) {
        return '';
    }

    return str_replace(
        ['\\\\(', '\\\\)', '\\\\[', '\\\\]'],
        ['\\(', '\\)', '\\[', '\\]'],
        $text
    );
}

/* ---------- Date Time Formatter ---------- */
function formatDateTimeWITA($datetime)
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return '-';
    }

    return date('d/m/Y H:i:s', $timestamp) . ' WITA';
}

/* ---------- Question Type Label ---------- */
function getQuestionTypeLabel($type)
{
    $type = strtolower(trim((string)$type));

    $labels = [
        'likert'                   => 'Angket',
        'multiple_choice'          => 'Pilihan Ganda',
        'reasoned_multiple_choice' => 'Pilihan Ganda Beralasan',
        'short_answer'             => 'Isian Singkat',
        'essay'                    => 'Uraian'
    ];

    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

/* ---------- Student Answer Table Renderer ---------- */
function renderStudentAnswerTable($question, $tableAnswer)
{
    if (
        empty($question['answer_table_config']) ||
        empty($question['wacana_description']) ||
        !is_array($tableAnswer)
    ) {
        return '';
    }

    $config = json_decode($question['answer_table_config'], true);

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($config) ||
        empty($config['input_cells']) ||
        !is_array($config['input_cells'])
    ) {
        return '';
    }

    preg_match_all(
        '/<table\b[^>]*>.*?<\/table>/is',
        $question['wacana_description'],
        $matches
    );

    if (empty($matches[0])) {
        return '';
    }

    $tableIndex = isset($config['table_index'])
        ? (int)$config['table_index']
        : 0;

    if (!isset($matches[0][$tableIndex])) {
        return '';
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    $dom->loadHTML(
        '<?xml encoding="UTF-8">' . $matches[0][$tableIndex],
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();

    $rows = $dom->getElementsByTagName('tr');

    foreach ($config['input_cells'] as $cell) {

        $rowIndex = isset($cell['row']) ? (int)$cell['row'] : -1;
        $colIndex = isset($cell['col']) ? (int)$cell['col'] : -1;

        if ($rowIndex < 0 || $colIndex < 0) {
            continue;
        }

        $key = "r{$rowIndex}_c{$colIndex}";

        if (!array_key_exists($key, $tableAnswer)) {
            continue;
        }

        $studentValue = trim((string)$tableAnswer[$key]);

        $row = $rows->item($rowIndex);

        if (!$row) {
            continue;
        }

        $cells = [];

        foreach ($row->childNodes as $child) {
            if (in_array(strtolower($child->nodeName), ['td', 'th'], true)) {
                $cells[] = $child;
            }
        }

        if (!isset($cells[$colIndex])) {
            continue;
        }

        $targetCell = $cells[$colIndex];

        while ($targetCell->firstChild) {
            $targetCell->removeChild($targetCell->firstChild);
        }

        $targetCell->appendChild(
            $dom->createTextNode($studentValue)
        );

        $oldClass = $targetCell->getAttribute('class');

        $colorClass = 'bg-blue-50 text-blue-700 font-semibold';

        $targetCell->setAttribute(
            'class',
            trim($oldClass . ' ' . $colorClass)
        );
    }

    $table = $dom->getElementsByTagName('table')->item(0);

    if (!$table) {
        return '';
    }

    return '
        <div class="overflow-x-auto">
            <div class="inline-block min-w-full">
                ' . $dom->saveHTML($table) . '
            </div>
        </div>
    ';
}

/* ---------- Answer Renderer ---------- */
function renderAnswerText($text, $question = null)
{
    if ($text === null || trim($text) === '') {
        return '<span class="italic text-gray-400">Tidak menjawab</span>';
    }

    $decoded = json_decode($text, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

        $html = '';

        if (!empty($decoded['text_answer'])) {
            $html .= '<div class="mb-4">';
            $html .= '<div class="text-xs font-semibold text-gray-500 mb-1">Jawaban teks:</div>';
            $html .= '<div class="math-content">';
            $html .= nl2br(
                normalizeMathText(
                    e($decoded['text_answer'])
                )
            );
            $html .= '</div>';
            $html .= '</div>';
        }

        if (!empty($decoded['table_answer']) && is_array($decoded['table_answer'])) {

            $html .= '<div class="mt-4">';
            $html .= '<div class="text-xs font-semibold text-gray-500 mb-2">';
            $html .= 'Jawaban tabel:';
            $html .= '</div>';

            $tableHtml = $question
                ? renderStudentAnswerTable($question, $decoded['table_answer'])
                : '';

            if ($tableHtml !== '') {
                $html .= '<div class="student-answer-table">';
                $html .= $tableHtml;
                $html .= '</div>';
            } else {
                $html .= '<div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-green-700">';
                $html .= count($decoded['table_answer']);
                $html .= ' sel tabel telah diisi.';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        return $html !== ''
            ? $html
            : '<span class="italic text-gray-400">Tidak menjawab</span>';
    }

    return '<div class="math-content">' .
        nl2br(
            normalizeMathText(
                e($text)
            )
        ) .
    '</div>';
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <!-- =======================================================
        GLOBAL HEAD CONFIGURATION
    ======================================================= -->
    <?php require_once '../includes/header.php'; ?>

    <title>
        Hasil Kuis<?= empty($accessError) ? ' - ' . e($resultData['quiz_title']) : '' ?>
    </title>
</head>

<body>

    <!-- =======================================================
        GLOBAL NAVBAR + SIDEBAR
    ======================================================= -->
    <?php require_once '../includes/nav_bar.php'; ?>

    <!-- =======================================================
        MAIN CONTENT AREA
    ======================================================= -->
    <main id="mainContent" class="main-content">

        <!-- =======================================================
            PAGE CONTAINER
        ======================================================= -->
        <div class="page-container">

        <?php if (empty($accessError)): ?>

            <!-- =======================================================
                RESULT HEADER SECTION
            ======================================================= -->
            <section class="result-header-card">

                <h1 class="page-title">
                    <?= e($resultData['quiz_title']) ?>
                </h1>

                <!-- ---------- Student Information ---------- -->
                <div class="mt-3">

                    <table class="text-sm text-gray-600">
                        <tr>
                            <td class="pr-4 py-1 whitespace-nowrap">
                                Nama Siswa
                            </td>
                            <td class="pr-4 py-1">
                                :
                            </td>
                            <td class="py-1 font-semibold text-gray-800">
                                <?= e($student['student_name']) ?>
                            </td>
                        </tr>

                        <tr>
                            <td class="pr-4 py-1 whitespace-nowrap">
                                Kelas
                            </td>
                            <td class="pr-4 py-1">
                                :
                            </td>
                            <td class="py-1 font-semibold text-gray-800">
                                <?= e($student['class_name'] ?? '-') ?>
                            </td>
                        </tr>

                        <tr>
                            <td class="pr-4 py-1 whitespace-nowrap">
                                Mata Pelajaran
                            </td>
                            <td class="pr-4 py-1">
                                :
                            </td>
                            <td class="py-1 font-semibold text-gray-800">
                                <?php
                                $subjects = array_filter(
                                    array_map('trim', explode(',', $resultData['subject_name'] ?? ''))
                                );

                                if (count($subjects) === 0) {

                                    $subjectText = '-';

                                } elseif (count($subjects) === 1) {

                                    $subjectText = $subjects[0];

                                } elseif (count($subjects) === 2) {

                                    $subjectText = implode(' & ', $subjects);

                                } else {

                                    $lastSubject = array_pop($subjects);

                                    $subjectText = implode(', ', $subjects)
                                        . ', & '
                                        . $lastSubject;
                                }
                                ?>

                                <?= e($subjectText) ?>
                            </td>
                        </tr>
                    </table>

                </div>

                <!-- ---------- PDF Export ---------- -->
                <div class="mt-4">
                    <a
                        href="download_answer.php?quiz_id=<?= (int)$quizId ?>&student_id=<?= (int)$studentId ?>"
                        target="_blank"
                        class="form-btn form-btn-danger"
                    >
                        Download PDF
                    </a>
                </div>

                <!-- ---------- Result Statistics ---------- -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">

                    <?php if (!$isOnlyQuestionnaire): ?>

                        <!-- Score summary -->
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <p class="text-sm text-blue-600 font-medium">
                                Poin Diperoleh
                            </p>

                            <h2 class="text-3xl font-bold text-blue-700 mt-1">
                                <?= number_format($totalPointEarned) ?>
                                /
                                <?= number_format($totalPointMax) ?>
                            </h2>
                        </div>

                        <!-- Final grade -->
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                            <p class="text-sm text-emerald-600 font-medium">
                                Nilai Akhir
                            </p>

                            <h2 class="text-3xl font-bold text-emerald-700 mt-1">
                                <?= formatSmartNumber($finalGrade) ?>
                            </h2>

                            <p class="text-xs text-emerald-600 mt-1">
                                dari 100
                            </p>
                        </div>

                    <?php endif; ?>

                    <!-- Start time -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <p class="text-sm text-blue-600 font-medium">Mulai</p>

                        <h2 class="text-lg font-semibold text-blue-700 mt-1">
                            <?= date('d/m/Y', strtotime($resultData['started_at'])) ?>
                        </h2>

                        <p class="text-sm text-blue-600 mt-1">
                            <?= date('H:i:s', strtotime($resultData['started_at'])) ?> WITA
                        </p>
                    </div>

                    <!-- Submit time -->
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                        <p class="text-sm text-green-600 font-medium">Dikumpulkan</p>

                        <h2 class="text-lg font-semibold text-green-700 mt-1">
                            <?= date('d/m/Y', strtotime($resultData['submitted_at'])) ?>
                        </h2>

                        <p class="text-sm text-green-600 mt-1">
                            <?= date('H:i:s', strtotime($resultData['submitted_at'])) ?> WITA
                        </p>
                    </div>

                    <!-- Violation summary -->
                    <?php
                    $violationCount = (int)($resultData['violation_count'] ?? 0);

                    if ($violationCount <= 0) {

                        $cardClass  = 'bg-green-50 border-green-200';
                        $titleClass = 'text-green-600';
                        $valueClass = 'text-green-700';

                    } elseif ($violationCount === 1) {

                        $cardClass  = 'bg-blue-50 border-blue-200';
                        $titleClass = 'text-blue-600';
                        $valueClass = 'text-blue-700';

                    } elseif ($violationCount === 2) {

                        $cardClass  = 'bg-yellow-50 border-yellow-200';
                        $titleClass = 'text-yellow-600';
                        $valueClass = 'text-yellow-700';

                    } else {

                        $cardClass  = 'bg-red-50 border-red-200';
                        $titleClass = 'text-red-600';
                        $valueClass = 'text-red-700';

                    }
                    ?>

                        <div class="<?= $cardClass ?> border rounded-xl p-4">

                            <p class="text-sm font-medium <?= $titleClass ?>">
                                Pelanggaran
                            </p>

                            <h2 class="text-3xl font-bold mt-1 <?= $valueClass ?>">
                                <?= $violationCount ?> / 3
                            </h2>

                            <p class="text-xs mt-1 <?= $titleClass ?>">
                                Jumlah pelanggaran selama pengerjaan
                            </p>

                        </div>

                    <!-- Auto submit status -->
                    <?php if (($resultData['submit_reason'] ?? '') === 'time_expired'): ?>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                            <p class="text-sm text-yellow-600 font-medium">Status Pengumpulan</p>

                            <h2 class="text-base font-bold text-yellow-700 mt-1 leading-relaxed">
                                Waktu ujian habis
                            </h2>

                            <p class="text-xs text-yellow-600 mt-1">
                                Jawaban dikumpulkan otomatis oleh sistem
                            </p>
                        </div>

                    <?php elseif (($resultData['submit_reason'] ?? '') === 'violation_limit'): ?>

                        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                            <p class="text-sm text-red-600 font-medium">Status Pengumpulan</p>

                            <h2 class="text-base font-bold text-red-700 mt-1 leading-relaxed">
                                Dihentikan karena batas pelanggaran
                            </h2>

                            <p class="text-xs text-red-600 mt-1">
                                Ujian dikumpulkan otomatis oleh sistem
                            </p>
                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <!-- =======================================================
                ANSWER REVIEW SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Title ---------- -->
                <h2 class="section-title border-b border-gray-200 pb-4 mb-6">
                    Rincian Jawaban
                </h2>

                <!-- ---------- Question Data Query ---------- -->
                <?php
                $questionStmt = $conn->prepare("
                    SELECT
                        q.*,
                        w.wacana_title,
                        w.description AS wacana_description,
                        p.phet_title,
                        p.iframe_phet
                    FROM questions q
                    LEFT JOIN wacana w
                        ON q.wacana_id = w.id
                    LEFT JOIN phet p
                        ON q.phet_id = p.id
                    WHERE q.quiz_id = ?
                    ORDER BY q.order_by ASC, q.id ASC
                ");

                $questionStmt->bind_param("i", $quizId);
                $questionStmt->execute();
                $questionResult = $questionStmt->get_result();

                $no = 1;

                while ($question = $questionResult->fetch_assoc()):

                    $questionId = (int) $question['id'];
                    $questionType = strtolower(trim($question['question_type']));

                    $answerStmt = $conn->prepare("
                        SELECT *
                        FROM answers
                        WHERE student_id = ?
                        AND quiz_id = ?
                        AND question_id = ?
                        LIMIT 1
                    ");

                    $answerStmt->bind_param(
                        "iii",
                        $studentId,
                        $quizId,
                        $questionId
                    );

                    $answerStmt->execute();
                    $answerResult = $answerStmt->get_result();

                    $studentAnswer = $answerResult && $answerResult->num_rows > 0
                        ? $answerResult->fetch_assoc()
                        : null;

                    $earnedScore = 0;
                    $aiEvaluation = null;

                    if ($studentAnswer) {

                        if (in_array($questionType, ['essay', 'reasoned_multiple_choice'])) {

                            $evalStmt = $conn->prepare("
                                SELECT
                                    category,
                                    score
                                FROM answer_evaluations
                                WHERE answer_id = ?
                                LIMIT 1
                            ");

                            $evalStmt->bind_param("i", $studentAnswer['id']);
                            $evalStmt->execute();
                            $evalResult = $evalStmt->get_result();

                            if ($evalResult && $evalResult->num_rows > 0) {
                                $aiEvaluation = $evalResult->fetch_assoc();
                                $earnedScore = (float) $aiEvaluation['score'];
                            }

                            $evalStmt->close();
                        }

                        if (!$aiEvaluation) {

                            if ($questionType === 'likert') {

                                $answerKey = strtolower(trim($studentAnswer['answer_text'] ?? ''));
                                $statementType = strtolower(trim($question['statement_type'] ?? 'positive'));
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

                                $earnedScore = $scoreMap[$answerKey] ?? 0;

                            } elseif ((int)($studentAnswer['is_right'] ?? 0) === 1) {

                                $earnedScore = (float) $question['points'];

                            }
                        }
                    }
                ?>

                <!-- =======================================================
                    QUESTION REVIEW ITEM
                ======================================================= -->
                <div class="review-question">

                    <!-- ---------- Question Information ---------- -->
                    <div class="mb-4">

                        <div class="flex items-start gap-3">

                            <div class="question-number">
                                <?= $no++ ?>
                            </div>

                            <div class="flex-1">

                                <div class="question-content text-justify">
                                    <?= normalizeMathText($question['question']) ?>
                                </div>

                                <p class="text-sm text-gray-500 mt-2">
                                    Jenis:
                                    <span class="font-medium">
                                        <?= e(getQuestionTypeLabel($questionType)) ?>
                                    </span>
                                    · Bobot:
                                    <span class="font-medium">
                                        <?= e($question['points']) ?> poin
                                    </span>
                                </p>

                            </div>

                        </div>

                    </div>

                    <!-- ---------- Wacana Reference ---------- -->
                    <?php if (!empty($question['wacana_description'])): ?>

                        <div class="content-reference-card mb-5">

                            <h3 class="table-td-title mb-3">
                                <?= e($question['wacana_title'] ?: 'Wacana') ?>
                            </h3>

                            <div class="wacana-content text-justify">
                                <?= normalizeMathText($question['wacana_description']) ?>
                            </div>

                        </div>

                    <?php endif; ?>

                    <!-- ---------- PhET Reference ---------- -->
                    <?php if (!empty($question['phet_title'])): ?>

                    <div class="notice-card notice-info mb-5">

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                            <div>
                                <h3 class="font-semibold text-blue-800">
                                    Simulasi PhET
                                </h3>

                                <p class="text-sm text-blue-700 mt-1">
                                    <?= e($question['phet_title']) ?>
                                </p>
                            </div>

                            <button
                                type="button"
                                class="btn-phet-preview phet-preview-btn"
                                data-title="<?= e($question['phet_title']) ?>"
                                data-iframe="<?= htmlspecialchars($question['iframe_phet'] ?? '', ENT_QUOTES) ?>"
                            >
                                Lihat Simulasi
                            </button>

                        </div>

                    </div>

                    <?php endif; ?>

                    <!-- ---------- Student Answer ---------- -->
                    <div class="mb-5">

                        <h4 class="text-sm font-semibold text-gray-700 mb-2">
                            Jawaban Anda:
                        </h4>

                        <div class="answer-box">

                            <?php
                            if ($questionType === 'likert') {

                                if ($studentAnswer && !empty($studentAnswer['answer_text'])) {

                                    $likertLabels = [
                                        'sangat_setuju' => 'Sangat Setuju',
                                        'setuju' => 'Setuju',
                                        'tidak_setuju' => 'Tidak Setuju',
                                        'sangat_tidak_setuju' => 'Sangat Tidak Setuju'
                                    ];

                                    $answerKey = strtolower(trim($studentAnswer['answer_text']));

                                    echo e($likertLabels[$answerKey] ?? $studentAnswer['answer_text']);

                                } else {

                                    echo '<span class="italic text-gray-400">Tidak menjawab</span>';

                                }

                            } elseif (
                                in_array($questionType, [
                                    'multiple_choice',
                                    'reasoned_multiple_choice'
                                ])
                            ) {

                                if ($studentAnswer && !empty($studentAnswer['option_id'])) {

                                    $optionId = (int) $studentAnswer['option_id'];

                                    $optionStmt = $conn->prepare("
                                        SELECT option_text
                                        FROM question_opt
                                        WHERE id = ?
                                        LIMIT 1
                                    ");

                                    $optionStmt->bind_param("i", $optionId);
                                    $optionStmt->execute();
                                    $optionResult = $optionStmt->get_result();

                                    if ($optionResult && $optionResult->num_rows > 0) {
                                        $option = $optionResult->fetch_assoc();
                                        echo '<span class="math-content">' . normalizeMathText(e($option['option_text'])) . '</span>';
                                    } else {
                                        echo '<span class="italic text-gray-400">Opsi tidak ditemukan</span>';
                                    }

                                } else {
                                    echo '<span class="italic text-gray-400">Tidak menjawab</span>';
                                }

                                if (
                                    $questionType === 'reasoned_multiple_choice' &&
                                    $studentAnswer &&
                                    !empty($studentAnswer['answer_text'])
                                ) {
                                    echo '<div class="mt-4 pt-4 border-t border-gray-200">';
                                    echo '<div class="text-xs font-semibold text-gray-500 mb-1">Alasan:</div>';
                                    echo renderAnswerText($studentAnswer['answer_text'], $question);
                                    echo '</div>';
                                }

                            } else {

                                echo $studentAnswer
                                    ? renderAnswerText($studentAnswer['answer_text'], $question)
                                    : '<span class="italic text-gray-400">Tidak menjawab</span>';

                            }
                            ?>

                        </div>

                    </div>

                    <!-- ---------- Answer Options ---------- -->
                    <?php if (
                        in_array($questionType, [
                            'multiple_choice',
                            'reasoned_multiple_choice'
                        ])
                    ): ?>

                        <div class="mb-5">

                            <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                Daftar Opsi:
                            </h4>

                            <div class="space-y-2">

                                <?php
                                $optionsStmt = $conn->prepare("
                                    SELECT *
                                    FROM question_opt
                                    WHERE question_id = ?
                                    ORDER BY order_by ASC, id ASC
                                ");

                                $optionsStmt->bind_param("i", $questionId);
                                $optionsStmt->execute();
                                $optionsResult = $optionsStmt->get_result();

                                while ($option = $optionsResult->fetch_assoc()):

                                    $isCorrect = (int)$option['is_right'] === 1;
                                    $isSelected = $studentAnswer &&
                                        (int)($studentAnswer['option_id'] ?? 0) === (int)$option['id'];
                                ?>

                                    <?php
                                    $optionClass = 'bg-white border-gray-200';

                                    if ($isCorrect) {
                                        $optionClass = 'bg-green-50 border-green-500';
                                    }

                                    if ($isSelected && !$isCorrect) {
                                        $optionClass = 'bg-red-50 border-red-500';
                                    }
                                    ?>

                                    <div class="option-review-card <?= $optionClass ?>">
                                        <div class="flex items-center justify-between gap-3">

                                            <span class="math-content">
                                                <?= normalizeMathText(e($option['option_text'])) ?>
                                            </span>

                                            <div class="flex items-center gap-2">

                                                <?php if ($isSelected): ?>

                                                    <span class="status-badge <?= $isCorrect ? 'status-success' : 'status-danger' ?>">
                                                        Pilihan Anda
                                                    </span>

                                                <?php endif; ?>

                                                <?php if ($isCorrect && $questionType !== 'likert'): ?>
                                                    <span class="status-badge status-success">Benar</span>
                                                <?php endif; ?>

                                                <?php if ($isSelected && !$isCorrect): ?>
                                                    <span class="status-badge status-danger">Salah</span>
                                                <?php endif; ?>

                                            </div>

                                        </div>
                                    </div>

                                <?php endwhile; ?>

                            </div>

                        </div>

                    <?php endif; ?>

                    <!-- ---------- Answer Key ---------- -->
                    <?php if (!empty($question['answer_key_text'])): ?>

                        <div class="mb-5">

                            <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                Kunci Jawaban:
                            </h4>

                            <div class="notice-card notice-info">

                                <div class="math-content">
                                    <?= nl2br(
                                        normalizeMathText(
                                            e($question['answer_key_text'])
                                        )
                                    ) ?>
                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                    <!-- ---------- Evaluation Result ---------- -->
                    <?php if ($questionType !== 'likert' || $canSeeQuestionnaireScore): ?>

                    <?php if ($aiEvaluation): ?>

                        <?php
                        $category = strtolower(trim($aiEvaluation['category']));

                        $categoryClass = 'bg-gray-50 border-gray-200 text-gray-700';

                        if ($category === 'benar') {
                            $categoryClass = 'bg-green-50 border-green-200 text-green-700';
                        } elseif ($category === 'cukup') {
                            $categoryClass = 'bg-blue-50 border-blue-200 text-blue-700';
                        } elseif ($category === 'kurang') {
                            $categoryClass = 'bg-yellow-50 border-yellow-200 text-yellow-700';
                        } elseif ($category === 'salah') {
                            $categoryClass = 'bg-red-50 border-red-200 text-red-700';
                        }

                        $choiceStatus = ((int)($studentAnswer['is_right'] ?? 0) === 1)
                            ? 'Benar'
                            : 'Salah';

                        $choiceClass = $choiceStatus === 'Benar'
                            ? 'text-green-700'
                            : 'text-red-700';

                        $reasonClass = 'text-gray-700 bg-gray-100';

                        if ($category === 'benar') {
                            $reasonClass = 'text-green-700 bg-green-100';
                        } elseif ($category === 'cukup') {
                            $reasonClass = 'text-blue-700 bg-blue-100';
                        } elseif ($category === 'kurang') {
                            $reasonClass = 'text-yellow-700 bg-yellow-100';
                        } elseif ($category === 'salah') {
                            $reasonClass = 'text-red-700 bg-red-100';
                        }
                        ?>

                        <?php if ($questionType === 'reasoned_multiple_choice'): ?>

                            <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 mb-3">

                                <div class="flex items-center justify-between <?= $choiceStatus === 'Salah' ? '' : 'mb-2' ?>">

                                    <span class="text-sm font-medium text-gray-700">
                                        Pilihan Ganda
                                    </span>

                                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                                        <?= $choiceStatus === 'Benar'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-red-100 text-red-700' ?>">
                                        <?= $choiceStatus ?>
                                    </span>

                                </div>

                                <?php if ($choiceStatus === 'Benar'): ?>

                                    <div class="flex items-center justify-between">

                                        <span class="text-sm font-medium text-gray-700">
                                            Alasan
                                        </span>

                                        <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $reasonClass ?>">
                                            <?= e($aiEvaluation['category']) ?>
                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="ai-category-card <?= $categoryClass ?>">
                                <span class="text-sm font-medium">
                                    Kategori Akhir
                                </span>

                                <span class="text-lg font-bold">
                                    <?= e($aiEvaluation['category']) ?>
                                </span>
                            </div>

                        <?php else: ?>

                            <div class="ai-category-card <?= $categoryClass ?>">
                                <span class="text-sm font-medium">
                                    Kategori
                                </span>

                                <span class="text-lg font-bold">
                                    <?= e($aiEvaluation['category']) ?>
                                </span>
                            </div>

                        <?php endif; ?>

                        <?php elseif (in_array($questionType, ['essay', 'reasoned_multiple_choice'])): ?>

                            <div class="ai-category-card notice-warning">
                                <span class="text-sm font-medium">
                                    Kategori
                                </span>

                                <span class="text-lg font-bold">
                                    Menunggu AI
                                </span>
                            </div>

                        <?php endif; ?>

                        <?php if ($questionType === 'short_answer'): ?>

                            <?php
                            $isCorrectShortAnswer = ((int)($studentAnswer['is_right'] ?? 0) === 1);

                            $statusClass = $isCorrectShortAnswer
                                ? 'bg-green-50 border-green-200 text-green-700'
                                : 'bg-red-50 border-red-200 text-red-700';

                            $statusText = $isCorrectShortAnswer
                                ? 'Benar'
                                : 'Salah';
                            ?>

                            <div class="flex items-center justify-between <?= $statusClass ?> border rounded-xl px-4 py-3 mb-3">

                                <span class="text-sm font-medium">
                                    Status Jawaban
                                </span>

                                <span class="text-lg font-bold">
                                    <?= $statusText ?>
                                </span>

                            </div>

                        <?php endif; ?>

                        <!-- ---------- Question Score ---------- -->
                        <?php if ($questionType !== 'likert'): ?>

                            <div class="score-card">

                                <span class="text-sm font-medium">
                                    Skor Butir
                                </span>

                                <span class="text-lg font-bold">
                                    <?= formatSmartNumber($earnedScore) ?> / <?= formatSmartNumber($question['points']) ?>
                                </span>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </div>

                <?php endwhile; ?>

            </section>

            <!-- =======================================================
                PAGE NAVIGATION
            ======================================================= -->
            <div class="flex flex-col sm:flex-row justify-center gap-4 mt-8">

                <?php if ($isAdmin || $isTeacher): ?>

                    <a
                        href="history.php"
                        class="px-5 py-3 rounded-xl bg-blue-600 text-white text-center font-medium hover:bg-blue-700 transition"
                    >
                        ← Kembali ke Hasil
                    </a>

                    <a
                        href="home.php"
                        class="px-5 py-3 rounded-xl bg-gray-600 text-white text-center font-medium hover:bg-gray-700 transition"
                    >
                        Beranda
                    </a>

                <?php else: ?>

                    <a
                        href="history.php"
                        class="px-5 py-3 rounded-xl bg-blue-600 text-white text-center font-medium hover:bg-blue-700 transition"
                    >
                        ← Riwayat Hasil
                    </a>

                    <a
                        href="student_quiz_list.php"
                        class="px-5 py-3 rounded-xl bg-indigo-600 text-white text-center font-medium hover:bg-indigo-700 transition"
                    >
                        Kuis Saya
                    </a>

                    <a
                        href="home.php"
                        class="px-5 py-3 rounded-xl bg-gray-600 text-white text-center font-medium hover:bg-gray-700 transition"
                    >
                        Beranda
                    </a>

                <?php endif; ?>

            </div>

            <?php endif; ?>

        </div>

    </main>


    <script>
    /* =======================================================
        PAGE INITIALIZATION
    ======================================================= */
    document.addEventListener("DOMContentLoaded", function () {

        <?php if (empty($accessError)): ?>

        /* =======================================================
            TABLE STYLING
        ======================================================= */
        document.querySelectorAll(".wacana-content figure.table").forEach(function (figure) {
            figure.style.display = "flex";
            figure.style.justifyContent = "center";
            figure.style.margin = "1rem 0";
        });

        document.querySelectorAll(".wacana-content table").forEach(function (table) {
            table.classList.add(
                "border",
                "border-gray-300",
                "border-collapse"
            );

            table.style.width = "auto";
            table.style.margin = "0 auto";
        });

        document.querySelectorAll(".student-answer-table table").forEach(function (table) {
            table.classList.add(
                "border",
                "border-gray-300",
                "border-collapse"
            );

            table.style.width = "auto";
            table.style.margin = "0";
        });

        document.querySelectorAll(".student-answer-table th").forEach(function (th) {
            th.classList.add(
                "border",
                "border-gray-300",
                "bg-gray-100",
                "px-3",
                "py-2",
                "font-semibold",
                "text-center"
            );
        });

        document.querySelectorAll(".student-answer-table td").forEach(function (td) {
            td.classList.add(
                "border",
                "border-gray-300",
                "px-3",
                "py-2",
                "text-center"
            );
        });

        document.querySelectorAll(".student-answer-table .bg-green-50").forEach(function (cell) {
            cell.classList.add(
                "bg-green-50",
                "text-green-700",
                "font-semibold"
            );
        });

        document.querySelectorAll(".wacana-content th").forEach(function (th) {
            th.classList.add(
                "border",
                "border-gray-300",
                "bg-gray-100",
                "px-3",
                "py-2",
                "font-semibold",
                "text-center"
            );
        });

        document.querySelectorAll(".wacana-content td").forEach(function (td) {
            td.classList.add(
                "border",
                "border-gray-300",
                "px-3",
                "py-2",
                "text-center"
            );
        });

        /* =======================================================
            PHET PREVIEW
        ======================================================= */
        document.querySelectorAll(".btn-phet-preview").forEach(function(btn) {

            btn.addEventListener("click", function() {

                Swal.fire({
                    title: this.dataset.title,
                    html: this.dataset.iframe,
                    width: "900px",
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: "rounded-2xl"
                    }
                });

            });

        });

        /* =======================================================
            MATHEMATICAL RENDERING
        ======================================================= */
        if (typeof renderMathInElement === "function") {
            document.querySelectorAll(".question-content, .wacana-content, .math-content").forEach(function (element) {
                renderMathInElement(element, {
                    delimiters: [
                        { left: "\\(", right: "\\)", display: false },
                        { left: "\\[", right: "\\]", display: true },
                        { left: "$$", right: "$$", display: true },
                        { left: "$", right: "$", display: false }
                    ],
                    throwOnError: false
                });
            });
        }

        <?php else: ?>

        /* =======================================================
            ACCESS ERROR HANDLER
        ======================================================= */
        Swal.fire({
            icon: <?= json_encode($errorIcon) ?>,
            title: <?= json_encode($errorTitle) ?>,
            text: <?= json_encode($accessError) ?>,

            confirmButtonText: "Kembali",

            allowOutsideClick: false,
            allowEscapeKey: false,

            customClass: {
                popup: "rounded-3xl",
                confirmButton: "rounded-xl px-6 py-3 font-medium"
            },

            buttonsStyling: false,

            didOpen: () => {

                Swal.getConfirmButton().classList.add(
                    "bg-blue-600",
                    "hover:bg-blue-700",
                    "text-white",
                    "transition"
                );

            }

        }).then(() => {
            window.location.href = "history.php";
        });

        <?php endif; ?>

    });
    </script>

</body>
</html>