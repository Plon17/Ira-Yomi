<?php
$host = 'sql113.infinityfree.com';
$dbname = 'if0_40241814_Irayomi';
$username = 'if0_40241814';
$password = 'Irasamaiscute'; 

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>