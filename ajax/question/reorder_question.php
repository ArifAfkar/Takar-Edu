<?php
/* =======================================================
   REORDER QUESTIONS AJAX
   File: ajax/question/reorder_questions.php
   FINAL VERSION TAKAREDU (FULL SYSTEM SUPPORT)

   SUPPORT:
   - Admin / Teacher validation
   - Ownership validation
   - Safe drag & drop reorder
   - Duplicate prevention
   - Missing ID prevention
   - Order normalization
   - Transaction safe
======================================================= */

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

/* =======================================================
   RESPONSE HELPER
======================================================= */
function jsonResponse($status, $msg)
{
    echo json_encode([
        "status" => $status,
        "msg"    => $msg
    ]);
    exit;
}

/* =======================================================
   ACCESS CONTROL
======================================================= */
if (
    !isset($_SESSION['login_id']) ||
    !isset($_SESSION['login_user_type']) ||
    !in_array((int)$_SESSION['login_user_type'], [1, 2])
) {
    jsonResponse(0, "Akses ditolak.");
}

$userId   = (int)$_SESSION['login_id'];
$userType = (int)$_SESSION['login_user_type'];

$isAdmin   = ($userType === 1);
$isTeacher = ($userType === 2);

/* =======================================================
   INPUT VALIDATION
======================================================= */
$quizId = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
$orderList = $_POST['order'] ?? [];

if (
    $quizId <= 0 ||
    !is_array($orderList) ||
    count($orderList) <= 0
) {
    jsonResponse(0, "Data reorder tidak valid.");
}

/* =======================================================
   SANITIZE ORDER LIST
======================================================= */
$cleanOrderList = [];

foreach ($orderList as $questionId) {
    $questionId = (int)$questionId;

    if ($questionId > 0 && !in_array($questionId, $cleanOrderList)) {
        $cleanOrderList[] = $questionId;
    }
}

if (count($cleanOrderList) <= 0) {
    jsonResponse(0, "Data urutan kosong.");
}

/* =======================================================
   TEACHER VALIDATION
======================================================= */
$teacherId = 0;

if ($isTeacher) {

    $teacherStmt = $conn->prepare("
        SELECT id
        FROM teachers
        WHERE user_id = ?
        LIMIT 1
    ");

    $teacherStmt->bind_param("i", $userId);
    $teacherStmt->execute();

    $teacherResult = $teacherStmt->get_result();

    if (!$teacherResult || $teacherResult->num_rows <= 0) {
        jsonResponse(0, "Data pengajar tidak ditemukan.");
    }

    $teacherData = $teacherResult->fetch_assoc();
    $teacherId = (int)$teacherData['id'];

    $teacherStmt->close();
}

/* =======================================================
   QUIZ OWNERSHIP VALIDATION
======================================================= */
if ($isAdmin) {

    $quizStmt = $conn->prepare("
        SELECT id
        FROM quiz_list
        WHERE id = ?
        LIMIT 1
    ");

    $quizStmt->bind_param("i", $quizId);

} else {

    $quizStmt = $conn->prepare("
        SELECT id
        FROM quiz_list
        WHERE id = ?
        AND created_by = ?
        LIMIT 1
    ");

    $quizStmt->bind_param(
        "ii",
        $quizId,
        $userId
    );
}

$quizStmt->execute();
$quizResult = $quizStmt->get_result();

if (!$quizResult || $quizResult->num_rows <= 0) {
    jsonResponse(0, "Kuis tidak ditemukan atau akses ditolak.");
}

$quizStmt->close();

/* =======================================================
   VALIDATE QUESTION OWNERSHIP
======================================================= */
$placeholders = implode(',', array_fill(0, count($cleanOrderList), '?'));

$query = "
    SELECT id
    FROM questions
    WHERE quiz_id = ?
    AND id IN ($placeholders)
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    jsonResponse(0, "Prepare validasi gagal.");
}

/* Dynamic bind */
$types = str_repeat('i', count($cleanOrderList) + 1);
$params = array_merge([$quizId], $cleanOrderList);

$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();

$validIds = [];

while ($row = $result->fetch_assoc()) {
    $validIds[] = (int)$row['id'];
}

$stmt->close();

/* Ensure all IDs valid */
if (count($validIds) !== count($cleanOrderList)) {
    jsonResponse(0, "Terdapat soal tidak valid dalam urutan.");
}

/* =======================================================
   TRANSACTION
======================================================= */
$conn->begin_transaction();

try {

    /* =======================================================
       STEP 1: TEMPORARY SHIFT
       Prevent duplicate unique conflicts
    ======================================================= */
    $tempStmt = $conn->prepare("
        UPDATE questions
        SET order_by = order_by + 1000,
            updated_at = NOW()
        WHERE quiz_id = ?
    ");

    $tempStmt->bind_param("i", $quizId);

    if (!$tempStmt->execute()) {
        throw new Exception("Gagal menyiapkan reorder.");
    }

    $tempStmt->close();

    /* =======================================================
       STEP 2: APPLY NEW ORDER
    ======================================================= */
    $updateStmt = $conn->prepare("
        UPDATE questions
        SET order_by = ?,
            updated_at = NOW()
        WHERE id = ?
        AND quiz_id = ?
    ");

    if (!$updateStmt) {
        throw new Exception("Prepare update gagal.");
    }

    $position = 1;

    foreach ($cleanOrderList as $questionId) {

        $updateStmt->bind_param(
            "iii",
            $position,
            $questionId,
            $quizId
        );

        if (!$updateStmt->execute()) {
            throw new Exception("Gagal memperbarui urutan soal.");
        }

        $position++;
    }

    $updateStmt->close();

    /* =======================================================
       STEP 3: NORMALIZE MISSING QUESTIONS
       If frontend missed some IDs
    ======================================================= */
    $remainingStmt = $conn->prepare("
        SELECT id
        FROM questions
        WHERE quiz_id = ?
        AND order_by >= 1000
        ORDER BY order_by ASC
    ");

    $remainingStmt->bind_param("i", $quizId);
    $remainingStmt->execute();

    $remainingResult = $remainingStmt->get_result();
    $remainingIds = [];

    while ($row = $remainingResult->fetch_assoc()) {
        $remainingIds[] = (int)$row['id'];
    }

    $remainingStmt->close();

    if (!empty($remainingIds)) {

        $normalizeStmt = $conn->prepare("
            UPDATE questions
            SET order_by = ?,
                updated_at = NOW()
            WHERE id = ?
            AND quiz_id = ?
        ");

        foreach ($remainingIds as $remainingId) {

            $normalizeStmt->bind_param(
                "iii",
                $position,
                $remainingId,
                $quizId
            );

            if (!$normalizeStmt->execute()) {
                throw new Exception("Gagal normalisasi urutan.");
            }

            $position++;
        }

        $normalizeStmt->close();
    }

    /* =======================================================
       COMMIT
    ======================================================= */
    $conn->commit();

    jsonResponse(1, "Urutan soal berhasil diperbarui.");

} catch (Exception $e) {

    $conn->rollback();

    jsonResponse(0, $e->getMessage());
}
?>