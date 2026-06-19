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
        'msg' => 'ID wacana tidak valid.'
    ]);
    exit;
}

/* =======================================================
   GET WACANA DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT id, user_id, description
    FROM wacana
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
        'msg' => 'Data wacana tidak ditemukan.'
    ]);
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

if (!$isAdmin && (int)$data['user_id'] !== $userId) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Anda tidak memiliki izin untuk menghapus wacana ini.'
    ]);
    exit;
}

$description = $data['description'] ?? '';

/* =======================================================
   CHECK USAGE IN QUESTIONS
======================================================= */
$usageStmt = $conn->prepare("
    SELECT
        q.id AS question_id,
        q.quiz_id,
        q.order_by,
        ql.quiz_title
    FROM questions q
    LEFT JOIN quiz_list ql
        ON q.quiz_id = ql.id
    WHERE q.wacana_id = ?
    ORDER BY q.quiz_id ASC, q.order_by ASC
");

$usageStmt->bind_param("i", $id);
$usageStmt->execute();
$usageResult = $usageStmt->get_result();

if ($usageResult && $usageResult->num_rows > 0) {

    $usageDetails = [];

    while ($usage = $usageResult->fetch_assoc()) {

        $usageDetails[] =
            (int)$usage['quiz_id'] .
            '##' .
            (int)$usage['question_id'] .
            '##' .
            ($usage['quiz_title'] ?: 'Quiz Tidak Diketahui') .
            ' — Soal No. ' .
            ($usage['order_by'] ?: '-');

    }

    $usageStmt->close();

    echo json_encode([
        'status' => 0,
        'msg' => 'Wacana masih digunakan pada soal dan tidak dapat dihapus.',
        'usage_detail' => implode('||', $usageDetails)
    ]);
    exit;
}

$usageStmt->close();

/* =======================================================
   COLLECT CKEDITOR IMAGES FROM DESCRIPTION
======================================================= */
$editorImages = [];

if (!empty($description)) {

    preg_match_all(
        '/<img[^>]+src=["\']([^"\']+)["\']/i',
        $description,
        $matches
    );

    if (!empty($matches[1])) {

        foreach ($matches[1] as $src) {

            $src = trim($src);

            if (
                strpos($src, 'uploads/editor/') !== false ||
                strpos($src, '../uploads/editor/') !== false
            ) {
                $fileName = basename(parse_url($src, PHP_URL_PATH));

                if (!empty($fileName)) {
                    $editorImages[] = $fileName;
                }
            }

        }

        $editorImages = array_values(array_unique($editorImages));
    }
}

/* =======================================================
   DELETE TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    $accessStmt = $conn->prepare("
        DELETE FROM wacana_teacher_access
        WHERE wacana_id = ?
    ");

    $accessStmt->bind_param("i", $id);
    $accessStmt->execute();
    $accessStmt->close();

    $legacyCheck = $conn->query("SHOW TABLES LIKE 'question_wacana_map'");

    if ($legacyCheck && $legacyCheck->num_rows > 0) {

        $mappingStmt = $conn->prepare("
            DELETE FROM question_wacana_map
            WHERE wacana_id = ?
        ");

        $mappingStmt->bind_param("i", $id);
        $mappingStmt->execute();
        $mappingStmt->close();

    }

    $deleteStmt = $conn->prepare("
        DELETE FROM wacana
        WHERE id = ?
        LIMIT 1
    ");

    $deleteStmt->bind_param("i", $id);

    if (!$deleteStmt->execute()) {
        throw new Exception('Gagal menghapus wacana.');
    }

    $deleteStmt->close();

    $conn->commit();

    /* =======================================================
       DELETE CKEDITOR IMAGES
    ======================================================= */
    foreach ($editorImages as $editorImage) {

        $editorFilePath = '../../uploads/editor/' . basename($editorImage);

        if (file_exists($editorFilePath)) {
            unlink($editorFilePath);
        }

    }

    echo json_encode([
        'status' => 1,
        'msg' => 'Wacana berhasil dihapus.'
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