<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "task_manager";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$sql = "SELECT id, title, deadline FROM tasks";
$result = $conn->query($sql);

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

$conn->close();
?>

<script>
    let tasks = <?php echo json_encode($tasks); ?>;

    function checkDeadlines() {
        let now = new Date().getTime();
        tasks.forEach(task => {
            let deadline = new Date(task.deadline).getTime();
            let timeLeft = deadline - now;

            if (timeLeft < 86400000) { // Dưới 24 giờ
                let taskElement = document.getElementById(`task-${task.id}`);
                if (taskElement) {
                    taskElement.style.backgroundColor = "#ffcccc"; // Đổi màu nền
                }
                alert(`⚠️ Nhiệm vụ "${task.title}" sắp hết hạn!`);
            }
        });
    }

    setInterval(checkDeadlines, 60000); // Kiểm tra mỗi phút
</script>
