<?php
session_start();

if(isset($_SESSION['login_id'])){
    header("Location: pages/home.php");
} else {
    header("Location: pages/login.php");
}
exit;
?>