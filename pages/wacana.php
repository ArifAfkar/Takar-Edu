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
    TEACHER SUBJECT ACCESS
======================================================= */
$teacherId = 0;
$teacherSubjectId = 0;

if ($isTeacher) {

    $teacherDataQuery = $conn->query("
        SELECT id, subject_id
        FROM teachers
        WHERE user_id = {$userId}
        LIMIT 1
    ");

    if ($teacherDataQuery && $teacherDataQuery->num_rows > 0) {

        $teacherData = $teacherDataQuery->fetch_assoc();

        $teacherId        = (int) $teacherData['id'];
        $teacherSubjectId = (int) $teacherData['subject_id'];

    }

}

/* =======================================================
    WACANA FILTER
======================================================= */
$wacanaWhereClause = "";

if ($isTeacher) {

    $wacanaWhereClause = "
        WHERE (
            w.user_id = {$userId}
            OR w.visibility_scope = 3
            OR EXISTS (
                SELECT 1
                FROM wacana_teacher_access wta
                WHERE wta.wacana_id = w.id
                AND wta.teacher_id = {$teacherId}
            )
        )
    ";
}

/* =======================================================
    SUBJECT OPTIONS
======================================================= */
if ($isAdmin) {

    /* ---------- All Subject Options ---------- */
    $subjectOptions = $conn->query("
        SELECT
            id,
            subject_name
        FROM subjects
        ORDER BY subject_name ASC
    ");

} else {

    /* ---------- Teacher Subject Options ---------- */
    $subjectOptions = $conn->query("
        SELECT
            id,
            subject_name
        FROM subjects
        WHERE id = {$teacherSubjectId}
        LIMIT 1
    ");
}

/* =======================================================
    SHARED ACCESS OPTIONS
======================================================= */

/* ---------- Subject Options ---------- */
$shareSubjectOptions = $conn->query("
    SELECT
        id,
        subject_name
    FROM subjects
    ORDER BY subject_name ASC
");

/* ---------- Grade Options ---------- */
$shareGradeOptions = $conn->query("
    SELECT DISTINCT grade_level
    FROM classes
    WHERE grade_level IS NOT NULL
    AND grade_level != ''
    ORDER BY FIELD(grade_level, 'X', 'XI', 'XII'), grade_level ASC
");

/* =======================================================
    WACANA STATISTICS
======================================================= */

/* ---------- Total Wacana ---------- */
$totalWacana = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM wacana w
    {$wacanaWhereClause}
")->fetch_assoc()['total'];

/* ---------- Used Wacana ---------- */
$usedWacana = (int) $conn->query("
    SELECT COUNT(DISTINCT w.id) AS total
    FROM wacana w
    INNER JOIN questions q
        ON w.id = q.wacana_id
    {$wacanaWhereClause}
")->fetch_assoc()['total'];

/* ---------- Unused Wacana ---------- */
$unusedWacana = max($totalWacana - $usedWacana, 0);

/* ---------- Public Wacana ---------- */
$publicWacana = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM wacana w
    {$wacanaWhereClause}
    " . ($wacanaWhereClause ? " AND " : " WHERE ") . "
    w.visibility_scope = 3
")->fetch_assoc()['total'];

/* =======================================================
    MAIN WACANA QUERY
======================================================= */
$wacanaQuery = $conn->query("
    SELECT
        w.*,
        s.subject_name,
        u.name AS creator_name,

        COUNT(DISTINCT q.id) AS usage_count,

        GROUP_CONCAT(
            DISTINCT CONCAT(
                ql.id,
                '##',
                q.id,
                '##',
                IFNULL(ql.quiz_title, 'Quiz Tidak Diketahui'),
                ' — Soal No. ',
                IFNULL(q.order_by, '-')
            )
            ORDER BY q.quiz_id ASC
            SEPARATOR '||'
        ) AS usage_detail

    FROM wacana w

    LEFT JOIN subjects s
        ON w.subject_id = s.id

    LEFT JOIN users u
        ON w.user_id = u.id

    LEFT JOIN questions q
        ON w.id = q.wacana_id

    LEFT JOIN quiz_list ql
        ON q.quiz_id = ql.id

    {$wacanaWhereClause}

    GROUP BY w.id
    ORDER BY w.created_at DESC
");

/* =======================================================
    DISPLAY HELPER FUNCTIONS
======================================================= */

/* ---------- Visibility Status Data ---------- */
function getVisibilityData($scope)
{
    switch ((int)$scope) {
        case 2:
            return [
                'label' => 'Dibagikan',
                'class' => 'status-warning'
            ];
        case 3:
            return [
                'label' => 'Publik',
                'class' => 'status-info'
            ];
        default:
            return [
                'label' => 'Privat',
                'class' => 'status-secondary'
            ];
    }
}

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Wacana | Takar-Edu";

/* =======================================================
    PAGE LAYOUT
======================================================= */
$wacanaColspan = 7;
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
                            Manajemen Wacana
                        </h1>

                        <p class="page-description">

                            <?php if ($isAdmin): ?>
                                Kelola seluruh bank wacana sebagai stimulus soal, asesmen, dan literasi berbasis konteks.

                            <?php else: ?>
                                Kelola wacana pribadi, mapel terkait, dan akses stimulus evaluasi Anda.

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

                    <!-- Total wacana -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="book-open-text" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Wacana</p>
                            <h3 class="stat-value">
                                <?= $totalWacana ?>
                            </h3>
                            <p class="stat-label">Bank stimulus</p>
                        </div>
                    </div>

                    <!-- Used wacana -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Digunakan</p>
                            <h3 class="stat-value">
                                <?= $usedWacana ?>
                            </h3>
                            <p class="stat-label">Sudah terpakai</p>
                        </div>
                    </div>

                    <!-- Unused wacana -->
                    <div class="stat-card">
                        <div class="stat-icon bg-yellow-600">
                            <i data-lucide="archive" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Belum Digunakan</p>
                            <h3 class="stat-value">
                                <?= $unusedWacana ?>
                            </h3>
                            <p class="stat-label">Siap digunakan</p>
                        </div>
                    </div>

                    <!-- Public wacana -->
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-600">
                            <i data-lucide="globe" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Publik</p>
                            <h3 class="stat-value">
                                <?= $publicWacana ?>
                            </h3>
                            <p class="stat-label">Dapat dibagikan</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                WACANA TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Bank Wacana
                    </h2>

                    <!-- Add wacana button -->
                    <button
                        id="newWacana"
                        type="button"
                        class="form-btn form-btn-primary"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah Wacana
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
                            placeholder="Cari wacana..."
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
                                <th class="table-th w-[30%]">Judul</th>
                                <th class="table-th w-[17%]">Mapel</th>
                                <th class="table-th w-[13%]">Akses</th>
                                <th class="table-th w-[10%]">Digunakan</th>
                                <th class="table-th w-[10%]">Status</th>
                                <th class="table-th w-[15%]">Aksi</th>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                        <?php if ($wacanaQuery && $wacanaQuery->num_rows > 0): ?>

                            <?php $no = 1; ?>

                            <?php while ($row = $wacanaQuery->fetch_assoc()): ?>

                                <?php
                                $visibility = getVisibilityData($row['visibility_scope']);

                                $isOwner = (int)$row['user_id'] === $userId;

                                $canManageRow = $isAdmin || ($isTeacher && $isOwner);
                                ?>

                                    <tr class="app-table-row">

                                        <!-- Number -->
                                        <td class="table-td text-center">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Wacana title -->
                                        <td class="table-td">

                                            <div class="table-td-title">
                                                <?= htmlspecialchars($row['wacana_title']) ?>
                                            </div>

                                            <div class="table-subtext">
                                                Oleh: <?= htmlspecialchars($row['creator_name']) ?>
                                            </div>

                                        </td>

                                        <!-- Subject -->
                                        <td class="table-td text-center">
                                            <?= !empty($row['subject_name'])
                                                ? htmlspecialchars($row['subject_name'])
                                                : '-' ?>
                                        </td>

                                        <!-- Visibility -->
                                        <td class="table-td text-center">

                                            <span class="status-badge <?= $visibility['class'] ?>">
                                                <?= $visibility['label'] ?>
                                            </span>

                                        </td>

                                        <!-- Usage -->
                                        <td class="table-td text-center">

                                        <?php if ((int)$row['usage_count'] > 0): ?>

                                            <button
                                                type="button"
                                                onclick="showUsageDetail(`<?= htmlspecialchars($row['usage_detail'], ENT_QUOTES) ?>`)"
                                                class="status-badge status-success cursor-pointer"
                                                title="Klik untuk melihat detail penggunaan"
                                            >
                                                <?= $row['usage_count'] ?> soal
                                            </button>

                                        <?php else: ?>

                                            <span class="status-badge status-warning">
                                                Belum
                                            </span>

                                        <?php endif; ?>

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

                                                <!-- Detail button -->
                                                <button
                                                    type="button"
                                                    onclick='showDescriptionDetail(<?= json_encode($row["description"]) ?>)'
                                                    class="action-btn action-info"
                                                >
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                    <span class="action-label">Detail</span>
                                                </button>

                                                <?php if ($canManageRow): ?>

                                                    <!-- Edit button -->
                                                    <button
                                                        type="button"
                                                        onclick="editWacana(<?= (int)$row['id'] ?>)"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="square-pen" class="w-4 h-4"></i>
                                                        <span class="action-label">Edit</span>
                                                    </button>

                                                    <!-- Status button -->
                                                    <?php $isActive = (int)$row['status'] === 1; ?>

                                                    <button
                                                        type="button"
                                                        onclick="toggleStatusWacana(<?= (int)$row['id'] ?>, <?= (int)$row['status'] ?>)"
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
                                                        onclick="deleteWacana(<?= (int)$row['id'] ?>)"
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
                                class="<?= ($wacanaQuery && $wacanaQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $wacanaColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="book-open-text" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada wacana tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $wacanaColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada wacana yang sesuai dengan pencarian
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
        WACANA MODAL SECTION
    ======================================================= -->
    <div id="wacanaModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah Wacana Baru
                </h3>

                <button
                    id="closeModalWacana"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- Wacana Form ---------- -->
            <form
                id="wacanaForm"
                class="modal-form"
                enctype="multipart/form-data"
            >

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id">
                    <input type="hidden" name="creator_role" value="<?= $userType ?>">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    <input type="hidden" name="status" value="1">

                    <!-- Wacana title -->
                    <div>

                        <label class="form-label">
                            Judul Wacana
                        </label>

                        <input
                            type="text"
                            name="wacana_title"
                            placeholder="Masukkan judul wacana"
                            class="form-input"
                        >

                    </div>

                    <!-- Subject -->
                    <?php if ($isAdmin): ?>

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
                                        
                                        <option value="<?= (int)$subject['id'] ?>">
                                            <?= htmlspecialchars($subject['subject_name']) ?>
                                        </option>

                                    <?php endwhile; ?>

                                <?php endif; ?>

                            </select>

                        </div>

                    <?php else: ?>

                        <input
                            type="hidden"
                            name="subject_id"
                            value="<?= (int)$teacherSubjectId ?>"
                        >

                    <?php endif; ?>

                    <!-- Wacana content -->
                    <div>

                        <label class="form-label">
                            Isi Wacana
                        </label>

                        <div class="editor-toolbar">

                            <button
                                type="button"
                                id="insertEquationBtn"
                                class="equation-btn"
                            >
                                ∑ Rumus
                            </button>

                        </div>

                        <textarea
                            id="wacanaEditor"
                            name="description"
                            rows="8"
                            placeholder="Masukkan isi lengkap wacana..."
                            class="form-textarea"
                        ></textarea>

                    </div>

                    <!-- Visibility scope -->
                    <?php if ($isAdmin): ?>

                        <div>

                            <label class="form-label">
                                Akses Wacana
                            </label>

                            <select name="visibility_scope" class="form-select">
                                <option value="1">Privat</option>
                                <option value="2">Dibagikan ke Pengajar</option>
                                <option value="3">Publik</option>
                            </select>

                        </div>

                        <!-- Shared access panel -->
                        <div id="sharedAccessPanel" class="hidden access-panel">

                            <div class="panel-header">
                                <h4 class="panel-title">
                                    Pengaturan Penerima Wacana
                                </h4>

                                <p class="panel-description">
                                    Pilih mapel dan tingkat, lalu pilih pengajar penerima akses.
                                </p>
                            </div>

                            <!-- Share filter -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                <!-- Subject -->
                                <div>
                                    <label class="form-label">
                                        Mapel
                                    </label>

                                    <select
                                        id="shareSubjectFilter"
                                        class="form-select"
                                        disabled
                                    >
                                        <option value="">Pilih mapel</option>

                                        <?php if ($shareSubjectOptions && $shareSubjectOptions->num_rows > 0): ?>

                                            <?php while ($subject = $shareSubjectOptions->fetch_assoc()): ?>

                                                <option value="<?= (int)$subject['id'] ?>">
                                                    <?= htmlspecialchars($subject['subject_name']) ?>
                                                </option>

                                            <?php endwhile; ?>

                                        <?php endif; ?>

                                    </select>
                                </div>

                                <!-- Grade level -->
                                <div>
                                    <label class="form-label">
                                        Tingkat
                                    </label>

                                    <select
                                        id="shareGradeFilter"
                                        name="grade_level"
                                        class="form-select"
                                    >
                                        <option value="">Pilih tingkat</option>

                                        <?php if ($shareGradeOptions && $shareGradeOptions->num_rows > 0): ?>

                                            <?php while ($grade = $shareGradeOptions->fetch_assoc()): ?>

                                                <option value="<?= htmlspecialchars($grade['grade_level']) ?>">
                                                    <?= htmlspecialchars($grade['grade_level']) ?>
                                                </option>

                                            <?php endwhile; ?>

                                        <?php endif; ?>

                                    </select>
                                </div>

                            </div>

                            <!-- Teacher -->
                            <div>

                                <label class="form-label">
                                    Pengajar
                                </label>

                                <!-- Select all -->
                                <div
                                    id="teacherBulkControls"
                                    class="hidden flex items-center justify-between mb-1"
                                >

                                    <label class="checkbox-inline">

                                        <input
                                            type="checkbox"
                                            id="selectAllTeachersBtn"
                                            class="checkbox-inline-input"
                                        >

                                        <span class="checkbox-inline-text">
                                            Pilih Semua
                                        </span>

                                    </label>

                                    <!-- Reset -->
                                    <button
                                        type="button"
                                        id="resetTeachersBtn"
                                        class="link-danger-sm"
                                    >
                                        Reset
                                    </button>

                                </div>

                                <!-- Teacher selection list -->
                                <div
                                    id="shareTeacherList"
                                    class="selection-container min-h-[80px]"
                                >
                                    Pilih mapel dan tingkat terlebih dahulu.
                                </div>

                            </div>

                            <!-- Class preview -->
                            <div>

                                <label class="form-label">
                                    Kelas yang Diampu
                                </label>

                                <div
                                    id="shareClassList"
                                    class="selection-container min-h-[70px]"
                                >
                                    Pilih pengajar untuk melihat kelas yang diampu.
                                </div>

                            </div>

                        </div>

                    <?php else: ?>

                        <input type="hidden" name="visibility_scope" value="1">

                    <?php endif; ?>

                </div>

                <!-- ---------- Form Buttons ---------- -->
                <div class="global-modal-footer">

                    <!-- Cancel button -->
                    <button
                        type="button"
                        id="cancelModalWacana"
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

    /* ---------- Toast ---------- */
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

    /* ---------- Loading ---------- */
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
        WACANA DETAIL HELPERS
    ======================================================= */
    function showUsageDetail(detail) {

        if (!detail || detail.trim() === "") {

            Swal.fire(
                getResponsiveSwal(
                    "info",
                    "Belum Digunakan",
                    "Wacana ini belum digunakan dalam soal manapun."
                )
            );

            return;
        }

        const items = detail.split("||");

        let formatted = "";

        items.forEach(item => {

            const parts = item.split("##");

            const quizId = parts[0];
            const questionId = parts[1];
            const label = parts[2];

            formatted += `
                <li class="flex items-center justify-between gap-3">
                    <span>${label}</span>

                    <button
                        onclick="goToQuestion(${quizId}, ${questionId})"
                        class="
                            px-3 py-1
                            rounded-lg
                            bg-blue-600 hover:bg-blue-700
                            text-white text-xs font-medium
                            transition
                        "
                    >
                        Buka
                    </button>
                </li>
            `;
        });

        Swal.fire({
            title: "Detail Penggunaan",
            html: `
                <ul class="text-left list-disc pl-5 space-y-3">
                    ${formatted}
                </ul>
            `,
            width: "42rem",
            confirmButtonText: "Tutup",
            buttonsStyling: false,

            customClass: {
                popup: "rounded-2xl shadow-xl px-6 py-6",
                title: "text-xl font-bold text-gray-800",
                confirmButton: `
                    inline-flex items-center justify-center
                    px-5 py-3 rounded-xl
                    font-semibold text-white
                    bg-blue-600 hover:bg-blue-700
                `
            }
        });

    }

    /* ---------- Question Redirect ---------- */
    function goToQuestion(quizId, questionId) {

        window.location.href =
            `quiz_view.php?id=${quizId}&question_id=${questionId}`;

    }

    /* ---------- Description Detail ---------- */
    function showDescriptionDetail(description) {

        if (!description || description.trim() === "") {

            Swal.fire(
                getResponsiveSwal(
                    "info",
                    "Deskripsi Kosong",
                    "Tidak ada deskripsi tersedia."
                )
            );

            return;
        }

        Swal.fire({
            title: "Detail Deskripsi Wacana",
            html: `
                <div class="ck-content text-left max-h-[60vh] overflow-y-auto px-6 py-4">
                    ${description}
                </div>
            `,
            width: window.innerWidth < 640 ? "95%" : "50rem",
            confirmButtonText: "Tutup",
            buttonsStyling: false,

            customClass: {
                popup: "rounded-2xl shadow-xl px-6 py-6",
                title: "text-xl font-bold text-gray-800",
                htmlContainer: "text-sm sm:text-base text-gray-700",
                confirmButton: `
                    inline-flex items-center justify-center
                    px-5 py-3 rounded-xl
                    font-semibold text-white
                    bg-blue-600 hover:bg-blue-700
                    transition
                `
            },

            didOpen: () => {
                const content = Swal.getHtmlContainer().querySelector(".ck-content");

                if (!content) return;

                setTimeout(() => {
                    renderMathContent(content);
                }, 100);

                content.querySelectorAll("img").forEach(img => {
                    img.style.maxWidth = "100%";
                    img.style.height = "auto";
                    img.style.display = "block";
                });

                content.querySelectorAll("ul, ol").forEach(list => {
                    list.style.paddingLeft = "2.5rem";
                    list.style.marginLeft = "0";
                    list.style.listStylePosition = "outside";
                });

                content.querySelectorAll("li").forEach(item => {
                    item.style.paddingLeft = "0.25rem";
                });
            }
        });

    }

    /* =======================================================
        WACANA ACTIONS
    ======================================================= */

    /* ---------- Delete Wacana ---------- */
    function deleteWacana(id) {

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                "Hapus wacana?",
                "Data yang dihapus tidak dapat dikembalikan."
            ),
            showCancelButton: true,
            confirmButtonText: "Ya, hapus",
            cancelButtonText: "Batal"
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Menghapus data...");

            fetch(`../ajax/wacana/delete_wacana.php?id=${id}`)

                .then(res => res.json())

                .then(res => {

                    Swal.close();

                    if (res.status == 1) {

                        showToast("success", res.msg);

                        setTimeout(() => {
                            location.reload();
                        }, 1200);

                    } else {

                        Swal.fire({
                        ...getResponsiveSwal(
                            "error",
                            "Gagal",
                            res.msg || "Gagal menghapus data."
                        ),

                        showCancelButton: true,
                        confirmButtonText: "Lihat Soal",
                        cancelButtonText: "Tutup"

                    }).then(result => {

                        if (result.isConfirmed) {

                            showUsageDetail(res.usage_detail);

                        }

                    });

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

    /* ---------- Toggle Wacana Status ---------- */
    function toggleStatusWacana(id, currentStatus) {

        const newStatus = currentStatus === 1 ? 0 : 1;
        const actionText = currentStatus === 1 ? "arsipkan" : "aktifkan";

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} wacana?`,
                currentStatus === 1
                    ? "Wacana akan disembunyikan dari penggunaan aktif."
                    : "Wacana akan tersedia kembali."
            ),
            showCancelButton: true,
            confirmButtonText: `Ya, ${actionText}`,
            cancelButtonText: "Batal"
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Memproses...");

            fetch("../ajax/wacana/toggle_status_wacana.php", {
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
        const form = document.getElementById("wacanaForm");
        if (!form) return;

        form.querySelectorAll("input, textarea, select").forEach(input => {
            clearFieldError(input);
        });

        clearTeacherAccessError();
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

    function setEditorError(message) {
        const textarea = document.getElementById("wacanaEditor");
        if (!textarea) return;

        setInlineError(textarea, message);
    }

    function setTeacherAccessError(message) {
        const teacherList = document.getElementById("shareTeacherList");
        if (!teacherList) return;

        teacherList.classList.add(
            "border-red-500",
            "bg-red-50"
        );

        teacherList.classList.remove(
            "border-gray-200",
            "bg-white"
        );

        document.getElementById("teacherAccessError")?.remove();

        const error = document.createElement("p");
        error.id = "teacherAccessError";
        error.className = "inline-error text-xs text-red-500 mt-2";
        error.textContent = message;

        teacherList.parentElement.appendChild(error);
    }

    function clearTeacherAccessError() {
        const teacherList = document.getElementById("shareTeacherList");

        if (teacherList) {
            teacherList.classList.remove(
                "border-red-500",
                "bg-red-50"
            );

            teacherList.classList.add(
                "border-gray-200",
                "bg-white"
            );
        }

        document.getElementById("teacherAccessError")?.remove();
    }

    function attachLiveValidation(input) {
        if (!input) return;

        input.addEventListener("input", () => clearFieldError(input));
        input.addEventListener("change", () => clearFieldError(input));
    }

    /* ---------- Edit Wacana ---------- */
    function editWacana(id) {

        showLoading("Memuat data...");

        fetch(`../ajax/wacana/get_wacana.php?id=${id}`)

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

                const modal =
                    document.getElementById("wacanaModal");

                const form =
                    document.getElementById("wacanaForm");

                const modalTitle =
                    document.getElementById("modalTitle");

                const data = res.data;

                modal.classList.remove("hidden");
                modal.classList.add("flex");

                document.body.classList.add("overflow-hidden");

                modalTitle.textContent =
                    "Edit Data Wacana";

                clearInlineErrors();

                form.querySelector('[name="id"]').value =
                    data.id;

                form.querySelector('[name="wacana_title"]').value =
                    data.wacana_title || "";

    /* ---------- Subject ---------- */
    const subjectSelect = form.querySelector('select[name="subject_id"]');
    const hiddenSubject = form.querySelector('input[type="hidden"][name="subject_id"]');

    if (subjectSelect) {
        subjectSelect.value = data.subject_id || "";
    }

    if (hiddenSubject) {
        hiddenSubject.value = data.subject_id || "";
    }

    if (window.wacanaEditor) {
        window.wacanaEditor.setData(data.description || "");
    } else {
        form.querySelector('[name="description"]').value =
            data.description || "";
    }

    const visibilityField = form.querySelector('[name="visibility_scope"]');
    if (visibilityField) {
        visibilityField.value =
            data.visibility_scope !== undefined && data.visibility_scope !== null
                ? String(data.visibility_scope)
                : "1";

        visibilityField.dispatchEvent(new Event("change"));

        setTimeout(() => {
            if (typeof prepareSharedAccessEdit === "function") {
                prepareSharedAccessEdit(data);
            }
        }, 300);
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

    }

    /* =======================================================
        MATH RENDERING HELPERS
    ======================================================= */
    function renderMathContent(target = document.body) {
        if (typeof renderMathInElement !== "function") return;

        renderMathInElement(target, {
            delimiters: [
                { left: "$$", right: "$$", display: true },
                { left: "\\(", right: "\\)", display: false },
                { left: "\\[", right: "\\]", display: true }
            ],
            throwOnError: false
        });
    }

    /* =======================================================
        EQUATION BUILDER
    ======================================================= */
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
        if (!input) return;

        const latex = input.value.trim();

        if (!latex) {
            showToast("warning", "Rumus masih kosong.");
            return;
        }

        const equationHtml = `
            <span class="math-equation">\\(${latex}\\)</span>
        `;

        if (window.wacanaEditor) {
            const currentData = window.wacanaEditor.getData();
            window.wacanaEditor.setData(currentData + equationHtml);
        } else {
            const textarea = document.getElementById("wacanaEditor");
            if (textarea) textarea.value += equationHtml;
        }

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

    document.addEventListener("DOMContentLoaded", () => {

            /* =======================================================
            PAGE INITIALIZATION
        ======================================================= */

        /* ---------- Lucide Icons ---------- */
        if (window.lucide) {
            lucide.createIcons();
        }

        /* ---------- Storage Keys ---------- */
        const STORAGE_KEYS = {
        search: "wacana_search_keyword",
        rows: "wacana_rows_per_page",
        page: "wacana_current_page"
        };

        /* ---------- Navigation Type ---------- */
        const navigationType =
        performance.getEntriesByType("navigation")[0]?.type || "navigate";

        const shouldRestore = navigationType === "reload";

        if (!shouldRestore) {
            sessionStorage.removeItem(STORAGE_KEYS.search);
            sessionStorage.removeItem(STORAGE_KEYS.rows);
            sessionStorage.removeItem(STORAGE_KEYS.page);
        }

        /* =======================================================
            ELEMENT REFERENCES
        ======================================================= */
        const table =
            document.getElementById("dataTable");

        if (!table) return;

        const rows = [...table.querySelectorAll("tbody tr")].filter(
            row =>
                row.id !== "emptyRow" &&
                row.id !== "noResult"
        );

        const searchInput =
            document.getElementById("searchInput");

        const rowsPerPage =
            document.getElementById("rowsPerPage");

        const pageInfo =
            document.getElementById("pageInfo");

        const pagination =
            document.getElementById("pagination");

        const emptyRow =
            document.getElementById("emptyRow");

        const noResult =
            document.getElementById("noResult");

        const modal =
            document.getElementById("wacanaModal");

        const form =
            document.getElementById("wacanaForm");

        const openBtn =
            document.getElementById("newWacana");

        const closeBtn =
            document.getElementById("closeModalWacana");

        const cancelBtn =
            document.getElementById("cancelModalWacana");

        const modalTitle =
            document.getElementById("modalTitle");

        /* =======================================================
            INLINE VALIDATION
        ======================================================= */
        const titleInput =
            form.querySelector(
                '[name="wacana_title"]'
            );

        const descInput =
            form.querySelector(
                '[name="description"]'
            );

        const subjectInput =
            form.querySelector('[name="subject_id"]');

        const gradeInput =
            form.querySelector('[name="grade_level"]');

        attachLiveValidation(titleInput);
        attachLiveValidation(descInput);
        attachLiveValidation(subjectInput);
        attachLiveValidation(gradeInput);

        let wacanaEditor = null;

        /* =======================================================
            CKEDITOR CONFIGURATION
        ======================================================= */

        /* ---------- Custom Upload Adapter ---------- */
        class MyUploadAdapter {

            constructor(loader) {
                this.loader = loader;
            }

            upload() {

                return this.loader.file
                    .then(file => new Promise((resolve, reject) => {

                        const data = new FormData();

                        data.append('upload', file);

                        fetch('../ajax/wacana/upload_editor_image.php', {
                            method: 'POST',
                            body: data
                        })

                        .then(response => response.json())

                        .then(result => {

                            if (result.error) {
                                reject(result.error.message);
                                return;
                            }

                            resolve({
                                default: result.url
                            });

                        })

                        .catch(error => {
                            reject('Upload gagal.');
                        });

                    }));
            }

            abort() {}
        }

        /* ---------- Upload Adapter Plugin ---------- */
        function MyCustomUploadAdapterPlugin(editor) {

            editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
                return new MyUploadAdapter(loader);
            };

        }

    const {
        ClassicEditor,
        Essentials,
        Paragraph,
        Heading,
        Bold,
        Italic,
        Underline,
        Strikethrough,
        Link,
        List,
        BlockQuote,
        Table,
        TableToolbar,
        ImageUpload,
        ImageBlock,
        ImageInline,
        ImageToolbar,
        ImageCaption,
        ImageStyle,
        ImageTextAlternative,
        FontSize,
        FontColor,
        FontBackgroundColor,
        Highlight,
        Alignment,
        Subscript,
        Superscript,
        HorizontalLine,
        RemoveFormat
    } = window.CKEDITOR;

        /* ---------- Editor Initialization ---------- */
        ClassicEditor
            .create(document.querySelector('#wacanaEditor'), {

            licenseKey: 'GPL',

            plugins: [
                Essentials,
                Paragraph,
                Heading,
                Bold,
                Italic,
                Underline,
                Strikethrough,
                Link,
                List,
                BlockQuote,
                Table,
                TableToolbar,
                ImageUpload,
                ImageBlock,
                ImageInline,
                ImageToolbar,
                ImageCaption,
                ImageStyle,
                ImageTextAlternative,
                FontSize,
                FontColor,
                FontBackgroundColor,
                Highlight,
                Alignment,
                Subscript,
                Superscript,
                HorizontalLine,
                RemoveFormat
            ],

            extraPlugins: [ MyCustomUploadAdapterPlugin ],

            toolbar: {
                items: [
                    'undo',
                    'redo',
                    '|',
                    'heading',
                    '|',
                    'bold',
                    'italic',
                    'underline',
                    'strikethrough',
                    'subscript',
                    'superscript',
                    '|',
                    'fontSize',
                    'fontColor',
                    'fontBackgroundColor',
                    'highlight',
                    '|',
                    'alignment',
                    '|',
                    'bulletedList',
                    'numberedList',
                    '|',
                    'link',
                    'insertTable',
                    'blockQuote',
                    'horizontalLine',
                    '|',
                    'imageUpload',
                    '|',
                    'removeFormat'
                ]
            },

            list: {
                properties: {
                    styles: true,
                    startIndex: true,
                    reversed: true
                }
            },

            image: {
                toolbar: [
                    'imageTextAlternative',
                    'toggleImageCaption',
                    '|',
                    'imageStyle:inline',
                    'imageStyle:block',
                    'imageStyle:side'
                ]
            },

            table: {
                contentToolbar: [
                    'tableColumn',
                    'tableRow',
                    'mergeTableCells'
                ]
            }

        })
        .then(editor => {
            wacanaEditor = editor;
            window.wacanaEditor = editor;

            const editable = editor.ui.view.editable.element;

            editable.classList.add(
                "px-8"
            );

            function fixListIndent() {
                editable.querySelectorAll("ul, ol").forEach(list => {
                    list.style.paddingLeft = "2.5rem";
                    list.style.marginLeft = "0";
                    list.style.listStylePosition = "outside";
                });

                editable.querySelectorAll("li").forEach(item => {
                    item.style.paddingLeft = "0.25rem";
                });
            }

            fixListIndent();

            editor.model.document.on("change:data", () => {
                setTimeout(fixListIndent, 0);
            });
        })
        .catch(error => {
            console.error(error);
        });

    const insertEquationBtn =
        document.getElementById("insertEquationBtn");

    if (insertEquationBtn) {

        insertEquationBtn.addEventListener("click", () => {

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

                        <!-- HEADER -->
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

                        <!-- BODY -->
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

                        <!-- FOOTER -->
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

        });

    }

        const visibilityInput =
            form.querySelector(
                '[name="visibility_scope"]'
            );

        const statusInput =
            form.querySelector(
                '[name="status"]'
            );

        const sharedAccessPanel =
        document.getElementById("sharedAccessPanel");

    const shareSubjectFilter =
        document.getElementById("shareSubjectFilter");

    const shareGradeFilter =
        document.getElementById("shareGradeFilter");

    const mainSubjectSelect =
        form.querySelector('select[name="subject_id"]');

    const shareTeacherList =
        document.getElementById("shareTeacherList");

    const shareClassList =
        document.getElementById("shareClassList");

    const selectAllTeachersBtn =
        document.getElementById("selectAllTeachersBtn");

    const resetTeachersBtn =
        document.getElementById("resetTeachersBtn");

    const teacherBulkControls =
        document.getElementById("teacherBulkControls");

    function toggleSharedAccessPanel() {

        if (!sharedAccessPanel || !visibilityInput) return;

        if (visibilityInput.value === "2") {
            sharedAccessPanel.classList.remove("hidden");
            syncShareSubjectWithMainSubject();
        } else {
            sharedAccessPanel.classList.add("hidden");

            if (shareSubjectFilter) shareSubjectFilter.value = "";
            if (shareGradeFilter) shareGradeFilter.value = "";

            if (shareTeacherList) {
                shareTeacherList.innerHTML =
                    "Pilih mapel dan tingkat terlebih dahulu.";
            }

            if (shareClassList) {
                shareClassList.innerHTML =
                    "Pilih pengajar untuk melihat kelas yang diampu.";
            }
        }
    }

    function resetSharePanel() {
        if (shareTeacherList) {
            shareTeacherList.innerHTML =
                "Pilih mapel dan tingkat terlebih dahulu.";
        }

        if (shareClassList) {
            shareClassList.innerHTML =
                "Pilih pengajar untuk melihat kelas yang diampu.";
        }

        if (teacherBulkControls) {
            teacherBulkControls.classList.add("hidden");
        }

        if (selectAllTeachersBtn) {
            selectAllTeachersBtn.checked = false;
        }
    }

    function syncShareSubjectWithMainSubject() {

        if (!mainSubjectSelect || !shareSubjectFilter) return;

        shareSubjectFilter.value = mainSubjectSelect.value || "";

        if (shareSubjectFilter.value === "") {
            resetSharePanel();
            return;
        }

        loadShareTeachers();
    }

    function loadShareTeachers() {

        if (!shareSubjectFilter || !shareGradeFilter || !shareTeacherList || !shareClassList) return;

        const subjectId = shareSubjectFilter.value;
        const gradeLevel = shareGradeFilter.value;

        shareClassList.innerHTML =
            "Pilih pengajar untuk melihat kelas yang diampu.";

        if (!subjectId || !gradeLevel) {
            resetSharePanel();
            return;
        }

        shareTeacherList.innerHTML =
            "Memuat daftar pengajar...";

        fetch(`../ajax/wacana/get_wacana.php?action=share_teachers&subject_id=${encodeURIComponent(subjectId)}&grade_level=${encodeURIComponent(gradeLevel)}`)
            .then(res => res.json())
            .then(res => {

                if (res.status !== 1 || !Array.isArray(res.data) || res.data.length === 0) {
                    shareTeacherList.innerHTML =
                        "Tidak ada pengajar untuk mapel dan tingkat ini.";
                    return;
                }

                shareTeacherList.innerHTML = res.data.map(teacher => `
                    <label class="flex items-center gap-2 py-1 cursor-pointer">
                        <input
                            type="checkbox"
                            name="teacher_ids[]"
                            value="${teacher.teacher_id}"
                            class="rounded border-gray-300"
                            onchange="clearTeacherAccessError(); updateTeacherBulkState(); loadTeacherClasses();"
                        >
                        <span>${teacher.teacher_name}</span>
                    </label>
                `).join("");

                if (teacherBulkControls) {
                    teacherBulkControls.classList.remove("hidden");
                }

                if (selectAllTeachersBtn) {
                    selectAllTeachersBtn.checked = false;
                }

            })
            .catch(() => {
                shareTeacherList.innerHTML =
                    "Gagal memuat data pengajar.";
            });
    }

    function prepareSharedAccessEdit(data) {

        if (!data || parseInt(data.visibility_scope) !== 2) return;

        if (shareSubjectFilter) {
            shareSubjectFilter.value = data.subject_id || "";
        }

        if (shareGradeFilter) {
            if (data.selected_grade_level) {
                shareGradeFilter.value = data.selected_grade_level;
            } else if (
                Array.isArray(data.selected_grade_levels) &&
                data.selected_grade_levels.length > 0
            ) {
                shareGradeFilter.value = data.selected_grade_levels[0];
            }
        }

        loadShareTeachers();

        setTimeout(() => {
            const selectedTeacherIds = (data.selected_teacher_ids || []).map(String);

            document
                .querySelectorAll('input[name="teacher_ids[]"]')
                .forEach(input => {
                    input.checked = selectedTeacherIds.includes(input.value);
                });

            updateTeacherBulkState();
            loadTeacherClasses();
        }, 700);
    }

    window.prepareSharedAccessEdit = prepareSharedAccessEdit;

    function loadTeacherClasses() {

        if (!shareClassList) return;

        const checkedTeachers = [
            ...document.querySelectorAll('input[name="teacher_ids[]"]:checked')
        ].map(input => ({
            id: input.value,
            name: input.closest("label")?.querySelector("span")?.textContent?.trim() || "Pengajar"
        }));

        if (checkedTeachers.length === 0) {
            shareClassList.innerHTML =
                "Pilih pengajar untuk melihat kelas yang diampu.";
            return;
        }

        shareClassList.innerHTML = "Memuat kelas...";

        Promise.all(
            checkedTeachers.map(teacher =>
                fetch(
                    `../ajax/wacana/get_wacana.php?action=teacher_classes`
                    + `&teacher_id=${teacher.id}`
                    + `&grade_level=${encodeURIComponent(shareGradeFilter.value)}`
                )
                    .then(res => res.json())
                    .then(res => ({
                        teacher,
                        response: res
                    }))
            )
        )
        .then(results => {

            shareClassList.innerHTML = results.map(item => {

                const teacher = item.teacher;
                const res = item.response;

                if (res.status !== 1 || !Array.isArray(res.data) || res.data.length === 0) {
                    return `
                        <div class="mb-3 last:mb-0">
                            <div class="font-semibold text-gray-700 mb-1">
                                ${teacher.name}
                            </div>
                            <div class="text-xs text-gray-500">
                                Belum memiliki kelas.
                            </div>
                        </div>
                    `;
                }

                const classesHtml = res.data.map(cls => `
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium mr-2 mb-2">
                        ${cls.class_name}
                    </span>
                `).join("");

                return `
                    <div class="mb-4 last:mb-0">
                        <div class="font-semibold text-gray-700 mb-2">
                            ${teacher.name}
                        </div>
                        <div>
                            ${classesHtml}
                        </div>
                    </div>
                `;

            }).join("");

        })
        .catch(() => {
            shareClassList.innerHTML =
                "Gagal memuat kelas.";
        });
    }

    function updateTeacherBulkState() {
        const teacherCheckboxes = [
            ...document.querySelectorAll('input[name="teacher_ids[]"]')
        ];

        if (!selectAllTeachersBtn || teacherCheckboxes.length === 0) return;

        selectAllTeachersBtn.checked =
            teacherCheckboxes.every(input => input.checked);
    }

    window.updateTeacherBulkState = updateTeacherBulkState;

    if (shareSubjectFilter) {
        shareSubjectFilter.addEventListener("change", loadShareTeachers);
    }

    if (shareGradeFilter) {
        shareGradeFilter.addEventListener("change", loadShareTeachers);
    }

    if (mainSubjectSelect) {
        mainSubjectSelect.addEventListener("change", () => {

            if (shareSubjectFilter) {
                shareSubjectFilter.value = mainSubjectSelect.value || "";
            }

            loadShareTeachers();

        });
    }

    window.loadTeacherClasses = loadTeacherClasses;

    if (selectAllTeachersBtn) {
        selectAllTeachersBtn.addEventListener("change", () => {
            const teacherCheckboxes = [
                ...document.querySelectorAll('input[name="teacher_ids[]"]')
            ];

            teacherCheckboxes.forEach(input => {
                input.checked = selectAllTeachersBtn.checked;
            });

            loadTeacherClasses();
        });
    }

    if (resetTeachersBtn) {
        resetTeachersBtn.addEventListener("click", () => {
            const teacherCheckboxes = [
                ...document.querySelectorAll('input[name="teacher_ids[]"]')
            ];

            teacherCheckboxes.forEach(input => {
                input.checked = false;
            });

            if (selectAllTeachersBtn) {
                selectAllTeachersBtn.checked = false;
            }

            if (shareClassList) {
                shareClassList.innerHTML =
                    "Pilih pengajar untuk melihat kelas yang diampu.";
            }
        });
    }

        /* =======================================================
            OPEN MODAL
        ======================================================= */
        function openWacanaModal() {

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            document.body.classList.add("overflow-hidden");

            form.reset();

            if (window.wacanaEditor) {
                window.wacanaEditor.setData("");
            }

            clearInlineErrors();

            form.querySelector('[name="id"]').value = "";

            modalTitle.textContent =
                "Tambah Wacana Baru";

            setTimeout(() => {
                titleInput.focus();
            }, 100);

    const visibilityField = form.querySelector('[name="visibility_scope"]');

    if (visibilityField) {
        visibilityField.value = "1";
        visibilityField.dispatchEvent(new Event("change"));
    }

            form.querySelector('[name="status"]').value =
                "1";

        }

        /* =======================================================
            CLOSE MODAL
        ======================================================= */
        function closeWacanaModal() {

            modal.classList.add("hidden");
            modal.classList.remove("flex");

            document.body.classList.remove("overflow-hidden");

            clearInlineErrors();

        }

        /* =======================================================
            MODAL CONTROL
        ======================================================= */
        if (openBtn) {
            openBtn.addEventListener(
                "click",
                openWacanaModal
            );
        }

        if (closeBtn) {
            closeBtn.addEventListener(
                "click",
                closeWacanaModal
            );
        }

        if (cancelBtn) {
            cancelBtn.addEventListener(
                "click",
                closeWacanaModal
            );
        }

        modal.addEventListener(
            "click",
            e => {

                if (e.target === modal) {
                    closeWacanaModal();
                }

            }
        );

        document.addEventListener(
            "keydown",
            e => {

                if (
                    e.key === "Escape" &&
                    !modal.classList.contains("hidden")
                ) {
                    closeWacanaModal();
                }

            }
        );

        /* =======================================================
            TABLE STATE
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

    function saveState() {
        sessionStorage.setItem(STORAGE_KEYS.search, searchInput.value);
        sessionStorage.setItem(STORAGE_KEYS.rows, rowsPerPage.value);
        sessionStorage.setItem(STORAGE_KEYS.page, currentPage);
    }

        /* =======================================================
            PAGINATION BUTTON
        ======================================================= */
        function createPaginationButton(
            label,
            disabled,
            onClick,
            active = false
        ) {

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

            return btn;

        }

        /* =======================================================
            RENDER PAGINATION
        ======================================================= */
        function renderPagination(totalPages) {

            pagination.innerHTML = "";

            if (
                rowsPerPage.value === "all" ||
                totalPages <= 1
            ) return;

            pagination.appendChild(
                createPaginationButton(
                    "Sebelumnya",
                    currentPage === 1,
                    () => {
                        currentPage--;
                        renderTable();
                    }
                )
            );

            for (let i = 1; i <= totalPages; i++) {

                pagination.appendChild(
                    createPaginationButton(
                        i,
                        false,
                        () => {
                            currentPage = i;
                            renderTable();
                        },
                        currentPage === i
                    )
                );

            }

            pagination.appendChild(
                createPaginationButton(
                    "Selanjutnya",
                    currentPage === totalPages,
                    () => {
                        currentPage++;
                        renderTable();
                    }
                )
            );

        }

        /* =======================================================
            RENDER TABLE
        ======================================================= */
        function renderTable() {

            rows.forEach(row => {
                row.style.display = "none";
            });

            if (rows.length === 0) {

                emptyRow.classList.remove(
                    "hidden"
                );

                noResult.classList.add(
                    "hidden"
                );

                pageInfo.textContent =
                    "0 data";

                pagination.innerHTML = "";

                return;
            }

            if (filteredRows.length === 0) {

                emptyRow.classList.add(
                    "hidden"
                );

                noResult.classList.remove(
                    "hidden"
                );

                pageInfo.textContent =
                    "0 data ditemukan";

                pagination.innerHTML = "";

                return;
            }

            emptyRow.classList.add(
                "hidden"
            );

            noResult.classList.add(
                "hidden"
            );

            const totalPages =
                rowsPerPage.value === "all"
                    ? 1
                    : Math.max(
                        Math.ceil(
                            filteredRows.length /
                            perPage
                        ),
                        1
                    );

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            const start =
                (currentPage - 1) * perPage;

            const end =
                rowsPerPage.value === "all"
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
                    : `Menampilkan ${start + 1} - ${Math.min(end, filteredRows.length)} dari ${filteredRows.length} data`;

            renderPagination(totalPages);

        }

        /* =======================================================
            SEARCH
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
    saveState();

            }
        );

        /* =======================================================
            ROW LIMIT
        ======================================================= */
        rowsPerPage.addEventListener(
            "change",
            () => {

                perPage =
                    rowsPerPage.value === "all"
                        ? Math.max(
                            filteredRows.length,
                            1
                        )
                        : parseInt(
                            rowsPerPage.value
                        );

                currentPage = 1;

                renderTable();
    saveState();

            }
        );

        if (visibilityInput) {
            visibilityInput.addEventListener("change", toggleSharedAccessPanel);
        }

    /* =======================================================
        FORM SUBMIT
    ======================================================= */
    form.addEventListener("submit", e => {

        e.preventDefault();

        clearInlineErrors();
        clearTeacherAccessError();

        const subjectInput =
            form.querySelector('[name="subject_id"]');

        const visibilityInput =
            form.querySelector('[name="visibility_scope"]');

        const title =
            titleInput.value.trim();

        const subjectValue =
            subjectInput ? subjectInput.value.trim() : "";

        const description =
            window.wacanaEditor
                ? window.wacanaEditor.getData().trim()
                : descInput.value.trim();

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
            }, 350);
        };

        /* ---------- 1. Judul Wacana ---------- */
        if (title === "") {

            setInlineError(
                titleInput,
                "Judul wacana wajib diisi."
            );

            scrollToField(titleInput);
            return;
        }

        /* ---------- 2. Mata Pelajaran ---------- */
        if (subjectInput && subjectValue === "") {

            setInlineError(
                subjectInput,
                "Mata pelajaran wajib dipilih."
            );

            scrollToField(subjectInput);
            return;
        }

        /* ---------- 3. Isi Wacana ---------- */
        if (
            description === "" ||
            description === "<p>&nbsp;</p>" ||
            description === "<p></p>"
        ) {

            const editorContainer =
                document.querySelector(".ck-editor");

            setInlineError(
                editorContainer,
                "Isi wacana wajib diisi."
            );

            scrollToField(editorContainer);
            return;
        }

        /* ---------- 4. Akses Dibagikan: Tingkat ---------- */
        if (
            visibilityInput &&
            visibilityInput.value === "2" &&
            shareGradeFilter &&
            shareGradeFilter.value.trim() === ""
        ) {

            setInlineError(
                shareGradeFilter,
                "Tingkat wajib dipilih."
            );

            scrollToField(shareGradeFilter);
            return;
        }

        /* ---------- 5. Akses Dibagikan: Pengajar ---------- */
        const checkedTeachers =
            form.querySelectorAll('input[name="teacher_ids[]"]:checked');

        if (
            visibilityInput &&
            visibilityInput.value === "2" &&
            checkedTeachers.length === 0
        ) {

            setTeacherAccessError(
                "Pilih minimal satu pengajar penerima akses."
            );

            scrollToField(
                document.getElementById("shareTeacherList")
            );

            return;
        }

        /* ---------- Sync CKEditor ke textarea ---------- */
        if (window.wacanaEditor) {
            descInput.value =
                window.wacanaEditor.getData();
        }

        const formData =
            new FormData(form);

        showLoading("Menyimpan data...");

        fetch("../ajax/wacana/save_wacana.php", {
            method: "POST",
            body: formData
        })

        .then(res => res.json())

        .then(res => {

            Swal.close();

            if (res.status == 1) {

                closeWacanaModal();

                showToast(
                    "success",
                    res.msg
                );

                setTimeout(() => {
                    location.reload();
                }, 1200);

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
        AUTO FOCUS QUESTION FROM URL
    ======================================================= */
    const urlParams = new URLSearchParams(window.location.search);
    const targetQuestionId = urlParams.get("question_id");

    if (targetQuestionId) {

        const targetRow = document.querySelector(
            `tr[data-id="${targetQuestionId}"]`
        );

        if (targetRow) {

            rowsPerPage.value = "all";
            filteredRows = [...questionRows];
            renderTable();

            setTimeout(() => {

                targetRow.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });

                targetRow.classList.add(
                    "bg-yellow-100",
                    "ring-2",
                    "ring-yellow-400"
                );

                setTimeout(() => {
                    targetRow.classList.remove(
                        "bg-yellow-100",
                        "ring-2",
                        "ring-yellow-400"
                    );
                }, 4000);

            }, 300);

        }

    }

    /* =======================================================
        INITIAL RENDER
    ======================================================= */
    renderTable();

    toggleSharedAccessPanel();

    });
    </script>

</body>
</html>