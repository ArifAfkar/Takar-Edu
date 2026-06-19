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

/* ---------- Student Only ---------- */
if ($_SESSION['login_user_type'] != 3) {
    header("Location: home.php");
    exit;
}

/* =======================================================
    QUIZ ID VALIDATION
======================================================= */
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: student_quiz_list.php");
    exit;
}

$quizId = (int) $_GET['id'];
$userId = (int) $_SESSION['login_id'];

/* =======================================================
    STUDENT ACCESS
======================================================= */
$studentQuery = $conn->query("
    SELECT id
    FROM students
    WHERE user_id = {$userId}
    LIMIT 1
");

if (!$studentQuery || $studentQuery->num_rows <= 0) {
    die("Data siswa tidak ditemukan.");
}

$student = $studentQuery->fetch_assoc();
$studentId = (int) $student['id'];

/* =======================================================
    MAIN QUIZ QUERY
======================================================= */
$quizQuery = $conn->query("
    SELECT
        q.id,
        q.quiz_title,
        q.description,
        q.quiz_duration,
        q.status,
        q.open_at,
        q.due_date,
        qsl.id AS assignment_id
    FROM quiz_student_list qsl
    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id
    WHERE q.id = {$quizId}
    AND qsl.student_id = {$studentId}
    AND q.status = 1
    LIMIT 1
");

if (!$quizQuery || $quizQuery->num_rows <= 0) {
    header("Location: student_quiz_list.php");
    exit;
}

$quiz = $quizQuery->fetch_assoc();

$assignmentId = (int) $quiz['assignment_id'];

$quizDuration = !empty($quiz['quiz_duration'])
    ? (int) $quiz['quiz_duration']
    : 60;

/* =======================================================
    SCHEDULE VALIDATION
======================================================= */
$now = time();

if (!empty($quiz['open_at']) && strtotime($quiz['open_at']) > $now) {
    die("Kuis belum dibuka.");
}

if (!empty($quiz['due_date']) && strtotime($quiz['due_date']) < $now) {
    die("Kuis sudah berakhir.");
}

/* =======================================================
    HISTORY VALIDATION
======================================================= */
$historyCheck = $conn->query("
    SELECT id
    FROM history
    WHERE quiz_student_id = {$assignmentId}
    LIMIT 1
");

$hasHistory = ($historyCheck && $historyCheck->num_rows > 0);
$isRetry = isset($_GET['retry']) && $_GET['retry'] == '1';

if ($hasHistory && !$isRetry) {
    header("Location: student_quiz_list.php");
    exit;
}

/* =======================================================
    PAGE CONFIGURATION
======================================================= */
$pageTitle = "Konfirmasi Kuis - " . htmlspecialchars($quiz['quiz_title']);
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
        MAIN CONTENT AREA
    ======================================================= -->
    <main
        id="mainContent"
        class="min-h-screen bg-gray-100 flex items-center justify-center p-4 md:p-8"
    >

        <!-- =======================================================
            CONFIRMATION CONTAINER
        ======================================================= -->
        <div class="page-container-sm">

            <!-- =======================================================
                QUIZ CONFIRMATION CARD
            ======================================================= -->
            <section class="section-card">

                <!-- ---------- Quiz Header ---------- -->
                <div class="text-center mb-8">

                    <div class="section-icon bg-blue-600 text-white mx-auto mb-4">
                        <i data-lucide="clipboard-check" class="w-8 h-8"></i>
                    </div>

                    <h1 class="page-title text-center text-lg">
                        <?= htmlspecialchars($quiz['quiz_title']) ?>
                    </h1>

                    <p class="page-description text-center">
                        Konfirmasi sebelum memulai pengerjaan kuis.
                    </p>

                </div>

                <!-- ---------- Quiz Information ---------- -->
                <div class="notice-card notice-info mb-6">

                    <div class="space-y-4 text-sm text-gray-700 leading-relaxed">

                        <?php if (!empty($quiz['description'])): ?>

                            <p>
                                <?= nl2br(htmlspecialchars($quiz['description'])) ?>
                            </p>

                        <?php endif; ?>

                        <p>
                            Durasi pengerjaan:
                            <span class="font-semibold text-red-600">
                                <?= $quizDuration ?> menit
                            </span>
                        </p>

                        <p>
                            Kuis akan otomatis berakhir ketika waktu habis atau Anda mengumpulkan jawaban.
                        </p>

                    </div>

                </div>

                <!-- ---------- Exam Rules ---------- -->
                <div class="notice-card notice-warning mb-8">

                    <h3 class="font-semibold text-yellow-700 mb-2">
                        Perhatian
                    </h3>

                    <ul class="list-disc pl-6 space-y-3 text-gray-700 text-sm leading-relaxed">

                        <li>Pastikan koneksi internet stabil selama ujian berlangsung.</li>

                        <li>Gunakan perangkat yang siap digunakan hingga ujian selesai.</li>

                        <li>Ujian menggunakan mode layar penuh (fullscreen).</li>

                        <li>Keluar dari fullscreen akan dihitung sebagai pelanggaran.</li>

                        <li>Batas maksimal pelanggaran adalah 3 kali.</li>

                        <li>Jika batas pelanggaran tercapai, ujian akan dihentikan dan jawaban dikumpulkan otomatis.</li>

                    </ul>

                </div>

                <!-- ---------- Action Buttons ---------- -->
                <div class="flex flex-col sm:flex-row justify-center gap-3">

                    <button
                        type="button"
                        onclick="startQuiz(<?= $quizId ?>)"
                        class="form-btn form-btn-primary w-full text-sm"
                    >
                        Mulai Kerjakan
                    </button>

                    <button
                        type="button"
                        onclick="openGuideModal()"
                        class="action-btn-lg form-btn-primary w-full text-sm"
                    >
                        Lihat Panduan
                    </button>

                    <a
                        href="student_quiz_list.php"
                        class="action-btn-lg form-btn-secondary w-full text-sm"
                    >
                        Kembali
                    </a>

                </div>

            </section>

        </div>

    </main>

    <!-- =======================================================
        GUIDE MODAL
    ======================================================= -->
    <div id="guideModal" class="global-modal">

        <!-- ---------- Modal Card ---------- -->
        <div class="global-modal-card">

            <!-- ---------- Modal Header ---------- -->
            <div class="global-modal-header">
                <h2 class="modal-title">
                    Panduan Pengerjaan Kuis
                </h2>

                <button
                    type="button"
                    onclick="closeGuideModal()"
                    class="modal-close"
                >
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- ---------- Modal Body ---------- -->
            <div class="global-modal-body">

                <ul class="list-disc pl-6 space-y-3 text-gray-700 text-sm leading-relaxed text-justify">

                    <li>Baca setiap soal dengan cermat sebelum menjawab.</li>

                    <li>Perhatikan batas waktu pengerjaan yang tersedia.</li>

                    <li>Pastikan perangkat dan koneksi internet dalam kondisi stabil.</li>

                    <li>Selama ujian berlangsung, sistem akan menggunakan mode layar penuh (fullscreen).</li>

                    <li>Keluar dari mode fullscreen akan tercatat sebagai pelanggaran.</li>

                    <li>Jika tidak kembali ke mode fullscreen dalam waktu yang ditentukan, pelanggaran akan bertambah otomatis.</li>

                    <li>Apabila pelanggaran mencapai 3 kali, ujian akan dihentikan dan jawaban dikumpulkan secara otomatis.</li>

                    <li>Periksa kembali jawaban Anda sebelum mengakhiri ujian.</li>

                </ul>

            </div>

            <!-- ---------- Modal Footer ---------- -->
            <div class="global-modal-footer">
                <button
                    type="button"
                    onclick="closeGuideModal()"
                    class="form-btn form-btn-secondary"
                >
                    Tutup
                </button>
            </div>

        </div>
    </div>


    <script>
    /* =======================================================
        GUIDE MODAL CONTROL
    ======================================================= */
    function openGuideModal() {
        const modal = document.getElementById("guideModal");
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    }

    function closeGuideModal() {
        const modal = document.getElementById("guideModal");
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    }

    /* ---------- Outside Click Close ---------- */
    window.addEventListener("click", function(e) {
        const modal = document.getElementById("guideModal");

        if (e.target === modal) {
            closeGuideModal();
        }
    });

    /* =======================================================
        START QUIZ HANDLER
    ======================================================= */
    function startQuiz(quizId) {

        /* ---------- Clear Local Storage ---------- */
        localStorage.removeItem(`exam_${quizId}`);
        localStorage.removeItem(`exam_timer_${quizId}`);
        localStorage.removeItem(`exam_answers_${quizId}`);
        localStorage.removeItem(`exam_doubt_${quizId}`);
        localStorage.removeItem(`exam_finished_${quizId}`);

        /* ---------- Clear Session Storage ---------- */
        sessionStorage.removeItem(`exam_${quizId}`);
        sessionStorage.removeItem(`exam_timer_${quizId}`);
        sessionStorage.removeItem(`exam_answers_${quizId}`);
        sessionStorage.removeItem(`exam_doubt_${quizId}`);
        sessionStorage.removeItem(`exam_finished_${quizId}`);

        /* ---------- Redirect To Answer Sheet ---------- */
        window.location.href = `answer_sheet.php?id=${quizId}`;
    }

    /* =======================================================
        INITIALIZE ICONS
    ======================================================= */
    if (window.lucide) {
        lucide.createIcons();
    }
    </script>

</body>
</html>