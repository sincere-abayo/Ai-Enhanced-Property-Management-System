<?php
// add error reporting
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
$host = 'localhost';
$dbname = 'property_management';
$username = 'root';
$password = ''; // Set your password here

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
