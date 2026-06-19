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

if (!in_array($_SESSION['login_user_type'], [1, 2, 3])) {
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
$isStudent = $userType === 3;

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

    if ($teacherQuery && $teacherQuery->num_rows > 0) {

        $teacherData = $teacherQuery->fetch_assoc();
        $teacherId   = (int) $teacherData['id'];

    } else {

        die("Data pengajar tidak ditemukan.");

    }

}

/* =======================================================
    FILTER CONFIGURATION
======================================================= */
$selectedQuizId = isset($_GET['quiz_id'])
    ? $_GET['quiz_id']
    : 'all';

$historyWhere = [];
$quizWhere    = [];

/* ---------- Teacher Restriction ---------- */
if ($isTeacher) {

    $historyWhere[] = "q.created_by = {$userId}";
    $quizWhere[]    = "created_by = {$userId}";

}

/* ---------- Student Restriction ---------- */
if ($isStudent) {

    $historyWhere[] = "s.user_id = {$userId}";

}

/* ---------- Quiz Filter ---------- */
if (
    $selectedQuizId !== 'all' &&
    is_numeric($selectedQuizId)
) {

    $selectedQuizId = (int) $selectedQuizId;

    $historyWhere[] = "q.id = {$selectedQuizId}";

}

/* ---------- Final WHERE ---------- */
$historyWhereClause = !empty($historyWhere)
    ? 'WHERE ' . implode(' AND ', $historyWhere)
    : '';

$quizWhereClause = !empty($quizWhere)
    ? 'WHERE ' . implode(' AND ', $quizWhere)
    : '';

/* =======================================================
    QUIZ FILTER DROPDOWN
======================================================= */
$quizFilterQuery = $conn->query("
    SELECT
        id,
        quiz_title
    FROM quiz_list
    {$quizWhereClause}
    ORDER BY quiz_title ASC
");

/* =======================================================
    HISTORY STATISTICS
======================================================= */

/* ---------- Total History ---------- */
$totalHistory = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM history h
    INNER JOIN quiz_student_list qsl
        ON h.quiz_student_id = qsl.id
    INNER JOIN students s
        ON qsl.student_id = s.id
    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id
    {$historyWhereClause}
")->fetch_assoc()['total'];

/* ---------- Total Students ---------- */
$totalStudents = (int) $conn->query("
    SELECT COUNT(DISTINCT qsl.student_id) AS total
    FROM history h
    INNER JOIN quiz_student_list qsl
        ON h.quiz_student_id = qsl.id
    INNER JOIN students s
        ON qsl.student_id = s.id
    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id
    {$historyWhereClause}
")->fetch_assoc()['total'];

/* ---------- Total Quiz With History ---------- */
$totalQuiz = (int) $conn->query("
    SELECT COUNT(DISTINCT q.id) AS total
    FROM history h
    INNER JOIN quiz_student_list qsl
        ON h.quiz_student_id = qsl.id
    INNER JOIN students s
        ON qsl.student_id = s.id
    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id
    {$historyWhereClause}
")->fetch_assoc()['total'];

/* ---------- Student Specific Statistics ---------- */
$highestScore = 0;
$latestScore  = 0;

if ($isStudent) {

    $highestScoreData = $conn->query("
        SELECT
            MAX(
                CASE
                    WHEN h.max_score > 0
                    THEN (h.final_score / h.max_score) * 100
                    ELSE 0
                END
            ) AS highest_score
        FROM history h
        INNER JOIN quiz_student_list qsl
            ON h.quiz_student_id = qsl.id
        INNER JOIN students s
            ON qsl.student_id = s.id
        INNER JOIN quiz_list q
            ON qsl.quiz_id = q.id
        {$historyWhereClause}
    ")->fetch_assoc();

    $highestScore = $highestScoreData['highest_score'] !== null
        ? number_format($highestScoreData['highest_score'], 2)
        : 0;

    $latestScoreData = $conn->query("
        SELECT
            CASE
                WHEN h.max_score > 0
                THEN (h.final_score / h.max_score) * 100
                ELSE 0
            END AS latest_score
        FROM history h
        INNER JOIN quiz_student_list qsl
            ON h.quiz_student_id = qsl.id
        INNER JOIN students s
            ON qsl.student_id = s.id
        INNER JOIN quiz_list q
            ON qsl.quiz_id = q.id
        {$historyWhereClause}
        ORDER BY h.submitted_at DESC
        LIMIT 1
    ")->fetch_assoc();

    $latestScore = $latestScoreData && $latestScoreData['latest_score'] !== null
        ? number_format($latestScoreData['latest_score'], 2)
        : 0;
}

/* =======================================================
    MAIN HISTORY QUERY
======================================================= */
$historyQuery = $conn->query("
    SELECT
        h.id AS history_id,
        h.final_score,
        h.max_score,
        h.submitted_at,

        q.quiz_title,

        u.name AS student_name,

        s.id AS student_id,

        q.id AS quiz_id

    FROM history h

    INNER JOIN quiz_student_list qsl
        ON h.quiz_student_id = qsl.id

    INNER JOIN students s
        ON qsl.student_id = s.id

    INNER JOIN users u
        ON s.user_id = u.id

    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id

    {$historyWhereClause}

    ORDER BY h.submitted_at DESC
");

/* =======================================================
    DISPLAY HELPER FUNCTIONS
======================================================= */
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

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Riwayat Hasil Kuis | Takar-Edu";

/* =======================================================
    TABLE LAYOUT
======================================================= */
$historyColspan = 7;
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

                        <h1 class="page-title text-xl sm:text-2xl">
                            Riwayat Hasil Kuis
                        </h1>

                        <p class="page-description">

                            <?php if ($isAdmin): ?>
                                Kelola seluruh hasil evaluasi siswa, performa kuis, dan dokumentasi asesmen sistem.
                            <?php elseif ($isTeacher): ?>
                                Pantau hasil pengerjaan siswa dari kuis yang Anda distribusikan.
                            <?php else: ?>
                                Lihat riwayat hasil kuis yang sudah Anda kerjakan.
                            <?php endif; ?>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">

                    <?php if ($isStudent): ?>

                        <!-- Completed Quiz -->
                        <div class="stat-card">
                            <div class="stat-icon bg-blue-600">
                                <i data-lucide="history" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Kuis Dikerjakan</p>
                                <h3 class="stat-value"><?= $totalHistory ?></h3>
                                <p class="stat-label">Riwayat tersimpan</p>
                            </div>
                        </div>

                        <!-- Highest Score -->
                        <div class="stat-card">
                            <div class="stat-icon bg-green-600">
                                <i data-lucide="trophy" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Nilai Tertinggi</p>
                                <h3 class="stat-value"><?= $highestScore ?></h3>
                                <p class="stat-label">Pencapaian terbaik</p>
                            </div>
                        </div>

                        <!-- Latest Score -->
                        <div class="stat-card">
                            <div class="stat-icon bg-purple-600">
                                <i data-lucide="clock-3" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Nilai Terakhir</p>
                                <h3 class="stat-value"><?= $latestScore ?></h3>
                                <p class="stat-label">Kuis terbaru</p>
                            </div>
                        </div>

                    <?php else: ?>

                        <!-- Quiz Attempts -->
                        <div class="stat-card">
                            <div class="stat-icon bg-blue-600">
                                <i data-lucide="history" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Pengerjaan Kuis</p>
                                <h3 class="stat-value"><?= $totalHistory ?></h3>
                                <p class="stat-label">Riwayat tersimpan</p>
                            </div>
                        </div>

                        <!-- Total Students -->
                        <div class="stat-card">
                            <div class="stat-icon bg-green-600">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Total Siswa</p>
                                <h3 class="stat-value"><?= $totalStudents ?></h3>
                                <p class="stat-label">Siswa yang mengerjakan</p>
                            </div>
                        </div>

                        <!-- Total Quiz -->
                        <div class="stat-card">
                            <div class="stat-icon bg-purple-600">
                                <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Total Kuis</p>
                                <h3 class="stat-value"><?= $totalQuiz ?></h3>
                                <p class="stat-label">Kuis memiliki hasil</p>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <!-- =======================================================
                HISTORY TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Riwayat Hasil Kuis
                    </h2>

                    <!-- Export pdf button -->
                    <button
                        id="downloadPdf"
                        type="button"
                        class="form-btn form-btn-danger"
                    >
                        <i data-lucide="file-down" class="w-4 h-4"></i>
                        Download PDF
                    </button>

                </div>

                <!-- ---------- Table Controls ---------- -->
                <div class="table-toolbar">

                    <!-- Row per page -->
                    <div class="table-length-control">

                        <span class="table-control-label">
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

                    <!-- Quiz filter -->
                    <select id="quizFilter" class="filter-select">

                        <option value="all">
                            Semua Kuis
                        </option>

                        <?php if ($quizFilterQuery && $quizFilterQuery->num_rows > 0): ?>

                            <?php while ($quiz = $quizFilterQuery->fetch_assoc()): ?>

                                <option
                                    value="<?= $quiz['id'] ?>"
                                    <?= ($selectedQuizId == $quiz['id']) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($quiz['quiz_title']) ?>
                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                    <!-- Search input -->
                    <div class="search-wrapper">

                        <input
                            id="searchInput"
                            type="text"
                            placeholder="Cari hasil kuis..."
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
                                <th class="table-th w-[22%]">Nama Siswa</th>
                                <th class="table-th w-[20%]">Judul Kuis</th>
                                <th class="table-th w-[10%]">Skor</th>
                                <th class="table-th w-[10%]">Nilai</th>
                                <th class="table-th w-[25%]">Tanggal</th>
                                <th class="table-th w-[8%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                        <?php if ($historyQuery && $historyQuery->num_rows > 0): ?>

                            <?php $no = 1; ?>

                            <?php while ($row = $historyQuery->fetch_assoc()): ?>

                                <?php
                                $isQuestionnaireOnly = empty($row['max_score']);

                                $scoreDisplay = $isQuestionnaireOnly
                                    ? 'Tidak Berlaku'
                                    : formatSmartNumber($row['final_score']) . '/' . formatSmartNumber($row['max_score']);

                                $finalScore = !$isQuestionnaireOnly && $row['max_score'] > 0
                                    ? formatSmartNumber(
                                        ($row['final_score'] / $row['max_score']) * 100
                                    )
                                    : null;
                                ?>

                                    <tr
                                        data-quiz-id="<?= (int)$row['quiz_id'] ?>"
                                        class="app-table-row"
                                    >

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Student -->
                                        <td class="table-td">
                                            <div class="table-td-title">
                                                <?= htmlspecialchars($row['student_name']) ?>
                                            </div>
                                        </td>

                                        <!-- Quiz -->
                                        <td class="table-td">
                                            <?= htmlspecialchars($row['quiz_title']) ?>
                                        </td>

                                        <!-- Total points -->
                                        <td class="table-td text-center">

                                            <span class="status-badge status-info">
                                                <?= htmlspecialchars($scoreDisplay) ?>
                                            </span>

                                        </td>

                                        <!-- Score -->
                                        <td class="table-td text-center">

                                            <?php
                                            $scoreValue = is_numeric($finalScore)
                                                ? (float)$finalScore
                                                : 0;

                                            $scoreClass = 'status-danger';

                                            if ($scoreValue >= 85) {
                                                $scoreClass = 'status-success';
                                            } elseif ($scoreValue >= 70) {
                                                $scoreClass = 'status-warning';
                                            }
                                            ?>

                                            <?php if ($finalScore === null): ?>

                                                <span class="status-badge status-secondary">
                                                    Tidak Berlaku
                                                </span>

                                            <?php else: ?>

                                                <span class="status-badge <?= $scoreClass ?>">
                                                    <?= $finalScore ?>
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Submission date -->
                                        <td class="table-td text-center">

                                            <?= !empty($row['submitted_at'])
                                                ? date('d/m/Y H:i:s', strtotime($row['submitted_at'])) . ' WITA'
                                                : '-' ?>

                                        </td>

                                        <!-- Action buttons -->
                                        <td class="table-td text-center">

                                            <div class="action-group">

                                                <!-- View button -->
                                                <a
                                                    href="view_answer.php?quiz_id=<?= (int)$row['quiz_id'] ?>&student_id=<?= (int)$row['student_id'] ?>"
                                                    class="action-btn action-info"
                                                >
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                    <span class="action-label">Detail</span>
                                                </a>

                                                <!-- Delete button -->
                                                <?php if (!$isStudent): ?>

                                                    <button
                                                        type="button"
                                                        onclick="deleteHistory(<?= (int)$row['history_id'] ?>)"
                                                        class="action-btn action-danger"
                                                    >
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        <span class="action-label">Hapus</span>
                                                    </button>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyRow"
                                class="<?= ($historyQuery && $historyQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $historyColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="history" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada riwayat hasil kuis tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $historyColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada riwayat hasil kuis yang sesuai dengan pencarian
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

    /* ---------- Toast Helper ---------- */
    function showToast(
        icon,
        title
    ) {

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

    /* ---------- Loading Helper ---------- */
    function showLoading(
        title = "Memproses..."
    ) {

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
        DELETE HISTORY
    ======================================================= */
    function deleteHistory(id) {

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                "Hapus riwayat?",
                "Riwayat hasil kuis ini akan dihapus permanen."
            ),
            showCancelButton: true,
            confirmButtonText: "Ya, hapus",
            cancelButtonText: "Batal"
        })

        .then(result => {

            if (
                !result.isConfirmed
            ) return;

            showLoading(
                "Menghapus data..."
            );

            fetch(
                `../ajax/history/delete_history.php?id=${id}`
            )

            .then(
                res =>
                    res.json()
            )

            .then(
                res => {

                    Swal.close();

                    if (
                        res.status ==
                        1
                    ) {

                        showToast(
                            "success",
                            res.msg
                        );

                        setTimeout(
                            () => {
                                location.reload();
                            },
                            1200
                        );

                    } else {

                        Swal.fire(
                            getResponsiveSwal(
                                "error",
                                "Gagal",
                                res.msg ||
                                "Gagal menghapus data."
                            )
                        );

                    }

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

        });

    }

    document.addEventListener(
        "DOMContentLoaded",
        () => {

        /* =======================================================
            STORAGE CONFIGURATION
        ======================================================= */
        const STORAGE_KEYS = {
            search:
                "history_search_keyword",
            rows:
                "history_rows_per_page",
            page:
                "history_current_page"
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
            ICON INITIALIZATION
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

        const quizFilter =
            document.getElementById(
                "quizFilter"
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

        const downloadPdfBtn =
            document.getElementById(
                "downloadPdf"
            );

        /* =======================================================
            RESTORE STATE
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
            SAVE STATE
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
            TABLE RENDER
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
                        currentPage = i;
                        renderTable();
                    },
                    currentPage === i
                );

            }

            /* ---------- Next Button ---------- */
            createButton(
                "Selanjutnya",
                currentPage === totalPages,
                () => {
                    currentPage++;
                    renderTable();
                }
            );

        }

        function applyFilters() {

            const keyword =
                searchInput.value
                    .toLowerCase()
                    .trim();

            const selectedQuiz =
                quizFilter
                    ? quizFilter.value
                    : "all";

            filteredRows = rows.filter(row => {

                const matchSearch =
                    keyword === "" ||
                    row.innerText
                        .toLowerCase()
                        .includes(keyword);

                const matchQuiz =
                    selectedQuiz === "all" ||
                    row.dataset.quizId === selectedQuiz;

                return matchSearch && matchQuiz;

            });

            currentPage = 1;
            renderTable();

        }

        /* =======================================================
            SEARCH
        ======================================================= */
        searchInput.addEventListener(
            "input",
            applyFilters
        );

        if (quizFilter) {
            quizFilter.addEventListener(
                "change",
                applyFilters
            );
        }

        /* =======================================================
            ROW LIMIT
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
            PDF EXPORT
        ======================================================= */
        if (
            downloadPdfBtn
        ) {

            downloadPdfBtn.addEventListener(
                "click",
                () => {

                    const {
                        jsPDF
                    } = window.jspdf;

                    const pdf =
                        new jsPDF();

                    const now =
                        new Date();

                    const dateText =
                        now.toLocaleString(
                            "id-ID",
                            {
                                weekday:
                                    "long",
                                year:
                                    "numeric",
                                month:
                                    "long",
                                day:
                                    "numeric",
                                hour:
                                    "2-digit",
                                minute:
                                    "2-digit"
                            }
                        );

                    const quizFilter =
                        document.getElementById(
                            "quizFilter"
                        );

                    let selectedQuiz =
                        "Semua Kuis";

                    if (
                        quizFilter &&
                        quizFilter.value !==
                            "all"
                    ) {

                        selectedQuiz =
                            quizFilter.options[
                                quizFilter.selectedIndex
                            ].text;

                    }

                    /* ---------- Header ---------- */
                    pdf.setFontSize(
                        10
                    );

                    pdf.text(
                        `Tanggal Unduh: ${dateText}`,
                        196,
                        10,
                        {
                            align:
                                "right"
                        }
                    );

                    pdf.setFontSize(
                        14
                    );

                    pdf.text(
                        `Riwayat Hasil Kuis - ${selectedQuiz}`,
                        14,
                        20
                    );

                    /* ---------- Table Data ---------- */
                    const bodyData =
                        [];

                    filteredRows.forEach(
                        (
                            row,
                            index
                        ) => {

                            const columns =
                                row.querySelectorAll(
                                    "td"
                                );

                            if (
                                columns.length >=
                                6
                            ) {

                                bodyData.push(
                                    [
                                        index +
                                        1,
                                        columns[1].innerText.trim(),
                                        columns[2].innerText.trim(),
                                        columns[3].innerText.trim(),
                                        columns[4].innerText.trim(),
                                        columns[5].innerText.trim()
                                    ]
                                );

                            }

                        }
                    );

                    /* ---------- AutoTable ---------- */
                    pdf.autoTable(
                        {

                            head: [[
                                "No",
                                "Nama",
                                "Kuis",
                                "Skor",
                                "Nilai",
                                "Tanggal"
                            ]],

                            body: bodyData,

                            startY: 28,

                            theme:
                                "grid",

                            styles: {
                                fontSize: 8,
                                cellPadding: 3,
                                lineColor: [
                                    0,
                                    0,
                                    0
                                ],
                                lineWidth: 0.2,
                                textColor: [
                                    0,
                                    0,
                                    0
                                ]
                            },

                            headStyles:
                                {
                                    fillColor: [
                                        37,
                                        99,
                                        235
                                    ],
                                    textColor: [
                                        255,
                                        255,
                                        255
                                    ],
                                    fontStyle:
                                        "bold",
                                    halign:
                                        "center",
                                    lineColor:
                                        [
                                            0,
                                            0,
                                            0
                                        ],
                                    lineWidth:
                                        0.2
                                },

                            bodyStyles:
                                {
                                    lineColor:
                                        [
                                            0,
                                            0,
                                            0
                                        ],
                                    lineWidth:
                                        0.2
                                },

                            alternateRowStyles:
                                {
                                    fillColor:
                                        [
                                            245,
                                            247,
                                            250
                                        ]
                                },

                            columnStyles:
                                {
                                    0: {
                                        halign:
                                            "center",
                                        cellWidth:
                                            12
                                    },
                                    3: {
                                        halign:
                                            "center",
                                        cellWidth:
                                            22
                                    },
                                    4: {
                                        halign:
                                            "center",
                                        cellWidth:
                                            22
                                    }
                                },

                            margin: {
                                left: 14,
                                right: 14
                            }

                        }
                    );

                    /* ---------- Dynamic File Name ---------- */
                    let fileName =
                        "Riwayat_Hasil_Kuis";

                    if (
                        quizFilter &&
                        quizFilter.value !==
                            "all"
                    ) {

                        let cleanQuizName =
                            quizFilter.options[
                                quizFilter.selectedIndex
                            ].text;

                        cleanQuizName =
                            cleanQuizName
                                .replace(
                                    /[^\w\s]/gi,
                                    ""
                                )
                                .trim()
                                .replace(
                                    /\s+/g,
                                    "_"
                                );

                        fileName +=
                            "_" +
                            cleanQuizName;

                    }

                    const exportDate =
                        new Date()
                        .toISOString()
                        .slice(
                            0,
                            10
                        )
                        .replace(
                            /-/g,
                            "_"
                        );

                    pdf.save(
                        `${fileName}_${exportDate}.pdf`
                    );

                }
            );

        }

        /* =======================================================
            INITIAL TABLE RENDER
        ======================================================= */
        renderTable();

    });
    </script>

</body>
</html>