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
    TEACHER OPTIONS
======================================================= */
$teacherOptions = $conn->query("
    SELECT
        t.id,
        u.name,
        t.subject_id,
        s.subject_name
    FROM teachers t
    INNER JOIN users u
        ON t.user_id = u.id
    LEFT JOIN subjects s
        ON t.subject_id = s.id
    WHERE u.status = 1
    ORDER BY u.name ASC
");

/* =======================================================
    CLASS OPTIONS
======================================================= */
$classOptions = $conn->query("
    SELECT
        id,
        class_name
    FROM classes
    ORDER BY class_name ASC
");

/* =======================================================
    TEACHER DISTRIBUTION STATISTICS
======================================================= */

/* ---------- Total Distribution Records ---------- */
$totalAssignments = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM teacher_class_assignments
    WHERE status = 1
")->fetch_assoc()['total'];

/* ---------- Active Distributed Teachers ---------- */
$totalTeachers = (int) $conn->query("
    SELECT COUNT(DISTINCT teacher_id) AS total
    FROM teacher_class_assignments
    WHERE status = 1
")->fetch_assoc()['total'];

/* ---------- Active Distributed Classes ---------- */
$totalClasses = (int) $conn->query("
    SELECT COUNT(DISTINCT class_id) AS total
    FROM teacher_class_assignments
    WHERE status = 1
")->fetch_assoc()['total'];

/* =======================================================
    MAIN TEACHER DISTRIBUTION QUERY
======================================================= */
$teacherDistributionQuery = $conn->query("
    SELECT
        a.id,
        a.teacher_id,
        a.class_id,
        a.subject_id,
        a.status,

        u.name AS teacher_name,
        s.subject_name,
        c.class_name

    FROM teacher_class_assignments a

    INNER JOIN teachers t
        ON a.teacher_id = t.id

    INNER JOIN users u
        ON t.user_id = u.id

    INNER JOIN subjects s
        ON a.subject_id = s.id

    INNER JOIN classes c
        ON a.class_id = c.id

    ORDER BY
        u.name ASC,
        c.class_name ASC
");

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Distribusi Pengajar | Takar-Edu";

/* =======================================================
    TABLE CONFIGURATION
======================================================= */
$teacherDistributionColspan = 6;
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
                            Distribusi Pengajar
                        </h1>

                        <p class="page-description">
                            Kelola penempatan pengajar ke kelas berdasarkan mata pelajaran yang diampu.
                        </p>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">

                    <!-- Total teacher distribution -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="network" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Distribusi</p>
                            <h3 class="stat-value">
                                <?= $totalAssignments ?>
                            </h3>
                            <p class="stat-label">Penempatan aktif</p>
                        </div>
                    </div>

                    <!-- Active teachers -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Pengajar Aktif</p>
                            <h3 class="stat-value">
                                <?= $totalTeachers ?>
                            </h3>
                            <p class="stat-label">Sudah terdistribusi</p>
                        </div>
                    </div>

                    <!-- Active classes -->
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-600">
                            <i data-lucide="school" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Kelas Aktif</p>
                            <h3 class="stat-value">
                                <?= $totalClasses ?>
                            </h3>
                            <p class="stat-label">Kelas terhubung</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                TEACHER DISTRIBUTION TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Manajemen Distribusi Pengajar
                    </h2>

                    <!-- Add teacher distribution button -->
                    <button
                        id="newTeacherDistribution"
                        type="button"
                        class="form-btn form-btn-primary"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah Distribusi
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
                            placeholder="Cari distribusi..."
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
                                <th class="table-th w-[30%]">Pengajar</th>
                                <th class="table-th w-[20%]">Mata Pelajaran</th>
                                <th class="table-th w-[15%]">Kelas</th>
                                <th class="table-th w-[10%]">Status</th>
                                <th class="table-th w-[20%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($teacherDistributionQuery && $teacherDistributionQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($row = $teacherDistributionQuery->fetch_assoc()): ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Teacher -->
                                        <td class="table-td">
                                            <?= htmlspecialchars($row['teacher_name']) ?>
                                        </td>

                                        <!-- Subject -->
                                        <td class="table-td text-center">
                                            <?= htmlspecialchars($row['subject_name']) ?>
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

                                            <div class="action-group">

                                                <!-- Status button -->
                                                <?php $isActive = (int)$row['status'] === 1; ?>

                                                <button
                                                    type="button"
                                                    onclick="toggleTeacherDistributionStatus(<?= (int)$row['id'] ?>, <?= $isActive ? 0 : 1 ?>)"
                                                    class="action-btn <?= $isActive ? 'action-secondary' : 'action-success' ?>"
                                                >
                                                    <i data-lucide="<?= $isActive ? 'archive' : 'check-circle' ?>" class="w-4 h-4"></i>
                                                    <span class="action-label"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></span>
                                                </button>

                                                <!-- Delete button -->
                                                <button
                                                    type="button"
                                                    onclick="deleteTeacherDistribution(<?= (int)$row['id'] ?>)"
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
                                class="<?= ($teacherDistributionQuery && $teacherDistributionQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $teacherDistributionColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="network" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada distribusi pengajar tersedia
                                        </span>

                                    </div>

                                </td>

                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $teacherDistributionColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada distribusi pengajar yang sesuai dengan pencarian
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
        TEACHER DISTRIBUTION MODAL SECTION
    ======================================================= -->
    <div id="teacherDistributionModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Distribusi Pengajar
                </h3>

                <button
                    id="closeModalTeacherDistribution"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Teacher Distribution Form ---------- -->
            <form id="teacherDistributionForm" class="modal-form">

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id">
                    <input type="hidden" name="subject_id" id="subject_id">

                    <!-- Teacher -->
                    <div>

                        <label class="form-label">
                            Pengajar
                        </label>

                        <select
                            name="teacher_id"
                            id="teacherSelect"
                            class="form-select"
                        >
                            <option value="">Pilih pengajar</option>

                            <?php if ($teacherOptions && $teacherOptions->num_rows > 0): ?>

                                <?php while ($teacher = $teacherOptions->fetch_assoc()): ?>

                                    <option
                                        value="<?= $teacher['id'] ?>"
                                        data-subject-id="<?= $teacher['subject_id'] ?>"
                                        data-subject-name="<?= htmlspecialchars($teacher['subject_name']) ?>"
                                    >
                                        <?= htmlspecialchars($teacher['name']) ?>
                                    </option>

                                <?php endwhile; ?>

                            <?php endif;?>

                        </select>

                    </div>

                    <!-- Subject Display -->
                    <div>

                        <label class="form-label">
                            Mata Pelajaran
                        </label>

                        <input
                            type="text"
                            id="subjectDisplay"
                            readonly
                            placeholder="Otomatis dari pengajar"
                            class="form-input form-control-readonly"
                        >

                    </div>

                    <!-- Class -->
                    <div>

                        <label class="form-label">
                            Kelas
                        </label>

                        <!-- Class multi select -->
                        <div>

                            <!-- Search input -->
                            <div class="mb-3">
                                <input
                                    type="text"
                                    id="classSearchInput"
                                    placeholder="Cari kelas..."
                                    class="form-input"
                                >
                            </div>

                            <!-- Select all classes -->
                            <div
                                id="multiClassControls"
                                class="flex items-center justify-between mb-3"
                            >

                                <label class="checkbox-inline">
                                        
                                    <input
                                        type="checkbox"
                                        id="selectAllClasses"
                                        class="checkbox-inline-input"
                                    >

                                    <span class="checkbox-inline-text">
                                        Pilih Semua
                                    </span>

                                </label>

                                <!-- Reset -->
                                <button
                                    type="button"
                                    id="clearAllClasses"
                                    class="link-danger-sm"
                                >
                                    Reset
                                </button>

                            </div>

                            <!-- Class select -->
                            <div
                                id="classCheckboxWrapper"
                                class="checkbox-grid grid-cols-2 sm:grid-cols-3"
                            >

                                <?php if ($classOptions && $classOptions->num_rows > 0): ?>

                                    <?php while ($class = $classOptions->fetch_assoc()): ?>

                                        <label
                                            class="class-item checkbox-card"
                                            data-class-name="<?= strtolower(htmlspecialchars($class['class_name'])) ?>"
                                        >

                                            <input
                                                type="checkbox"
                                                name="class_ids[]"
                                                value="<?= $class['id'] ?>"
                                                class="class-checkbox checkbox-inline-input"
                                            >

                                            <span class="checkbox-card-text">
                                                <?= htmlspecialchars($class['class_name']) ?>
                                            </span>

                                        </label>

                                    <?php endwhile; ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- ---------- Form Buttons ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        id="cancelModalTeacherDistribution"
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
        RESPONSIVE SWEETALERT CONFIG
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
        SELECT ALL AVAILABILITY
    ======================================================= */
    function updateSelectAllAvailability() {

        const classCheckboxes =
            [...document.querySelectorAll(".class-checkbox")];

        const selectAllClasses =
            document.getElementById("selectAllClasses");

        const availableCheckboxes =
            classCheckboxes.filter(cb => !cb.disabled);

        /* ---------- No Available Classes ---------- */
        if (availableCheckboxes.length === 0) {

            selectAllClasses.checked = false;
            selectAllClasses.disabled = true;

            selectAllClasses.closest("label").classList.add(
                "opacity-50",
                "cursor-not-allowed"
            );

            return;
        }

        /* ---------- Available Classes ---------- */
        selectAllClasses.disabled = false;

        selectAllClasses.closest("label").classList.remove(
            "opacity-50",
            "cursor-not-allowed"
        );

        /* ---------- Checked State ---------- */
        const allChecked =
            availableCheckboxes.length > 0 &&
            availableCheckboxes.every(cb => cb.checked);

        selectAllClasses.checked = allChecked;

    }

    function toggleTeacherDistributionStatus(id, status) {

        const title = status === 1
            ? "Aktifkan distribusi?"
            : "Nonaktifkan distribusi?";

        const text = status === 1
            ? "Distribusi pengajar akan aktif kembali."
            : "Distribusi pengajar akan dinonaktifkan sementara.";

        Swal.fire({
            ...getResponsiveSwal("warning", title, text),
            showCancelButton: true,
            confirmButtonText: status === 1 ? "Ya, aktifkan" : "Ya, nonaktifkan",
            cancelButtonText: "Batal"
        }).then(result => {

            if (!result.isConfirmed) return;

            showLoading("Memperbarui status...");

            const formData = new FormData();
            formData.append("id", id);
            formData.append("status", status);

            fetch("../ajax/teacher_distribution/toggle_teacher_distribution.php", {
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

                Swal.fire(getResponsiveSwal("error", "Gagal", res.msg));
            })
            .catch(() => {
                Swal.close();
                Swal.fire(getResponsiveSwal("error", "Kesalahan Sistem", "Terjadi kesalahan sistem."));
            });

        });
    }

    /* =======================================================
        DELETE TEACHER DISTRIBUTION
    ======================================================= */
    function deleteTeacherDistribution(id) {

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                "Hapus distribusi?",
                "Data yang dihapus tidak dapat dikembalikan."
            ),
            showCancelButton: true,
            confirmButtonText: "Ya, hapus",
            cancelButtonText: "Batal",
            reverseButtons: false
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Menghapus data...");

            fetch(`../ajax/teacher_distribution/delete_teacher_distribution.php?id=${id}`)

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

    document.addEventListener("DOMContentLoaded", () => {

        /* =======================================================
            STORAGE CONFIG
        ======================================================= */
        const STORAGE_KEYS = {
            search: "teacher_distribution_search_keyword",
            rows: "teacher_distribution_rows_per_page",
            page: "teacher_distribution_current_page"
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
            INITIALIZE LUCIDE
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

        const modal = document.getElementById("teacherDistributionModal");
        const form = document.getElementById("teacherDistributionForm");
        const openBtn = document.getElementById("newTeacherDistribution");
        const closeBtn = document.getElementById("closeModalTeacherDistribution");
        const cancelBtn = document.getElementById("cancelModalTeacherDistribution");
        const modalTitle = document.getElementById("modalTitle");

        const teacherSelect = document.getElementById("teacherSelect");
        const subjectDisplay = document.getElementById("subjectDisplay");
        const subjectInput = document.getElementById("subject_id");

        const classSearchInput = document.getElementById("classSearchInput");
        const selectAllClasses = document.getElementById("selectAllClasses");
        const clearAllClasses = document.getElementById("clearAllClasses");
        const classCheckboxes = [...document.querySelectorAll(".class-checkbox")];
        const classItems = [...document.querySelectorAll(".class-item")];

        /* =======================================================
            SEARCH CLASS
        ======================================================= */
        classSearchInput.addEventListener("input", function () {

            const keyword = this.value.toLowerCase().trim();

            classItems.forEach(item => {

                const className = item.dataset.className || "";

                item.style.display = className.includes(keyword)
                    ? "flex"
                    : "none";

            });

                /* ---------- Select All State ---------- */
            const visibleCheckboxes = classItems
                .filter(item => item.style.display !== "none")
                .map(item => item.querySelector(".class-checkbox"));

            const allChecked = visibleCheckboxes.length > 0 &&
                visibleCheckboxes.every(cb => cb.checked);

            selectAllClasses.checked = allChecked;

        });

        /* =======================================================
            SELECT ALL CLASSES
        ======================================================= */
        selectAllClasses.addEventListener("change", function () {

            classItems.forEach(item => {

                if (item.style.display !== "none") {

                    const checkbox = item.querySelector(".class-checkbox");

                    if (checkbox) {
                        checkbox.checked = this.checked;
                    }

                }

            });

        });

        /* =======================================================
            CLEAR ALL CLASSES
        ======================================================= */
        clearAllClasses.addEventListener("click", function () {

            classCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });

            selectAllClasses.checked = false;

        });

        /* =======================================================
            AUTO UPDATE SELECT ALL
        ======================================================= */
        classCheckboxes.forEach(checkbox => {

    checkbox.addEventListener("change", function () {

        if (form.dataset.mode === "edit" && this.checked) {

            classCheckboxes.forEach(cb => {
                if (cb !== this) cb.checked = false;
            });

        }

        const visibleCheckboxes = classItems
            .filter(item => item.style.display !== "none")
            .map(item => item.querySelector(".class-checkbox"));

        const allChecked = visibleCheckboxes.length > 0 &&
            visibleCheckboxes.every(cb => cb.checked);

        selectAllClasses.checked = allChecked;

    });

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

            clearClassWrapperError();
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
            error.className = "inline-error text-xs text-red-500 mt-2";
            error.textContent = message;

            getErrorWrapper(input).appendChild(error);
        }

        function setClassWrapperError(message) {
            const wrapper = document.getElementById("classCheckboxWrapper");
            if (!wrapper) return;

            wrapper.classList.remove(
                "border-gray-300",
                "focus:ring-2",
                "focus:ring-blue-500"
            );

            wrapper.classList.add(
                "border-red-500",
                "bg-red-50"
            );

            wrapper.parentElement
                .querySelectorAll(".inline-error")
                .forEach(el => el.remove());

            const error = document.createElement("p");
            error.className = "inline-error text-xs text-red-500 mt-2";
            error.textContent = message;

            wrapper.parentElement.appendChild(error);
        }

        function clearClassWrapperError() {
            const wrapper = document.getElementById("classCheckboxWrapper");
            if (!wrapper) return;

            wrapper.classList.remove(
                "border-red-500",
                "bg-red-50"
            );

            wrapper.classList.add("border-gray-300");

            wrapper.parentElement
                .querySelectorAll(".inline-error")
                .forEach(el => el.remove());
        }

        function attachLiveValidation(input) {
            if (!input) return;

            input.addEventListener("input", () => clearFieldError(input));
            input.addEventListener("change", () => clearFieldError(input));
        }

        attachLiveValidation(teacherSelect);
        attachLiveValidation(classSearchInput);

        classCheckboxes.forEach(checkbox => {
            checkbox.addEventListener("change", clearClassWrapperError);
        });

        /* =======================================================
            SUBJECT AUTO FILL
        ======================================================= */
        teacherSelect.addEventListener("change", function () {

    const selectedOption = this.options[this.selectedIndex];

    const subjectId = selectedOption.dataset.subjectId || "";
    const subjectName = selectedOption.dataset.subjectName || "";

    subjectInput.value = subjectId;
    subjectDisplay.value = subjectName;
    selectAllClasses.checked = false;

    const originalClassId = parseInt(form.dataset.originalClassId || 0);
    const isEditMode = form.dataset.mode === "edit";

            /* ---------- Reset Class Checkbox ---------- */
    classCheckboxes.forEach(cb => {

        const classId = parseInt(cb.value);

        cb.checked = false;
        cb.disabled = false;

        const wrapper = cb.closest(".class-item");

        wrapper.classList.remove(
            "opacity-50",
            "cursor-not-allowed",
            "bg-gray-100"
        );

            /* Restore original class when original teacher is selected */
        if (
            isEditMode &&
            parseInt(this.value) === parseInt(form.dataset.originalTeacherId) &&
            classId === originalClassId
        ) {
            cb.checked = true;
        }

    });

    if (!this.value) return;

        /* ---------- Used Class Retrieval ---------- */
    fetch(`../ajax/teacher_distribution/get_teacher_distribution.php?teacher_id=${this.value}`)

        .then(res => res.json())

        .then(res => {

            if (res.status !== 1) return;

            classCheckboxes.forEach(cb => {

                const classId = parseInt(cb.value);

                const isOriginalClass =
                    isEditMode &&
                    parseInt(teacherSelect.value) === parseInt(form.dataset.originalTeacherId) &&
                    classId === originalClassId;

                if (
                    res.used_classes.includes(classId) &&
                    !isOriginalClass
                ) {

                    cb.disabled = true;
                    cb.checked = false;

                    const wrapper = cb.closest(".class-item");

                    wrapper.classList.add(
                        "opacity-50",
                        "cursor-not-allowed",
                        "bg-gray-100"
                    );

                }

            });

            updateSelectAllAvailability();

        });

});

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

        /* =======================================================
            SAVE STATE
        ======================================================= */
        function saveState() {

            sessionStorage.setItem(STORAGE_KEYS.search, searchInput.value);
            sessionStorage.setItem(STORAGE_KEYS.rows, rowsPerPage.value);
            sessionStorage.setItem(STORAGE_KEYS.page, currentPage);

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
            PAGINATION
        ======================================================= */
        function renderPagination(totalPages) {

            pagination.innerHTML = "";

            if (rowsPerPage.value === "all" || totalPages <= 1) return;

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
            SEARCH
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
            LIMIT
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
        function openTeacherDistributionModal() {

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            form.reset();
            clearInlineErrors();

            form.querySelector('[name="id"]').value = "";

            form.dataset.mode = "create";

            document.getElementById("multiClassControls").classList.remove("hidden");

            classSearchInput.value = "";

            classItems.forEach(item => {
                item.style.display = "flex";
            });

selectAllClasses.checked = false;
            subjectDisplay.value = "";
            subjectInput.value = "";

            /* =======================================================
                RESET MULTI CLASS CHECKBOX
            ======================================================= */
            document.querySelectorAll('[name="class_ids[]"]').forEach(cb => {
                cb.checked = false;
            });

            selectAllClasses.checked = false;
            classSearchInput.value = "";

            classItems.forEach(item => {
                item.style.display = "flex";
            });

            modalTitle.textContent = "Tambah Distribusi Pengajar";

            classCheckboxes.forEach(cb => {

                cb.disabled = false;

                cb.closest(".class-item").classList.remove(
                    "opacity-50",
                    "cursor-not-allowed",
                    "bg-gray-100"
                );

            });

            updateSelectAllAvailability();

        }

        function closeTeacherDistributionModal() {

            modal.classList.add("hidden");
            modal.classList.remove("flex");

            clearInlineErrors();

                        document.getElementById("multiClassControls").classList.remove("hidden");

            classCheckboxes.forEach(cb => {

                cb.disabled = false;

                cb.closest(".class-item").classList.remove(
                    "opacity-50",
                    "cursor-not-allowed",
                    "bg-gray-100"
                );

            });

        }

        openBtn.addEventListener("click", openTeacherDistributionModal);
        closeBtn.addEventListener("click", closeTeacherDistributionModal);
        cancelBtn.addEventListener("click", closeTeacherDistributionModal);

        modal.addEventListener("click", e => {

            if (e.target === modal) {
                closeTeacherDistributionModal();
            }

        });

        /* =======================================================
            ESC KEY CLOSE MODAL
        ======================================================= */
        document.addEventListener("keydown", e => {

            if (
                e.key === "Escape" &&
                !modal.classList.contains("hidden")
            ) {
                closeTeacherDistributionModal();
            }

        });

        /* =======================================================
            FORM SUBMIT
        ======================================================= */
        form.addEventListener("submit", e => {

            e.preventDefault();

            clearInlineErrors();

            const submitBtn = form.querySelector('button[type="submit"]');

            submitBtn.disabled = true;
            submitBtn.classList.add("opacity-70", "cursor-not-allowed");

            const teacherField = form.querySelector('[name="teacher_id"]');
            const classFields = form.querySelectorAll('[name="class_ids[]"]');

            const resetSubmitButton = () => {
                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
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

            if (teacherField.value.trim() === "") {
                setInlineError(teacherField, "Pengajar wajib dipilih.");
                scrollToField(teacherField);
                resetSubmitButton();
                return;
            }

            if (!subjectInput.value) {
                setInlineError(teacherField, "Pengajar belum memiliki mata pelajaran.");
                scrollToField(teacherField);
                resetSubmitButton();
                return;
            }

            const checkedClasses = [...classFields].filter(cb => cb.checked);

            if (checkedClasses.length === 0) {
                setClassWrapperError("Minimal satu kelas wajib dipilih.");
                scrollToField(document.getElementById("classCheckboxWrapper"));
                resetSubmitButton();
                return;
            }

            showLoading("Menyimpan distribusi...");

            const formData = new FormData(form);

            fetch("../ajax/teacher_distribution/save_teacher_distribution.php", {
                method: "POST",
                body: formData
            })

            .then(res => res.json())

            .then(res => {

                Swal.close();

                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");

                /* =======================================================
                    SUCCESS
                ======================================================= */
                if (res.status == 1) {

                    closeTeacherDistributionModal();

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

                    const msg = res.msg.toLowerCase();

                    if (msg.includes("pengajar")) {

                        setInlineError(
                            teacherField,
                            res.msg
                        );

                    } else if (msg.includes("kelas")) {

                        setClassWrapperError(res.msg);

                    } else {

                        setInlineError(
                            teacherField,
                            res.msg
                        );

                    }

                    return;
                }

                /* =======================================================
                    SYSTEM ERROR
                ======================================================= */
                Swal.fire(
                    getResponsiveSwal(
                        "error",
                        "Gagal",
                        res.msg || "Gagal menyimpan distribusi."
                    )
                );

            })

            .catch(() => {

                Swal.close();

                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");

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