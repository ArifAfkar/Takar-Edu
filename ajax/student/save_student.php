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
    AUTO ASSIGN STUDENT TO AVAILABLE QUIZZES
======================================================= */
function autoAssignStudentToAvailableQuizzes($conn, $studentId, $classId)
{
    $studentId = (int) $studentId;
    $classId   = (int) $classId;

    if ($studentId <= 0 || $classId <= 0) {
        return false;
    }

    $sql = "
        INSERT INTO quiz_student_list (
            quiz_id,
            student_id,
            status,
            assigned_at
        )

        SELECT DISTINCT
            q.id,
            {$studentId},
            1,
            NOW()

        FROM quiz_list q

        INNER JOIN quiz_teacher_list qtl
            ON q.id = qtl.quiz_id

        INNER JOIN teacher_class_assignments tca
            ON qtl.teacher_id = tca.teacher_id

        WHERE q.status = 1
        AND tca.status = 1
        AND tca.class_id = {$classId}

        AND NOT EXISTS (
            SELECT 1
            FROM quiz_student_list qsl
            WHERE qsl.quiz_id = q.id
            AND qsl.student_id = {$studentId}
        )
    ";

    return $conn->query($sql);
}

/* =======================================================
    ACCESS CONTROL
======================================================= */
if ($_SESSION['login_user_type'] != 1) {

    $response['msg'] = "Akses ditolak.";

    echo json_encode($response);
    exit;
}

/* =======================================================
    INPUT HANDLING
======================================================= */
$id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$user_id  = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

$name     = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$gender   = trim($_POST['gender'] ?? '');
$class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

/* =======================================================
    VALIDATION
======================================================= */
if ($name === '') {
    echo json_encode([
        "status" => 2,
        "msg"    => "Nama siswa wajib diisi."
    ]);
    exit;
}

if ($username === '') {
    echo json_encode([
        "status" => 2,
        "msg"    => "Username wajib diisi."
    ]);
    exit;
}

if (!in_array($gender, ['L', 'P'])) {
    echo json_encode([
        "status" => 2,
        "msg"    => "Jenis kelamin wajib dipilih."
    ]);
    exit;
}

if ($class_id <= 0) {
    echo json_encode([
        "status" => 2,
        "msg"    => "Kelas wajib dipilih."
    ]);
    exit;
}

/* ---------- Password required only for create ---------- */
if ($id === 0 && $password === '') {
    echo json_encode([
        "status" => 2,
        "msg"    => "Password wajib diisi."
    ]);
    exit;
}

/* =======================================================
    CLASS VALIDATION
======================================================= */
$stmt = $conn->prepare("
    SELECT id
    FROM classes
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $class_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {

    echo json_encode([
        "status" => 2,
        "msg"    => "Kelas tidak valid."
    ]);

    exit;
}

/* =======================================================
    CREATE MODE
======================================================= */
if ($id === 0) {

    /* ---------- Username Check ---------- */
    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {

        echo json_encode([
            "status" => 2,
            "msg"    => "Username sudah digunakan."
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
        ) VALUES (?, ?, ?, 3, 1, NOW(), NOW())
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
            "msg"    => "Gagal menambahkan akun siswa."
        ]);

        exit;
    }

    $new_user_id = $stmt->insert_id;

    /* ---------- Insert Student ---------- */
    $stmt = $conn->prepare("
        INSERT INTO students (
            user_id,
            class_id,
            gender,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, NOW(), NOW())
    ");

    $stmt->bind_param(
        "iis",
        $new_user_id,
        $class_id,
        $gender
    );

    if ($stmt->execute()) {

        $studentId = $stmt->insert_id;

        autoAssignStudentToAvailableQuizzes(
            $conn,
            $studentId,
            $class_id
        );

        echo json_encode([
            "status" => 1,
            "msg"    => "Siswa berhasil ditambahkan dan otomatis dimasukkan ke kuis yang tersedia."
        ]);

    } else {

        echo json_encode([
            "status" => 0,
            "msg"    => "Gagal menambahkan data siswa."
        ]);

    }

    exit;
}

/* =======================================================
    UPDATE MODE
======================================================= */

/* ---------- Existing Student Check ---------- */
$stmt = $conn->prepare("
    SELECT user_id
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    echo json_encode([
        "status" => 0,
        "msg"    => "Data siswa tidak ditemukan."
    ]);

    exit;
}

$studentData = $result->fetch_assoc();
$user_id     = (int) $studentData['user_id'];

/* ---------- Username Duplicate ---------- */
$stmt = $conn->prepare("
    SELECT id
    FROM users
    WHERE username = ?
    AND id != ?
    LIMIT 1
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
        "msg"    => "Username sudah digunakan."
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
        "msg"    => "Gagal memperbarui akun siswa."
    ]);

    exit;
}

/* ---------- Update Student ---------- */
$stmt = $conn->prepare("
    UPDATE students
    SET
        class_id = ?,
        gender = ?,
        updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param(
    "isi",
    $class_id,
    $gender,
    $id
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => 1,
        "msg"    => "Data siswa berhasil diperbarui."
    ]);

} else {

    echo json_encode([
        "status" => 0,
        "msg"    => "Gagal memperbarui data siswa."
    ]);

}
?>