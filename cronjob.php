<?php
require 'send_email.php'; // Import hàm sendDeadlineReminder()

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "task_manager";

// Kết nối MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy danh sách nhiệm vụ sắp hết hạn (trong 24 giờ)
$sql = "SELECT tasks.title, tasks.deadline, users.email 
        FROM tasks 
        JOIN users ON tasks.user_id = users.id
        WHERE TIMESTAMPDIFF(HOUR, NOW(), tasks.deadline) <= 24 
        AND tasks.status = 'pending'";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        sendDeadlineReminder($row['email'], $row['title'], $row['deadline']);
    }
} else {
    echo "Không có nhiệm vụ nào cần nhắc nhở.";
}

$conn->close();
?>
