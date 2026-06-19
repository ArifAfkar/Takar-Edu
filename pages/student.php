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
    TEACHER CLASS ACCESS
======================================================= */
$teacherClassIds = [];

if ($isTeacher) {

    /* ---------- Assigned Class Retrieval ---------- */
    $teacherDataQuery = $conn->query("
        SELECT id
        FROM teachers
        WHERE user_id = {$loginId}
        LIMIT 1
    ");

    $teacherId = 0;

    if ($teacherDataQuery && $teacherDataQuery->num_rows > 0) {
        $teacherId = (int) $teacherDataQuery
            ->fetch_assoc()['id'];

        $classQuery = $conn->query("
            SELECT class_id
            FROM teacher_class_assignments
            WHERE teacher_id = {$teacherId}
            AND status = 1
        ");

        while ($classRow = $classQuery->fetch_assoc()) {
            $teacherClassIds[] = (int) $classRow['class_id'];
        }
    }

}

/* =======================================================
    STUDENT FILTER
======================================================= */
$studentWhereClause = "";

if ($isTeacher) {

    $classIdList = !empty($teacherClassIds)
        ? implode(',', $teacherClassIds)
        : "0";

    $studentWhereClause = "
        WHERE s.class_id IN ({$classIdList})
    ";

}

/* =======================================================
    CLASS OPTIONS
======================================================= */
if ($isAdmin) {

    /* ---------- All Classes ---------- */
    $classOptions = $conn->query("
        SELECT
            id,
            class_name
        FROM classes
        ORDER BY class_name ASC
    ");

} else {

    /* ---------- Teacher Assigned Classes ---------- */
    $classIdList = !empty($teacherClassIds)
        ? implode(',', $teacherClassIds)
        : "0";

    $classOptions = $conn->query("
        SELECT
            id,
            class_name
        FROM classes
        WHERE id IN ({$classIdList})
        ORDER BY class_name ASC
    ");

}

/* =======================================================
    STUDENT STATISTICS
======================================================= */

/* ---------- Total Students ---------- */
$totalStudents = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM students s
    {$studentWhereClause}
")->fetch_assoc()['total'];

/* ---------- Active Students ---------- */
$activeStudents = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM students s
    INNER JOIN users u
        ON s.user_id = u.id
    {$studentWhereClause}
    " . ($studentWhereClause ? " AND " : " WHERE ") . "
    u.status = 1
")->fetch_assoc()['total'];

/* ---------- Inactive Students ---------- */
$inactiveStudents = max(
    $totalStudents - $activeStudents,
    0
);

/* ---------- Gender Statistics ---------- */
$maleStudents = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM students s
    {$studentWhereClause}
    " . ($studentWhereClause ? " AND " : " WHERE ") . "
    s.gender = 'L'
")->fetch_assoc()['total'];

$femaleStudents = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM students s
    {$studentWhereClause}
    " . ($studentWhereClause ? " AND " : " WHERE ") . "
    s.gender = 'P'
")->fetch_assoc()['total'];


/* =======================================================
    MAIN STUDENT QUERY
======================================================= */
$studentQuery = $conn->query("
    SELECT
        s.id,
        s.user_id,
        s.class_id,
        s.gender,
        s.created_at,
        s.updated_at,

        c.class_name,

        u.name,
        u.username,
        u.status,
        u.profile_image

    FROM students s

    INNER JOIN users u
        ON s.user_id = u.id

    LEFT JOIN classes c
        ON s.class_id = c.id

    {$studentWhereClause}

    ORDER BY
        c.class_name ASC,
        u.name ASC
");

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Siswa | Takar-Edu";

/* =======================================================
    PAGE LAYOUT
======================================================= */
$studentColspan = $isAdmin ? 7 : 6;
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
                            Manajemen Siswa
                        </h1>

                        <p class="page-description">

                            <?php if ($isAdmin): ?>
                                Kelola seluruh akun siswa, kelas, gender, dan status peserta didik dalam sistem.

                            <?php else: ?>
                                Pantau data siswa berdasarkan kelas yang Anda ampu dalam sistem evaluasi.

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

                    <!-- Total students -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Siswa</p>
                            <h3 class="stat-value">
                                <?= $totalStudents ?>
                            </h3>
                            <p class="stat-label">Siswa terdaftar</p>
                        </div>
                    </div>

                    <!-- Active students -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Siswa Aktif</p>
                            <h3 class="stat-value">
                                <?= $activeStudents ?>
                            </h3>
                            <p class="stat-label">Akun aktif</p>
                        </div>
                    </div>

                    <!-- Inactive students -->
                    <div class="stat-card">
                        <div class="stat-icon bg-red-600">
                            <i data-lucide="user-x" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Siswa Nonaktif</p>
                            <h3 class="stat-value">
                                <?= $inactiveStudents ?>
                            </h3>
                            <p class="stat-label">Akun nonaktif</p>
                        </div>
                    </div>

                    <!-- Gender statistics -->
                    <div class="stat-card">
                        <div class="stat-icon bg-pink-600">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Distribusi Gender</p>
                            <h3 class="stat-value">
                                L: <?= $maleStudents ?> &nbsp;&nbsp;&nbsp; P: <?= $femaleStudents ?>
                            </h3>
                            <p class="stat-label">Laki-laki & Perempuan</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                STUDENT TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title text-xl sm:text-2xl">
                        Daftar Siswa
                    </h2>

                    <!-- Add student button -->
                    <?php if ($isAdmin): ?>

                        <button
                            id="newStudent"
                            type="button"
                            class="form-btn form-btn-primary"
                        >
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Tambah Siswa
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

                    <!-- Search input -->
                    <div class="search-wrapper">

                        <input
                            id="searchInput"
                            type="text"
                            placeholder="Cari siswa..."
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
                                <th class="table-th <?= $isAdmin ? 'w-[25%]' : 'w-[35%]' ?>">Nama Siswa</th>
                                <th class="table-th <?= $isAdmin ? 'w-[20%]' : 'w-[30%]' ?>">Username</th>
                                <th class="table-th w-[10%]">Kelas</th>
                                <th class="table-th w-[12%]">Gender</th>
                                <th class="table-th w-[8%]">Status</th>

                                <?php if ($isAdmin): ?>
                                    <th class="table-th w-[20%]">Aksi</th>
                                <?php endif; ?>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($studentQuery && $studentQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($student = $studentQuery->fetch_assoc()): ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Student name -->
                                        <td class="table-td">

                                            <div class="table-td-title">
                                                <?= htmlspecialchars($student['name']) ?>
                                            </div>

                                            <div class="table-subtext">
                                                <?= ((int)$student['status'] === 1) 
                                                ? 'Akun aktif' 
                                                : 'Akun nonaktif' ?>
                                            </div>

                                        </td>

                                        <!-- Username -->
                                        <td class="table-td text-center">
                                            <?= htmlspecialchars($student['username']) ?>
                                        </td>

                                        <!-- Class -->
                                        <td class="table-td text-center">
                                            <?= !empty($student['class_name'])
                                                ? htmlspecialchars($student['class_name'])
                                                : '-' ?>
                                        </td>

                                        <!-- Gender -->
                                        <td class="table-td text-center">
                                            <?= $student['gender'] === 'L' 
                                            ? 'Laki-laki' 
                                            : 'Perempuan' ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="table-td text-center">

                                        <?php if ((int)$student['status'] === 1): ?>

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
                                        <?php if ($isAdmin): ?>

                                            <td class="table-td text-center">

                                                <div class="action-group">

                                                    <!-- Edit button -->
                                                    <button
                                                        type="button"
                                                        onclick="editStudent(<?= $student['id'] ?>)"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="square-pen" class="w-4 h-4"></i>
                                                        <span class="action-label">Edit</span>
                                                    </button>

                                                    <!-- Status button -->
                                                    <button
                                                        type="button"
                                                        onclick="toggleStudentStatus(<?= $student['id'] ?>, <?= $student['status'] ?>)"
                                                        class="action-btn <?= $student['status'] == 1 ? 'action-danger' : 'action-success' ?>"
                                                    >
                                                        <i data-lucide="<?= $student['status'] == 1 ? 'user-x' : 'user-check' ?>" class="w-4 h-4"></i>
                                                        <span class="action-label"><?= $student['status'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?></span>
                                                    </button>

                                                    <!-- Delete button -->
                                                    <button
                                                        type="button"
                                                        onclick="deleteStudent(<?= $student['id'] ?>)"
                                                        class="action-btn action-danger"
                                                    >
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        <span class="action-label">Hapus</span>
                                                    </button>

                                                </div>

                                            </td>

                                        <?php endif; ?>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyRow"
                                class="<?= ($studentQuery && $studentQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $studentColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="graduation-cap" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada siswa tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $studentColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada siswa yang sesuai dengan pencarian
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
        STUDENT MODAL SECTION
    ======================================================= -->
    <div id="studentModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Siswa Baru
                </h3>

                <button
                    id="closeModalStudent"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Student Form ---------- -->
            <form id="studentForm" class="modal-form">

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id">
                    <input type="hidden" name="user_id">
                    <input type="hidden" name="user_type" value="3">

                    <!-- Student name -->
                    <div>

                        <label class="form-label">
                            Nama Siswa
                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="Masukkan nama siswa"
                            class="form-input"
                        >

                    </div>

                    <!-- Username -->
                    <div>

                        <label class="form-label">
                            Username
                        </label>

                        <input
                            type="text"
                            name="username"
                            placeholder="Masukkan username"
                            class="form-input"
                        >

                    </div>

                    <!-- Password add mode -->
                    <div id="passwordAddWrapper">

                        <label class="form-label">
                            Password
                        </label>

                        <div class="password-wrapper">

                            <input
                                type="password"
                                name="password"
                                id="studentPassword"
                                placeholder="Masukkan password"
                                class="form-input password-input"
                            >

                            <button
                                type="button"
                                id="toggleStudentPassword"
                                class="password-toggle"
                            >
                                <i data-lucide="eye" class="w-5 h-5"></i>
                            </button>

                        </div>

                    </div>

                    <!-- Password edit mode -->
                    <div id="passwordEditWrapper" class="hidden space-y-3">

                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">

                            <input
                                type="checkbox"
                                id="changePasswordToggle"
                                class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                            >

                            Ubah Password

                        </label>

                        <div id="editPasswordInputWrapper" class="hidden">

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="studentPasswordEdit"
                                    placeholder="Masukkan password baru"
                                    class="form-input password-input"
                                >

                                <button
                                    type="button"
                                    id="toggleStudentPasswordEdit"
                                    class="password-toggle"
                                >
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>

                            </div>

                        </div>

                    </div>

                    <!-- Class -->
                    <div>

                        <label class="form-label">
                            Kelas
                        </label>

                        <select
                            name="class_id"
                            class="form-select"
                        >

                            <option value="">
                                Pilih kelas
                            </option>

                            <?php if ($classOptions && $classOptions->num_rows > 0): ?>

                                <?php while ($class = $classOptions->fetch_assoc()): ?>

                                    <option value="<?= $class['id'] ?>">
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </select>

                    </div>

                    <!-- Gender -->
                    <div>

                        <label class="form-label">
                            Jenis Kelamin
                        </label>

                        <select name="gender" class="form-select">
                            <option value="">Pilih gender</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>

                    </div>

                </div>

                <!-- ---------- Form Actions ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        id="cancelModalStudent"
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
        RGLOBAL SWEETALERT HELPERS
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
        TOAST NOTIFICATION HELPER
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
        LOADING DIALOG HELPER
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
        DELETE STUDENT
    ======================================================= */
    function deleteStudent(id) {

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                "Hapus siswa permanen?",
                "Seluruh akun, jawaban, evaluasi, dan riwayat kuis siswa akan dihapus permanen.\nGunakan nonaktifkan akun jika hanya ingin menonaktifkan akses."
            ),
            showCancelButton: true,
            confirmButtonText: "Ya, hapus",
            cancelButtonText: "Batal",
            reverseButtons: false
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Menghapus data...");

            fetch(`../ajax/student/delete_student.php?id=${id}`)

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

    /* =======================================================
        TOGGLE STUDENT STATUS
    ======================================================= */
    function toggleStudentStatus(studentId, currentStatus) {

        const newStatus = currentStatus == 1 ? 0 : 1;

        const actionText = currentStatus == 1
            ? "menonaktifkan"
            : "mengaktifkan";

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                `Konfirmasi`,
                `Apakah Anda yakin ingin ${actionText} akun ini?`
            ),
            showCancelButton: true,
            confirmButtonText: "Ya",
            cancelButtonText: "Batal"
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Memproses status...");

            fetch("../ajax/student/toggle_student_status.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `id=${studentId}&status=${newStatus}`
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

    /* =======================================================
        EDIT STUDENT
    ======================================================= */
    function editStudent(id) {

        showLoading("Memuat data...");

        fetch(`../ajax/student/get_student.php?id=${id}`)

            .then(res => res.json())

            .then(res => {

                Swal.close();

                if (res.status !== 1) {

                    Swal.fire(
                        getResponsiveSwal(
                            "error",
                            "Gagal",
                            res.msg || "Data tidak ditemukan."
                        )
                    );

                    return;
                }

                const modal = document.getElementById("studentModal");
                const form = document.getElementById("studentForm");
                const modalTitle = document.getElementById("modalTitle");

                const data = res.data;

                modal.classList.add("flex");

                setTimeout(() => {
                    nameInput.focus();
                }, 100);

                modalTitle.textContent = "Edit Data Siswa";

                form.querySelector('[name="id"]').value = data.id;
                form.querySelector('[name="user_id"]').value = data.user_id;
                form.querySelector('[name="name"]').value = data.name;
                form.querySelector('[name="username"]').value = data.username;
                form.querySelector('[name="gender"]').value = data.gender;
                form.querySelector('[name="class_id"]').value = data.class_id;

                passwordAddWrapper.classList.add("hidden");
                passwordEditWrapper.classList.remove("hidden");

                studentPassword.value = "";
                studentPassword.type = "password";

                changePasswordToggle.checked = false;
                editPasswordInputWrapper.classList.add("hidden");

                studentPasswordEdit.value = "";
                studentPasswordEdit.type = "password";

                lucide.createIcons();

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

    }

    document.addEventListener("DOMContentLoaded", () => {

        /* ---------- Storage Configuration ---------- */
        const STORAGE_KEYS = {
            search: "student_search_keyword",
            rows: "student_rows_per_page",
            page: "student_current_page"
        };

        const navigationType =
            performance.getEntriesByType("navigation")[0]?.type || "navigate";

        const shouldRestore = navigationType === "reload";

        if (!shouldRestore) {
            sessionStorage.removeItem(STORAGE_KEYS.search);
            sessionStorage.removeItem(STORAGE_KEYS.rows);
            sessionStorage.removeItem(STORAGE_KEYS.page);
        }

        /* ---------- Icon Initialization ---------- */
        if (window.lucide) {
            lucide.createIcons();
        }

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

        const modal = document.getElementById("studentModal");
        const form = document.getElementById("studentForm");

        const openBtn = document.getElementById("newStudent");
        const closeBtn = document.getElementById("closeModalStudent");
        const cancelBtn = document.getElementById("cancelModalStudent");

        const modalTitle = document.getElementById("modalTitle");

        const passwordAddWrapper = document.getElementById("passwordAddWrapper");
        const passwordEditWrapper = document.getElementById("passwordEditWrapper");
        const editPasswordInputWrapper = document.getElementById("editPasswordInputWrapper");
        const changePasswordToggle = document.getElementById("changePasswordToggle");
        const studentPassword = document.getElementById("studentPassword");
        const studentPasswordEdit = document.getElementById("studentPasswordEdit");

        /* =======================================================
            PASSWORD TOGGLE
        ======================================================= */
        function setupPasswordToggle(buttonId, inputId) {

            /* ---------- Element References ---------- */
            const button = document.getElementById(buttonId);
            const input = document.getElementById(inputId);

            button?.addEventListener("click", function () {

                /* ---------- Visibility State ---------- */
                if (input.type === "password") {
                    input.type = "text";
                    this.innerHTML = `<i data-lucide="eye-off" class="w-5 h-5"></i>`;
                } else {
                    input.type = "password";
                    this.innerHTML = `<i data-lucide="eye" class="w-5 h-5"></i>`;
                }

                /* ---------- Icon Refresh ---------- */
                lucide.createIcons();

            });

        }

        setupPasswordToggle("toggleStudentPassword", "studentPassword");
        setupPasswordToggle("toggleStudentPasswordEdit", "studentPasswordEdit");

        /* =======================================================
            EDIT PASSWORD FIELD TOGGLE
        ======================================================= */
        changePasswordToggle?.addEventListener("change", function () {

            if (this.checked) {
                editPasswordInputWrapper.classList.remove("hidden");
            } else {
                editPasswordInputWrapper.classList.add("hidden");
                studentPasswordEdit.value = "";
            }

        });

        /* =======================================================
            INLINE VALIDATION
        ======================================================= */
        const nameInput = form.querySelector('[name="name"]');
        const usernameInput = form.querySelector('[name="username"]');
        const genderInput = form.querySelector('[name="gender"]');
        const classInput = form.querySelector('[name="class_id"]');

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

        function attachLiveValidation(input) {
            if (!input) return;

            input.addEventListener("input", () => clearFieldError(input));
            input.addEventListener("change", () => clearFieldError(input));
        }

        attachLiveValidation(nameInput);
        attachLiveValidation(usernameInput);
        attachLiveValidation(studentPassword);
        attachLiveValidation(studentPasswordEdit);
        attachLiveValidation(genderInput);
        attachLiveValidation(classInput);

        /* ---------- Restore Table State ---------- */
        const savedSearch = shouldRestore
            ? sessionStorage.getItem(STORAGE_KEYS.search) || ""
            : "";

        const savedRows = shouldRestore
            ? sessionStorage.getItem(STORAGE_KEYS.rows) || "10"
            : "10";

        const savedPage = shouldRestore
            ? parseInt(sessionStorage.getItem(STORAGE_KEYS.page) || "1")
            : 1;

        searchInput.value = savedSearch;
        rowsPerPage.value = savedRows;

        let currentPage = savedPage;

        let filteredRows = savedSearch
            ? rows.filter(row =>
                row.innerText.toLowerCase().includes(
                    savedSearch.toLowerCase()
                )
            )
            : [...rows];

        let perPage = rowsPerPage.value === "all"
            ? Math.max(filteredRows.length, 1)
            : parseInt(rowsPerPage.value);

        /* ---------- Save Table State ---------- */
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

            rows.forEach(row => {
                row.style.display = "none";
            });

            /* ---------- Empty DB ---------- */
            if (rows.length === 0) {

                emptyRow.classList.remove("hidden");
                noResult.classList.add("hidden");

                pageInfo.textContent = "0 data";
                pagination.innerHTML = "";

                return;
            }

            /* ---------- Empty Search ---------- */
            if (filteredRows.length === 0) {

                emptyRow.classList.add("hidden");
                noResult.classList.remove("hidden");

                pageInfo.textContent =
                    "0 data ditemukan";

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

            const start =
                (currentPage - 1) * perPage;

            const end = rowsPerPage.value === "all"
                ? filteredRows.length
                : start + perPage;

            filteredRows
                .slice(start, end)
                .forEach(row => {
                    row.style.display = "";
                });

            pageInfo.textContent = rowsPerPage.value === "all"
                ? `Menampilkan ${filteredRows.length} data`
                : `Menampilkan ${start + 1} - ${Math.min(end, filteredRows.length)} dari ${filteredRows.length} data`;

            renderPagination(totalPages);
            saveState();

        }

        /* =======================================================
            PAGINATION RENDERING
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

                const btn =
                    document.createElement("button");

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

            /* ---------- Previous Button ---------- */
            createButton(
                "Sebelumnya",
                currentPage === 1,
                () => {
                    currentPage--;
                    renderTable();
                }
            );

            /* ---------- Page Numbers ---------- */
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

        /* =======================================================
            SEARCH HANDLER
        ======================================================= */
        searchInput.addEventListener("input", () => {

            const keyword =
                searchInput.value.toLowerCase().trim();

            filteredRows = keyword === ""
                ? [...rows]
                : rows.filter(row =>
                    row.innerText.toLowerCase().includes(
                        keyword
                    )
                );

            currentPage = 1;

            renderTable();

        });

        /* =======================================================
            ROW LIMIT HANDLER
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
        function openStudentModal() {

            modal.classList.add("flex");

            setTimeout(() => {
                nameInput.focus();
            }, 100);

            form.reset();
            clearInlineErrors();

            form.querySelector('[name="id"]').value = "";
            form.querySelector('[name="user_id"]').value = "";

            modalTitle.textContent =
                "Tambah Siswa Baru";

            passwordAddWrapper.classList.remove("hidden");
            passwordEditWrapper.classList.add("hidden");

                studentPassword.value = "";
                studentPassword.type = "password";

                changePasswordToggle.checked = false;
                editPasswordInputWrapper.classList.add("hidden");

                studentPasswordEdit.value = "";
                studentPasswordEdit.type = "password";

                lucide.createIcons();

        }

        function closeStudentModal() {

            modal.classList.remove("flex");

            clearInlineErrors();

        }

        /* ---------- Open Modal ---------- */
        if (openBtn) {
            openBtn.addEventListener(
                "click",
                openStudentModal
            );
        }

        /* ---------- Close Modal ---------- */
        if (closeBtn) {
            closeBtn.addEventListener(
                "click",
                closeStudentModal
            );
        }

        if (cancelBtn) {
            cancelBtn.addEventListener(
                "click",
                closeStudentModal
            );
        }

        /* ---------- Outside Click Close ---------- */
        modal.addEventListener("click", e => {

            if (e.target === modal) {
                closeStudentModal();
            }

        });

        /* ---------- Escape Key Close ---------- */
        document.addEventListener("keydown", e => {

            if (
                e.key === "Escape" &&
                modal.classList.contains("flex")
            ) {
                closeStudentModal();
            }

        });

        /* =======================================================
            FORM SUBMISSION
        ======================================================= */
        form.addEventListener("submit", e => {

            e.preventDefault();

            clearInlineErrors();

            const submitBtn =
                form.querySelector(
                    'button[type="submit"]'
                );

            submitBtn.disabled = true;
            submitBtn.classList.add(
                "opacity-70",
                "cursor-not-allowed"
            );

            const studentId = form.querySelector('[name="id"]').value;

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

            if (!nameInput.value.trim()) {
                setInlineError(nameInput, "Nama siswa wajib diisi.");
                scrollToField(nameInput);
                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                return;
            }

            if (!usernameInput.value.trim()) {
                setInlineError(usernameInput, "Username wajib diisi.");
                scrollToField(usernameInput);
                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                return;
            }

            if (!studentId && !studentPassword.value.trim()) {
                setInlineError(studentPassword, "Password wajib diisi.");
                scrollToField(studentPassword);
                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                return;
            }

            if (studentId && changePasswordToggle.checked && !studentPasswordEdit.value.trim()) {
                setInlineError(studentPasswordEdit, "Password baru wajib diisi.");
                scrollToField(studentPasswordEdit);
                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                return;
            }

            if (!genderInput.value.trim()) {
                setInlineError(genderInput, "Jenis kelamin wajib dipilih.");
                scrollToField(genderInput);
                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                return;
            }

            if (!classInput.value.trim()) {
                setInlineError(classInput, "Kelas wajib dipilih.");
                scrollToField(classInput);
                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                return;
            }

            showLoading("Menyimpan data...");

            const formData = new FormData(form);

            fetch(
                "../ajax/student/save_student.php",
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

                /* ---------- Save Success ---------- */
                if (res.status == 1) {

                    closeStudentModal();

                    showToast(
                        "success",
                        res.msg
                    );

                    setTimeout(() => {
                        location.reload();
                    }, 1200);

                    return;
                }

                /* ---------- Server Validation ---------- */
                if (res.status == 2) {

                    const msg =
                        res.msg.toLowerCase();

                    if (msg.includes("nama")) {

                        setInlineError(
                            nameInput,
                            res.msg
                        );

                    } else if (
                        msg.includes("username")
                    ) {

                        setInlineError(
                            usernameInput,
                            res.msg
                        );

                    } else if (
                        msg.includes("gender")
                    ) {

                        setInlineError(
                            genderInput,
                            res.msg
                        );

                    } else if (
                        msg.includes("kelas")
                    ) {

                        setInlineError(
                            classInput,
                            res.msg
                        );

                    } else if (
                        msg.includes("password")
                    ) {

                        setInlineError(
                            studentId ? studentPasswordEdit : studentPassword,
                            res.msg
                        );

                    } else {

                        setInlineError(
                            nameInput,
                            res.msg
                        );

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

        /* =======================================================
            INITIAL TABLE RENDER
        ======================================================= */
        renderTable();

    });
    </script>

</body>
</html>