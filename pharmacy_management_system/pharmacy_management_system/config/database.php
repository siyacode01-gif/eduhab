<?php
// Database connection. Change these 4 lines if your MySQL settings are different.
date_default_timezone_set('Africa/Johannesburg');
$host = 'localhost';
$db   = 'pharmacy_management';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed. Start MySQL in XAMPP and make sure pharmacy_management.sql was imported.');
}
