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
    INPUT HANDLING
======================================================= */
$id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name       = trim($_POST['name'] ?? '');
$username   = trim($_POST['username'] ?? '');
$password   = trim($_POST['password'] ?? '');
$subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== ''
    ? (int) $_POST['subject_id']
    : null;
$gender     = trim($_POST['gender'] ?? '');

/* =======================================================
    VALIDATION
======================================================= */
if ($name === '') {
    echo json_encode([
        "status" => 2,
        "msg" => "Nama pengajar wajib diisi."
    ]);
    exit;
}

if ($username === '') {
    echo json_encode([
        "status" => 2,
        "msg" => "Username wajib diisi."
    ]);
    exit;
}

if (is_null($subject_id)) {
    echo json_encode([
        "status" => 2,
        "msg" => "Mata pelajaran wajib dipilih."
    ]);
    exit;
}

if (!in_array($gender, ['L', 'P'])) {
    echo json_encode([
        "status" => 2,
        "msg" => "Jenis kelamin wajib dipilih."
    ]);
    exit;
}

/* ---------- Password required only for create ---------- */
if ($id === 0 && $password === '') {
    echo json_encode([
        "status" => 2,
        "msg" => "Password wajib diisi."
    ]);
    exit;
}

/* =======================================================
    SUBJECT VALIDATION
======================================================= */
if (!is_null($subject_id)) {

    $stmt = $conn->prepare("
        SELECT id
        FROM subjects
        WHERE id = ?
    ");

    $stmt->bind_param("i", $subject_id);
    $stmt->execute();

    $subjectCheck = $stmt->get_result();

    if ($subjectCheck->num_rows === 0) {
        echo json_encode([
            "status" => 2,
            "msg" => "Mata pelajaran tidak valid."
        ]);
        exit;
    }
}

/* =======================================================
    CREATE MODE
======================================================= */
if ($id === 0) {

    /* ---------- Username Duplicate ---------- */
    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE username = ?
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode([
            "status" => 2,
            "msg" => "Username sudah digunakan."
        ]);
        exit;
    }

    /* ---------- Insert User ---------- */
    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $stmt = $conn->prepare("
        INSERT INTO users (
            name,
            username,
            password,
            user_type,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, 2, 1, NOW(), NOW())
    ");

    $stmt->bind_param(
        "sss",
        $name,
        $username,
        $hashedPassword
    );

    if (!$stmt->execute()) {
        echo json_encode([
            "status" => 0,
            "msg" => "Gagal menambahkan akun pengajar."
        ]);
        exit;
    }

    $user_id = $stmt->insert_id;

    /* ---------- Insert Teacher ---------- */
    $stmt = $conn->prepare("
        INSERT INTO teachers (
            user_id,
            subject_id,
            gender,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, NOW(), NOW())
    ");

    $stmt->bind_param(
        "iis",
        $user_id,
        $subject_id,
        $gender
    );

    if ($stmt->execute()) {

        echo json_encode([
            "status" => 1,
            "msg" => "Pengajar berhasil ditambahkan."
        ]);

    } else {

        echo json_encode([
            "status" => 0,
            "msg" => "Gagal menambahkan data pengajar."
        ]);

    }

    exit;
}

/* =======================================================
    UPDATE MODE
======================================================= */

/* ---------- Get Existing Teacher ---------- */
$stmt = $conn->prepare("
    SELECT user_id
    FROM teachers
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => 0,
        "msg" => "Data pengajar tidak ditemukan."
    ]);
    exit;
}

$teacherData = $result->fetch_assoc();
$user_id = (int) $teacherData['user_id'];

/* ---------- Username Duplicate ---------- */
$stmt = $conn->prepare("
    SELECT id
    FROM users
    WHERE username = ?
    AND id != ?
");

$stmt->bind_param(
    "si",
    $username,
    $user_id
);

$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode([
        "status" => 2,
        "msg" => "Username sudah digunakan."
    ]);
    exit;
}

/* ---------- Update User ---------- */
if ($password !== '') {

    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $stmt = $conn->prepare("
        UPDATE users
        SET
            name = ?,
            username = ?,
            password = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssi",
        $name,
        $username,
        $hashedPassword,
        $user_id
    );

} else {

    $stmt = $conn->prepare("
        UPDATE users
        SET
            name = ?,
            username = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssi",
        $name,
        $username,
        $user_id
    );
}

if (!$stmt->execute()) {
    echo json_encode([
        "status" => 0,
        "msg" => "Gagal memperbarui akun pengajar."
    ]);
    exit;
}

/* ---------- Update Teacher ---------- */
$stmt = $conn->prepare("
    UPDATE teachers
    SET
        subject_id = ?,
        gender = ?,
        updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param(
    "isi",
    $subject_id,
    $gender,
    $id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => 1,
        "msg" => "Data pengajar berhasil diperbarui."
    ]);

} else {

    echo json_encode([
        "status" => 0,
        "msg" => "Gagal memperbarui data pengajar."
    ]);

}
?>