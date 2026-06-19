<?php
/* =======================================================
    FILE: ajax/quiz/save_quiz.php
    FINAL VERSION (FIXED)
    - Fix foreign key quiz_teacher_list
    - Fix insert/update logic
    - Quiz berbasis teacher distribution
    - Auto teacher assignment
    - Auto student assignment
    - Support admin & teacher
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (!isset($_SESSION['login_id'])) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Unauthorized access."
    ]);
    exit;
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);

if (!$isAdmin && !$isTeacher) {
    echo json_encode([
        "status" => 0,
        "msg"    => "Akses ditolak."
    ]);
    exit;
}

/* =======================================================
    INPUT VALIDATION
======================================================= */
$id           = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$title        = strip_tags(trim($_POST['quiz_title'] ?? ''));
$description  = strip_tags(trim($_POST['description'] ?? ''));

if ($description === '') {
    $description = null;
}

$quizDuration = isset($_POST['quiz_duration']) ? (int) $_POST['quiz_duration'] : 0;
$status       = isset($_POST['status']) ? (int) $_POST['status'] : 1;
$openAt       = trim($_POST['open_at'] ?? '');
$dueDate      = trim($_POST['due_date'] ?? '');

if (!in_array($status, [0, 1])) {
    $status = 1;
}

if ($quizDuration > 600) {
    $quizDuration = 600;
}

if (
    empty($title) ||
    $quizDuration <= 0 ||
    empty($openAt) ||
    empty($dueDate)
) {
    echo json_encode([
        "status" => 2,
        "msg"    => "Semua field wajib diisi."
    ]);
    exit;
}

/* =======================================================
    DATE VALIDATION
======================================================= */
if (!strtotime($openAt) || !strtotime($dueDate)) {
    echo json_encode([
        "status" => 2,
        "msg"    => "Format jadwal tidak valid."
    ]);
    exit;
}

if (strtotime($dueDate) <= strtotime($openAt)) {
    echo json_encode([
        "status" => 2,
        "msg"    => "Batas akhir harus lebih besar dari waktu mulai."
    ]);
    exit;
}

/* =======================================================
    DETERMINE TEACHER IDS
======================================================= */
if ($isAdmin) {

    if (
        !isset($_POST['teacher_class_ids']) ||
        !is_array($_POST['teacher_class_ids']) ||
        count($_POST['teacher_class_ids']) === 0
    ) {
        echo json_encode([
            "status" => 2,
            "msg"    => "Minimal satu pengajar dan kelas wajib dipilih."
        ]);
        exit;
    }

    $teacherIds = [];
    $classIds   = [];

    foreach ($_POST['teacher_class_ids'] as $item) {

        $parts = explode('|', $item);

        if (count($parts) !== 2) {
            continue;
        }

        $teacherId = (int) $parts[0];
        $classId   = (int) $parts[1];

        if ($teacherId > 0 && $classId > 0) {
            $teacherIds[] = $teacherId;
            $classIds[]   = $classId;
        }

    }

    $teacherIds = array_values(array_unique($teacherIds));
    $classIds   = array_values(array_unique($classIds));

    if (count($teacherIds) === 0 || count($classIds) === 0) {
        echo json_encode([
            "status" => 2,
            "msg"    => "Data pengajar atau kelas tidak valid."
        ]);
        exit;
    }

} else {

    $teacherQuery = $conn->query("
        SELECT id
        FROM teachers
        WHERE user_id = {$userId}
        LIMIT 1
    ");

    if (!$teacherQuery || $teacherQuery->num_rows === 0) {
        echo json_encode([
            "status" => 0,
            "msg"    => "Data pengajar tidak ditemukan."
        ]);
        exit;
    }

    $teacherData = $teacherQuery->fetch_assoc();
    $teacherIds[] = (int) $teacherData['id'];

    $classIds = [];

    $classQuery = $conn->query("
        SELECT DISTINCT class_id
        FROM teacher_class_assignments
        WHERE teacher_id = {$teacherIds[0]}
        AND status = 1
    ");

    if ($classQuery) {
        while ($class = $classQuery->fetch_assoc()) {
            $classIds[] = (int) $class['class_id'];
        }
    }
}

/* =======================================================
    DUPLICATE CHECK
======================================================= */
$titleEsc = $conn->real_escape_string($title);

$duplicate = $conn->query("
    SELECT id
    FROM quiz_list
    WHERE quiz_title = '{$titleEsc}'
    AND created_by = {$userId}
    AND id != {$id}
    LIMIT 1
");

if ($duplicate && $duplicate->num_rows > 0) {
    echo json_encode([
        "status" => 2,
        "msg"    => "Judul kuis sudah digunakan."
    ]);
    exit;
}

/* =======================================================
    ESCAPE DATA
======================================================= */
$descriptionSql = is_null($description)
    ? "NULL"
    : "'" . $conn->real_escape_string($description) . "'";
$openAtEsc      = $conn->real_escape_string($openAt);
$dueDateEsc     = $conn->real_escape_string($dueDate);

/* =======================================================
    TRANSACTION START
======================================================= */
$conn->begin_transaction();

try {

    /* =======================================================
        INSERT QUIZ BARU
    ======================================================= */
    if ($id <= 0) {

        $successMessage = "Data kuis berhasil ditambahkan.";

        $save = $conn->query("
            INSERT INTO quiz_list (
                quiz_title,
                description,
                created_by,
                quiz_duration,
                status,
                open_at,
                due_date,
                created_at,
                updated_at
            ) VALUES (
                '{$titleEsc}',
                {$descriptionSql},
                {$userId},
                {$quizDuration},
                {$status},
                '{$openAtEsc}',
                '{$dueDateEsc}',
                NOW(),
                NOW()
            )
        ");

        if (!$save) {
            throw new Exception("Gagal menambahkan kuis.");
        }

        $quizId = $conn->insert_id;

    }

    /* =======================================================
        UPDATE QUIZ
    ======================================================= */
    else {

        if ($isTeacher) {

            $ownershipCheck = $conn->query("
                SELECT id
                FROM quiz_list
                WHERE id = {$id}
                AND created_by = {$userId}
                LIMIT 1
            ");

            if (!$ownershipCheck || $ownershipCheck->num_rows === 0) {
                throw new Exception("Anda tidak memiliki akses untuk mengubah kuis ini.");
            }

        }

        $successMessage = "Data kuis berhasil diperbarui.";
        $quizId = $id;

        $save = $conn->query("
            UPDATE quiz_list SET
                quiz_title    = '{$titleEsc}',
                description   = {$descriptionSql},
                quiz_duration = {$quizDuration},
                status        = {$status},
                open_at       = '{$openAtEsc}',
                due_date      = '{$dueDateEsc}',
                updated_at    = NOW()
            WHERE id = {$quizId}
        ");

        if (!$save) {
            throw new Exception("Gagal memperbarui kuis.");
        }

        /* Reset distribusi pengajar lama */
        $conn->query("
            DELETE FROM quiz_teacher_list
            WHERE quiz_id = {$quizId}
        ");

        /*
            Jangan hapus quiz_student_list.
            Data siswa yang sudah mulai / selesai harus tetap aman.

            status:
            0 = nonaktif
            1 = ditugaskan / belum mulai
            2 = sedang mengerjakan
            3 = selesai
        */
        $conn->query("
            UPDATE quiz_student_list
            SET
                status = 0,
                updated_at = NOW()
            WHERE quiz_id = {$quizId}
            AND status = 1
        ");
    }

    /* =======================================================
        INSERT TEACHER + CLASS DISTRIBUTION
    ======================================================= */
    foreach ($_POST['teacher_class_ids'] as $item) {

        $parts = explode('|', $item);

        if (count($parts) !== 2) {
            continue;
        }

        $teacherId = (int)$parts[0];
        $classId   = (int)$parts[1];

        if ($teacherId <= 0 || $classId <= 0) {
            continue;
        }

        $assignTeacher = $conn->query("
            INSERT INTO quiz_teacher_list (
                quiz_id,
                teacher_id,
                class_id,
                assigned_at,
                updated_at
            ) VALUES (
                {$quizId},
                {$teacherId},
                {$classId},
                NOW(),
                NOW()
            )
        ");

        if (!$assignTeacher) {

            throw new Exception(
                "Gagal mendistribusikan pengajar dan kelas."
            );

        }
    }

    /* =======================================================
        AUTO DISTRIBUTE STUDENTS BY SELECTED CLASSES
    ======================================================= */
    $classIds = array_values(array_unique(array_filter($classIds)));

    if (count($classIds) === 0) {
        throw new Exception("Tidak ada kelas target yang dipilih.");
    }

    $classIdList = implode(',', array_map('intval', $classIds));

    $studentQuery = $conn->query("
        SELECT id
        FROM students
        WHERE class_id IN ({$classIdList})
    ");

    if (!$studentQuery) {
        throw new Exception("Gagal membaca data siswa.");
    }

    while ($student = $studentQuery->fetch_assoc()) {

        $studentId = (int) $student['id'];

        $checkStudent = $conn->query("
            SELECT id
            FROM quiz_student_list
            WHERE quiz_id = {$quizId}
            AND student_id = {$studentId}
            LIMIT 1
        ");

        if ($checkStudent && $checkStudent->num_rows === 0) {

            $assignStudent = $conn->query("
                INSERT INTO quiz_student_list (
                    quiz_id,
                    student_id,
                    status,
                    assigned_at,
                    updated_at
                ) VALUES (
                    {$quizId},
                    {$studentId},
                    1,
                    NOW(),
                    NOW()
                )
            ");

            if (!$assignStudent) {
                throw new Exception("Gagal mendistribusikan siswa.");
            }

        } else {

            /*
                Jika siswa sebelumnya dinonaktifkan karena edit distribusi,
                aktifkan kembali hanya kalau belum pernah mulai mengerjakan.
            */
            $reactivateStudent = $conn->query("
                UPDATE quiz_student_list
                SET
                    status = 1,
                    updated_at = NOW()
                WHERE quiz_id = {$quizId}
                AND student_id = {$studentId}
                AND status = 0
                AND started_at IS NULL
                AND completed_at IS NULL
            ");

            if (!$reactivateStudent) {
                throw new Exception("Gagal mengaktifkan ulang distribusi siswa.");
            }
        }

    }

    /* =======================================================
        COMMIT
    ======================================================= */
    $conn->commit();

    echo json_encode([
        "status"  => 1,
        "msg"     => $successMessage,
        "quiz_id" => $quizId
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => 0,
        "msg"    => $e->getMessage()
    ]);
}
?>