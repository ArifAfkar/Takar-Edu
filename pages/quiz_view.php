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

/* ---------- Admin and Teacher Access ---------- */
if (!in_array($_SESSION['login_user_type'], [1, 2])) {
    header("Location: home.php");
    exit;
}

/* =======================================================
    SESSION CONFIGURATION
======================================================= */
$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

/* ---------- Logged User ---------- */
$userName = $_SESSION['login_name']
    ?? $_SESSION['login_user_name']
    ?? $_SESSION['name']
    ?? 'User';

/* =======================================================
    ROLE FLAGS
======================================================= */
$isAdmin   = $userType === 1;
$isTeacher = $userType === 2;

/* =======================================================
    QUIZ ID VALIDATION
======================================================= */
$quizId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($quizId <= 0) {
    die("ID kuis tidak valid.");
}

/* =======================================================
    TEACHER ACCESS
======================================================= */
$teacherId = 0;

if ($isTeacher) {

    $teacherQuery = $conn->query("
        SELECT id
        FROM teachers
        WHERE user_id = {$userId}
        LIMIT 1
    ");

    if (!$teacherQuery || $teacherQuery->num_rows <= 0) {
        die("Data pengajar tidak ditemukan.");
    }

    $teacherData = $teacherQuery->fetch_assoc();
    $teacherId   = (int) $teacherData['id'];

}

/* =======================================================
    QUIZ FILTER
======================================================= */
$quizWhere = [];
$quizWhere[] = "q.id = {$quizId}";

if ($isTeacher) {
    $quizWhere[] = "(
        q.created_by = {$userId}
        OR EXISTS (
            SELECT 1
            FROM quiz_teacher_list qtl
            WHERE qtl.quiz_id = q.id
            AND qtl.teacher_id = {$teacherId}
            AND EXISTS (
                SELECT 1
                FROM teacher_class_assignments tca
                WHERE tca.teacher_id = qtl.teacher_id
                AND tca.status = 1
            )
        )
    )";
}

$quizWhereClause = implode(' AND ', $quizWhere);

/* =======================================================
    QUIZ DATA
======================================================= */
$quizQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_title,
        q.description,
        q.quiz_duration,
        q.status,
        q.created_by,
        q.open_at,
        q.due_date,
        q.created_at,

        (
            SELECT GROUP_CONCAT(DISTINCT c2.class_name ORDER BY c2.class_name SEPARATOR ', ')
            FROM quiz_teacher_list qtl2
            INNER JOIN teacher_class_assignments td2 ON qtl2.teacher_id = td2.teacher_id
            INNER JOIN classes c2 ON td2.class_id = c2.id
            WHERE qtl2.quiz_id = q.id
            AND td2.status = 1
        ) AS class_names,

        (
            SELECT GROUP_CONCAT(
                DISTINCT s2.subject_name
                ORDER BY s2.subject_name
                SEPARATOR '||'
            )
            FROM quiz_teacher_list qtl_subject
            INNER JOIN teachers t_subject
                ON qtl_subject.teacher_id = t_subject.id
            INNER JOIN subjects s2
                ON t_subject.subject_id = s2.id
            WHERE qtl_subject.quiz_id = q.id
        ) AS subject_name,

        (
            SELECT GROUP_CONCAT(u2.name SEPARATOR ', ')
            FROM quiz_teacher_list qtl
            INNER JOIN teachers t2 ON qtl.teacher_id = t2.id
            INNER JOIN users u2 ON t2.user_id = u2.id
            WHERE qtl.quiz_id = q.id
        ) AS assigned_teachers,

        (
            SELECT COUNT(*)
            FROM questions qs
            WHERE qs.quiz_id = q.id
        ) AS total_questions

    FROM quiz_list q

    LEFT JOIN users teacher_user
        ON q.created_by = teacher_user.id

    WHERE {$quizWhereClause}

    LIMIT 1
");

if (!$quizQuery || $quizQuery->num_rows <= 0) {
    die("Kuis tidak ditemukan atau akses ditolak.");
}

$quiz = $quizQuery->fetch_assoc();

/* =======================================================
    QUIZ MANAGEMENT PERMISSION
======================================================= */

$isOwnerQuiz = ((int)$quiz['created_by'] === $userId);

$canManageQuiz = $isAdmin || $isOwnerQuiz;

/* =======================================================
    MAIN QUESTION QUERY
======================================================= */
$questionQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_id,
        q.question,
        q.question_type,
        q.statement_type,
        q.points,
        q.order_by,
        q.answer_key_text,
        q.wacana_id,
        q.phet_id,

        (
            SELECT COUNT(*)
            FROM question_opt qo
            WHERE qo.question_id = q.id
        ) AS total_options

    FROM questions q

    WHERE q.quiz_id = {$quizId}

    ORDER BY q.order_by ASC, q.id ASC
");

$questions       = [];
$totalQuestions  = 0;
$totalPoints     = 0;

if ($questionQuery) {

    while ($row = $questionQuery->fetch_assoc()) {

        $questions[] = $row;
        $totalPoints += (int)$row['points'];

    }

    $totalQuestions = count($questions);

}

/* =======================================================
    WACANA REFERENCE DATA
======================================================= */
$wacanaOptions = [];

$wacanaWhere = $isAdmin
    ? ""
    : "WHERE user_id = {$userId}";

$wacanaQuery = $conn->query("
    SELECT
        id,
        wacana_title,
        description
    FROM wacana
    {$wacanaWhere}
    ORDER BY wacana_title ASC
");

if ($wacanaQuery) {

    while ($row = $wacanaQuery->fetch_assoc()) {
        $wacanaOptions[] = $row;
    }

}

$wacanaTableMap = [];

foreach ($wacanaOptions as $wacana) {

    $tables = [];

    if (!empty($wacana['description'])) {
        preg_match_all(
            '/<table\b[^>]*>.*?<\/table>/is',
            $wacana['description'],
            $matches
        );

        if (!empty($matches[0])) {
            $tables = $matches[0];
        }
    }

    $wacanaTableMap[(int)$wacana['id']] = $tables;
}

/* =======================================================
    PHET REFERENCE DATA
======================================================= */
$phetOptions = [];

$phetWhere = $isAdmin
    ? ""
    : "WHERE user_id = {$userId}";

$phetQuery = $conn->query("
    SELECT
        id,
        phet_title
    FROM phet
    {$phetWhere}
    ORDER BY phet_title ASC
");

if ($phetQuery) {

    while ($row = $phetQuery->fetch_assoc()) {
        $phetOptions[] = $row;
    }

}

/* =======================================================
    DISPLAY HELPER FUNCTIONS
======================================================= */
function questionTypeLabel($type)
{
    switch ($type) {

        case 'likert':
            return 'Angket';

        case 'multiple_choice':
            return 'Pilihan Ganda';

        case 'reasoned_multiple_choice':
            return 'PG Beralasan';

        case 'short_answer':
            return 'Isian Singkat';

        case 'essay':
            return 'Uraian';

        default:
            return ucfirst(
                str_replace('_', ' ', $type)
            );
    }
}

function questionTypeBadge($type)
{
    switch ($type) {

        case 'likert':
            return 'bg-purple-100 text-purple-700';

        case 'multiple_choice':
            return 'bg-blue-100 text-blue-700';

        case 'reasoned_multiple_choice':
            return 'bg-indigo-100 text-indigo-700';

        case 'short_answer':
            return 'bg-green-100 text-green-700';

        case 'essay':
            return 'bg-orange-100 text-orange-700';

        default:
            return 'bg-gray-100 text-gray-700';
    }
}

function quizStatusBadge($status)
{
    return ((int)$status === 1)
        ? '<span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Aktif</span>'
        : '<span class="inline-flex items-center px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold">Nonaktif</span>';
}

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Kelola Kuis - " . htmlspecialchars($quiz['quiz_title']) . " | Takar-Edu";

/* =======================================================
    TABLE LAYOUT
======================================================= */
$questionColspan = 6;
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <!-- =======================================================
        GLOBAL HEADER / ASSETS
    ======================================================= -->
    <?php require_once '../includes/header.php'; ?>

    <title><?= $pageTitle ?></title>
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
            DASHBOARD CONTAINER
        ======================================================= -->
        <div class="page-container">

            <!-- =======================================================
                PAGE HEADER SECTION
            ======================================================= -->
            <section class="mb-6">

                <!-- ---------- Back Button ---------- -->
                <div class="mb-4">

                    <a href="quiz.php" class="back-button">
                        <i data-lucide="arrow-left" class="w-3 h-3"></i>
                        Kembali ke Daftar Kuis
                    </a>

                </div>

                <!-- ---------- Quiz Header Card ---------- -->
                <div class="section-card">

                    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-6">

                        <!-- Quiz information -->
                        <div class="flex-1">

                            <div class="flex flex-wrap items-center gap-3 mb-3">

                                <h2 class="section-title text-xl sm:text-2xl">
                                    <?= htmlspecialchars($quiz['quiz_title']) ?>
                                </h2>

                                <?= quizStatusBadge($quiz['status']) ?>

                            </div>

                            <?php if (!empty($quiz['description'])): ?>

                                <p class="text-gray-600 leading-relaxed max-w-4xl">
                                    <?= nl2br(htmlspecialchars($quiz['description'])) ?>
                                </p>

                            <?php endif; ?>

                            <!-- Quiz metadata -->
                            <div class="flex flex-wrap gap-4 mt-5 text-sm text-gray-800">

                                <!-- Duration -->
                                <div class="inline-flex items-center gap-2 bg-gray-100 px-3 py-2 text-sm rounded-xl">
                                    <i data-lucide="clock-3" class="w-4 h-4"></i>
                                    <?= (int)$quiz['quiz_duration'] ?> menit
                                </div>

                                <!-- Class -->
                                <div class="relative inline-flex">

                                    <button
                                        type="button"
                                        class="meta-dropdown-btn table-dropdown-btn text-sm"
                                        data-target="classDropdown"
                                    >
                                        <i data-lucide="graduation-cap" class="w-4 h-4"></i>

                                        <span>
                                            <?= !empty($quiz['class_names'])
                                                ? substr_count($quiz['class_names'], ',') + 1 . ' Kelas'
                                                : '-' ?>
                                        </span>

                                        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform dropdown-chevron"></i>
                                    </button>

                                    <!-- Dropdown -->
                                    <div
                                        id="classDropdown"
                                        class="meta-dropdown hidden absolute left-0 top-full z-50 bg-white border border-gray-200 rounded-xl shadow-xl min-w-[240px]"
                                    >

                                        <div class="table-dropdown-header">
                                            <p class="table-dropdown-title">
                                                Daftar Kelas:
                                            </p>
                                        </div>

                                        <?php
                                        $classList = !empty($quiz['class_names'])
                                            ? explode(', ', $quiz['class_names'])
                                            : [];
                                        ?>

                                        <?php foreach ($classList as $class): ?>
                                            <div class="table-dropdown-item">
                                                <?= htmlspecialchars($class) ?>
                                            </div>
                                        <?php endforeach; ?>

                                    </div>

                                </div>

                                <!-- Subject -->
                                <?php
                                    $subjectList = !empty($quiz['subject_name'])
                                        ? array_filter(array_map('trim', explode('||', $quiz['subject_name'])))
                                        : [];

                                    $subjectCount = count($subjectList);
                                ?>

                                <?php if ($subjectCount <= 1): ?>

                                    <div class="inline-flex items-center gap-2 bg-gray-100 px-3 py-2 text-sm rounded-xl">
                                        <i data-lucide="book-open" class="w-4 h-4"></i>
                                        <?= htmlspecialchars($subjectList[0] ?? '-') ?>
                                    </div>

                                <?php else: ?>

                                    <div class="relative inline-block">

                                        <button
                                            type="button"
                                            onclick="toggleSubjectDropdown(event, 'subject-dropdown-<?= (int)$quiz['id'] ?>')"
                                            class="table-dropdown-btn text-sm"
                                        >
                                            <i data-lucide="layers-3" class="w-4 h-4"></i>
                                            Umum
                                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                        </button>

                                        <div
                                            id="subject-dropdown-<?= (int)$quiz['id'] ?>"
                                            class="hidden table-floating-dropdown"
                                        >

                                            <div class="table-dropdown-header">
                                                <p class="table-dropdown-title">
                                                    Daftar Mata Pelajaran:
                                                </p>
                                            </div>

                                            <?php foreach ($subjectList as $subject): ?>

                                                <div class="table-dropdown-item">
                                                    <?= htmlspecialchars($subject) ?>
                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <!-- Teacher -->
                                <?php if ($isAdmin): ?>

                                    <div class="relative inline-flex">

                                        <button
                                            type="button"
                                            class="meta-dropdown-btn table-dropdown-btn text-sm"
                                            data-target="teacherDropdown"
                                        >
                                            <i data-lucide="user-round" class="w-4 h-4"></i>

                                            <span>
                                                <?= !empty($quiz['assigned_teachers'])
                                                    ? substr_count($quiz['assigned_teachers'], ',') + 1 . ' Pengajar'
                                                    : '-' ?>
                                            </span>

                                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform dropdown-chevron"></i>
                                        </button>

                                        <!-- Teacher list -->
                                        <div
                                            id="teacherDropdown"
                                            class="meta-dropdown hidden absolute left-0 top-full z-50 bg-white border border-gray-200 rounded-xl shadow-xl min-w-[240px]"
                                        >

                                            <div class="table-dropdown-header">
                                                <p class="table-dropdown-title">
                                                    Daftar Pengajar:
                                                </p>
                                            </div>

                                            <?php
                                            $teacherList = !empty($quiz['assigned_teachers'])
                                                ? explode(', ', $quiz['assigned_teachers'])
                                                : [];
                                            ?>

                                            <?php foreach ($teacherList as $teacher): ?>
                                                <div class="table-dropdown-item">
                                                    <?= htmlspecialchars($teacher) ?>
                                                </div>
                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                        <!-- ---------- Quiz Actions ---------- -->
                        <div class="flex flex-row xl:flex-col gap-3">

                            <a
                                href="quiz_distribution.php?id=<?= (int)$quiz['id'] ?>"
                                class="page-action-btn flex-1 bg-indigo-600 hover:bg-indigo-700 text-white"
                            >
                                <i data-lucide="users" class="w-4 h-4"></i>
                                Distribusi
                            </a>

                            <a
                                href="history.php?quiz_id=<?= (int)$quiz['id'] ?>"
                                class="page-action-btn flex-1 bg-gray-700 hover:bg-gray-800 text-white"
                            >
                                <i data-lucide="history" class="w-4 h-4"></i>
                                Lihat Hasil
                            </a>

                        </div>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">

                    <!-- Total questions -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="file-question" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Soal</p>
                            <h3 class="stat-value"><?= $totalQuestions ?></h3>
                            <p class="stat-label">Soal dalam kuis</p>
                        </div>
                    </div>

                    <!-- Total points -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="award" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Poin</p>
                            <h3 class="stat-value"><?= $totalPoints ?></h3>
                            <p class="stat-label">Maksimum nilai mentah</p>
                        </div>
                    </div>

                    <!-- Quiz status -->
                    <div class="stat-card">
                        <div class="stat-icon <?= ((int)$quiz['status'] === 1) ? 'bg-purple-600' : 'bg-red-600' ?>">
                            <i data-lucide="shield-check" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="stat-label">Status Kuis</p>
                            <h3 class="stat-value"><?= ((int)$quiz['status'] === 1) ? 'Aktif' : 'Nonaktif' ?></h3>
                            <p class="stat-label">Distribusi dan akses</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                QUESTION TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Manajemen Soal Kuis
                    </h2>

                    <!-- Add question button -->
                    <?php if ($canManageQuiz): ?>
                        <button
                            id="newQuestion"
                            type="button"
                            class="form-btn form-btn-primary"
                        >
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            Tambah Soal
                        </button>
                    <?php endif; ?>

                </div>

                <!-- ---------- Table Controls ---------- -->
                <div class="table-toolbar">

                    <!-- Rows per page -->
                    <div class="table-length-control">

                        <span for="rowsPerPage" class="table-control-label">
                            Tampilkan
                        </span>

                        <select id="rowsPerPage" class="filter-select">
                            <option value="all">Semua</option>
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>

                        <span class="table-control-label">data</span>

                    </div>

                    <!-- Search input -->
                    <div class="search-wrapper">

                        <input
                            id="searchInput"
                            type="text"
                            placeholder="Cari soal..."
                            class="search-input"
                        >

                        <i data-lucide="search" class="input-icon"></i>

                    </div>

                </div>

                <!-- ---------- Table Wrapper ---------- -->
                <div class="table-wrapper">

                    <table id="dataTable" class="app-table">

                        <!-- ---------- Section Header ---------- -->
                        <thead class="app-table-head">

                            <tr>
                                <th class="table-th w-[5%]">No</th>
                                <th class="table-th w-[5%]"></th>
                                <th class="table-th w-[35%]">Pertanyaan</th>
                                <th class="table-th w-[14%]">Jenis</th>
                                <th class="table-th w-[10%]">Poin</th>
                                <th class="table-th w-[16%]">Referensi</th>
                                <th class="table-th w-[15%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody id="questionTableBody" class="bg-white">

                            <?php if ($totalQuestions > 0): ?>

                                <?php $no = 1; ?>

                                <?php foreach ($questions as $question): ?>

                                    <tr class="app-table-row" data-id="<?= (int)$question['id'] ?>">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Sortable -->
                                        <td class="table-td text-center">
                                            <button
                                                type="button"
                                                class="drag-handle cursor-move text-gray-400 hover:text-blue-600"
                                                title="Geser untuk mengubah urutan"
                                            >
                                                <i data-lucide="grip-vertical" class="w-4 h-4"></i>
                                            </button>
                                        </td>

                                        <!-- Question -->
                                        <td class="table-td">

                                            <div class="table-td-title quiz-math-content">
                                                <?= nl2br(htmlspecialchars(mb_strimwidth($question['question'], 0, 120, '...'))) ?>
                                            </div>

                                            <?php if (!empty($question['answer_key_text'])): ?>

                                                <div class="table-subtext">
                                                    Kunci: <?= htmlspecialchars(mb_strimwidth($question['answer_key_text'], 0, 80, '...')) ?>
                                                </div>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Type -->
                                        <td class="table-td text-center">

                                            <span class="status-badge <?= questionTypeBadge($question['question_type']) ?>">
                                                <?= questionTypeLabel($question['question_type']) ?>
                                            </span>

                                        </td>

                                        <!-- Points -->
                                        <td class="table-td text-center">

                                            <span class="status-badge bg-green-100 text-green-700">
                                                <?= (int)$question['points'] ?> poin
                                            </span>

                                        </td>

                                        <!-- References -->
                                        <td class="table-td text-center">

                                            <div class="flex flex-wrap justify-center gap-2">

                                                <?php if (!empty($question['wacana_id'])): ?>

                                                    <span class="status-badge bg-orange-100 text-orange-700">
                                                        Wacana
                                                    </span>

                                                <?php endif; ?>
                                                <?php if (!empty($question['phet_id'])): ?>

                                                    <span class="status-badge bg-purple-100 text-purple-700">
                                                        PhET
                                                    </span>

                                                <?php endif; ?>

                                                <?php if (empty($question['wacana_id']) && empty($question['phet_id'])): ?>

                                                    <span class="status-badge bg-gray-100 text-gray-500">
                                                        Tidak Ada
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                        <!-- Action buttons -->
                                        <td class="table-td text-center">

                                            <?php if ($canManageQuiz): ?>

                                                <div class="action-group">

                                                    <!-- Edit button -->
                                                    <button
                                                        type="button"
                                                        onclick="editQuestion(<?= (int)$question['id'] ?>)"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="square-pen" class="w-4 h-4"></i>
                                                        <span class="action-label">Edit</span>
                                                    </button>

                                                    <!-- Delete button -->
                                                    <button
                                                        type="button"
                                                        onclick="deleteQuestion(<?= (int)$question['id'] ?>)"
                                                        class="action-btn action-danger"
                                                    >
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        <span class="action-label">Hapus</span>
                                                    </button>

                                                </div>

                                            <?php else: ?>

                                                <span class="status-badge bg-gray-100 text-gray-500">
                                                    Read-only
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyQuestionRow"
                                class="<?= $totalQuestions > 0 ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $questionColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="file-question" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada soal tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noQuestionResult" class="hidden">
                                <td colspan="<?= $questionColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada soal yang sesuai dengan pencarian
                                        </span>

                                    </div>
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

                <!-- ---------- Table Footer ---------- -->
                <div class="table-footer">

                    <!-- Page info -->
                    <div id="pageInfo" class="page-info">
                        Menampilkan data
                    </div>

                    <!-- Pagination -->
                    <div id="pagination" class="pagination"></div>

                </div>

            </section>

        </div>

    </main>

    <!-- =======================================================
        QUESTION MODAL SECTION
    ======================================================= -->
    <div id="questionModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Soal
                </h3>

                <button
                    type="button"
                    class="modal-close"
                    data-close="questionModal"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Question Form ---------- -->
            <form id="questionForm" class="modal-form">

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id" id="questionId">
                    <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

                    <!-- Question title -->
                    <div>

                        <label class="form-label">
                            Pertanyaan
                        </label>

                        <div class="flex flex-wrap items-center gap-2 mb-2">

                            <button
                                type="button"
                                onclick="openQuizEquationModal('#questionText')"
                                class="equation-btn"
                            >
                                ∑ Rumus
                            </button>

                            <span class="editor-hint">
                                Contoh: p = mv, I = F \Delta t, E_k = \frac{1}{2}mv^2
                            </span>

                        </div>

                        <textarea
                            name="question"
                            id="questionText"
                            rows="4"
                            placeholder="Masukkan soal..."
                            class="form-textarea"
                        ></textarea>

                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        <!-- Type -->
                        <div>

                            <label class="form-label">
                                Jenis Soal
                            </label>

                            <select
                                name="question_type"
                                id="questionType"
                                class="form-select"
                            >
                                <option value="likert"> Angket</option>
                                <option value="multiple_choice" selected>Pilihan Ganda</option>
                                <option value="reasoned_multiple_choice">Pilihan Ganda Beralasan</option>
                                <option value="short_answer">Isian Singkat</option>
                                <option value="essay">Uraian</option>
                            </select>

                        </div>

                        <!-- Points -->
                        <div id="pointsWrapper">

                            <label class="form-label">
                                Poin
                            </label>

                            <input
                                type="number"
                                name="points"
                                id="questionPoints"
                                min="1"
                                value="1"
                                class="form-input"
                            >

                        </div>

                        <!-- Statement type -->
                        <div
                            id="statementTypeWrapper"
                            class="hidden"
                        >

                            <label class="form-label">
                                Tipe Pernyataan
                            </label>

                            <select
                                name="statement_type"
                                id="statementType"
                                class="form-select"
                            >
                                <option value="positive">Positif</option>
                                <option value="negative">Negatif</option>
                            </select>

                        </div>

                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <!-- Wacana -->
                        <div>

                            <label class="form-label">
                                Wacana
                            </label>

                            <select
                                name="wacana_id"
                                id="questionWacana"
                                class="form-select"
                            >

                                <option value="">Tanpa Wacana</option>

                                <?php foreach ($wacanaOptions as $wacana): ?>

                                    <option value="<?= (int)$wacana['id'] ?>">
                                        <?= htmlspecialchars($wacana['wacana_title']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- PhET -->
                        <div>

                            <label class="form-label">
                                PhET
                            </label>

                            <select
                                name="phet_id"
                                id="questionPhet"
                                class="form-select"
                            >

                                <option value="">Tanpa PhET</option>

                                <?php foreach ($phetOptions as $phet): ?>

                                    <option value="<?= (int)$phet['id'] ?>">
                                        <?= htmlspecialchars($phet['phet_title']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                    <!-- Wacana with table -->
                    <div
                        id="wacanaTableAnswerWrapper"
                        class="hidden border border-blue-200 bg-blue-50 rounded-2xl p-4 space-y-3"
                    >
                        <h4 class="font-semibold text-gray-800">
                            Konfigurasi Tabel dari Wacana
                        </h4>

                        <div>
                            <label class="form-label">
                                Mode Tabel
                            </label>

                            <select
                                id="tableMode"
                                class="form-select"
                            >
                                <option value="info">Tabel Informasi</option>
                                <option value="rubric_based">Tabel Jawaban Siswa (Praktikum/Analisis)</option>
                            </select>
                        </div>

                        <p id="tableModeInfo" class="text-sm text-gray-600"></p>

                        <div
                            id="wacanaTableAnswerPreview"
                            class="overflow-x-auto bg-white border border-gray-200 rounded-xl p-3"
                        ></div>

                        <textarea
                            name="answer_table_config"
                            id="answerTableConfig"
                            class="hidden"
                        ></textarea>
                    </div>

                    <!-- Options -->
                    <div
                        id="multipleChoiceSection"
                        class="space-y-3"
                    >

                        <h4 class="text-base font-semibold text-gray-800">
                            Opsi Jawaban
                        </h4>

                        <div
                            id="multipleChoiceOptions"
                            class="space-y-3"
                        >

                            <?php for ($i = 0; $i < 5; $i++): ?>

                                <div class="flex items-center gap-3">

                                    <input
                                        type="radio"
                                        name="correct_answer"
                                        value="<?= $i ?>"
                                        class="w-4 h-4 text-blue-600"
                                        <?= $i === 0 ? 'checked' : '' ?>
                                    >

                                    <input
                                        type="text"
                                        name="options[]"
                                        placeholder="Opsi <?= chr(65 + $i) ?>"
                                        class="option-answer-input form-input"
                                    >

                                    <button
                                        type="button"
                                        onclick="openQuizEquationModal(this.previousElementSibling)"
                                        class="mini-equation-btn"
                                        title="Sisipkan rumus"
                                    >
                                        ∑
                                    </button>

                                </div>

                            <?php endfor; ?>

                        </div>

                    </div>

                    <!-- Answer key -->
                    <div id="answerKeySection" class="hidden">

                        <h4 id="answerKeyTitle" class="text-base font-semibold text-gray-800 mb-2">
                            Kunci Jawaban
                        </h4>

                        <div
                            id="shortAnswerInfo"
                            class="hidden mb-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3"
                        >
                            <p class="text-sm text-blue-800 font-medium">
                                Isian Singkat digunakan untuk jawaban objektif seperti simbol, satuan,
                                angka, rumus pendek, atau istilah.
                            </p>

                            <p class="text-xs text-blue-700 mt-1">
                                Jika jawaban dapat memiliki banyak variasi benar atau memerlukan
                                penjelasan, gunakan jenis soal Uraian.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <button
                                type="button"
                                onclick="openQuizEquationModal('#answerKeyText')"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-100 hover:bg-indigo-200 text-indigo-700 text-sm font-medium transition"
                            >
                                ∑ Rumus
                            </button>
                        </div>

                        <textarea
                            name="answer_key_text"
                            id="answerKeyText"
                            rows="3"
                            placeholder="Masukkan kunci jawaban..."
                            class="form-textarea"
                        ></textarea>

                    </div>

                    <!-- Rubric -->
                    <div id="rubricSection" class="hidden border border-orange-200 bg-orange-50 rounded-2xl p-4 space-y-3">

                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                id="useRubricToggle"
                                name="use_rubric"
                                value="1"
                                class="w-4 h-4 text-orange-600 rounded"
                            >

                            <span class="text-sm font-semibold text-gray-800">
                                Gunakan Rubrik Penilaian
                            </span>
                        </label>

                        <div id="rubricInputWrapper" class="hidden space-y-3">

                            <p id="rubricRequiredInfo" class="hidden text-sm text-orange-700 bg-orange-100 border border-orange-200 rounded-xl px-4 py-3">
                                Rubrik wajib diisi karena soal menggunakan tabel jawaban siswa.
                            </p>

                            <div>
                                <label class="form-label">
                                    Poin: 3; Kategori: Benar
                                </label>
                                <textarea id="rubricScore3" rows="2" placeholder="Kriteria jawaban untuk skor 3..." class="rubric-structured-input form-textarea"></textarea>
                            </div>

                            <div>
                                <label class="form-label">
                                    Poin: 2; Kategori: Cukup
                                </label>
                                <textarea id="rubricScore2" rows="2" placeholder="Kriteria jawaban untuk skor 2..." class="rubric-structured-input form-textarea"></textarea>
                            </div>

                            <div>
                                <label class="form-label">
                                    Poin: 1; Kategori: Kurang
                                </label>
                                <textarea id="rubricScore1" rows="2" placeholder="Kriteria jawaban untuk skor 1..." class="rubric-structured-input form-textarea"></textarea>
                            </div>

                            <div>
                                <label class="form-label">
                                    Poin: 0; Kategori: Salah
                                </label>
                                <textarea id="rubricScore0" rows="2" placeholder="Kriteria jawaban untuk skor 0..." class="rubric-structured-input form-textarea"></textarea>
                            </div>

                            <div>
                                <label class="form-label">
                                    Catatan Tambahan
                                </label>
                                <textarea id="rubricNotes" rows="2" placeholder="Catatan tambahan jika diperlukan..." class="rubric-structured-input form-textarea"></textarea>
                            </div>

                            <textarea
                                name="rubric_text"
                                id="rubricText"
                                class="hidden"
                            ></textarea>

                        </div>

                    </div>

                </div>

                <!-- ---------- Form Buttons ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        data-close="questionModal"
                        class="form-btn form-btn-secondary"
                    >
                        Batal
                    </button>

                    <!-- Save button -->
                    <button
                        type="submit"
                        class="form-btn form-btn-primary"
                    >
                        Simpan
                    </button>

                </div>

            </form>

        </div>

    </div>


    <script>
    /* =======================================================
        SUBJECT DROPDOWN SYSTEM
    ======================================================= */
    function closeAllSubjectDropdowns() {
        document.querySelectorAll('[id^="subject-dropdown-"]').forEach(dropdown => {
            dropdown.classList.add("hidden");
        });
    }

    function toggleSubjectDropdown(event, dropdownId) {

        event.stopPropagation();

        const target = document.getElementById(dropdownId);
        if (!target) return;

        const isOpen = !target.classList.contains("hidden");

        closeAllSubjectDropdowns();

        if (isOpen) return;

        const button = event.currentTarget;
        const rect = button.getBoundingClientRect();

        target.classList.remove("hidden");

        let left = rect.left + (rect.width / 2) - (target.offsetWidth / 2);

        if (left < 10) left = 10;

        if (left + target.offsetWidth > window.innerWidth - 10) {
            left = window.innerWidth - target.offsetWidth - 10;
        }

        let top = rect.bottom + 8;

        if (top + target.offsetHeight > window.innerHeight - 10) {
            top = rect.top - target.offsetHeight - 8;
        }

        if (top < 10) top = 10;

        target.style.left = `${left}px`;
        target.style.top = `${top}px`;
    }

    document.addEventListener("click", closeAllSubjectDropdowns);
    window.addEventListener("scroll", closeAllSubjectDropdowns, true);
    window.addEventListener("resize", closeAllSubjectDropdowns);

    document.addEventListener("DOMContentLoaded",
        function() {

        /* =======================================================
            INITIALIZE ICONS
        ======================================================= */
        if (
            window.lucide
        ) {
            lucide.createIcons();
        }

/* =======================================================
    META DROPDOWN SYSTEM (CLASS & TEACHER)
======================================================= */
const metaDropdownButtons = document.querySelectorAll(".meta-dropdown-btn");

metaDropdownButtons.forEach(button => {

    button.addEventListener("click", function(e) {

        e.stopPropagation();

        const targetId = this.dataset.target;
        const targetDropdown = document.getElementById(targetId);
        const chevron = this.querySelector(".dropdown-chevron");

        /* Close all others */
        document.querySelectorAll(".meta-dropdown").forEach(dropdown => {
            if (dropdown !== targetDropdown) {
                dropdown.classList.add("hidden");
            }
        });

        document.querySelectorAll(".dropdown-chevron").forEach(icon => {
            if (icon !== chevron) {
                icon.classList.remove("rotate-180");
            }
        });

        /* Toggle current */
        targetDropdown.classList.toggle("hidden");
        chevron.classList.toggle("rotate-180");

    });

});

/* Click outside closes all */
document.addEventListener("click", function() {

    document.querySelectorAll(".meta-dropdown").forEach(dropdown => {
        dropdown.classList.add("hidden");
    });

    document.querySelectorAll(".dropdown-chevron").forEach(icon => {
        icon.classList.remove("rotate-180");
    });

});

        /* =======================================================
            ELEMENTS
        ======================================================= */
        const wacanaTableMap = <?= json_encode(
            $wacanaTableMap,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ); ?>;
        
        const questionTable =
            document.getElementById(
                "dataTable"
            );

        const searchInput =
            document.getElementById(
                "searchInput"
            );

        const rowsPerPage =
            document.getElementById(
                "rowsPerPage"
            );

        const pageInfo =
            document.getElementById(
                "pageInfo"
            );

        const pagination =
            document.getElementById(
                "pagination"
            );

        const modal =
            document.getElementById(
                "questionModal"
            );

        const form =
            document.getElementById(
                "questionForm"
            );

        const modalTitle =
            document.getElementById(
                "modalTitle"
            );

        const questionType =
            document.getElementById(
                "questionType"
            );

        const questionTextInput = document.getElementById("questionText");
        const questionPointsInput = document.getElementById("questionPoints");
        const answerKeyTextInput = document.getElementById("answerKeyText");
        const rubricTextInput = document.getElementById("rubricText");
        const useRubricToggle = document.getElementById("useRubricToggle");

        function getErrorWrapper(input) {
            return input.closest(".relative")?.parentElement || input.parentElement;
        }

        function clearFieldError(input) {
            if (!input) return;

            input.classList.remove(
                "border-red-500",
                "focus:border-red-500",
                "focus:ring-red-200",
                "focus:ring-0"
            );

            input.classList.add(
                "border-gray-300",
                "focus:ring-2",
                "focus:ring-blue-500"
            );

            getErrorWrapper(input)
                .querySelectorAll(".inline-error")
                .forEach(el => el.remove());
        }

        function clearInlineErrors() {
            form.querySelectorAll("input, textarea, select").forEach(input => {
                clearFieldError(input);
            });
        }

        function setInlineError(input, message) {
            if (!input) return;

            input.classList.remove(
                "border-gray-300",
                "focus:ring-2",
                "focus:ring-blue-500",
                "focus:ring-red-200"
            );

            input.classList.add(
                "border-red-500",
                "focus:border-red-500",
                "focus:ring-0"
            );

            getErrorWrapper(input)
                .querySelectorAll(".inline-error")
                .forEach(el => el.remove());

            const error = document.createElement("p");
            error.className = "inline-error text-xs text-red-500 mt-1";
            error.textContent = message;

            getErrorWrapper(input).appendChild(error);
        }

        function setOptionSectionError(message) {

            const wrapper =
                document.getElementById("multipleChoiceSection");

            wrapper
                .querySelectorAll(".option-inline-error")
                .forEach(el => el.remove());

            const error = document.createElement("p");

            error.className =
                "option-inline-error text-xs text-red-500 mt-2";

            error.textContent = message;

            wrapper.appendChild(error);
        }

        function clearOptionSectionError() {

            const wrapper =
                document.getElementById("multipleChoiceSection");

            wrapper
                .querySelectorAll(".option-inline-error")
                .forEach(el => el.remove());
        }

        function attachLiveValidation(input) {
            if (!input) return;

            input.addEventListener("input", () => clearFieldError(input));
            input.addEventListener("change", () => clearFieldError(input));
        }

        attachLiveValidation(questionTextInput);
        attachLiveValidation(questionType);
        attachLiveValidation(questionPointsInput);
        attachLiveValidation(answerKeyTextInput);
        attachLiveValidation(rubricTextInput);

        function getStructuredRubricFields() {
            return {
                score3: document.getElementById("rubricScore3"),
                score2: document.getElementById("rubricScore2"),
                score1: document.getElementById("rubricScore1"),
                score0: document.getElementById("rubricScore0"),
                notes: document.getElementById("rubricNotes")
            };
        }

        function buildStructuredRubricText() {
            const fields = getStructuredRubricFields();

            return JSON.stringify({
                score_3: fields.score3?.value.trim() || "",
                score_2: fields.score2?.value.trim() || "",
                score_1: fields.score1?.value.trim() || "",
                score_0: fields.score0?.value.trim() || "",
                notes: fields.notes?.value.trim() || ""
            });
        }

        function fillStructuredRubric(rawRubric) {
            const fields = getStructuredRubricFields();

            fields.score3.value = "";
            fields.score2.value = "";
            fields.score1.value = "";
            fields.score0.value = "";
            fields.notes.value = "";

            if (!rawRubric) return;

            try {
                const data = JSON.parse(rawRubric);

                fields.score3.value = data.score_3 || "";
                fields.score2.value = data.score_2 || "";
                fields.score1.value = data.score_1 || "";
                fields.score0.value = data.score_0 || "";
                fields.notes.value = data.notes || "";
            } catch (e) {
                fields.notes.value = rawRubric;
            }
        }

        function clearStructuredRubric() {
            fillStructuredRubric("");
            if (rubricTextInput) rubricTextInput.value = "";
        }

        function hasStructuredRubricContent() {
            const fields = getStructuredRubricFields();

            return (
                fields.score3?.value.trim() &&
                fields.score2?.value.trim() &&
                fields.score1?.value.trim() &&
                fields.score0?.value.trim()
            );
        }

        function isTableAnswerMode() {
            return document.getElementById("tableMode")?.value === "rubric_based";
        }

        function updateRubricRequirement() {
            const rubricInputWrapper = document.getElementById("rubricInputWrapper");
            const rubricRequiredInfo = document.getElementById("rubricRequiredInfo");

            if (!useRubricToggle || !rubricInputWrapper) return;

            if (questionType.value === "essay" && isTableAnswerMode()) {
                useRubricToggle.checked = true;
                useRubricToggle.disabled = true;
                rubricInputWrapper.classList.remove("hidden");
                rubricRequiredInfo?.classList.remove("hidden");
            } else {
                useRubricToggle.disabled = false;
                rubricRequiredInfo?.classList.add("hidden");

                if (useRubricToggle.checked) {
                    rubricInputWrapper.classList.remove("hidden");
                } else {
                    rubricInputWrapper.classList.add("hidden");
                }
            }
        }

        document.querySelectorAll(".rubric-structured-input").forEach(input => {
            attachLiveValidation(input);
        });

        document
            .querySelectorAll(".option-answer-input")
            .forEach(input => {

                attachLiveValidation(input);

                input.addEventListener("input", () => {
                    clearOptionSectionError();
                });

            });

        const emptyRow =
            document.getElementById(
                "emptyQuestionRow"
            );

        const noResultRow =
            document.getElementById(
                "noQuestionResult"
            );

        if (
            !questionTable ||
            !form ||
            !modal
        ) return;

        let questionRows =
            Array.from(
                questionTable.querySelectorAll(
                    "tbody tr"
                )
            ).filter(
                row =>
                    row.id !==
                        "emptyQuestionRow" &&
                    row.id !==
                        "noQuestionResult"
            );

/* =======================================================
    RESTORE TABLE STATE ON REFRESH
======================================================= */
const STORAGE_KEYS = {
    search: "quiz_view_<?= (int)$quizId ?>_search_keyword",
    rows: "quiz_view_<?= (int)$quizId ?>_rows_per_page",
    page: "quiz_view_<?= (int)$quizId ?>_current_page"
};

const navigationType =
    performance.getEntriesByType("navigation")[0]?.type || "navigate";

const shouldRestore =
    navigationType === "reload";

if (!shouldRestore) {
    sessionStorage.removeItem(STORAGE_KEYS.search);
    sessionStorage.removeItem(STORAGE_KEYS.rows);
    sessionStorage.removeItem(STORAGE_KEYS.page);
}

const savedSearch =
    shouldRestore
        ? sessionStorage.getItem(STORAGE_KEYS.search) || ""
        : "";

const savedRows =
    shouldRestore
        ? sessionStorage.getItem(STORAGE_KEYS.rows) || "10"
        : "10";

const savedPage =
    shouldRestore
        ? parseInt(sessionStorage.getItem(STORAGE_KEYS.page) || "1")
        : 1;

searchInput.value = savedSearch;
rowsPerPage.value = savedRows;

let filteredRows =
    savedSearch
        ? questionRows.filter(row =>
            row.innerText.toLowerCase().includes(savedSearch.toLowerCase())
        )
        : [...questionRows];

let currentPage = savedPage;

/* =======================================================
    SWEETALERT HELPERS
======================================================= */
function getResponsiveSwal(icon, title, text) {

    const isMobile =
        window.innerWidth < 640;

    return {
        icon,
        title,
        text,

        width: isMobile
            ? "90%"
            : "34rem",

        padding: isMobile
            ? "1.25rem"
            : "2rem",

        buttonsStyling: false,
        reverseButtons: false,
        focusConfirm: false,

        customClass: {
            popup: `
                rounded-2xl
                shadow-2xl
                px-6 py-6
                sm:px-8 sm:py-8
            `,

            title: `
                text-lg sm:text-2xl
                font-bold
                text-gray-800
            `,

            htmlContainer: `
                text-sm sm:text-base
                text-gray-600
            `,

            actions: `
                flex flex-row justify-center gap-3 mt-6
            `,

            confirmButton: `
                inline-flex items-center justify-center
                min-w-[120px]
                px-5 py-3
                rounded-xl
                font-semibold
                text-white
                bg-red-600 hover:bg-red-700
                transition
                shadow-sm
            `,

            cancelButton: `
                inline-flex items-center justify-center
                min-w-[120px]
                px-5 py-3
                rounded-xl
                font-semibold
                text-white
                bg-gray-500 hover:bg-gray-600
                transition
                shadow-sm
            `
        }
    };

}

function showToast(icon, title) {

    Swal.fire({
        toast: true,
        position: "top-end",
        icon,
        title,
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,

        customClass: {
            popup: "rounded-2xl shadow-xl",
            title: "font-semibold text-gray-800 text-sm sm:text-base"
        }
    });

}

function showLoading(title = "Memproses.") {

    Swal.fire({
        title,
        allowOutsideClick: false,
        allowEscapeKey: false,

        didOpen: () => {
            Swal.showLoading();
        },

        customClass: {
            popup: "rounded-2xl shadow-xl"
        }
    });

}

/* =======================================================
    EQUATION BUILDER SYSTEM
======================================================= */
let activeQuizEquationTarget = null;

function insertEquationText(text) {
    const input = document.getElementById("equationInput");
    if (!input) return;

    const start = input.selectionStart;
    const end = input.selectionEnd;
    const value = input.value;

    input.value =
        value.substring(0, start) +
        text +
        value.substring(end);

    input.focus();

    const cursorPosition = start + text.length;
    input.setSelectionRange(cursorPosition, cursorPosition);

    updateEquationPreview();
}

function insertTrigFunction(func) {
    const input = document.getElementById("equationInput");
    if (!input) return;

    const trigMap = {
        sin: "\\sin()",
        cos: "\\cos()",
        tan: "\\tan()",
        csc: "\\csc()",
        sec: "\\sec()",
        cot: "\\cot()",
        asin: "\\sin^{-1}()",
        acos: "\\cos^{-1}()",
        atan: "\\tan^{-1}()",
        sin2: "\\sin^2()",
        cos2: "\\cos^2()",
        tan2: "\\tan^2()",
        degree: "^\\circ",
        identity: "\\sin^2(x)+\\cos^2(x)=1"
    };

    const text = trigMap[func] || "";
    if (!text) return;

    insertEquationText(text);

    if (["sin", "cos", "tan", "csc", "sec", "cot", "asin", "acos", "atan", "sin2", "cos2", "tan2"].includes(func)) {
        const cursorBack = 1;
        const currentPos = input.selectionStart;
        input.setSelectionRange(currentPos - cursorBack, currentPos - cursorBack);
        input.focus();
    }
}

function updateEquationPreview() {
    const input = document.getElementById("equationInput");
    const preview = document.getElementById("equationPreview");

    if (!input || !preview) return;

    const value = input.value.trim();

    if (!value) {
        preview.innerHTML = `<span class="text-gray-400">Preview rumus akan muncul di sini.</span>`;
        return;
    }

    try {
        if (typeof katex !== "undefined") {
            preview.innerHTML = katex.renderToString(value, {
                throwOnError: false,
                displayMode: true
            });
        }
    } catch (e) {
        preview.innerHTML = `<span class="text-red-500">Rumus belum valid.</span>`;
    }
}

function insertEquationToEditor() {
    const input = document.getElementById("equationInput");

    if (!input || !activeQuizEquationTarget) return;

    const latex = input.value.trim();

    if (!latex) {
        showToast("warning", "Rumus masih kosong.");
        return;
    }

    const equationText = ` \\(${latex}\\) `;

    const start = activeQuizEquationTarget.selectionStart ?? activeQuizEquationTarget.value.length;
    const end = activeQuizEquationTarget.selectionEnd ?? activeQuizEquationTarget.value.length;
    const value = activeQuizEquationTarget.value || "";

    activeQuizEquationTarget.value =
        value.substring(0, start) +
        equationText +
        value.substring(end);

    activeQuizEquationTarget.focus();

    const cursorPosition = start + equationText.length;
    activeQuizEquationTarget.setSelectionRange(cursorPosition, cursorPosition);

    Swal.close();
}

function builderMiniToolbar(targetInputId) {
    return `
        <div class="mt-2">
            <p class="text-[11px] text-gray-500 mb-1">
                Bantuan isi field: klik tombol, lalu isi bagian kosongnya.
            </p>

            <div class="grid grid-cols-6 gap-1">
                <button type="button" title="Pangkat" onclick="insertBuilderText('${targetInputId}', '^{}', 2)" class="mini-eq-btn">xⁿ</button>
                <button type="button" title="Indeks bawah" onclick="insertBuilderText('${targetInputId}', '_{}', 2)" class="mini-eq-btn">xₙ</button>
                <button type="button" title="Pecahan" onclick="insertBuilderText('${targetInputId}', '\\\\frac{}{}', 6)" class="mini-eq-btn">a/b</button>
                <button type="button" title="Akar" onclick="insertBuilderText('${targetInputId}', '\\\\sqrt{}', 6)" class="mini-eq-btn">√</button>
                <button type="button" title="Sinus" onclick="insertBuilderText('${targetInputId}', '\\\\sin()', 5)" class="mini-eq-btn">sin</button>
                <button type="button" title="Cosinus" onclick="insertBuilderText('${targetInputId}', '\\\\cos()', 5)" class="mini-eq-btn">cos</button>
                <button type="button" title="Tangen" onclick="insertBuilderText('${targetInputId}', '\\\\tan()', 5)" class="mini-eq-btn">tan</button>
                <button type="button" title="Delta" onclick="insertBuilderText('${targetInputId}', '\\\\Delta ')" class="mini-eq-btn">Δ</button>
                <button type="button" title="Pi" onclick="insertBuilderText('${targetInputId}', '\\\\pi ')" class="mini-eq-btn">π</button>
                <button type="button" title="Theta" onclick="insertBuilderText('${targetInputId}', '\\\\theta ')" class="mini-eq-btn">θ</button>
                <button type="button" title="Tak Hingga" onclick="insertBuilderText('${targetInputId}', '\\\\infty ')" class="mini-eq-btn">∞</button>
                <button type="button" title="Kali" onclick="insertBuilderText('${targetInputId}', '\\\\times ')" class="mini-eq-btn">×</button>
            </div>
        </div>
    `;
}

function insertBuilderText(inputId, text, cursorOffset = null) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const start = input.selectionStart;
    const end = input.selectionEnd;
    const value = input.value;

    input.value =
        value.substring(0, start) +
        text +
        value.substring(end);

    input.focus();

    const cursorPosition =
        cursorOffset !== null
            ? start + cursorOffset
            : start + text.length;

    input.setSelectionRange(cursorPosition, cursorPosition);

    const activeType = document.getElementById("equationBuilderPanel")?.dataset.type;
    if (activeType) updateBuilderPreview(activeType);
}

function updateBuilderPreview(type) {
    const preview = document.getElementById("builderPreview");
    if (!preview) return;

    const value1 = document.getElementById("builderValue1")?.value.trim() || "";
    const value2 = document.getElementById("builderValue2")?.value.trim() || "";
    const value3 = document.getElementById("builderValue3")?.value.trim() || "";

    let latex = "";

    const emptyBox = "\\Box";

    if (type === "fraction") {
        latex = `\\frac{${value1 || emptyBox}}{${value2 || emptyBox}}`;
    }

    if (type === "power") {
        latex = `${value1 || emptyBox}^{${value2 || emptyBox}}`;
    }

    if (type === "root") {
        latex = `\\sqrt{${value1 || emptyBox}}`;
    }

    if (type === "integral") {
        latex = `\\int ${value1 || emptyBox} \\, d${value2 || emptyBox}`;
    }

    if (type === "limit") {
        latex = `\\lim_{${value1 || "x"} \\to ${value2 || emptyBox}} ${value3 || emptyBox}`;
    }

    if (type === "derivative") {
        const order = document.getElementById("builderValue3")?.value || "first";

        latex = order === "second"
            ? `\\frac{d^2}{d${value2 || "x"}^2}(${value1 || emptyBox})`
            : `\\frac{d}{d${value2 || "x"}}(${value1 || emptyBox})`;
    }

    if (type === "index") {
        latex = `${value1 || emptyBox}_{${value2 || emptyBox}}`;
    }

    if (type === "vector") {
        latex = `\\vec{${value1 || emptyBox}}`;
    }

    if (type === "chemical") {
        latex = value1 || emptyBox;

        if (value2) {
            latex += `_{${value2}}`;
        }

        if (value3) {
            latex += `^{${value3}}`;
        }
    }

    try {
        if (typeof katex !== "undefined") {
            preview.innerHTML = katex.renderToString(latex, {
                throwOnError: false,
                displayMode: true
            });
        } else {
            preview.innerHTML = `<span class="text-red-500">KaTeX belum terbaca.</span>`;
        }
    } catch (e) {
        preview.innerHTML = `<span class="text-red-500">Rumus belum valid.</span>`;
    }
}

function showEquationBuilder(type) {
    const panel = document.getElementById("equationBuilderPanel");
    if (!panel) return;

    panel.dataset.type = type;

    let title = "";
    let html = "";

    if (type === "fraction") {
        title = "Buat Pecahan";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('fraction')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Pembilang, contoh: x^2">
            ${builderMiniToolbar("builderValue1")}

            <input id="builderValue2" oninput="updateBuilderPreview('fraction')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Penyebut, contoh: y + 1">
            ${builderMiniToolbar("builderValue2")}

            <button type="button" onclick="applyEquationBuilder('fraction')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Pecahan
            </button>
        `;
    }

    if (type === "power") {
        title = "Buat Pangkat";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('power')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Bilangan/variabel, contoh: v">
            ${builderMiniToolbar("builderValue1")}

            <input id="builderValue2" oninput="updateBuilderPreview('power')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Pangkat, contoh: 2">
            ${builderMiniToolbar("builderValue2")}

            <button type="button" onclick="applyEquationBuilder('power')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Pangkat
            </button>
        `;
    }

    if (type === "root") {
        title = "Buat Akar";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('root')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Isi akar, contoh: x^2 + y^2">
            ${builderMiniToolbar("builderValue1")}

            <button type="button" onclick="applyEquationBuilder('root')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Akar
            </button>
        `;
    }

    if (type === "integral") {
        title = "Buat Integral";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('integral')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Fungsi, contoh: x^2">
            ${builderMiniToolbar("builderValue1")}

            <input id="builderValue2" oninput="updateBuilderPreview('integral')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Variabel, contoh: x">

            <button type="button" onclick="applyEquationBuilder('integral')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Integral
            </button>
        `;
    }

    if (type === "limit") {
        title = "Buat Limit";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('limit')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Variabel, contoh: x">

            <input id="builderValue2" oninput="updateBuilderPreview('limit')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Menuju, contoh: 0">

            <input id="builderValue3" oninput="updateBuilderPreview('limit')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Fungsi, contoh: sin(x)/x">
            ${builderMiniToolbar("builderValue3")}

            <button type="button" onclick="applyEquationBuilder('limit')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Limit
            </button>
        `;
    }

    if (type === "derivative") {
        title = "Buat Turunan";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('derivative')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Fungsi, contoh: x^2">
            ${builderMiniToolbar("builderValue1")}

            <input id="builderValue2" oninput="updateBuilderPreview('derivative')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Variabel, contoh: x">

            <select id="builderValue3" onchange="updateBuilderPreview('derivative')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="first">Turunan pertama</option>
                <option value="second">Turunan kedua</option>
            </select>

            <button type="button" onclick="applyEquationBuilder('derivative')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Turunan
            </button>
        `;
    }

    if (type === "index") {
        title = "Buat Indeks";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('index')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Simbol/variabel, contoh: H">

            <input id="builderValue2" oninput="updateBuilderPreview('index')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Indeks bawah, contoh: 2">

            <button type="button" onclick="applyEquationBuilder('index')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Indeks
            </button>
        `;
    }

    if (type === "vector") {
        title = "Buat Vektor";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('vector')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Simbol vektor, contoh: F">

            <button type="button" onclick="applyEquationBuilder('vector')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Vektor
            </button>
        `;
    }

    if (type === "chemical") {
        title = "Buat Rumus Kimia";
        html = `
            <input id="builderValue1" oninput="updateBuilderPreview('chemical')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Simbol unsur, contoh: SO">

            <input id="builderValue2" oninput="updateBuilderPreview('chemical')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Subscript bawah, contoh: 4">

            <input id="builderValue3" oninput="updateBuilderPreview('chemical')" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Superscript atas, contoh: 2-">

            <button type="button" onclick="applyEquationBuilder('chemical')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Rumus Kimia
            </button>
        `;
    }

    if (type === "matrix") {
        title = "Buat Matriks";
        html = `
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs text-gray-600">Baris</label>
                    <input id="matrixRows" type="number" min="1" max="6" value="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="text-xs text-gray-600">Kolom</label>
                    <input id="matrixCols" type="number" min="1" max="6" value="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <button type="button" onclick="generateMatrixInputs()" class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold">
                Buat Grid Matriks
            </button>

            <div id="matrixInputContainer"></div>

            <button type="button" onclick="applyEquationBuilder('matrix')" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
                Tambahkan Matriks
            </button>
        `;
    }

    panel.innerHTML = `
        <div class="border border-blue-200 bg-blue-50 rounded-xl p-3 space-y-2">
            <div class="flex justify-between items-center">
                <p class="text-sm font-semibold text-blue-700">${title}</p>
                <button type="button" onclick="closeEquationBuilder()" class="text-xs text-gray-500 hover:text-red-600">
                    Tutup
                </button>
            </div>

            ${html}

            <div id="builderPreview" class="min-h-[55px] bg-white border border-blue-200 rounded-lg px-3 py-2 flex items-center justify-center text-sm text-gray-500">
                Preview builder muncul di sini.
            </div>
        </div>
    `;

    const firstInput = document.getElementById("builderValue1");
    if (firstInput) firstInput.focus();

    document.querySelectorAll(".mini-eq-btn").forEach(btn => {
        btn.className =
            "h-7 px-2 rounded-md bg-white border border-indigo-200 hover:bg-indigo-100 text-indigo-700 text-[11px] font-bold transition flex items-center justify-center";
    });
}

function closeEquationBuilder() {
    const panel = document.getElementById("equationBuilderPanel");
    if (panel) panel.innerHTML = "";
}

function generateMatrixInputs() {

    const rows =
        parseInt(document.getElementById("matrixRows")?.value || 2);

    const cols =
        parseInt(document.getElementById("matrixCols")?.value || 2);

    const container =
        document.getElementById("matrixInputContainer");

    if (!container) return;

    let html = `
        <div class="space-y-2">
    `;

    for (let r = 0; r < rows; r++) {

        html += `<div class="grid gap-2" style="grid-template-columns: repeat(${cols}, minmax(0,1fr));">`;

        for (let c = 0; c < cols; c++) {

            html += `
                <input
                    id="matrix_${r}_${c}"
                    class="border border-gray-300 rounded-lg px-2 py-2 text-sm"
                    placeholder="${r+1},${c+1}"
                >
            `;
        }

        html += `</div>`;
    }

    html += `</div>`;

    container.innerHTML = html;
}

function applyEquationBuilder(type) {
    const value1 = document.getElementById("builderValue1")?.value.trim() || "";
    const value2 = document.getElementById("builderValue2")?.value.trim() || "";

    if (type === "fraction") {
        if (!value1 || !value2) return;
        insertEquationText(`\\frac{${value1}}{${value2}}`);
    }

    if (type === "power") {
        if (!value1 || !value2) return;
        insertEquationText(`${value1}^{${value2}}`);
    }

    if (type === "root") {
        if (!value1) return;
        insertEquationText(`\\sqrt{${value1}}`);
    }

    if (type === "integral") {

        if (!value1 || !value2) return;

        insertEquationText(`\\int ${value1} \\, d${value2}`);
    }

    if (type === "derivative") {
        const order =
            document.getElementById("builderValue3")?.value || "first";

        if (!value1 || !value2) return;

        if (order === "second") {
            insertEquationText(`\\frac{d^2}{d${value2}^2}(${value1})`);
        } else {
            insertEquationText(`\\frac{d}{d${value2}}(${value1})`);
        }
    }

    if (type === "index") {
        if (!value1 || !value2) return;

        insertEquationText(`${value1}_{${value2}}`);
    }

    if (type === "limit") {
        const value3 = document.getElementById("builderValue3")?.value.trim() || "";

        if (!value1 || !value2 || !value3) return;

        insertEquationText(`\\lim_{${value1} \\to ${value2}} ${value3}`);
    }

    if (type === "vector") {
        if (!value1) return;

        insertEquationText(`\\vec{${value1}}`);
    }

    if (type === "chemical") {

        const value3 =
            document.getElementById("builderValue3")?.value.trim() || "";

        if (!value1) return;

        let result = value1;

        if (value2) {
            result += `_{${value2}}`;
        }

        if (value3) {
            result += `^{${value3}}`;
        }

        insertEquationText(result);
    }

    if (type === "matrix") {

        const rows =
            parseInt(document.getElementById("matrixRows")?.value || 2);

        const cols =
            parseInt(document.getElementById("matrixCols")?.value || 2);

        let matrixRows = [];

        for (let r = 0; r < rows; r++) {

            let currentRow = [];

            for (let c = 0; c < cols; c++) {

                const value =
                    document.getElementById(`matrix_${r}_${c}`)?.value.trim() || "0";

                currentRow.push(value);
            }

            matrixRows.push(currentRow.join(" & "));
        }

        const latex =
            `\\begin{bmatrix} ${matrixRows.join(" \\\\ ")} \\end{bmatrix}`;

        insertEquationText(latex);
    }

    closeEquationBuilder();
}

function openFractionBuilder() {
    showEquationBuilder("fraction");
}

function openPowerBuilder() {
    showEquationBuilder("power");
}

function openRootBuilder() {
    showEquationBuilder("root");
}

function openQuizEquationModal(target) {

    activeQuizEquationTarget =
        typeof target === "string"
            ? document.querySelector(target)
            : target;

    if (!activeQuizEquationTarget) return;

    Swal.fire({

            width: window.innerWidth < 640 ? "95%" : "34rem",
            padding: 0,

            showConfirmButton: false,
            showCancelButton: false,
            buttonsStyling: false,

            customClass: {
                popup: "rounded-2xl overflow-hidden shadow-2xl",
                htmlContainer: "m-0 p-0"
            },

            html: `

                <div class="flex flex-col max-h-[85vh] bg-white">

                    <!-- ---------- Modal Header ---------- -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 shrink-0">

                        <h3 class="text-xl font-bold text-gray-800">
                            Masukkan Rumus
                        </h3>

                        <button
                            type="button"
                            onclick="Swal.close()"
                            class="text-gray-500 hover:text-gray-800 text-2xl leading-none"
                        >
                            ×
                        </button>

                    </div>

                    <!-- ---------- Modal Body ---------- -->
                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4 text-left">

                        <label class="block text-sm font-medium text-gray-700">
                            Susun rumus
                        </label>

                        <input
                            id="equationInput"
                            type="text"
                            class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Contoh: p = mv"
                            oninput="updateEquationPreview()"
                        >

                        <div id="equationBuilderPanel"></div>

                        <div
                            id="equationPreview"
                            class="min-h-[70px] border border-gray-200 rounded-xl bg-gray-50 px-4 py-3 flex items-center justify-center text-gray-500"
                        >
                            Preview rumus akan muncul di sini.
                        </div>

                        <div class="space-y-3">

                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Struktur</p>
                                <div class="grid grid-cols-6 gap-2">
                                    <button type="button" title="Pecahan" onclick="openFractionBuilder()" class="eq-btn">a/b</button>
                                    <button type="button" title="Pangkat" onclick="openPowerBuilder()" class="eq-btn">xⁿ</button>
                                    <button type="button" title="Indeks" onclick="showEquationBuilder('index')" class="eq-btn">xₙ</button>
                                    <button type="button" title="Akar" onclick="openRootBuilder()" class="eq-btn">√</button>
                                    <button type="button" title="Matriks" onclick="showEquationBuilder('matrix')" class="eq-btn">[ ]</button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Kalkulus</p>
                                <div class="grid grid-cols-6 gap-2">
                                    <button type="button" title="Integral" onclick="showEquationBuilder('integral')" class="eq-btn">∫</button>
                                    <button type="button" title="Limit" onclick="showEquationBuilder('limit')" class="eq-btn">lim</button>
                                    <button type="button" title="Tak Hingga" onclick="insertEquationText('\\\\infty')" class="eq-btn">∞</button>
                                    <button type="button" title="Turunan" onclick="showEquationBuilder('derivative')" class="eq-btn">d/dx</button>
                                    <button type="button" title="dx" onclick="insertEquationText('dx')" class="eq-btn">dx</button>
                                    <button type="button" title="dy" onclick="insertEquationText('dy')" class="eq-btn">dy</button>
                                    <button type="button" title="dt" onclick="insertEquationText('dt')" class="eq-btn">dt</button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Trigonometri</p>
                                <div class="grid grid-cols-6 gap-2">
                                    <button type="button" title="Sinus" onclick="insertTrigFunction('sin')" class="eq-btn">sin</button>
                                    <button type="button" title="Cosinus" onclick="insertTrigFunction('cos')" class="eq-btn">cos</button>
                                    <button type="button" title="Tangen" onclick="insertTrigFunction('tan')" class="eq-btn">tan</button>
                                    <button type="button" title="Cosecan" onclick="insertTrigFunction('csc')" class="eq-btn">csc</button>
                                    <button type="button" title="Secan" onclick="insertTrigFunction('sec')" class="eq-btn">sec</button>
                                    <button type="button" title="Cotangen" onclick="insertTrigFunction('cot')" class="eq-btn">cot</button>

                                    <button type="button" title="Sinus Invers" onclick="insertTrigFunction('asin')" class="eq-btn">sin⁻¹</button>
                                    <button type="button" title="Cosinus Invers" onclick="insertTrigFunction('acos')" class="eq-btn">cos⁻¹</button>
                                    <button type="button" title="Tangen Invers" onclick="insertTrigFunction('atan')" class="eq-btn">tan⁻¹</button>

                                    <button type="button" title="Sinus Kuadrat" onclick="insertTrigFunction('sin2')" class="eq-btn">sin²</button>
                                    <button type="button" title="Cosinus Kuadrat" onclick="insertTrigFunction('cos2')" class="eq-btn">cos²</button>
                                    <button type="button" title="Tangen Kuadrat" onclick="insertTrigFunction('tan2')" class="eq-btn">tan²</button>

                                    <button type="button" title="Derajat" onclick="insertTrigFunction('degree')" class="eq-btn">°</button>
                                    <button type="button" title="Identitas Trigonometri" onclick="insertTrigFunction('identity')" class="eq-btn">sin²+cos²</button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Huruf Yunani</p>
                                <div class="grid grid-cols-6 gap-2">
                                    <button type="button" title="Delta" onclick="insertEquationText('\\\\Delta')" class="eq-btn">Δ</button>
                                    <button type="button" title="Sigma" onclick="insertEquationText('\\\\sum')" class="eq-btn">Σ</button>
                                    <button type="button" title="Theta" onclick="insertEquationText('\\\\theta')" class="eq-btn">θ</button>
                                    <button type="button" title="Lambda" onclick="insertEquationText('\\\\lambda')" class="eq-btn">λ</button>
                                    <button type="button" title="Mu" onclick="insertEquationText('\\\\mu')" class="eq-btn">μ</button>
                                    <button type="button" title="Pi" onclick="insertEquationText('\\\\pi')" class="eq-btn">π</button>
                                    <button type="button" title="Omega" onclick="insertEquationText('\\\\omega')" class="eq-btn">ω</button>
                                    <button type="button" title="Alpha" onclick="insertEquationText('\\\\alpha')" class="eq-btn">α</button>
                                    <button type="button" title="Beta" onclick="insertEquationText('\\\\beta')" class="eq-btn">β</button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Operator</p>
                                <div class="grid grid-cols-6 gap-2">
                                    <button type="button" title="Kali" onclick="insertEquationText('\\\\times')" class="eq-btn">×</button>
                                    <button type="button" title="Bagi" onclick="insertEquationText('\\\\div')" class="eq-btn">÷</button>
                                    <button type="button" title="Plus Minus" onclick="insertEquationText('\\\\pm')" class="eq-btn">±</button>
                                    <button type="button" title="Aproksimasi" onclick="insertEquationText('\\\\approx')" class="eq-btn">≈</button>
                                    <button type="button" title="Tidak Sama" onclick="insertEquationText('\\\\neq')" class="eq-btn">≠</button>
                                    <button type="button" title="Kurang dari sama dengan" onclick="insertEquationText('\\\\leq')" class="eq-btn">≤</button>
                                    <button type="button" title="Lebih dari sama dengan" onclick="insertEquationText('\\\\geq')" class="eq-btn">≥</button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Fisika & Kimia</p>
                                <div class="grid grid-cols-6 gap-2">
                                    <button type="button" title="Vektor" onclick="showEquationBuilder('vector')" class="eq-btn">→</button>
                                    <button type="button" title="Paralel" onclick="insertEquationText('\\\\parallel')" class="eq-btn">∥</button>
                                    <button type="button" title="Tegak Lurus" onclick="insertEquationText('\\\\perp')" class="eq-btn">⊥</button>
                                    <button type="button" title="Reaksi Kimia" onclick="insertEquationText('\\\\rightleftharpoons')" class="eq-btn">⇌</button>
                                    <button type="button" title="Subscript Kimia" onclick="showEquationBuilder('chemical')" class="eq-btn">H₂</button>                            </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1">Statistik</p>
                                <div class="grid grid-cols-6 gap-2">
                                    <button type="button" title="Rata-rata" onclick="insertEquationText('\\\\bar{x}')" class="eq-btn">x̄</button>
                                    <button type="button" title="Faktorial" onclick="insertEquationText('n!')" class="eq-btn">n!</button>
                                    <button type="button" title="Peluang" onclick="insertEquationText('P(A)')" class="eq-btn">P(A)</button>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- ---------- Modal Footer ---------- -->
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-white shrink-0">

                        <button
                            type="button"
                            onclick="Swal.close()"
                            class="px-5 py-3 rounded-xl bg-gray-500 hover:bg-gray-600 text-white font-semibold"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            onclick="insertEquationToEditor()"
                            class="px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                        >
                            Sisipkan
                        </button>

                    </div>

                </div>
            `,

            didOpen: () => {

                const swalContainer =
                    document.querySelector(".swal2-container");

                if (swalContainer) {
                    swalContainer.style.zIndex = "3000";
                }

                const input =
                    document.getElementById("equationInput");

                if (input) {
                    input.focus();
                }

                document.querySelectorAll(".eq-btn")
                    .forEach(btn => {

                        btn.className = `
                            h-8 min-w-8 px-2
                            rounded-lg
                            bg-indigo-100 hover:bg-indigo-200
                            text-indigo-700
                            text-xs font-bold
                            transition
                            flex items-center justify-center
                        `;
                    });

            }

        });

}

window.openQuizEquationModal = openQuizEquationModal;
window.insertEquationText = insertEquationText;
window.insertTrigFunction = insertTrigFunction;
window.updateEquationPreview = updateEquationPreview;
window.insertEquationToEditor = insertEquationToEditor;
window.insertBuilderText = insertBuilderText;
window.updateBuilderPreview = updateBuilderPreview;
window.showEquationBuilder = showEquationBuilder;
window.closeEquationBuilder = closeEquationBuilder;
window.applyEquationBuilder = applyEquationBuilder;
window.openFractionBuilder = openFractionBuilder;
window.openPowerBuilder = openPowerBuilder;
window.openRootBuilder = openRootBuilder;
window.openQuizEquationModal = openQuizEquationModal;
window.insertEquationText = insertEquationText;
window.insertTrigFunction = insertTrigFunction;
window.updateEquationPreview = updateEquationPreview;
window.insertEquationToEditor = insertEquationToEditor;
window.insertBuilderText = insertBuilderText;
window.updateBuilderPreview = updateBuilderPreview;
window.showEquationBuilder = showEquationBuilder;
window.closeEquationBuilder = closeEquationBuilder;
window.applyEquationBuilder = applyEquationBuilder;
window.generateMatrixInputs = generateMatrixInputs;
window.openFractionBuilder = openFractionBuilder;
window.openPowerBuilder = openPowerBuilder;
window.openRootBuilder = openRootBuilder;

/* =======================================================
    WACANA TABLE ANSWER CONFIG
======================================================= */
let selectedWacanaTableCells = [];
let currentWacanaTables = [];
let currentWacanaTableIndex = 0;
let currentTableMode = "info";

function resetWacanaTableAnswerConfig() {
    selectedWacanaTableCells = [];
    currentWacanaTables = [];
    currentWacanaTableIndex = 0;
    currentTableMode = "info";

    const wrapper = document.getElementById("wacanaTableAnswerWrapper");
    const preview = document.getElementById("wacanaTableAnswerPreview");
    const configInput = document.getElementById("answerTableConfig");
    const tableMode = document.getElementById("tableMode");
    const tableModeInfo = document.getElementById("tableModeInfo");

    if (wrapper) wrapper.classList.add("hidden");
    if (preview) preview.innerHTML = "";
    if (configInput) configInput.value = "";
    if (tableMode) tableMode.value = "info";
    if (tableModeInfo) tableModeInfo.textContent = "";
}

function parseTableConfig(rawConfig) {
    if (!rawConfig) return null;

    try {
        return JSON.parse(rawConfig);
    } catch (e) {
        return null;
    }
}

function isEmptyAnswerCell(cell) {
    const text = (cell.textContent || "").trim();
    return text === "" || text === "...";
}

function renderWacanaTableAnswerConfig(wacanaId, tables = [], existingConfig = null) {
    const wrapper = document.getElementById("wacanaTableAnswerWrapper");
    const preview = document.getElementById("wacanaTableAnswerPreview");
    const configInput = document.getElementById("answerTableConfig");
    const tableMode = document.getElementById("tableMode");

    if (!wrapper || !preview || !configInput || !tableMode) return;

    if (!wacanaId || !Array.isArray(tables) || tables.length === 0) {
        resetWacanaTableAnswerConfig();
        return;
    }

    currentWacanaTables = tables;
    currentWacanaTableIndex = Number(existingConfig?.table_index ?? 0);
    currentTableMode = existingConfig?.mode || "info";

    if (!["info", "key_based", "rubric_based"].includes(currentTableMode)) {
        currentTableMode = "info";
    }

    selectedWacanaTableCells = Array.isArray(existingConfig?.input_cells)
        ? existingConfig.input_cells
        : [];

    if (!currentWacanaTables[currentWacanaTableIndex]) {
        currentWacanaTableIndex = 0;
    }

    tableMode.value = currentTableMode;
    wrapper.classList.remove("hidden");

    preview.innerHTML = `
        ${currentWacanaTables.length > 1 ? `
            <div class="mb-3">
                <label class="form-label">
                    Pilih Tabel
                </label>
                <select
                    id="wacanaTableIndexSelect"
                    class="border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    ${currentWacanaTables.map((_, index) => `
                        <option value="${index}" ${index === currentWacanaTableIndex ? "selected" : ""}>
                            Tabel ${index + 1}
                        </option>
                    `).join("")}
                </select>
            </div>
        ` : ""}
        <div id="selectedWacanaTableArea">
            ${currentWacanaTables[currentWacanaTableIndex]}
        </div>
    `;

    const tableSelect = document.getElementById("wacanaTableIndexSelect");

    if (tableSelect) {
        tableSelect.addEventListener("change", function() {
            currentWacanaTableIndex = parseInt(this.value) || 0;
            selectedWacanaTableCells = [];

            renderWacanaTableAnswerConfig(
                wacanaId,
                currentWacanaTables,
                {
                    mode: currentTableMode,
                    table_index: currentWacanaTableIndex,
                    input_cells: []
                }
            );
        });
    }

    makeWacanaTableSelectable(wacanaId);
    updateTableModeInfo();
    updateWacanaTableConfigInput(wacanaId);
}

function makeWacanaTableSelectable(wacanaId) {
    const tableArea = document.getElementById("selectedWacanaTableArea");
    if (!tableArea) return;

    tableArea.querySelectorAll("table").forEach(table => {
        table.classList.add("min-w-full", "border", "border-gray-300", "border-collapse");
    });

    tableArea.querySelectorAll("th, td").forEach(cell => {
        cell.classList.add("border", "border-gray-300", "px-3", "py-2");
    });

    tableArea.querySelectorAll("td").forEach(cell => {
        const row = cell.parentElement.rowIndex;
        const col = cell.cellIndex;

        cell.classList.remove("cursor-pointer", "hover:bg-yellow-100", "bg-yellow-200");

        if (currentTableMode === "info") {
            return;
        }

        if (!isEmptyAnswerCell(cell)) {
            cell.classList.add("bg-gray-50", "text-gray-500");
            return;
        }

        const existing = selectedWacanaTableCells.find(
            item => Number(item.row) === row && Number(item.col) === col
        );

        cell.classList.add("bg-yellow-50");

        if (currentTableMode === "key_based") {
            cell.innerHTML = `
                <input
                    type="text"
                    class="table-key-input w-full min-w-[100px] border border-yellow-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-yellow-500 focus:outline-none"
                    placeholder="Kunci"
                    data-row="${row}"
                    data-col="${col}"
                    value="${existing?.answer ? String(existing.answer).replace(/"/g, "&quot;") : ""}"
                >
            `;
        } else if (currentTableMode === "rubric_based") {

            if (!existing) {
                selectedWacanaTableCells.push({
                    row,
                    col
                });
            }

            cell.innerHTML = `
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">
                    Input siswa
                </span>
            `;
        }
    });

    tableArea.querySelectorAll(".table-key-input").forEach(input => {
        input.addEventListener("input", function() {
            const row = Number(this.dataset.row);
            const col = Number(this.dataset.col);
            const answer = this.value.trim();

            const index = selectedWacanaTableCells.findIndex(
                item => Number(item.row) === row && Number(item.col) === col
            );

            if (answer === "") {
                if (index >= 0) selectedWacanaTableCells.splice(index, 1);
            } else {
                if (index >= 0) {
                    selectedWacanaTableCells[index].answer = answer;
                } else {
                    selectedWacanaTableCells.push({ row, col, answer });
                }
            }

            updateWacanaTableConfigInput(wacanaId);
        });
    });

    if (currentTableMode === "rubric_based") {
        updateWacanaTableConfigInput(wacanaId);
    }
}

function updateTableModeInfo() {
    const tableModeInfo = document.getElementById("tableModeInfo");
    if (!tableModeInfo) return;

    if (currentTableMode === "info") {
        tableModeInfo.textContent = "Tabel hanya ditampilkan sebagai informasi. Tidak ada input siswa dan tidak dinilai.";
    } else if (currentTableMode === "rubric_based") {
        tableModeInfo.textContent = "Sel kosong atau berisi ... akan otomatis menjadi input siswa. Penilaian menggunakan rubrik wajib.";
    }
}

function updateWacanaTableConfigInput(wacanaId) {
    const configInput = document.getElementById("answerTableConfig");
    if (!configInput) return;

    if (currentTableMode === "info") {
        configInput.value = JSON.stringify({
            source: "wacana_table",
            mode: "info",
            wacana_id: parseInt(wacanaId),
            table_index: currentWacanaTableIndex,
            input_cells: []
        });
        return;
    }

    configInput.value = JSON.stringify({
        source: "wacana_table",
        mode: currentTableMode,
        wacana_id: parseInt(wacanaId),
        table_index: currentWacanaTableIndex,
        input_cells: selectedWacanaTableCells
    });
}

document.getElementById("tableMode")?.addEventListener("change", function() {
    currentTableMode = this.value;

    updateRubricRequirement();

    selectedWacanaTableCells = [];

    if (currentTableMode === "rubric_based") {
        const tableArea = document.getElementById("selectedWacanaTableArea");

        if (tableArea) {
            tableArea.querySelectorAll("td").forEach(cell => {
                if (isEmptyAnswerCell(cell)) {
                    selectedWacanaTableCells.push({
                        row: cell.parentElement.rowIndex,
                        col: cell.cellIndex
                    });
                }
            });
        }
    }

    renderWacanaTableAnswerConfig(
        document.getElementById("questionWacana").value,
        currentWacanaTables,
        {
            mode: currentTableMode,
            table_index: currentWacanaTableIndex,
            input_cells: selectedWacanaTableCells
        }
    );
});

        /* =======================================================
            MODAL SYSTEM
        ======================================================= */
        function openQuestionModal(
            mode = "add", preserveData = false
        ) {

            if (
                mode ===
                "add"
            ) {

                form.reset();

                document.getElementById(
                    "questionId"
                ).value = "";

                modalTitle.textContent =
                    "Tambah Soal";

                /* Default radio */
                const firstRadio =
                    form.querySelector(
                        '[name="correct_answer"][value="0"]'
                    );

                if (
                    firstRadio
                ) {
                    firstRadio.checked =
                        true;
                }

            }

            toggleQuestionType(preserveData);

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            setTimeout(() => {
                questionTextInput.focus();
            }, 100);

            document.body.style.overflow =
                "hidden";

            document.documentElement.style.overflow =
                "hidden";

            if (
                window.lucide
            ) {
                lucide.createIcons();
            }

        }

        function closeQuestionModal() {

            modal.classList.add("hidden");
            modal.classList.remove("flex");

            document.body.style.overflow =
                "";

            document.documentElement.style.overflow =
                "";

        }

        /* ---------- Open ---------- */
document.getElementById("newQuestion")?.addEventListener(
    "click",
    function(e) {

        e.preventDefault();

        form.reset();

        resetWacanaTableAnswerConfig();

        document.getElementById("questionId").value = "";

        document.getElementById("questionType").value = "multiple_choice";

        toggleQuestionType(false);

        openQuestionModal("add");

    }
);

        /* ---------- Close ---------- */
        document.querySelectorAll(
            '[data-close="questionModal"]'
        ).forEach(
            btn => {

                btn.addEventListener(
                    "click",
                    function(e) {

                        e.preventDefault();

                        closeQuestionModal();

                    }
                );

            }
        );

        /* ---------- Outside Click ---------- */
        modal.addEventListener(
            "click",
            function(e) {

                if (
                    e.target ===
                    modal
                ) {

                    closeQuestionModal();

                }

            }
        );

        /* ---------- ESC ---------- */
        document.addEventListener(
            "keydown",
            function(e) {

                if (
                    e.key ===
                    "Escape"
                ) {

                    closeQuestionModal();

                }

            }
        );

        /* =======================================================
            QUESTION TYPE TOGGLE
        ======================================================= */
function toggleQuestionType(preserveData = false) {

    const type = questionType.value;

    const mcSection = document.getElementById("multipleChoiceSection");
    const answerKeySection = document.getElementById("answerKeySection");
    const answerKeyTitle = document.getElementById("answerKeyTitle");
    const answerKeyText = document.getElementById("answerKeyText");
    const shortAnswerInfo = document.getElementById("shortAnswerInfo");

    const rubricSection = document.getElementById("rubricSection");
    const useRubricToggle = document.getElementById("useRubricToggle");
    const rubricInputWrapper = document.getElementById("rubricInputWrapper");
    const rubricText = document.getElementById("rubricText");

    const statementWrapper = document.getElementById("statementTypeWrapper");
    const pointsWrapper = document.getElementById("pointsWrapper");
    const pointsInput = document.getElementById("questionPoints");

    const questionWacana = document.getElementById("questionWacana");
    const wacanaTableAnswerWrapper = document.getElementById("wacanaTableAnswerWrapper");
    const answerTableConfig = document.getElementById("answerTableConfig");

    const optionInputs = form.querySelectorAll('[name="options[]"]');
    const radioInputs = form.querySelectorAll('[name="correct_answer"]');

    /* =======================================================
        RESET DASAR
    ======================================================= */
    mcSection.classList.add("hidden");
    answerKeySection.classList.add("hidden");
    shortAnswerInfo.classList.add("hidden");
    rubricSection.classList.add("hidden");
    rubricInputWrapper.classList.add("hidden");
    
    statementWrapper.classList.add("hidden");
    pointsWrapper.classList.remove("hidden");

    wacanaTableAnswerWrapper.classList.add("hidden");

    if (type !== "essay" && !preserveData) {
        resetWacanaTableAnswerConfig();
    }

    pointsInput.removeAttribute("readonly");

    if (!preserveData) {

        /* Reset opsi */
        optionInputs.forEach((input, index) => {
            input.value = "";
            input.readOnly = false;
            input.placeholder = `Opsi ${String.fromCharCode(65 + index)}`;
        });

        /* Reset radio */
        radioInputs.forEach((radio, index) => {
            radio.disabled = false;
            radio.checked = (index === 0);
        });

        /* Reset jawaban teks */
        answerKeyText.value = "";

        useRubricToggle.checked = false;
        useRubricToggle.disabled = false;
        clearStructuredRubric();

    }

    /* =======================================================
        TIPE SOAL
    ======================================================= */

    /* ANGKET */
    if (type === "likert") {

        pointsInput.value = 4;
        pointsInput.setAttribute("readonly", true);

        statementWrapper.classList.remove("hidden");

        /* Sembunyikan poin */
        pointsWrapper.classList.add("hidden");

        /* Sembunyikan opsi */
        mcSection.classList.add("hidden");

    }

    /* PILIHAN GANDA */
    else if (type === "multiple_choice") {

        mcSection.classList.remove("hidden");

    }

    /* PILIHAN GANDA BERALASAN */
    else if (type === "reasoned_multiple_choice") {

        shortAnswerInfo.classList.add("hidden");

        pointsInput.value = 3;
        pointsInput.setAttribute("readonly", true);

        mcSection.classList.remove("hidden");

        answerKeySection.classList.remove("hidden");
        answerKeyTitle.textContent = "Kunci Jawaban Alasan dari Opsi Jawaban";
        answerKeyText.placeholder = "Masukkan alasan jawaban...";

        pointsWrapper.classList.add("hidden");

    }

    /* ISIAN SINGKAT */
    else if (type === "short_answer") {

        answerKeySection.classList.remove("hidden");
        shortAnswerInfo.classList.remove("hidden");
        answerKeyTitle.textContent = "Kunci Jawaban";
        answerKeyText.placeholder = "Masukkan kunci jawaban singkat...";

    }

    /* URAIAN */
    else if (type === "essay") {

        pointsInput.value = 3;
        pointsInput.setAttribute("readonly", true);

        answerKeySection.classList.remove("hidden");
        answerKeyTitle.textContent = "Kunci Jawaban";
        answerKeyText.placeholder = "Masukkan kunci jawaban uraian...";

        rubricSection.classList.remove("hidden");

        pointsWrapper.classList.add("hidden");

        if (questionWacana.value) {
            const tables = wacanaTableMap[questionWacana.value] || [];

            if (tables.length > 0) {
                renderWacanaTableAnswerConfig(
                    questionWacana.value,
                    tables,
                    null
                );
            }
        }

        updateRubricRequirement();

    }
}

questionType.addEventListener(
    "change",
    function() {
        toggleQuestionType(false);
    }
);

document.getElementById("useRubricToggle")?.addEventListener("change", function() {

    const wrapper = document.getElementById("rubricInputWrapper");

    if (this.checked) {
        wrapper.classList.remove("hidden");
    } else {
        wrapper.classList.add("hidden");
        clearStructuredRubric();
    }

});

document.getElementById("questionWacana").addEventListener("change", function () {

    const type = questionType.value;
    const wacanaId = this.value;
    const tables = wacanaTableMap[wacanaId] || [];

    if (!wacanaId || tables.length === 0) {
        resetWacanaTableAnswerConfig();
        return;
    }

    if (type !== "essay") {
        resetWacanaTableAnswerConfig();
        return;
    }

    renderWacanaTableAnswerConfig(
        wacanaId,
        tables,
        {
            mode: "info",
            table_index: 0,
            input_cells: []
        }
    );

});

function renderMathContent(container) {
    if (!container || typeof katex === "undefined") return;

    container.innerHTML = container.innerHTML.replace(
        /\\\((.*?)\\\)/gs,
        function(match, latex) {
            try {
                return katex.renderToString(latex.trim(), {
                    throwOnError: false,
                    displayMode: false
                });
            } catch (e) {
                return match;
            }
        }
    );

    container.innerHTML = container.innerHTML.replace(
        /\$\$(.*?)\$\$/gs,
        function(match, latex) {
            try {
                return katex.renderToString(latex.trim(), {
                    throwOnError: false,
                    displayMode: false
                });
            } catch (e) {
                return match;
            }
        }
    );
}

function renderAllQuestionMath() {
    document.querySelectorAll(".quiz-math-content").forEach(el => {
        renderMathContent(el);
    });
}

/* =======================================================
    SAVE TABLE STATE
======================================================= */
function saveState() {

    sessionStorage.setItem(
        STORAGE_KEYS.search,
        searchInput.value
    );

    sessionStorage.setItem(
        STORAGE_KEYS.rows,
        rowsPerPage.value
    );

    sessionStorage.setItem(
        STORAGE_KEYS.page,
        currentPage
    );

}

        /* =======================================================
            PAGINATION SYSTEM
        ======================================================= */
        function renderTable() {

            questionRows.forEach(
                row => {
                    row.style.display =
                        "none";
                }
            );

            /* Empty DB */
            if (
                questionRows.length ===
                0
            ) {

                emptyRow.classList.remove(
                    "hidden"
                );

                noResultRow.classList.add(
                    "hidden"
                );

                pageInfo.textContent =
                    "0 data";

                pagination.innerHTML =
                    "";

                saveState();

                return;

            }

            /* Empty Search */
            if (
                filteredRows.length ===
                0
            ) {

                emptyRow.classList.add(
                    "hidden"
                );

                noResultRow.classList.remove(
                    "hidden"
                );

                pageInfo.textContent =
                    "0 data ditemukan";

                pagination.innerHTML =
                    "";

                saveState();

                return;

            }

            emptyRow.classList.add(
                "hidden"
            );

            noResultRow.classList.add(
                "hidden"
            );

            const perPage =
                rowsPerPage.value ===
                "all"
                    ? filteredRows.length
                    : parseInt(
                        rowsPerPage.value
                    );

            const totalPages =
                rowsPerPage.value ===
                "all"
                    ? 1
                    : Math.max(
                        Math.ceil(
                            filteredRows.length /
                            perPage
                        ),
                        1
                    );

            if (
                currentPage >
                totalPages
            ) {
                currentPage =
                    totalPages;
            }

            const start =
                (currentPage - 1) *
                perPage;

            const end =
                rowsPerPage.value ===
                "all"
                    ? filteredRows.length
                    : start +
                    perPage;

            filteredRows
                .slice(
                    start,
                    end
                )
                .forEach(
                    row => {
                        row.style.display =
                            "";
                    }
                );

            pageInfo.textContent =
                rowsPerPage.value ===
                "all"
                    ? `Menampilkan ${filteredRows.length} data`
                    : `Menampilkan ${start + 1} - ${Math.min(end, filteredRows.length)} dari ${filteredRows.length} data`;

            renderPagination(
                totalPages
            );

            saveState();

            setTimeout(() => {
                renderAllQuestionMath();
            }, 50);

        }

        function renderPagination(
            totalPages
        ) {

            pagination.innerHTML =
                "";

            if (
                rowsPerPage.value ===
                    "all" ||
                totalPages <= 1
            ) return;

            const createButton = (
                label,
                disabled,
                onClick,
                active = false
            ) => {

                const btn =
                    document.createElement(
                        "button"
                    );

                btn.textContent =
                    label;

                btn.className = `
                    inline-flex items-center justify-center
                    min-w-8 h-8 px-2 rounded-md text-sm font-medium
                    border transition-all duration-200
                    ${active
                        ? "bg-blue-600 text-white border-blue-600 shadow-sm"
                        : disabled
                            ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                            : "bg-white text-gray-700 border-gray-300 hover:bg-blue-100 hover:text-blue-700 hover:border-blue-300"}
                `;

                btn.disabled =
                    disabled;

                if (
                    !disabled
                ) {
                    btn.onclick =
                        onClick;
                }

                pagination.appendChild(
                    btn
                );

            };

            /* Previous */
            createButton(
                "Sebelumnya",
                currentPage ===
                    1,
                () => {
                    currentPage--;
                    renderTable();
                }
            );

            /* Numbers */
            for (
                let i = 1;
                i <= totalPages;
                i++
            ) {

                createButton(
                    i,
                    false,
                    () => {
                        currentPage =
                            i;
                        renderTable();
                    },
                    currentPage ===
                        i
                );

            }

            /* Next */
            createButton(
                "Selanjutnya",
                currentPage ===
                    totalPages,
                () => {
                    currentPage++;
                    renderTable();
                }
            );

        }

        /* =======================================================
            SEARCH
        ======================================================= */
        searchInput.addEventListener(
            "input",
            function() {

                const keyword =
                    searchInput.value
                        .toLowerCase()
                        .trim();

                filteredRows =
                    keyword === ""
                        ? [
                            ...questionRows
                        ]
                        : questionRows.filter(
                            row =>
                                row.innerText
                                    .toLowerCase()
                                    .includes(
                                        keyword
                                    )
                        );

                currentPage = 1;

                renderTable();

            }
        );

        /* =======================================================
            ROW LIMIT
        ======================================================= */
        rowsPerPage.addEventListener(
            "change",
            function() {

                currentPage = 1;

                renderTable();

            }
        );

        /* =======================================================
            EDIT QUESTION
        ======================================================= */
        window.editQuestion =
            function(id) {

            showLoading(
                "Memuat data..."
            );

            fetch(
                `../ajax/question/get_question.php?id=${id}`
            )

            .then(
                res =>
                    res.json()
            )

            .then(
                res => {

                    Swal.close();

                    if (
                        res.status !=
                        1
                    ) {

                        Swal.fire(
                            getResponsiveSwal(
                                "error",
                                "Gagal",
                                res.msg ||
                                "Data tidak ditemukan."
                            )
                        );

                        return;

                    }

                    const data =
                        res.data;

form.reset();

                    document.getElementById(
                        "questionId"
                    ).value =
                        data.id;

                    document.getElementById(
                        "questionText"
                    ).value =
                        data.question ||
                        "";

                    document.getElementById(
                        "questionType"
                    ).value =
                        data.question_type ||
                        "multiple_choice";

                    document.getElementById(
                        "questionPoints"
                    ).value =
                        data.points ||
                        1;

                    document.getElementById(
                        "statementType"
                    ).value =
                        data.statement_type ||
                        "";

                    document.getElementById(
                        "questionWacana"
                    ).value =
                        data.wacana_id ||
                        "";

                    let existingTableConfig = parseTableConfig(data.answer_table_config);

                    if (
                        data.question_type === "essay" &&
                        data.wacana_id &&
                        Array.isArray(data.wacana_tables) &&
                        data.wacana_tables.length > 0
                    ) {
                        renderWacanaTableAnswerConfig(
                            data.wacana_id,
                            data.wacana_tables,
                            existingTableConfig || {
                                mode: "info",
                                table_index: 0,
                                input_cells: []
                            }
                        );
                    } else {
                        resetWacanaTableAnswerConfig();
                    }

                    document.getElementById(
                        "questionPhet"
                    ).value =
                        data.phet_id ||
                        "";

                    document.getElementById("answerKeyText").value =
                        data.answer_key_text || "";

                    /* Multiple choice options */
                    if (
                        data.options &&
                        Array.isArray(
                            data.options
                        )
                    ) {

                        const optionInputs =
                            form.querySelectorAll(
                                '[name="options[]"]'
                            );

                        data.options.forEach(
                            (
                                opt,
                                index
                            ) => {

                                if (
                                    optionInputs[
                                        index
                                    ]
                                ) {

                                    optionInputs[
                                        index
                                    ].value =
                                        opt.option_text ||
                                        "";

                                }

                            }
                        );

                        /* Correct answer */
                        const correctRadio =
                            form.querySelector(
                                `[name="correct_answer"][value="${data.correct_answer_index}"]`
                            );

                        if (
                            correctRadio
                        ) {

                            correctRadio.checked =
                                true;

                        }

                    }

                    modalTitle.textContent =
                        "Edit Soal";

                    openQuestionModal(
                        "edit", true
                    );

                    const useRubricToggle = document.getElementById("useRubricToggle");
                    const rubricInputWrapper = document.getElementById("rubricInputWrapper");
                    const rubricText = document.getElementById("rubricText");

                    fillStructuredRubric(data.rubric_text || "");

                    if (data.rubric_text) {
                        useRubricToggle.checked = true;
                        rubricInputWrapper.classList.remove("hidden");
                    } else {
                        useRubricToggle.checked = false;
                        rubricInputWrapper.classList.add("hidden");
                    }

                    updateRubricRequirement();

                }
            )

            .catch(
                () => {

                    Swal.close();

                    Swal.fire(
                        getResponsiveSwal(
                            "error",
                            "Kesalahan Sistem",
                            "Terjadi kesalahan sistem."
                        )
                    );

                }
            );

        };

        /* =======================================================
            DELETE QUESTION
        ======================================================= */
window.deleteQuestion = function(id) {

    Swal.fire({
        ...getResponsiveSwal(
            "warning",
            "Hapus soal?",
            "Soal ini akan dihapus permanen dari kuis."
        ),
        showCancelButton: true,
        confirmButtonText: "Ya, hapus",
        cancelButtonText: "Batal"
    })

    .then(result => {

        if (!result.isConfirmed) return;

        showLoading("Menghapus soal.");

        const formData = new FormData();
        formData.append("id", id);

        fetch("../ajax/question/delete_question.php", {
            method: "POST",
            body: formData
        })

        .then(res => res.json())

        .then(res => {

            Swal.close();

            if (res.status == 1) {

                showToast(
                    "success",
                    res.msg
                );

                setTimeout(() => {
                    location.reload();
                }, 1200);

            } else {

                Swal.fire(
                    getResponsiveSwal(
                        "error",
                        "Gagal",
                        res.msg || "Gagal menghapus soal."
                    )
                );

            }

        })

        .catch(() => {

            Swal.close();

            Swal.fire(
                getResponsiveSwal(
                    "error",
                    "Kesalahan Sistem",
                    "Terjadi kesalahan sistem."
                )
            );

        });

    });

};

        /* =======================================================
            FORM SUBMIT
        ======================================================= */
        form.addEventListener(
            "submit",
            function(e) {

                e.preventDefault();

                clearInlineErrors();

                const submitBtn = form.querySelector('button[type="submit"]');

                submitBtn.disabled = true;
                submitBtn.classList.add("opacity-70", "cursor-not-allowed");

                const resetSubmitButton = () => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                };

                const scrollToField = field => {
                    field.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });

                    setTimeout(() => {
                        if (typeof field.focus === "function") {
                            field.focus();
                        }
                    }, 200);
                };

                const selectedType = questionType.value;

                const requiredAnswerTypes = [
                    "reasoned_multiple_choice",
                    "short_answer",
                    "essay"
                ];

                /* 1. Validasi pertanyaan */
                if (!questionTextInput.value.trim()) {
                    setInlineError(questionTextInput, "Pertanyaan wajib diisi.");
                    scrollToField(questionTextInput);
                    resetSubmitButton();
                    return;
                }

                /* 2. Validasi poin */
                if (
                    questionPointsInput.value.trim() === "" ||
                    parseInt(questionPointsInput.value) <= 0
                ) {
                    setInlineError(questionPointsInput, "Poin wajib diisi.");
                    scrollToField(questionPointsInput);
                    resetSubmitButton();
                    return;
                }

                /* 3. Validasi opsi untuk PG dan PG beralasan */
                if (
                    selectedType === "multiple_choice" ||
                    selectedType === "reasoned_multiple_choice"
                ) {
                    const optionInputs =
                        form.querySelectorAll('[name="options[]"]');

                    const filledOptions =
                        [...optionInputs].filter(input => input.value.trim() !== "");

                    if (filledOptions.length < 2) {
                        setOptionSectionError("Minimal 2 opsi jawaban diperlukan.");
                        scrollToField(document.getElementById("multipleChoiceSection"));
                        resetSubmitButton();
                        return;
                    }
                }

                /* 4. Validasi kunci jawaban / alasan */
                if (
                    requiredAnswerTypes.includes(selectedType) &&
                    answerKeyTextInput.value.trim() === ""
                ) {
                    let message = "Kunci jawaban wajib diisi.";

                    if (selectedType === "reasoned_multiple_choice") {
                        message = "Kunci jawaban alasan dari opsi jawaban wajib diisi.";
                    }

                    if (selectedType === "short_answer") {
                        message = "Kunci jawaban isian singkat wajib diisi.";
                    }

                    if (selectedType === "essay") {
                        message = "Kunci jawaban uraian wajib diisi.";
                    }

                    setInlineError(answerKeyTextInput, message);
                    scrollToField(answerKeyTextInput);
                    resetSubmitButton();
                    return;
                }

                /* 5. Validasi rubrik uraian */
                if (
                    selectedType === "essay" &&
                    useRubricToggle &&
                    useRubricToggle.checked
                ) {
                    const fields = getStructuredRubricFields();

                    const rubricFields = [
                        {
                            field: fields.score3,
                            message: "Rubrik skor 3 wajib diisi."
                        },
                        {
                            field: fields.score2,
                            message: "Rubrik skor 2 wajib diisi."
                        },
                        {
                            field: fields.score1,
                            message: "Rubrik skor 1 wajib diisi."
                        },
                        {
                            field: fields.score0,
                            message: "Rubrik skor 0 wajib diisi."
                        }
                    ];

                    const invalidField = rubricFields.find(
                        item => !item.field?.value.trim()
                    );

                    if (invalidField) {

                        setInlineError(
                            invalidField.field,
                            invalidField.message
                        );

                        scrollToField(invalidField.field);
                        resetSubmitButton();
                        return;
                    }

                    rubricTextInput.value = buildStructuredRubricText();

                } else {

                    rubricTextInput.value = "";

                }

                const formData =
                    new FormData(
                        form
                    );

                if (
                    selectedType === "essay" &&
                    useRubricToggle &&
                    useRubricToggle.checked
                ) {
                    formData.set("use_rubric", "1");
                }

                fetch(
                    "../ajax/question/save_question.php",
                    {
                        method: "POST",
                        body: formData
                    }
                )

                .then(
                    res =>
                        res.json()
                )

                .then(
                    res => {

                        Swal.close();

                        submitBtn.disabled = false;
                        submitBtn.classList.remove("opacity-70", "cursor-not-allowed");

                        if (res.status == 1) {

                            closeQuestionModal();

                            showToast("success", res.msg);

                            setTimeout(() => {
                                location.reload();
                            }, 1200);

                            return;
                        }

                        if (res.status == 2 || res.status == 0) {

                            const msg = (res.msg || "").toLowerCase();

                            if (
                                msg.includes("opsi") ||
                                msg.includes("pilihan")
                            ) {
                                const optionSection =
                                    document.getElementById("multipleChoiceSection");

                                setOptionSectionError(
                                    res.msg || "Minimal 2 opsi jawaban diperlukan."
                                );

                                scrollToField(optionSection);
                                return;
                            }

                            if (msg.includes("pertanyaan") || msg.includes("soal")) {
                                setInlineError(questionTextInput, res.msg);
                                scrollToField(questionTextInput);
                                return;
                            }

                            if (msg.includes("poin")) {
                                setInlineError(questionPointsInput, res.msg);
                                scrollToField(questionPointsInput);
                                return;
                            }

                            if (
                                msg.includes("kunci") ||
                                msg.includes("jawaban") ||
                                msg.includes("alasan")
                            ) {
                                setInlineError(answerKeyTextInput, res.msg);
                                scrollToField(answerKeyTextInput);
                                return;
                            }

                            if (msg.includes("rubrik")) {
                                setInlineError(rubricTextInput, res.msg);
                                scrollToField(rubricTextInput);
                                return;
                            }

                            setInlineError(questionTextInput, res.msg || "Data soal belum lengkap.");
                            scrollToField(questionTextInput);
                            return;
                        }

                        Swal.fire(
                            getResponsiveSwal(
                                "error",
                                "Gagal",
                                res.msg || "Gagal menyimpan soal."
                            )
                        );

                    }
                )

                .catch(
                    () => {

                        Swal.close();

                        Swal.fire(
                            getResponsiveSwal(
                                "error",
                                "Kesalahan Sistem",
                                "Terjadi kesalahan sistem."
                            )
                        );

                    }
                );

            }
        );

/* =======================================================
    INITIALIZE
======================================================= */

/* AUTO SCROLL & HIGHLIGHT QUESTION FROM URL */
/* AUTO SCROLL & HIGHLIGHT QUESTION FROM URL */
const urlParams = new URLSearchParams(window.location.search);
const targetQuestionId = urlParams.get("question_id");

if (targetQuestionId) {

    setTimeout(() => {

        const targetRow = document.querySelector(
            `tr[data-id="${targetQuestionId}"]`
        );

        if (targetRow) {

            targetRow.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });

            targetRow.classList.add(
                "bg-yellow-100",
                "transition",
                "duration-500"
            );

            setTimeout(() => {
                targetRow.classList.remove("bg-yellow-100");
            }, 4000);

        }

        setTimeout(() => {
            urlParams.delete("question_id");

            const newUrl =
                window.location.pathname +
                "?" +
                urlParams.toString();

            window.history.replaceState({}, "", newUrl);
        }, 2500);

    }, 300);

}

renderTable();

setTimeout(() => {
    renderAllQuestionMath();
}, 100);

        /* =======================================================
            SORTABLE QUESTION REORDER
        ======================================================= */
        const questionTableBody =
            document.getElementById(
                "questionTableBody"
            );

        /* =======================================================
            DRAG & DROP REORDER QUESTIONS (FINAL VERSION)
        ======================================================= */
<?php if ($canManageQuiz): ?>
if (window.Sortable && questionTableBody) {

    Sortable.create(questionTableBody, {

        handle: ".drag-handle",

        delay: 150,
        delayOnTouchOnly: true,
        touchStartThreshold: 5,

        animation: 200,
        ghostClass: "bg-blue-50",
        chosenClass: "bg-blue-100",
        dragClass: "opacity-70",

        filter: "#emptyQuestionRow, #noQuestionResult",

        onStart: function () {

            window.previousQuestionOrder = Array.from(
                questionTableBody.querySelectorAll("tr[data-id]")
            ).map(row => row.dataset.id);

        },

        onEnd: function () {

            const rows = Array.from(
                questionTableBody.querySelectorAll("tr[data-id]")
            );

            if (rows.length <= 1) return;

            /* Update nomor tabel */
            rows.forEach((row, index) => {

                const firstCell = row.querySelector("td");

                if (firstCell) {
                    firstCell.textContent = index + 1;
                }

            });

            const formData = new FormData();

            formData.append(
                "quiz_id",
                "<?= (int)$quizId ?>"
            );

            rows.forEach(row => {

                formData.append(
                    "order[]",
                    row.dataset.id
                );

            });

            fetch(
                "../ajax/question/reorder_question.php",
                {
                    method: "POST",
                    body: formData
                }
            )

            .then(res => res.json())

            .then(res => {

                if (res.status == 1) {

                    showToast(
                        "success",
                        "Urutan soal berhasil diperbarui."
                    );

                } else {

                    throw new Error(
                        res.msg ||
                        "Gagal memperbarui urutan soal."
                    );

                }

            })

            .catch(error => {

                console.error(
                    "Reorder Error:",
                    error
                );

                Swal.fire(
                    getResponsiveSwal(
                        "error",
                        "Gagal Reorder",
                        error.message ||
                        "Terjadi kesalahan sistem."
                    )
                );

            });

        }

    });

}
<?php endif; ?>

        });
    </script>

</body>
</html>