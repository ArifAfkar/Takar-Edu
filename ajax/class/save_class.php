<?php
/* =======================================================
    SYSTEM INITIALIZATION
======================================================= */
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
    ACCESS CONTROL
======================================================= */
if ($_SESSION['login_user_type'] != 1) {
    echo json_encode([
        "status" => 0,
        "msg" => "Akses ditolak."
    ]);
    exit;
}

/* =======================================================
    INPUT
======================================================= */
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$className = trim($_POST['class_name'] ?? '');
$gradeLevel = trim($_POST['grade_level'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($description === '') {
    $description = null;
}

/* =======================================================
    VALIDATION
======================================================= */
if ($className === '') {
    echo json_encode([
        "status" => 2,
        "msg" => "Nama kelas wajib diisi."
    ]);
    exit;
}

/* =======================================================
    DUPLICATE CHECK
======================================================= */
$stmt = $conn->prepare("
    SELECT id
    FROM classes
    WHERE class_name = ?
    AND id != ?
");

$stmt->bind_param("si", $className, $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        "status" => 2,
        "msg" => "Nama kelas sudah digunakan."
    ]);
    exit;
}

if ($gradeLevel === '') {
    echo json_encode([
        "status" => 2,
        "msg" => "Tingkat kelas wajib dipilih."
    ]);
    exit;
}

/* =======================================================
    INSERT MODE
======================================================= */
if ($id === 0) {

    $stmt = $conn->prepare("
        INSERT INTO classes (
            class_name,
            grade_level,
            description
        ) VALUES (?, ?, ?)
    ");

    $stmt->bind_param(
        "sss",
        $className,
        $gradeLevel,
        $description
    );

    if ($stmt->execute()) {

        echo json_encode([
            "status" => 1,
            "msg" => "Kelas berhasil ditambahkan."
        ]);

    } else {

        echo json_encode([
            "status" => 0,
            "msg" => "Gagal menambahkan kelas."
        ]);

    }

    exit;
}

/* =======================================================
    UPDATE MODE
======================================================= */
$stmt = $conn->prepare("
    UPDATE classes
    SET
        class_name = ?,
        grade_level = ?,
        description = ?,
        updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param(
    "sssi",
    $className,
    $gradeLevel,
    $description,
    $id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => 1,
        "msg" => "Kelas berhasil diperbarui."
    ]);

} else {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal memperbarui kelas."
    ]);

}
?>