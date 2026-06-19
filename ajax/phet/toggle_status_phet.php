<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   ACCESS VALIDATION
======================================================= */
if (
    !isset($_SESSION['login_user_type']) ||
    !in_array((int)$_SESSION['login_user_type'], [1, 2])
) {
    echo json_encode([
        "status" => 0,
        "msg" => "Akses ditolak."
    ]);
    exit;
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];
$isAdmin  = $userType === 1;

/* =======================================================
   INPUT VALIDATION
======================================================= */
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$status = isset($_POST['status'])
    ? (int)$_POST['status']
    : -1;

if (
    $id <= 0 ||
    !in_array($status, [0, 1])
) {
    echo json_encode([
        "status" => 0,
        "msg" => "Data tidak valid."
    ]);
    exit;
}

/* =======================================================
   GET PHET DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT
        id,
        user_id,
        creator_role,
        status,
        visibility_scope
    FROM phet
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows <= 0) {
    echo json_encode([
        "status" => 0,
        "msg" => "Data PhET tidak ditemukan."
    ]);
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

/* =======================================================
   OWNER VALIDATION
======================================================= */
$isOwner = (int)$data['user_id'] === $userId;

if (!$isAdmin && !$isOwner) {
    echo json_encode([
        "status" => 0,
        "msg" => "Anda tidak memiliki akses."
    ]);
    exit;
}

/* =======================================================
   UPDATE STATUS
======================================================= */
$update = $conn->prepare("
    UPDATE phet
    SET
        status = ?,
        updated_at = NOW()
    WHERE id = ?
");

$update->bind_param("ii", $status, $id);

if ($update->execute()) {

    echo json_encode([
        "status" => 1,
        "msg" => $status === 1
            ? "PhET berhasil diaktifkan."
            : "PhET berhasil diarsipkan."
    ]);

} else {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal memperbarui status PhET."
    ]);

}

$update->close();
exit;