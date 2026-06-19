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

/* ---------- Student Only ---------- */
if ($_SESSION['login_user_type'] != 3) {
    header("Location: home.php");
    exit;
}

/* =======================================================
    SESSION CONFIGURATION
======================================================= */
$userId = (int) $_SESSION['login_id'];

/* ---------- Logged User ---------- */
$userName = $_SESSION['login_name']
    ?? $_SESSION['login_user_name']
    ?? $_SESSION['name']
    ?? 'Siswa';

/* =======================================================
    STUDENT ACCESS
======================================================= */
$studentQuery = $conn->query("
    SELECT
        s.id,
        s.class_id,
        c.class_name
    FROM students s
    LEFT JOIN classes c
        ON s.class_id = c.id
    WHERE s.user_id = {$userId}
    LIMIT 1
");

if (!$studentQuery || $studentQuery->num_rows === 0) {
    die("Data siswa tidak ditemukan.");
}

$studentData = $studentQuery->fetch_assoc();

$studentId   = (int) $studentData['id'];
$classId     = (int) $studentData['class_id'];
$className   = $studentData['class_name'] ?? '-';

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Daftar Kuis Saya | Takar-Edu";

/* =======================================================
    TABLE CONFIGURATION
======================================================= */
$quizColspan = 9;

/* =======================================================
    MAIN QUIZ STUDENT QUERY
======================================================= */
$quizQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_title,
        q.description,
        q.quiz_duration,
        q.status,
        q.open_at,
        q.due_date,
        q.created_at,

        (
            SELECT GROUP_CONCAT(
                DISTINCT sub2.subject_name
                ORDER BY sub2.subject_name
                SEPARATOR '||'
            )
            FROM quiz_teacher_list qtl2
            INNER JOIN teachers t2
                ON qtl2.teacher_id = t2.id
            INNER JOIN subjects sub2
                ON t2.subject_id = sub2.id
            WHERE qtl2.quiz_id = q.id
        ) AS subject_name,

        (
            SELECT GROUP_CONCAT(
                DISTINCT u2.name
                ORDER BY u2.name
                SEPARATOR '||'
            )
            FROM quiz_teacher_list qtl3
            INNER JOIN teachers t3
                ON qtl3.teacher_id = t3.id
            INNER JOIN users u2
                ON t3.user_id = u2.id
            WHERE qtl3.quiz_id = q.id
        ) AS teacher_name,

        qsl.id AS assignment_id,

        h.id AS history_id,
        h.final_score,
        h.max_score,
        h.submitted_at,

        CASE
            WHEN h.id IS NULL THEN 0
            ELSE 1
        END AS is_completed

    FROM quiz_student_list qsl

    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id

    LEFT JOIN history h
        ON h.quiz_student_id = qsl.id
        AND h.id = (
            SELECT MAX(h2.id)
            FROM history h2
            WHERE h2.quiz_student_id = qsl.id
        )

    WHERE
        qsl.student_id = {$studentId}
        AND q.status = 1

    ORDER BY
        CASE
            WHEN h.id IS NULL THEN 0
            ELSE 1
        END ASC,
        q.due_date ASC,
        q.created_at DESC
");

/* =======================================================
    SUMMARY STATISTICS
======================================================= */
$totalQuiz        = 0;
$totalCompleted   = 0;
$totalPending     = 0;
$totalScoreAccum  = 0;
$totalScoreCount  = 0;

$quizRows = [];

if ($quizQuery && $quizQuery->num_rows > 0) {

    while ($row = $quizQuery->fetch_assoc()) {

        $isCompleted = (int) $row['is_completed'] === 1;

        $totalQuiz++;

        if ($isCompleted) {

            $totalCompleted++;

            if (
                (int) $row['max_score'] > 0
            ) {

                $scorePercent =
                    ((float)$row['final_score'] / (float)$row['max_score']) * 100;

                $totalScoreAccum += $scorePercent;
                $totalScoreCount++;

            }

        } else {

            $totalPending++;

        }

        $quizRows[] = $row;

    }

}

/* =======================================================
    DISPLAY HELPER FUNCTIONS
======================================================= */

/* ---------- Number Formatter ---------- */
function formatSmartNumber($number)
{
    if ($number === null || $number === '') {
        return '-';
    }

    return rtrim(
        rtrim(
            number_format((float)$number, 2, '.', ''),
            '0'
        ),
        '.'
    );
}
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
            <section class="page-header">

                <div class="page-header-content">

                    <div>

                        <h1 class="page-title">
                            Daftar Kuis Saya
                        </h1>

                        <p class="page-description">
                            Kelola seluruh kuis aktif yang diberikan kepada Anda dan pantau progres pengerjaan.
                        </p>

                    </div>

                    <!-- Class badge -->
                    <span class="info-badge status-info">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                        <?= htmlspecialchars($className) ?>
                    </span>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">

                    <!-- Total quiz -->
                    <div class="stat-card">

                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Kuis</p>
                            <h3 class="stat-value"><?= $totalQuiz ?></h3>
                            <p class="stat-label">Kuis tersedia</p>
                        </div>

                    </div>

                    <!-- Completed quiz -->
                    <div class="stat-card">

                        <div class="stat-icon bg-green-600">
                            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Selesai</p>
                            <h3 class="stat-value"><?= $totalCompleted ?></h3>
                            <p class="stat-label">Sudah dikerjakan</p>
                        </div>

                    </div>

                    <!-- Pending quiz -->
                    <div class="stat-card">

                        <div class="stat-icon bg-orange-600">
                            <i data-lucide="clock-3" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Belum Dikerjakan</p>
                            <h3 class="stat-value"><?= $totalPending ?></h3>
                            <p class="stat-label">Menunggu pengerjaan</p>
                        </div>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                QUIZ TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Daftar Kuis
                    </h2>

                </div>

                    <!-- ---------- Table Controls ---------- -->
                <div class="table-toolbar">

                    <!-- Rows per page -->
                    <div class="table-length-control">

                        <label for="rowsPerPage" class="table-control-label">
                            Tampilkan
                        </label>

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
                            placeholder="Cari kuis..."
                            class="search-input"
                        >

                        <i data-lucide="search" class="input-icon"></i>

                    </div>

                </div>

                <!-- ---------- Table Wrapper ---------- -->
                <div class="table-wrapper">

                    <table id="dataTable" class="app-table">

                        <!-- ---------- Table Header ---------- -->
                        <thead class="app-table-head">

                            <tr>
                                <th class="table-th w-[5%]">No</th>
                                <th class="table-th w-[18%]">Judul Kuis</th>
                                <th class="table-th w-[10%]">Mapel</th>
                                <th class="table-th w-[19%]">Pengajar</th>
                                <th class="table-th w-[10%]">Durasi</th>
                                <th class="table-th w-[10%]">Status</th>
                                <th class="table-th w-[8%]">Nilai</th>
                                <th class="table-th w-[12%]">Batas Waktu</th>
                                <th class="table-th w-[8%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                        <?php if (!empty($quizRows)): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($quizRows as $quiz): ?>

                                <?php
                                $isCompleted = (int)$quiz['is_completed'] === 1;

                                $scorePercent = null;

                                $isQuestionnaireOnly = $isCompleted && empty($quiz['max_score']);

                                if (
                                    $isCompleted &&
                                    !$isQuestionnaireOnly &&
                                    (int)$quiz['max_score'] > 0
                                ) {

                                    $scorePercent =
                                        formatSmartNumber(
                                            ((float)$quiz['final_score'] / (float)$quiz['max_score']) * 100
                                        );

                                }

                                $isExpired = false;

                                if (!empty($quiz['due_date'])) {
                                    $isExpired =
                                        strtotime($quiz['due_date']) < time();
                                }
                                ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Quiz title -->
                                        <td class="table-td">

                                            <div class="table-td-title">
                                                <?= htmlspecialchars($quiz['quiz_title']) ?>
                                            </div>

                                            <?php if (!empty($quiz['description'])): ?>

                                                <div class="table-subtext line-clamp-2">
                                                    <?= htmlspecialchars($quiz['description']) ?>
                                                </div>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Subject -->
                                        <td class="table-td text-center relative overflow-visible">

                                            <?php
                                                $subjectList = !empty($quiz['subject_name'])
                                                    ? array_filter(array_map('trim', explode('||', $quiz['subject_name'])))
                                                    : [];

                                                $totalSubjects = count($subjectList);
                                            ?>

                                            <?php if ($totalSubjects <= 1): ?>

                                                <div class="flex justify-center">
                                                    <span class="badge status-info badge-pill-info">
                                                        <?= htmlspecialchars($subjectList[0] ?? '-') ?>
                                                    </span>
                                                </div>

                                            <?php else: ?>

                                                <div class="relative inline-block text-left">

                                                    <button
                                                        type="button"
                                                        class="table-dropdown-btn"
                                                        onclick="toggleSubjectDropdown(event, 'subject-dropdown-<?= (int)$quiz['id'] ?>')"
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
                                                                Daftar Mapel:
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

                                        </td>

                                        <!-- Teacher -->
                                        <td class="table-td text-center">

                                            <?php if (!empty($quiz['teacher_name'])): ?>

                                                <?php
                                                    $teachers = array_filter(
                                                        array_map('trim', explode('||', $quiz['teacher_name']))
                                                    );

                                                    $teacherCount = count($teachers);
                                                ?>

                                                <?php if ($teacherCount === 1): ?>

                                                    <div class="text-sm text-gray-700 font-medium">
                                                        <?= htmlspecialchars($teachers[0]) ?>
                                                    </div>

                                                <?php else: ?>

                                                    <div class="relative inline-block text-left">

                                                        <button
                                                            type="button"
                                                            onclick="toggleTeacherDropdown(event, 'teacher-dropdown-<?= (int)$quiz['id'] ?>')"
                                                            class="table-dropdown-btn"
                                                        >
                                                            <i data-lucide="users" class="w-4 h-4"></i>

                                                            <?= $teacherCount ?> Pengajar

                                                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                                        </button>

                                                        <div
                                                            id="teacher-dropdown-<?= (int)$quiz['id'] ?>"
                                                            class="hidden table-floating-dropdown"
                                                        >

                                                            <div class="table-dropdown-header">
                                                                <p class="table-dropdown-title">
                                                                    Daftar Pengajar:
                                                                </p>
                                                            </div>

                                                            <?php foreach ($teachers as $teacher): ?>

                                                                <div class="table-dropdown-item">
                                                                    <?= htmlspecialchars($teacher) ?>
                                                                </div>

                                                            <?php endforeach; ?>

                                                        </div>

                                                    </div>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="text-gray-400">-</span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Duration -->
                                        <td class="table-td text-center">
                                            <?= (int)$quiz['quiz_duration'] ?> menit
                                        </td>

                                        <!-- Status -->
                                        <td class="table-td text-center">

                                            <?php if ($isCompleted): ?>

                                                <span class="status-badge status-success">
                                                    Selesai
                                                </span>

                                            <?php elseif ($isExpired): ?>

                                                <span class="status-badge status-danger">
                                                    Berakhir
                                                </span>

                                            <?php else: ?>

                                                <span class="status-badge status-warning">
                                                    Belum
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Score -->
                                        <td class="table-td text-center">

                                            <?php if ($isCompleted): ?>

                                                <?php if ($isQuestionnaireOnly): ?>

                                                    <span class="status-badge status-secondary">
                                                        Tidak Berlaku
                                                    </span>

                                                <?php else: ?>

                                                    <span class="status-badge status-info">
                                                        <?= $scorePercent ?>
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="text-gray-400">-</span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Dateline -->
                                        <td class="table-td text-center text-xs">

                                            <?php if (!empty($quiz['due_date'])): ?>

                                                <div class="<?= $isExpired ? 'text-red-600' : 'text-gray-700' ?> font-medium">
                                                    <?= date('d/m/Y H:i', strtotime($quiz['due_date'])) ?> WITA
                                                </div>

                                            <?php else: ?>

                                                <span class="text-gray-400">
                                                    Tidak diatur
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Action buttons -->
                                        <td class="table-td text-center">

                                            <div class="action-group">

                                                <?php if ($isCompleted): ?>

                                                    <!-- View button -->
                                                    <a
                                                        href="view_answer.php?quiz_id=<?= (int)$quiz['id'] ?>"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                        <span class="action-label">Hasil</span>
                                                    </a>

                                                <?php elseif (!$isExpired): ?>

                                                    <!-- Start quiz -->
                                                    <a
                                                        href="confirm_quiz.php?id=<?= (int)$quiz['id'] ?>"
                                                        class="action-btn action-success"
                                                    >
                                                        <i data-lucide="play-circle" class="w-4 h-4"></i>
                                                        <span class="action-label">Kerjakan</span>
                                                    </a>

                                                <?php else: ?>

                                                    <!-- Quiz closed -->
                                                    <span class="action-btn action-disabled">
                                                        <i data-lucide="ban" class="w-4 h-4"></i>
                                                        <span class="action-label">Ditutup</span>
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyRow"
                                class="<?= !empty($quizRows) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $quizColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="clipboard-list" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada kuis tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $quizColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada kuis yang sesuai dengan pencarian
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


    <script>
    /* =======================================================
        TEACHER DROPDOWN HELPERS
    ======================================================= */

    /* ---------- Toggle Teacher Dropdown ---------- */
    function toggleTeacherDropdown(event, dropdownId) {

        event.stopPropagation();

        closeAllTeacherDropdowns();

        const target = document.getElementById(dropdownId);
        if (!target) return;

        if (!target.classList.contains("hidden")) {
            target.classList.add("hidden");
            return;
        }

        const button = event.currentTarget;
        const rect = button.getBoundingClientRect();

        target.classList.remove("hidden");

        let left = rect.left + (rect.width / 2) - (target.offsetWidth / 2);

        if (left < 10) left = 10;

        if (left + target.offsetWidth > window.innerWidth - 10) {
            left = window.innerWidth - target.offsetWidth - 10;
        }

        const top = rect.bottom + 8;

        target.style.top = `${top}px`;
        target.style.left = `${left}px`;
    }

    /* ---------- Close All Teacher Dropdowns ---------- */
    function closeAllTeacherDropdowns() {

        document
            .querySelectorAll('[id^="teacher-dropdown-"]')
            .forEach(dropdown => {
                dropdown.classList.add("hidden");
            });

    }

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

        closeAllTeacherDropdowns();

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

    /* =======================================================
        DROPDOWN AUTO CLOSE
    ======================================================= */
    /* ---------- Auto Close Teacher Dropdown ---------- */
    document.addEventListener("click", closeAllTeacherDropdowns);
    window.addEventListener("scroll", closeAllTeacherDropdowns, true);
    window.addEventListener("resize", closeAllTeacherDropdowns);

    /* ---------- Auto Close Subject Dropdown ---------- */
    document.addEventListener("click", closeAllSubjectDropdowns);
    window.addEventListener("scroll", closeAllSubjectDropdowns, true);
    window.addEventListener("resize", closeAllSubjectDropdowns);
    
    /* =======================================================
        PAGE INITIALIZATION
    ======================================================= */
    document.addEventListener(
        "DOMContentLoaded",
        () => {

        /* =======================================================
            STORAGE CONFIGURATION
        ======================================================= */
        const STORAGE_KEYS = {
            search:
                "student_quiz_search_keyword",
            rows:
                "student_quiz_rows_per_page",
            page:
                "student_quiz_current_page"
        };

        const navigationType =
            performance.getEntriesByType(
                "navigation"
            )[0]?.type ||
            "navigate";

        const shouldRestore =
            navigationType ===
            "reload";

        if (
            !shouldRestore
        ) {

            sessionStorage.removeItem(
                STORAGE_KEYS.search
            );

            sessionStorage.removeItem(
                STORAGE_KEYS.rows
            );

            sessionStorage.removeItem(
                STORAGE_KEYS.page
            );

        }

        /* =======================================================
            INITIALIZE ICONS
        ======================================================= */
        if (
            window.lucide
        ) {
            lucide.createIcons();
        }

        /* =======================================================
            ELEMENT REFERENCES
        ======================================================= */
        const table =
            document.getElementById(
                "dataTable"
            );

        if (
            !table
        ) return;

        const rows = [
            ...table.querySelectorAll(
                "tbody tr"
            )
        ].filter(
            row =>
                row.id !==
                    "emptyRow" &&
                row.id !==
                    "noResult"
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

        const emptyRow =
            document.getElementById(
                "emptyRow"
            );

        const noResult =
            document.getElementById(
                "noResult"
            );

        /* =======================================================
            TABLE STATE RESTORATION
        ======================================================= */
        const savedSearch =
            shouldRestore
                ? sessionStorage.getItem(
                    STORAGE_KEYS.search
                ) || ""
                : "";

        const savedRows =
            shouldRestore
                ? sessionStorage.getItem(
                    STORAGE_KEYS.rows
                ) || "10"
                : "10";

        const savedPage =
            shouldRestore
                ? parseInt(
                    sessionStorage.getItem(
                        STORAGE_KEYS.page
                    ) || "1"
                )
                : 1;

        searchInput.value =
            savedSearch;

        rowsPerPage.value =
            savedRows;

        let currentPage =
            savedPage;

        let filteredRows =
            savedSearch
                ? rows.filter(
                    row =>
                        row.innerText
                            .toLowerCase()
                            .includes(
                                savedSearch.toLowerCase()
                            )
                )
                : [...rows];

        let perPage =
            rowsPerPage.value ===
            "all"
                ? Math.max(
                    filteredRows.length,
                    1
                )
                : parseInt(
                    rowsPerPage.value
                );

        /* =======================================================
            TABLE STATE STORAGE
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
            TABLE RENDERING
        ======================================================= */
        function renderTable() {

            rows.forEach(
                row => {
                    row.style.display =
                        "none";
                }
            );

            /* ---------- Empty DB ---------- */
            if (
                rows.length ===
                0
            ) {

                emptyRow.classList.remove(
                    "hidden"
                );

                noResult.classList.add(
                    "hidden"
                );

                pageInfo.textContent =
                    "0 data";

                pagination.innerHTML =
                    "";

                return;
            }

            /* ---------- Empty Search ---------- */
            if (
                filteredRows.length ===
                0
            ) {

                emptyRow.classList.add(
                    "hidden"
                );

                noResult.classList.remove(
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

            noResult.classList.add(
                "hidden"
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

        }

        /* =======================================================
            PAGINATION
        ======================================================= */
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
                    pagination-btn
                    ${active
                        ? "pagination-active"
                        : disabled
                            ? "pagination-disabled"
                            : ""}
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

            /* ---------- Previous Button ---------- */
            createButton(
                "Sebelumnya",
                currentPage === 1,
                () => {
                    currentPage--;
                    renderTable();
                }
            );

            /* ---------- Page Number Buttons ---------- */
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

            /* ---------- Next Button ---------- */
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
            SEARCH FILTER
        ======================================================= */
        searchInput.addEventListener(
            "input",
            () => {

                const keyword =
                    searchInput.value
                        .toLowerCase()
                        .trim();

                filteredRows =
                    keyword === ""
                        ? [...rows]
                        : rows.filter(
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
            ROW LIMIT CONTROL
        ======================================================= */
        rowsPerPage.addEventListener(
            "change",
            () => {

                perPage =
                    rowsPerPage.value ===
                    "all"
                        ? Math.max(
                            filteredRows.length,
                            1
                        )
                        : parseInt(
                            rowsPerPage.value
                        );

                currentPage = 1;

                renderTable();

            }
        );

        /* =======================================================
            INITIAL TABLE RENDER
        ======================================================= */
        renderTable();

    });
    </script>

</body>
</html>