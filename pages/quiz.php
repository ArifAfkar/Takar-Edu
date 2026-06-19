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

        $teacherId = (int) $teacherData['id'];

    }

}

/* =======================================================
    QUIZ FILTER
======================================================= */
$quizWhereClause = "";

if ($isTeacher) {

    $quizWhereClause = "
        WHERE (
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
        )
    ";

}

/* =======================================================
    TEACHER OPTIONS
======================================================= */
$teacherOptions = [];

if ($isAdmin) {

    /* ---------- Teacher Assignment Options ---------- */
    $teacherList = $conn->query("
        SELECT
            t.id AS teacher_id,
            u.name,
            s.subject_name,
            c.id AS class_id,
            c.class_name,
            c.grade_level

        FROM teachers t

        INNER JOIN users u
            ON t.user_id = u.id

        LEFT JOIN subjects s
            ON t.subject_id = s.id

        INNER JOIN teacher_class_assignments tca
            ON t.id = tca.teacher_id

        INNER JOIN classes c
            ON tca.class_id = c.id

        WHERE tca.status = 1

        ORDER BY
            s.subject_name ASC,
            FIELD(c.grade_level, 'I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'),
            c.grade_level ASC,
            c.class_name ASC,
            u.name ASC
    ");

    /* ---------- Group Teachers by Subject and Grade ---------- */
    if ($teacherList) {

        while ($teacher = $teacherList->fetch_assoc()) {

            $subjectName = $teacher['subject_name'] ?: 'Tanpa Mapel';
            $gradeLevel  = $teacher['grade_level'] ?: 'Tanpa Tingkat';

            $teacherOptions[$subjectName][$gradeLevel][] = $teacher;

        }

    }

}

/* =======================================================
    QUIZ STATISTICS
======================================================= */

/* ---------- Total Quiz ---------- */
$totalQuiz = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM quiz_list q
    {$quizWhereClause}
")->fetch_assoc()['total'];

/* ---------- Own Quiz ---------- */
$ownQuizCount = 0;

if ($isTeacher) {

    $ownQuizCount = (int) $conn->query("
        SELECT COUNT(*) AS total
        FROM quiz_list
        WHERE created_by = {$userId}
    ")->fetch_assoc()['total'];

}

/* ---------- Active Quiz ---------- */
$activeQuiz = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM quiz_list q
    {$quizWhereClause}
    " . ($quizWhereClause ? " AND " : " WHERE ") . "
    q.status = 1
")->fetch_assoc()['total'];

/* ---------- Total Assigned Quiz ---------- */
if ($isAdmin) {

    $totalAssignments = (int) $conn->query("
        SELECT COUNT(DISTINCT q.id) AS total
        FROM quiz_list q
        INNER JOIN quiz_teacher_list qtl
            ON q.id = qtl.quiz_id
        WHERE q.status = 1
    ")->fetch_assoc()['total'];

} else {

    $totalAssignments = (int) $conn->query("
        SELECT COUNT(DISTINCT q.id) AS total
        FROM quiz_list q
        INNER JOIN quiz_teacher_list qtl
            ON q.id = qtl.quiz_id
        WHERE q.status = 1
        AND qtl.teacher_id = {$teacherId}
    ")->fetch_assoc()['total'];

}

/* ---------- Assigned Quiz ---------- */
$assignmentQuizCount = 0;

if ($isTeacher) {

    $assignmentQuizCount = (int) $conn->query("
        SELECT COUNT(DISTINCT q.id) AS total
        FROM quiz_list q
        INNER JOIN quiz_teacher_list qtl
            ON q.id = qtl.quiz_id
        WHERE qtl.teacher_id = {$teacherId}
        AND q.created_by != {$userId}
        AND q.status = 1
        AND EXISTS (
            SELECT 1
            FROM teacher_class_assignments tca
            WHERE tca.teacher_id = qtl.teacher_id
            AND tca.status = 1
        )
    ")->fetch_assoc()['total'];

}

/* ---------- Total Results ---------- */
if ($isAdmin) {

    $totalResults = (int) $conn->query("
        SELECT COUNT(DISTINCT a.quiz_id, a.student_id) AS total
        FROM answers a
    ")->fetch_assoc()['total'];

} else {

    $totalResults = (int) $conn->query("
        SELECT COUNT(DISTINCT a.quiz_id, a.student_id) AS total
        FROM answers a
        INNER JOIN quiz_teacher_list qtl
            ON a.quiz_id = qtl.quiz_id
        WHERE qtl.teacher_id = {$teacherId}
    ")->fetch_assoc()['total'];

}

/* =======================================================
    MAIN QUIZ QUERY
======================================================= */
$quizQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_title,
        q.description,
        q.created_by,
        q.quiz_duration,
        q.status,
        q.open_at,
        q.due_date,
        q.created_at,
        q.updated_at,

        u.name AS creator_name,

        (
            SELECT GROUP_CONCAT(u2.name SEPARATOR '||')
            FROM quiz_teacher_list qtl
            INNER JOIN teachers t2 ON qtl.teacher_id = t2.id
            INNER JOIN users u2 ON t2.user_id = u2.id
            WHERE qtl.quiz_id = q.id
        ) AS assigned_teachers,

        (
            SELECT COUNT(*)
            FROM questions qs
            WHERE qs.quiz_id = q.id
        ) AS total_questions,

        (
            SELECT COUNT(*)
            FROM quiz_student_list qsl
            WHERE qsl.quiz_id = q.id
        ) AS assigned_students

    FROM quiz_list q

    LEFT JOIN users u
        ON q.created_by = u.id

    {$quizWhereClause}

    ORDER BY
        q.created_at DESC
");

/* =======================================================
    DISPLAY HELPER FUNCTIONS
======================================================= */

/* ---------- Date Time Format ---------- */
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

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Kuis | Takar-Edu";

/* =======================================================
    PAGE LAYOUT
======================================================= */
$quizColspan = $isAdmin ? 8 : 7;
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
                            Manajemen Kuis
                        </h1>

                        <p class="page-description">

                            <?php if ($isAdmin): ?>
                                Kelola seluruh kuis evaluasi, distribusi kelas, jadwal pengerjaan, dan bank asesmen sistem.

                            <?php else: ?>
                                Kelola kuis pembelajaran Anda berdasarkan distribusi kelas dan asesmen aktif.

                            <?php endif; ?>

                        </p>

                    </div>

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
                            <p class="stat-label"><?= $isAdmin ? 'Total Kuis' : 'Kuis Saya' ?></p>
                            <h3 class="stat-value"><?= $isAdmin ? $totalQuiz : $ownQuizCount ?></h3>
                            <p class="stat-label"><?= $isAdmin ? 'Bank evaluasi' : 'Kuis dibuat' ?></p>
                        </div>
                    </div>

                    <!-- Active quiz -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="badge-check" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label"><?= $isAdmin ? 'Kuis Aktif' : 'Kuis Aktif' ?></p>
                            <h3 class="stat-value"><?= $activeQuiz ?></h3>
                            <p class="stat-label"><?= $isAdmin ? 'Sedang berjalan' : 'Dapat diakses' ?></p>
                        </div>
                    </div>

                    <!-- Quiz distribution -->
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-600">
                            <i data-lucide="send" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label"><?= $isAdmin ? 'Distribusi Kuis' : 'Penugasan Kuis' ?></p>
                            <h3 class="stat-value"><?= $isAdmin ? $totalAssignments : $assignmentQuizCount ?></h3>
                            <p class="stat-label"><?= $isAdmin ? 'Kuis ditugaskan' : 'Kuis diterima' ?></p>
                        </div>
                    </div>

                    <!-- Total results -->
                    <div class="stat-card">
                        <div class="stat-icon bg-yellow-600">
                            <i data-lucide="history" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Hasil</p>
                            <h3 class="stat-value"><?= $totalResults ?></h3>
                            <p class="stat-label">Pengerjaan siswa</p>
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
                    <h2 class="section-title text-xl sm:text-2xl">
                        Daftar Kuis
                    </h2>

                    <!-- Add quiz button -->
                    <button
                        id="newQuiz"
                        type="button"
                        class="form-btn form-btn-primary"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah Kuis
                    </button>

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

                        <i
                            data-lucide="search"
                            class="input-icon"
                        ></i>

                    </div>

                </div>

                <!-- ---------- Table Wrapper ---------- -->
                <div class="table-wrapper">

                    <table id="dataTable" class="app-table">

                        <!-- ---------- Table Header ---------- -->
                        <thead class="app-table-head">

                            <tr>
                                <th class="table-th w-[5%]">No</th>
                                <th class="table-th <?= $isAdmin ? 'w-[15%]' : 'w-[35%]' ?>">Judul</th>

                                <?php if ($isAdmin): ?>
                                    <th class="table-th w-[20%]">Penanggung Jawab</th>
                                <?php endif; ?>

                                <th class="table-th w-[10%]">Durasi</th>
                                <th class="table-th w-[15%]">Distribusi</th>
                                <th class="table-th w-[12%]">Jadwal</th>
                                <th class="table-th w-[8%]">Status</th>
                                <th class="table-th w-[15%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($quizQuery && $quizQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($row = $quizQuery->fetch_assoc()): ?>

                                <?php
                                $isOwnedQuiz = (int)$row['created_by'] === $userId;

                                $canManageRow = $isAdmin || ($isTeacher && $isOwnedQuiz);
                                ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Quiz title -->
                                        <td class="table-td">

                                            <div class="table-td-title">
                                                <?= htmlspecialchars($row['quiz_title']) ?>
                                            </div>

                                            <?php if (!empty($row['description'])): ?>
                                                <div class="table-subtext line-clamp-2">
                                                    <?= htmlspecialchars($row['description']) ?>
                                                </div>
                                            <?php endif; ?>

                                        </td>

                                        <!-- Assigned teachers -->
                                        <?php if ($isAdmin): ?>

                                            <td class="table-td text-center">

                                                <?php if (!empty($row['assigned_teachers'])): ?>

                                                    <?php
                                                        $teachers = array_filter(
                                                            array_map('trim', explode('||', $row['assigned_teachers']))
                                                        );

                                                        $teacherCount = count($teachers);
                                                    ?>

                                                    <?php if ($teacherCount === 1): ?>

                                                        <!-- Single teacher -->
                                                        <div class="text-sm text-gray-700 font-medium">
                                                            <?= htmlspecialchars($teachers[0]) ?>
                                                        </div>

                                                    <?php else: ?>

                                                        <!-- Multiple teachers -->
                                                        <div class="relative inline-block text-left">

                                                            <!-- Badge button -->
                                                            <button
                                                                type="button"
                                                                onclick="toggleTeacherDropdown(event, 'teacher-dropdown-<?= (int)$row['id'] ?>')"
                                                                class="table-dropdown-btn"
                                                            >
                                                                <i data-lucide="users" class="w-4 h-4"></i>

                                                                <?= $teacherCount ?> Pengajar

                                                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                                            </button>

                                                            <!-- Dropdown menu -->
                                                            <div
                                                                id="teacher-dropdown-<?= (int)$row['id'] ?>"
                                                                class="hidden table-floating-dropdown"
                                                            >

                                                                <!-- Dropdown header -->
                                                                <div class="table-dropdown-header">
                                                                    <p class="table-dropdown-title">
                                                                        Daftar Pengajar:
                                                                    </p>
                                                                </div>

                                                                <!-- Teacher list -->
                                                                <?php foreach ($teachers as $teacher): ?>

                                                                    <div class="table-dropdown-item">
                                                                        <?= htmlspecialchars($teacher) ?>
                                                                    </div>

                                                                <?php endforeach; ?>

                                                            </div>

                                                        </div>

                                                    <?php endif; ?>

                                                <?php else: ?>

                                                    <span class="text-gray-400">Data tidak valid</span>

                                                <?php endif; ?>

                                            </td>

                                        <?php endif; ?>

                                        <!-- Duration -->
                                        <td class="table-td text-center">
                                            <?= (int)$row['quiz_duration'] ?> menit
                                        </td>

                                        <!-- Distribution -->
                                        <td class="table-td text-center">

                                            <div class="inline-flex flex-col gap-1">

                                                <span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">
                                                    <?= (int)$row['total_questions'] ?> soal
                                                </span>

                                                <span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-medium">
                                                    <?= (int)$row['assigned_students'] ?> siswa
                                                </span>

                                            </div>

                                        </td>

                                        <!-- Schedule -->
                                        <td class="table-td text-center">

                                            <div class="space-y-1">

                                                <?php if (!empty($row['open_at'])): ?>

                                                    <div class="text-green-600 font-medium">
                                                        Mulai: <?= formatDateTimeWITA($row['open_at']) ?>
                                                    </div>

                                                <?php endif; ?>

                                                <?php if (!empty($row['due_date'])): ?>

                                                    <div class="text-red-600 font-medium">
                                                        Selesai: <?= formatDateTimeWITA($row['due_date']) ?>
                                                    </div>

                                                <?php endif; ?>

                                                <?php if (empty($row['open_at']) && empty($row['due_date'])): ?>

                                                    <span class="text-gray-500">
                                                        Tidak diatur
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                        <!-- Status -->
                                        <td class="table-td text-center">

                                            <?php if ((int)$row['status'] === 1): ?>

                                                <span class="status-badge status-success">
                                                    Aktif
                                                </span>

                                            <?php else: ?>

                                                <span class="status-badge status-secondary">
                                                    Arsip
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Action buttons -->
                                        <td class="table-td text-center">

                                            <div class="action-group">

                                                <!-- Admin / owner quiz -->
                                                <?php if ($canManageRow): ?>

                                                    <!-- Manage button -->
                                                    <a
                                                        href="quiz_view.php?id=<?= (int)$row['id'] ?>"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="settings-2" class="w-4 h-4"></i>
                                                        <span class="action-label">Atur</span>
                                                    </a>

                                                    <!-- Edit button -->
                                                    <button
                                                        type="button"
                                                        onclick="editQuiz(<?= (int)$row['id'] ?>)"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="square-pen" class="w-4 h-4"></i>
                                                        <span class="action-label">Edit</span>
                                                    </button>

                                                    <!-- Status button -->
                                                    <?php $isActive = (int)$row['status'] === 1; ?>

                                                    <button
                                                        type="button"
                                                        onclick="toggleStatusQuiz(<?= (int)$row['id'] ?>, <?= (int)$row['status'] ?>)"
                                                        class="action-btn <?= $isActive ? 'action-secondary' : 'action-success' ?>"
                                                    >
                                                        <i data-lucide="<?= $isActive ? 'archive' : 'refresh-ccw' ?>" class="w-4 h-4"></i>
                                                        <span class="action-label">
                                                            <?= $isActive ? 'Arsipkan' : 'Aktifkan' ?>
                                                        </span>
                                                    </button>

                                                    <!-- Delete button -->
                                                    <button
                                                        type="button"
                                                        onclick="deleteQuiz(<?= (int)$row['id'] ?>)"
                                                        class="action-btn action-danger"
                                                    >
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        <span class="action-label">Hapus</span>
                                                    </button>

                                                <!-- Distributed quiz read only -->
                                                <?php else: ?>

                                                    <!-- View only -->
                                                    <a
                                                        href="quiz_view.php?id=<?= (int)$row['id'] ?>"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                        <span class="action-label">Lihat</span>
                                                    </a>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyRow"
                                class="<?= ($quizQuery && $quizQuery->num_rows > 0) ? 'hidden' : '' ?>"
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

    <!-- =======================================================
        QUIZ MODAL SECTION
    ======================================================= -->
    <div id="quizModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Kuis Baru
                </h3>

                <button
                    id="closeModalQuiz"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Quiz Form ---------- -->
            <form id="quizForm" class="modal-form">

                <div class="global-modal-body">

                <!-- Hidden id -->
                <input type="hidden" name="id">

                    <?php if (!$isAdmin): ?>

                        <input
                            type="hidden"
                            name="created_by"
                            value="<?= $userId ?>"
                        >

                    <?php endif; ?>

                <!-- Quiz title -->
                <div>

                    <label class="form-label">
                        Judul Kuis
                    </label>

                    <input
                        type="text"
                        name="quiz_title"
                        placeholder="Masukkan judul kuis"
                        class="form-input"
                    >

                </div>

                <!-- Description -->
                <div>

                    <label class="form-label">
                        Deskripsi
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Masukkan deskripsi kuis (opsional)"
                        class="form-textarea"
                    ></textarea>

                </div>

                <!-- Teacher -->
                <?php if ($isAdmin): ?>

                    <div>

                        <label class="form-label">
                            Pengajar Penanggung Jawab
                        </label>

                        <!-- Teacher search -->
                        <div class="form-group">
                            <input
                                type="text"
                                id="teacherSearchInput"
                                placeholder="Cari pengajar..."
                                class="form-input"
                            >
                        </div>

                        <!-- Select all -->
                        <div
                            id="multiTeacherControls"
                            class="flex items-center justify-between mb-3"
                        >

                            <label class="checkbox-inline">

                                <input
                                    type="checkbox"
                                    id="selectAllTeachers"
                                    class="checkbox-inline-input"
                                >

                                <span class="checkbox-inline-text">
                                    Pilih Semua
                                </span>

                            </label>

                            <!-- Reset -->
                            <button
                                type="button"
                                id="resetTeachers"
                                class="link-danger-sm"
                            >
                                Reset
                            </button>

                        </div>

                        <!-- Teacher selection list -->
                        <div
                            id="teacherCheckboxWrapper"
                            class="selection-container grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-64 overflow-y-auto"
                        >

                        <?php foreach ($teacherOptions as $subjectName => $grades): ?>

                            <div class="col-span-full">

                                <div class="mb-3 flex items-center gap-2">

                                    <div class="h-px flex-1 bg-gray-200"></div>

                                        <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">
                                            <?= htmlspecialchars($subjectName) ?>
                                        </span>

                                    <div class="h-px flex-1 bg-gray-200"></div>

                                </div>

                                <?php foreach ($grades as $gradeLevel => $teachers): ?>

                                    <div class="mb-4">

                                        <div class="mb-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold">
                                                Tingkat <?= htmlspecialchars($gradeLevel) ?>
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                                            <?php foreach ($teachers as $teacher): ?>

                                                <label
                                                    class="
                                                        teacher-item
                                                        flex items-start gap-2
                                                        p-3 rounded-lg
                                                        border border-gray-200
                                                        bg-white
                                                        hover:border-blue-400
                                                        hover:bg-blue-50
                                                        cursor-pointer
                                                        transition
                                                    "
                                                    data-teacher-id="<?= (int)$teacher['teacher_id'] ?>"
                                                    data-teacher-name="<?= strtolower(htmlspecialchars($teacher['name'])) ?>"
                                                    data-subject-name="<?= strtolower(htmlspecialchars($subjectName)) ?>"
                                                    data-grade-level="<?= strtolower(htmlspecialchars($gradeLevel)) ?>"
                                                >

                                                    <input
                                                        type="checkbox"
                                                        name="teacher_class_ids[]"
                                                        value="<?= (int)$teacher['teacher_id'] ?>|<?= (int)$teacher['class_id'] ?>"
                                                        class="teacher-checkbox checkbox-inline-input"
                                                    >

                                                    <div class="flex flex-col">

                                                        <span class="text-sm font-medium text-gray-700">
                                                            <?= htmlspecialchars($teacher['name']) ?>
                                                        </span>

                                                        <span class="text-xs text-gray-500">
                                                            <?= htmlspecialchars($teacher['class_name'] ?: '-') ?>
                                                        </span>

                                                    </div>

                                                </label>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endforeach; ?>

                            <div
                                id="teacherNoResult"
                                class="hidden col-span-full flex flex-col items-center justify-center py-8 text-gray-400"
                            >
                                <i data-lucide="search-x" class="w-8 h-8 mb-2"></i>
                                <p class="text-sm font-medium">
                                    Pengajar tidak ditemukan.
                                </p>
                            </div>

                        </div>

                    </div>

                <?php endif; ?>

                    <!-- Duration -->
                    <div>

                        <label class="form-label">
                            Durasi Pengerjaan (Menit)
                        </label>

                        <input
                            type="number"
                            name="quiz_duration"
                            min="1"
                            max="600"
                            placeholder="Contoh: 45"
                            class="form-input"
                        >

                    </div>

                    <!-- Schedule -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <!-- Open at -->
                        <div>

                            <label class="form-label">
                                Waktu Mulai
                            </label>

                            <input
                                type="datetime-local"
                                name="open_at"
                                step="1"
                                class="form-input"
                            >

                        </div>

                        <!-- Due date -->
                        <div>

                            <label class="form-label">
                                Batas Akhir
                            </label>

                            <input
                                type="datetime-local"
                                name="due_date"
                                step="1"
                                class="form-input"
                            >

                        </div>

                    </div>

                </div>

                <!-- ---------- Form Buttons ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        id="cancelModalQuiz"
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

    /* =======================================================
        TOAST HELPER
    ======================================================= */
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

    /* =======================================================
        LOADING HELPER
    ======================================================= */
    function showLoading(title = "Memproses...") {

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
        QUIZ ACTIONS
    ======================================================= */

/* ---------- Delete Quiz ---------- */
function deleteQuiz(id) {

    Swal.fire({
        ...getResponsiveSwal(
            "warning",
            "Hapus kuis?",
            "Semua soal, distribusi siswa, dan data terkait akan ikut terhapus."
        ),
        showCancelButton: true,
        confirmButtonText: "Ya, hapus",
        cancelButtonText: "Batal"
    }).then(result => {

        if (!result.isConfirmed) return;

        showLoading("Menghapus data...");

        const formData = new FormData();
        formData.append("id", id);

        fetch("../ajax/quiz/delete_quiz.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(res => {

            Swal.close();

            if (res.status == 1) {

                showToast("success", res.msg);

                setTimeout(() => {
                    location.reload();
                }, 1200);

            } else {

                Swal.fire(
                    getResponsiveSwal(
                        "error",
                        "Gagal",
                        res.msg || "Gagal menghapus data."
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

}


/* ---------- Toggle Quiz Status ---------- */
function toggleStatusQuiz(id, currentStatus) {

    const newStatus = currentStatus === 1 ? 0 : 1;
    const actionText = currentStatus === 1 ? "arsipkan" : "aktifkan";

    Swal.fire({
        ...getResponsiveSwal(
            "warning",
            `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} kuis?`,
            currentStatus === 1
                ? "Kuis akan disembunyikan dari penggunaan aktif."
                : "Kuis akan tersedia kembali."
        ),
        showCancelButton: true,
        confirmButtonText: `Ya, ${actionText}`,
        cancelButtonText: "Batal"
    })

    .then(result => {

        if (!result.isConfirmed) return;

        showLoading("Memproses...");

        fetch("../ajax/quiz/toggle_quiz_status.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `id=${id}&status=${newStatus}`
        })

        .then(res => res.json())

        .then(res => {

            Swal.close();

            if (res.status == 1) {

                showToast("success", res.msg);

                setTimeout(() => {
                    location.reload();
                }, 1000);

            } else {

                Swal.fire(
                    getResponsiveSwal(
                        "error",
                        "Gagal",
                        res.msg || "Gagal memperbarui status."
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

}

    /* ---------- Edit Quiz ---------- */
    function editQuiz(id) {

        showLoading(
            "Memuat data..."
        );

        fetch(
            `../ajax/quiz/get_quiz.php?id=${id}`
        )

        .then(
            res =>
                res.json()
        )

        .then(
            res => {

                Swal.close();

                if (
                    res.status !==
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

                const modal = document.getElementById("quizModal");
                const form = document.getElementById("quizForm");
                const modalTitle = document.getElementById("modalTitle");

                const teacherCheckboxes = [...document.querySelectorAll(".teacher-checkbox")];
                const teacherItems = [...document.querySelectorAll(".teacher-item")];
                const data = res.data;
                modal.classList.remove("hidden");
                modal.classList.add("flex");
                modalTitle.textContent ="Edit Data Kuis";

                /* ---------- ID ---------- */
                form.querySelector(
                    '[name="id"]'
                ).value =
                    data.id;

                /* ---------- Title ---------- */
                form.querySelector(
                    '[name="quiz_title"]'
                ).value =
                    data.quiz_title ||
                    "";

                /* ---------- Description ---------- */
                form.querySelector(
                    '[name="description"]'
                ).value =
                    data.description ||
                    "";

                /* =======================================================
                    RESET TEACHER CHECKBOX
                ======================================================= */
                teacherCheckboxes.forEach(cb => {
                    cb.checked = false;
                });

                /* =======================================================
                    SET SELECTED TEACHER + CLASS CHECKBOXES
                ======================================================= */
                if (Array.isArray(data.teacher_class_ids)) {

                    teacherCheckboxes.forEach(cb => {

                        if (data.teacher_class_ids.includes(cb.value)) {
                            cb.checked = true;
                        }

                    });

                }

                /* =======================================================
                    RESET SEARCH + DISPLAY
                ======================================================= */
                const teacherSearchInput = document.getElementById("teacherSearchInput");
                const selectAllTeachers = document.getElementById("selectAllTeachers");

                if (teacherSearchInput) {
                    teacherSearchInput.value = "";
                }

                teacherItems.forEach(item => {
                    item.style.display = "flex";
                });

                /* =======================================================
                    AUTO CHECK PILIH SEMUA
                ======================================================= */
                if (selectAllTeachers) {

                    const totalVisibleTeachers = teacherCheckboxes.length;

                    const checkedTeachersCount = teacherCheckboxes.filter(
                        cb => cb.checked
                    ).length;

                    selectAllTeachers.checked =
                        totalVisibleTeachers > 0 &&
                        checkedTeachersCount === totalVisibleTeachers;

                }

                teacherItems.forEach(item => {
                    item.style.display = "flex";
                });

                /* ---------- Duration ---------- */
                form.querySelector(
                    '[name="quiz_duration"]'
                ).value =
                    data.quiz_duration || "";

                /* ---------- Open At ---------- */
                form.querySelector(
                    '[name="open_at"]'
                ).value =
                    data.open_at
                        ? data.open_at.replace(
                            " ",
                            "T"
                        ).slice(
                            0,
                            19
                        )
                        : "";

                /* ---------- Due Date ---------- */
                form.querySelector(
                    '[name="due_date"]'
                ).value =
                    data.due_date
                        ? data.due_date.replace(
                            " ",
                            "T"
                        ).slice(
                            0,
                            19
                        )
                        : "";

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

/* =======================================================
        TEACHER DROPDOWN HELPERS
    ======================================================= */

/* ---------- Toggle Teacher Dropdown ---------- */
function toggleTeacherDropdown(event, dropdownId) {

    event.stopPropagation();

    /* Close other dropdowns */
    closeAllTeacherDropdowns();

    const target = document.getElementById(dropdownId);
    if (!target) return;

    /* Close dropdown if already open */
    if (!target.classList.contains("hidden")) {
        target.classList.add("hidden");
        return;
    }

    const button = event.currentTarget;
    const rect = button.getBoundingClientRect();

    target.classList.remove("hidden");

    /* Horizontal position */
    let left = rect.left + (rect.width / 2) - (target.offsetWidth / 2);

    /* Prevent left overflow */
    if (left < 10) left = 10;

    /* Prevent right overflow */
    if (left + target.offsetWidth > window.innerWidth - 10) {
        left = window.innerWidth - target.offsetWidth - 10;
    }

    /* Vertical position */
    const top = rect.bottom + 8;

    target.style.top = `${top}px`;
    target.style.left = `${left}px`;
}

/* ---------- Close All Teacher Dropdowns ---------- */
function closeAllTeacherDropdowns() {
    document.querySelectorAll('[id^="teacher-dropdown-"]').forEach(drop => {
        drop.classList.add("hidden");
    });
}

document.addEventListener("click", function() {
    closeAllTeacherDropdowns();
});

window.addEventListener("scroll", closeAllTeacherDropdowns, true);
window.addEventListener("resize", closeAllTeacherDropdowns);

    document.addEventListener(
        "DOMContentLoaded",
        () => {

        /* =======================================================
            STORAGE CONFIGURATION
        ======================================================= */
        const STORAGE_KEYS = {
            search:
                "quiz_search_keyword",
            rows:
                "quiz_rows_per_page",
            page:
                "quiz_current_page"
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

        const modal =
            document.getElementById(
                "quizModal"
            );

        const form =
            document.getElementById(
                "quizForm"
            );

        const openBtn =
            document.getElementById(
                "newQuiz"
            );

        const closeBtn =
            document.getElementById(
                "closeModalQuiz"
            );

        const cancelBtn =
            document.getElementById(
                "cancelModalQuiz"
            );

        const modalTitle =
            document.getElementById(
                "modalTitle"
            );

        /* =======================================================
            TEACHER MULTI SELECT REFERENCES
        ======================================================= */
        const teacherSearchInput =
            document.getElementById("teacherSearchInput");

        const selectAllTeachers =
            document.getElementById("selectAllTeachers");

        const resetTeachers =
            document.getElementById("resetTeachers");

        const teacherCheckboxes =
            [...document.querySelectorAll(".teacher-checkbox")];

        const teacherItems =
            [...document.querySelectorAll(".teacher-item")];

        const teacherNoResult =
            document.getElementById("teacherNoResult");

        /* =======================================================
            SEARCH TEACHER
        ======================================================= */
        if (teacherSearchInput) {

            teacherSearchInput.addEventListener("input", function () {

                const keyword = this.value.toLowerCase().trim();
                let visibleCount = 0;

                teacherItems.forEach(item => {

                const teacherName = item.dataset.teacherName || "";
                const subjectName = item.dataset.subjectName || "";
                const gradeLevel = item.dataset.gradeLevel || "";

                const isVisible =
                    teacherName.includes(keyword) ||
                    subjectName.includes(keyword) ||
                    gradeLevel.includes(keyword);

                    item.style.display = isVisible
                        ? "flex"
                        : "none";

                    if (isVisible) visibleCount++;

                });

                if (teacherNoResult) {
                    teacherNoResult.classList.toggle(
                        "hidden",
                        visibleCount > 0
                    );
                }

                const visibleCheckboxes = teacherItems
                    .filter(item => item.style.display !== "none")
                    .map(item => item.querySelector(".teacher-checkbox"));

                const allChecked = visibleCheckboxes.length > 0 &&
                    visibleCheckboxes.every(cb => cb.checked);

                selectAllTeachers.checked = allChecked;

            });

        }

        /* =======================================================
            SELECT ALL TEACHERS
        ======================================================= */
        if (selectAllTeachers) {

            selectAllTeachers.addEventListener("change", function () {

                teacherItems.forEach(item => {

                    if (item.style.display !== "none") {

                        const checkbox = item.querySelector(".teacher-checkbox");

                        if (checkbox) {
                            checkbox.checked = this.checked;
                        }

                    }

                });

            });

        }

        /* =======================================================
            RESET TEACHERS
        ======================================================= */
        if (resetTeachers) {

            resetTeachers.addEventListener("click", function () {

                teacherCheckboxes.forEach(cb => {
                    cb.checked = false;
                });

                selectAllTeachers.checked = false;

                if (teacherSearchInput) {
                    teacherSearchInput.value = "";
                }

                teacherItems.forEach(item => {
                    item.style.display = "flex";
                });

                if (teacherNoResult) {
                    teacherNoResult.classList.add("hidden");
                }

            });

        }

        /* =======================================================
            SELECT ALL STATE
        ======================================================= */
        teacherCheckboxes.forEach(cb => {

            cb.addEventListener("change", function () {

                const visibleCheckboxes = teacherItems
                    .filter(item => item.style.display !== "none")
                    .map(item => item.querySelector(".teacher-checkbox"));

                const allChecked = visibleCheckboxes.length > 0 &&
                    visibleCheckboxes.every(box => box.checked);

                selectAllTeachers.checked = allChecked;

            });

        });

        /* =======================================================
            INLINE VALIDATION
        ======================================================= */
        const titleInput = form.querySelector('[name="quiz_title"]');
        const descriptionInput = form.querySelector('[name="description"]');
        const durationInput = form.querySelector('[name="quiz_duration"]');
        const openAtInput = form.querySelector('[name="open_at"]');
        const dueDateInput = form.querySelector('[name="due_date"]');

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

        function clearTeacherWrapperError() {
            const wrapper = document.getElementById("teacherCheckboxWrapper");
            if (!wrapper) return;

            wrapper.classList.remove("border-red-500");
            wrapper.classList.add("border-gray-300");

            wrapper.parentElement
                .querySelectorAll(".inline-error, .teacher-inline-error")
                .forEach(el => el.remove());
        }

        function clearInlineErrors() {
            form.querySelectorAll("input, textarea, select").forEach(input => {
                clearFieldError(input);
            });

            clearTeacherWrapperError();
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

        function setTeacherWrapperError(message) {
            const wrapper = document.getElementById("teacherCheckboxWrapper");
            if (!wrapper) return;

            wrapper.classList.remove("border-gray-300");
            wrapper.classList.add("border-red-500");

            wrapper.parentElement
                .querySelectorAll(".inline-error, .teacher-inline-error")
                .forEach(el => el.remove());

            const error = document.createElement("p");
            error.className = "inline-error text-xs text-red-500 mt-2";
            error.textContent = message;

            wrapper.parentElement.appendChild(error);
        }

        function attachLiveValidation(input) {
            if (!input) return;

            input.addEventListener("input", () => clearFieldError(input));
            input.addEventListener("change", () => clearFieldError(input));
        }

        attachLiveValidation(titleInput);
        attachLiveValidation(descriptionInput);
        attachLiveValidation(durationInput);
        attachLiveValidation(openAtInput);
        attachLiveValidation(dueDateInput);
        attachLiveValidation(teacherSearchInput);

        teacherCheckboxes.forEach(cb => {
            cb.addEventListener("change", clearTeacherWrapperError);
        });

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

            closeAllTeacherDropdowns();

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

            /* Previous */
            createButton(
                "Sebelumnya",
                currentPage === 1,
                () => {
                    currentPage--;
                    closeAllTeacherDropdowns();
                    renderTable();
                }
            );

            /* Page Numbers */
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
                    closeAllTeacherDropdowns();
                    renderTable();
                }
            );

        }

        /* =======================================================
            SEARCH
        ======================================================= */
        searchInput.addEventListener(
            "input",
            () => {

                closeAllTeacherDropdowns();

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
                closeAllTeacherDropdowns();

                renderTable();

            }
        );

        /* =======================================================
            ROW LIMIT
        ======================================================= */
        rowsPerPage.addEventListener(
            "change",
            () => {

            closeAllTeacherDropdowns();

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
            MODAL CONTROL
        ======================================================= */
        function openQuizModal() {

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            form.reset();

            clearInlineErrors();

            form.querySelector(
                '[name="id"]'
            ).value = "";

            modalTitle.textContent =
                "Tambah Kuis Baru";

            /* =======================================================
                RESET TEACHER SECTION
            ======================================================= */
            if (teacherSearchInput) {
                teacherSearchInput.value = "";
            }

            if (selectAllTeachers) {
                selectAllTeachers.checked = false;
            }

            teacherCheckboxes.forEach(cb => {
                cb.checked = false;
            });

            teacherItems.forEach(item => {
                item.style.display = "flex";
            });

            if (teacherNoResult) {
                teacherNoResult.classList.add("hidden");
            }

            /* =======================================================
                AUTO FOCUS TITLE INPUT
            ======================================================= */
            setTimeout(() => {
                titleInput.focus();
            }, 150);

        }

        function closeQuizModal() {

            modal.classList.add("hidden");
            modal.classList.remove("flex");

            clearInlineErrors();

        }

        /* ---------- Open ---------- */
        if (
            openBtn
        ) {

            openBtn.addEventListener(
                "click",
                openQuizModal
            );

        }

        /* ---------- Close ---------- */
        if (
            closeBtn
        ) {

            closeBtn.addEventListener(
                "click",
                closeQuizModal
            );

        }

        if (
            cancelBtn
        ) {

            cancelBtn.addEventListener(
                "click",
                closeQuizModal
            );

        }

        /* ---------- Outside Click ---------- */
        modal.addEventListener(
            "click",
            e => {

                if (
                    e.target ===
                    modal
                ) {

                    closeQuizModal();

                }

            }
        );

        /* ---------- ESC ---------- */
        document.addEventListener(
            "keydown",
            e => {

                if (
                    e.key ===
                        "Escape" &&
                    !modal.classList.contains(
                        "hidden"
                    )
                ) {

                    closeQuizModal();

                }

            }
        );

        /* =======================================================
            FORM SUBMISSION
        ======================================================= */
        form.addEventListener(
            "submit",
            e => {

                e.preventDefault();

                clearInlineErrors();

                const submitBtn =
                    form.querySelector(
                        'button[type="submit"]'
                    );

                submitBtn.disabled =
                    true;

                submitBtn.classList.add(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                const resetSubmitButton = () => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove(
                        "opacity-70",
                        "cursor-not-allowed"
                    );
                };

                const scrollToField = field => {
                    if (!field) return;

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

                /* ---------- Title Validation ---------- */
                if (titleInput.value.trim() === "") {
                    setInlineError(titleInput, "Judul kuis wajib diisi.");
                    scrollToField(titleInput);
                    resetSubmitButton();
                    return;
                }

                /* ---------- Teacher Validation ---------- */
                const teacherFields =
                    form.querySelectorAll('[name="teacher_class_ids[]"]');

                if (teacherFields.length > 0) {
                    const checkedTeachers =
                        form.querySelectorAll('[name="teacher_class_ids[]"]:checked');

                    if (checkedTeachers.length === 0) {
                        setTeacherWrapperError("Minimal satu pengajar wajib dipilih.");
                        scrollToField(document.getElementById("teacherCheckboxWrapper"));
                        resetSubmitButton();
                        return;
                    }
                }

                /* ---------- Duration Validation ---------- */
                if (
                    durationInput.value.trim() === "" ||
                    parseInt(durationInput.value) <= 0
                ) {
                    setInlineError(durationInput, "Durasi kuis wajib diisi.");
                    scrollToField(durationInput);
                    resetSubmitButton();
                    return;
                }

                /* ---------- Open At Validation ---------- */
                const openAt = openAtInput.value;
                const dueDate = dueDateInput.value;

                if (!openAt) {
                    setInlineError(openAtInput, "Waktu mulai wajib diisi.");
                    scrollToField(openAtInput);
                    resetSubmitButton();
                    return;
                }

                /* ---------- Due Date Validation ---------- */
                if (!dueDate) {
                    setInlineError(dueDateInput, "Batas akhir wajib diisi.");
                    scrollToField(dueDateInput);
                    resetSubmitButton();
                    return;
                }

                /* ---------- Date Order Validation ---------- */
                if (
                    openAt &&
                    dueDate &&
                    new Date(dueDate) <= new Date(openAt)
                ) {
                    setInlineError(
                        dueDateInput,
                        "Batas akhir harus lebih besar dari waktu mulai."
                    );

                    scrollToField(dueDateInput);
                    resetSubmitButton();
                    return;
                }

                showLoading(
                    "Menyimpan data..."
                );

                const formData =
                    new FormData(
                        form
                    );

                fetch(
                    "../ajax/quiz/save_quiz.php",
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

                        submitBtn.disabled =
                            false;

                        submitBtn.classList.remove(
                            "opacity-70",
                            "cursor-not-allowed"
                        );

                        /* ---------- Success ---------- */
                        if (
                            res.status ==
                            1
                        ) {

                            closeQuizModal();

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

                            return;
                        }

                        /* ---------- Validation ---------- */
                        if (res.status == 2) {

                            const msg = res.msg.toLowerCase();

                            if (msg.includes("judul")) {

                                setInlineError(titleInput, res.msg);
                                scrollToField(titleInput);

                            } else if (msg.includes("pengajar")) {

                                setTeacherWrapperError(res.msg);
                                scrollToField(document.getElementById("teacherCheckboxWrapper"));

                            } else if (msg.includes("durasi")) {

                                setInlineError(durationInput, res.msg);
                                scrollToField(durationInput);

                            } else if (msg.includes("mulai")) {

                                setInlineError(openAtInput, res.msg);
                                scrollToField(openAtInput);

                            } else if (
                                msg.includes("batas") ||
                                msg.includes("akhir") ||
                                msg.includes("selesai")
                            ) {

                                setInlineError(dueDateInput, res.msg);
                                scrollToField(dueDateInput);

                            } else {

                                setInlineError(titleInput, res.msg);
                                scrollToField(titleInput);

                            }

                            return;
                        }

                        /* ---------- System Error ---------- */
                        Swal.fire(
                            getResponsiveSwal(
                                "error",
                                "Gagal",
                                res.msg ||
                                "Gagal menyimpan data."
                            )
                        );

                    }
                )

                .catch(
                    () => {

                        Swal.close();

                        submitBtn.disabled =
                            false;

                        submitBtn.classList.remove(
                            "opacity-70",
                            "cursor-not-allowed"
                        );

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
            INITIAL TABLE RENDER
        ======================================================= */
        renderTable();

    });
    </script>

</body>
</html>