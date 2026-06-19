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

/* ---------- Admin Only ---------- */
if ($_SESSION['login_user_type'] != 1) {
    header("Location: home.php");
    exit;
}

/* =======================================================
    SESSION CONFIGURATION
======================================================= */

/* ---------- Logged User ---------- */
$userName = $_SESSION['login_name']
    ?? $_SESSION['login_user_name']
    ?? $_SESSION['name']
    ?? 'Administrator';

/* =======================================================
    SUBJECT OPTIONS
======================================================= */
$subjectOptions = $conn->query("
    SELECT
        id,
        subject_name
    FROM subjects
    ORDER BY subject_name ASC
");

/* =======================================================
    TEACHER STATISTICS
======================================================= */

/* ---------- Total Teachers ---------- */
$totalTeachers = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM teachers
")->fetch_assoc()['total'];

/* ---------- Active Teachers ---------- */
$activeTeachers = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM teachers t
    INNER JOIN users u
        ON t.user_id = u.id
    WHERE u.status = 1
")->fetch_assoc()['total'];

/* ---------- Inactive Teachers ---------- */
$inactiveTeachers = max(
    $totalTeachers - $activeTeachers,
    0
);

/* ---------- Teachers With Class Assignment ---------- */
$teachersWithClasses = (int) $conn->query("
    SELECT COUNT(DISTINCT tca.teacher_id) AS total
    FROM teacher_class_assignments tca
    INNER JOIN teachers t
        ON tca.teacher_id = t.id
    INNER JOIN users u
        ON t.user_id = u.id
    WHERE tca.status = 1
    AND u.status = 1
")->fetch_assoc()['total'];

/* =======================================================
    MAIN TEACHER QUERY
======================================================= */
$teacherQuery = $conn->query("
    SELECT
        t.id,
        t.user_id,
        t.subject_id,
        t.gender,
        t.created_at,

        u.name,
        u.username,
        u.status,

        s.subject_name,
        s.subject_code,

        COUNT(DISTINCT tca.class_id) AS total_classes

    FROM teachers t

    INNER JOIN users u
        ON t.user_id = u.id

    LEFT JOIN subjects s
        ON t.subject_id = s.id

    LEFT JOIN teacher_class_assignments tca
        ON tca.teacher_id = t.id
        AND tca.status = 1

    GROUP BY t.id

    ORDER BY u.name ASC
");

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Pengajar | Takar-Edu";

/* =======================================================
    PAGE LAYOUT
======================================================= */
$teacherColspan = 8;
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
                            Manajemen Pengajar
                        </h1>

                        <p class="page-description">
                            Kelola seluruh data pengajar, distribusi mata pelajaran, dan penugasan kelas dalam sistem.
                        </p>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">

                    <!-- Total teachers -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Pengajar</p>
                            <h3 class="stat-value">
                                <?= $totalTeachers ?>
                            </h3>
                            <p class="stat-label">Pengajar terdaftar</p>
                        </div>
                    </div>

                    <!-- Active teachers -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="user-check" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Pengajar Aktif</p>
                            <h3 class="stat-value">
                                <?= $activeTeachers ?>
                            </h3>
                            <p class="stat-label">Akun aktif</p>
                        </div>
                    </div>

                    <!-- Inactive teachers -->
                    <div class="stat-card">
                        <div class="stat-icon bg-yellow-600">
                            <i data-lucide="user-x" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Pengajar Nonaktif</p>
                            <h3 class="stat-value">
                                <?= $inactiveTeachers ?>
                            </h3>
                            <p class="stat-label">Akun nonaktif</p>
                        </div>
                    </div>

                    <!-- Teachers with class assignment -->
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-600">
                            <i data-lucide="school" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Pengajar Bertugas</p>
                            <h3 class="stat-value">
                                <?= $teachersWithClasses ?>
                            </h3>
                            <p class="stat-label">Memiliki distribusi kelas</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                TEACHER TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title text-xl sm:text-2xl">
                        Daftar Pengajar
                    </h2>

                    <!-- Add teacher button -->
                    <button
                        id="newTeacher"
                        type="button"
                        class="form-btn form-btn-primary"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah Pengajar
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
                            placeholder="Cari pengajar..."
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
                                <th class="table-th w-[21%]">Nama Pengajar</th>
                                <th class="table-th w-[18%]">Username</th>
                                <th class="table-th w-[10%]">Mapel</th>
                                <th class="table-th w-[12%]">Gender</th>
                                <th class="table-th w-[6%]">Kelas</th>
                                <th class="table-th w-[8%]">Status</th>
                                <th class="table-th w-[20%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($teacherQuery && $teacherQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($teacher = $teacherQuery->fetch_assoc()): ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Teacher name -->
                                        <td class="table-td">

                                            <div class="table-td-title">
                                                <?= htmlspecialchars($teacher['name']) ?>
                                            </div>

                                            <div class="table-subtext">
                                                <?= ((int)$teacher['status'] === 1)
                                                    ? 'Akun aktif'
                                                    : 'Akun nonaktif' ?>
                                            </div>

                                        </td>

                                        <!-- Username -->
                                        <td class="table-td">
                                            <?= htmlspecialchars($teacher['username']) ?>
                                        </td>

                                        <!-- Subject -->
                                        <td class="table-td text-center">
                                            <?= !empty($teacher['subject_name'])
                                                ? htmlspecialchars($teacher['subject_name'])
                                                : '-' ?>
                                        </td>

                                        <!-- Gender -->
                                        <td class="table-td text-center">
                                            <?= $teacher['gender'] === 'L' 
                                            ? 'Laki-laki' 
                                            : 'Perempuan' ?>
                                        </td>

                                        <!-- Class count -->
                                        <td class="table-td text-center">
                                            <?= (int) $teacher['total_classes'] ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="table-td text-center">

                                            <?php if ($teacher['status'] == 1): ?>

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

                                            <div class="action-group">

                                                <!-- Edit button -->
                                                <button
                                                    type="button"
                                                    onclick="editTeacher(<?= $teacher['id'] ?>)"
                                                    class="action-btn action-info"
                                                >
                                                    <i data-lucide="square-pen" class="w-4 h-4"></i>
                                                    <span class="action-label">Edit</span>
                                                </button>

                                                <!-- Status button -->
                                                <button
                                                    type="button"
                                                    onclick="toggleTeacherStatus(<?= $teacher['id'] ?>, <?= $teacher['status'] ?>)"
                                                    class="action-btn <?= $teacher['status'] == 1 ? 'action-danger' : 'action-success' ?>"
                                                >
                                                    <i data-lucide="<?= $teacher['status'] == 1 ? 'user-x' : 'user-check' ?>" class="w-4 h-4"></i>
                                                    <span class="action-label"><?= $teacher['status'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?></span>
                                                </button>

                                                <!-- Delete button -->
                                                <button
                                                    type="button"
                                                    onclick="deleteTeacher(<?= $teacher['id'] ?>)"
                                                    class="action-btn action-danger"
                                                >
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    <span class="action-label">Hapus</span>
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyRow" 
                                class="<?= ($teacherQuery && $teacherQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $teacherColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="users-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada pengajar tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $teacherColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada pengajar yang sesuai dengan pencarian
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
        TEACHER MODAL SECTION
    ======================================================= -->
    <div id="teacherModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Pengajar Baru
                </h3>

                <button
                    id="closeModalTeacher"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Teacher Form ---------- -->
            <form id="teacherForm" class="modal-form">

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id">

                    <!-- Teacher name -->
                    <div>

                        <label class="form-label">
                            Nama Pengajar
                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="Masukkan nama pengajar"
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
                                id="teacherPassword"
                                placeholder="Masukkan password"
                                class="form-input password-input"
                            >

                            <button
                                type="button"
                                id="toggleTeacherPassword"
                                class="password-toggle"
                            >
                                <i data-lucide="eye" class="w-5 h-5"></i>
                            </button>

                        </div>

                    </div>

                    <!-- Password edit mode -->
                    <div id="passwordEditWrapper" class="hidden space-y-3">

                        <label class="checkbox-inline">

                            <input
                                type="checkbox"
                                id="changePasswordToggle"
                                class="checkbox-inline-input"
                            >

                            <span class="checkbox-inline-text">
                                Ubah Password
                            </span>

                        </label>

                        <div id="editPasswordInputWrapper" class="hidden">

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="teacherPasswordEdit"
                                    placeholder="Masukkan password baru"
                                    class="form-input password-input"
                                >

                                <button
                                    type="button"
                                    id="toggleTeacherPasswordEdit"
                                    class="password-toggle"
                                >
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>

                            </div>

                        </div>

                    </div>

                    <!-- Subject -->
                    <div>

                        <label class="form-label">
                            Mata Pelajaran
                        </label>

                        <select
                            name="subject_id"
                            class="form-select"
                        >

                            <option value="">Pilih mata pelajaran</option>

                            <?php if ($subjectOptions && $subjectOptions->num_rows > 0): ?>

                                <?php while ($subject = $subjectOptions->fetch_assoc()): ?>

                                    <option value="<?= $subject['id'] ?>">
                                        <?= htmlspecialchars($subject['subject_name']) ?>
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

                <!-- ---------- Form Buttons ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        id="cancelModalTeacher"
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
        GLOBAL SWEETALERT HELPERS
    ======================================================= */
    function getResponsiveSwal(icon, title, text) {

        const isMobile = window.innerWidth < 640;

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

    function showLoading(title = "Memproses...") {
        Swal.fire({
            title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
            customClass: {
                popup: "rounded-2xl shadow-xl"
            }
        });
    }

    /* =======================================================
        DOM READY
    ======================================================= */
    document.addEventListener("DOMContentLoaded", () => {

        /* =======================================================
            STORAGE CONFIGURATION
        ======================================================= */
        const STORAGE_KEYS = {
            search: "teacher_search_keyword",
            rows: "teacher_rows_per_page",
            page: "teacher_current_page"
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
        const pageInfo = document.getElementById("pageInfo");
        const pagination = document.getElementById("pagination");

        const emptyRow = document.getElementById("emptyRow");
        const noResult = document.getElementById("noResult");

        const modal = document.getElementById("teacherModal");
        const form = document.getElementById("teacherForm");
        const openBtn = document.getElementById("newTeacher");
        const closeBtn = document.getElementById("closeModalTeacher");
        const cancelBtn = document.getElementById("cancelModalTeacher");
        const modalTitle = document.getElementById("modalTitle");

        /* =======================================================
            FORM INPUTS
        ======================================================= */
        const teacherIdInput = form.querySelector('[name="id"]');
        const nameInput = form.querySelector('[name="name"]');
        const usernameInput = form.querySelector('[name="username"]');
        const subjectInput = form.querySelector('[name="subject_id"]');
        const genderInput = form.querySelector('[name="gender"]');

        /* =======================================================
            PASSWORD SYSTEM
        ======================================================= */
        const passwordAddWrapper = document.getElementById("passwordAddWrapper");
        const passwordEditWrapper = document.getElementById("passwordEditWrapper");
        const editPasswordInputWrapper = document.getElementById("editPasswordInputWrapper");
        const changePasswordToggle = document.getElementById("changePasswordToggle");
        const teacherPassword = document.getElementById("teacherPassword");
        const teacherPasswordEdit = document.getElementById("teacherPasswordEdit");

        /* Password toggle helper */
        function setupPasswordToggle(buttonId, inputId) {

            const button = document.getElementById(buttonId);
            const input = document.getElementById(inputId);

            button?.addEventListener("click", function () {

                const iconContainer = this;

                if (input.type === "password") {
                    input.type = "text";

                    iconContainer.innerHTML = `
                        <i data-lucide="eye-off" class="w-5 h-5"></i>
                    `;
                } else {
                    input.type = "password";

                    iconContainer.innerHTML = `
                        <i data-lucide="eye" class="w-5 h-5"></i>
                    `;
                }

                lucide.createIcons();

            });

        }

        /* ---------- Password Toggle Initialization ---------- */
        setupPasswordToggle("toggleTeacherPassword", "teacherPassword");
        setupPasswordToggle("toggleTeacherPasswordEdit", "teacherPasswordEdit");

        changePasswordToggle?.addEventListener("change", function () {
            if (this.checked) {
                editPasswordInputWrapper.classList.remove("hidden");
            } else {
                editPasswordInputWrapper.classList.add("hidden");
                teacherPasswordEdit.value = "";
            }

        });

        /* =======================================================
            INLINE VALIDATION
        ======================================================= */
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
        attachLiveValidation(teacherPassword);
        attachLiveValidation(teacherPasswordEdit);
        attachLiveValidation(subjectInput);
        attachLiveValidation(genderInput);

        /* =======================================================
            TABLE STATE CONFIGURATION
        ======================================================= */
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
                row.innerText.toLowerCase().includes(savedSearch.toLowerCase())
            )
            : [...rows];

        let perPage = rowsPerPage.value === "all"
            ? Math.max(filteredRows.length, 1)
            : parseInt(rowsPerPage.value);

        /* ---------- State Persistence ---------- */
        function saveState() {
            sessionStorage.setItem(STORAGE_KEYS.search, searchInput.value);
            sessionStorage.setItem(STORAGE_KEYS.rows, rowsPerPage.value);
            sessionStorage.setItem(STORAGE_KEYS.page, currentPage);
        }

        /* =======================================================
            PAGINATION
        ======================================================= */
        function renderPagination(totalPages) {
            pagination.innerHTML = "";

            if (rowsPerPage.value === "all" || totalPages <= 1) return;

            const createButton = (label, disabled, onClick, active = false) => {
                const btn = document.createElement("button");

                btn.textContent = label;
                btn.className = `
                    inline-flex items-center justify-center
                    min-w-8 h-8 px-2 rounded-md text-sm font-medium
                    border transition-all duration-200
                    ${
                        active
                            ? "bg-blue-600 text-white border-blue-600 shadow-sm"
                            : disabled
                                ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                                : "bg-white text-gray-700 border-gray-300 hover:bg-blue-100 hover:text-blue-700 hover:border-blue-300"
                    }
                `;

                btn.disabled = disabled;

                if (!disabled) {
                    btn.onclick = onClick;
                }

                pagination.appendChild(btn);
            };

            createButton("Sebelumnya", currentPage === 1, () => {
                currentPage--;
                renderTable();
            });

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

            createButton("Selanjutnya", currentPage === totalPages, () => {
                currentPage++;
                renderTable();
            });
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

            pageInfo.textContent = rowsPerPage.value === "all"
                ? `Menampilkan ${filteredRows.length} data`
                : `Menampilkan ${start + 1} - ${Math.min(end, filteredRows.length)} dari ${filteredRows.length} data`;

            renderPagination(totalPages);
            saveState();
        }

        /* =======================================================
            SEARCH HANDLER
        ======================================================= */
        searchInput.addEventListener("input", () => {
            const keyword = searchInput.value.toLowerCase().trim();

            filteredRows = keyword === ""
                ? [...rows]
                : rows.filter(row =>
                    row.innerText.toLowerCase().includes(keyword)
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
        /* ---------- Open Modal ---------- */
        function openTeacherModal() {
            modal.classList.add("flex");
            form.reset();
            clearInlineErrors();

            teacherIdInput.value = "";
            modalTitle.textContent = "Tambah Pengajar Baru";

            passwordAddWrapper.classList.remove("hidden");
            passwordEditWrapper.classList.add("hidden");

            teacherPassword.value = "";

            changePasswordToggle.checked = false;
            editPasswordInputWrapper.classList.add("hidden");

            teacherPasswordEdit.value = "";

            setTimeout(() => {
                nameInput.focus();
            }, 100);
        }

        /* ---------- Close Modal ---------- */
        function closeTeacherModal() {
            modal.classList.remove("flex");
            clearInlineErrors();
        }

        openBtn.addEventListener("click", openTeacherModal);
        closeBtn.addEventListener("click", closeTeacherModal);
        cancelBtn.addEventListener("click", closeTeacherModal);

        modal.addEventListener("click", e => {
            if (e.target === modal) {
                closeTeacherModal();
            }
        });

        document.addEventListener("keydown", e => {
            if (e.key === "Escape" && modal.classList.contains("flex")) {
                closeTeacherModal();
            }
        });

        /* =======================================================
            EDIT TEACHER
        ======================================================= */
        function editTeacher(id) {
            showLoading("Memuat data...");

            fetch(`../ajax/teacher/get_teacher.php?id=${id}`)
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

                    const data = res.data;

                    modal.classList.add("flex");
                    modalTitle.textContent = "Edit Data Pengajar";

                    form.reset();
                    clearInlineErrors();

                    teacherIdInput.value = data.id;
                    nameInput.value = data.name;
                    usernameInput.value = data.username;
                    subjectInput.value = data.subject_id || "";
                    genderInput.value = data.gender;

                    passwordAddWrapper.classList.add("hidden");
                    passwordEditWrapper.classList.remove("hidden");

                    teacherPassword.value = "";

                    changePasswordToggle.checked = false;
                    editPasswordInputWrapper.classList.add("hidden");

                    teacherPasswordEdit.value = "";
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

        /* =======================================================
            DELETE TEACHER
        ======================================================= */
        function deleteTeacher(id) {
            Swal.fire({
                ...getResponsiveSwal(
                    "warning",
                    "Hapus pengajar permanen?",
                    "Akun pengajar dan seluruh distribusi aksesnya akan dihapus permanen.\nGunakan nonaktifkan akun jika hanya ingin menonaktifkan akses."
                ),
                showCancelButton: true,
                confirmButtonText: "Ya, hapus",
                cancelButtonText: "Batal",
                reverseButtons: false
            }).then(result => {
                if (!result.isConfirmed) return;

                showLoading("Menghapus data...");

                fetch(`../ajax/teacher/delete_teacher.php?id=${id}`)
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
            TEACHER STATUS TOGGLE
        ======================================================= */
        function toggleTeacherStatus(id, currentStatus) {
            const actionText = currentStatus == 1
                ? "menonaktifkan"
                : "mengaktifkan";

            Swal.fire({
                ...getResponsiveSwal(
                    "warning",
                    `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} akun?`,
                    `Anda yakin ingin ${actionText} akun pengajar ini?`
                ),
                showCancelButton: true,
                confirmButtonText: "Ya",
                cancelButtonText: "Batal"
            }).then(result => {
                if (!result.isConfirmed) return;

                showLoading("Memproses...");

                const formData = new FormData();
                formData.append("id", id);

                fetch("../ajax/teacher/toggle_teacher_status.php", {
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
                            }, 1000);
                        } else {
                            Swal.fire(
                                getResponsiveSwal(
                                    "error",
                                    "Gagal",
                                    res.msg
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
            GLOBAL BUTTON ACCESS
        ======================================================= */
        window.editTeacher = editTeacher;
        window.deleteTeacher = deleteTeacher;
        window.toggleTeacherStatus = toggleTeacherStatus;

        /* =======================================================
            FORM SUBMISSION
        ======================================================= */
        form.addEventListener("submit", e => {
            e.preventDefault();

            clearInlineErrors();

            const submitBtn = form.querySelector('button[type="submit"]');
            const teacherId = teacherIdInput.value;

            submitBtn.disabled = true;
            submitBtn.classList.add("opacity-70", "cursor-not-allowed");

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

            /* ---------- Name Validation ---------- */
            if (!nameInput.value.trim()) {

                setInlineError(
                    nameInput,
                    "Nama pengajar wajib diisi."
                );

                scrollToField(nameInput);

                submitBtn.disabled = false;
                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                return;
            }

            /* ---------- Username Validation ---------- */
            if (!usernameInput.value.trim()) {

                setInlineError(
                    usernameInput,
                    "Username wajib diisi."
                );

                scrollToField(usernameInput);

                submitBtn.disabled = false;
                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                return;
            }

            /* ---------- Add Password Validation ---------- */
            if (
                !teacherId &&
                !teacherPassword.value.trim()
            ) {

                setInlineError(
                    teacherPassword,
                    "Password wajib diisi."
                );

                scrollToField(teacherPassword);

                submitBtn.disabled = false;
                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                return;
            }

            /* ---------- Edit Password Validation ---------- */
            if (
                teacherId &&
                changePasswordToggle.checked &&
                !teacherPasswordEdit.value.trim()
            ) {

                setInlineError(
                    teacherPasswordEdit,
                    "Password baru wajib diisi."
                );

                scrollToField(teacherPasswordEdit);

                submitBtn.disabled = false;
                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                return;
            }

            /* ---------- Subject Validation ---------- */
            if (!subjectInput.value.trim()) {

                setInlineError(
                    subjectInput,
                    "Mata pelajaran wajib dipilih."
                );

                scrollToField(subjectInput);

                submitBtn.disabled = false;
                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                return;
            }

            /* ---------- Gender Validation ---------- */
            if (!genderInput.value.trim()) {

                setInlineError(
                    genderInput,
                    "Jenis kelamin wajib dipilih."
                );

                scrollToField(genderInput);

                submitBtn.disabled = false;
                submitBtn.classList.remove(
                    "opacity-70",
                    "cursor-not-allowed"
                );

                return;
            }

            showLoading("Menyimpan data...");

            const formData = new FormData(form);

            if (teacherId && !changePasswordToggle.checked) {
                formData.delete("password");
            }

            if (teacherId && changePasswordToggle.checked) {
                formData.set("password", teacherPasswordEdit.value);
            }

            fetch("../ajax/teacher/save_teacher.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(res => {
                    Swal.close();

                    submitBtn.disabled = false;
                    submitBtn.classList.remove(
                        "opacity-70",
                        "cursor-not-allowed"
                    );

                    if (res.status == 1) {
                        closeTeacherModal();

                        showToast("success", res.msg);

                        setTimeout(() => {
                            location.reload();
                        }, 1200);

                        return;
                    }

                    if (res.status == 2) {

                        const msg = res.msg.toLowerCase();

                        const focusServerError = input => {

                            if (!input) return;

                            input.scrollIntoView({
                                behavior: "smooth",
                                block: "center"
                            });

                            setTimeout(() => {
                                input.focus();
                            }, 250);
                        };

                        if (msg.includes("nama")) {

                            setInlineError(nameInput, res.msg);
                            focusServerError(nameInput);

                        } else if (msg.includes("username")) {

                            setInlineError(usernameInput, res.msg);
                            focusServerError(usernameInput);

                        } else if (
                            msg.includes("mata pelajaran") ||
                            msg.includes("mapel")
                        ) {

                            setInlineError(subjectInput, res.msg);
                            focusServerError(subjectInput);

                        } else if (
                            msg.includes("jenis kelamin") ||
                            msg.includes("gender")
                        ) {

                            setInlineError(genderInput, res.msg);
                            focusServerError(genderInput);

                        } else if (msg.includes("password")) {

                            if (!teacherId) {

                                setInlineError(teacherPassword, res.msg);
                                focusServerError(teacherPassword);

                            } else if (changePasswordToggle.checked) {

                                setInlineError(teacherPasswordEdit, res.msg);
                                focusServerError(teacherPasswordEdit);

                            }

                        } else {

                            Swal.fire(
                                getResponsiveSwal(
                                    "warning",
                                    "Validasi Gagal",
                                    res.msg
                                )
                            );
                        }

                        return;
                    }

                    Swal.fire(
                        getResponsiveSwal(
                            "error",
                            "Gagal",
                            res.msg || "Gagal menyimpan data."
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