<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "task_manager";

// Kết nối MySQL
$conn = new mysqli($servername, $username, $password, $database);

// Kiểm tra lỗi kết nối
if ($conn->connect_error) {
    die("Kết nối Database thất bại: " . $conn->connect_error);
}
?>
