<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */

/* ---------- Required Files ---------- */
require_once '../config/auth.php';
require_once '../config/db_connect.php';

/* =======================================================
    SESSION CONFIGURATION
======================================================= */
$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

/* ---------- Logged User ---------- */
$userName = $_SESSION['login_name']
    ?? $_SESSION['login_user_name']
    ?? $_SESSION['name']
    ?? 'Pengguna';

/* =======================================================
    ROLE FLAGS
======================================================= */
$isAdmin   = $userType === 1;
$isTeacher = $userType === 2;
$isStudent = $userType === 3;

/* =======================================================
    USER ROLE ENTITY MAPPING
======================================================= */

/* ---------- Default Entity IDs ---------- */
$teacherId = null;
$studentId = null;

/* ---------- Resolve Teacher ID ---------- */
if ($isTeacher) {

    $teacherQuery = $conn->query("
        SELECT id
        FROM teachers
        WHERE user_id = {$userId}
        LIMIT 1
    ");

    if ($teacherQuery && $teacherQuery->num_rows > 0) {
        $teacherId = (int) $teacherQuery->fetch_assoc()['id'];
    }

}

/* ---------- Resolve Student ID ---------- */
if ($isStudent) {

    $studentQuery = $conn->query("
        SELECT id
        FROM students
        WHERE user_id = {$userId}
        LIMIT 1
    ");

    if ($studentQuery && $studentQuery->num_rows > 0) {
        $studentId = (int) $studentQuery->fetch_assoc()['id'];
    }

}

/* =======================================================
    QUIZ FILTER
======================================================= */
$quizFilter = '';

/* ---------- Teacher Quiz Filter ---------- */
if ($isTeacher && $teacherId) {

    $quizFilter = "
        WHERE (
            q.created_by = {$userId}

            OR q.id IN (
                SELECT qtl.quiz_id
                FROM quiz_teacher_list qtl
                WHERE qtl.teacher_id = {$teacherId}
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

/* ---------- Student Quiz Filter ---------- */
elseif ($isStudent && $studentId) {

    $quizFilter = "
        WHERE q.id IN (
            SELECT qsl.quiz_id
            FROM quiz_student_list qsl
            WHERE qsl.student_id = {$studentId}
            AND qsl.status IN (1,2,3)
        )
        AND q.status = 1
        AND (
            q.open_at IS NULL
            OR q.open_at <= NOW()
        )
        AND (
            q.due_date IS NULL
            OR q.due_date >= NOW()
        )
    ";

}

/* =======================================================
    HISTORY FILTER
======================================================= */

$historyFilter = '';

if ($isStudent && $studentId) {

    $historyFilter = "
        AND quizStudent.student_id = {$studentId}
    ";

}

/* =======================================================
    ADMIN STATISTICS
======================================================= */

if ($isAdmin) {

    /* ---------- Total Quiz ---------- */
    $totalQuiz = (int) $conn->query("
        SELECT COUNT(*) AS total
        FROM quiz_list
    ")->fetch_assoc()['total'];

    /* ---------- Total Teachers ---------- */
    $totalTeachers = (int) $conn->query("
        SELECT COUNT(*) AS total
        FROM teachers
    ")->fetch_assoc()['total'];

    /* ---------- Total Students ---------- */
    $totalStudents = (int) $conn->query("
        SELECT COUNT(*) AS total
        FROM students
    ")->fetch_assoc()['total'];

    /* ---------- Active Quiz ---------- */
    $activeQuiz = (int) $conn->query("
        SELECT COUNT(*) AS total
        FROM quiz_list
        WHERE status = 1
    ")->fetch_assoc()['total'];

}

/* =======================================================
    TEACHER STATISTICS
======================================================= */

if ($isTeacher && $teacherId) {

    /* ---------- My Quiz ---------- */
    $myQuiz = (int) $conn->query("
        SELECT COUNT(DISTINCT q.id) AS total
        FROM quiz_list q
        WHERE (
            q.created_by = {$userId}
            OR q.id IN (
                SELECT qtl.quiz_id
                FROM quiz_teacher_list qtl
                WHERE qtl.teacher_id = {$teacherId}
                AND EXISTS (
                    SELECT 1
                    FROM teacher_class_assignments tca
                    WHERE tca.teacher_id = qtl.teacher_id
                    AND tca.status = 1
                )
            )
        )
    ")->fetch_assoc()['total'];

    /* ---------- Active Quiz ---------- */
    $activeQuiz = (int) $conn->query("
        SELECT COUNT(DISTINCT q.id) AS total
        FROM quiz_list q
        WHERE (
            q.created_by = {$userId}
            OR q.id IN (
                SELECT qtl.quiz_id
                FROM quiz_teacher_list qtl
                WHERE qtl.teacher_id = {$teacherId}
                AND EXISTS (
                    SELECT 1
                    FROM teacher_class_assignments tca
                    WHERE tca.teacher_id = qtl.teacher_id
                    AND tca.status = 1
                )
            )
        )
        AND q.status = 1
    ")->fetch_assoc()['total'];

    /* ---------- My Classes ---------- */
    $myClasses = (int) $conn->query("
        SELECT COUNT(DISTINCT tca.class_id) AS total
        FROM teacher_class_assignments tca
        WHERE tca.teacher_id = {$teacherId}
        AND tca.status = 1
    ")->fetch_assoc()['total'];

    /* ---------- My Students ---------- */
    $myStudents = (int) $conn->query("
        SELECT COUNT(DISTINCT s.id) AS total
        FROM students s
        INNER JOIN teacher_class_assignments tca
            ON s.class_id = tca.class_id
        WHERE tca.teacher_id = {$teacherId}
        AND tca.status = 1
    ")->fetch_assoc()['total'];

}

/* =======================================================
    STUDENT STATISTICS
======================================================= */

if ($isStudent && $studentId) {

    /* ---------- Available Quiz ---------- */
    $availableQuiz = (int) $conn->query("
        SELECT COUNT(DISTINCT q.id) AS total
        FROM quiz_list q
        INNER JOIN quiz_student_list qsl
            ON q.id = qsl.quiz_id
        WHERE qsl.student_id = {$studentId}
        AND q.status = 1
        AND qsl.status IN (1,2)
        AND (
            q.open_at IS NULL
            OR q.open_at <= NOW()
        )
        AND (
            q.due_date IS NULL
            OR q.due_date >= NOW()
        )
    ")->fetch_assoc()['total'];

    /* ---------- Completed Quiz ---------- */
    $completedQuiz = (int) $conn->query("
        SELECT COUNT(DISTINCT qsl.quiz_id) AS total
        FROM quiz_student_list qsl
        WHERE qsl.student_id = {$studentId}
        AND qsl.status = 3
    ")->fetch_assoc()['total'];

    /* ---------- Remaining Quiz ---------- */
    $remainingQuiz = max(
        $availableQuiz - $completedQuiz,
        0
    );

}

/* =======================================================
    MAIN QUIZ QUERY
======================================================= */
$quizQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_title,
        q.created_by,
        q.status,
        q.open_at,
        q.due_date,

        creator.name AS creator_name,

        (
            SELECT COUNT(DISTINCT teacherSubject.subject_id)
            FROM quiz_teacher_list quizTeacher
            INNER JOIN teachers teacherSubject
                ON quizTeacher.teacher_id = teacherSubject.id
            WHERE quizTeacher.quiz_id = q.id
        ) AS total_subjects,

        (
            SELECT GROUP_CONCAT(
                DISTINCT subject.subject_name
                ORDER BY subject.subject_name ASC
                SEPARATOR '||'
            )
            FROM quiz_teacher_list quizTeacher
            INNER JOIN teachers teacherSubject
                ON quizTeacher.teacher_id = teacherSubject.id
            INNER JOIN subjects subject
                ON teacherSubject.subject_id = subject.id
            WHERE quizTeacher.quiz_id = q.id
        ) AS subject_list,

        GROUP_CONCAT(
            DISTINCT teacherUser.name
            SEPARATOR ', '
        ) AS teachers,

        COUNT(DISTINCT question.id) AS total_questions,
        COUNT(DISTINCT history.id) AS total_completed

    FROM quiz_list q

    LEFT JOIN users creator
        ON q.created_by = creator.id

    LEFT JOIN quiz_teacher_list quizTeacherList
        ON q.id = quizTeacherList.quiz_id

    LEFT JOIN teachers teacher
        ON quizTeacherList.teacher_id = teacher.id

    LEFT JOIN users teacherUser
        ON teacher.user_id = teacherUser.id

    LEFT JOIN questions question
        ON question.quiz_id = q.id

    LEFT JOIN quiz_student_list quizStudent
        ON q.id = quizStudent.quiz_id

    LEFT JOIN history history
        ON history.quiz_student_id = quizStudent.id
        {$historyFilter}

    {$quizFilter}

    GROUP BY q.id

    ORDER BY q.quiz_title ASC
");

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Beranda | Takar-Edu";

/* =======================================================
    PAGE LAYOUT
======================================================= */
$homeColspan = $isAdmin ? 6 : ($isStudent ? 4 : 5);
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

                            <?php if ($isAdmin): ?>
                                Dashboard Administrator
                            <?php elseif ($isTeacher): ?>
                                Dashboard Pengajar
                            <?php else: ?>
                                Dashboard Siswa
                            <?php endif; ?>

                        </h1>

                        <p class="page-description">

                            <?php if ($isAdmin): ?>
                                Selamat datang kembali, <span class="font-semibold"><?= htmlspecialchars($userName) ?></span>. 
                                Pusat administrasi untuk mengelola sistem kuis, pengguna, dan aktivitas evaluasi.

                            <?php elseif ($isTeacher): ?>
                                Selamat datang kembali, <span class="font-semibold"><?= htmlspecialchars($userName) ?></span>. 
                                Kelola kuis Anda dan pantau hasil pengerjaan siswa.

                            <?php else: ?>
                                Selamat datang kembali, <span class="font-semibold"><?= htmlspecialchars($userName) ?></span>. 
                                Kerjakan kuis yang tersedia dan pantau riwayat hasil evaluasi Anda.

                            <?php endif; ?>

                        </p>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 <?= $isStudent ? 'xl:grid-cols-3' : 'xl:grid-cols-4' ?> gap-3">

                    <!-- ---------- Admin Dashboard Cards ---------- -->
                    <?php if ($isAdmin): ?>

                        <!-- Total quiz -->
                        <div class="stat-card">
                            <div class="stat-icon bg-blue-600">
                                <i data-lucide="file-text" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Total Kuis</p>
                                <h3 class="stat-value"><?= $totalQuiz ?></h3>
                                <p class="stat-label">Kuis tersedia</p>
                            </div>
                        </div>

                        <!-- Total teachers -->
                        <div class="stat-card">
                            <div class="stat-icon bg-green-600">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Total Pengajar</p>
                                <h3 class="stat-value"><?= $totalTeachers ?></h3>
                                <p class="stat-label">Pengajar terdaftar</p>
                            </div>
                        </div>

                        <!-- Total students -->
                        <div class="stat-card">
                            <div class="stat-icon bg-purple-600">
                                <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Total Siswa</p>
                                <h3 class="stat-value"><?= $totalStudents ?></h3>
                                <p class="stat-label">Siswa terdaftar</p>
                            </div>
                        </div>

                        <!-- Active quiz -->
                        <div class="stat-card">
                            <div class="stat-icon bg-yellow-600">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Kuis Aktif</p>
                                <h3 class="stat-value"><?= $activeQuiz ?></h3>
                                <p class="stat-label">Kuis tersedia</p>
                            </div>
                        </div>

                        <!-- ---------- Teacher Dashboard Cards ---------- -->
                        <?php elseif ($isTeacher): ?>

                            <!-- Total quiz -->
                            <div class="stat-card">
                                <div class="stat-icon bg-blue-600">
                                    <i data-lucide="book-copy" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="stat-label">Total Kuis</p>
                                    <h3 class="stat-value"><?= $myQuiz ?></h3>
                                    <p class="stat-label">Kuis dikelola</p>
                                </div>
                            </div>

                            <!-- Active quiz -->
                            <div class="stat-card">
                                <div class="stat-icon bg-green-600">
                                    <i data-lucide="play-circle" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="stat-label">Kuis Aktif</p>
                                    <h3 class="stat-value"><?= $activeQuiz ?></h3>
                                    <p class="stat-label">Kuis tersedia</p>
                                </div>
                            </div>

                            <!-- Active classes -->
                            <div class="stat-card">
                                <div class="stat-icon bg-purple-600">
                                    <i data-lucide="school" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="stat-label">Total Kelas</p>
                                    <h3 class="stat-value"><?= $myClasses ?></h3>
                                    <p class="stat-label">Kelas aktif Anda</p>
                                </div>
                            </div>

                            <!-- Total students -->
                            <div class="stat-card">
                                <div class="stat-icon bg-yellow-600">
                                    <i data-lucide="users" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="stat-label">Total Siswa</p>
                                    <h3 class="stat-value"><?= $myStudents ?></h3>
                                    <p class="stat-label">Siswa di kelas Anda</p>
                                </div>
                            </div>

                    <!-- ---------- Student Dashboard Cards ---------- -->
                    <?php elseif ($isStudent): ?>

                        <!-- Available quiz -->
                        <div class="stat-card">
                            <div class="stat-icon bg-blue-600">
                                <i data-lucide="book-open-check" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Kuis Tersedia</p>
                                <h3 class="stat-value"><?= $availableQuiz ?></h3>
                                <p class="stat-label">Siap dikerjakan</p>
                            </div>
                        </div>

                        <!-- Completed quiz -->
                        <div class="stat-card">
                            <div class="stat-icon bg-green-600">
                                <i data-lucide="badge-check" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Kuis Selesai</p>
                                <h3 class="stat-value"><?= $completedQuiz ?></h3>
                                <p class="stat-label">Sudah dikerjakan</p>
                            </div>
                        </div>

                        <!-- Remaining quiz -->
                        <div class="stat-card">
                            <div class="stat-icon bg-yellow-600">
                                <i data-lucide="clock-3" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="stat-label">Sisa Kuis</p>
                                <h3 class="stat-value"><?= $remainingQuiz ?></h3>
                                <p class="stat-label">Belum dikerjakan</p>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <!-- =======================================================
                QUIZ TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->

                    <?php if (!$isStudent): ?>
                        <h2 class="section-title text-xl sm:text-2xl">
                            Daftar Kuis
                        </h2>
                    <?php else: ?>
                        <h2 class="section-title text-xl sm:text-2xl">
                            Daftar Kuis Tersedia
                        </h2>
                    <?php endif; ?>

                    <!-- Quiz management button -->
                    <?php if (!$isStudent): ?>
                        <a
                            href="quiz.php"
                            class="form-btn form-btn-primary"
                        >
                            Kelola Kuis
                        </a>
                    <?php endif; ?>

                </div>

                <!-- ---------- Table Controls ---------- -->
                <div class="table-toolbar">

                    <!-- Rows per page -->
                    <div class="table-length-control">

                        <label for="rowsPerPage" class="table-control-label">Tampilkan</label>

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
                                <th class="table-th <?= $isAdmin ? 'w-[30%]' : ($isTeacher ? 'w-[55%]' : 'w-[45%]') ?>">Judul</th>
                                <th class="table-th w-[15%]">Mapel</th>

                                <?php if (!$isTeacher): ?>
                                    <th class="table-th w-[25%]">
                                        <span class="hidden md:inline">Penanggung Jawab</span>
                                        <span class="md:hidden">PJ</span>
                                    </th>
                                <?php endif; ?>

                                <?php if (!$isStudent): ?>
                                    <th class="table-th w-[15%]">Status</th>
                                <?php endif; ?>

                                <th class="table-th w-[10%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($quizQuery && $quizQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($row = $quizQuery->fetch_assoc()): ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Quiz title -->
                                        <td class="table-td">
                                            <?= htmlspecialchars($row['quiz_title']) ?>
                                        </td>

                                        <!-- Subject -->
                                        <td class="table-td text-center relative overflow-visible">

                                            <?php
                                                $subjectList = !empty($row['subject_list'])
                                                    ? array_filter(array_map('trim', explode('||', $row['subject_list'])))
                                                    : [];

                                                $totalSubjects = count($subjectList);
                                            ?>

                                            <!-- Single subject -->
                                            <?php if ($totalSubjects <= 1): ?>

                                                <div class="flex justify-center">

                                                    <span class="badge status-info badge-pill-info">
                                                        <?= htmlspecialchars($subjectList[0] ?? '-') ?>
                                                    </span>

                                                </div>

                                            <!-- Multiple subjects -->
                                            <?php else: ?>

                                                <div class="relative inline-block text-left">

                                                    <!-- Subject dropdown -->
                                                    <button
                                                        type="button"
                                                        class="subject-dropdown-btn table-dropdown-btn"
                                                        onclick="toggleSubjectDropdown(event, 'subject-dropdown-<?= (int)$row['id'] ?>')"
                                                    >
                                                        <i data-lucide="layers-3" class="w-4 h-4"></i>
                                                        Umum
                                                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                                    </button>

                                                    <!-- Subject list -->
                                                    <div
                                                        id="subject-dropdown-<?= (int)$row['id'] ?>"
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

                                        <!-- Teachers -->
                                        <?php if (!$isTeacher): ?>

                                            <td class="table-td text-center relative overflow-visible">

                                                <?php
                                                    $teacherList = !empty($row['teachers'])
                                                        ? array_filter(array_map('trim', explode(',', $row['teachers'])))
                                                        : [];
                                                ?>

                                                <!-- Single teacher -->
                                                <?php if (count($teacherList) === 1): ?>

                                                    <div class="text-sm text-gray-700 font-medium">
                                                        <?= htmlspecialchars($teacherList[0]) ?>
                                                    </div>

                                                <!-- Multiple teachers -->
                                                <?php else: ?>

                                                    <div class="relative inline-block text-left">

                                                        <!-- Teacher dropdown -->
                                                        <button
                                                            type="button"
                                                            class="teacher-dropdown-btn table-dropdown-btn"
                                                            onclick="toggleTeacherDropdown(event, 'teacher-dropdown-<?= (int)$row['id'] ?>')"
                                                        >
                                                            <i data-lucide="users" class="w-4 h-4"></i>

                                                            <?= count($teacherList) ?> Pengajar

                                                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                                        </button>

                                                        <!-- Teacher list -->
                                                        <div
                                                            id="teacher-dropdown-<?= (int)$row['id'] ?>"
                                                            class="hidden table-floating-dropdown"
                                                        >

                                                            <div class="table-dropdown-header">
                                                                <p class="table-dropdown-title">
                                                                    Daftar Pengajar:
                                                                </p>
                                                            </div>

                                                            <?php foreach ($teacherList as $teacher): ?>

                                                                <div class="table-dropdown-item">
                                                                    <?= htmlspecialchars($teacher) ?>
                                                                </div>

                                                            <?php endforeach; ?>

                                                        </div>

                                                    </div>

                                                <?php endif; ?>

                                            </td>

                                        <?php endif; ?>

                                        <!-- Status -->
                                        <?php if (!$isStudent): ?>

                                            <td class="table-td text-center">

                                                <?php if ((int) $row['status'] === 1): ?>

                                                    <span class="status-badge status-success">
                                                        Aktif
                                                    </span>

                                                <?php else: ?>

                                                    <span class="status-badge status-secondary">
                                                        Arsip
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        <?php endif; ?>

                                        <!-- Action buttons -->
                                        <td class="table-td text-center">

                                            <!-- Student actions -->
                                            <?php if ($isStudent): ?>

                                                <!-- Completed quiz -->
                                                <?php if ((int) $row['total_completed'] > 0): ?>

                                                    <a
                                                        href="view_answer.php?id=<?= (int)$row['id'] ?>"
                                                        class="action-btn action-primary"
                                                    >
                                                        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                                                        <span class="action-label">Hasil</span>
                                                    </a>

                                                <!-- Available quiz -->
                                                <?php else: ?>

                                                    <a
                                                        href="confirm_quiz.php?id=<?= (int)$row['id'] ?>"
                                                        class="action-btn action-success"
                                                    >
                                                        <i data-lucide="play" class="w-4 h-4"></i>
                                                        <span class="action-label">Mulai</span>
                                                    </a>

                                                <?php endif; ?>

                                            <!-- Admin / teacher actions -->
                                            <?php else: ?>

                                                <!-- Assigned quiz -->
                                                <?php if ($isTeacher && (int)$row['created_by'] !== $userId): ?>

                                                    <a
                                                        href="quiz_distribution.php?quiz_id=<?= $row['id'] ?>"
                                                        class="action-btn action-secondary"
                                                        title="Lihat"
                                                    >
                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                        <span class="action-label">Lihat</span>
                                                    </a>

                                                <!-- Owned quiz -->
                                                <?php else: ?>

                                                    <a
                                                        href="quiz_view.php?id=<?= (int)$row['id'] ?>"
                                                        class="action-btn action-info"
                                                        title="Kelola"
                                                    >
                                                        <i data-lucide="settings" class="w-4 h-4"></i>
                                                        <span class="action-label">Kelola</span>
                                                    </a>

                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr id="emptyRow" class="<?= ($quizQuery && $quizQuery->num_rows > 0) ? 'hidden' : '' ?>">
                                <td colspan="<?= $homeColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="file-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada kuis tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $homeColspan ?>" class="empty-state-cell">
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
        TEACHER DROPDOWN SYSTEM
    ======================================================= */
    function closeAllTeacherDropdowns() {
        document.querySelectorAll('[id^="teacher-dropdown-"]').forEach(dropdown => {
            dropdown.classList.add("hidden");
        });
    }

    function toggleTeacherDropdown(event, dropdownId) {

        event.stopPropagation();

        closeAllSubjectDropdowns();

        const target = document.getElementById(dropdownId);
        if (!target) return;

        const isOpen = !target.classList.contains("hidden");

        closeAllTeacherDropdowns();

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

    document.addEventListener("DOMContentLoaded", () => {

        /* ---------- Storage Configuration ---------- */
        const STORAGE_KEYS = {
            search: "home_search_keyword",
            rows: "home_rows_per_page",
            page: "home_current_page"
        };

        /* ---------- Icon Initialization ---------- */
        initLucideIcons();

        /* ---------- Page Refresh Detection ---------- */
        const pageMarkerKey = "home_page_visited";

        const shouldRestore = sessionStorage.getItem(pageMarkerKey) === "true";

        if (!shouldRestore) {
            sessionStorage.removeItem(STORAGE_KEYS.search);
            sessionStorage.removeItem(STORAGE_KEYS.rows);
            sessionStorage.removeItem(STORAGE_KEYS.page);
        }

        sessionStorage.setItem(pageMarkerKey, "true");

        /* ---------- Navigation State Reset ---------- */
        document.querySelectorAll('#sidebar a').forEach(link => {
            link.addEventListener('click', function () {

                const href = this.getAttribute("href");

                if (
                    href &&
                    !href.startsWith("#") &&
                    !href.startsWith("javascript:") &&
                    !this.hasAttribute("target")
                ) {
                    sessionStorage.removeItem("home_page_visited");
                }

            });
        });

        /* ---------- Element References ---------- */
        const table = document.getElementById("dataTable");
        if (!table) return;

        const rows = [...table.querySelectorAll("tbody tr")].filter(
            row => row.id !== "emptyRow" && row.id !== "noResult"
        );

        const searchInput = document.getElementById("searchInput");
        const rowsPerPage = document.getElementById("rowsPerPage");
        const pageInfo = document.getElementById("pageInfo");
        const pagination = document.getElementById("pagination");

        const emptyRow = document.getElementById("emptyRow");
        const noResult = document.getElementById("noResult");

        /* ---------- Restore Saved State ---------- */
        const savedSearch = shouldRestore
            ? sessionStorage.getItem(STORAGE_KEYS.search) || ""
            : "";

        const savedRows = shouldRestore
            ? sessionStorage.getItem(STORAGE_KEYS.rows) || "10"
            : "10";

        const savedPage = shouldRestore
            ? parseInt(sessionStorage.getItem(STORAGE_KEYS.page) || "1")
            : 1;

        if (searchInput) searchInput.value = savedSearch;
        if (rowsPerPage) rowsPerPage.value = savedRows;

        let currentPage = savedPage;

        let filteredRows = savedSearch
            ? rows.filter(row =>
                row.innerText.toLowerCase().includes(savedSearch.toLowerCase())
            )
            : [...rows];

        let perPage = rowsPerPage.value === "all"
            ? Math.max(filteredRows.length, 1)
            : parseInt(rowsPerPage.value);

        /* ---------- State Persistence ---------- */
        function saveState() {
            sessionStorage.setItem(STORAGE_KEYS.search, searchInput?.value || "");
            sessionStorage.setItem(STORAGE_KEYS.rows, rowsPerPage?.value || "10");
            sessionStorage.setItem(STORAGE_KEYS.page, currentPage);
        }

        /* =======================================================
            TABLE RENDERING
        ======================================================= */
        function renderTable() {

            closeAllSubjectDropdowns();
            closeAllTeacherDropdowns();

            rows.forEach(row => {
                row.style.display = "none";
            });

            /* ---------- Empty Database State ---------- */
            if (rows.length === 0) {

                if (emptyRow) {
                    emptyRow.classList.remove("hidden");
                    emptyRow.style.display = "";
                }

                if (noResult) noResult.classList.add("hidden");
                if (pageInfo) pageInfo.textContent = "0 data";
                if (pagination) pagination.innerHTML = "";

                return;
            }

            /* ---------- Empty Search Result ---------- */
            if (filteredRows.length === 0) {

                if (emptyRow) emptyRow.classList.add("hidden");

                if (noResult) {
                    noResult.classList.remove("hidden");
                    noResult.style.display = "";
                }

                if (pageInfo) pageInfo.textContent = "0 data ditemukan";
                if (pagination) pagination.innerHTML = "";

                saveState();
                return;
            }

            if (emptyRow) emptyRow.classList.add("hidden");
            if (noResult) noResult.classList.add("hidden");

            const totalPages = rowsPerPage.value === "all"
                ? 1
                : Math.max(Math.ceil(filteredRows.length / perPage), 1);

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            const start = (currentPage - 1) * perPage;

            const end = rowsPerPage.value === "all"
                ? filteredRows.length
                : start + perPage;

            filteredRows.slice(start, end).forEach(row => {
                row.style.display = "";
            });

            /* ---------- Page Info ---------- */
            if (pageInfo) {

                if (rowsPerPage.value === "all") {
                    pageInfo.textContent = `Menampilkan ${filteredRows.length} data`;
                } else {
                    pageInfo.textContent =
                        `Menampilkan ${start + 1} - ${Math.min(end, filteredRows.length)} dari ${filteredRows.length} data`;
                }

            }

            renderPagination(totalPages);
            saveState();
        }

        /* =======================================================
            PAGINATION RENDERING
        ======================================================= */
        function renderPagination(totalPages) {

            if (!pagination) return;

            pagination.innerHTML = "";

            if (!rowsPerPage || rowsPerPage.value === "all") return;
            if (filteredRows.length <= perPage) return;
            if (totalPages <= 1) return;

            /* ---------- Previous ---------- */
            const prevBtn = document.createElement("button");

            prevBtn.innerHTML = window.innerWidth < 768
                ? '<i data-lucide="chevron-left" class="w-4 h-4"></i>'
                : 'Sebelumnya';

            prevBtn.className = `
                inline-flex items-center justify-center
                min-w-8 h-8 px-2
                rounded-md text-sm font-medium
                border transition-all duration-200
                ${currentPage === 1
                    ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                    : "bg-white text-gray-700 border-gray-300 hover:bg-blue-100 hover:text-blue-700 hover:border-blue-300"}
            `;

            prevBtn.disabled = currentPage === 1;

            prevBtn.onclick = () => {
                closeAllSubjectDropdowns();
                closeAllTeacherDropdowns();
                if (currentPage > 1) {
                    currentPage--;
                    renderTable();
                }
            };

            pagination.appendChild(prevBtn);

            /* ---------- Page Numbers ---------- */
            for (let i = 1; i <= totalPages; i++) {

                const pageBtn = document.createElement("button");
                pageBtn.textContent = i;

                pageBtn.className = `
                    inline-flex items-center justify-center
                    min-w-8 h-8
                    rounded-md text-sm font-medium
                    border transition-all duration-200
                    ${currentPage === i
                        ? "bg-blue-600 text-white border-blue-600 shadow-sm"
                        : "bg-white text-gray-700 border-gray-300 hover:bg-blue-100 hover:text-blue-700 hover:border-blue-300"}
                `;

                pageBtn.onclick = () => {
                    closeAllSubjectDropdowns();
                    closeAllTeacherDropdowns();
                    currentPage = i;
                    renderTable();
                };

                pagination.appendChild(pageBtn);
            }

            /* ---------- Next ---------- */
            const nextBtn = document.createElement("button");

            nextBtn.innerHTML = window.innerWidth < 768
                ? '<i data-lucide="chevron-right" class="w-4 h-4"></i>'
                : 'Selanjutnya';

            nextBtn.className = `
                inline-flex items-center justify-center
                min-w-8 h-8 px-2
                rounded-md text-sm font-medium
                border transition-all duration-200
                ${currentPage === totalPages
                    ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                    : "bg-white text-gray-700 border-gray-300 hover:bg-blue-100 hover:text-blue-700 hover:border-blue-300"}
            `;

            nextBtn.disabled = currentPage === totalPages;

            nextBtn.onclick = () => {
                closeAllSubjectDropdowns();
                closeAllTeacherDropdowns();
                if (currentPage < totalPages) {
                    currentPage++;
                    renderTable();
                }
            };

            pagination.appendChild(nextBtn);

            if (window.lucide) {
                lucide.createIcons();
            }
        }

        /* =======================================================
            SEARCH HANDLER
        ======================================================= */
        if (searchInput) {
            searchInput.addEventListener("input", () => {

                closeAllSubjectDropdowns();
                closeAllTeacherDropdowns();

                const keyword = searchInput.value.toLowerCase().trim();

                filteredRows = keyword === ""
                    ? [...rows]
                    : rows.filter(row =>
                        row.innerText.toLowerCase().includes(keyword)
                    );

                currentPage = 1;
                renderTable();
            });
        }

        /* =======================================================
            ROW LIMIT HANDLER
        ======================================================= */
        if (rowsPerPage) {
            rowsPerPage.addEventListener("change", () => {

                closeAllSubjectDropdowns();
                closeAllTeacherDropdowns();

                perPage = rowsPerPage.value === "all"
                    ? Math.max(filteredRows.length, 1)
                    : parseInt(rowsPerPage.value);

                currentPage = 1;
                renderTable();
            });
        }

        /* =======================================================
            INITIAL RENDER
        ======================================================= */
        renderTable();

    });
    </script>

</body>
</html>