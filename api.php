<?php
session_start();
header('Content-Type: application/json');

// Tắt hiển thị lỗi trên production (để tránh rò rỉ thông tin nhạy cảm)
ini_set('display_errors', 0);
error_reporting(0);

// Kết nối cơ sở dữ liệu
$host = 'localhost';
$dbname = 'task_manager';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'"); // Đảm bảo hỗ trợ UTF-8
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage()]);
    exit();
}

// Hàm kiểm tra quyền truy cập workspace
function checkWorkspacePermission($pdo, $user_id, $workspace_id) {
    try {
        $stmt = $pdo->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
        $stmt->execute([$workspace_id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace && $workspace['owner_id'] == $user_id) {
            return ['access' => true, 'permission' => 'owner'];
        }

        $stmt = $pdo->prepare("SELECT access_level FROM workspace_permissions WHERE workspace_id = ? AND user_id = ?");
        $stmt->execute([$workspace_id, $user_id]);
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($permission) {
            return ['access' => true, 'permission' => $permission['access_level']];
        }

        return ['access' => false, 'permission' => null];
    } catch (PDOException $e) {
        error_log("Error in checkWorkspacePermission: " . $e->getMessage());
        return ['access' => false, 'permission' => null];
    }
}

// Hàm tạo thông báo
function createNotification($pdo, $user_id, $sender_id, $workspace_id, $message, $type, $card_id = null) {
    try {
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM workspace_permissions 
            WHERE workspace_id = ?
            UNION
            SELECT owner_id AS user_id 
            FROM workspaces 
            WHERE id = ?
        ");
        $stmt->execute([$workspace_id, $workspace_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, sender_id, workspace_id, message, type, card_id, is_read)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$user['user_id'], $sender_id, $workspace_id, $message, $type, $card_id]);
        }
    } catch (PDOException $e) {
        error_log("Error in createNotification: " . $e->getMessage());
        throw $e;
    }
}

// Xử lý các yêu cầu
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_title = isset($_POST['list_title']) ? trim($_POST['list_title']) : '';
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thêm danh sách']);
            exit();
        }

        if (empty($list_title)) {
            echo json_encode(['success' => false, 'message' => 'Tên danh sách không được để trống']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO lists (title, workspace_id, archived) VALUES (?, ?, 0)");
            $stmt->execute([$list_title, $workspace_id]);
            $list_id = $pdo->lastInsertId();

            // Lấy tên người dùng gửi
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            // Tạo thông báo
            $message = "$sender_name đã thêm bảng '$list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'add_list');

            echo json_encode([
                'success' => true,
                'list' => [
                    'id' => $list_id,
                    'title' => htmlspecialchars($list_title)
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Error in add_list: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm danh sách: ' . $e->getMessage()]);
        }
        break;

    case 'add_card':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $card_title = isset($_POST['card_title']) ? trim($_POST['card_title']) : '';
        $card_description = isset($_POST['card_description']) ? trim($_POST['card_description']) : '';
        $due_date = isset($_POST['due_date']) ? trim($_POST['due_date']) : null;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thêm thẻ']);
            exit();
        }

        if ($list_id <= 0 || empty($card_title)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            // Lấy giá trị position lớn nhất
            $stmt = $pdo->prepare("SELECT IFNULL(MAX(position), 0) + 1 AS new_position FROM cards WHERE list_id = ?");
            $stmt->execute([$list_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_position = $result['new_position'];

            // Chèn bản ghi mới với giá trị position và due_date
            $stmt = $pdo->prepare("INSERT INTO cards (list_id, title, description, position, due_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$list_id, $card_title, $card_description, $new_position, $due_date ? $due_date : null]);
            $card_id = $pdo->lastInsertId();

            // Lấy tên người dùng gửi
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            // Lấy tiêu đề danh sách
            $stmt = $pdo->prepare("SELECT title FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            $list_title = $list['title'] ?? 'Danh sách không xác định';

            // Tạo thông báo
            $message = "$sender_name đã thêm thẻ '$card_title' vào bảng '$list_title'";
            if ($due_date) {
                $message .= " với hạn chót " . date('d/m/Y H:i', strtotime($due_date));
            }
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'add_card', $card_id);

            echo json_encode([
                'success' => true,
                'card' => [
                    'id' => $card_id,
                    'title' => htmlspecialchars($card_title),
                    'description' => htmlspecialchars($card_description),
                    'due_date' => $due_date
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Error in add_card: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm thẻ: ' . $e->getMessage()]);
        }
        break;

    case 'update_card_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
        $new_list_id = isset($_POST['new_list_id']) ? (int)$_POST['new_list_id'] : 0;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền di chuyển thẻ']);
            exit();
        }

        if ($card_id <= 0 || $new_list_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            // Lấy giá trị position lớn nhất trong danh sách mới
            $stmt = $pdo->prepare("SELECT IFNULL(MAX(position), 0) + 1 AS new_position FROM cards WHERE list_id = ?");
            $stmt->execute([$new_list_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_position = $result['new_position'];

            // Cập nhật list_id và position của thẻ
            $stmt = $pdo->prepare("UPDATE cards SET list_id = ?, position = ? WHERE id = ?");
            $stmt->execute([$new_list_id, $new_position, $card_id]);

            // Lấy thông tin để tạo thông báo
            $stmt = $pdo->prepare("SELECT title FROM cards WHERE id = ?");
            $stmt->execute([$card_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            $card_title = $card['title'] ?? 'Thẻ không xác định';

            $stmt = $pdo->prepare("SELECT title FROM lists WHERE id = ?");
            $stmt->execute([$new_list_id]);
            $new_list = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_list_title = $new_list['title'] ?? 'Danh sách không xác định';

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $message = "$sender_name đã di chuyển thẻ '$card_title' đến bảng '$new_list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'move_card', $card_id);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error in update_card_list: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi di chuyển thẻ: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_card_completion':
        if (!isset($_SESSION['user_id'])) {
            error_log('Session user_id not set');
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
        $completed = isset($_POST['completed']) ? (int)$_POST['completed'] : 0;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        if ($completed !== 0 && $completed !== 1) {
            error_log("Invalid completed value: $completed");
            echo json_encode(['success' => false, 'message' => 'Giá trị completed không hợp lệ']);
            exit();
        }

        if ($card_id <= 0) {
            error_log("Invalid card_id: $card_id");
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            error_log("Permission denied for user_id={$_SESSION['user_id']}, workspace_id=$workspace_id");
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền đánh dấu hoàn thành']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM cards WHERE id = ?");
            $stmt->execute([$card_id]);
            $cardExists = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cardExists) {
                error_log("Card not found: card_id=$card_id");
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy thẻ']);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE cards SET completed = ? WHERE id = ?");
            $stmt->execute([$completed, $card_id]);

            $rowCount = $stmt->rowCount();
            if ($rowCount === 0) {
                error_log("No rows updated for card_id=$card_id");
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy thẻ để cập nhật']);
                exit();
            }

            if ($completed == 1) {
                $stmt = $pdo->prepare("
                    DELETE FROM notifications 
                    WHERE card_id = ? AND type = 'overdue_card' AND user_id = ?
                ");
                $stmt->execute([$card_id, $_SESSION['user_id']]);
            }

            $stmt = $pdo->prepare("SELECT cards.title, lists.title AS list_title 
                                   FROM cards 
                                   JOIN lists ON cards.list_id = lists.id 
                                   WHERE cards.id = ?");
            $stmt->execute([$card_id]);
            $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
            $card_title = $card_info['title'] ?? 'Thẻ không xác định';
            $list_title = $card_info['list_title'] ?? 'Danh sách không xác định';

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $action = $completed ? 'đánh dấu hoàn thành' : 'bỏ đánh dấu hoàn thành';
            $message = "$sender_name đã $action thẻ '$card_title' trong bảng '$list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'complete_card', $card_id);

            $stmt = $pdo->prepare("SELECT completed FROM cards WHERE id = ?");
            $stmt->execute([$card_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'completed' => $card['completed']]);
        } catch (PDOException $e) {
            error_log('Error in toggle_card_completion: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage()]);
        }
        break;

    case 'delete_card':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa thẻ']);
            exit();
        }

        if ($card_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            // Lấy thông tin thẻ để tạo thông báo
            $stmt = $pdo->prepare("SELECT cards.title, lists.title AS list_title 
                                   FROM cards 
                                   JOIN lists ON cards.list_id = lists.id 
                                   WHERE cards.id = ?");
            $stmt->execute([$card_id]);
            $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
            $card_title = $card_info['title'] ?? 'Thẻ không xác định';
            $list_title = $card_info['list_title'] ?? 'Danh sách không xác định';

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            // Xóa thông báo liên quan đến thẻ
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE card_id = ? AND type = 'overdue_card'");
            $stmt->execute([$card_id]);

            // Xóa thẻ
            $stmt = $pdo->prepare("DELETE FROM cards WHERE id = ?");
            $stmt->execute([$card_id]);

            // Tạo thông báo
            $message = "$sender_name đã xóa thẻ '$card_title' khỏi bảng '$list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'delete_card');

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error in delete_card: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa thẻ: ' . $e->getMessage()]);
        }
        break;

    case 'delete_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa danh sách']);
            exit();
        }

        if ($list_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            // Kiểm tra xem danh sách có đang được lưu trữ không
            $stmt = $pdo->prepare("SELECT archived, title FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$list) {
                echo json_encode(['success' => false, 'message' => 'Danh sách không tồn tại']);
                exit();
            }

            if ($list['archived'] == 1) {
                echo json_encode(['success' => false, 'message' => 'Không thể xóa danh sách đã lưu trữ. Vui lòng khôi phục trước!']);
                exit();
            }

            $list_title = $list['title'] ?? 'Danh sách không xác định';

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE card_id IN (SELECT id FROM cards WHERE list_id = ?)");
            $stmt->execute([$list_id]);
            $stmt = $pdo->prepare("DELETE FROM cards WHERE list_id = ?");
            $stmt->execute([$list_id]);
            $stmt = $pdo->prepare("DELETE FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $pdo->commit();

            $message = "$sender_name đã xóa bảng '$list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'delete_list');

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error in delete_list: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa danh sách: ' . $e->getMessage()]);
        }
        break;

    case 'sort_cards':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $card_ids = isset($_POST['card_ids']) ? $_POST['card_ids'] : [];
        $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'asc';
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền sắp xếp thẻ']);
            exit();
        }

        if ($list_id <= 0 || !is_array($card_ids) || empty($card_ids)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $pdo->beginTransaction();
            foreach ($card_ids as $position => $card_id) {
                $stmt = $pdo->prepare("UPDATE cards SET position = ? WHERE id = ? AND list_id = ?");
                $stmt->execute([$position, $card_id, $list_id]);
            }
            $pdo->commit();

            $order = $sort_order === 'desc' ? 'DESC' : 'ASC';
            $stmt = $pdo->prepare("SELECT * FROM cards WHERE list_id = ? ORDER BY position $order");
            $stmt->execute([$list_id]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'cards' => array_map(function($card) {
                    return [
                        'id' => $card['id'],
                        'title' => htmlspecialchars($card['title']),
                        'description' => htmlspecialchars($card['description']),
                        'completed' => $card['completed'],
                        'due_date' => $card['due_date']
                    ];
                }, $cards)
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error in sort_cards: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi sắp xếp thẻ: ' . $e->getMessage()]);
        }
        break;

    case 'archive_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền lưu trữ danh sách']);
            exit();
        }

        if ($list_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT title FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            $list_title = $list['title'] ?? 'Danh sách không xác định';

            $stmt = $pdo->prepare("UPDATE lists SET archived = 1, archived_at = NOW() WHERE id = ?");
            $stmt->execute([$list_id]);

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $message = "$sender_name đã lưu trữ bảng '$list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'archive_list');

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error in archive_list: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu trữ danh sách: ' . $e->getMessage()]);
        }
        break;

    case 'get_archived_lists':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem danh sách đã lưu trữ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT id, title, archived_at FROM lists WHERE workspace_id = ? AND archived = 1");
            $stmt->execute([$workspace_id]);
            $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'lists' => array_map(function($list) {
                    return [
                        'id' => $list['id'],
                        'title' => htmlspecialchars($list['title']),
                        'archived_at' => $list['archived_at']
                    ];
                }, $lists)
            ]);
        } catch (PDOException $e) {
            error_log("Error in get_archived_lists: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách đã lưu trữ: ' . $e->getMessage()]);
        }
        break;

    case 'restore_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền khôi phục danh sách']);
            exit();
        }

        if ($list_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT title FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            $list_title = $list['title'] ?? 'Danh sách không xác định';

            $stmt = $pdo->prepare("UPDATE lists SET archived = 0, archived_at = NULL WHERE id = ?");
            $stmt->execute([$list_id]);
            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                echo json_encode(['success' => false, 'message' => 'Danh sách không tồn tại hoặc không thể khôi phục']);
                exit();
            }

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $message = "$sender_name đã khôi phục bảng '$list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'restore_list');

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error in restore_list: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi khôi phục danh sách: ' . $e->getMessage()]);
        }
        break;

    case 'check_list_status':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        if ($list_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT archived FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($list) {
                echo json_encode(['success' => true, 'archived' => $list['archived'] == 1]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Danh sách không tồn tại']);
            }
        } catch (PDOException $e) {
            error_log("Error in check_list_status: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'share_workspace':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 0;
        $user_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
        $access_level = isset($_POST['access_level']) ? trim($_POST['access_level']) : '';

        if ($workspace_id <= 0 || empty($user_email) || !in_array($access_level, ['view', 'edit'])) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
            $stmt->execute([$workspace_id]);
            $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workspace || $workspace['owner_id'] != $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chia sẻ không gian làm việc này']);
                exit();
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$user_email]);
            $shared_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shared_user) {
                echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại']);
                exit();
            }

            if ($shared_user['id'] == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Không thể chia sẻ cho chính bạn']);
                exit();
            }

            // Kiểm tra xem người dùng đã được chia sẻ chưa
            $stmt = $pdo->prepare("SELECT * FROM workspace_permissions WHERE workspace_id = ? AND user_id = ?");
            $stmt->execute([$workspace_id, $shared_user['id']]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'Người dùng này đã được chia sẻ trước đó']);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO workspace_permissions (workspace_id, user_id, access_level) VALUES (?, ?, ?)");
            $stmt->execute([$workspace_id, $shared_user['id'], $access_level]);

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $stmt = $pdo->prepare("SELECT name FROM workspaces WHERE id = ?");
            $stmt->execute([$workspace_id]);
            $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
            $workspace_name = $workspace['name'] ?? 'Không gian làm việc không xác định';

            $message = "$sender_name đã chia sẻ không gian làm việc '$workspace_name' với bạn (quyền: $access_level)";
            createNotification($pdo, $shared_user['id'], $_SESSION['user_id'], $workspace_id, $message, 'share_workspace');

            echo json_encode(['success' => true, 'message' => 'Chia sẻ không gian làm việc thành công']);
        } catch (PDOException $e) {
            error_log("Error in share_workspace: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi chia sẻ không gian làm việc: ' . $e->getMessage()]);
        }
        break;

    case 'get_workspace_collaborators':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 0;

        if ($workspace_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
            $stmt->execute([$workspace_id]);
            $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workspace) {
                echo json_encode(['success' => false, 'message' => 'Không gian làm việc không tồn tại']);
                exit();
            }

            if ($workspace['owner_id'] != $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem danh sách người được chia sẻ']);
                exit();
            }

            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$workspace['owner_id']]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            $owner_email = $owner ? htmlspecialchars($owner['email']) : 'Không xác định';

            $stmt = $pdo->prepare("
                SELECT u.id AS user_id, u.email, wp.access_level, wp.created_at 
                FROM workspace_permissions wp 
                JOIN users u ON wp.user_id = u.id 
                WHERE wp.workspace_id = ?
            ");
            $stmt->execute([$workspace_id]);
            $collaborators = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $collaborators_list = [
                [
                    'user_id' => $workspace['owner_id'],
                    'email' => $owner_email,
                    'access_level' => 'owner',
                    'created_at' => null
                ]
            ];

            foreach ($collaborators as $collaborator) {
                $collaborators_list[] = [
                    'user_id' => $collaborator['user_id'],
                    'email' => htmlspecialchars($collaborator['email']),
                    'access_level' => $collaborator['access_level'],
                    'created_at' => $collaborator['created_at']
                ];
            }

            echo json_encode([
                'success' => true,
                'collaborators' => $collaborators_list
            ]);
        } catch (PDOException $e) {
            error_log("Error in get_workspace_collaborators: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách người được chia sẻ: ' . $e->getMessage()]);
        }
        break;

    case 'remove_collaborator':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 0;
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        if ($workspace_id <= 0 || $user_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
            $stmt->execute([$workspace_id]);
            $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workspace) {
                echo json_encode(['success' => false, 'message' => 'Không gian làm việc không tồn tại']);
                exit();
            }

            if ($workspace['owner_id'] != $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền hủy chia sẻ']);
                exit();
            }

            if ($user_id == $workspace['owner_id']) {
                echo json_encode(['success' => false, 'message' => 'Không thể hủy chia sẻ với chủ sở hữu']);
                exit();
            }

            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_email = $user['email'] ?? 'Người dùng không xác định';

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $stmt = $pdo->prepare("SELECT name FROM workspaces WHERE id = ?");
            $stmt->execute([$workspace_id]);
            $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
            $workspace_name = $workspace['name'] ?? 'Không gian làm việc không xác định';

            $stmt = $pdo->prepare("DELETE FROM workspace_permissions WHERE workspace_id = ? AND user_id = ?");
            $stmt->execute([$workspace_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                $message = "$sender_name đã hủy chia sẻ không gian làm việc '$workspace_name' với $user_email";
                createNotification($pdo, $user_id, $_SESSION['user_id'], $workspace_id, $message, 'remove_collaborator');
                echo json_encode(['success' => true, 'message' => 'Đã hủy chia sẻ thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại trong danh sách chia sẻ']);
            }
        } catch (PDOException $e) {
            error_log("Error in remove_collaborator: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi hủy chia sẻ: ' . $e->getMessage()]);
        }
        break;

    case 'filter_lists_and_cards':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;
        $filter_type = isset($_POST['filter_type']) ? trim($_POST['filter_type']) : 'all';
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : null;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem không gian làm việc này']);
            exit();
        }

        try {
            date_default_timezone_set('Asia/Ho_Chi_Minh');

            $list_conditions = ["workspace_id = ?", "archived = 0"];
            $card_conditions = ["1=1"];
            $list_params = [$workspace_id];
            $card_params = [];

            if ($filter_type !== 'all') {
                if ($filter_type === 'today') {
                    $list_conditions[] = "DATE(created_at) = CURDATE()";
                    $card_conditions[] = "DATE(cards.created_at) = CURDATE()";
                } elseif ($filter_type === 'last7days') {
                    $list_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    $card_conditions[] = "cards.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                } elseif ($filter_type === 'last30days') {
                    $list_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    $card_conditions[] = "cards.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                } elseif ($filter_type === 'custom' && $start_date && $end_date) {
                    $list_conditions[] = "created_at BETWEEN ? AND ?";
                    $card_conditions[] = "cards.created_at BETWEEN ? AND ?";
                    $list_params[] = $start_date;
                    $list_params[] = $end_date . ' 23:59:59';
                    $card_params[] = $start_date;
                    $card_params[] = $end_date . ' 23:59:59';
                } else {
                    echo json_encode(['success' => false, 'message' => 'Loại bộ lọc không hợp lệ']);
                    exit();
                }
            }

            $list_conditions_sql = implode(" AND ", $list_conditions);
            $card_conditions_sql = implode(" AND ", $card_conditions);

            $stmt = $pdo->prepare("SELECT * FROM lists WHERE $list_conditions_sql");
            $stmt->execute($list_params);
            $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filtered_lists = [];
            foreach ($lists as $list) {
                $stmt = $pdo->prepare("SELECT cards.* FROM cards JOIN lists ON cards.list_id = lists.id WHERE cards.list_id = ? AND $card_conditions_sql ORDER BY cards.position");
                $stmt->execute(array_merge([$list['id']], $card_params));
                $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $filtered_lists[] = [
                    'id' => $list['id'],
                    'title' => htmlspecialchars($list['title']),
                    'cards' => array_map(function($card) {
                        return [
                            'id' => $card['id'],
                            'title' => htmlspecialchars($card['title']),
                            'description' => htmlspecialchars($card['description']),
                            'completed' => $card['completed'],
                            'position' => $card['position'],
                            'due_date' => $card['due_date'] ? date('c', strtotime($card['due_date'])) : null
                        ];
                    }, $cards)
                ];
            }

            echo json_encode([
                'success' => true,
                'lists' => $filtered_lists
            ]);
        } catch (PDOException $e) {
            error_log("Error in filter_lists_and_cards: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lọc dữ liệu: ' . $e->getMessage()]);
        }
        break;

    case 'get_notifications':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem thông báo']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM notifications
                WHERE user_id = ? AND workspace_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $workspace_id]);
            $total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $pdo->prepare("
                SELECT n.id, n.message, n.type, n.is_read, n.created_at, u.name AS sender_name
                FROM notifications n
                JOIN users u ON n.sender_id = u.id
                WHERE n.user_id = ? AND n.workspace_id = ?
                ORDER BY n.created_at DESC
                LIMIT ?, ?
            ");
            $stmt->bindValue(1, $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(2, $workspace_id, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->bindValue(4, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count
                FROM notifications
                WHERE user_id = ? AND workspace_id = ? AND is_read = 0
            ");
            $stmt->execute([$_SESSION['user_id'], $workspace_id]);
            $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

            $notifications = array_map(function($notification) {
                return [
                    'id' => $notification['id'],
                    'message' => mb_convert_encoding($notification['message'], 'UTF-8', 'UTF-8'),
                    'type' => $notification['type'],
                    'is_read' => (bool)$notification['is_read'],
                    'created_at' => $notification['created_at'],
                    'sender_name' => mb_convert_encoding($notification['sender_name'], 'UTF-8', 'UTF-8')
                ];
            }, $notifications);

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count,
                'total_notifications' => $total_notifications,
                'current_page' => $page,
                'total_pages' => ceil($total_notifications / $limit)
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('Error in get_notifications: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy thông báo: ' . $e->getMessage()]);
        }
        break;

    case 'edit_card':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
        $card_title = isset($_POST['card_title']) ? trim($_POST['card_title']) : '';
        $card_description = isset($_POST['card_description']) ? trim($_POST['card_description']) : '';
        $due_date = isset($_POST['due_date']) ? trim($_POST['due_date']) : null;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa thẻ']);
            exit();
        }

        if ($card_id <= 0 || empty($card_title)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT title, list_id FROM cards WHERE id = ?");
            $stmt->execute([$card_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$card) {
                echo json_encode(['success' => false, 'message' => 'Thẻ không tồn tại']);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE cards SET title = ?, description = ?, due_date = ? WHERE id = ?");
            $stmt->execute([$card_title, $card_description, $due_date ? $due_date : null, $card_id]);

            $stmt = $pdo->prepare("SELECT title FROM lists WHERE id = ?");
            $stmt->execute([$card['list_id']]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            $list_title = $list['title'] ?? 'Danh sách không xác định';

            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $message = "$sender_name đã chỉnh sửa thẻ '$card_title' trong bảng '$list_title'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'edit_card', $card_id);

            echo json_encode([
                'success' => true,
                'card' => [
                    'id' => $card_id,
                    'title' => htmlspecialchars($card_title),
                    'description' => htmlspecialchars($card_description),
                    'due_date' => $due_date
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Error in edit_card: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi chỉnh sửa thẻ: ' . $e->getMessage()]);
        }
        break;

    case 'search_cards':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;
        $query = isset($_POST['query']) ? trim($_POST['query']) : '';

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem không gian làm việc này']);
            exit();
        }

        if (empty($query)) {
            echo json_encode(['success' => false, 'message' => 'Từ khóa tìm kiếm không được để trống']);
            exit();
        }

        try {
            $list_conditions = ["workspace_id = ?", "archived = 0"];
            $list_params = [$workspace_id];
            $list_conditions_sql = implode(" AND ", $list_conditions);

            $stmt = $pdo->prepare("SELECT * FROM lists WHERE $list_conditions_sql");
            $stmt->execute($list_params);
            $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filtered_lists = [];
            foreach ($lists as $list) {
                $stmt = $pdo->prepare("SELECT * FROM cards WHERE list_id = ? AND (title LIKE ? OR description LIKE ?) ORDER BY position");
                $stmt->execute([$list['id'], "%$query%", "%$query%"]);
                $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($cards)) {
                    $filtered_lists[] = [
                        'id' => $list['id'],
                        'title' => htmlspecialchars($list['title']),
                        'cards' => array_map(function($card) {
                            return [
                                'id' => $card['id'],
                                'title' => htmlspecialchars($card['title']),
                                'description' => htmlspecialchars($card['description']),
                                'completed' => $card['completed'],
                                'position' => $card['position'],
                                'due_date' => $card['due_date']
                            ];
                        }, $cards)
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'lists' => $filtered_lists
            ]);
        } catch (PDOException $e) {
            error_log("Error in search_cards: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tìm kiếm: ' . $e->getMessage()]);
        }
        break;

    case 'delete_notification':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        if ($notification_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID thông báo không hợp lệ']);
            exit();
        }

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa thông báo trong workspace này']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE id = ? AND user_id = ? AND workspace_id = ?
            ");
            $stmt->execute([$notification_id, $_SESSION['user_id'], $workspace_id]);
            $rowCount = $stmt->rowCount();

            if ($rowCount > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Đã xóa thông báo thành công'
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Thông báo không tồn tại hoặc bạn không có quyền xóa'
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error in delete_notification: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi xóa thông báo: ' . $e->getMessage()
            ]);
        }
        break;

    case 'mark_all_notifications_as_read':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền đánh dấu thông báo']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND workspace_id = ? AND is_read = 0
            ");
            $stmt->execute([$_SESSION['user_id'], $workspace_id]);
            $rowCount = $stmt->rowCount();

            error_log("User {$_SESSION['user_id']} marked $rowCount notifications as read in workspace $workspace_id");

            if ($rowCount > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => "Đã đánh dấu $rowCount thông báo là đã đọc",
                    'updated_count' => $rowCount
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Không có thông báo mới',
                    'updated_count' => 0
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
        } catch (PDOException $e) {
            error_log("Error in mark_all_notifications_as_read: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi đánh dấu tất cả thông báo: ' . $e->getMessage()
            ]);
        }
        break;

    case 'update_card_position':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $position = isset($_POST['position']) ? (int)$_POST['position'] : 0;

        if ($card_id <= 0 || $list_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT workspace_id FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);

            $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $list['workspace_id']);
            if (!$permission['access'] || ($permission['permission'] != 'edit' && $permission['permission'] != 'owner')) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa thẻ này']);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE cards SET list_id = ?, position = ? WHERE id = ?");
            $stmt->execute([$list_id, $position, $card_id]);

            $stmt = $pdo->prepare("SELECT id FROM cards WHERE list_id = ? AND id != ? ORDER BY position");
            $stmt->execute([$list_id, $card_id]);
            $other_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $index = 0;
            foreach ($other_cards as $card) {
                if ($index == $position) {
                    $index++;
                }
                $stmt = $pdo->prepare("UPDATE cards SET position = ? WHERE id = ?");
                $stmt->execute([$index, $card['id']]);
                $index++;
            }

            echo json_encode(['success' => true, 'message' => 'Cập nhật vị trí thẻ thành công']);
        } catch (PDOException $e) {
            error_log("Error in update_card_position: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật vị trí: ' . $e->getMessage()]);
        }
        break;

    case 'get_nearing_due_cards':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem không gian làm việc này']);
            exit();
        }

        try {
            $query = "SELECT c.id, c.title, c.due_date, c.completed 
                      FROM cards c 
                      JOIN lists l ON c.list_id = l.id 
                      WHERE l.workspace_id = ? 
                      AND c.due_date IS NOT NULL 
                      AND c.completed = 0";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$workspace_id]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $nearing_due_cards = [];
            $now = new DateTime();
            foreach ($cards as $card) {
                $due_date = new DateTime($card['due_date']);
                $interval = $now->diff($due_date);
                $hours_until_due = ($interval->days * 24) + $interval->h;
                if ($hours_until_due <= 24 && $hours_until_due >= 0) {
                    $nearing_due_cards[] = [
                        'id' => $card['id'],
                        'title' => htmlspecialchars($card['title']),
                        'due_date' => $card['due_date'],
                        'completed' => $card['completed']
                    ];
                }
            }

            echo json_encode(['success' => true, 'cards' => $nearing_due_cards]);
        } catch (Exception $e) {
            error_log("Error in get_nearing_due_cards: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách thẻ gần hết hạn: ' . $e->getMessage()]);
        }
        break;

    case 'send_gmail_notification':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 1;
        $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;

        $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $workspace_id);
        if (!$permission['access']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền gửi thông báo']);
            exit();
        }

        if ($card_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        try {
            // Lấy thông tin thẻ
            $stmt = $pdo->prepare("SELECT c.title, c.due_date, l.title AS list_title 
                                   FROM cards c 
                                   JOIN lists l ON c.list_id = l.id 
                                   WHERE c.id = ?");
            $stmt->execute([$card_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                echo json_encode(['success' => false, 'message' => 'Thẻ không tồn tại']);
                exit();
            }

            // Lấy danh sách người nhận (chủ sở hữu và người được chia sẻ)
            $stmt = $pdo->prepare("
                SELECT u.email 
                FROM workspace_permissions wp 
                JOIN users u ON wp.user_id = u.id 
                WHERE wp.workspace_id = ?
                UNION
                SELECT u.email 
                FROM workspaces w 
                JOIN users u ON w.owner_id = u.id 
                WHERE w.id = ?
            ");
            $stmt->execute([$workspace_id, $workspace_id]);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($recipients)) {
                echo json_encode(['success' => false, 'message' => 'Không có người nhận để gửi thông báo']);
                exit();
            }

            // Cấu hình gửi email (sử dụng PHPMailer)
            // require_once 'path/to/PHPMailer/src/Exception.php';
            // require_once 'path/to/PHPMailer/src/PHPMailer.php';
            // require_once 'path/to/PHPMailer/src/SMTP.php';
            require_once 'vendor/PHPMailer/PHPMailer/src/Exception.php';
            require_once 'vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
            require_once 'vendor/PHPMailer/PHPMailer/src/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'dongtao805@gmail.com'; // Thay bằng email của bạn
            $mail->Password = 'ahqn tjge obkp kzow'; // Thay bằng App Password của Gmail
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('dongtao805@gmail.comcom', 'Task Manager');
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient['email']);
            }

            $mail->isHTML(true);
            $mail->Subject = 'Thông báo: Thẻ sắp hết hạn';
            $mail->Body = "
                <h2>Thông báo thẻ sắp hết hạn</h2>
                <p>Thẻ <strong>{$card['title']}</strong> trong bảng <strong>{$card['list_title']}</strong> sắp hết hạn.</p>
                <p>Hạn chót: <strong>" . date('d/m/Y H:i', strtotime($card['due_date'])) . "</strong></p>
                <p>Vui lòng kiểm tra và hoàn thành công việc đúng hạn!</p>
            ";

            $mail->send();

            // Tạo thông báo trong hệ thống
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $sender_name = $sender['name'] ?? 'Người dùng không xác định';

            $message = "$sender_name đã gửi thông báo qua Gmail về thẻ '{$card['title']}' sắp hết hạn trong bảng '{$card['list_title']}'";
            createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'gmail_notification', $card_id);

            echo json_encode(['success' => true, 'message' => 'Đã gửi thông báo qua Gmail thành công']);
        } catch (Exception $e) {
            error_log("Error in send_gmail_notification: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi gửi thông báo qua Gmail: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        break;
}

exit();