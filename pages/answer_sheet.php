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

$userId = (int) $_SESSION['login_id'];

/* =======================================================
    QUIZ ID VALIDATION
======================================================= */
if (!isset($_GET['id']) || (int) $_GET['id'] <= 0) {
    header('Location: student_quiz_list.php');
    exit;
}

$quizId = (int) $_GET['id'];

/* =======================================================
    REDIRECT ALERT HELPER
======================================================= */
function redirect_with_alert($message, $target)
{
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <?php require_once '../includes/header.php'; ?>
    </head>
    <body>

    <script>
    Swal.fire({
        icon: 'error',

        title: 'Akses Ditolak',

        text: <?= json_encode($message) ?>,

        allowOutsideClick: false,
        allowEscapeKey: false,

        confirmButtonText: 'OK',

        customClass: {
            popup: 'rounded-3xl',
            confirmButton: 'rounded-xl px-6 py-3'
        },

        buttonsStyling: false,

        didOpen: () => {

            Swal.getConfirmButton().classList.add(
                'bg-blue-600',
                'hover:bg-blue-700',
                'text-white',
                'transition'
            );

        }

    }).then(() => {
        window.location.replace(<?= json_encode($target) ?>);
    });
    </script>

    </body>
    </html>
    <?php
    exit;
}

/* =======================================================
    STUDENT DATA
======================================================= */
$studentStmt = $conn->prepare("
    SELECT
        s.id,
        s.class_id,
        c.class_name
    FROM students s
    LEFT JOIN classes c
        ON s.class_id = c.id
    WHERE s.user_id = ?
    LIMIT 1
");

$studentStmt->bind_param("i", $userId);
$studentStmt->execute();

$studentResult = $studentStmt->get_result();

if (!$studentResult || $studentResult->num_rows <= 0) {
    redirect_with_alert(
        'Data siswa tidak ditemukan.',
        'student_quiz_list.php'
    );
}

$student = $studentResult->fetch_assoc();

$studentId = (int) $student['id'];
$classId   = (int) $student['class_id'];

/* =======================================================
    QUIZ ASSIGNMENT VALIDATION
======================================================= */
$quizStmt = $conn->prepare("
    SELECT
        q.id,
        q.quiz_title,
        q.description,
        q.quiz_duration,
        q.status,
        q.open_at,
        q.due_date,

        qsl.id AS quiz_student_id,
        qsl.status AS assignment_status,
        qsl.started_at,
        qsl.completed_at

    FROM quiz_student_list qsl

    INNER JOIN quiz_list q
        ON qsl.quiz_id = q.id

    WHERE q.id = ?
    AND qsl.student_id = ?
    AND qsl.status IN (1, 2)
    AND q.status = 1

    LIMIT 1
");

$quizStmt->bind_param("ii", $quizId, $studentId);
$quizStmt->execute();

$quizResult = $quizStmt->get_result();

if (!$quizResult || $quizResult->num_rows <= 0) {
    redirect_with_alert(
        'Kuis tidak tersedia atau akses ditolak.',
        'student_quiz_list.php'
    );
}

$quiz = $quizResult->fetch_assoc();

$quizStudentId = (int) $quiz['quiz_student_id'];

/* =======================================================
    SCHEDULE VALIDATION
======================================================= */
$now = time();

if (!empty($quiz['open_at']) && strtotime($quiz['open_at']) > $now) {
    redirect_with_alert(
        'Kuis belum dibuka.',
        'student_quiz_list.php'
    );
}

if (!empty($quiz['due_date']) && strtotime($quiz['due_date']) < $now) {
    redirect_with_alert(
        'Kuis sudah berakhir.',
        'student_quiz_list.php'
    );
}

/* =======================================================
    HISTORY CHECK
======================================================= */
$historyStmt = $conn->prepare("
    SELECT id
    FROM history
    WHERE quiz_student_id = ?
    LIMIT 1
");

$historyStmt->bind_param("i", $quizStudentId);
$historyStmt->execute();

$historyResult = $historyStmt->get_result();

if ($historyResult && $historyResult->num_rows > 0) {
    redirect_with_alert(
        'Anda sudah menyelesaikan kuis ini sebelumnya.',
        'student_quiz_list.php'
    );
}

/* =======================================================
    ASSIGNMENT START STATUS
======================================================= */
if ((int) $quiz['assignment_status'] === 1) {

    $startStmt = $conn->prepare("
        UPDATE quiz_student_list
        SET
            status = 2,
            started_at = IFNULL(started_at, NOW()),
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");

    $startStmt->bind_param("i", $quizStudentId);
    $startStmt->execute();
}

/* =======================================================
    QUESTION REFERENCE DATA
======================================================= */
$questionsStmt = $conn->prepare("
    SELECT
        q.id,
        q.quiz_id,
        q.question,
        q.question_type,
        q.statement_type,
        q.points,
        q.order_by,
        q.wacana_id,
        q.phet_id,
        q.answer_key_text,
        q.rubric_text,
        q.answer_table_config,

        w.wacana_title,
        w.description AS wacana_description,

        p.phet_title,
        p.iframe_phet

    FROM questions q

    LEFT JOIN wacana w
        ON q.wacana_id = w.id

    LEFT JOIN phet p
        ON q.phet_id = p.id

    WHERE q.quiz_id = ?

    ORDER BY
        q.order_by ASC,
        q.id ASC
");

$questionsStmt->bind_param("i", $quizId);
$questionsStmt->execute();

$questionsResult = $questionsStmt->get_result();

if (!$questionsResult || $questionsResult->num_rows <= 0) {
    redirect_with_alert(
        'Belum ada soal untuk kuis ini.',
        'student_quiz_list.php'
    );
}

/* =======================================================
    QUESTION OPTION DATA
======================================================= */
$questions = [];
$options   = [];

while ($question = $questionsResult->fetch_assoc()) {

    $questionId = (int) $question['id'];

    $questions[] = $question;

    $questionOptions = [];

    if ($question['question_type'] === 'likert') {

        $questionOptions = [
            [
                'id' => null,
                'value' => 'sangat_setuju',
                'option_text' => 'Sangat Setuju',
                'order_by' => 1,
                'is_right' => 0
            ],
            [
                'id' => null,
                'value' => 'setuju',
                'option_text' => 'Setuju',
                'order_by' => 2,
                'is_right' => 0
            ],
            [
                'id' => null,
                'value' => 'tidak_setuju',
                'option_text' => 'Tidak Setuju',
                'order_by' => 3,
                'is_right' => 0
            ],
            [
                'id' => null,
                'value' => 'sangat_tidak_setuju',
                'option_text' => 'Sangat Tidak Setuju',
                'order_by' => 4,
                'is_right' => 0
            ]
        ];

    } else {

        $optionStmt = $conn->prepare("
            SELECT
                id,
                question_id,
                order_by,
                option_text,
                is_right
            FROM question_opt
            WHERE question_id = ?
            ORDER BY order_by ASC, id ASC
        ");

        $optionStmt->bind_param("i", $questionId);
        $optionStmt->execute();

        $optionResult = $optionStmt->get_result();

        if ($optionResult) {
            while ($option = $optionResult->fetch_assoc()) {
                $questionOptions[] = $option;
            }
        }

        $optionStmt->close();
    }

    $options[$questionId] = $questionOptions;
}

/* =======================================================
    SUMMARY DATA
======================================================= */
$totalQuestions = count($questions);

$quizDurationMinutes = !empty($quiz['quiz_duration'])
    ? (int) $quiz['quiz_duration']
    : 60;

/* =======================================================
    FRONTEND DATA
======================================================= */
$examData = [
    'quiz' => [
        'id'              => $quizId,
        'quiz_student_id' => $quizStudentId,
        'title'           => $quiz['quiz_title'],
        'description'     => $quiz['description'],
        'duration'        => $quizDurationMinutes
    ],

    'student' => [
        'user_id'    => $userId,
        'student_id' => $studentId,
        'class_id'   => $classId,
        'class_name' => $student['class_name'] ?? '-'
    ],

    'summary' => [
        'total_questions' => $totalQuestions
    ],

    'questions' => $questions,
    'options'   => $options
];
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <!-- =======================================================
        GLOBAL HEADER / ASSETS
    ======================================================= -->
    <?php require_once '../includes/header.php'; ?>

    <title>
        Ujian: <?= htmlspecialchars($quiz['quiz_title']) ?>
    </title>
</head>

<body>

    <!-- =======================================================
        EXAM LAYOUT
    ======================================================= -->
    <div class="exam-layout">

        <!-- =======================================================
            EXAM MAIN CONTENT
        ======================================================= -->
        <main class="exam-main">

            <!-- ---------- Exam Header ---------- -->
            <div class="exam-header">

                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">
                        <?= htmlspecialchars($quiz['quiz_title']) ?>
                    </h1>

                    <p class="text-sm text-gray-500 mt-1">
                        Kelas:
                        <span class="font-semibold text-gray-700">
                            <?= htmlspecialchars($student['class_name'] ?? '-') ?>
                        </span>
                        · Total Soal:
                        <span class="font-semibold text-blue-600">
                            <?= $totalQuestions ?>
                        </span>
                    </p>
                </div>

                <div class="flex flex-col items-start md:items-end">
                    <span class="text-sm text-gray-500">
                        Sisa Waktu
                    </span>

                    <div id="timer-display-element" class="exam-timer">
                        --:--:--
                    </div>

                    <div id="violationCounter" class="exam-violation">
                        Pelanggaran: 0 / 3
                    </div>
                </div>

            </div>

            <div class="w-full bg-gray-200 h-2">
                <div id="progressBar" class="progress-bar-fill" style="width:0%;"></div>
            </div>

            <!-- ---------- Exam Content ---------- -->
            <section class="exam-content">

                <div class="mb-6">
                    <h2 id="question-title-display" class="question-title">
                        SOAL NO. 1
                    </h2>
                </div>

                <div
                    id="wacana-section-display"
                    class="hidden mb-6 content-reference-card"
                >
                    <h3
                        id="wacana-title-text"
                        class="font-semibold text-gray-800 mb-3"
                    ></h3>

                    <div
                        id="wacana-content-text"
                        class="text-gray-700 leading-relaxed overflow-x-auto wacana-content"
                    ></div>
                </div>

                <div
                    id="phet-section-display"
                    class="hidden mb-6 content-reference-card"
                >
                    <h3
                        id="phet-title-text"
                        class="font-semibold text-gray-800 mb-3"
                    ></h3>

                    <div
                        id="phet-content-iframe"
                        class="w-full overflow-x-auto"
                    ></div>
                </div>

                <div id="question-text-display" class="question-content mb-6"></div>

                <div
                    id="answer-table-input-display"
                    class="hidden mb-6"
                ></div>

                <div
                    id="options-container-display"
                    class="space-y-4"
                ></div>

            </section>

            <!-- ---------- Exam Footer ---------- -->
            <footer class="exam-footer">

                <button
                    id="prev-question-btn"
                    type="button"
                    class="exam-nav-btn action-secondary"
                    disabled
                >
                    ← Soal Sebelumnya
                </button>

                <button
                    id="toggle-doubt-btn"
                    type="button"
                    class="exam-nav-btn action-warning"
                >
                    Ragu-Ragu
                </button>

                <button
                    id="next-question-btn"
                    type="button"
                    class="exam-nav-btn form-btn-primary"
                >
                    Soal Selanjutnya →
                </button>

            </footer>

        </main>

        <!-- =======================================================
            EXAM SIDEBAR
        ======================================================= -->
        <aside class="exam-sidebar">

            <div class="p-5 border-b border-gray-200 bg-gray-900">

                <button
                    id="finish-exam-trigger-btn"
                    type="button"
                    class="exam-nav-btn form-btn-danger"
                >
                    Hentikan Ujian
                </button>

            </div>

            <div class="px-5 py-4 border-b border-gray-200">

                <h3 class="text-lg font-semibold text-gray-800 mb-3">
                    Navigasi Soal
                </h3>

                <div class="question-grid">

                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded bg-green-500"></span>
                        <span>Terjawab</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded bg-gray-300"></span>
                        <span>Kosong</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded bg-yellow-400"></span>
                        <span>Ragu</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded bg-blue-600"></span>
                        <span>Aktif</span>
                    </div>

                </div>

            </div>

            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">

                <div class="flex justify-between items-center text-sm">

                    <span class="text-gray-600">
                        Progres
                    </span>

                    <span
                        id="navigator-progress-text"
                        class="font-semibold text-blue-600"
                    >
                        0 / 0
                    </span>

                </div>

            </div>

            <div
                id="question-switcher-display-area"
                class="
                    flex-1
                    min-h-0
                    overflow-y-auto
                    p-4
                    grid
                    grid-cols-4
                    gap-2
                    content-start
                "
            ></div>

        </aside>

    </div>

    <!-- =======================================================
        STOP EXAM MODAL
    ======================================================= -->
    <div id="stopExamModal" class="global-modal">

        <div class="global-modal-card">

            <div class="global-modal-header">
                <h2 class="modal-title text-red-600">
                    Konfirmasi Hentikan Ujian
                </h2>
            </div>

            <div class="global-modal-body">

                <p class="text-gray-700 leading-relaxed mb-4">
                    Setelah ujian dihentikan, jawaban yang sudah Anda isi akan dikumpulkan dan diproses.
                </p>

                <label class="flex items-start gap-3 mb-6">

                    <input
                        type="checkbox"
                        id="confirmStopExamCheckbox"
                        class="mt-1"
                    >

                    <span class="text-sm text-gray-700">
                        Saya yakin ingin menghentikan pengerjaan ujian ini.
                    </span>

                </label>

            </div>

            <div class="global-modal-footer">

                <button id="cancelStopExamBtn" type="button" class="form-btn form-btn-secondary">
                    Batal
                </button>

                <button id="confirmStopExamActionBtn" type="button" class="form-btn form-btn-danger" disabled>
                    Hentikan
                </button>

            </div>

        </div>
    </div>

    <!-- =======================================================
        HIDDEN ANSWER FORM
    ======================================================= -->
    <form
        id="cbt-answer-form"
        method="POST"
        class="hidden"
    >
        <input type="hidden" name="user_id" value="<?= $userId ?>">
        <input type="hidden" name="student_id" value="<?= $studentId ?>">
        <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <input type="hidden" name="quiz_student_id" value="<?= $quizStudentId ?>">
    </form>

    <!-- =======================================================
        FULLSCREEN ENTRY OVERLAY
    ======================================================= -->
    <div
        id="fullscreenExamOverlay"
        class="fullscreen-overlay"
    >
        <div class="fullscreen-card">
            <div class="w-16 h-16 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mx-auto mb-4 text-3xl font-bold">
                !
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-3">
                Mode Ujian Fullscreen
            </h2>

            <p class="text-gray-600 leading-relaxed mb-6">
                Untuk melanjutkan pengerjaan kuis, silakan masuk ke mode layar penuh.
            </p>

            <button
                id="enterFullscreenBtn"
                type="button"
                class="fullscreen-btn"
            >
                Masuk Mode Ujian
            </button>
        </div>
    </div>


    <script>
    /* =======================================================
        FRONTEND DATA
    ======================================================= */
    const examData = <?= json_encode($examData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    /* =======================================================
        CORE STATE
    ======================================================= */
    const quizId        = examData.quiz.id;
    const quizStudentId = examData.quiz.quiz_student_id;
    const studentId     = examData.student.student_id;
    const duration      = examData.quiz.duration;
    const questions     = examData.questions;
    const options       = examData.options;

    const STORAGE_KEY = `answer_sheet_${quizStudentId}`;

    let currentQuestionIndex = 0;

    let examState = {
        answers: {},
        doubtful: {},
        endTime: null,
        currentQuestionIndex: 0
    };

    let isSubmitting = false;
    let timerInterval = null;

    /* =======================================================
        EXAM VIOLATION TRACKER
    ======================================================= */
    const MAX_VIOLATIONS = 3;
    const VIOLATION_COOLDOWN_MS = 3000;
    const FULLSCREEN_RETURN_LIMIT_MS = 30000;

    let lastViolationTime = 0;
    let fullscreenPenaltyInterval = null;

    function getViolationCount() {
        return parseInt(examState.violation_count || 0);
    }

    function setViolationCount(count) {
        examState.violation_count = count;
        saveExamState();
        updateViolationCounter();
    }

    function updateViolationCounter() {
        const el = document.getElementById("violationCounter");

        if (el) {
            el.textContent = `Pelanggaran: ${getViolationCount()} / ${MAX_VIOLATIONS}`;
        }
    }

    let alarmAudio = null;

    function playViolationAlarm() {

        try {

            if (!alarmAudio) {

                alarmAudio = new Audio(
                    "https://actions.google.com/sounds/v1/alarms/beep_short.ogg"
                );

                alarmAudio.volume = 0.8;
            }

            alarmAudio.currentTime = 0;
            alarmAudio.play();

        } catch (e) {
            console.log(e);
        }
    }

    function startFullscreenPenaltyTimer() {

        if (fullscreenPenaltyInterval) {
            return;
        }

        fullscreenPenaltyInterval = setInterval(() => {

            if (isSubmitting) return;

            if (isFullscreenActive()) {
                stopFullscreenPenaltyTimer();
                return;
            }

            handleViolation(
                "Anda belum kembali ke mode fullscreen."
            );

        }, FULLSCREEN_RETURN_LIMIT_MS);

    }

    function stopFullscreenPenaltyTimer() {

        if (fullscreenPenaltyInterval) {
            clearInterval(fullscreenPenaltyInterval);
            fullscreenPenaltyInterval = null;
        }

    }

    function handleViolation(reason) {

        if (isSubmitting) return;

        const now = Date.now();

        if (now - lastViolationTime < VIOLATION_COOLDOWN_MS) {
            return;
        }

        lastViolationTime = now;

        const currentCount = getViolationCount();

        if (currentCount >= MAX_VIOLATIONS) {
            return;
        }

        const newCount = Math.min(currentCount + 1, MAX_VIOLATIONS);

        setViolationCount(newCount);

        playViolationAlarm();

        if (newCount >= MAX_VIOLATIONS) {

            isSubmitting = true;

            Swal.fire({
                icon: "warning",

                title: "Ujian Dihentikan",

                html: `
                    <div class="text-center">
                        Anda telah melakukan pelanggaran sebanyak
                        <b>${MAX_VIOLATIONS}</b> kali.
                        <br><br>
                        Jawaban akan dikumpulkan otomatis.
                    </div>
                `,

                allowOutsideClick: false,
                allowEscapeKey: false,

                confirmButtonText: "OK",

                customClass: {
                    popup: "rounded-3xl",
                    confirmButton: "rounded-xl px-6 py-3 font-semibold"
                },

                buttonsStyling: false,

                didOpen: () => {

                    Swal.getPopup().style.padding = "2rem";

                    Swal.getConfirmButton().classList.add(
                        "bg-blue-600",
                        "hover:bg-blue-700",
                        "text-white",
                        "transition"
                    );

                }
            }).then(() => {
                isSubmitting = false;
                submitExam(true, "violation_limit");
            });

            return;
        }

        Swal.fire({
            icon: "warning",
            title: "Pelanggaran Terdeteksi",
            html: `
                <div class="text-center">
                    <p class="mb-2">${reason}</p>

                    <div class="mt-3 p-3 rounded-xl bg-red-50 border border-red-200">
                        Pelanggaran:
                        <b>${newCount}</b> /
                        <b>${MAX_VIOLATIONS}</b>
                    </div>
                </div>
            `,
            confirmButtonText: "Kembali ke Ujian",
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    }

    /* =======================================================
        ELEMENT REFERENCES
    ======================================================= */
    const questionTitleEl = document.getElementById("question-title-display");
    const questionTextEl  = document.getElementById("question-text-display");
    const answerTableInputEl = document.getElementById("answer-table-input-display");
    const optionsEl       = document.getElementById("options-container-display");
    const switcherEl      = document.getElementById("question-switcher-display-area");
    const navigatorProgressEl = document.getElementById(
        "navigator-progress-text"
    );
    const progressBarEl   = document.getElementById("progressBar");
    const timerEl         = document.getElementById("timer-display-element");

    const prevBtn   = document.getElementById("prev-question-btn");
    const nextBtn   = document.getElementById("next-question-btn");
    const doubtBtn  = document.getElementById("toggle-doubt-btn");
    const finishBtn = document.getElementById("finish-exam-trigger-btn");

    const stopModal      = document.getElementById("stopExamModal");
    const stopCheckbox   = document.getElementById("confirmStopExamCheckbox");
    const confirmStopBtn = document.getElementById("confirmStopExamActionBtn");
    const cancelStopBtn  = document.getElementById("cancelStopExamBtn");

    const fullscreenOverlay = document.getElementById("fullscreenExamOverlay");
    const enterFullscreenBtn = document.getElementById("enterFullscreenBtn");

    /* =======================================================
        LOCAL STORAGE
    ======================================================= */
    function loadExamState() {
        const saved = localStorage.getItem(STORAGE_KEY);

        if (saved) {
            try {
                examState = JSON.parse(saved);
            } catch (e) {
                resetExamState();
            }
        } else {
            resetExamState();
        }

        if (!examState.endTime) {
            examState.endTime = Date.now() + (duration * 60 * 1000);
            saveExamState();
        }
    }

    function saveExamState() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(examState));
    }

    function clearExamState() {
        localStorage.removeItem(STORAGE_KEY);
    }

    function resetExamState() {
        examState = {
            answers: {},
            doubtful: {},
            endTime: Date.now() + (duration * 60 * 1000),
            currentQuestionIndex: 0,
            violation_count: 0
        };

        saveExamState();
    }

    /* =======================================================
        UTILITY FUNCTIONS
    ======================================================= */
    function hasAnswer(question, answer) {
        if (!question || !answer) return false;

        const qType = String(question.question_type || "").toLowerCase();

        const hasOption =
            answer.option_id !== null &&
            answer.option_id !== undefined &&
            String(answer.option_id).trim() !== "";

        const hasText =
            typeof answer.answer_text === "string" &&
            answer.answer_text.trim() !== "";

        const hasTable =
            answer.table_answer &&
            typeof answer.table_answer === "object" &&
            Object.values(answer.table_answer).some(value =>
                String(value || "").trim() !== ""
            );

        if (qType === "likert") {
            return hasText;
        }

        if (qType === "multiple_choice") {
            return hasOption;
        }

        if (qType === "reasoned_multiple_choice") {
            return hasOption && hasText;
        }

        if (qType === "short_answer" || qType === "essay") {
            return hasText || hasTable;
        }

        return hasOption || hasText || hasTable;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return "";

        return String(text)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function normalizeMathText(text) {
        if (!text) return "";

        return String(text)
            .replace(/\\\\\(/g, "\\(")
            .replace(/\\\\\)/g, "\\)")
            .replace(/\\\\\[/g, "\\[")
            .replace(/\\\\\]/g, "\\]");
    }

    function renderKatexInElement(element) {
        if (typeof renderMathInElement === "function") {
            renderMathInElement(element, {
                delimiters: [
                    { left: "\\(", right: "\\)", display: false },
                    { left: "\\[", right: "\\]", display: true },
                    { left: "$$", right: "$$", display: true },
                    { left: "$", right: "$", display: false }
                ],
                throwOnError: false
            });
        }
    }

    /* =======================================================
        TIMER SYSTEM
    ======================================================= */
    function formatTime(ms) {
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));

        const hours   = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        return [
            String(hours).padStart(2, "0"),
            String(minutes).padStart(2, "0"),
            String(seconds).padStart(2, "0")
        ].join(":");
    }

    function startTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
        }

        timerInterval = setInterval(() => {
            const remaining = examState.endTime - Date.now();

            timerEl.textContent = formatTime(remaining);

            if (remaining <= 300000) {
                timerEl.classList.add("animate-pulse", "text-red-700");
            }

            if (remaining <= 0) {
                clearInterval(timerInterval);
                submitExam(true, "time_expired");
            }
        }, 1000);
    }

    /* =======================================================
        PROGRESS AND NAVIGATION
    ======================================================= */
    function updateProgress() {
        const answeredCount = questions.filter(q => {
            return hasAnswer(q, examState.answers[q.id]);
        }).length;

        const percent = questions.length > 0
            ? (answeredCount / questions.length) * 100
            : 0;

        progressBarEl.style.width = `${percent}%`;
    }

    function renderNavigator() {
        const answeredCount = questions.filter(question =>
            hasAnswer(
                question,
                examState.answers[question.id]
            )
        ).length;

        navigatorProgressEl.textContent =
            `${answeredCount} / ${questions.length}`;

        switcherEl.innerHTML = "";

        questions.forEach((question, index) => {
            const qid = question.id;

            let colorClass = "bg-gray-300 text-gray-800";

            if (hasAnswer(question, examState.answers[qid])) {
                colorClass = "bg-green-500 text-white";
            }

            if (examState.doubtful[qid]) {
                colorClass = "bg-yellow-400 text-gray-900";
            }

            if (index === currentQuestionIndex) {
                colorClass = "bg-blue-600 text-white";
            }

            const btn = document.createElement("button");

            btn.type = "button";
            btn.textContent = index + 1;
            btn.className = `
                w-full h-11
                rounded-lg
                font-semibold
                transition
                hover:scale-105
                ${colorClass}
            `;

            btn.addEventListener("click", () => {
                currentQuestionIndex = index;
                renderQuestion();
            });

            switcherEl.appendChild(btn);
        });
    }

    function refreshUI() {
        saveExamState();
        updateProgress();
        renderNavigator();
    }

    /* =======================================================
        REFERENCE CONTENT RENDERING
    ======================================================= */
    function renderWacana(question) {
        const section = document.getElementById("wacana-section-display");
        const title   = document.getElementById("wacana-title-text");
        const content = document.getElementById("wacana-content-text");

        if (question.wacana_id && question.wacana_title) {
            section.classList.remove("hidden");
            title.textContent = question.wacana_title;
            content.innerHTML = normalizeMathText(
                question.wacana_description || ""
            );

            content.querySelectorAll("table").forEach(table => {
                table.classList.add(
                    "w-full",
                    "border",
                    "border-gray-300",
                    "border-collapse",
                    "my-4"
                );
            });

            content.querySelectorAll("th").forEach(th => {
                th.classList.add(
                    "border",
                    "border-gray-300",
                    "bg-gray-100",
                    "px-3",
                    "py-2",
                    "font-semibold",
                    "text-left"
                );
            });

            content.querySelectorAll("td").forEach(td => {
                td.classList.add(
                    "border",
                    "border-gray-300",
                    "px-3",
                    "py-2"
                );
            });
        } else {
            section.classList.add("hidden");
            title.textContent = "";
            content.innerHTML = "";
        }
    }

    function renderPhet(question) {
        const section = document.getElementById("phet-section-display");
        const title   = document.getElementById("phet-title-text");
        const iframe  = document.getElementById("phet-content-iframe");

        if (question.phet_id && question.phet_title) {
            section.classList.remove("hidden");
            title.textContent = question.phet_title;
            iframe.innerHTML = question.iframe_phet || "";
        } else {
            section.classList.add("hidden");
            title.textContent = "";
            iframe.innerHTML = "";
        }
    }

    /* =======================================================
        ANSWER RENDER HELPERS
    ======================================================= */
    function setAnswer(questionId, answerData) {
        examState.answers[questionId] = {
            ...(examState.answers[questionId] || {}),
            ...answerData
        };

        refreshUI();
    }

    function renderOptionRadio(question, optionList) {
        optionList.forEach(option => {
            const questionId = question.id;
            const qType = String(question.question_type).toLowerCase();
            const saved = examState.answers[questionId];

            const isLikert = qType === "likert";

            const optionValue = isLikert
                ? option.value
                : option.id;

            const checked = isLikert
                ? saved?.answer_text === optionValue
                : saved?.option_id == optionValue;

            const wrapper = document.createElement("label");

            wrapper.className = `
                flex items-start gap-3 p-4 rounded-xl border
                hover:bg-gray-50 cursor-pointer transition
            `;

            wrapper.innerHTML = `
                <input
                    type="radio"
                    name="answer_${questionId}"
                    value="${optionValue}"
                    ${checked ? "checked" : ""}
                    class="mt-1 w-4 h-4"
                >

                <span class="leading-relaxed">
                    ${normalizeMathText(option.option_text)}
                </span>
            `;

            wrapper.querySelector("input").addEventListener("change", () => {

                const latestAnswer = examState.answers[questionId] || {};

                setAnswer(questionId, {
                    question_type: question.question_type,
                    option_id: isLikert ? null : parseInt(option.id),
                    answer_text: isLikert ? option.value : (latestAnswer.answer_text || "")
                });

            });

            optionsEl.appendChild(wrapper);
        });
    }

    function renderReasonTextarea(question) {
        const questionId = question.id;
        const saved = examState.answers[questionId];

        const textarea = document.createElement("textarea");

        textarea.className = `
            w-full mt-4 p-4 border rounded-xl min-h-[120px]
            focus:outline-none focus:ring-2 focus:ring-blue-500
        `;

        textarea.placeholder = "Tuliskan alasan Anda...";

        textarea.value = saved?.answer_text || "";

        textarea.addEventListener("input", () => {

            const latestAnswer = examState.answers[questionId] || {};

            setAnswer(questionId, {
                question_type: question.question_type,
                option_id: latestAnswer.option_id || null,
                answer_text: textarea.value
            });

        });

        optionsEl.appendChild(textarea);
    }

    function renderTextAnswer(question, placeholder, minHeight = "120px") {
        const questionId = question.id;
        const saved = examState.answers[questionId];

        const textarea = document.createElement("textarea");

        textarea.className = `
            w-full p-4 border rounded-xl
            focus:outline-none focus:ring-2 focus:ring-blue-500
        `;

        textarea.style.minHeight = minHeight;
        textarea.placeholder = placeholder;
        textarea.value = saved?.answer_text || "";

        textarea.addEventListener("input", () => {
            setAnswer(questionId, {
                question_type: question.question_type,
                option_id: null,
                answer_text: textarea.value
            });
        });

        optionsEl.appendChild(textarea);
    }

    function applyTableTailwind(table) {
        table.classList.add(
            "w-full",
            "border",
            "border-gray-300",
            "border-collapse",
            "bg-white"
        );

        table.querySelectorAll("th").forEach(th => {
            th.classList.add(
                "border",
                "border-gray-300",
                "bg-gray-100",
                "px-3",
                "py-2",
                "font-semibold",
                "text-center"
            );
        });

        table.querySelectorAll("td").forEach(td => {
            td.classList.add(
                "border",
                "border-gray-300",
                "px-3",
                "py-2"
            );
        });
    }

    function renderAnswerTableInput(question) {
        answerTableInputEl.innerHTML = "";
        answerTableInputEl.classList.add("hidden");

        if (!question.answer_table_config) return;

        let config;

        try {
            config = JSON.parse(question.answer_table_config);
        } catch (e) {
            console.warn("answer_table_config tidak valid:", question.answer_table_config);
            return;
        }

        if (
            !config ||
            config.source !== "wacana_table" ||
            !Array.isArray(config.input_cells)
        ) {
            return;
        }

        const temp = document.createElement("div");
        temp.innerHTML = normalizeMathText(question.wacana_description || "");

        const tables = temp.querySelectorAll("table");
        const sourceTable = tables[config.table_index || 0];

        if (!sourceTable) return;

        const cloneTable = sourceTable.cloneNode(true);

        applyTableTailwind(cloneTable);

        const questionId = question.id;

        if (!examState.answers[questionId]) {
            examState.answers[questionId] = {
                question_type: question.question_type,
                option_id: null,
                answer_text: ""
            };
        }

        let savedTableAnswer = {};

        if (
            examState.answers[questionId] &&
            typeof examState.answers[questionId].table_answer === "object"
        ) {
            savedTableAnswer = examState.answers[questionId].table_answer;
        }

        config.input_cells.forEach(cell => {
            const rowIndex = parseInt(cell.row);
            const colIndex = parseInt(cell.col);

            const row = cloneTable.rows[rowIndex];

            if (!row || !row.cells[colIndex]) return;

            const targetCell = row.cells[colIndex];
            const key = `r${rowIndex}_c${colIndex}`;

            targetCell.innerHTML = `
                <input
                    type="text"
                    value="${escapeHtml(savedTableAnswer[key] || "")}"
                    class="w-full min-w-[90px] px-2 py-1 border border-blue-300 rounded-lg bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Isi..."
                    data-answer-cell="${key}"
                >
            `;
        });

        cloneTable.querySelectorAll("input[data-answer-cell]").forEach(input => {
            input.addEventListener("input", () => {
                const currentAnswer = {};

                cloneTable.querySelectorAll("input[data-answer-cell]").forEach(item => {
                    currentAnswer[item.dataset.answerCell] = item.value;
                });

                setAnswer(questionId, {
                    question_type: question.question_type,
                    table_answer: currentAnswer
                });
            });
        });

        answerTableInputEl.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4">
                <h4 class="font-semibold text-gray-800 mb-2">
                    Tabel Jawaban
                </h4>

                <p class="text-sm text-gray-600 mb-3">
                    Lengkapi bagian kosong pada tabel berikut.
                </p>

                <div class="overflow-x-auto bg-white border border-gray-200 rounded-xl p-3"></div>
            </div>
        `;

        answerTableInputEl
            .querySelector(".overflow-x-auto")
            .appendChild(cloneTable);

        answerTableInputEl.classList.remove("hidden");
    }

    /* =======================================================
        QUESTION RENDERING
    ======================================================= */
    function renderQuestion() {
        examState.currentQuestionIndex = currentQuestionIndex;
        saveExamState();

        const question = questions[currentQuestionIndex];

        if (!question) return;

        const questionId = question.id;
        const qType = String(question.question_type).toLowerCase();

        questionTitleEl.textContent = `SOAL NO. ${currentQuestionIndex + 1}`;

        questionTextEl.innerHTML = `
            <div class="font-medium text-lg leading-relaxed">
                ${normalizeMathText(question.question)}
            </div>

            <div class="mt-3 text-sm text-gray-500">
                Bobot:
                <span class="font-semibold">
                    ${question.points}
                </span>
                poin
            </div>
        `;

        optionsEl.innerHTML = "";

        answerTableInputEl.innerHTML = "";
        answerTableInputEl.classList.add("hidden");

        renderWacana(question);
        renderPhet(question);

        if (qType === "essay") {
            renderAnswerTableInput(question);
        }

        if (!examState.answers[questionId]) {
            examState.answers[questionId] = {
                question_type: qType,
                option_id: null,
                answer_text: ""
            };
        }

        switch (qType) {

            case "likert": {
                const optionList = options[questionId] || [];
                renderOptionRadio(question, optionList);
                break;
            }

            case "multiple_choice": {
                const optionList = options[questionId] || [];
                renderOptionRadio(question, optionList);
                break;
            }

            case "reasoned_multiple_choice": {
                const optionList = options[questionId] || [];

                renderOptionRadio(question, optionList);
                renderReasonTextarea(question);

                break;
            }

            case "short_answer": {
                renderTextAnswer(
                    question,
                    "Ketik jawaban singkat Anda...",
                    "90px"
                );
                break;
            }

            case "essay": {
                renderTextAnswer(
                    question,
                    "Tuliskan jawaban uraian Anda...",
                    "180px"
                );
                break;
            }

            default: {
                optionsEl.innerHTML = `
                    <div class="p-4 rounded-xl bg-red-50 text-red-700 border border-red-200">
                        Jenis soal tidak dikenali: ${escapeHtml(qType)}
                    </div>
                `;
                break;
            }
        }

        prevBtn.disabled = currentQuestionIndex === 0;

        nextBtn.textContent =
            currentQuestionIndex === questions.length - 1
                ? "Selesai →"
                : "Soal Selanjutnya →";

        doubtBtn.textContent = examState.doubtful[questionId]
            ? "Batalkan Ragu-Ragu"
            : "Ragu-Ragu";

        renderNavigator();
        updateProgress();

        setTimeout(() => {
            renderKatexInElement(document.getElementById("wacana-content-text"));
            renderKatexInElement(questionTextEl);
            renderKatexInElement(optionsEl);
        }, 50);
    }

    /* =======================================================
        NAVIGATION EVENTS
    ======================================================= */
    prevBtn.addEventListener("click", () => {
        if (currentQuestionIndex > 0) {
            currentQuestionIndex--;
            renderQuestion();
        }
    });

    nextBtn.addEventListener("click", () => {
        if (currentQuestionIndex < questions.length - 1) {
            currentQuestionIndex++;
            renderQuestion();
            return;
        }

        if (!validateDoubtfulBeforeFinish()) {
            return;
        }

        openStopModal();
    });

    doubtBtn.addEventListener("click", () => {
        const question = questions[currentQuestionIndex];

        if (!question) return;

        const questionId = question.id;

        examState.doubtful[questionId] = !examState.doubtful[questionId];

        refreshUI();
        renderQuestion();
    });

    function getDoubtfulQuestions() {
        return questions
            .map((question, index) => ({
                id: question.id,
                number: index + 1
            }))
            .filter(item => examState.doubtful[item.id]);
    }

    function validateDoubtfulBeforeFinish() {

        const doubtfulQuestions = getDoubtfulQuestions();

        if (doubtfulQuestions.length === 0) {
            return true;
        }

        const numbers = doubtfulQuestions
            .map(item => item.number)
            .join(", ");

        Swal.fire({
            icon: "warning",

            title: "Masih Ada Soal Ragu-Ragu",

            html: `
                <div class="text-center">

                    <p class="text-gray-600 mb-3">
                        Anda masih menandai soal berikut sebagai ragu-ragu:
                    </p>

                    <div class="flex flex-wrap justify-center gap-2 mb-4">
                        ${doubtfulQuestions.map(item => `
                            <span class="
                                inline-flex items-center justify-center
                                w-10 h-10
                                rounded-xl
                                bg-yellow-100
                                text-yellow-700
                                font-bold
                                border border-yellow-300
                            ">
                                ${item.number}
                            </span>
                        `).join("")}
                    </div>

                    <p class="text-sm text-gray-500">
                        Periksa kembali soal tersebut sebelum mengakhiri ujian.
                    </p>

                </div>
            `,

            confirmButtonText: "Kembali ke Soal",

            allowOutsideClick: false,
            allowEscapeKey: false,

            customClass: {
                popup: "rounded-3xl",
                title: "text-xl font-bold",
                confirmButton: "px-6 py-3 rounded-xl font-semibold"
            },

            buttonsStyling: false,

            didOpen: () => {

                Swal.getConfirmButton().classList.add(
                    "bg-blue-600",
                    "hover:bg-blue-700",
                    "text-white",
                    "transition"
                );

            }
        });

        return false;
    }

    function getUnansweredQuestions() {
        return questions
            .map((question, index) => ({
                id: question.id,
                number: index + 1
            }))
            .filter(item => !hasAnswer(
                questions[item.number - 1],
                examState.answers[item.id]
            ));
    }

    function confirmUnansweredBeforeFinish(callback) {
        const unansweredQuestions = getUnansweredQuestions();

        if (unansweredQuestions.length === 0) {
            callback();
            return;
        }

        const numbers = unansweredQuestions
            .map(item => item.number)
            .join(", ");

        Swal.fire({

            title: "Ada Soal Belum Dijawab",

            html: `
                <div class="text-center">

                    <div class="
                        w-16 h-16
                        mx-auto mb-4
                        rounded-full
                        bg-red-100
                        flex items-center justify-center
                    ">
                        <i data-lucide="alert-triangle"
                        class="w-8 h-8 text-red-600"></i>
                    </div>

                    <p class="text-gray-600 mb-4">
                        Soal berikut masih belum dijawab:
                    </p>

                    <div class="flex flex-wrap justify-center gap-2 mb-5">
                        ${unansweredQuestions.map(item => `
                            <span class="
                                w-10 h-10
                                rounded-xl
                                bg-red-100
                                border border-red-200
                                text-red-600
                                font-semibold
                                flex items-center justify-center
                            ">
                                ${item.number}
                            </span>
                        `).join("")}
                    </div>

                    <div class="
                        bg-yellow-50
                        border border-yellow-200
                        rounded-xl
                        p-3
                        text-sm
                        text-yellow-800
                    ">
                        Jawaban pada nomor di atas masih kosong.
                    </div>

                </div>
            `,

            showCancelButton: true,
            reverseButtons: true,

            allowOutsideClick: false,
            allowEscapeKey: false,

            confirmButtonText: "Tetap Kumpulkan",
            cancelButtonText: "Periksa Lagi",

            customClass: {
                popup: "rounded-3xl",
                title: "text-xl font-bold",
                actions: "gap-4 mt-6",
                confirmButton: "px-6 py-3 rounded-2xl font-semibold min-w-[180px]",
                cancelButton: "px-6 py-3 rounded-2xl font-semibold min-w-[140px]"
            },

            buttonsStyling: false,

            didOpen: () => {

                if (window.lucide) {
                    lucide.createIcons();
                }

                const actions = Swal.getActions();

                actions.classList.add(
                    "flex",
                    "flex-row",
                    "justify-center",
                    "gap-4",
                    "mt-6"
                );

                Swal.getConfirmButton().classList.add(
                    "bg-red-600",
                    "hover:bg-red-700",
                    "text-white",
                    "shadow-sm",
                    "transition"
                );

                Swal.getCancelButton().classList.add(
                    "bg-blue-600",
                    "hover:bg-blue-700",
                    "text-white",
                    "shadow-sm",
                    "transition"
                );
            }
        })
        .then(result => {
            if (result.isConfirmed) {
                callback();
                return;
            }

            currentQuestionIndex = unansweredQuestions[0].number - 1;
            renderQuestion();
        });
    }

    /* =======================================================
        STOP MODAL EVENTS
    ======================================================= */
    function openStopModal() {
        stopModal.classList.remove("hidden");
        stopModal.classList.add("flex");
    }

    function closeStopModal() {
        stopModal.classList.add("hidden");
        stopModal.classList.remove("flex");

        stopCheckbox.checked = false;
        confirmStopBtn.disabled = true;
    }

    finishBtn.addEventListener("click", () => {
        if (!validateDoubtfulBeforeFinish()) {
            return;
        }

        openStopModal();
    });

    cancelStopBtn.addEventListener("click", closeStopModal);

    stopCheckbox.addEventListener("change", () => {
        confirmStopBtn.disabled = !stopCheckbox.checked;
    });

    stopModal.addEventListener("click", (e) => {
        if (e.target === stopModal) {
            closeStopModal();
        }
    });

    confirmStopBtn.addEventListener("click", () => {
        if (!validateDoubtfulBeforeFinish()) {
            closeStopModal();
            return;
        }

        closeStopModal();

        confirmUnansweredBeforeFinish(() => {
            submitExam(false);
        });
    });

    function buildFinalAnswerText(answer) {
        const hasTableAnswer =
            answer.table_answer &&
            typeof answer.table_answer === "object" &&
            Object.keys(answer.table_answer).length > 0;

        if (hasTableAnswer) {
            return JSON.stringify({
                text_answer: answer.answer_text || "",
                table_answer: answer.table_answer
            });
        }

        return answer.answer_text || "";
    }

    /* =======================================================
        EXAM SUBMISSION
    ======================================================= */
    function submitExam(isAutoSubmit = false, submitReason = null) {

        if (isSubmitting) return;

        isSubmitting = true;

        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }

        timerEl.classList.remove("animate-pulse", "text-red-700");

        Swal.fire({
            title: "Mengumpulkan Jawaban",
            text: "Mohon tunggu, jawaban sedang disimpan dan diproses.",
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            customClass: {
                popup: "rounded-3xl"
            },
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();

        formData.append("quiz_id", quizId);
        formData.append("student_id", studentId);
        formData.append("quiz_student_id", quizStudentId);
        formData.append("auto_submit", isAutoSubmit ? 1 : 0);

        formData.append("violation_count", getViolationCount());

        if (!submitReason) {
            submitReason = isAutoSubmit ? "time_expired" : "manual";
        }

        formData.append("submit_reason", submitReason);

        questions.forEach(question => {

            const questionId = question.id;
            const answer = examState.answers[questionId] || {};

            formData.append(`question_id[${questionId}]`, questionId);
            formData.append(`question_type[${questionId}]`, question.question_type);

            if (
                question.question_type !== "likert" &&
                answer.option_id
            ) {
                formData.append(`option_id[${questionId}]`, answer.option_id);
            }

            const finalAnswerText = buildFinalAnswerText(answer);

            formData.append(
                `answer_text[${questionId}]`,
                finalAnswerText
            );

            formData.append(
                `is_doubtful[${questionId}]`,
                examState.doubtful[questionId] ? 1 : 0
            );

        });

        fetch("../ajax/answer/submit_answer.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(text => {

            let res;

            try {
                res = JSON.parse(text);
            } catch (e) {
                console.error("RAW RESPONSE:", text);
                throw new Error("Response bukan JSON valid.");
            }

            if (res.status == 1) {

                clearExamState();

                Swal.fire({
                    icon: isAutoSubmit ? "warning" : "success",

                    title: isAutoSubmit
                        ? "Ujian Berakhir Otomatis"
                        : "Jawaban Berhasil Dikumpulkan",

                    text: isAutoSubmit
                        ? "Waktu ujian telah habis dan jawaban berhasil dikumpulkan."
                        : "Jawaban Anda berhasil dikumpulkan.",

                    allowOutsideClick: false,
                    allowEscapeKey: false,

                    confirmButtonText: "OK",

                    customClass: {
                        popup: "rounded-3xl",
                        confirmButton: "rounded-xl px-6 py-3"
                    },

                    buttonsStyling: false,

                    didOpen: () => {

                        Swal.getConfirmButton().classList.add(
                            "bg-blue-600",
                            "hover:bg-blue-700",
                            "text-white",
                            "transition"
                        );

                    }
                }).then(() => {

                    window.location.replace(
                        `view_answer.php?id=${quizId}`
                    );

                });

                return;
            }

            isSubmitting = false;

            Swal.fire({
                icon: "error",
                title: "Gagal Mengumpulkan",
                text: res.msg || res.message || "Gagal menyimpan jawaban.",
                confirmButtonText: "OK",
                customClass: {
                    popup: "rounded-3xl",
                    confirmButton: "rounded-xl px-6 py-3"
                },
                buttonsStyling: false,
                didOpen: () => {
                    Swal.getConfirmButton().classList.add(
                        "bg-blue-600",
                        "hover:bg-blue-700",
                        "text-white",
                        "transition"
                    );
                }
            });

        })
        .catch(error => {

            console.error(error);

            isSubmitting = false;

            Swal.fire({
                icon: "error",
                title: "Terjadi Kesalahan",
                text: "Terjadi kesalahan saat mengirim jawaban.",
                confirmButtonText: "OK",
                customClass: {
                    popup: "rounded-3xl",
                    confirmButton: "rounded-xl px-6 py-3"
                },
                buttonsStyling: false,
                didOpen: () => {
                    Swal.getConfirmButton().classList.add(
                        "bg-blue-600",
                        "hover:bg-blue-700",
                        "text-white",
                        "transition"
                    );
                }
            });

        });
    }

    /* =======================================================
        FULLSCREEN EXAM MODE
    ======================================================= */
    function isFullscreenActive() {
        return (
            document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.msFullscreenElement
        );
    }

    function enterExamFullscreen() {
        const el = document.documentElement;

        if (el.requestFullscreen) {
            return el.requestFullscreen();
        }

        if (el.webkitRequestFullscreen) {
            return el.webkitRequestFullscreen();
        }

        if (el.msRequestFullscreen) {
            return el.msRequestFullscreen();
        }

        return Promise.resolve();
    }

    function showFullscreenOverlay() {
        if (!fullscreenOverlay) return;

        fullscreenOverlay.classList.remove("hidden");
        fullscreenOverlay.classList.add("flex");
    }

    function hideFullscreenOverlay() {
        if (!fullscreenOverlay) return;

        fullscreenOverlay.classList.add("hidden");
        fullscreenOverlay.classList.remove("flex");
    }

    enterFullscreenBtn.addEventListener("click", () => {
        enterExamFullscreen()
            .then(() => {
                if (isFullscreenActive()) {
                    hideFullscreenOverlay();
                    stopFullscreenPenaltyTimer();
                } else {
                    showFullscreenOverlay();
                }
            })
            .catch(() => {
                showFullscreenOverlay();
            });
    });

    /* =======================================================
        VIOLATION SYSTEM
    ======================================================= */
    document.addEventListener("fullscreenchange", () => {

        if (isSubmitting) return;

        if (isFullscreenActive()) {

            hideFullscreenOverlay();

            stopFullscreenPenaltyTimer();

            return;
        }

        showFullscreenOverlay();

        handleViolation(
            "Anda keluar dari mode fullscreen."
        );

        startFullscreenPenaltyTimer();

    });

    /* =======================================================
        PAGE INITIALIZATION
    ======================================================= */
    loadExamState();

    currentQuestionIndex =
        parseInt(examState.currentQuestionIndex || 0);

    renderQuestion();
    updateProgress();
    renderNavigator();
    startTimer();
    updateViolationCounter();
    if (isFullscreenActive()) {
        hideFullscreenOverlay();
    }

    if (window.lucide) {
        lucide.createIcons();
    }
    </script>

</body>
</html>