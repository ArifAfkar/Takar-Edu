<?php
include '../../config/db_connect.php';

header('Content-Type: application/json');

$phet_id = $_GET['id'] ?? 0;
$quiz_id = $_GET['qid'] ?? 0;

if(!$phet_id || !$quiz_id){
    echo json_encode(["status"=>0]);
    exit;
}

$update = $conn->query("
    UPDATE questions 
    SET phet_id = NULL 
    WHERE phet_id = $phet_id 
    AND qid = $quiz_id
");

echo json_encode([
    "status" => $update ? 1 : 0
]);