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

$userType = (int) $_SESSION['login_user_type'];

if ($userType !== 1) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Hanya administrator yang dapat menghapus distribusi pengajar.'
    ]);
    exit;
}

/* =======================================================
   INPUT
======================================================= */
$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'ID distribusi tidak valid.'
    ]);
    exit;
}

/* =======================================================
   VALIDATE DISTRIBUTION
======================================================= */
$checkQuery = $conn->query("
    SELECT
        tca.id,
        tca.teacher_id,
        tca.class_id,
        tca.subject_id
    FROM teacher_class_assignments tca
    WHERE tca.id = {$id}
    AND tca.status = 1
    LIMIT 1
");

if (!$checkQuery || $checkQuery->num_rows <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Distribusi tidak ditemukan.'
    ]);
    exit;
}

$distribution = $checkQuery->fetch_assoc();

/* =======================================================
   OPTIONAL RELATION VALIDATION
======================================================= */
/*
   Jika ingin mencegah distribusi dihapus ketika:
   - sudah ada quiz aktif
   - sudah ada distribusi siswa
   - sudah ada hasil siswa

   Aktifkan validasi berikut.
*/
/*
$usageCheck = $conn->query("
    SELECT id
    FROM quiz_teacher_list
    WHERE teacher_id = {$distribution['teacher_id']}
    LIMIT 1
");

if ($usageCheck && $usageCheck->num_rows > 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Distribusi masih digunakan pada kuis dan tidak dapat dihapus.'
    ]);
    exit;
}
*/

/* =======================================================
   SOFT DELETE (RECOMMENDED)
======================================================= */
$conn->begin_transaction();

try {

    $delete = $conn->query("
        UPDATE teacher_class_assignments
        SET
            status = 0,
            updated_at = NOW()
        WHERE id = {$id}
        AND status = 1
        LIMIT 1
    ");

    if (!$delete) {
        throw new Exception("Gagal menghapus distribusi pengajar.");
    }

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'msg' => 'Distribusi pengajar berhasil dihapus.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 0,
        'msg' => $e->getMessage()
    ]);
}