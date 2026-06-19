<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (
    !isset($_SESSION['login_user_type']) ||
    !in_array((int)$_SESSION['login_user_type'], [1, 2])
) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Akses ditolak.'
    ]);
    exit;
}

$userId = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin = $userType === 1;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'ID PhET tidak valid.'
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, user_id
    FROM phet
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    $stmt->close();

    echo json_encode([
        'status' => 0,
        'msg' => 'Data PhET tidak ditemukan.'
    ]);
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

if (!$isAdmin && (int)$data['user_id'] !== $userId) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Anda tidak memiliki izin untuk menghapus PhET ini.'
    ]);
    exit;
}

/* =======================================================
   CHECK USAGE IN QUESTIONS
======================================================= */
$usageStmt = $conn->prepare("
    SELECT
        q.id AS question_id,
        q.order_by,
        q.quiz_id,
        ql.quiz_title

    FROM questions q

    LEFT JOIN quiz_list ql
        ON q.quiz_id = ql.id

    WHERE q.phet_id = ?

    ORDER BY q.quiz_id ASC
");

$usageStmt->bind_param("i", $id);
$usageStmt->execute();

$usageResult = $usageStmt->get_result();

if ($usageResult->num_rows > 0) {

    $usageDetails = [];

    while ($usage = $usageResult->fetch_assoc()) {

        $usageDetails[] =
            $usage['quiz_id']
            . '##'
            . $usage['question_id']
            . '##'
            . ($usage['quiz_title'] ?? 'Quiz Tidak Diketahui')
            . ' — Soal No. '
            . ($usage['order_by'] ?? '-');
    }

    $usageStmt->close();

    echo json_encode([
        'status' => 0,
        'msg' => 'PhET masih digunakan pada soal dan tidak dapat dihapus.',
        'usage_detail' => implode('||', $usageDetails)
    ]);

    exit;
}

$usageStmt->close();

/* =======================================================
   DELETE TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    $accessStmt = $conn->prepare("
        DELETE FROM phet_teacher_access
        WHERE phet_id = ?
    ");
    $accessStmt->bind_param("i", $id);
    $accessStmt->execute();
    $accessStmt->close();

    /*
       Optional legacy cleanup.
       Aman dipertahankan jika tabel question_phet_map masih ada.
       Jika tabel ini sudah tidak digunakan, blok ini tetap aman.
    */
    $legacyCheck = $conn->query("SHOW TABLES LIKE 'question_phet_map'");

    if ($legacyCheck && $legacyCheck->num_rows > 0) {
        $mappingStmt = $conn->prepare("
            DELETE FROM question_phet_map
            WHERE phet_id = ?
        ");
        $mappingStmt->bind_param("i", $id);
        $mappingStmt->execute();
        $mappingStmt->close();
    }

    $deleteStmt = $conn->prepare("
        DELETE FROM phet
        WHERE id = ?
        LIMIT 1
    ");
    $deleteStmt->bind_param("i", $id);

    if (!$deleteStmt->execute()) {
        throw new Exception('Gagal menghapus PhET.');
    }

    $deleteStmt->close();

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'msg' => 'PhET berhasil dihapus.'
    ]);
    exit;

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 0,
        'msg' => $e->getMessage()
    ]);
    exit;
}
?>