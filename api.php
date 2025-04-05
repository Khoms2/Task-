<?php
session_start();
header('Content-Type: application/json');

// Kết nối cơ sở dữ liệu
$host = 'localhost';
$dbname = 'task_manager';
$username = 'root'; // Thay bằng tên người dùng MySQL của bạn
$password = ''; // Thay bằng mật khẩu MySQL của bạn

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage()]);
    exit();
}

// Hàm kiểm tra quyền truy cập workspace
function checkWorkspacePermission($pdo, $user_id, $workspace_id) {
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
}

// Hàm tạo thông báo
function createNotification($pdo, $user_id, $sender_id, $workspace_id, $message, $type) {
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
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, sender_id, workspace_id, message, type, is_read)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$user['user_id'], $sender_id, $workspace_id, $message, $type]);
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
            $sender_name = $sender['name'];

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
            $due_date = isset($_POST['due_date']) ? trim($_POST['due_date']) : null; // Thêm trường due_date
            $workspace_id = 1;
        
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
                // Bước 1: Lấy giá trị position lớn nhất
                $stmt = $pdo->prepare("SELECT IFNULL(MAX(position), 0) + 1 AS new_position FROM cards WHERE list_id = ?");
                $stmt->execute([$list_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $new_position = $result['new_position'];
        
                // Bước 2: Chèn bản ghi mới với giá trị position và due_date
                $stmt = $pdo->prepare("INSERT INTO cards (list_id, title, description, position, due_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$list_id, $card_title, $card_description, $new_position, $due_date ? $due_date : null]);
                $card_id = $pdo->lastInsertId();
        
                // Lấy tên người dùng gửi
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $sender = $stmt->fetch(PDO::FETCH_ASSOC);
                $sender_name = $sender['name'];
        
                // Lấy tiêu đề danh sách
                $stmt = $pdo->prepare("SELECT title FROM lists WHERE id = ?");
                $stmt->execute([$list_id]);
                $list = $stmt->fetch(PDO::FETCH_ASSOC);
                $list_title = $list['title'];
        
                // Tạo thông báo
                $message = "$sender_name đã thêm thẻ '$card_title' vào bảng '$list_title'";
                if ($due_date) {
                    $message .= " với hạn chót " . date('d/m/Y H:i', strtotime($due_date));
                }
                createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'add_card');
        
                echo json_encode([
                    'success' => true,
                    'card' => [
                        'id' => $card_id,
                        'title' => htmlspecialchars($card_title),
                        'description' => htmlspecialchars($card_description),
                        'due_date' => $due_date // Trả về thông tin hạn chót
                    ]
                ]);
            } catch (PDOException $e) {
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
        $workspace_id = 1;

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
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
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
            $workspace_id = 1;
        
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
        
                // Nếu thẻ được đánh dấu hoàn thành, xóa thông báo quá hạn liên quan
                if ($completed == 1) {
                    $stmt = $pdo->prepare("
                        DELETE FROM notifications 
                        WHERE card_id = ? AND type = 'overdue_card' AND user_id = ?
                    ");
                    $stmt->execute([$card_id, $_SESSION['user_id']]);
                }
        
                // Lấy thông tin thẻ và danh sách
                $stmt = $pdo->prepare("SELECT cards.title, lists.title AS list_title 
                                       FROM cards 
                                       JOIN lists ON cards.list_id = lists.id 
                                       WHERE cards.id = ?");
                $stmt->execute([$card_id]);
                $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
                $card_title = $card_info['title'];
                $list_title = $card_info['list_title'];
        
                // Lấy tên người dùng gửi
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $sender = $stmt->fetch(PDO::FETCH_ASSOC);
                $sender_name = $sender['name'];
        
                // Tạo thông báo
                $action = $completed ? 'đánh dấu hoàn thành' : 'bỏ đánh dấu hoàn thành';
                $message = "$sender_name đã $action thẻ '$card_title' trong bảng '$list_title'";
                createNotification($pdo, $_SESSION['user_id'], $_SESSION['user_id'], $workspace_id, $message, 'complete_card');
        
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
                $workspace_id = 1;
            
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
                    // Xóa thông báo liên quan đến thẻ
                    $stmt = $pdo->prepare("
                        DELETE FROM notifications 
                        WHERE card_id = ? AND type = 'overdue_card' AND user_id = ?
                    ");
                    $stmt->execute([$card_id, $_SESSION['user_id']]);
            
                    // Xóa thẻ
                    $stmt = $pdo->prepare("DELETE FROM cards WHERE id = ?");
                    $stmt->execute([$card_id]);
                    echo json_encode(['success' => true]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa thẻ: ' . $e->getMessage()]);
                }
                break;

    case 'delete_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $workspace_id = 1;

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
            $stmt = $pdo->prepare("SELECT archived FROM lists WHERE id = ?");
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

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM cards WHERE list_id = ?");
            $stmt->execute([$list_id]);
            $stmt = $pdo->prepare("DELETE FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
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
        $workspace_id = 1;

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
                        'completed' => $card['completed']
                    ];
                }, $cards)
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lỗi khi sắp xếp thẻ: ' . $e->getMessage()]);
        }
        break;

    case 'archive_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $workspace_id = 1;

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
            $stmt = $pdo->prepare("UPDATE lists SET archived = 1, archived_at = NOW() WHERE id = ?");
            $stmt->execute([$list_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
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
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách đã lưu trữ: ' . $e->getMessage()]);
        }
        break;

    case 'restore_list':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $workspace_id = 1;

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
            $stmt = $pdo->prepare("UPDATE lists SET archived = 0, archived_at = NULL WHERE id = ?");
            $stmt->execute([$list_id]);
            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                echo json_encode(['success' => false, 'message' => 'Danh sách không tồn tại hoặc không thể khôi phục']);
                exit();
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
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
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    // case 'share_workspace':
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
        
                echo json_encode(['success' => true, 'message' => 'Chia sẻ không gian làm việc thành công']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi chia sẻ không gian làm việc: ' . $e->getMessage()]);
            }
            break;
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

            $stmt = $pdo->prepare("INSERT INTO workspace_permissions (workspace_id, user_id, access_level) VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE access_level = ?");
            $stmt->execute([$workspace_id, $shared_user['id'], $access_level, $access_level]);

            echo json_encode(['success' => true, 'message' => 'Chia sẻ không gian làm việc thành công']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi chia sẻ không gian làm việc: ' . $e->getMessage()]);
        }
        break;

    // case 'get_workspace_collaborators':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
            exit();
        }

        $workspace_id = isset($_POST['workspace_id']) ? (int)$_POST['workspace_id'] : 0;

        if ($workspace_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit();
        }

        $stmt = $pdo->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
        $stmt->execute([$workspace_id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$workspace || $workspace['owner_id'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem danh sách người được chia sẻ']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT u.email, wp.access_level, wp.created_at 
                                   FROM workspace_permissions wp 
                                   JOIN users u ON wp.user_id = u.id 
                                   WHERE wp.workspace_id = ?");
            $stmt->execute([$workspace_id]);
            $collaborators = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'collaborators' => array_map(function($collaborator) {
                    return [
                        'email' => htmlspecialchars($collaborator['email']),
                        'access_level' => $collaborator['access_level'],
                        'created_at' => $collaborator['created_at']
                    ];
                }, $collaborators)
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách người được chia sẻ: ' . $e->getMessage()]);
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
            // Kiểm tra workspace và chủ sở hữu
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
    
            // Lấy thông tin chủ sở hữu
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$workspace['owner_id']]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            $owner_email = $owner ? htmlspecialchars($owner['email']) : 'Không xác định';
    
            // Lấy danh sách người được chia sẻ
            $stmt = $pdo->prepare("
                SELECT u.id AS user_id, u.email, wp.access_level, wp.created_at 
                FROM workspace_permissions wp 
                JOIN users u ON wp.user_id = u.id 
                WHERE wp.workspace_id = ?
            ");
            $stmt->execute([$workspace_id]);
            $collaborators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Thêm chủ sở hữu vào danh sách (với vai trò "Chủ sở hữu")
            $collaborators_list = [
                [
                    'user_id' => $workspace['owner_id'],
                    'email' => $owner_email,
                    'access_level' => 'owner',
                    'created_at' => null // Chủ sở hữu không có thời gian chia sẻ
                ]
            ];
    
            // Thêm các người được chia sẻ khác
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
        //  Hủy chia sẻ không gian làm việc
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
                // Kiểm tra quyền của người dùng hiện tại
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
        
                // Không cho phép hủy chia sẻ với chính chủ sở hữu
                if ($user_id == $workspace['owner_id']) {
                    echo json_encode(['success' => false, 'message' => 'Không thể hủy chia sẻ với chủ sở hữu']);
                    exit();
                }
        
                // Xóa người dùng khỏi workspace_permissions
                $stmt = $pdo->prepare("DELETE FROM workspace_permissions WHERE workspace_id = ? AND user_id = ?");
                $stmt->execute([$workspace_id, $user_id]);
        
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Đã hủy chia sẻ thành công']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại trong danh sách chia sẻ']);
                }
            } catch (PDOException $e) {
                error_log("Error in remove_collaborator: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Lỗi khi hủy chia sẻ: ' . $e->getMessage()]);
            }
            break;

            // Lọc danh sách và thẻ theo ngày tạo
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
                    // Đặt múi giờ cho PHP
                    date_default_timezone_set('Asia/Ho_Chi_Minh'); // Đảm bảo múi giờ khớp với client
            
                    // Sử dụng mảng để lưu các điều kiện
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
                            $list_params[] = $end_date . ' 23:59:59'; // Đảm bảo bao gồm cả ngày cuối
                            $card_params[] = $start_date;
                            $card_params[] = $end_date . ' 23:59:59';
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Loại bộ lọc không hợp lệ']);
                            exit();
                        }
                    }
            
                    // Nối các điều kiện bằng " AND "
                    $list_conditions_sql = implode(" AND ", $list_conditions);
                    $card_conditions_sql = implode(" AND ", $card_conditions);
            
                    // Ghi log câu truy vấn để kiểm tra
                    error_log("List query: SELECT * FROM lists WHERE $list_conditions_sql");
                    error_log("List params: " . json_encode($list_params));
                    error_log("Card query: SELECT cards.* FROM cards JOIN lists ON cards.list_id = lists.id WHERE cards.list_id = ? AND $card_conditions_sql");
                    error_log("Card params: " . json_encode($card_params));
            
                    // Lấy danh sách
                    $stmt = $pdo->prepare("SELECT * FROM lists WHERE $list_conditions_sql");
                    $stmt->execute($list_params);
                    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
                    // Lấy thẻ cho các danh sách
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
                                    'due_date' => $card['due_date'] ? date('c', strtotime($card['due_date'])) : null // Trả về định dạng ISO
                                ];
                            }, $cards)
                        ];
                    }
            
                    echo json_encode([
                        'success' => true,
                        'lists' => $filtered_lists
                    ]);
                } catch (PDOException $e) {
                    error_log("Filter error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Lỗi khi lọc dữ liệu: ' . $e->getMessage()]);
                }
                break;
                // newnew

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
                // Lấy tổng số thông báo
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total
                    FROM notifications
                    WHERE user_id = ? AND workspace_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $workspace_id]);
                $total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
                // Lấy danh sách thông báo với phân trang
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
        
                // Lấy số lượng thông báo chưa đọc
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as unread_count
                    FROM notifications
                    WHERE user_id = ? AND workspace_id = ? AND is_read = 0
                ");
                $stmt->execute([$_SESSION['user_id'], $workspace_id]);
                $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
        
                echo json_encode([
                    'success' => true,
                    'notifications' => array_map(function($notification) {
                        return [
                            'id' => $notification['id'],
                            'message' => htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'),
                            'type' => $notification['type'],
                            'is_read' => (bool)$notification['is_read'],
                            'created_at' => $notification['created_at'],
                            'sender_name' => htmlspecialchars($notification['sender_name'], ENT_QUOTES, 'UTF-8')
                        ];
                    }, $notifications),
                    'unread_count' => $unread_count,
                    'total_notifications' => $total_notifications,
                    'current_page' => $page,
                    'total_pages' => ceil($total_notifications / $limit)
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (PDOException $e) {
                error_log('Lỗi trong get_notifications: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy thông báo: ' . $e->getMessage()]);
            }
            break;



// edit thẻ
case 'edit_card':
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
        exit();
    }

    $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
    $card_title = isset($_POST['card_title']) ? trim($_POST['card_title']) : '';
    $card_description = isset($_POST['card_description']) ? trim($_POST['card_description']) : '';
    $due_date = isset($_POST['due_date']) ? trim($_POST['due_date']) : null;
    $workspace_id = 1;

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
        $stmt = $pdo->prepare("UPDATE cards SET title = ?, description = ?, due_date = ? WHERE id = ?");
        $stmt->execute([$card_title, $card_description, $due_date ? $due_date : null, $card_id]);

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
        echo json_encode(['success' => false, 'message' => 'Lỗi khi chỉnh sửa thẻ: ' . $e->getMessage()]);
    }
    break;
//  search thẻ

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

    try {
        // Lấy danh sách không bị lưu trữ
        $list_conditions = ["workspace_id = ?", "archived = 0"];
        $list_params = [$workspace_id];

        $list_conditions_sql = implode(" AND ", $list_conditions);
        $stmt = $pdo->prepare("SELECT * FROM lists WHERE $list_conditions_sql");
        $stmt->execute($list_params);
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filtered_lists = [];
        foreach ($lists as $list) {
            // Tìm kiếm thẻ theo tiêu đề hoặc mô tả
            $stmt = $pdo->prepare("SELECT * FROM cards WHERE list_id = ? AND (title LIKE ? OR description LIKE ?) ORDER BY position");
            $stmt->execute([$list['id'], "%$query%", "%$query%"]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Chỉ thêm danh sách nếu có thẻ phù hợp
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
        echo json_encode(['success' => false, 'message' => 'Lỗi khi tìm kiếm: ' . $e->getMessage()]);
    }
    break;

    // xóa thông báo
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


        //  Đánh dấu đã đọc

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
        // Chỉ cập nhật các thông báo chưa đọc
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
            // Kiểm tra quyền truy cập
            $stmt = $pdo->prepare("SELECT workspace_id FROM lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
    
            $permission = checkWorkspacePermission($pdo, $_SESSION['user_id'], $list['workspace_id']);
            if (!$permission['access'] || ($permission['access_level'] !== 'edit' && $permission['access_level'] !== 'owner')) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa thẻ này']);
                exit();
            }
    
            // Cập nhật vị trí thẻ
            $stmt = $pdo->prepare("UPDATE cards SET list_id = ?, position = ? WHERE id = ?");
            $stmt->execute([$list_id, $position, $card_id]);
    
            // Cập nhật lại vị trí của các thẻ khác trong danh sách
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
}
// Lấy danh sách thẻ gần hết hạn GmailGmail


// Tắt hiển thị lỗi PHP
ini_set('display_errors', 0);
error_reporting(0);

// Kết nối cơ sở dữ liệu
try {
    $pdo = new PDO("mysql:host=localhost;dbname=task_manager", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Lấy danh sách thẻ gần hết hạn
if ($_POST['action'] === 'get_nearing_due_cards') {
    $workspace_id = $_POST['workspace_id'];
    
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
                $nearing_due_cards[] = $card;
            }
        }

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'cards' => $nearing_due_cards]);
        exit;
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error fetching cards: ' . $e->getMessage()]);
        exit;
    }
}

// Gửi thông báo qua Gmail
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_POST['action'] === 'send_gmail_notification') {
    $card_id = $_POST['card_id'];
    $card_title = $_POST['card_title'];
    $due_date = $_POST['due_date'];
    $workspace_id = $_POST['workspace_id'];

    // Log giá trị workspace_id để debug
    file_put_contents('debug.log', "Workspace ID: $workspace_id\n", FILE_APPEND);

    // Lấy danh sách email của người dùng trong workspace
    try {
        $query = "SELECT u.email 
                  FROM users u 
                  JOIN workspace_collaborators c ON u.id = c.user_id 
                  WHERE c.workspace_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$workspace_id]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log số lượng người nhận để debug
        file_put_contents('debug.log', "Số lượng người nhận: " . count($recipients) . "\n", FILE_APPEND);
        if (!empty($recipients)) {
            file_put_contents('debug.log', "Danh sách email: " . json_encode($recipients) . "\n", FILE_APPEND);
        }

        if (empty($recipients)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy người nhận trong workspace']);
            exit;
        }
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error fetching recipients: ' . $e->getMessage()]);
        exit;
    }

    $mail = new PHPMailer(true);
    try {
        // Cấu hình SMTP của Gmail
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'dongtao805@gmail.com';
        $mail->Password = 'tylk hkqr hnuq fkzs'; // App Password của bạn
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Bật debug để ghi log
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('phpmailer_debug.log', "$level: $str\n", FILE_APPEND);
        };

        // Người gửi
        $mail->setFrom('dongtao805@gmail.com', 'Kanban App');

        // Thêm tất cả người nhận
        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient['email']);
        }

        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = "Thông báo: Thẻ gần hết hạn - $card_title";
        $mail->Body = "
            <h3>Thẻ gần hết hạn</h3>
            <p><strong>Tên thẻ:</strong> $card_title</p>
            <p><strong>Hạn chót:</strong> " . date('d/m/Y H:i', strtotime($due_date)) . "</p>
            <p>Vui lòng kiểm tra và hoàn thành thẻ trước khi hết hạn!</p>
        ";

        $mail->send();

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
        exit;
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"]);
        exit;
    }
}

// Nếu không có action nào khớp, trả về lỗi
ob_clean();
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
?>
