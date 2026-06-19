<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
    DEFAULT RESPONSE
======================================================= */
$response = [
    "status" => 0,
    "msg"    => "Terjadi kesalahan sistem."
];

/* =======================================================
    ACCESS CONTROL
======================================================= */
if (!isset($_SESSION['login_user_type']) || $_SESSION['login_user_type'] != 1) {

    $response['msg'] = "Akses ditolak.";

    echo json_encode($response);
    exit;
}

/* =======================================================
    VALIDATE INPUT
======================================================= */
$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {

    $response['msg'] = "ID pengajar tidak valid.";

    echo json_encode($response);
    exit;
}

/* =======================================================
    FETCH TEACHER DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT
        t.id,
        t.user_id,
        u.name
    FROM teachers t
    INNER JOIN users u
        ON t.user_id = u.id
    WHERE t.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $response['msg'] = "Data pengajar tidak ditemukan.";

    echo json_encode($response);
    exit;
}

$teacher = $result->fetch_assoc();

$teacher_id   = (int) $teacher['id'];
$user_id      = (int) $teacher['user_id'];
$teacher_name = $teacher['name'];

$stmt->close();

/* =======================================================
    RELATION CHECK
    Prevent deletion if teacher still owns:
    - Quiz
    - Wacana
    - PhET
======================================================= */

/* ---------- Quiz Check ---------- */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM quiz_list
    WHERE created_by = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$quizCount = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();

if ($quizCount > 0) {

    echo json_encode([
        "status" => 0,
        "msg"    => "Pengajar tidak dapat dihapus karena masih memiliki kuis."
    ]);

    exit;
}

/* ---------- Wacana Check ---------- */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM wacana
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$wacanaCount = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();

if ($wacanaCount > 0) {

    echo json_encode([
        "status" => 0,
        "msg"    => "Pengajar tidak dapat dihapus karena masih memiliki wacana."
    ]);

    exit;
}

/* ---------- PhET Check ---------- */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM phet
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$phetCount = (int) $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();

if ($phetCount > 0) {

    echo json_encode([
        "status" => 0,
        "msg"    => "Pengajar tidak dapat dihapus karena masih memiliki PhET."
    ]);

    exit;
}

/* =======================================================
    START TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    /* ===================================================
       DELETE TEACHER CLASS ASSIGNMENTS
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM teacher_class_assignments
        WHERE teacher_id = ?
    ");

    $stmt->bind_param("i", $teacher_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus distribusi kelas pengajar.");
    }

    $stmt->close();

    /* ===================================================
       DELETE WACANA ACCESS
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM wacana_teacher_access
        WHERE teacher_id = ?
    ");

    $stmt->bind_param("i", $teacher_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus akses wacana pengajar.");
    }

    $stmt->close();

    /* ===================================================
       DELETE PHET ACCESS
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM phet_teacher_access
        WHERE teacher_id = ?
    ");

    $stmt->bind_param("i", $teacher_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus akses PhET pengajar.");
    }

    $stmt->close();

    /* ===================================================
       DELETE TEACHER RECORD
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM teachers
        WHERE id = ?
    ");

    $stmt->bind_param("i", $teacher_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus data pengajar.");
    }

    $stmt->close();

    /* ===================================================
       DELETE USER ACCOUNT
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM users
        WHERE id = ?
    ");

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus akun pengguna.");
    }

    $stmt->close();

    /* ===================================================
       COMMIT
    =================================================== */
    $conn->commit();

    $response = [
        "status"       => 1,
        "msg"          => "Data pengajar berhasil dihapus.",
        "teacher_name" => $teacher_name
    ];

} catch (Exception $e) {

    /* ===================================================
       ROLLBACK
    =================================================== */
    $conn->rollback();

    $response = [
        "status" => 0,
        "msg"    => $e->getMessage()
    ];
}

/* =======================================================
    OUTPUT
======================================================= */
echo json_encode($response);
exit;
?>