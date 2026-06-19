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

$id               = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$phetTitle        = trim($_POST['phet_title'] ?? '');
$subjectId        = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
$description      = trim($_POST['description'] ?? '');

if ($description === '') {
    $description = null;
}

$originalUrl      = trim($_POST['original_url'] ?? '');
$visibilityScope  = isset($_POST['visibility_scope']) ? (int) $_POST['visibility_scope'] : 1;
$status           = isset($_POST['status']) ? (int) $_POST['status'] : 1;

$teacherIds = isset($_POST['teacher_ids']) && is_array($_POST['teacher_ids'])
    ? array_map('intval', $_POST['teacher_ids'])
    : [];

$teacherIds = array_values(array_unique(array_filter($teacherIds)));

$gradeLevel = trim($_POST['grade_level'] ?? '');

if ($phetTitle === '') {
    $response['status'] = 2;
    $response['msg'] = 'Judul PhET wajib diisi.';
    echo json_encode($response);
    exit;
}

if ($subjectId <= 0) {
    $response['status'] = 2;
    $response['msg'] = 'Mata pelajaran wajib dipilih.';
    echo json_encode($response);
    exit;
}

if ($originalUrl === '') {
    $response['status'] = 2;
    $response['msg'] = 'URL asli PhET wajib diisi.';
    echo json_encode($response);
    exit;
}

if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
    $response['status'] = 2;
    $response['msg'] = 'URL PhET tidak valid.';
    echo json_encode($response);
    exit;
}

if (stripos($originalUrl, 'phet.colorado.edu') === false) {
    $response['status'] = 2;
    $response['msg'] = 'URL harus berasal dari situs resmi PhET.';
    echo json_encode($response);
    exit;
}

if (!in_array($visibilityScope, [1, 2, 3])) {
    $response['status'] = 2;
    $response['msg'] = 'Akses PhET tidak valid.';
    echo json_encode($response);
    exit;
}

if (!in_array($status, [0, 1])) {
    $response['status'] = 2;
    $response['msg'] = 'Status PhET tidak valid.';
    echo json_encode($response);
    exit;
}

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
        $response['msg'] = 'Data pengajar tidak ditemukan.';
        echo json_encode($response);
        exit;
    }

    $teacherData = $teacherResult->fetch_assoc();

    if ((int)$teacherData['subject_id'] !== $subjectId) {
        $response['msg'] = 'Anda hanya dapat membuat PhET sesuai mapel Anda.';
        echo json_encode($response);
        exit;
    }

    $visibilityScope = 1;
    $teacherIds = [];

    $teacherCheck->close();
}

if ($isAdmin && $visibilityScope === 2 && empty($teacherIds)) {
    $response['status'] = 2;
    $response['msg'] = 'Pilih minimal satu pengajar penerima akses.';
    echo json_encode($response);
    exit;
}

if ($isAdmin && $visibilityScope === 2 && $gradeLevel === '') {
    $response['status'] = 2;
    $response['msg'] = 'Tingkat wajib dipilih.';
    echo json_encode($response);
    exit;
}

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
    $response['status'] = 2;
    $response['msg'] = 'Mata pelajaran tidak valid.';
    echo json_encode($response);
    exit;
}

$subjectCheck->close();

function convertPhetUrl($url) {
    if (preg_match('/\/simulations\/([^\/]+)/', $url, $matches)) {
        $slug = $matches[1];
        return "https://phet.colorado.edu/sims/html/{$slug}/latest/{$slug}_en.html";
    }

    return false;
}

$embedUrl = convertPhetUrl($originalUrl);

if (!$embedUrl) {
    $response['status'] = 2;
    $response['msg'] = 'URL simulasi PhET tidak valid.';
    echo json_encode($response);
    exit;
}

$iframePhet = '<iframe src="' . htmlspecialchars($embedUrl, ENT_QUOTES) . '" 
    width="100%" 
    height="600" 
    allowfullscreen 
    loading="lazy" 
    style="border:none;"></iframe>';

$conn->begin_transaction();

try {

    if ($id <= 0) {

        $stmt = $conn->prepare("
            INSERT INTO phet (
                phet_title,
                subject_id,
                description,
                original_url,
                iframe_phet,
                user_id,
                creator_role,
                visibility_scope,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->bind_param(
            "sisssiiii",
            $phetTitle,
            $subjectId,
            $description,
            $originalUrl,
            $iframePhet,
            $userId,
            $userType,
            $visibilityScope,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception('Gagal menambahkan data PhET.');
        }

        $newPhetId = $stmt->insert_id;
        $stmt->close();

        if ($isAdmin && $visibilityScope === 2 && !empty($teacherIds)) {

            $accessStmt = $conn->prepare("
                INSERT INTO phet_teacher_access
                    (phet_id, teacher_id, grade_level, assigned_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");

            foreach ($teacherIds as $teacherId) {
                $accessStmt->bind_param(
                    "iis",
                    $newPhetId,
                    $teacherId,
                    $gradeLevel
                );
                $accessStmt->execute();
            }

            $accessStmt->close();
        }

        $response['msg'] = 'PhET berhasil ditambahkan.';

    } else {

        $oldStmt = $conn->prepare("
            SELECT user_id
            FROM phet
            WHERE id = ?
            LIMIT 1
        ");

        $oldStmt->bind_param("i", $id);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result();

        if ($oldResult->num_rows <= 0) {
            throw new Exception('Data PhET tidak ditemukan.');
        }

        $oldData = $oldResult->fetch_assoc();
        $oldStmt->close();

        if (!$isAdmin && (int)$oldData['user_id'] !== $userId) {
            throw new Exception('Anda tidak memiliki akses untuk mengedit data ini.');
        }

        $stmt = $conn->prepare("
            UPDATE phet
            SET
                phet_title = ?,
                subject_id = ?,
                description = ?,
                original_url = ?,
                iframe_phet = ?,
                visibility_scope = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sisssiii",
            $phetTitle,
            $subjectId,
            $description,
            $originalUrl,
            $iframePhet,
            $visibilityScope,
            $status,
            $id
        );

        if (!$stmt->execute()) {
            throw new Exception('Gagal memperbarui data PhET.');
        }

        $stmt->close();

        $deleteAccessStmt = $conn->prepare("
            DELETE FROM phet_teacher_access
            WHERE phet_id = ?
        ");

        $deleteAccessStmt->bind_param("i", $id);
        $deleteAccessStmt->execute();
        $deleteAccessStmt->close();

        if ($isAdmin && $visibilityScope === 2 && !empty($teacherIds)) {

            $accessStmt = $conn->prepare("
                INSERT INTO phet_teacher_access
                    (phet_id, teacher_id, grade_level, assigned_at, updated_at)
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

        $response['msg'] = 'PhET berhasil diperbarui.';
    }

    $conn->commit();

    $response['status'] = 1;
    echo json_encode($response);
    exit;

} catch (Exception $e) {

    $conn->rollback();

    $response['status'] = 0;
    $response['msg'] = $e->getMessage();

    echo json_encode($response);
    exit;
}
?>