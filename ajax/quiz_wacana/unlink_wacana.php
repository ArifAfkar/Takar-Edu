<?php
include '../../config/db_connect.php';

header('Content-Type: application/json');

$wacana_id = $_GET['id'] ?? 0;
$quiz_id = $_GET['qid'] ?? 0;

if(!$wacana_id || !$quiz_id){
    echo json_encode(["status"=>0,"msg"=>"Parameter tidak lengkap"]);
    exit;
}

$update = $conn->query("
    UPDATE questions 
    SET wacana_id = NULL 
    WHERE wacana_id = $wacana_id 
    AND qid = $quiz_id
");

echo json_encode([
    "status" => $update ? 1 : 0
]);