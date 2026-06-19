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
    SUBJECT STATISTICS
======================================================= */

/* ---------- Total Subjects ---------- */
$totalSubjects = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM subjects
")->fetch_assoc()['total'];

/* ---------- Subjects With Quiz ---------- */
$subjectsWithQuiz = (int) $conn->query("
    SELECT COUNT(DISTINCT t.subject_id) AS total
    FROM quiz_list q
    INNER JOIN quiz_teacher_list qtl
        ON qtl.quiz_id = q.id
    INNER JOIN teachers t
        ON t.id = qtl.teacher_id
    WHERE t.subject_id IS NOT NULL
")->fetch_assoc()['total'];

/* ---------- Subjects Without Quiz ---------- */
$subjectsWithoutQuiz = max(
    $totalSubjects - $subjectsWithQuiz,
    0
);

/* ---------- Subjects With Teachers ---------- */
$subjectsWithTeachers = (int) $conn->query("
    SELECT COUNT(DISTINCT t.subject_id) AS total
    FROM teachers t
    INNER JOIN subjects s
        ON s.id = t.subject_id
    WHERE t.subject_id IS NOT NULL
")->fetch_assoc()['total'];

/* =======================================================
    MAIN SUBJECT QUERY
======================================================= */
$subjectQuery = $conn->query("
    SELECT
        s.id,
        s.subject_name,
        s.subject_code,
        s.description,
        s.created_at,

        COUNT(DISTINCT q.id) AS total_quiz,
        COUNT(DISTINCT t.id) AS total_teachers

    FROM subjects s

    LEFT JOIN teachers t
        ON t.subject_id = s.id

    LEFT JOIN quiz_teacher_list qtl
        ON qtl.teacher_id = t.id

    LEFT JOIN quiz_list q
        ON q.id = qtl.quiz_id

    GROUP BY s.id

    ORDER BY s.subject_name ASC
");

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Mapel | Takar-Edu";

/* =======================================================
    PAGE LAYOUT
======================================================= */
$subjectColspan = 6;

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
                            Manajemen Mata Pelajaran
                        </h1>

                        <p class="page-description">
                            Kelola seluruh master mata pelajaran, kode mapel, dan distribusi pengajar dalam sistem.
                        </p>

                    </div>

                </div>

            </section>

            <!-- =======================================================
                STATISTICS SECTION
            ======================================================= -->
            <section class="mb-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">

                    <!-- ---------- Total Subjects ---------- -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="book-open" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Mapel</p>
                            <h3 class="stat-value">
                                <?= $totalSubjects ?>
                            </h3>
                            <p class="stat-label">Mapel terdaftar</p>
                        </div>
                    </div>

                    <!-- ---------- Subjects With Quiz ---------- -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="file-text" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Mapel Berkuis</p>
                            <h3 class="stat-value">
                                <?= $subjectsWithQuiz ?>
                            </h3>
                            <p class="stat-label">Sudah memiliki kuis</p>
                        </div>
                    </div>

                    <!-- ---------- Subjects Without Quiz ---------- -->
                    <div class="stat-card">
                        <div class="stat-icon bg-yellow-600">
                            <i data-lucide="clock-3" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Belum Digunakan</p>
                            <h3 class="stat-value">
                                <?= $subjectsWithoutQuiz ?>
                            </h3>
                            <p class="stat-label">Belum memiliki kuis</p>
                        </div>
                    </div>

                    <!-- ---------- Subjects With Teachers ---------- -->
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-600">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Mapel Diajar</p>
                            <h3 class="stat-value">
                                <?= $subjectsWithTeachers ?>
                            </h3>
                            <p class="stat-label">Terhubung pengajar</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                SUBJECT TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Daftar Mata Pelajaran
                    </h2>

                    <!-- Add subject button -->
                    <button
                        id="newSubject"
                        type="button"
                        class="form-btn form-btn-primary"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah Mapel
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
                            placeholder="Cari mapel..."
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
                                <th class="table-th w-[35%]">Nama Mapel</th>
                                <th class="table-th w-[10%]">Kode</th>
                                <th class="table-th w-[15%]">Total Kuis</th>
                                <th class="table-th w-[15%]">Pengajar</th>
                                <th class="table-th w-[20%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($subjectQuery && $subjectQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($subject = $subjectQuery->fetch_assoc()): ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Subject name -->
                                        <td class="table-td">

                                            <div class="table-td-title">
                                                <?= htmlspecialchars($subject['subject_name']) ?>
                                            </div>

                                            <div class="table-subtext">
                                                <?= !empty($subject['description'])
                                                    ? htmlspecialchars($subject['description'])
                                                    : 'Tidak ada deskripsi mapel' ?>
                                            </div>

                                        </td>

                                        <!-- Subject code -->
                                        <td class="table-td text-center">
                                            <?= !empty($subject['subject_code'])
                                                ? htmlspecialchars($subject['subject_code'])
                                                : '-' ?>
                                        </td>

                                        <!-- Total quiz -->
                                        <td class="table-td text-center">
                                            <?= (int) $subject['total_quiz'] ?>
                                        </td>

                                        <!-- Total teachers -->
                                        <td class="table-td text-center">
                                            <?= (int) $subject['total_teachers'] ?>
                                        </td>

                                        <!-- Action buttons -->
                                        <td class="table-td text-center">

                                            <div class="action-group">

                                                <!-- Edit button -->
                                                <button
                                                    type="button"
                                                    onclick="editSubject(<?= $subject['id'] ?>)"
                                                    class="action-btn action-info"
                                                >
                                                    <i data-lucide="square-pen" class="w-4 h-4"></i>
                                                    <span class="action-label">Edit</span>
                                                </button>

                                                <!-- Delete button -->
                                                <button
                                                    type="button"
                                                    onclick="deleteSubject(<?= $subject['id'] ?>)"
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
                                class="<?= ($subjectQuery && $subjectQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $subjectColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="book-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada mata pelajaran tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $subjectColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada mata pelajaran yang sesuai dengan pencarian
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
                    <div id="pageInfo" class="page-info"></div>

                    <!-- Pagination -->
                    <div id="pagination" class="pagination"></div>

                </div>

            </section>

        </div>

    </main>

    <!-- =======================================================
        SUBJECT MODAL SECTION
    ======================================================= -->
    <div id="subjectModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Mata Pelajaran Baru
                </h3>

                <button
                    id="closeModalSubject"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Subject Form ---------- -->
            <form id="subjectForm" class="modal-form">

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id">

                    <!-- Subject name -->
                    <div>

                        <label class="form-label">
                            Nama Mata Pelajaran
                        </label>

                        <input
                            type="text"
                            name="subject_name"
                            placeholder="Masukkan nama mapel"
                            class="form-input"
                        >

                    </div>

                    <!-- Subject code -->
                    <div>

                        <label class="form-label">
                            Kode Mata Pelajaran
                        </label>

                        <input
                            type="text"
                            name="subject_code"
                            placeholder="Masukkan kode mapel"
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
                            rows="3"
                            placeholder="Deskripsi mapel (opsional)"
                            class="form-textarea"
                        ></textarea>

                    </div>

                </div>

                <!-- ---------- Modal Footer ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        id="cancelModalSubject"
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
    /* ---------- Responsive Dialog Config ---------- */
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
        DELETE SUBJECT
    ======================================================= */
    function deleteSubject(id) {

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                "Hapus mata pelajaran?",
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

            fetch(`../ajax/subject/delete_subject.php?id=${id}`)

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
        EDIT SUBJECT
    ======================================================= */
    function editSubject(id) {

        showLoading("Memuat data...");

        fetch(`../ajax/subject/get_subject.php?id=${id}`)

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

                const modal = document.getElementById("subjectModal");
                const form = document.getElementById("subjectForm");
                const modalTitle = document.getElementById("modalTitle");

                const data = res.data;

                modal.classList.remove("hidden");
                modal.classList.add("flex");

                modalTitle.textContent = "Edit Data Mata Pelajaran";

                form.querySelector('[name="id"]').value = data.id;
                form.querySelector('[name="subject_name"]').value = data.subject_name;
                form.querySelector('[name="subject_code"]').value = data.subject_code || "";
                form.querySelector('[name="description"]').value = data.description || "";

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
        DOM READY
    ======================================================= */
    document.addEventListener("DOMContentLoaded", () => {

                /* ---------- Storage Config ---------- */
        const STORAGE_KEYS = {
            search: "subject_search_keyword",
            rows: "subject_rows_per_page",
            page: "subject_current_page"
        };

        const navigationType =
            performance.getEntriesByType("navigation")[0]?.type || "navigate";

        const shouldRestore = navigationType === "reload";

        if (!shouldRestore) {
            sessionStorage.removeItem(STORAGE_KEYS.search);
            sessionStorage.removeItem(STORAGE_KEYS.rows);
            sessionStorage.removeItem(STORAGE_KEYS.page);
        }

                /* ---------- Lucide Initialization ---------- */
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

        const modal = document.getElementById("subjectModal");
        const form = document.getElementById("subjectForm");
        const openBtn = document.getElementById("newSubject");
        const closeBtn = document.getElementById("closeModalSubject");
        const cancelBtn = document.getElementById("cancelModalSubject");
        const modalTitle = document.getElementById("modalTitle");

        /* =======================================================
            INLINE VALIDATION
        ======================================================= */
        const subjectNameInput = form.querySelector('[name="subject_name"]');
        const subjectCodeInput = form.querySelector('[name="subject_code"]');

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

            input.parentElement
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
        "focus:ring-blue-500"
            );

            input.classList.add(
        "border-red-500",
        "focus:border-red-500",
        "focus:ring-0"
            );

            input.parentElement
        .querySelectorAll(".inline-error")
        .forEach(el => el.remove());

            const error = document.createElement("p");
            error.className = "inline-error text-xs text-red-500 mt-1";
            error.textContent = message;

            input.parentElement.appendChild(error);
        }

        function attachLiveValidation(input) {
            if (!input) return;

            input.addEventListener("input", () => clearFieldError(input));
            input.addEventListener("change", () => clearFieldError(input));
        }

        attachLiveValidation(subjectNameInput);
        attachLiveValidation(subjectCodeInput);

        /* ---------- Subject Code Auto Uppercase ---------- */
        subjectCodeInput.addEventListener("input", () => {
            subjectCodeInput.value = subjectCodeInput.value.toUpperCase();
        });



        /* ---------- Restore State ---------- */
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

                /* ---------- Save State ---------- */
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
        function openSubjectModal() {

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            form.reset();
            clearInlineErrors();

            form.querySelector('[name="id"]').value = "";

            modalTitle.textContent = "Tambah Mata Pelajaran Baru";

            setTimeout(() => {
                subjectNameInput.focus();
            }, 100);

        }

        function closeSubjectModal() {

            modal.classList.add("hidden");
            modal.classList.remove("flex");

            clearInlineErrors();

        }

        openBtn.addEventListener("click", openSubjectModal);
        closeBtn.addEventListener("click", closeSubjectModal);
        cancelBtn.addEventListener("click", closeSubjectModal);

        modal.addEventListener("click", e => {

            if (e.target === modal) {
                closeSubjectModal();
            }

        });

                /* ---------- ESC Close Modal ---------- */
        document.addEventListener("keydown", e => {

            if (
                e.key === "Escape" &&
                !modal.classList.contains("hidden")
            ) {
                closeSubjectModal();
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

            const scrollToField = field => {
                field.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });

                setTimeout(() => {
                    field.focus();
                }, 200);
            };

            if (!subjectNameInput.value.trim()) {
                setInlineError(subjectNameInput, "Nama mata pelajaran wajib diisi.");
                scrollToField(subjectNameInput);

                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");
                return;
            }

            showLoading("Menyimpan data...");

            const formData = new FormData(form);

            fetch("../ajax/subject/save_subject.php", {
                method: "POST",
                body: formData
            })

            .then(res => res.json())

            .then(res => {

                Swal.close();

                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");

                if (res.status == 1) {

                    closeSubjectModal();

                    showToast("success", res.msg);

                    setTimeout(() => {
                        location.reload();
                    }, 1200);

                    return;
                }

                if (res.status == 2) {

                    const msg = res.msg.toLowerCase();

                    if (msg.includes("kode")) {
                        setInlineError(subjectCodeInput, res.msg);
                        scrollToField(subjectCodeInput);
                    } else {
                        setInlineError(subjectNameInput, res.msg);
                        scrollToField(subjectNameInput);
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

                /* ---------- Initial Table Render ---------- */
        renderTable();

    });
    </script>

</body>
</html>