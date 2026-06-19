<?php
/* =======================================================
    DEPENDENCIES
======================================================= */
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

/* =======================================================
    LOAD ENVIRONMENT VARIABLES
======================================================= */
$dotenv = Dotenv::createImmutable(
    dirname(__DIR__)
);

$dotenv->safeLoad();

/* =======================================================
    DATABASE CONFIGURATION
======================================================= */
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';
$db   = $_ENV['DB_NAME'] ?? '';

/* =======================================================
    MYSQLI ERROR REPORTING
======================================================= */
mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/* =======================================================
    DEFAULT TIMEZONE CONFIGURATION
======================================================= */
date_default_timezone_set(
    'Asia/Makassar'
);

/* =======================================================
    DATABASE CONNECTION INITIALIZATION
======================================================= */
try {

    $conn = new mysqli(
        $host,
        $user,
        $pass,
        $db
    );

    $conn->set_charset(
        "utf8mb4"
    );

} catch (mysqli_sql_exception $e) {

    error_log(
        "Database Connection Error: " .
        $e->getMessage()
    );

    die(
        "Database connection failed."
    );
}
?>