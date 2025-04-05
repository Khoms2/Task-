<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $workspace_id = $_POST['workspace_id'];
    $board_name = $_POST['board_name'];

    if (!empty($board_name)) {
        $stmt = $conn->prepare("INSERT INTO boards (workspace_id, name) VALUES (?, ?)");
        $stmt->execute([$workspace_id, $board_name]);
    }
}

header("Location: index.php?workspace_id=" . $workspace_id);
exit();
?>
