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
if (!isset($_SESSION['login_id'])) {
    header("Location: ../login.php");
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
    QUIZ VALIDATION
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
    $quizWhere[] = "
        (
            q.created_by = {$userId}
            OR EXISTS (
                SELECT 1
                FROM quiz_teacher_list qtl
                WHERE qtl.quiz_id = q.id
                AND qtl.teacher_id = {$teacherId}
            )
        )
    ";
}

$quizWhereClause = implode(" AND ", $quizWhere);

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

        (
            SELECT GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', ')
            FROM quiz_teacher_list qtl_subject
            INNER JOIN teachers t_subject
                ON qtl_subject.teacher_id = t_subject.id
            INNER JOIN subjects s
                ON t_subject.subject_id = s.id
            WHERE qtl_subject.quiz_id = q.id
        ) AS subject_name

    FROM quiz_list q
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

$canManageQuiz = $isAdmin || ($isTeacher && $isOwnerQuiz);

/* =======================================================
    QUIZ TEACHER IDS
======================================================= */
$quizTeacherIds = [];

$teacherListQuery = $conn->query("
    SELECT teacher_id
    FROM quiz_teacher_list
    WHERE quiz_id = {$quizId}
");

if ($teacherListQuery) {
    while ($row = $teacherListQuery->fetch_assoc()) {
        $quizTeacherIds[] = (int) $row['teacher_id'];
    }
}

/* =======================================================
    TEACHER FILTER OPTIONS (ADMIN ONLY)
======================================================= */
$selectedTeacherFilter = isset($_GET['teacher_filter'])
    ? (int) $_GET['teacher_filter']
    : 0;

$teacherFilterOptions = [];

if ($isAdmin && count($quizTeacherIds) > 0) {

    $teacherIdsString = implode(',', $quizTeacherIds);

    $teacherFilterQuery = $conn->query("
        SELECT
            t.id,
            u.name
        FROM teachers t
        INNER JOIN users u
            ON t.user_id = u.id
        WHERE t.id IN ({$teacherIdsString})
        ORDER BY u.name ASC
    ");

    if ($teacherFilterQuery) {
        while ($teacher = $teacherFilterQuery->fetch_assoc()) {
            $teacherFilterOptions[] = $teacher;
        }
    }
}

/* =======================================================
    AVAILABLE STUDENTS
======================================================= */
$studentWhere = [];

if ($isTeacher && $canManageQuiz) {

    $studentWhere[] = "
        s.class_id IN (
            SELECT class_id
            FROM teacher_class_assignments
            WHERE teacher_id = {$teacherId}
            AND status = 1
        )
    ";

} elseif ($isTeacher && !$canManageQuiz) {

    $studentWhere[] = "1 = 0";

} elseif ($isAdmin && count($quizTeacherIds) > 0) {

    $teacherIdsString = implode(',', $quizTeacherIds);

    $studentWhere[] = "
        s.class_id IN (
            SELECT class_id
            FROM teacher_class_assignments
            WHERE teacher_id IN ({$teacherIdsString})
            AND status = 1
        )
    ";
}

$studentWhereClause = count($studentWhere)
    ? "WHERE " . implode(" AND ", $studentWhere)
    : "";

$studentOptions = $conn->query("
    SELECT
        s.id,
        u.name,
        c.class_name
    FROM students s
    INNER JOIN users u
        ON s.user_id = u.id
    INNER JOIN classes c
        ON s.class_id = c.id
    {$studentWhereClause}
    ORDER BY
        c.class_name ASC,
        u.name ASC
");

/* =======================================================
    QUIZ DISTRIBUTION STATISTICS
======================================================= */
$totalAssignments = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM quiz_student_list
    WHERE quiz_id = {$quizId}
")->fetch_assoc()['total'];

$totalActiveStudents = (int) $conn->query("
    SELECT COUNT(DISTINCT student_id) AS total
    FROM quiz_student_list
    WHERE quiz_id = {$quizId}
    AND status = 1
")->fetch_assoc()['total'];

$totalClasses = (int) $conn->query("
    SELECT COUNT(DISTINCT s.class_id) AS total
    FROM quiz_student_list qsl
    INNER JOIN students s
        ON qsl.student_id = s.id
    WHERE qsl.quiz_id = {$quizId}
    AND qsl.status = 1
")->fetch_assoc()['total'];

/* =======================================================
    MAIN QUIZ DISTRIBUTION QUERY
======================================================= */
$distributionWhere = [];
$distributionWhere[] = "qsl.quiz_id = {$quizId}";

if ($isTeacher) {
    $distributionWhere[] = "
        s.class_id IN (
            SELECT class_id
            FROM teacher_class_assignments
            WHERE teacher_id = {$teacherId}
            AND status = 1
        )
    ";
}

elseif ($isAdmin && $selectedTeacherFilter > 0) {

    $distributionWhere[] = "
        s.class_id IN (
            SELECT class_id
            FROM teacher_class_assignments
            WHERE teacher_id = {$selectedTeacherFilter}
            AND status = 1
        )
    ";
}

$distributionWhereClause = implode(" AND ", $distributionWhere);
$distributionQuery = $conn->query("
    SELECT
        qsl.id,
        qsl.student_id,
        qsl.quiz_id,
        qsl.status,
        qsl.assigned_at,

        u.name AS student_name,
        c.class_name,

        GROUP_CONCAT(DISTINCT tca.teacher_id) AS teacher_ids

    FROM quiz_student_list qsl

    INNER JOIN students s
        ON qsl.student_id = s.id

    INNER JOIN users u
        ON s.user_id = u.id

    INNER JOIN classes c
        ON s.class_id = c.id

    LEFT JOIN teacher_class_assignments tca
        ON tca.class_id = s.class_id
        AND tca.status = 1

    WHERE {$distributionWhereClause}

    GROUP BY
        qsl.id,
        qsl.student_id,
        qsl.quiz_id,
        qsl.status,
        qsl.assigned_at,
        u.name,
        c.class_name

    ORDER BY
        c.class_name ASC,
        u.name ASC
");

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Distribusi Kuis Siswa | " . htmlspecialchars($quiz['quiz_title']);

/* =======================================================
    PAGE LAYOUT
======================================================= */
$distributionColspan = 5;
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

                    <a href="quiz_view.php?id=<?= $quizId ?>" class="back-button">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Kembali ke Detail Kuis
                    </a>

                </div>

                <!-- ---------- Quiz Distribution Header Card ---------- -->

                <div class="section-card">

                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">

                        <!-- Quiz information -->
                        <div class="flex-1">

                            <div class="flex flex-wrap items-center gap-3 mb-3">

                                <h2 class="section-title text-xl sm:text-2xl">
                                    Distribusi Kuis Siswa
                                </h2>

                                <span class="status-badge <?= $quiz['status'] == 1 ? 'status-success' : 'status-danger' ?>">
                                    <?= $quiz['status'] == 1 ? 'Aktif' : 'Nonaktif' ?>
                                </span>

                            </div>

                            <p class="text-lg font-semibold text-blue-700 mb-2">
                                <?= htmlspecialchars($quiz['quiz_title']) ?>
                            </p>

                            <p class="text-gray-600 mb-4">
                                <?= htmlspecialchars($quiz['description'] ?: 'Distribusi kuis kepada siswa berdasarkan kelas.') ?>
                            </p>

                            <!-- Quiz metadata -->
                            <div class="flex flex-wrap gap-3">

                                <!-- Duration -->
                                <div class="inline-flex items-center gap-2 bg-gray-100 px-4 py-2 text-sm rounded-xl text-gray-800">
                                    <i data-lucide="clock-3" class="w-4 h-4"></i>
                                    <?= (int) $quiz['quiz_duration'] ?> menit
                                </div>

                                <!-- Subject -->
                                <?php
                                    $subjects = !empty($quiz['subject_name'])
                                        ? array_filter(array_map('trim', explode(',', $quiz['subject_name'])))
                                        : [];

                                    $subjectCount = count($subjects);
                                ?>

                                <?php if ($subjectCount <= 1): ?>

                                    <div class="inline-flex items-center gap-2 bg-gray-100 px-4 py-2 rounded-xl text-gray-800">
                                        <i data-lucide="book-open" class="w-4 h-4"></i>
                                        <?= htmlspecialchars($subjects[0] ?? '-') ?>
                                    </div>

                                <?php else: ?>

                                    <div class="relative inline-block">

                                        <button
                                            type="button"
                                            onclick="toggleSubjectDropdown(event, 'subject-dropdown-<?= (int)$quiz['id'] ?>')"
                                            class="table-dropdown-btn text-sm px-4 py-2"
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

                                            <?php foreach ($subjects as $subject): ?>

                                                <div class="table-dropdown-item">
                                                    <?= htmlspecialchars($subject) ?>
                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <!-- Total student -->
                                <div class="inline-flex items-center gap-2 bg-gray-100 px-4 py-2 text-sm rounded-xl text-gray-800">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                    <?= $totalActiveStudents ?> siswa aktif
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">

                    <!-- Total distribution -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="network" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Distribusi</p>
                            <h3 class="stat-value"><?= $totalAssignments ?></h3>
                            <p class="stat-label">Penugasan siswa</p>
                        </div>
                    </div>

                    <!-- Active students -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="graduation-cap" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="stat-label">Siswa Aktif</p>
                            <h3 class="stat-value"><?= $totalActiveStudents ?></h3>
                            <p class="stat-label">Sudah menerima kuis</p>
                        </div>
                    </div>

                    <!-- Connected classes -->
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-600">
                            <i data-lucide="school" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <p class="stat-label">Kelas Terhubung</p>
                            <h3 class="stat-value"><?= $totalClasses ?></h3>
                            <p class="stat-label">Distribusi kelas aktif</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                QUIZ DISTRIBUTION TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Manajemen Distribusi Kuis Siswa
                    </h2>

                    <!-- Add quiz distribution button -->
                    <?php if ($canManageQuiz): ?>
                        <button
                            id="newDistribution"
                            type="button"
                            class="form-btn form-btn-primary"
                        >
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Tambah Distribusi
                        </button>
                    <?php endif; ?>

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

                    <!-- Teacher filter -->
                    <?php if ($isAdmin && count($teacherFilterOptions) > 0): ?>

                        <select id="teacherFilter" class="filter-select">

                            <option value="0">Semua Pengajar</option>

                            <?php foreach ($teacherFilterOptions as $teacher): ?>

                                <option value="<?= (int)$teacher['id'] ?>">
                                    <?= htmlspecialchars($teacher['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    <?php endif; ?>

                    <!-- Search input -->
                    <div class="search-wrapper">

                        <input
                            id="searchInput"
                            type="text"
                            placeholder="Cari distribusi kuis siswa..."
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
                                <th class="table-th w-[40%]">Siswa</th>
                                <th class="table-th w-[15%]">Kelas</th>
                                <th class="table-th w-[15%]">Status</th>
                                <th class="table-th w-[25%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($distributionQuery && $distributionQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($row = $distributionQuery->fetch_assoc()): ?>

                                    <tr class="app-table-row" data-teacher-ids="<?= htmlspecialchars($row['teacher_ids'] ?? '') ?>">

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

                                        <!-- Class -->
                                        <td class="table-td text-center">
                                            <?= htmlspecialchars($row['class_name']) ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="table-td text-center">

                                            <?php if ((int)$row['status'] === 1): ?>

                                                <span class="status-badge status-success">
                                                    Aktif
                                                </span>

                                            <?php else: ?>

                                                <span class="status-badge status-danger">
                                                    Nonaktif
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- Action buttons -->
                                        <td class="table-td text-center">

                                            <?php if ($canManageQuiz): ?>

                                                <div class="action-group">

                                                    <!-- Status button -->
                                                    <?php $isActive = (int)$row['status'] === 1; ?>

                                                    <button
                                                        type="button"
                                                        onclick="toggleDistributionStatus(<?= (int)$row['id'] ?>, <?= $isActive ? 0 : 1 ?>)"
                                                        class="action-btn <?= $isActive ? 'action-secondary' : 'action-success' ?>"
                                                    >
                                                        <i data-lucide="<?= $isActive ? 'archive' : 'check-circle' ?>" class="w-4 h-4"></i>
                                                        <span class="action-label"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></span>
                                                    </button>

                                                    <!-- Delete button -->
                                                    <button
                                                        type="button"
                                                        onclick="deleteDistribution(<?= (int)$row['id'] ?>)"
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

                                <?php endwhile; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyRow"
                                class="<?= ($distributionQuery && $distributionQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $distributionColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="graduation-cap" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada distribusi kuis siswa tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $distributionColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada distribusi kuis siswa yang sesuai dengan pencarian
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
        QUIZ DISTRIBUTION MODAL SECTION
    ======================================================= -->
    <div id="distributionModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Distribusi Kuis Siswa
                </h3>

                <button
                    id="closeModalDistribution"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Quiz Distribution Form ---------- -->
            <form id="distributionForm" class="modal-form">

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id">
                    <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

                    <!-- Search input -->
                    <div>

                        <label class="form-label">
                            Cari Siswa
                        </label>

                        <input
                            type="text"
                            id="studentSearchInput"
                            placeholder="Cari nama siswa atau kelas..."
                            class="form-input"
                        >

                    </div>

                    <!-- Student multi select -->
                    <div>

                        <div id="multiStudentControls" class="bulk-toolbar">

                            <label class="checkbox-inline">

                                <input
                                    type="checkbox"
                                    id="selectAllStudents"
                                    class="checkbox-inline-input"
                                >

                                <span class="text-sm font-medium text-gray-700">
                                    Pilih Semua
                                </span>

                            </label>

                            <!-- Reset -->
                            <button
                                type="button"
                                id="clearAllStudents"
                                class="link-danger-sm"
                            >
                                Reset
                            </button>

                        </div>

                        <!-- Student select -->
                        <div
                            id="studentCheckboxWrapper"
                            class="checkbox-grid grid-cols-1 sm:grid-cols-2"
                        >

                            <?php if ($studentOptions && $studentOptions->num_rows > 0): ?>

                                <?php while ($student = $studentOptions->fetch_assoc()): ?>

                                    <label
                                        class="student-item checkbox-card"
                                        data-student-name="<?= strtolower(htmlspecialchars($student['name'] . ' ' . $student['class_name'])) ?>"
                                    >

                                        <input
                                            type="checkbox"
                                            name="student_ids[]"
                                            value="<?= (int)$student['id'] ?>"
                                            class="class-checkbox checkbox-inline-input"
                                        >

                                        <div class="flex-1">

                                            <div class="font-medium text-sm text-gray-800">
                                                <?= htmlspecialchars($student['name']) ?>
                                            </div>

                                            <div class="text-xs text-gray-500 mt-1">
                                                <?= htmlspecialchars($student['class_name']) ?>
                                            </div>

                                        </div>

                                    </label>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <!-- ---------- Form Buttons ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        id="cancelModalDistribution"
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

        const isMobile = window.innerWidth < 640;

        return {
            icon,
            title,
            text,
            width: isMobile ? "90%" : "32rem",
            padding: isMobile ? "1.25rem" : "2rem",
            buttonsStyling: false,
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
                    flex flex-row-reverse justify-center gap-3 mt-6
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

    /* ---------- Loading Helper ---------- */
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
        STUDENT SELECTION HELPERS
    ======================================================= */
    function updateSelectAllStudentAvailability() {

    const studentCheckboxes =
        [...document.querySelectorAll(".student-checkbox")];

    const selectAllStudents =
        document.getElementById("selectAllStudents");

    const availableCheckboxes =
        studentCheckboxes.filter(cb => !cb.disabled);

    if (availableCheckboxes.length === 0) {

        selectAllStudents.checked = false;
        selectAllStudents.disabled = true;

        selectAllStudents.closest("label").classList.add(
            "opacity-50",
            "cursor-not-allowed"
        );

        return;
    }

    selectAllStudents.disabled = false;

    selectAllStudents.closest("label").classList.remove(
        "opacity-50",
        "cursor-not-allowed"
    );

    const allChecked =
        availableCheckboxes.length > 0 &&
        availableCheckboxes.every(cb => cb.checked);

    selectAllStudents.checked = allChecked;
}

    /* =======================================================
        TOGGLE DISTRIBUTION STATUS
    ======================================================= */
    function toggleDistributionStatus(id, status) {

    const title = status === 1
        ? "Aktifkan distribusi?"
        : "Nonaktifkan distribusi?";

    const text = status === 1
        ? "Siswa akan dapat mengakses kuis kembali."
        : "Siswa tidak akan dapat mengakses kuis sementara.";

    Swal.fire({
        ...getResponsiveSwal(
            "warning",
            title,
            text
        ),
        showCancelButton: true,
        confirmButtonText: status === 1 ? "Ya, aktifkan" : "Ya, nonaktifkan",
        cancelButtonText: "Batal"
    }).then(result => {

        if (!result.isConfirmed) return;

        showLoading("Memperbarui status...");

        const formData = new FormData();
        formData.append("id", id);
        formData.append("status", status);

        fetch("../ajax/quiz_distribution/toggle_quiz_distribution.php", {
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

                return;
            }

            Swal.fire(
                getResponsiveSwal(
                    "error",
                    "Gagal",
                    res.msg || "Gagal memperbarui status."
                )
            );

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

    /* =======================================================
        DELETE DISTRIBUTION
    ======================================================= */
    function deleteDistribution(id) {

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                "Hapus distribusi?",
                "Distribusi Kuis siswa akan dihapus permanen."
            ),
            showCancelButton: true,
            confirmButtonText: "Ya, hapus",
            cancelButtonText: "Batal"
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Menghapus distribusi...");

            const formData = new FormData();
            formData.append("id", id);

            fetch("../ajax/quiz_distribution/delete_quiz_distribution.php", {
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
                                res.msg || "Gagal menghapus distribusi."
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

    /* =======================================================
        SUBJECT DROPDOWN
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

        const rect = event.currentTarget.getBoundingClientRect();

        target.classList.remove("hidden");

        let left = rect.left + (rect.width / 2) - (target.offsetWidth / 2);

        if (left < 10) left = 10;

        if (left + target.offsetWidth > window.innerWidth - 10) {
            left = window.innerWidth - target.offsetWidth - 10;
        }

        target.style.left = `${left}px`;
        target.style.top = `${rect.bottom + 8}px`;
    }

    document.addEventListener("click", closeAllSubjectDropdowns);
    window.addEventListener("scroll", closeAllSubjectDropdowns, true);
    window.addEventListener("resize", closeAllSubjectDropdowns);

    document.addEventListener("DOMContentLoaded", () => {

        /* =======================================================
            STORAGE CONFIG
        ======================================================= */
        const STORAGE_KEYS = {
            search: "quiz_distribution_search_keyword",
            rows: "quiz_distribution_rows_per_page",
            page: "quiz_distribution_current_page"
        };

        const navigationType =
            performance.getEntriesByType("navigation")[0]?.type || "navigate";

        const shouldRestore = navigationType === "reload";

        if (!shouldRestore) {
            sessionStorage.removeItem(STORAGE_KEYS.search);
            sessionStorage.removeItem(STORAGE_KEYS.rows);
            sessionStorage.removeItem(STORAGE_KEYS.page);
        }

        /* =======================================================
            ICON INITIALIZATION
        ======================================================= */
        if (window.lucide) {
            lucide.createIcons();
        }

        /* =======================================================
            ELEMENT REFERENCES
        ======================================================= */
        const table = document.getElementById("dataTable");
        if (!table) return;

        const rows = [...table.querySelectorAll("tbody tr")].filter(
            row => row.id !== "emptyRow" && row.id !== "noResult"
        );

        const searchInput = document.getElementById("searchInput");
        const rowsPerPage = document.getElementById("rowsPerPage");
        const teacherFilter = document.getElementById("teacherFilter");
        const pageInfo = document.getElementById("pageInfo");
        const pagination = document.getElementById("pagination");

        const emptyRow = document.getElementById("emptyRow");
        const noResult = document.getElementById("noResult");

        const modal = document.getElementById("distributionModal");
        const form = document.getElementById("distributionForm");
        const openBtn = document.getElementById("newDistribution");
        const closeBtn = document.getElementById("closeModalDistribution");
        const cancelBtn = document.getElementById("cancelModalDistribution");
        const modalTitle = document.getElementById("modalTitle");

        const studentSearchInput = document.getElementById("studentSearchInput");
        const selectAllStudents = document.getElementById("selectAllStudents");
        const clearAllStudents = document.getElementById("clearAllStudents");

        const studentCheckboxes = [
            ...document.querySelectorAll(".student-checkbox")
        ];

        const studentItems = [
            ...document.querySelectorAll(".student-item")
        ];

        /* =======================================================
            SEARCH STUDENT
        ======================================================= */
        studentSearchInput.addEventListener("input", function () {

            const keyword = this.value.toLowerCase().trim();

            studentItems.forEach(item => {

                const studentName = item.dataset.studentName || "";

                item.style.display = studentName.includes(keyword)
                    ? "flex"
                    : "none";

            });

            const visibleCheckboxes = studentItems
                .filter(item => item.style.display !== "none")
                .map(item => item.querySelector(".student-checkbox"));

            const allChecked =
                visibleCheckboxes.length > 0 &&
                visibleCheckboxes.every(cb => cb.checked);

            selectAllStudents.checked = allChecked;

        });

        /* =======================================================
            SELECT ALL STUDENTS
        ======================================================= */
        selectAllStudents.addEventListener("change", function () {

            studentItems.forEach(item => {

                if (item.style.display !== "none") {

                    const checkbox =
                        item.querySelector(".student-checkbox");

                    if (checkbox && !checkbox.disabled) {
                        checkbox.checked = this.checked;
                    }

                }

            });

        });

        /* =======================================================
            CLEAR ALL STUDENTS
        ======================================================= */
        clearAllStudents.addEventListener("click", function () {

            studentCheckboxes.forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = false;
                }
            });

            selectAllStudents.checked = false;

        });

        /* =======================================================
            AUTO UPDATE SELECT ALL
        ======================================================= */
        studentCheckboxes.forEach(checkbox => {

            checkbox.addEventListener("change", function () {

                if (
                    form.dataset.mode === "edit" &&
                    this.checked
                ) {
                    studentCheckboxes.forEach(cb => {
                        if (cb !== this) cb.checked = false;
                    });
                }

                const visibleCheckboxes = studentItems
                    .filter(item => item.style.display !== "none")
                    .map(item =>
                        item.querySelector(".student-checkbox")
                    );

                const allChecked =
                    visibleCheckboxes.length > 0 &&
                    visibleCheckboxes.every(cb => cb.checked);

                updateSelectAllStudentAvailability();

            });

        });

        /* =======================================================
            INLINE VALIDATION
        ======================================================= */
        function clearInlineErrors() {

            form.querySelectorAll(".inline-error").forEach(el =>
                el.remove()
            );

            form.querySelectorAll(".border-red-500").forEach(el => {
                el.classList.remove("border-red-500");
            });

        }

        function setInlineError(input, message) {

            if (input.classList) {
                input.classList.add("border-red-500");
            }

            const error = document.createElement("p");

            error.className =
                "inline-error text-xs text-red-500 mt-2";

            error.textContent = message;

            input.parentElement.appendChild(error);

        }

        /* =======================================================
            RESTORE STATE
        ======================================================= */
        const savedSearch = shouldRestore
            ? sessionStorage.getItem(STORAGE_KEYS.search) || ""
            : "";

        const savedRows = shouldRestore
            ? sessionStorage.getItem(STORAGE_KEYS.rows) || "10"
            : "10";

        const savedPage = shouldRestore
            ? parseInt(
                sessionStorage.getItem(STORAGE_KEYS.page) || "1"
            )
            : 1;

        searchInput.value = savedSearch;
        rowsPerPage.value = savedRows;

        let currentPage = savedPage;

        let filteredRows = savedSearch
            ? rows.filter(row =>
                row.innerText
                    .toLowerCase()
                    .includes(savedSearch.toLowerCase())
            )
            : [...rows];

        let perPage = rowsPerPage.value === "all"
            ? Math.max(filteredRows.length, 1)
            : parseInt(rowsPerPage.value);

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

            rows.forEach(row => row.style.display = "none");

            if (rows.length === 0) {

                emptyRow.classList.remove("hidden");
                noResult.classList.add("hidden");

                pageInfo.textContent = "0 data";
                pagination.innerHTML = "";

                return;
            }

            if (filteredRows.length === 0) {

                emptyRow.classList.add("hidden");
                noResult.classList.remove("hidden");

                pageInfo.textContent = "0 data ditemukan";
                pagination.innerHTML = "";

                saveState();
                return;
            }

            emptyRow.classList.add("hidden");
            noResult.classList.add("hidden");

            const totalPages = rowsPerPage.value === "all"
                ? 1
                : Math.max(
                    Math.ceil(filteredRows.length / perPage),
                    1
                );

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            const start = (currentPage - 1) * perPage;

            const end = rowsPerPage.value === "all"
                ? filteredRows.length
                : start + perPage;

            filteredRows
                .slice(start, end)
                .forEach(row => {
                    row.style.display = "";
                });

            pageInfo.textContent =
                rowsPerPage.value === "all"
                    ? `Menampilkan ${filteredRows.length} data`
                    : `Menampilkan ${start + 1} - ${Math.min(
                        end,
                        filteredRows.length
                    )} dari ${filteredRows.length} data`;

            renderPagination(totalPages);
            saveState();

        }

        /* =======================================================
            PAGINATION
        ======================================================= */
        function renderPagination(totalPages) {

            pagination.innerHTML = "";

            if (
                rowsPerPage.value === "all" ||
                totalPages <= 1
            ) return;

            const createButton = (
                label,
                disabled,
                onClick,
                active = false
            ) => {

                const btn = document.createElement("button");

                btn.textContent = label;

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

                btn.disabled = disabled;

                if (!disabled) {
                    btn.onclick = onClick;
                }

                pagination.appendChild(btn);

            };

            createButton(
                "Sebelumnya",
                currentPage === 1,
                () => {
                    currentPage--;
                    renderTable();
                }
            );

            for (let i = 1; i <= totalPages; i++) {

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

            createButton(
                "Selanjutnya",
                currentPage === totalPages,
                () => {
                    currentPage++;
                    renderTable();
                }
            );

        }

        /* =======================================================
            TABLE SEARCH FILTER
        ======================================================= */
        searchInput.addEventListener("input", applyCombinedFilters);

        function applyCombinedFilters() {

    const keyword = searchInput.value.toLowerCase().trim();
    const selectedTeacher = teacherFilter
        ? teacherFilter.value
        : "0";

    filteredRows = rows.filter(row => {

        const matchesSearch =
            keyword === "" ||
            row.innerText.toLowerCase().includes(keyword);

        const teacherIds = (row.dataset.teacherIds || "")
            .split(",")
            .map(id => id.trim());

        const matchesTeacher =
            selectedTeacher === "0" ||
            teacherIds.includes(selectedTeacher);

        return matchesSearch && matchesTeacher;

    });

    currentPage = 1;
    renderTable();
}

        if (teacherFilter) {

            teacherFilter.addEventListener("change", applyCombinedFilters);

        }

        /* =======================================================
            TABLE LIMIT CONTROL
        ======================================================= */
        rowsPerPage.addEventListener("change", () => {

            perPage = rowsPerPage.value === "all"
                ? Math.max(filteredRows.length, 1)
                : parseInt(rowsPerPage.value);

            currentPage = 1;

            renderTable();

        });

        /* =======================================================
            MODAL CONTROL
        ======================================================= */
        function openDistributionModal() {

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            form.reset();
            clearInlineErrors();

            form.querySelector('[name="id"]').value = "";

            form.dataset.mode = "create";

            modalTitle.textContent = "Tambah Distribusi Kuis Siswa";

            document
                .getElementById("multiStudentControls")
                .classList.remove("hidden");

            studentSearchInput.value = "";

            studentItems.forEach(item => {
                item.style.display = "flex";
            });

            selectAllStudents.checked = false;

            studentCheckboxes.forEach(cb => {

                cb.checked = false;
                cb.disabled = false;

                cb.closest(".student-item").classList.remove(
                    "opacity-50",
                    "cursor-not-allowed",
                    "bg-gray-100"
                );

            });

            updateSelectAllStudentAvailability();

            /* =======================================================
                BLOCK USED STUDENTS
            ======================================================= */
            fetch(
                `../ajax/quiz_distribution/get_quiz_distribution.php?quiz_id=<?= $quizId ?>`
            )

            .then(res => res.json())

            .then(res => {

                if (res.status !== 1) return;

                const usedStudents = Array.isArray(res.used_students)
                    ? res.used_students
                    : [];

                studentCheckboxes.forEach(cb => {

                    const studentId = parseInt(cb.value);

                    if (usedStudents.includes(studentId)) {

                        cb.disabled = true;
                        cb.checked = false;

                        cb.closest(".student-item").classList.add(
                            "opacity-50",
                            "cursor-not-allowed",
                            "bg-gray-100"
                        );

                    }

                });

                updateSelectAllStudentAvailability();

            });

        }

        function closeDistributionModal() {

            modal.classList.add("hidden");
            modal.classList.remove("flex");

            clearInlineErrors();

            document
                .getElementById("multiStudentControls")
                .classList.remove("hidden");

            studentCheckboxes.forEach(cb => {

                cb.disabled = false;

                cb.closest(".student-item").classList.remove(
                    "opacity-50",
                    "cursor-not-allowed",
                    "bg-gray-100"
                );

            });

        }

        if (openBtn) {
            openBtn.addEventListener(
                "click",
                openDistributionModal
            );
        }

        if (closeBtn) {
            closeBtn.addEventListener(
                "click",
                closeDistributionModal
            );
        }

        if (cancelBtn) {
            cancelBtn.addEventListener(
                "click",
                closeDistributionModal
            );
        }

        modal.addEventListener("click", e => {

            if (e.target === modal) {
                closeDistributionModal();
            }

        });

        /* =======================================================
            ESC KEY MODAL CLOSE
        ======================================================= */
        document.addEventListener("keydown", e => {

            if (
                e.key === "Escape" &&
                !modal.classList.contains("hidden")
            ) {
                closeDistributionModal();
            }

        });

        /* =======================================================
            FORM SUBMIT
        ======================================================= */
        form.addEventListener("submit", e => {

            e.preventDefault();

            clearInlineErrors();

            const submitBtn = form.querySelector(
                'button[type="submit"]'
            );

            submitBtn.disabled = true;

            submitBtn.classList.add(
                "opacity-70",
                "cursor-not-allowed"
            );

            const studentFields =
                form.querySelectorAll('[name="student_ids[]"]');

            let hasError = false;

            const checkedStudents = [...studentFields].filter(
                cb => cb.checked
            );

            if (checkedStudents.length === 0) {

                setInlineError(
                    document.getElementById(
                        "studentCheckboxWrapper"
                    ),
                    "Minimal satu siswa wajib dipilih."
                );

                hasError = true;

            }

            if (hasError) {

                submitBtn.disabled = false;

                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                return;
            }

            showLoading("Menyimpan distribusi...");

            const formData = new FormData(form);

            fetch(
                "../ajax/quiz_distribution/save_quiz_distribution.php",
                {
                    method: "POST",
                    body: formData
                }
            )

            .then(res => res.json())

            .then(res => {

                Swal.close();

                submitBtn.disabled = false;

                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                /* =======================================================
                    SUCCESS
                ======================================================= */
                if (res.status == 1) {

                    closeDistributionModal();

                    showToast("success", res.msg);

                    setTimeout(() => {
                        location.reload();
                    }, 1200);

                    return;
                }

                /* =======================================================
                    INLINE VALIDATION
                ======================================================= */
                if (res.status == 2) {

                    setInlineError(
                        document.getElementById(
                            "studentCheckboxWrapper"
                        ),
                        res.msg
                    );

                    return;
                }

                /* =======================================================
                    SYSTEM ERROR
                ======================================================= */
                Swal.fire(
                    getResponsiveSwal(
                        "error",
                        "Gagal",
                        res.msg ||
                        "Gagal menyimpan distribusi."
                    )
                );

            })

            .catch(() => {

                Swal.close();

                submitBtn.disabled = false;

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

            });

        });

        applyCombinedFilters();

        /* =======================================================
            INITIAL TABLE RENDER
        ======================================================= */
        renderTable();

    });

    </script>

</body>
</html>