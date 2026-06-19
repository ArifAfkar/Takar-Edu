<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../config/auth.php';
require_once '../config/db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type'])
) {
    exit('Akses ditolak.');
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);
$isStudent = ($userType === 3);

if (!$isAdmin && !$isTeacher && !$isStudent) {
    exit('Akses ditolak.');
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
    exit('Kuis tidak valid.');
}

/* =======================================================
    STUDENT INFORMATION
======================================================= */
if ($isStudent) {

    $studentStmt = $conn->prepare("
        SELECT id
        FROM students
        WHERE user_id = ?
        LIMIT 1
    ");

    $studentStmt->bind_param("i", $userId);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();

    if (!$studentResult || $studentResult->num_rows <= 0) {
        exit('Data siswa tidak ditemukan.');
    }

    $studentData = $studentResult->fetch_assoc();
    $studentId = (int)$studentData['id'];

} else {

    if ($targetStudentId <= 0) {
        exit('Siswa tidak valid.');
    }

    $studentId = $targetStudentId;
}

/* =======================================================
    PDF HELPER FUNCTIONS
======================================================= */
/* ---------- HTML Escape ---------- */
function e($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/* ---------- Smart Number Format ---------- */
function smart($number)
{
    if ($number === null || $number === '') {
        return '-';
    }

    return rtrim(rtrim(number_format((float)$number, 2, '.', ''), '0'), '.');
}

/* ---------- Math Text Cleanup ---------- */
function cleanMathText($text)
{
    return str_replace(
        ['\\\\(', '\\\\)', '\\\\[', '\\\\]'],
        ['\\(', '\\)', '\\[', '\\]'],
        (string)$text
    );
}

/* ---------- PDF Math Renderer ---------- */
function renderMathForPdf($text)
{
    $text = cleanMathText((string)$text);
    $text = e($text);

    $patterns = [
        '/\\\\\((.*?)\\\\\)/s',
        '/\\\\\[(.*?)\\\\\]/s'
    ];

    foreach ($patterns as $pattern) {
        $text = preg_replace_callback($pattern, function ($m) {
            $formula = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));

            if ($formula === '') {
                return '';
            }

            $url = 'https://latex.codecogs.com/png.image?' . rawurlencode($formula);

            return '<img src="' . $url . '" class="math-img">';
        }, $text);
    }

    return nl2br($text);
}

/* ---------- PDF Content Renderer ---------- */
function renderContentForPdf($html)
{
    if (empty($html)) {
        return '';
    }

    $html = cleanMathText($html);

    $patterns = [
        '/\\\\\((.*?)\\\\\)/s',
        '/\\\\\[(.*?)\\\\\]/s'
    ];

    foreach ($patterns as $pattern) {
        $html = preg_replace_callback($pattern, function ($m) {

            $formula = trim($m[1]);

            if ($formula === '') {
                return '';
            }

            $url = 'https://latex.codecogs.com/png.image?' . rawurlencode($formula);

            return '<img src="' . $url . '" class="math-img">';
        }, $html);
    }

    return $html;
}

/* ---------- Student Table Renderer ---------- */
function renderStudentAnswerTableForPdf($question, $tableAnswer)
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

        $key = "r{$rowIndex}_c{$colIndex}";

        if (!array_key_exists($key, $tableAnswer)) {
            continue;
        }

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
            $dom->createTextNode((string)$tableAnswer[$key])
        );

        $targetCell->setAttribute('class', 'student-filled-cell');
    }

    $table = $dom->getElementsByTagName('table')->item(0);

    return $table ? $dom->saveHTML($table) : '';
}

/* ---------- Date Formatter ---------- */
function dateWITA($datetime)
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return '-';
    }

    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    $hari  = (int)date('j', $timestamp);
    $bulanNama = $bulan[(int)date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    $jam = date('H:i:s', $timestamp);

    return "{$hari} {$bulanNama} {$tahun} • {$jam} WITA";
}

/* ---------- Question Type Label ---------- */
function typeLabel($type)
{
    $labels = [
        'likert'                   => 'Angket',
        'multiple_choice'          => 'Pilihan Ganda',
        'reasoned_multiple_choice' => 'Pilihan Ganda Beralasan',
        'short_answer'             => 'Isian Singkat',
        'essay'                    => 'Uraian'
    ];

    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

/* ---------- Category Color Class ---------- */
function categoryClass($category)
{
    $category = strtolower(trim((string)$category));

    if ($category === 'benar') {
        return 'green';
    }

    if ($category === 'cukup') {
        return 'blue';
    }

    if ($category === 'kurang') {
        return 'yellow';
    }

    if ($category === 'salah') {
        return 'red';
    }

    return '';
}

/* ---------- Answer Renderer ---------- */
function answerTextToHtml($text, $question = null)
{
    if ($text === null || trim((string)$text) === '') {
        return '<em class="muted">Tidak menjawab</em>';
    }

    $decoded = json_decode($text, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

        $html = '';

        if (!empty($decoded['text_answer'])) {
            $html .= '<div class="label">Jawaban teks:</div>';
            $html .= '<div class="answer-box">' . renderMathForPdf($decoded['text_answer']) . '</div>';
        }

        if (!empty($decoded['table_answer']) && is_array($decoded['table_answer'])) {

            $tableHtml = $question
                ? renderStudentAnswerTableForPdf($question, $decoded['table_answer'])
                : '';

            $html .= '<div class="label">Jawaban tabel:</div>';

            if ($tableHtml !== '') {

                $html .= '<div class="answer-box student-answer-table">';
                $html .= $tableHtml;
                $html .= '</div>';

            } else {

                $html .= '<div class="answer-box">';
                $html .= count($decoded['table_answer']) . ' sel tabel telah diisi.';
                $html .= '</div>';

            }
        }

        return $html !== ''
            ? $html
            : '<em class="muted">Tidak menjawab</em>';
    }

    return renderMathForPdf($text);
}

/* =======================================================
    QUIZ RESULT INFORMATION
======================================================= */
/* ---------- Quiz Result Query ---------- */
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
        h.submitted_at,

        u.name AS student_name,
        c.class_name

    FROM quiz_student_list qsl

    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id

    INNER JOIN history h
        ON h.quiz_student_id = qsl.id

    INNER JOIN students s
        ON qsl.student_id = s.id

    INNER JOIN users u
        ON s.user_id = u.id

    LEFT JOIN classes c
        ON s.class_id = c.id

    WHERE q.id = ?
    AND qsl.student_id = ?
    LIMIT 1
");

$dataStmt->bind_param("ii", $quizId, $studentId);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();

if (!$dataResult || $dataResult->num_rows <= 0) {
    exit('Riwayat pengerjaan kuis tidak ditemukan.');
}

$data = $dataResult->fetch_assoc();

if ($isTeacher && (int)$data['created_by'] !== $userId) {
    exit('Anda tidak memiliki akses ke hasil kuis ini.');
}

$totalPointEarned = (float)($data['final_score'] ?? 0);
$totalPointMax    = (float)($data['max_score'] ?? 0);

$finalGrade = $totalPointMax > 0
    ? round(($totalPointEarned / $totalPointMax) * 100, 2)
    : 0;

$nilaiClass = 'score-red';

if ($finalGrade >= 80) {
    $nilaiClass = 'score-green';
}
elseif ($finalGrade >= 60) {
    $nilaiClass = 'score-yellow';
}

$violationCount = (int)($data['violation_count'] ?? 0);

$violationClass = 'score-green';

if ($violationCount >= 3) {
    $violationClass = 'score-red';
}
elseif ($violationCount >= 2) {
    $violationClass = 'score-orange';
}
elseif ($violationCount >= 1) {
    $violationClass = 'score-yellow';
}

$statusText = '';

if (($data['submit_reason'] ?? '') === 'time_expired') {
    $statusText = 'Waktu ujian habis';
} elseif (($data['submit_reason'] ?? '') === 'violation_limit') {
    $statusText = 'Dihentikan karena batas pelanggaran';
}

/* =======================================================
    DISPLAY HELPER FUNCTIONS
======================================================= */
function formatSubjectList($subjectString)
{
    $subjects = array_filter(
        array_map('trim', explode(',', (string)$subjectString))
    );

    $count = count($subjects);

    if ($count === 0) {
        return '-';
    }

    if ($count === 1) {
        return $subjects[0];
    }

    if ($count === 2) {
        return implode(' & ', $subjects);
    }

    $lastSubject = array_pop($subjects);

    return implode(', ', $subjects)
        . ', & '
        . $lastSubject;
}

/* =======================================================
    PDF HTML TEMPLATE
======================================================= */
ob_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .header h1 {
            font-size: 18px;
            margin: 0;
        }

        .header p {
            margin: 3px 0 0;
            font-size: 12px;
        }

        .info-table,
        .summary-table,
        .small-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table {
            margin-bottom: 14px;
        }

        .info-table td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .summary-table {
            margin: 12px 0 18px;
        }

        .summary-table th,
        .summary-table td,
        .small-table th,
        .small-table td {
            border: 1px solid #d1d5db;
            padding: 7px;
        }

        .summary-table th,
        .small-table th {
            background: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }

        .summary-table td {
            text-align: center;
        }

        h2 {
            font-size: 14px;
            margin-top: 18px;
            padding-bottom: 5px;
            border-bottom: 1px solid #d1d5db;
        }

        .question {
            page-break-inside: auto;
            border: 1px solid #d1d5db;
            padding: 10px;
            margin-bottom: 14px;
        }

        .question-title,
        .meta,
        .status-box,
        .score-box {
            page-break-inside: avoid;
        }

        .wacana-box,
        .box,
        .option-box,
        .key-box {
            page-break-inside: auto;
        }

        .question-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .meta {
            font-size: 11px;
            color: #4b5563;
            margin-bottom: 8px;
        }

        .label {
            font-weight: bold;
            margin-top: 8px;
            margin-bottom: 4px;
        }

        .box {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 7px;
            margin-bottom: 6px;
        }

        .key-box {
            border: 1px solid #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 7px;
            margin-bottom: 6px;
        }

        .wacana-box {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 8px;
            margin: 8px 0;
        }

        .phet-box {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 8px;
            margin: 8px 0;
        }

        .status-box {
            border: 1px solid #d1d5db;
            padding: 7px;
            margin-top: 7px;
            margin-bottom: 6px;
        }

        .score-box {
            border: 1px solid #d1d5db;
            background: #f3f4f6;
            padding: 7px;
            margin-top: 7px;
        }

        .green {
            background: #ecfdf5;
            border-color: #86efac;
            color: #047857;
            font-weight: bold;
        }

        .red {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
            font-weight: bold;
        }

        .yellow {
            background: #fefce8;
            border-color: #fde047;
            color: #a16207;
            font-weight: bold;
        }

        .blue {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }

        .option-correct {
            background: #ecfdf5;
        }

        .option-wrong {
            background: #fef2f2;
        }

        .footer {
            margin-top: 20px;
            font-size: 10px;
            color: #6b7280;
            text-align: right;
        }

        .score-red {
            color: #b91c1c;
            font-weight: bold;
        }

        .score-orange {
            color: #c2410c;
            font-weight: bold;
        }

        .score-yellow {
            color: #a16207;
            font-weight: bold;
        }

        .score-green {
            color: #047857;
            font-weight: bold;
        }

        .math-img {
            max-height: 22px;
            vertical-align: middle;
        }

        .option-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            border: 1px solid #d1d5db;
        }

        .option-box td {
            padding: 8px;
            vertical-align: middle;
        }

        .option-text {
            width: 75%;
        }

        .option-status {
            width: 25%;
            text-align: right;
            font-size: 11px;
            font-weight: bold;
        }

        .option-normal {
            background: #ffffff;
        }

        .option-correct {
            background: #ecfdf5;
            border-color: #22c55e;
            color: #047857;
        }

        .option-wrong {
            background: #fef2f2;
            border-color: #ef4444;
            color: #b91c1c;
        }

        .wacana-box table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .wacana-box table th,
        .wacana-box table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: center;
        }

        .wacana-box table th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .wacana-box img {
            max-width: 100%;
        }

        .student-answer-table table {
            width: auto;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .student-answer-table th,
        .student-answer-table td {
            border: 1px solid #d1d5db;
            padding: 7px 10px;
            text-align: center;
        }

        .student-answer-table th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .student-filled-cell {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <!-- =======================================================
        PDF HEADER
    ======================================================= -->
    <div class="header">
        <h1>LAPORAN HASIL PENGERJAAN KUIS</h1>
        <p>Takar-Edu</p>
    </div>

    <!-- =======================================================
        STUDENT INFORMATION
    ======================================================= -->
    <table class="info-table">
        <tr>
            <td width="22%">Judul Kuis</td>
            <td width="3%">:</td>
            <td><?= e($data['quiz_title']) ?></td>
        </tr>
        <tr>
            <td>Nama Siswa</td>
            <td>:</td>
            <td><?= e($data['student_name']) ?></td>
        </tr>
        <tr>
            <td>Kelas</td>
            <td>:</td>
            <td><?= e($data['class_name'] ?? '-') ?></td>
        </tr>
        <tr>
            <td>Mata Pelajaran</td>
            <td>:</td>
            <td><?= e(formatSubjectList($data['subject_name'] ?? '')) ?></td>
        </tr>
        <tr>
            <td>Waktu Mulai</td>
            <td>:</td>
            <td><?= e(dateWITA($data['started_at'])) ?></td>
        </tr>
        <tr>
            <td>Dikumpulkan</td>
            <td>:</td>
            <td><?= e(dateWITA($data['submitted_at'])) ?></td>
        </tr>

        <?php if ($statusText !== ''): ?>
            <tr>
                <td>Status Pengumpulan</td>
                <td>:</td>
                <td><?= e($statusText) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <!-- =======================================================
        RESULT SUMMARY
    ======================================================= -->
    <table class="summary-table">
        <tr>
            <th>Poin Diperoleh</th>
            <th>Nilai Akhir</th>
            <th>Pelanggaran</th>
        </tr>
        <tr>
            <td><?= smart($data['final_score']) ?> / <?= smart($data['max_score']) ?></td>
            <td class="<?= $nilaiClass ?>"><?= smart($finalGrade) ?> / 100</td>
            <td class="<?= $violationClass ?>"><?= $violationCount ?> / 3</td>
        </tr>
    </table>

    <!-- =======================================================
        ANSWER REVIEW
    ======================================================= -->
    <h2>Rincian Jawaban</h2>

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

        $questionId = (int)$question['id'];
        $questionType = strtolower(trim($question['question_type']));

        $answerStmt = $conn->prepare("
            SELECT *
            FROM answers
            WHERE student_id = ?
            AND quiz_id = ?
            AND question_id = ?
            LIMIT 1
        ");

        $answerStmt->bind_param("iii", $studentId, $quizId, $questionId);
        $answerStmt->execute();
        $answerResult = $answerStmt->get_result();

        $studentAnswer = $answerResult && $answerResult->num_rows > 0
            ? $answerResult->fetch_assoc()
            : null;

        $earnedScore = 0;
        $category = null;

        if ($studentAnswer) {

            if (in_array($questionType, ['essay', 'reasoned_multiple_choice'])) {

                $evalStmt = $conn->prepare("
                    SELECT category, score
                    FROM answer_evaluations
                    WHERE answer_id = ?
                    LIMIT 1
                ");

                $evalStmt->bind_param("i", $studentAnswer['id']);
                $evalStmt->execute();
                $evalResult = $evalStmt->get_result();

                if ($evalResult && $evalResult->num_rows > 0) {
                    $eval = $evalResult->fetch_assoc();
                    $category = $eval['category'];
                    $earnedScore = (float)$eval['score'];
                }

            } elseif ((int)($studentAnswer['is_right'] ?? 0) === 1) {

                $earnedScore = (float)$question['points'];

            }
        }
    ?>

    <!-- =======================================================
        QUESTION ITEM
    ======================================================= -->
    <div class="question">

        <!-- ---------- Question Information ---------- -->
        <div class="question-title">
            Soal <?= $no++ ?>.
        </div>

        <div>
            <?= renderMathForPdf($question['question']) ?>
        </div>

        <div class="meta">
            Jenis: <?= e(typeLabel($questionType)) ?> |
            Bobot: <?= smart($question['points']) ?> poin
        </div>

        <?php if (!empty($question['wacana_description'])): ?>
            <!-- ---------- Wacana Reference ---------- -->
            <div class="label">Wacana:</div>
            <div class="wacana-box">
                <?php if (!empty($question['wacana_title'])): ?>
                    <strong><?= e($question['wacana_title']) ?></strong><br>
                <?php endif; ?>

                <?= renderContentForPdf($question['wacana_description']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($question['phet_title'])): ?>
            <!-- ---------- PhET Reference ---------- -->
            <div class="label">Simulasi PhET:</div>
            <div class="phet-box">
                <?= e($question['phet_title']) ?>
            </div>
        <?php endif; ?>

        <!-- ---------- Student Answer ---------- -->
        <div class="label">Jawaban Siswa:</div>

        <div class="box">
            <?php
            if ($questionType === 'likert') {

                $likertLabels = [
                    'sangat_setuju' => 'Sangat Setuju',
                    'setuju' => 'Setuju',
                    'tidak_setuju' => 'Tidak Setuju',
                    'sangat_tidak_setuju' => 'Sangat Tidak Setuju'
                ];

                $answerKey = strtolower(trim($studentAnswer['answer_text'] ?? ''));

                echo e($likertLabels[$answerKey] ?? 'Tidak menjawab');

            } elseif (in_array($questionType, ['multiple_choice', 'reasoned_multiple_choice'])) {

                if ($studentAnswer && !empty($studentAnswer['option_id'])) {

                    $optionId = (int)$studentAnswer['option_id'];

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
                        echo renderMathForPdf($option['option_text']);
                    } else {
                        echo '<em class="muted">Opsi tidak ditemukan</em>';
                    }

                } else {
                    echo '<em class="muted">Tidak menjawab</em>';
                }

                if (
                    $questionType === 'reasoned_multiple_choice' &&
                    $studentAnswer &&
                    !empty($studentAnswer['answer_text'])
                ) {
                    echo '<div class="label">Alasan:</div>';
                    echo answerTextToHtml($studentAnswer['answer_text'], $question);
                }

            } else {

                echo $studentAnswer
                    ? answerTextToHtml($studentAnswer['answer_text'], $question)
                    : '<em class="muted">Tidak menjawab</em>';

            }
            ?>
        </div>

        <?php if (in_array($questionType, ['multiple_choice', 'reasoned_multiple_choice'])): ?>

            <!-- ---------- Answer Options ---------- -->
            <div class="label">Daftar Opsi:</div>

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

                $optionClass = 'option-normal';

                if ($isCorrect) {
                    $optionClass = 'option-correct';
                }

                if ($isSelected && !$isCorrect) {
                    $optionClass = 'option-wrong';
                }

                $status = [];

                if ($isSelected) {
                    $status[] = 'Pilihan Anda';
                }

                if ($isCorrect) {
                    $status[] = 'Benar';
                }

                if ($isSelected && !$isCorrect) {
                    $status[] = 'Salah';
                }
            ?>

                <table class="option-box <?= $optionClass ?>">
                    <tr>
                        <td class="option-text">
                            <?= renderMathForPdf($option['option_text']) ?>
                        </td>
                        <td class="option-status">
                            <?= !empty($status) ? e(implode(' | ', $status)) : '' ?>
                        </td>
                    </tr>
                </table>

            <?php endwhile; ?>

        <?php endif; ?>

        <?php if (!empty($question['answer_key_text'])): ?>

            <!-- ---------- Answer Key ---------- -->
            <div class="label">Kunci Jawaban:</div>

            <div class="key-box">
                <?= renderMathForPdf($question['answer_key_text']) ?>
            </div>

        <?php endif; ?>

        <!-- ---------- Evaluation Result ---------- -->
        <?php if ($questionType === 'short_answer'): ?>

            <?php
            $isCorrectShortAnswer = $studentAnswer &&
                (int)($studentAnswer['is_right'] ?? 0) === 1;

            $statusClass = $isCorrectShortAnswer ? 'green' : 'red';
            $statusText  = $isCorrectShortAnswer ? 'Benar' : 'Salah';
            ?>

            <div class="status-box <?= $statusClass ?>">
                <strong>Status Jawaban:</strong> <?= $statusText ?>
            </div>

        <?php endif; ?>

        <?php if ($questionType === 'reasoned_multiple_choice' && $category !== null): ?>

            <?php
            $choiceCorrect = $studentAnswer &&
                (int)($studentAnswer['is_right'] ?? 0) === 1;

            $choiceStatus = $choiceCorrect ? 'Benar' : 'Salah';
            $choiceClass  = $choiceCorrect ? 'green' : 'red';
            $reasonClass  = categoryClass($category);

            // Jika pilihan ganda salah,
            // kategori akhir otomatis Salah
            if (!$choiceCorrect) {
                $finalCategory = 'Salah';
                $finalClass    = 'red';
            } else {
                $finalCategory = $category;
                $finalClass    = $reasonClass;
            }
            ?>

            <?php if ($choiceCorrect): ?>

                <div class="status-box">
                    <strong>Pilihan Ganda:</strong>
                    <span class="<?= $choiceClass ?>"><?= $choiceStatus ?></span><br>

                    <strong>Alasan:</strong>
                    <span class="<?= $reasonClass ?>"><?= e($category) ?></span>
                </div>

            <?php else: ?>

                <div class="status-box">
                    <strong>Pilihan Ganda:</strong>
                    <span class="<?= $choiceClass ?>">Salah</span>
                </div>

            <?php endif; ?>

            <div class="status-box <?= $finalClass ?>">
                <strong>Kategori Akhir:</strong> <?= e($finalCategory) ?>
            </div>

        <?php elseif ($category !== null): ?>

            <?php $catClass = categoryClass($category); ?>

            <div class="status-box <?= $catClass ?>">
                <strong>Kategori:</strong> <?= e($category) ?>
            </div>

        <?php endif; ?>

        <?php if ($questionType !== 'likert'): ?>

            <!-- ---------- Question Score ---------- -->
            <div class="score-box">
                <strong>Skor:</strong>
                <?= smart($earnedScore) ?> / <?= smart($question['points']) ?>
            </div>

        <?php endif; ?>

    </div>

    <?php endwhile; ?>

    <div class="footer">
        Dicetak dari Takar-Edu pada <?= date('d/m/Y H:i:s') ?> WITA
    </div>

</body>
</html>

<?php
$html = ob_get_clean();

/* =======================================================
    PDF GENERATION
======================================================= */
/* ---------- Dompdf Configuration ---------- */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

/* ---------- File Name Generation ---------- */
$cleanStudentName = preg_replace('/[^A-Za-z0-9_-]/', '_', $data['student_name']);
$cleanQuizTitle   = preg_replace('/[^A-Za-z0-9_-]/', '_', $data['quiz_title']);

$fileName = 'Hasil_Kuis_' . $cleanStudentName . '_' . $cleanQuizTitle . '.pdf';

/* ---------- PDF Output ---------- */
$dompdf->stream($fileName, [
    'Attachment' => true
]);

exit;