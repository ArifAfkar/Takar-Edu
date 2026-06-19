<?php

require_once '../../config/auth.php';
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_FILES['upload'])) {
    echo json_encode([
        'error' => [
            'message' => 'Tidak ada file upload.'
        ]
    ]);
    exit;
}

$file = $_FILES['upload'];

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

if (!isset($allowed[$file['type']])) {
    echo json_encode([
        'error' => [
            'message' => 'Format gambar tidak didukung.'
        ]
    ]);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {

    echo json_encode([
        'error' => [
            'message' => 'Ukuran gambar maksimal 5MB.'
        ]
    ]);

    exit;
}

$extension = $allowed[$file['type']];

$fileName =
    'editor_' .
    time() .
    '_' .
    bin2hex(random_bytes(5)) .
    '.' .
    $extension;

$uploadPath =
    '../../uploads/editor/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {

    echo json_encode([
        'error' => [
            'message' => 'Gagal upload gambar.'
        ]
    ]);

    exit;
}

$imageUrl =
    '../uploads/editor/' . $fileName;

echo json_encode([
    'url' => $imageUrl
]);