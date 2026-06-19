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

/* =======================================================
    INPUT
======================================================= */
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$subjectName = trim($_POST['subject_name'] ?? '');
$subjectCode = trim($_POST['subject_code'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($description === '') {
    $description = null;
}

/* =======================================================
    VALIDATION
======================================================= */
if ($subjectName === '') {
    echo json_encode([
        "status" => 0,
        "msg" => "Nama mata pelajaran wajib diisi."
    ]);
    exit;
}

/* =======================================================
    DUPLICATE CHECK - NAME
======================================================= */
$stmt = $conn->prepare("
    SELECT id
    FROM subjects
    WHERE subject_name = ?
    AND id != ?
");
$stmt->bind_param("si", $subjectName, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        "status" => 2,
        "msg" => "Nama mata pelajaran sudah digunakan."
    ]);
    exit;
}

/* =======================================================
    DUPLICATE CHECK - CODE
======================================================= */
if ($subjectCode !== '') {

    $stmt = $conn->prepare("
        SELECT id
        FROM subjects
        WHERE subject_code = ?
        AND id != ?
    ");
    $stmt->bind_param("si", $subjectCode, $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode([
            "status" => 2,
            "msg" => "Kode mata pelajaran sudah digunakan."
        ]);
        exit;
    }
}

/* =======================================================
    INSERT
======================================================= */
if ($id === 0) {

    $stmt = $conn->prepare("
        INSERT INTO subjects (
            subject_name,
            subject_code,
            description
        ) VALUES (?, ?, ?)
    ");

    $stmt->bind_param(
        "sss",
        $subjectName,
        $subjectCode,
        $description
    );

    if ($stmt->execute()) {

        echo json_encode([
            "status" => 1,
            "msg" => "Mata pelajaran berhasil ditambahkan."
        ]);

    } else {

        echo json_encode([
            "status" => 0,
            "msg" => "Gagal menambahkan mata pelajaran."
        ]);

    }

    exit;
}

/* =======================================================
    UPDATE
======================================================= */
$stmt = $conn->prepare("
    UPDATE subjects
    SET
        subject_name = ?,
        subject_code = ?,
        description = ?,
        updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param(
    "sssi",
    $subjectName,
    $subjectCode,
    $description,
    $id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => 1,
        "msg" => "Mata pelajaran berhasil diperbarui."
    ]);

} else {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal memperbarui mata pelajaran."
    ]);

}
?>