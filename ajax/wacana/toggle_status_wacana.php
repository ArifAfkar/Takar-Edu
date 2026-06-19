<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_user_type']) || !in_array((int)$_SESSION['login_user_type'], [1,2])) {
    echo json_encode([
        "status" => 0,
        "msg" => "Akses ditolak."
    ]);
    exit;
}

$userId   = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];
$isAdmin  = $userType === 1;

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : -1;

if ($id <= 0 || !in_array($status, [0,1])) {
    echo json_encode([
        "status" => 0,
        "msg" => "Data tidak valid."
    ]);
    exit;
}

/* =======================================================
   GET DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT user_id
    FROM wacana
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    echo json_encode([
        "status" => 0,
        "msg" => "Wacana tidak ditemukan."
    ]);
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

/* =======================================================
   OWNER CHECK
======================================================= */
if (!$isAdmin && (int)$data['user_id'] !== $userId) {
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
    UPDATE wacana
    SET status = ?, updated_at = NOW()
    WHERE id = ?
");

$update->bind_param("ii", $status, $id);

if ($update->execute()) {

    echo json_encode([
        "status" => 1,
        "msg" => $status === 1
            ? "Wacana berhasil diaktifkan."
            : "Wacana berhasil diarsipkan."
    ]);

} else {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal memperbarui status."
    ]);
}

$update->close();
exit;
?>