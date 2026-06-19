<?php
require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (
    !isset($_SESSION['login_user_type']) ||
    !in_array((int)$_SESSION['login_user_type'], [1, 2])
) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Akses ditolak.'
    ]);
    exit;
}

$userId = (int) $_SESSION['login_id'];
$userType = (int) $_SESSION['login_user_type'];

$isAdmin = $userType === 1;
$isTeacher = $userType === 2;

$action = $_GET['action'] ?? 'detail';

/* =======================================================
   ACTION: SHARE TEACHERS
======================================================= */
if ($action === 'share_teachers') {

    if (!$isAdmin) {
        echo json_encode(['status' => 0, 'msg' => 'Akses ditolak.']);
        exit;
    }

    $subjectId = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
    $gradeLevel = trim($_GET['grade_level'] ?? '');

    if ($subjectId <= 0 || $gradeLevel === '') {
        echo json_encode([
            'status' => 0,
            'msg' => 'Mapel dan tingkat wajib dipilih.'
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT
            t.id AS teacher_id,
            u.name AS teacher_name
        FROM teachers t
        INNER JOIN users u
            ON u.id = t.user_id
        INNER JOIN teacher_class_assignments tca
            ON tca.teacher_id = t.id
        INNER JOIN classes c
            ON c.id = tca.class_id
        WHERE t.subject_id = ?
        AND c.grade_level = ?
        AND tca.status = 1
        AND u.status = 1
        ORDER BY u.name ASC
    ");

    $stmt->bind_param("is", $subjectId, $gradeLevel);
    $stmt->execute();

    $result = $stmt->get_result();
    $teachers = [];

    while ($row = $result->fetch_assoc()) {
        $teachers[] = [
            'teacher_id' => (int) $row['teacher_id'],
            'teacher_name' => $row['teacher_name']
        ];
    }

    $stmt->close();

    echo json_encode([
        'status' => 1,
        'msg' => 'Data pengajar berhasil diambil.',
        'data' => $teachers
    ]);
    exit;
}

/* =======================================================
   ACTION: TEACHER CLASSES
======================================================= */
if ($action === 'teacher_classes') {

    if (!$isAdmin) {
        echo json_encode(['status' => 0, 'msg' => 'Akses ditolak.']);
        exit;
    }

    $teacherId  = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : 0;
    $gradeLevel = trim($_GET['grade_level'] ?? '');

    if ($teacherId <= 0 || $gradeLevel === '') {
        echo json_encode([
            'status' => 0,
            'msg' => 'Pengajar atau tingkat tidak valid.'
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            c.id AS class_id,
            c.class_name,
            c.grade_level
        FROM teacher_class_assignments tca
        INNER JOIN classes c
            ON c.id = tca.class_id
        WHERE tca.teacher_id = ?
        AND c.grade_level = ?
        AND tca.status = 1
        ORDER BY c.class_name ASC
    ");

    $stmt->bind_param("is", $teacherId, $gradeLevel);
    $stmt->execute();

    $result = $stmt->get_result();
    $classes = [];

    while ($row = $result->fetch_assoc()) {
        $classes[] = [
            'class_id'    => (int) $row['class_id'],
            'class_name'  => $row['class_name'],
            'grade_level' => $row['grade_level']
        ];
    }

    $stmt->close();

    echo json_encode([
        'status' => 1,
        'msg'    => 'Data kelas berhasil diambil.',
        'data'   => $classes
    ]);
    exit;
}

/* =======================================================
   TEACHER DATA
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

    if ($teacherResult->num_rows > 0) {
        $teacherData = $teacherResult->fetch_assoc();
        $teacherId = (int) $teacherData['id'];
    }

    $teacherStmt->close();
}

/* =======================================================
   DETAIL PHET
======================================================= */
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        'status' => 0,
        'msg' => 'ID PhET tidak valid.'
    ]);
    exit;
}

$accessSql = "";

if ($isTeacher) {
    $accessSql = "
        AND (
            p.user_id = ?
            OR p.visibility_scope = 3
            OR EXISTS (
                SELECT 1
                FROM phet_teacher_access pta
                WHERE pta.phet_id = p.id
                AND pta.teacher_id = ?
            )
        )
    ";
}

$sql = "
    SELECT
        p.id,
        p.phet_title,
        p.subject_id,
        p.description,
        p.original_url,
        p.iframe_phet,
        p.visibility_scope,
        p.status,
        p.user_id,
        p.creator_role,
        p.created_at,
        p.updated_at,
        s.subject_name,
        u.name AS creator_name
    FROM phet p
    LEFT JOIN subjects s
        ON p.subject_id = s.id
    LEFT JOIN users u
        ON p.user_id = u.id
    WHERE p.id = ?
    {$accessSql}
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'status' => 0,
        'msg' => 'Gagal menyiapkan query.'
    ]);
    exit;
}

if ($isAdmin) {
    $stmt->bind_param("i", $id);
} else {
    $stmt->bind_param("iii", $id, $userId, $teacherId);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    $stmt->close();

    echo json_encode([
        'status' => 0,
        'msg' => 'Data tidak ditemukan atau Anda tidak memiliki akses.'
    ]);
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

/* =======================================================
   GET SELECTED TEACHERS
======================================================= */
$selectedTeachers = [];

if ($isAdmin && (int)$data['visibility_scope'] === 2) {

    $accessStmt = $conn->prepare("
        SELECT
            pta.teacher_id,
            u.name AS teacher_name,
            pta.grade_level
        FROM phet_teacher_access pta
        INNER JOIN teachers t
            ON t.id = pta.teacher_id
        INNER JOIN users u
            ON u.id = t.user_id
        WHERE pta.phet_id = ?
        ORDER BY u.name ASC
    ");

    $accessStmt->bind_param("i", $id);
    $accessStmt->execute();

    $accessResult = $accessStmt->get_result();

    while ($row = $accessResult->fetch_assoc()) {
        $selectedTeachers[] = [
            'teacher_id'   => (int) $row['teacher_id'],
            'teacher_name' => $row['teacher_name'],
            'grade_levels' => $row['grade_level'] ?? ''
        ];
    }

    $accessStmt->close();
}

/* =======================================================
   NORMALIZE DATA
======================================================= */
$data['id'] = (int) $data['id'];
$data['subject_id'] = (int) $data['subject_id'];
$data['visibility_scope'] = (int) $data['visibility_scope'];
$data['status'] = (int) $data['status'];
$data['user_id'] = (int) $data['user_id'];
$data['creator_role'] = (int) $data['creator_role'];

$data['subject_name'] = $data['subject_name'] ?? '';
$data['creator_name'] = $data['creator_name'] ?? '';

$data['selected_teachers'] = $selectedTeachers;
$data['selected_teacher_ids'] = array_column($selectedTeachers, 'teacher_id');

$selectedGradeLevels = [];

foreach ($selectedTeachers as $teacher) {
    if (!empty($teacher['grade_levels'])) {
        $grades = explode(',', $teacher['grade_levels']);

        foreach ($grades as $grade) {
            $selectedGradeLevels[] = trim($grade);
        }
    }
}

$selectedGradeLevels = array_values(array_unique(array_filter($selectedGradeLevels)));

$data['selected_grade_levels'] = $selectedGradeLevels;
$data['selected_grade_level'] = count($selectedGradeLevels) === 1
    ? $selectedGradeLevels[0]
    : '';

/* =======================================================
   OUTPUT
======================================================= */
echo json_encode([
    'status' => 1,
    'msg' => 'Data berhasil diambil.',
    'data' => $data
]);
exit;
?>