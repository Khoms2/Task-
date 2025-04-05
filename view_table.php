<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kết nối cơ sở dữ liệu
$host = 'localhost';
$dbname = 'task_manager';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}

// Hàm kiểm tra quyền truy cập bảng
function checkTablePermission($pdo, $user_id, $table_id) {
    $stmt = $pdo->prepare("SELECT owner_id FROM tasks WHERE id = ?");
    $stmt->execute([$table_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task && $task['owner_id'] == $user_id) {
        return ['access' => true, 'permission' => 'owner'];
    }

    $stmt = $pdo->prepare("SELECT permission_type FROM table_permissions WHERE table_id = ? AND user_id = ?");
    $stmt->execute([$table_id, $user_id]);
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($permission) {
        return ['access' => true, 'permission' => $permission['permission_type']];
    }

    return ['access' => false, 'permission' => null];
}

// Lấy ID bảng từ URL
$table_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kiểm tra quyền truy cập
$permission = checkTablePermission($pdo, $_SESSION['user_id'], $table_id);

if (!$permission['access']) {
    die("Bạn không có quyền truy cập tác vụ bảng này.");
}

// Lấy thông tin bảng
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$table_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Bảng không tồn tại.");
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết bảng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2><?php echo htmlspecialchars($task['title']); ?></h2>
        <p><?php echo htmlspecialchars($task['description'] ?? 'Không có mô tả'); ?></p>
        <p>
            của bạn: <?php echo $permission['permission'] == 'owner' ? 'Owner' : ucfirst($permission['permission']); ?></p>

        <?php if ($permission['permission'] == 'edit' || $permission['permission'] == 'owner'): ?>
            <p>Bạn có thể chỉnh sửa bảng này.</p>
            <!-- Thêm form chỉnh sửa tại đây -->
        <?php else: ?>
            <p>Bạn chỉ có thể xem bảng này.</p>
        <?php endif; ?>

        <a href="index.php" class="btn btn-primary">Quay lại</a>
    </div>
</body>
</html>