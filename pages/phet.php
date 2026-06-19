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
        SELECT
            id,
            subject_id
        FROM teachers
        WHERE user_id = {$userId}
        ROW LIMIT HANDLER 1
    ");

    if ($teacherDataQuery && $teacherDataQuery->num_rows > 0) {

        $teacherData = $teacherDataQuery->fetch_assoc();

        $teacherId        = (int) $teacherData['id'];
        $teacherSubjectId = (int) $teacherData['subject_id'];

    }

}

/* =======================================================
    PHET FILTER
======================================================= */
$phetWhereClause = "";

if ($isTeacher) {

    $phetWhereClause = "
        WHERE
        (
            p.user_id = {$userId}
            OR p.visibility_scope = 3
            OR EXISTS (
                SELECT 1
                FROM phet_teacher_access pta
                WHERE pta.phet_id = p.id
                AND pta.teacher_id = {$teacherId}
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
        ROW LIMIT HANDLER 1
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
    PHET STATISTICS
======================================================= */

/* ---------- Total PhET ---------- */
$totalPhet = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM phet p
    {$phetWhereClause}
")->fetch_assoc()['total'];

/* ---------- Used PhET ---------- */
$usedPhet = (int) $conn->query("
    SELECT COUNT(DISTINCT p.id) AS total
    FROM phet p
    INNER JOIN questions q
        ON p.id = q.phet_id
    {$phetWhereClause}
")->fetch_assoc()['total'];

/* ---------- Unused PhET ---------- */
$unusedPhet = max($totalPhet - $usedPhet, 0);

/* ---------- Public PhET ---------- */
$publicPhet = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM phet p
    {$phetWhereClause}
    " . ($phetWhereClause ? " AND " : " WHERE ") . "
    p.visibility_scope = 3
")->fetch_assoc()['total'];

/* =======================================================
    MAIN PHET QUERY
======================================================= */
$phetQuery = $conn->query("
    SELECT
        p.*,
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

    FROM phet p

    LEFT JOIN subjects s
        ON p.subject_id = s.id

    LEFT JOIN users u
        ON p.user_id = u.id

    LEFT JOIN questions q
        ON p.id = q.phet_id

    LEFT JOIN quiz_list ql
        ON q.quiz_id = ql.id

    {$phetWhereClause}

    GROUP BY p.id

    ORDER BY
        p.created_at DESC
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
$pageTitle = "PhET | Takar-Edu";

/* =======================================================
    PAGE LAYOUT
======================================================= */
$phetColspan = $isAdmin ? 9 : 8;
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
                            Manajemen PhET
                        </h1>

                        <p class="page-description">

                            <?php if ($isAdmin): ?>
                                Kelola seluruh bank simulasi PhET sebagai media eksperimen virtual, asesmen interaktif, dan sumber pembelajaran digital.

                            <?php else: ?>
                                Kelola simulasi PhET pribadi, mapel terkait, dan akses eksperimen virtual Anda.

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

                    <!-- Total PhET -->
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-600">
                            <i data-lucide="atom" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total PhET</p>
                            <h3 class="stat-value">
                                <?= $totalPhet ?>
                            </h3>
                            <p class="stat-label">Simulasi tersimpan</p>
                        </div>
                    </div>

                    <!-- Used PhET -->
                    <div class="stat-card">
                        <div class="stat-icon bg-green-600">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Digunakan</p>
                            <h3 class="stat-value">
                                <?= $usedPhet ?>
                            </h3>
                            <p class="stat-label">Sudah terpakai</p>
                        </div>
                    </div>

                    <!-- Unused PhET -->
                    <div class="stat-card">
                        <div class="stat-icon bg-yellow-600">
                            <i data-lucide="archive" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Belum Digunakan</p>
                            <h3 class="stat-value">
                                <?= $unusedPhet ?>
                            </h3>
                            <p class="stat-label">Siap digunakan</p>
                        </div>
                    </div>

                    <!-- Public PhET -->
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-600">
                            <i data-lucide="globe" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="stat-label">Publik</p>
                            <h3 class="stat-value">
                                <?= $publicPhet ?>
                            </h3>
                            <p class="stat-label">Dapat dibagikan</p>
                        </div>
                    </div>

                </div>

            </section>

            <!-- =======================================================
                PHET TABLE SECTION
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Section Header ---------- -->
                <div class="section-header">

                    <!-- Section title -->
                    <h2 class="section-title">
                        Bank PhET
                    </h2>

                    <!-- Add PhET button -->
                    <button
                        id="newPhet"
                        type="button"
                        class="form-btn form-btn-primary"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah PhET
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
                            placeholder="Cari PhET..."
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
                                <th class="table-th w-[15%]">Judul</th>
                                <th class="table-th w-[12%]">Mapel</th>
                                <th class="table-th w-[17%]">Deskripsi</th>
                                <th class="table-th w-[23%]">Preview</th>
                                <th class="table-th w-[10%]">Akses</th>
                                <th class="table-th w-[10%]">Digunakan</th>
                                <th class="table-th w-[8%]">Status</th>

                                <?php if ($isAdmin): ?>
                                    <th class="table-th w-[15%]">Aksi</th>
                                <?php endif; ?>
                            </tr>

                        </thead>

                        <!-- ---------- Table Body ---------- -->
                        <tbody class="bg-white">

                            <?php if ($phetQuery && $phetQuery->num_rows > 0): ?>

                                <?php $no = 1; ?>

                                <?php while ($row = $phetQuery->fetch_assoc()): ?>

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

                                        <!-- PhET title -->
                                        <td class="table-td">

                                            <div class="font-semibold text-gray-800">
                                                <?= htmlspecialchars($row['phet_title']) ?>
                                            </div>

                                            <div class="text-xs text-gray-500 mt-1">
                                                Oleh: <?= htmlspecialchars($row['creator_name']) ?>
                                            </div>

                                        </td>

                                        <!-- Subject -->
                                        <td class="table-td text-center">
                                            <?= !empty($row['subject_name'])
                                                ? htmlspecialchars($row['subject_name'])
                                                : '-' ?>
                                        </td>

                                        <!-- Description -->
                                        <td class="table-td">
                                            <div
                                                class="text-justify leading-relaxed max-w-xs mx-auto break-words"
                                                title="<?= htmlspecialchars($row['description']) ?>"
                                            >
                                                <?= !empty($row['description'])
                                                    ? htmlspecialchars(mb_strimwidth($row['description'], 0, 50, '...'))
                                                    : '<span class="text-gray-400 italic">Tidak ada deskripsi</span>' ?>
                                            </div>
                                        </td>

                                        <!-- Preview -->
                                        <td class="table-td text-center">

                                            <?php
                                            $thumbnailUrl = '';

                                            if (!empty($row['original_url'])) {

                                                if (preg_match('/\/simulations\/([^\/]+)/', $row['original_url'], $matches)) {

                                                    $slug = $matches[1];

                                                    $thumbnailUrl = "https://phet.colorado.edu/sims/html/{$slug}/latest/{$slug}-900.png";

                                                }

                                            }
                                            ?>

                                            <?php if (!empty($thumbnailUrl)): ?>

                                                <div
                                                    onclick="previewPhet(`<?= htmlspecialchars($row['iframe_phet'], ENT_QUOTES) ?>`)"
                                                    class="preview-card group w-full max-w-[300px] mx-auto"
                                                >

                                                    <!-- Thumbnail -->
                                                    <img
                                                        src="<?= htmlspecialchars($thumbnailUrl) ?>"
                                                        alt="<?= htmlspecialchars($row['phet_title']) ?>"
                                                        class="w-full h-auto object-cover"
                                                        loading="lazy"
                                                        onerror="this.src='../assets/img/no-preview.png'"
                                                    >

                                                    <!-- Preview Overlay -->
                                                    <div class="absolute inset-0 bg-black/35 opacity-0 group-hover:opacity-100 flex items-center justify-center transition">

                                                        <span class="inline-flex items-center gap-1 text-white text-xs font-semibold text-sm">
                                                            <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                                            Lihat Simulasi
                                                        </span>

                                                    </div>

                                                </div>

                                            <?php else: ?>

                                                <span class="status-badge status-secondary">
                                                    Tidak Ada
                                                </span>

                                            <?php endif; ?>

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
                                        <?php if ($isAdmin): ?>

                                            <td class="table-td text-center">

                                                <div class="action-group">

                                                <?php if ($canManageRow): ?>

                                                    <!-- Edit button -->
                                                    <button
                                                        type="button"
                                                        onclick="editPhet(<?= (int)$row['id'] ?>)"
                                                        class="action-btn action-info"
                                                    >
                                                        <i data-lucide="square-pen" class="w-4 h-4"></i>
                                                        <span class="action-label">Edit</span>
                                                    </button>

                                                    <!-- Status button -->
                                                    <?php $isActive = (int)$row['status'] === 1; ?>

                                                    <button
                                                        type="button"
                                                        onclick="toggleStatusPhet(<?= (int)$row['id'] ?>, <?= (int)$row['status'] ?>)"
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
                                                        onclick="deletePhet(<?= (int)$row['id'] ?>)"
                                                        class="action-btn action-danger"
                                                    >
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        <span class="action-label">Hapus</span>
                                                    </button>

                                                    <?php endif; ?>

                                                </div>

                                            </td>

                                        <?php endif; ?>

                                    </tr>

                                <?php endwhile; ?>

                            <?php endif; ?>

                            <!-- Empty database state -->
                            <tr
                                id="emptyRow"
                                class="<?= ($phetQuery && $phetQuery->num_rows > 0) ? 'hidden' : '' ?>"
                            >
                                <td colspan="<?= $phetColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="atom" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Belum ada PhET tersedia
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <!-- Search empty state -->
                            <tr id="noResult" class="hidden">
                                <td colspan="<?= $phetColspan ?>" class="empty-state-cell">
                                    <div class="empty-state">

                                        <i data-lucide="search-x" class="empty-state-icon"></i>

                                        <span class="empty-state-title">
                                            Tidak ada PhET yang sesuai dengan pencarian
                                        </span>

                                    </div>
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

                <!-- =======================================================
                    TABLE FOOTER
                ======================================================= -->
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
        PHET MODAL SECTION
    ======================================================= -->
    <div id="phetModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">

                <h3 id="modalTitle" class="modal-title">
                    Tambah PhET Baru
                </h3>

                <button
                    id="closeModalPhet"
                    type="button"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

            </div>

            <!-- ---------- PhET Wacana ---------- -->
            <form id="phetForm" class="modal-form">

                <div class="global-modal-body">

                    <!-- Hidden id -->
                    <input type="hidden" name="id">
                    <input type="hidden" name="creator_role" value="<?= $userType ?>">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    <input type="hidden" name="status" value="1">

                    <!-- PhET title -->
                    <div>

                        <label class="form-label">
                            Judul PhET
                        </label>

                        <input
                            type="text"
                            name="phet_title"
                            placeholder="Masukkan judul simulasi"
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

                                        <option value="<?= $subject['id'] ?>">
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

                    <!-- Description -->
                    <div>

                        <label class="form-label">
                            Deskripsi Simulasi
                        </label>

                        <textarea
                            name="description"
                            rows="5"
                            placeholder="Masukkan deskripsi simulasi (opsional)..."
                            class="form-textarea"
                        ></textarea>

                    </div>

                    <!-- Original URL -->
                    <div>

                        <label class="form-label">
                            URL PhET
                        </label>

                        <input
                            type="text"
                            name="original_url"
                            placeholder="https://phet.colorado.edu/..."
                            class="form-input"
                        >

                    </div>

                    <!-- Live Preview -->
                    <div
                        id="iframePreviewContainer"
                        class="hidden"
                    >

                        <label class="form-label">
                            Preview Simulasi
                        </label>

                        <div class="border border-gray-300 rounded-2xl overflow-hidden bg-gray-50">

                            <div
                                id="iframePreview"
                                class="w-full min-h-[400px]"
                            ></div>

                        </div>

                    </div>

                    <!-- Visibility scope -->
                    <?php if ($isAdmin): ?>

                        <div>

                            <label class="form-label">
                                Akses PhET
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
                                    Pengaturan Penerima PhET
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

                                                <option value="<?= $subject['id'] ?>">
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

                            <!-- Teacher selection -->
                            <div>
                                <label class="form-label">
                                    Pengajar
                                </label>

                                <div
                                    id="teacherBulkControls"
                                    class="hidden flex items-center justify-between mb-1"
                                >

                                    <!-- Checkbox all select -->
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

                                <!-- Select teachers -->
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
                        id="cancelModalPhet"
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

    <!-- =======================================================
        PHET PREVIEW MODAL
    ======================================================= -->
    <div
        id="previewModal"
        class="hidden fixed inset-0 z-[3000] bg-black/70 backdrop-blur-sm flex items-start justify-center p-4"
    >

        <div
            class="relative w-full max-w-7xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
            style="height: calc(100vh - 40px);"
        >

            <!-- ---------- Preview Header ---------- -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 bg-white shrink-0">

                <h3 class="text-lg font-semibold text-gray-800">
                    Preview PhET
                </h3>

                <button
                    id="closePreviewModal"
                    type="button"
                    class="text-gray-600 hover:text-black bg-gray-100 hover:bg-gray-200 rounded-full p-2 transition"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>

            </div>

            <!-- ---------- Preview Content ---------- -->
            <div
                id="previewIframeContainer"
                class="flex-1 bg-black overflow-hidden"
            ></div>

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
            width: isMobile ? "90%" : "34rem",
            padding: isMobile ? "1.25rem" : "2rem",
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
        PHET PREVIEW
    ======================================================= */
function previewPhet(iframeCode) {

    const modal =
        document.getElementById("previewModal");

    const container =
        document.getElementById(
            "previewIframeContainer"
        );

    container.innerHTML = iframeCode;

    const iframe =
        container.querySelector("iframe");

    if (iframe) {

        iframe.style.width = "100%";
        iframe.style.height = "100%";
        iframe.style.border = "none";

    }

    modal.classList.remove("hidden");

}

    /* =======================================================
        PHET USAGE DETAIL
    ======================================================= */
    function showUsageDetail(detail) {

        if (!detail || detail.trim() === "") {

            Swal.fire(
                getResponsiveSwal(
                    "info",
                    "Belum Digunakan",
                    "PhET ini belum digunakan dalam soal manapun."
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
                        type="button"
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
                <ul class="text-left list-none space-y-3">
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

    function goToQuestion(quizId, questionId) {

        window.location.href =
            `quiz_view.php?id=${quizId}&question_id=${questionId}`;

    }

    /* =======================================================
        PHET DELETE ACTION
    ======================================================= */
    function deletePhet(id) {

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                "Hapus PhET?",
                "Data yang dihapus tidak dapat dikembalikan."
            ),
            showCancelButton: true,
            confirmButtonText: "Ya, hapus",
            cancelButtonText: "Batal"
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Menghapus data...");

            fetch(`../ajax/phet/delete_phet.php?id=${id}`)

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

                            if (result.isConfirmed && res.usage_detail) {
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

    /* ---------- Toggle PhET Status ---------- */
    function toggleStatusPhet(id, currentStatus) {

        const newStatus = currentStatus === 1 ? 0 : 1;
        const actionText = currentStatus === 1 ? "arsipkan" : "aktifkan";

        Swal.fire({
            ...getResponsiveSwal(
                "warning",
                `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} wacana?`,
                currentStatus === 1
                    ? "PhET akan disembunyikan dari penggunaan aktif."
                    : "PhET akan tersedia kembali."
            ),
            showCancelButton: true,
            confirmButtonText: `Ya, ${actionText}`,
            cancelButtonText: "Batal"
        })

        .then(result => {

            if (!result.isConfirmed) return;

            showLoading("Memproses...");

            fetch("../ajax/phet/toggle_status_phet.php", {
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
    PHET URL HELPERS
======================================================= */
function convertPhetUrl(url) {
    try {
        if (!url.includes("phet.colorado.edu")) return "";

        const match = url.match(/\/simulations\/([^\/]+)/);

        if (!match || !match[1]) return "";

        const slug = match[1];

        return `https://phet.colorado.edu/sims/html/${slug}/latest/${slug}_en.html`;

    } catch (e) {
        return "";
    }
}

function generatePhetIframe(url) {
    const embedUrl = convertPhetUrl(url);

    if (!embedUrl) return "";

    return `<iframe src="${embedUrl}"
        width="100%"
        height="600"
        allowfullscreen
        loading="lazy"
        style="border:none;"></iframe>`;
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
    const form = document.getElementById("phetForm");
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

function setTeacherAccessError(message) {
    const teacherList = document.getElementById("shareTeacherList");
    if (!teacherList) return;

    teacherList.classList.add("border-red-500", "bg-red-50");
    teacherList.classList.remove("border-gray-200", "bg-white");

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
        teacherList.classList.remove("border-red-500", "bg-red-50");
        teacherList.classList.add("border-gray-200", "bg-white");
    }

    document.getElementById("teacherAccessError")?.remove();
}

function attachLiveValidation(input) {
    if (!input) return;

    input.addEventListener("input", () => clearFieldError(input));
    input.addEventListener("change", () => clearFieldError(input));
}

    /* =======================================================
        PHET EDIT ACTION
    ======================================================= */
    function editPhet(id) {

        showLoading("Memuat data...");

        fetch(`../ajax/phet/get_phet.php?id=${id}`)

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
                    document.getElementById("phetModal");

                const form =
                    document.getElementById("phetForm");

                const modalTitle =
                    document.getElementById("modalTitle");

                const iframePreviewContainer =
                    document.getElementById(
                        "iframePreviewContainer"
                    );

                const iframePreview =
                    document.getElementById(
                        "iframePreview"
                    );

                const data = res.data;

                modal.classList.remove("hidden");
                modal.classList.add("flex");
                modalTitle.textContent = "Edit Data PhET";

                form.querySelector('[name="id"]').value =
                    data.id;

                form.querySelector('[name="phet_title"]').value =
                    data.phet_title;

                /* ---------- Subject ---------- */
const subjectSelect = form.querySelector('select[name="subject_id"]:not([type="hidden"])');
const subjectHidden = form.querySelector('input[type="hidden"][name="subject_id"]');

if (subjectSelect && !subjectSelect.disabled) {
    subjectSelect.value = String(data.subject_id);
}

if (subjectHidden) {
    subjectHidden.value = String(data.subject_id);
}

                /* ---------- Description ---------- */
                form.querySelector(
                    '[name="description"]'
                ).value = data.description || "";

                /* ---------- Original URL ---------- */
                form.querySelector(
                    '[name="original_url"]'
                ).value = data.original_url || "";

                /* ---------- Visibility ---------- */
                const visibilityField = form.querySelector('[name="visibility_scope"]');

                if (visibilityField) {
                    visibilityField.value = data.visibility_scope;
                    visibilityField.dispatchEvent(new Event("change"));
                }

                setTimeout(() => {
                    if (typeof window.prepareSharedAccessEdit === "function") {
                        window.prepareSharedAccessEdit(data);
                    }
                }, 300);

                /* ---------- Status ---------- */
                form.querySelector(
                    '[name="status"]'
                ).value = data.status;

                /* ---------- Live Preview ---------- */
const generatedIframe = generatePhetIframe(data.original_url);

if (generatedIframe) {

    iframePreviewContainer.classList.remove("hidden");
    iframePreview.innerHTML = generatedIframe;

    const iframe = iframePreview.querySelector("iframe");

    if (iframe) {
        iframe.style.width = "100%";
        iframe.style.height = "400px";
        iframe.style.border = "none";
    }

} else {

    iframePreviewContainer.classList.add("hidden");
    iframePreview.innerHTML = "";

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

    document.addEventListener("DOMContentLoaded", () => {

        /* =======================================================
            STORAGE CONFIG
        ======================================================= */
        const STORAGE_KEYS = {
            search: "phet_search_keyword",
            rows: "phet_rows_per_page",
            page: "phet_current_page"
        };

        const navigationType =
            performance.getEntriesByType("navigation")[0]?.type || "navigate";

        const shouldRestore =
            navigationType === "reload";

        if (!shouldRestore) {

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
            PAGE INITIALIZATION
        ======================================================= */
        if (window.lucide) {
            lucide.createIcons();
        }

        /* =======================================================
            ELEMENT REFERENCES
        ======================================================= */
        const table =
            document.getElementById(
                "dataTable"
            );

        if (!table) return;

        const rows = [
            ...table.querySelectorAll(
                "tbody tr"
            )
        ].filter(
            row =>
                row.id !== "emptyRow" &&
                row.id !== "noResult"
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
                "phetModal"
            );

        const form =
            document.getElementById(
                "phetForm"
            );

        const openBtn =
            document.getElementById(
                "newPhet"
            );

        const closeBtn =
            document.getElementById(
                "closeModalPhet"
            );

        const cancelBtn =
            document.getElementById(
                "cancelModalPhet"
            );

        const modalTitle =
            document.getElementById(
                "modalTitle"
            );

const originalUrlInput = form.querySelector('[name="original_url"]');

const titleInput = form.querySelector('[name="phet_title"]');
const subjectInput = form.querySelector('[name="subject_id"]');
const descriptionInput = form.querySelector('[name="description"]');
const visibilityInput = form.querySelector('[name="visibility_scope"]');
const gradeInput = form.querySelector('[name="grade_level"]');

attachLiveValidation(titleInput);
attachLiveValidation(subjectInput);
attachLiveValidation(descriptionInput);
attachLiveValidation(originalUrlInput);
attachLiveValidation(visibilityInput);
attachLiveValidation(gradeInput);

const iframePreviewContainer =
    document.getElementById("iframePreviewContainer");

const iframePreview =
    document.getElementById("iframePreview");


originalUrlInput.addEventListener("input", function() {

    const url = this.value.trim();
    const iframeCode = generatePhetIframe(url);

    if (!iframeCode) {

        iframePreviewContainer.classList.add("hidden");
        iframePreview.innerHTML = "";
        return;

    }

    iframePreviewContainer.classList.remove("hidden");
    iframePreview.innerHTML = iframeCode;

    const iframe = iframePreview.querySelector("iframe");

    if (iframe) {
        iframe.style.width = "100%";
        iframe.style.height = "400px";
        iframe.style.border = "none";
    }

});


        const previewModal =
            document.getElementById(
                "previewModal"
            );

        const previewClose =
            document.getElementById(
                "closePreviewModal"
            );

        const sharedAccessPanel = document.getElementById("sharedAccessPanel");
        const shareSubjectFilter = document.getElementById("shareSubjectFilter");
        const shareGradeFilter = document.getElementById("shareGradeFilter");
        const mainSubjectSelect = form.querySelector('select[name="subject_id"]');
        const shareTeacherList = document.getElementById("shareTeacherList");
        const shareClassList = document.getElementById("shareClassList");
        const selectAllTeachersBtn = document.getElementById("selectAllTeachersBtn");
        const resetTeachersBtn = document.getElementById("resetTeachersBtn");
        const teacherBulkControls = document.getElementById("teacherBulkControls");

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

function toggleSharedAccessPanel() {
    if (!sharedAccessPanel || !visibilityInput) return;

    if (visibilityInput.value === "2") {
        sharedAccessPanel.classList.remove("hidden");
        syncShareSubjectWithMainSubject();
    } else {
        sharedAccessPanel.classList.add("hidden");

        if (shareSubjectFilter) shareSubjectFilter.value = "";
        if (shareGradeFilter) shareGradeFilter.value = "";

        resetSharePanel();
    }
}

function loadShareTeachers() {
    if (
        !shareSubjectFilter ||
        !shareGradeFilter ||
        !shareTeacherList ||
        !shareClassList
    ) {
        return Promise.resolve();
    }

    const subjectId = shareSubjectFilter.value;
    const gradeLevel = shareGradeFilter.value;

    shareClassList.innerHTML =
        "Pilih pengajar untuk melihat kelas yang diampu.";

    if (!subjectId || !gradeLevel) {
        resetSharePanel();
        return Promise.resolve();
    }

    shareTeacherList.innerHTML = "Memuat daftar pengajar...";

    return fetch(`../ajax/phet/get_phet.php?action=share_teachers&subject_id=${encodeURIComponent(subjectId)}&grade_level=${encodeURIComponent(gradeLevel)}`)
        .then(res => res.json())
        .then(res => {
            if (
                res.status !== 1 ||
                !Array.isArray(res.data) ||
                res.data.length === 0
            ) {
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
                        onchange="updateTeacherBulkState(); loadTeacherClasses();"
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
                `../ajax/phet/get_phet.php?action=teacher_classes`
                + `&teacher_id=${encodeURIComponent(teacher.id)}`
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
                    <div>${classesHtml}</div>
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

function prepareSharedAccessEdit(data) {

    if (!data || String(data.visibility_scope) !== "2") {
        toggleSharedAccessPanel();
        return;
    }

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

    if (sharedAccessPanel) {
        sharedAccessPanel.classList.remove("hidden");
    }

    loadShareTeachers().then(() => {

        const selectedTeacherIds =
            (data.selected_teacher_ids || []).map(String);

        document
            .querySelectorAll('input[name="teacher_ids[]"]')
            .forEach(input => {
                input.checked = selectedTeacherIds.includes(input.value);
            });

        updateTeacherBulkState();
        loadTeacherClasses();

    });
}

window.loadTeacherClasses = loadTeacherClasses;
window.updateTeacherBulkState = updateTeacherBulkState;
window.prepareSharedAccessEdit = prepareSharedAccessEdit;
window.toggleSharedAccessPanel = toggleSharedAccessPanel;

if (visibilityInput) {
    visibilityInput.addEventListener("change", toggleSharedAccessPanel);
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
            RESTORE TABLE STATE
        ======================================================= */
        const savedSearch = shouldRestore
            ? sessionStorage.getItem(
                STORAGE_KEYS.search
            ) || ""
            : "";

        const savedRows = shouldRestore
            ? sessionStorage.getItem(
                STORAGE_KEYS.rows
            ) || "10"
            : "10";

        const savedPage = shouldRestore
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
            rowsPerPage.value === "all"
                ? Math.max(
                    filteredRows.length,
                    1
                )
                : parseInt(
                    rowsPerPage.value
                );

        /* =======================================================
            SAVE TABLE STATE
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
                rows.length === 0
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

        /* =======================================================
            SEARCH HANDLER
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
            ROW LIMIT HANDLER
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
            PHET MODAL CONTROL
        ======================================================= */
        function openPhetModal() {

            modal.classList.remove("hidden");
            modal.classList.add("flex");

            form.reset();

            clearInlineErrors();

            form.querySelector(
                '[name="id"]'
            ).value = "";

            modalTitle.textContent =
                "Tambah PhET Baru";

            setTimeout(() => {
                titleInput.focus();
            }, 100);

            iframePreviewContainer
                .classList.add(
                    "hidden"
                );

            iframePreview.innerHTML =
                "";

            if (visibilityInput) {
                visibilityInput.value = "1";
                visibilityInput.dispatchEvent(new Event("change"));
            }

        }

        function closePhetModal() {

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
                openPhetModal
            );

        }

        /* ---------- Close ---------- */
        if (
            closeBtn
        ) {

            closeBtn.addEventListener(
                "click",
                closePhetModal
            );

        }

        if (
            cancelBtn
        ) {

            cancelBtn.addEventListener(
                "click",
                closePhetModal
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

                    closePhetModal();

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

                    closePhetModal();

                }

            }
        );

        /* =======================================================
            PREVIEW PHET MODAL CONTROL
        ======================================================= */
        if (
            previewClose
        ) {

            previewClose.addEventListener(
                "click",
                () => {

                    previewModal.classList.add(
                        "hidden"
                    );

                    document.getElementById(
                        "previewIframeContainer"
                    ).innerHTML =
                        "";

                }
            );

        }

        previewModal.addEventListener(
            "click",
            e => {

                if (
                    e.target ===
                    previewModal
                ) {

                    previewModal.classList.add(
                        "hidden"
                    );

                    document.getElementById(
                        "previewIframeContainer"
                    ).innerHTML =
                        "";

                }

            }
        );

        /* =======================================================
            PHET FORM SUBMISSION
        ======================================================= */
        form.addEventListener("submit", e => {

            e.preventDefault();

            clearInlineErrors();

            const submitBtn = form.querySelector('button[type="submit"]');

            const subjectInput = form.querySelector('[name="subject_id"]');
            const urlInput = form.querySelector('[name="original_url"]');

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
                }, 300);
            };

            /* ---------- 1. Judul PhET ---------- */
            if (titleInput.value.trim() === "") {
                setInlineError(titleInput, "Judul PhET wajib diisi.");
                scrollToField(titleInput);
                return;
            }

            /* ---------- 2. Mata Pelajaran ---------- */
            if (subjectInput && subjectInput.value.trim() === "") {
                setInlineError(subjectInput, "Mata pelajaran wajib dipilih.");
                scrollToField(subjectInput);
                return;
            }

            /* ---------- 3. URL PhET ---------- */
            if (urlInput.value.trim() === "") {
                setInlineError(urlInput, "URL PhET wajib diisi.");
                scrollToField(urlInput);
                return;
            }

            try {
                new URL(urlInput.value.trim());
            } catch {
                setInlineError(urlInput, "URL PhET tidak valid.");
                scrollToField(urlInput);
                return;
            }

            if (!urlInput.value.trim().includes("phet.colorado.edu")) {
                setInlineError(urlInput, "URL harus berasal dari situs resmi PhET.");
                scrollToField(urlInput);
                return;
            }

            /* ---------- 4. Akses Dibagikan: Tingkat ---------- */
            if (
                visibilityInput &&
                visibilityInput.value === "2" &&
                shareGradeFilter &&
                shareGradeFilter.value.trim() === ""
            ) {
                setInlineError(shareGradeFilter, "Tingkat wajib dipilih.");
                scrollToField(shareGradeFilter);
                return;
            }

            /* ---------- 5. Akses Dibagikan: Pengajar ---------- */
            const selectedTeachers = document.querySelectorAll(
                'input[name="teacher_ids[]"]:checked'
            );

            if (
                visibilityInput &&
                visibilityInput.value === "2" &&
                selectedTeachers.length === 0
            ) {
                setInlineError(shareTeacherList, "Pilih minimal satu pengajar penerima akses.");
                scrollToField(shareTeacherList);
                return;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add("opacity-70", "cursor-not-allowed");

            const formData = new FormData(form);

            showLoading("Menyimpan data...");

            fetch("../ajax/phet/save_phet.php", {
                method: "POST",
                body: formData
            })

            .then(res => res.json())

            .then(res => {

                Swal.close();

                submitBtn.disabled = false;
                submitBtn.classList.remove("opacity-70", "cursor-not-allowed");

                if (res.status == 1) {

                    closePhetModal();

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

        /* =======================================================
            INITIAL TABLE RENDER
        ======================================================= */
        renderTable();

    });
    </script>

</body>
</html>