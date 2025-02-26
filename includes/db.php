<?php
$host     = 'localhost'; 
$dbname   = 'imfvqdhp_social_media'; // database
$username = 'imfvqdhp_social_user'; // Username database
$password = 'juspnN5cC_i4Pm@'; // Password database

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("An error occurred while connecting to the database.");
}
?>
