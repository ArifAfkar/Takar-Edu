<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if ($_SESSION['login_user_type'] != 1) {
    echo json_encode([
        "status" => 0,
        "msg" => "Akses ditolak."
    ]);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => 0,
        "msg" => "ID tidak valid."
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT t.user_id, u.status
    FROM teachers t
    INNER JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => 0,
        "msg" => "Pengajar tidak ditemukan."
    ]);
    exit;
}

$data = $result->fetch_assoc();

$newStatus = $data['status'] == 1 ? 0 : 1;
$user_id = (int) $data['user_id'];

$stmt = $conn->prepare("
    UPDATE users
    SET status = ?, updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param("ii", $newStatus, $user_id);

if ($stmt->execute()) {

    echo json_encode([
        "status" => 1,
        "msg" => $newStatus == 1
            ? "Akun pengajar berhasil diaktifkan."
            : "Akun pengajar berhasil dinonaktifkan."
    ]);

} else {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal memperbarui status akun."
    ]);

}
?>