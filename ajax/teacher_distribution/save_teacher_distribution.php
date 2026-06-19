<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   ACCESS CONTROL
======================================================= */
if (!isset($_SESSION['login_id'])) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Akses ditolak.'
    ]);
    exit;
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin = ($userType === 1);

if (!$isAdmin) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Hanya administrator yang dapat mengelola distribusi pengajar.'
    ]);
    exit;
}

/* =======================================================
   INPUT
======================================================= */
$id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$teacherIds = [];

if (isset($_POST['teacher_ids']) && is_array($_POST['teacher_ids'])) {
    $teacherIds = $_POST['teacher_ids'];
} elseif (isset($_POST['teacher_id']) && $_POST['teacher_id'] !== '') {
    $teacherIds = [$_POST['teacher_id']];
}
$classIds   = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];
$subjectId  = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$status     = isset($_POST['status']) ? (int) $_POST['status'] : 1;

/* =======================================================
   VALIDATION
======================================================= */
if ($subjectId <= 0) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Mata pelajaran tidak valid.'
    ]);
    exit;
}

if (empty($teacherIds) || !is_array($teacherIds)) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Minimal satu pengajar wajib dipilih.'
    ]);
    exit;
}

if (empty($classIds) || !is_array($classIds)) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Minimal satu kelas wajib dipilih.'
    ]);
    exit;
}

/* =======================================================
   SANITIZE
======================================================= */
$teacherIds = array_unique(array_map('intval', $teacherIds));
$classIds   = array_unique(array_map('intval', $classIds));

/* =======================================================
   VALIDATE SUBJECT
======================================================= */
$subjectCheck = $conn->query("
    SELECT id
    FROM subjects
    WHERE id = {$subjectId}
    LIMIT 1
");

if (!$subjectCheck || $subjectCheck->num_rows <= 0) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Mata pelajaran tidak ditemukan.'
    ]);
    exit;
}

/* =======================================================
   VALIDATE TEACHERS
======================================================= */
foreach ($teacherIds as $teacherId) {

    $teacherCheck = $conn->query("
        SELECT id
        FROM teachers
        WHERE id = {$teacherId}
        LIMIT 1
    ");

    if (!$teacherCheck || $teacherCheck->num_rows <= 0) {
        echo json_encode([
            'status' => 2,
            'msg' => 'Pengajar tidak valid.'
        ]);
        exit;
    }
}

/* =======================================================
   VALIDATE CLASSES
======================================================= */
foreach ($classIds as $classId) {

    $classCheck = $conn->query("
        SELECT id
        FROM classes
        WHERE id = {$classId}
        LIMIT 1
    ");

    if (!$classCheck || $classCheck->num_rows <= 0) {
        echo json_encode([
            'status' => 2,
            'msg' => 'Kelas tidak valid.'
        ]);
        exit;
    }
}

/* =======================================================
   CONFLICT VALIDATION:
   1 KELAS + 1 MAPEL = 1 PENGAJAR AKTIF
======================================================= */
foreach ($classIds as $classId) {

    foreach ($teacherIds as $teacherId) {

        $conflictSql = "
            SELECT id
            FROM teacher_class_assignments
            WHERE class_id = {$classId}
            AND subject_id = {$subjectId}
            AND status = 1
            AND teacher_id != {$teacherId}
        ";

        if ($id > 0) {
            $conflictSql .= " AND id != {$id}";
        }

        $conflictSql .= " LIMIT 1";

        $conflictCheck = $conn->query($conflictSql);

        if ($conflictCheck && $conflictCheck->num_rows > 0) {

            echo json_encode([
                'status' => 2,
                'msg' => 'Salah satu kelas sudah memiliki pengajar aktif untuk mata pelajaran ini.'
            ]);
            exit;
        }
    }
}

/* =======================================================
   EDIT MODE
======================================================= */
if ($id > 0) {

    $teacherId = $teacherIds[0];
    $classId   = $classIds[0];

    $existingCheck = $conn->query("
        SELECT id
        FROM teacher_class_assignments
        WHERE id = {$id}
        LIMIT 1
    ");

    if (!$existingCheck || $existingCheck->num_rows <= 0) {
        echo json_encode([
            'status' => 2,
            'msg' => 'Distribusi tidak ditemukan.'
        ]);
        exit;
    }

    $duplicateCheck = $conn->query("
        SELECT id
        FROM teacher_class_assignments
        WHERE teacher_id = {$teacherId}
        AND class_id = {$classId}
        AND subject_id = {$subjectId}
        AND id != {$id}
        LIMIT 1
    ");

    if ($duplicateCheck && $duplicateCheck->num_rows > 0) {
        echo json_encode([
            'status' => 2,
            'msg' => 'Distribusi serupa sudah ada.'
        ]);
        exit;
    }

    $update = $conn->query("
        UPDATE teacher_class_assignments
        SET
            teacher_id = {$teacherId},
            class_id = {$classId},
            subject_id = {$subjectId},
            status = {$status},
            updated_at = NOW()
        WHERE id = {$id}
        LIMIT 1
    ");

    if ($update) {
        echo json_encode([
            'status' => 1,
            'msg' => 'Distribusi pengajar berhasil diperbarui.'
        ]);
    } else {
        echo json_encode([
            'status' => 0,
            'msg' => 'Gagal memperbarui distribusi.'
        ]);
    }

    exit;
}

/* =======================================================
   CREATE MODE
======================================================= */
$conn->begin_transaction();

try {

    foreach ($teacherIds as $teacherId) {

        foreach ($classIds as $classId) {

        $duplicateCheck = $conn->query("
            SELECT id, status
            FROM teacher_class_assignments
            WHERE teacher_id = {$teacherId}
            AND class_id = {$classId}
            AND subject_id = {$subjectId}
            LIMIT 1
        ");

        if ($duplicateCheck && $duplicateCheck->num_rows > 0) {

            $existing = $duplicateCheck->fetch_assoc();

            /* =======================================================
            JIKA DATA NONAKTIF, AKTIFKAN KEMBALI
            ======================================================= */
            if ((int)$existing['status'] === 0) {

                $reactivate = $conn->query("
                    UPDATE teacher_class_assignments
                    SET
                        status = 1,
                        updated_at = NOW()
                    WHERE id = {$existing['id']}
                    LIMIT 1
                ");

                if (!$reactivate) {
                    throw new Exception("Gagal mengaktifkan kembali distribusi pengajar.");
                }

            }

            /* =======================================================
            JIKA SUDAH AKTIF, LEWATI
            ======================================================= */
            continue;
        }

            $insert = $conn->query("
                INSERT INTO teacher_class_assignments (
                    teacher_id,
                    class_id,
                    subject_id,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    {$teacherId},
                    {$classId},
                    {$subjectId},
                    {$status},
                    NOW(),
                    NOW()
                )
            ");

            if (!$insert) {
                throw new Exception("Gagal menyimpan distribusi pengajar.");
            }
        }
    }

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'msg' => 'Distribusi pengajar berhasil disimpan.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 0,
        'msg' => $e->getMessage()
    ]);
}