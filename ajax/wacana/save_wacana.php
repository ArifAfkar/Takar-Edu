<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

$response = [
    'status' => 0,
    'msg'    => 'Terjadi kesalahan sistem.'
];

if (
    !isset($_SESSION['login_user_type']) ||
    !in_array((int)$_SESSION['login_user_type'], [1, 2])
) {
    $response['msg'] = 'Akses ditolak.';
    echo json_encode($response);
    exit;
}

$userId    = (int) $_SESSION['login_id'];
$userType  = (int) $_SESSION['login_user_type'];
$isAdmin   = $userType === 1;
$isTeacher = $userType === 2;

$id              = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$wacanaTitle     = trim($_POST['wacana_title'] ?? '');
$subjectId       = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$description     = trim($_POST['description'] ?? '');
$visibilityScope = isset($_POST['visibility_scope']) ? (int) $_POST['visibility_scope'] : 1;
$status          = isset($_POST['status']) ? (int) $_POST['status'] : 1;

$teacherIds = isset($_POST['teacher_ids']) && is_array($_POST['teacher_ids'])
    ? array_map('intval', $_POST['teacher_ids'])
    : [];

$teacherIds = array_values(array_unique(array_filter($teacherIds)));
$gradeLevel = trim($_POST['grade_level'] ?? '');

if ($wacanaTitle === '') {
    echo json_encode([
        'status' => 2,
        'msg' => 'Judul wacana wajib diisi.'
    ]);
    exit;
}

if ($subjectId <= 0) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Mata pelajaran wajib dipilih.'
    ]);
    exit;
}

if ($description === '') {
    echo json_encode([
        'status' => 2,
        'msg' => 'Isi wacana wajib diisi.'
    ]);
    exit;
}

if (!in_array($visibilityScope, [1, 2, 3])) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Akses wacana tidak valid.'
    ]);
    exit;
}

if (!in_array($status, [0, 1])) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Status wacana tidak valid.'
    ]);
    exit;
}

/* Pengajar hanya boleh membuat wacana sesuai mapelnya */
if ($isTeacher) {

    $teacherCheck = $conn->prepare("
        SELECT subject_id
        FROM teachers
        WHERE user_id = ?
        LIMIT 1
    ");

    $teacherCheck->bind_param("i", $userId);
    $teacherCheck->execute();
    $teacherResult = $teacherCheck->get_result();

    if ($teacherResult->num_rows <= 0) {
        echo json_encode([
            'status' => 0,
            'msg' => 'Data pengajar tidak ditemukan.'
        ]);
        exit;
    }

    $teacherData = $teacherResult->fetch_assoc();
    $teacherCheck->close();

    if ((int)$teacherData['subject_id'] !== $subjectId) {
        echo json_encode([
            'status' => 0,
            'msg' => 'Anda hanya dapat membuat wacana sesuai mapel Anda.'
        ]);
        exit;
    }

    $visibilityScope = 1;
    $teacherIds = [];
}

/* Cek mapel */
$subjectCheck = $conn->prepare("
    SELECT id
    FROM subjects
    WHERE id = ?
    LIMIT 1
");

$subjectCheck->bind_param("i", $subjectId);
$subjectCheck->execute();
$subjectResult = $subjectCheck->get_result();

if ($subjectResult->num_rows <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Mata pelajaran tidak ditemukan.'
    ]);
    exit;
}

$subjectCheck->close();

if ($isAdmin && $visibilityScope === 2 && empty($teacherIds)) {
    echo json_encode([
        'status' => 2,
        'msg' => 'Pilih minimal satu pengajar penerima akses.'
    ]);
    exit;
}

$conn->begin_transaction();

try {

    if ($id <= 0) {

        $stmt = $conn->prepare("
            INSERT INTO wacana (
                wacana_title,
                subject_id,
                description,
                user_id,
                creator_role,
                visibility_scope,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->bind_param(
            "sisiiii",
            $wacanaTitle,
            $subjectId,
            $description,
            $userId,
            $userType,
            $visibilityScope,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception('Gagal menambahkan wacana.');
        }

        $wacanaId = $stmt->insert_id;
        $stmt->close();

        if ($isAdmin && $visibilityScope === 2 && !empty($teacherIds)) {

            $accessStmt = $conn->prepare("
                INSERT INTO wacana_teacher_access
                    (wacana_id, teacher_id, grade_level, assigned_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");

            foreach ($teacherIds as $teacherId) {
                $gradeLevel = trim($_POST['grade_level'] ?? '');

                $accessStmt->bind_param(
                    "iis",
                    $wacanaId,
                    $teacherId,
                    $gradeLevel
                );
                $accessStmt->execute();
            }

            $accessStmt->close();
        }

        $response = [
            'status' => 1,
            'msg' => 'Wacana berhasil ditambahkan.'
        ];

    } else {

        $oldStmt = $conn->prepare("
            SELECT user_id
            FROM wacana
            WHERE id = ?
            LIMIT 1
        ");

        $oldStmt->bind_param("i", $id);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result();

        if ($oldResult->num_rows <= 0) {
            throw new Exception('Data wacana tidak ditemukan.');
        }

        $oldData = $oldResult->fetch_assoc();
        $oldStmt->close();

        if (!$isAdmin && (int)$oldData['user_id'] !== $userId) {
            throw new Exception('Anda tidak memiliki akses untuk mengedit data ini.');
        }

        $stmt = $conn->prepare("
            UPDATE wacana
            SET
                wacana_title = ?,
                subject_id = ?,
                description = ?,
                visibility_scope = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sisiii",
            $wacanaTitle,
            $subjectId,
            $description,
            $visibilityScope,
            $status,
            $id
        );

        if (!$stmt->execute()) {
            throw new Exception('Gagal memperbarui wacana.');
        }

        $stmt->close();

        $deleteAccessStmt = $conn->prepare("
            DELETE FROM wacana_teacher_access
            WHERE wacana_id = ?
        ");

        $deleteAccessStmt->bind_param("i", $id);
        $deleteAccessStmt->execute();
        $deleteAccessStmt->close();

        if ($isAdmin && $visibilityScope === 2 && !empty($teacherIds)) {

            $accessStmt = $conn->prepare("
                INSERT INTO wacana_teacher_access
                    (wacana_id, teacher_id, grade_level, assigned_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");

            foreach ($teacherIds as $teacherId) {
                $accessStmt->bind_param(
                    "iis",
                    $id,
                    $teacherId,
                    $gradeLevel
                );
                $accessStmt->execute();
            }

            $accessStmt->close();
        }

        $response = [
            'status' => 1,
            'msg' => 'Wacana berhasil diperbarui.'
        ];
    }

    $conn->commit();

} catch (Exception $e) {

    $conn->rollback();

    $response = [
        'status' => 0,
        'msg' => $e->getMessage()
    ];
}

echo json_encode($response);
exit;
?>