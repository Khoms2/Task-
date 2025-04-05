<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . "/database/db.php"; // Kiểm tra đường dẫn đúng
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Không gian làm việc</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Không gian làm việc</h2>
        <ul id="workspace-list">
            <?php
            $result = $conn->query("SELECT * FROM workspaces");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    echo "<li><a href='boards.php?workspace_id={$row['id']}'>{$row['name']}</a></li>";
                }
            } else {
                echo "<li>Không có không gian làm việc nào.</li>";
            }
            ?>
        </ul>
        <button id="add-workspace-btn">+ Thêm không gian</button>
    </div>

    <script>
        document.getElementById("add-workspace-btn").addEventListener("click", function() {
            let workspaceName = prompt("Nhập tên không gian làm việc mới:");
            if (workspaceName) {
                fetch("workspace_create.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "name=" + encodeURIComponent(workspaceName)
                })
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    location.reload();
                });
            }
        });
    </script>

</body>  
</html>
