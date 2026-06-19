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
   VALIDATE STUDENT ID
======================================================= */
$id = 0;

if (isset($_POST['id'])) {
    $id = (int) $_POST['id'];
} elseif (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
}

if ($id <= 0) {
    $response['msg'] = "ID siswa tidak valid.";
    echo json_encode($response);
    exit;
}

/* =======================================================
   FETCH STUDENT DATA
======================================================= */
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.user_id,
        u.name
    FROM students s
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['msg'] = "Data siswa tidak ditemukan.";
    echo json_encode($response);
    exit;
}

$student = $result->fetch_assoc();

$student_id   = (int) $student['id'];
$user_id      = (int) $student['user_id'];
$student_name = $student['name'];

$stmt->close();

/* =======================================================
   START TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    /* ===================================================
       DELETE ANSWER EVALUATIONS
       answer_evaluations -> answers -> users
    =================================================== */
    $stmt = $conn->prepare("
        DELETE ae
        FROM answer_evaluations ae
        INNER JOIN answers a ON ae.answer_id = a.id
        WHERE a.student_id = ?
    ");

    $stmt->bind_param("i", $student_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus evaluasi jawaban siswa.");
    }

    $stmt->close();

    /* ===================================================
       DELETE ANSWERS
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM answers
        WHERE student_id = ?
    ");

    $stmt->bind_param("i", $student_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus jawaban siswa.");
    }

    $stmt->close();

    /* ===================================================
       DELETE HISTORY
       history -> quiz_student_list
    =================================================== */
    $stmt = $conn->prepare("
        DELETE h
        FROM history h
        INNER JOIN quiz_student_list qsl ON h.quiz_student_id = qsl.id
        WHERE qsl.student_id = ?
    ");

    $stmt->bind_param("i", $student_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus riwayat kuis siswa.");
    }

    $stmt->close();

    /* ===================================================
       DELETE QUIZ STUDENT LIST
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM quiz_student_list
        WHERE student_id = ?
    ");

    $stmt->bind_param("i", $student_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus penugasan kuis siswa.");
    }

    $stmt->close();

    /* ===================================================
       DELETE STUDENT RECORD
    =================================================== */
    $stmt = $conn->prepare("
        DELETE FROM students
        WHERE id = ?
    ");

    $stmt->bind_param("i", $student_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus data siswa.");
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
        "msg"          => "Data siswa berhasil dihapus beserta seluruh riwayatnya.",
        "student_name" => $student_name
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