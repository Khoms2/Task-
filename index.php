<?php
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kết nối cơ sở dữ liệu
$host = 'localhost';
$dbname = 'task_manager';
$username = 'root'; // Thay bằng tên người dùng MySQL của bạn
$password = ''; // Thay bằng mật khẩu MySQL của bạn

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Lỗi kết nối: " . $e->getMessage();
    exit();
}

// Lấy workspace (giả sử workspace_id = 1)
$workspace_id = 1; // Bạn có thể thay đổi nếu cần
$workspace = $pdo->query("SELECT * FROM workspaces WHERE id = $workspace_id")->fetch(PDO::FETCH_ASSOC);

// Lấy tất cả danh sách trong workspace (chưa lưu trữ)
$lists = $pdo->query("SELECT * FROM lists WHERE workspace_id = $workspace_id AND archived = 0")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Không gian làm việc</title>
    <link rel="icon" href="./img/logo2.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome cho biểu tượng -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f5f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 20px;
        }
        .header {
            background-color: #0079bf;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        .navbar {
            background-color: #e3f2fd;
            padding: 8px 20px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .navbar-left, .navbar-right {
            display: flex;
            gap: 8px;
        }
        .navbar .btn {
            font-size: 14px;
        }
        .notification-bell {
            position: relative;
            cursor: pointer;
            font-size: 16px;
            color: #5e6c84;
            padding: 6px 10px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }

        .notification-bell:hover {
            background-color: #091e4214;
            color: #172b4d;
        }

        .notification-bell .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

        .notification-dropdown {
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            padding: 0;
            border: none;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .notification-item {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
            color: #172b4d;
            background-color: #fff;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-item.unread {
            background-color: #e3f2fd;
            font-weight: 500;
        }

        .notification-item:hover {
            background-color: #f4f5f7;
        }

        .notification-item i {
            font-size: 16px;
            color: #5e6c84;
        }

        .notification-item .content {
            flex: 1;
        }

        .notification-item .time {
            font-size: 12px;
            color: #5e6c84;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-empty {
            padding: 10px;
            text-align: center;
            color: #5e6c84;
            font-size: 14px;
        }
        .board {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 10px 0;
        }
        .list {
            background-color: #ebecf0;
            border-radius: 8px;
            width: 270px;
            padding: 8px;
            min-height: 100px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
            flex-shrink: 0;
            transition: opacity 0.3s ease;
        }
        .list.loading {
            opacity: 0.5;
            pointer-events: none;
        }
        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 8px;
        }
        .list h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #172b4d;
            line-height: 20px;
        }
        .list-actions {
            cursor: pointer;
            color: #5e6c84;
            font-size: 14px;
            padding: 4px;
            border-radius: 3px;
        }
        .list-actions:hover {
            background-color: #091e4214;
            color: #172b4d;
        }
        .card {
            background-color: #fff;
            border-radius: 3px;
            padding: 8px;
            margin-bottom: 8px;
            box-shadow: 0 1px 0 rgba(9, 30, 66, 0.25);
            cursor: move;
            position: relative;
            transition: background-color 0.2s, transform 0.3s ease;
            opacity: 1;
        }
        .card.added {
            animation: slideIn 0.3s ease;
        }
        .card.removed {
            animation: slideOut 0.3s ease forwards;
        }
        .card.completed {
            background-color: #e4f0e2;
        }
        .card:hover {
            background-color: #f4f5f7;
        }
        .card-inner {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .card input[type="checkbox"] {
            margin: 0;
            accent-color: #2ecc71;
            margin-top: 25px;
            display: none;
        }
        .card:hover input[type="checkbox"],
        .card.completed input[type="checkbox"] {
            display: block;
        }
        .card-content {
            flex: 1;
        }
        .card-content strong {
            font-size: 14px;
            color: #172b4d;
            line-height: 20px;
            display: block;
        }
        .card-content p {
            margin: 0;
            font-size: 12px;
            color: #5e6c84;
            line-height: 16px;
        }
        .card-actions {
            display: none;
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
        }
        .card:hover .card-actions {
            display: block;
        }
        .card-actions .delete-card {
            color: #5e6c84;
            font-size: 12px;
            padding: 4px;
            border-radius: 3px;
            cursor: pointer;
        }
        .card-actions .delete-card:hover {
            color: #172b4d;
            background-color: #091e4214;
        }
        .card.nearing-due {
        background-color: #ffe5b4; /* Màu cam khác */
        border-left: 4px solid #ff9500; /* Viền cam đậm hơn */
            }
                .add-list, .add-card {
                    margin-top: 8px;
                }
                .add-list-btn, .add-card-btn {
                    background-color: transparent;
                    color: #5e6c84;
                    border: none;
                    padding: 8px;
                    border-radius: 3px;
                    width: 100%;
                    text-align: left;
                    font-size: 14px;
                    font-weight: 400;
                    transition: background-color 0.2s;
                    position: relative;
                }
                .add-list-btn:hover, .add-card-btn:hover {
                    background-color: #091e4214;
                    color: #172b4d;
                }
                .add-list-btn i, .add-card-btn i {
                    margin-right: 4px;
                }
                .add-list-btn.loading::after, .add-card-btn.loading::after {
                    content: '';
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    border: 2px solid #5e6c84;
                    border-top: 2px solid transparent;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                    position: absolute;
                    right: 8px;
                    top: 50%;
                    transform: translateY(-50%);
                }
                .add-list-form, .add-card-form {
                    display: none;
                    background-color: #ebecf0;
                    padding: 8px;
                    border-radius: 3px;
                }
                .add-list-form input, .add-card-form input, .add-card-form textarea {
                    width: 100%;
                    margin-bottom: 8px;
                    padding: 8px;
                    border-radius: 3px;
                    border: 1px solid #dfe1e6;
                    font-size: 14px;
                    box-shadow: inset 0 0 0 2px #dfe1e6;
                    transition: box-shadow 0.2s;
                }
                .add-list-form input:focus, .add-card-form input:focus, .add-card-form textarea:focus {
                    box-shadow: inset 0 0 0 2px #0079bf;
                    border-color: #0079bf;
                    outline: none;
                }
                .add-card-form textarea {
                    resize: none;
                    height: 60px;
                }
                .btn-primary {
                    background-color: #0079bf;
                    border: none;
                    font-size: 16.9px;
                    padding: 6px 12px;
                    border-radius: 7px;
                    margin: 10px ;
                }
                .btn-primary:hover {
                    background-color: #005ea6;
                }
                .btn-cancel {
                    background-color: transparent;
                    color: #5e6c84;
                    border: none;
                    font-size: 14px;
                    padding: 6px;
                    border-radius: 3px;
                }
                .btn-cancel:hover {
                    background-color: #091e4214;
                    color: #172b4d;
                }
                .dropdown-menu {
                    font-size: 14px;
                    border-radius: 3px;
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
                }
                .dropdown-item {
                    padding: 6px 12px;
                    color: #172b4d;
                }
                .dropdown-item:hover {
                    background-color: #f4f5f7;
                }
                .navbar-left {
                    display: flex;
                    align-items: center;
                    gap: 10px; /* Khoảng cách giữa các phần tử */
                }

                #searchInput {
                    width: 300px;
                    border-radius: 20px; /* Bo góc */
                    padding: 5px 10px;
                    font-size: 14px;
                }

                #searchInput:focus {
                    outline: none;
                    box-shadow: 0 0 5px rgba(0, 123, 255, 0.3); /* Hiệu ứng khi focus */
                }
                        .card.overdue {
                    border-left: 4px solid #dc3545; /* Đường viền đỏ */
                    background-color: #fff3f3; /* Nền đỏ nhạt */
                }
                /* Hiệu ứng chuyển động */
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                @keyframes slideOut {
                    from {
                        opacity: 1;
                        transform: translateY(0);
                    }
                    to {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                }
                @keyframes spin {
                    0% { transform: translateY(-50%) rotate(0deg); }
                    100% { transform: translateY(-50%) rotate(360deg); }
                }
                /* Hiệu ứng cho SweetAlert2 */
                .animated {
                    animation-duration: 0.5s;
                }
                .fadeInDown {
                    animation-name: fadeInDown;
                }
                .fadeInRight {
                    animation-name: fadeInRight;
                }
                .faster {
                    animation-duration: 0.3s;
                }
                @keyframes fadeInDown {
                    from {
                        opacity: 0;
                        transform: translate3d(0, -20px, 0);
                    }
                    to {
                        opacity: 1;
                        transform: translate3d(0, 0, 0);
                    }
                }
                @keyframes fadeInRight {
                    from {
                        opacity: 0;
                        transform: translate3d(20px, 0, 0);
                    }
                    to {
                        opacity: 1;
                        transform: translate3d(0, 0, 0);
                    }
                }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-briefcase me-2"></i> <?php echo htmlspecialchars($workspace['name']); ?></h1>
        <div>
            <span class="text-light me-3">Xin chào, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <button class="btn btn-light btn-sm logout-btn"><i class="fas fa-sign-out-alt me-1"></i> Đăng xuất</button>
        </div>
    </div>
<!-- Navbar -->
<div class="navbar">
    <div class="navbar-left">
        <button class="btn btn-sm btn-outline-primary share-workspace"><i class="fas fa-share-alt"></i> Chia sẻ</button>
<!-- Nút Xem người được chia sẻ -->
<button class="btn btn-sm btn-outline-info view-workspace-collaborators">
    <i class="fas fa-users"></i> Xem người được chia sẻ
</button>

<!-- Modal Xem người được chia sẻ -->
<div class="modal fade" id="viewCollaboratorsModal" tabindex="-1" aria-labelledby="viewCollaboratorsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCollaboratorsModalLabel">Danh sách người được chia sẻ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form thêm người được chia sẻ -->
                <!-- <form id="shareWorkspaceForm" class="mb-3">
                    <div class="mb-3">
                        <label for="userEmail" class="form-label">Email người dùng</label>
                        <input type="email" class="form-control" id="userEmail" name="user_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="accessLevel" class="form-label">Quyền truy cập</label>
                        <select class="form-select" id="accessLevel" name="access_level">
                            <option value="view">Xem</option>
                            <option value="edit">Chỉnh sửa</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Chia sẻ</button>
                </form> -->

                <!-- Danh sách người được chia sẻ -->
                <h6>Danh sách người được chia sẻ</h6>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Quyền</th>
                            <th>Thời gian chia sẻ</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="collaborators-list">
                        <tr><td colspan="4">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
        <!-- <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm thẻ..."  style="width: 300px;" >
        <i class="fa-solid fa-magnifying-glass"></i> -->
        <div style="position: relative; width: 300px;">
    <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm thẻ... "  
        style="width: 100%; padding-left: 30px; box-sizing: border-box; ">
    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left : 10px; top: 50%; transform: translateY(-50%); color: gray;"></i>
</div>

      
       
    </div>
    
    <div class="navbar-right">
        <!-- Chuông thông báo -->
        <div class="dropdown me-2">
    <span class="notification-bell" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <span class="badge bg-danger" id="unread-count" style="display: none;">0</span>
    </span>
    <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationBell">
        <div class="notifications-header d-flex justify-content-between align-items-center p-2">
            <span>Thông báo</span>
            <button class="btn btn-sm btn-outline-primary mark-all-read">Đánh dấu tất cả đã đọc</button>
        </div>
        <div id="notification-list"></div>
        <div id="load-more-notifications" class="text-center p-2" style="display: none;">
            <button class="btn btn-sm btn-outline-secondary">Tải thêm</button>
        </div>
    </div>
</div>

        <button class="btn btn-sm btn-outline-secondary view-archived-lists"><i class="fas fa-archive"></i> Xem danh sách đã lưu trữ</button>
        <!-- Thêm dropdown bộ lọc -->
        <div class="dropdown me-2">
            <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-filter"></i> Bộ lọc
            </button>
            <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                <li><a class="dropdown-item filter-option" href="#" data-filter="all">Tất cả</a></li>
                <li><a class="dropdown-item filter-option" href="#" data-filter="today">Hôm nay</a></li>
                <li><a class="dropdown-item filter-option" href="#" data-filter="last7days">7 ngày qua</a></li>
                <li><a class="dropdown-item filter-option" href="#" data-filter="last30days">30 ngày qua</a></li>
                <li><a class="dropdown-item filter-option" href="#" data-filter="custom">Tùy chỉnh</a></li>
            </ul>
        </div>
        <!-- Thêm nút xóa bộ lọc -->
        <button class="btn btn-sm btn-outline-danger clear-filter me-2" style="display: none;"><i class="fas fa-times"></i> Xóa bộ lọc</button>
    </div>
</div>
  

    <!-- Modal để chọn khoảng thời gian tùy chỉnh -->
    <div class="modal fade" id="customFilterModal" tabindex="-1" aria-labelledby="customFilterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customFilterModalLabel">Chọn khoảng thời gian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="startDate" class="form-label">Từ ngày</label>
                        <input type="date" class="form-control" id="startDate">
                    </div>
                    <div class="mb-3">
                        <label for="endDate" class="form-label">Đến ngày</label>
                        <input type="date" class="form-control" id="endDate">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary apply-custom-filter">Áp dụng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Board -->
    <div class="board" id="lists-container">
        <!-- Hiển thị các danh sách -->
        <?php foreach ($lists as $list): ?>
            <div class="list" data-list-id="<?php echo $list['id']; ?>">
                <div class="list-header">
                    <h3><?php echo htmlspecialchars($list['title']); ?></h3>
                    <div class="dropdown">
                        <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item sort-cards" href="#" data-sort="asc">Sắp xếp A-Z</a></li>
                            <li><a class="dropdown-item sort-cards" href="#" data-sort="desc">Sắp xếp Z-A</a></li>
                            <li><a class="dropdown-item archive-list" href="#">Lưu trữ danh sách này</a></li>
                            <li><a class="dropdown-item delete-list" href="#">Xóa danh sách này</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Hiển thị các thẻ trong danh sách -->
                <div class="cards-container">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM cards WHERE list_id = ?");
                    $stmt->execute([$list['id']]);
                    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cards as $card):
                        if (!isset($card['id']) || !is_numeric($card['id']) || $card['id'] <= 0) {
                            error_log("Invalid card ID for card: " . json_encode($card));
                            continue;
                        }
                        $completed = isset($card['completed']) ? $card['completed'] : 0;
                    ?>
            <!--  -->
                            <div class="card <?php echo $completed ? 'completed' : ''; ?> <?php echo (strtotime($card['due_date']) < time() && !$completed) ? 'overdue' : ''; ?>" data-card-id="<?php echo htmlspecialchars($card['id']); ?>">
                            <div class="card-inner">
                                <input type="checkbox" class="complete-card" data-card-id="<?php echo htmlspecialchars($card['id']); ?>" <?php echo $completed ? 'checked' : ''; ?>>
                                <div class="card-content">
                                    <strong><?php echo htmlspecialchars($card['title']); ?></strong>
                                    <p><?php echo htmlspecialchars($card['description']); ?></p>
                                    <?php if (!empty($card['due_date'])): ?>
                                        <small class="text-muted">Hạn chót: <?php echo date('d/m/Y H:i', strtotime($card['due_date'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-actions">
                                <!-- <span class="delete-card"><i class="fas fa-times"></i></span> -->
                                <span class="edit-card"><i class="fas fa-edit"></i></span>
                                <span class="delete-card"><i class="fas fa-times"></i></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Nút thêm thẻ -->
                <div class="add-card">
                    <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
                    <!-- <form class="add-card-form">
                        <input type="hidden" name="list_id" value="<?php echo $list['id']; ?>">
                        <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                        <textarea name="card_description" placeholder="Mô tả..."></textarea>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Thêm</button>
                            <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                        </div>
                    </form> -->
                    <form class="add-card-form">
                    <input type="hidden" name="list_id" value="<?php echo $list['id']; ?>">
                    <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                    <textarea name="card_description" placeholder="Mô tả..."></textarea>
                    <input type="datetime-local" name="due_date" placeholder="Hạn chót..."> 
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Thêm</button>
                        <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                    </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Nút thêm danh sách -->
        <div class="list">
            <div class="add-list">
                <button class="add-list-btn"><i class="fas fa-plus"></i> Tạo bảng mới</button>
                <form class="add-list-form">
                    <input type="text" name="list_title" placeholder="Tên bảng..." required>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Thêm</button>
                        <button type="button" class="btn btn-cancel cancel-list">Hủy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <!-- Bootstrap JS và Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Moment.js để định dạng thời gian -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/vi.min.js"></script>
    <script>
        
                            // Hàm debounce để hạn chế số lượng yêu cầu AJAX
                            function debounce(func, wait) {
                                let timeout;
                                return function executedFunction(...args) {
                                    const later = () => {
                                        clearTimeout(timeout);
                                        func(...args);
                                    };
                                    clearTimeout(timeout);
                                    timeout = setTimeout(later, wait);
                                };
                            }

                        // Hàm hiển thị thông báo toast
                        function showToast(icon, title, text = '', timer = 1500) {
                            Swal.fire({
                                icon,
                                title,
                                text,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
                        }

            // Kéo thả thẻ giữa các danh sách
            document.querySelectorAll('.cards-container').forEach(container => {
                new Sortable(container, {
                    group: 'shared',
                    animation: 150,
                    onEnd: debounce(function(evt) {
                        const card = evt.item;
                        const cardId = card.dataset.cardId;
                        const newList = evt.to.closest('.list');
                        const newListId = newList.dataset.listId;

                        newList.classList.add('loading');

                        $.ajax({
                            url: 'api.php',
                            method: 'POST',
                            data: {
                                action: 'update_card_list',
                                card_id: cardId,
                                new_list_id: newListId
                            },
                            dataType: 'json',
                            success: function(response) {
                                newList.classList.remove('loading');
                                if (response.success) {
                                    showToast('success', 'Đã di chuyển thẻ!');
                                } else {
                                    showToast('error', 'Lỗi khi di chuyển thẻ!', response.message || 'Không có thông tin lỗi');
                                }
                            },
                            error: function() {
                                newList.classList.remove('loading');
                                showToast('error', 'Đã có lỗi xảy ra!');
                            }
                        });
                    }, 300)
                });
            });

            // Đánh dấu thẻ hoàn thành
            $(document).on('change', '.complete-card', debounce(function() {
                console.log('Sự kiện change được kích hoạt');

                const $checkbox = $(this);
                const $card = $checkbox.closest('.card');
                const cardId = $card.data('card-id');
                console.log('Card element:', $card[0]);
                console.log('Card ID:', cardId);

                // Kiểm tra cardId
                if (!cardId || isNaN(cardId)) {
                    console.error('Card ID không hợp lệ:', cardId);
                    showToast('error', 'Card ID không hợp lệ!');
                    return;
                }

                const completed = $checkbox.is(':checked') ? 1 : 0;
                console.log('Gửi yêu cầu toggle_card_completion:', { cardId, completed });

                const $list = $card.closest('.list');
                $list.addClass('loading');

                $.ajax({
                    url: 'api.php',
                    method: 'POST',
                    data: {
                        action: 'toggle_card_completion',
                        card_id: cardId,
                        completed: completed
                    },
                    dataType: 'json',
                    success(response) {
    $list.removeClass('loading');
    if (response.success) {
        $checkbox.prop('checked', response.completed === 1);
        if (response.completed === 1) {
            $card.addClass('completed');
            showToast('success', 'Đã đánh dấu hoàn thành!');
        } else {
            $card.removeClass('completed');
            showToast('info', 'Đã bỏ đánh dấu hoàn thành!');
        }
        loadListsAndCards(); // Làm mới giao diện
    } else {
        showToast('error', 'Lỗi khi cập nhật trạng thái!', response.message || 'Không có thông tin lỗi', 3000);
        $checkbox.prop('checked', !completed);
    }
},
                    error(xhr, status, error) {
                        console.error('Lỗi khi gửi yêu cầu:', error);
                        $list.removeClass('loading');
                        showToast('error', 'Đã có lỗi xảy ra!', `Chi tiết lỗi: ${xhr.status} - ${error}`, 3000);
                        $checkbox.prop('checked', !completed);
                    }
                });
            }, 300));

            // Hàm debounce (nếu chưa có)
            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

        // Hàm hiển thị thông báo toast
        function showToast(icon, title, text = '', timer = 1500) {
            Swal.fire({
                icon,
                title,
                text,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer,
                customClass: {
                    popup: 'animated fadeInRight faster'
                }
            });
        }
        // Xóa thẻ
        $(document).on('click', '.delete-card', function() {
            const card = $(this).closest('.card');
            const cardId = card.data('card-id');
            const list = card.closest('.list');

            Swal.fire({
                title: 'Xóa thẻ này?',
                text: 'Thẻ sẽ bị xóa vĩnh viễn. Bạn có chắc chắn muốn tiếp tục?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xóa vĩnh viễn',
                cancelButtonText: 'Hủy',
                customClass: {
                    popup: 'animated fadeInDown faster',
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    list.addClass('loading');
                    $.ajax({
                        url: 'api.php',
                        type: 'POST',
                        data: {
                            action: 'delete_card',
                            card_id: cardId
                        },
                        dataType: 'json',
                        success: function(response) {
                            list.removeClass('loading');
                            if (response.success) {
                                card.addClass('removed');
                                setTimeout(() => card.remove(), 300);
                                showToast('success', 'Đã xóa thẻ!');
                            } else {
                                showToast('error', response.message || 'Lỗi khi xóa thẻ!');
                            }
                        },
                        error: function(xhr, status, error) {
                            list.removeClass('loading');
                            showToast('error', 'Lỗi khi xóa thẻ!', 'Chi tiết lỗi: ' + error, 3000);
                        }
                    });
                }
            });
        });

        // Xóa danh sách
        $(document).on('click', '.delete-list', function(e) {
            e.preventDefault();
            const list = $(this).closest('.list');
            const listId = list.data('list-id');

            Swal.fire({
                title: 'Xóa danh sách này?',
                text: 'Tất cả các thẻ trong danh sách này sẽ bị xóa vĩnh viễn. Bạn có chắc chắn muốn tiếp tục?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xóa vĩnh viễn',
                cancelButtonText: 'Hủy',
                customClass: {
                    popup: 'animated fadeInDown faster',
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    list.addClass('loading');
                    $.ajax({
                        url: 'api.php',
                        type: 'POST',
                        data: {
                            action: 'delete_list',
                            list_id: listId
                        },
                        dataType: 'json',
                        success: function(response) {
                            list.removeClass('loading');
                            if (response.success) {
                                list.addClass('removed');
                                setTimeout(() => list.remove(), 300);
                                showToast('success', 'Đã xóa danh sách!');
                            } else {
                                showToast('error', response.message || 'Lỗi khi xóa danh sách!');
                            }
                        },
                        error: function(xhr, status, error) {
                            list.removeClass('loading');
                            showToast('error', 'Lỗi khi xóa danh sách!', 'Chi tiết lỗi: ' + error, 3000);
                        }
                    });
                }
            });
        });

        // Chia sẻ không gian làm việc
        $(document).on('click', '.share-workspace', function() {
            const workspaceId = <?php echo $workspace_id; ?>;

            Swal.fire({
                title: 'Chia sẻ không gian làm việc',
                html: `
                    <input type="email" id="collaborator-email" class="swal2-input" placeholder="Nhập email người dùng">
                    <select id="access-level" class="swal2-select">
                        <option value="edit">Có thể chỉnh sửa</option>
                        <option value="view">Chỉ xem</option>
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: 'Mời',
                cancelButtonText: 'Hủy',
                customClass: {
                    popup: 'animated fadeInDown faster',
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false,
                preConfirm: () => {
                    const email = Swal.getPopup().querySelector('#collaborator-email').value;
                    const accessLevel = Swal.getPopup().querySelector('#access-level').value;
                    if (!email) {
                        Swal.showValidationMessage('Vui lòng nhập email');
                        return false;
                    }
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        Swal.showValidationMessage('Email không hợp lệ');
                        return false;
                    }
                    return { email: email, accessLevel: accessLevel };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { email, accessLevel } = result.value;
                    $.ajax({
                        url: 'api.php',
                        type: 'POST',
                        data: {
                            action: 'share_workspace',
                            workspace_id: workspaceId,
                            user_email: email,
                            access_level: accessLevel
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showToast('success', 'Đã gửi lời mời!', `Đã mời ${email} với quyền ${accessLevel === 'edit' ? 'chỉnh sửa' : 'chỉ xem'}.`, 2000);
                            } else {
                                showToast('error', 'Lỗi khi gửi lời mời!', response.message, 2000);
                            }
                        },
                        error: function(xhr, status, error) {
                            showToast('error', 'Lỗi khi gửi lời mời!', 'Chi tiết lỗi: ' + error, 3000);
                        }
                    });
                }
            });
        });
        // newnew
        // Đặt ngôn ngữ Moment.js thành tiếng Việt
        $(document).ready(function() {
            moment.locale('vi');

            // Khi dropdown thông báo được mở, đánh dấu tất cả thông báo là đã đọc
            $('.notification-bell').on('click', function() {
                markAllNotificationsAsRead();
            });
        });

        // Hàm lấy thông báo
        let currentNotificationPage = 1;

        function fetchNotifications(page = 1) {
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'get_notifications',
            workspace_id: Number(<?php echo $workspace_id; ?>), // Ép kiểu thành số
            page: Number(page) // Ép kiểu thành số
        },
        dataType: 'json',
        success: function(response) {
            console.log('Phản hồi từ get_notifications:', response);
            if (response.success) {
                const $notificationList = $('#notification-list');
                const $unreadCount = $('#unread-count');

                const unreadCount = response.unread_count;
                if (unreadCount > 0) {
                    $unreadCount.text(unreadCount).show();
                } else {
                    $unreadCount.hide();
                }

                if (response.notifications.length === 0) {
                    $notificationList.html('<div class="notification-empty">Không có thông báo nào.</div>');
                } else {
                    $notificationList.empty();
                    response.notifications.forEach(notification => {
                        const timeAgo = moment(notification.created_at).fromNow();
                        const iconClass = {
                            'add_list': 'fas fa-list',
                            'add_card': 'fas fa-plus-square',
                            'complete_card': 'fas fa-check-circle',
                            'overdue_card': 'fas fa-clock'
                        }[notification.type] || 'fas fa-info-circle';

                        const $notificationItem = $(`
                            <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-notification-id="${notification.id}">
                                <i class="${iconClass}"></i>
                                <div class="content">
                                    <div>${notification.message}</div>
                                    <div class="time">${timeAgo}</div>
                                </div>
                              
                            </div>
                        `);
                        $notificationList.append($notificationItem);
                    });
                }
            } else {
                showToast('error', 'Lỗi khi lấy thông báo!', response.message || 'Không có thông tin lỗi', 2000);
            }
        },
        error: function(xhr, status, error) {
            console.log('Lỗi khi gọi get_notifications:', xhr.responseText);
            showToast('error', 'Lỗi khi lấy thông báo!', 'Chi tiết lỗi: ' + xhr.responseText, 3000);
        }
    });
}
// sk xóa
$(document).on('click', '.delete-notification', function() {
    const $item = $(this).closest('.notification-item');
    const notificationId = $item.data('notification-id');
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'delete_notification',
            notification_id: notificationId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $item.remove();
                fetchNotifications(1); // Cập nhật lại danh sách
            } else {
                showToast('error', 'Lỗi khi xóa thông báo!', response.message);
            }
        }
    });
});
// Gọi lần đầu khi tải trang
$(document).ready(function() {
    moment.locale('vi');
    fetchNotifications();
    setInterval(fetchNotifications, 10000);
});

// Xử lý nút "Tải thêm"
$(document).on('click', '#load-more-notifications', function() {
    fetchNotifications(currentNotificationPage + 1);
});

// Gọi lần đầu khi tải trang
$(document).ready(function() {
    moment.locale('vi');
    fetchNotifications();
    setInterval(() => fetchNotifications(1), 10000); // Cập nhật thông báo mỗi 10 giây, chỉ lấy trang đầu tiên
});

                // Hàm đánh dấu tất cả thông báo là đã đọc
                function markAllNotificationsAsRead() {
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'mark_all_notifications_as_read',
            workspace_id: <?php echo $workspace_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            console.log('Phản hồi từ mark_all_notifications_as_read:', response);
            if (response.success) {
                fetchNotifications(); // Cập nhật lại danh sách thông báo
                showToast('success', response.message || 'Đã đánh dấu tất cả thông báo là đã đọc!');
            } else {
                showToast('error', 'Lỗi khi đánh dấu thông báo!', response.message || 'Không có thông tin lỗi', 2000);
            }
        },
        error: function(xhr, status, error) {
            console.log('Lỗi khi gọi mark_all_notifications_as_read:', xhr.responseText);
            showToast('error', 'Lỗi khi đánh dấu thông báo!', 'Chi tiết lỗi: ' + (xhr.responseText || error), 3000);
        }
    });
}

                // Đăng xuất
                // $(document).on('click', '.logout-btn', function() {
                //     Swal.fire({
                //         title: 'Bạn có chắc chắn muốn đăng xuất?',
                //         icon: 'warning',
                //         showCancelButton: true,
                //         confirmButtonText: 'Đăng xuất',
                //         cancelButtonText: 'Hủy',
                //         customClass: {
                //             popup: 'animated fadeInDown faster',
                //             confirmButton: 'btn btn-danger',
                //             cancelButton: 'btn btn-secondary'
                //         },
                //         buttonsStyling: false
                //     }).then((result) => {
                //         if (result.isConfirmed) {
                //             $.ajax({
                //                 url: 'auth.php',
                //                 type: 'POST',
                //                 data: {
                //                     action: 'logout'
                //                 },
                //                 dataType: 'json',
                //                 success: function(response) {
                //                     if (response.success) {
                //                         showToast('success', 'Đăng xuất thành công!').then(() => {
                //                             window.location.href = 'login.php';
                //                         });
                //                     } else {
                //                         showToast('error', 'Lỗi khi đăng xuất!');
                //                     }
                //                 },
                //                 error: function() {
                //                     showToast('error', 'Lỗi khi đăng xuất!');
                //                 }
                //             });
                //         }
                //     });
                // });
                $(document).on('click', '.logout-btn', function() {
    Swal.fire({
        title: 'Bạn có chắc chắn muốn đăng xuất?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đăng xuất',
        cancelButtonText: 'Hủy',
        customClass: {
            popup: 'animated fadeInDown faster',
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'auth.php',
                type: 'POST',
                data: {
                    action: 'logout'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Hiển thị thông báo nhưng không chờ nó hoàn tất
                        showToast('success', 'Đăng xuất thành công!');
                        // Chuyển hướng ngay lập tức
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000); // 500 Đợi 0.5 giây để người dùng thấy thông báo (tùy chỉnh thời gian nếu cần)
                    } else {
                        showToast('error', 'Lỗi khi đăng xuất!');
                    }
                },
                error: function() {
                    showToast('error', 'Lỗi khi đăng xuất!');
                }
            });
        }
    });
});

                // Hiển thị form thêm danh sách
                $('.add-list-btn').on('click', function() {
                    $(this).hide();
                    $(this).siblings('.add-list-form').show().find('input[name="list_title"]').focus();
                });

                // Hủy thêm danh sách
                $('.cancel-list').on('click', function() {
                    const form = $(this).closest('.add-list-form');
                    form.hide();
                    form.siblings('.add-list-btn').show();
                    form.find('input[name="list_title"]').val('');
                });

                // Hiển thị form thêm thẻ
                $(document).on('click', '.add-card-btn', function() {
                    $(this).hide();
                    $(this).siblings('.add-card-form').show().find('input[name="card_title"]').focus();
                });

                // Hủy thêm thẻ
                $(document).on('click', '.cancel-card', function() {
                    const form = $(this).closest('.add-card-form');
                    form.hide();
                    form.siblings('.add-card-btn').show();
                    form.find('input[name="card_title"]').val('');
                    form.find('textarea[name="card_description"]').val('');
                });

        // Xử lý thêm danh sách mới
        $('.add-list-form').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const listTitle = form.find('input[name="list_title"]').val();
            const addListBtn = form.siblings('.add-list-btn');

            addListBtn.addClass('loading');

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'add_list',
                    list_title: listTitle,
                    workspace_id: <?php echo $workspace_id; ?>
                },
                dataType: 'json',
                success: function(response) {
                    addListBtn.removeClass('loading');
                    if (response.success) {
                        const newList = `
                            <div class="list" data-list-id="${response.list.id}">
                                <div class="list-header">
                                    <h3>${response.list.title}</h3>
                                    <div class="dropdown">
                                        <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item sort-cards" href="#" data-sort="asc">Sắp xếp A-Z</a></li>
                                            <li><a class="dropdown-item sort-cards" href="#" data-sort="desc">Sắp xếp Z-A</a></li>
                                            <li><a class="dropdown-item archive-list" href="#">Lưu trữ danh sách này</a></li>
                                            <li><a class="dropdown-item delete-list" href="#">Xóa danh sách này</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="cards-container"></div>
                                <div class="add-card">
                                    <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
                                    <form class="add-card-form">
                                        <input type="hidden" name="list_id" value="${response.list.id}">
                                        <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                                        <textarea name="card_description" placeholder="Mô tả..."></textarea>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">Thêm</button>
                                            <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                                        </div>
                                    </form>
                                </div>
                            </div>`;
                        form.closest('.list').before(newList);
                        form.find('input[name="list_title"]').val('');
                        form.hide();
                        form.siblings('.add-list-btn').show();

                        new Sortable(document.querySelectorAll('.cards-container').slice(-1)[0], {
                            group: 'shared',
                            animation: 150,
                            onEnd: debounce(function(evt) {
                                const card = evt.item;
                                const cardId = card.dataset.cardId;
                                const newList = evt.to.closest('.list');
                                const newListId = newList.dataset.listId;

                                newList.classList.add('loading');

                                $.ajax({
                                    url: 'api.php',
                                    method: 'POST',
                                    data: {
                                        action: 'update_card_list',
                                        card_id: cardId,
                                        new_list_id: newListId
                                    },
                                    dataType: 'json',
                                    success: function(response) {
                                        newList.classList.remove('loading');
                                        if (response.success) {
                                            showToast('success', 'Đã di chuyển thẻ!');
                                        } else {
                                            showToast('error', 'Lỗi khi di chuyển thẻ!', response.message || 'Không có thông tin lỗi');
                                        }
                                    },
                                    error: function() {
                                        newList.classList.remove('loading');
                                        showToast('error', 'Đã có lỗi xảy ra!');
                                    }
                                });
                            }, 300)
                        });

                        showToast('success', 'Đã thêm danh sách!');
                    } else {
                        showToast('error', response.message);
                    }
                },
                error: function() {
                    addListBtn.removeClass('loading');
                    showToast('error', 'Đã có lỗi xảy ra!');
                }
            });
        });
// edit thẻ mới
$(document).on('click', '.edit-card', function() {
    const $card = $(this).closest('.card');
    const cardId = $card.data('card-id');
    const title = $card.find('.card-content strong').text();
    const description = $card.find('.card-content p').text();
    const dueDate = $card.find('.card-content small').text().replace('Hạn chót: ', '');

    Swal.fire({
        title: 'Chỉnh sửa thẻ',
        html: `
            <input id="edit-title" class="swal2-input" value="${title}">
            <textarea id="edit-description" class="swal2-textarea">${description}</textarea>
            <input type="datetime-local" id="edit-due-date" class="swal2-input" value="${dueDate ? new Date(dueDate).toISOString().slice(0,16) : ''}">
        `,
        showCancelButton: true,
        confirmButtonText: 'Lưu',
        preConfirm: () => {
            return {
                title: document.getElementById('edit-title').value,
                description: document.getElementById('edit-description').value,
                due_date: document.getElementById('edit-due-date').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'edit_card',
                    card_id: cardId,
                    card_title: result.value.title,
                    card_description: result.value.description,
                    due_date: result.value.due_date
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $card.find('.card-content strong').text(response.card.title);
                        $card.find('.card-content p').text(response.card.description);
                        $card.find('.card-content small').text(response.card.due_date ? `Hạn chót: ${new Date(response.card.due_date).toLocaleString('vi-VN')}` : '');
                        showToast('success', 'Đã cập nhật thẻ!');
                    } else {
                        showToast('error', response.message);
                    }
                }
            });
        }
    });
});
        // Xử lý thêm thẻ mới
        // $(document).on('submit', '.add-card-form', function(e) {
        //     e.preventDefault();

        //     const form = $(this);
        //     const listId = form.find('input[name="list_id"]').val();
        //     const cardTitle = form.find('input[name="card_title"]').val();
        //     const cardDescription = form.find('textarea[name="card_description"]').val();
        //     const addCardBtn = form.siblings('.add-card-btn');

        //     addCardBtn.addClass('loading');

        //     $.ajax({
        //         url: 'api.php',
        //         method: 'POST',
        //         data: {
        //             action: 'add_card',
        //             list_id: listId,
        //             card_title: cardTitle,
        //             card_description: cardDescription
        //         },
        //         dataType: 'json',
        //         success: function(response) {
        //             addCardBtn.removeClass('loading');
        //             if (response.success) {
        //                 const newCard = `
        //                     <div class="card added" data-card-id="${response.card.id}">
        //                         <div class="card-inner">
        //                             <input type="checkbox" class="complete-card" data-card-id="${response.card.id}">
        //                             <div class="card-content">
        //                                 <strong>${response.card.title}</strong>
        //                                 <p>${response.card.description}</p>
        //                             </div>
        //                         </div>
        //                         <div class="card-actions">
        //                             <span class="delete-card"><i class="fas fa-times"></i></span>
        //                         </div>
        //                     </div>`;
        //                 form.siblings('.cards-container').append(newCard);
        //                 form.find('input[name="card_title"]').val('');
        //                 form.find('textarea[name="card_description"]').val('');
        //                 form.hide();
        //                 form.siblings('.add-card-btn').show();

        //                 showToast('success', 'Đã thêm thẻ!');
        //             } else {
        //                 showToast('error', response.message);
        //             }
        //         },
        //         error: function() {
        //             addCardBtn.removeClass('loading');
        //             showToast('error', 'Đã có lỗi xảy ra!');
        //         }
        //     });
        // });
        $(document).on('submit', '.add-card-form', function(e) {
    e.preventDefault();

    const form = $(this);
    const listId = form.find('input[name="list_id"]').val();
    const cardTitle = form.find('input[name="card_title"]').val();
    const cardDescription = form.find('textarea[name="card_description"]').val();
    const dueDate = form.find('input[name="due_date"]').val(); // Lấy giá trị hạn chót
    const addCardBtn = form.siblings('.add-card-btn');

    addCardBtn.addClass('loading');

    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'add_card',
            list_id: listId,
            card_title: cardTitle,
            card_description: cardDescription,
            due_date: dueDate // Gửi thêm due_date
        },
        dataType: 'json',
        success: function(response) {
            addCardBtn.removeClass('loading');
            if (response.success) {
                const dueDateHtml = response.card.due_date ? `<small class="text-muted">Hạn chót: ${new Date(response.card.due_date).toLocaleString('vi-VN')}</small>` : '';
                const newCard = `
                    <div class="card added" data-card-id="${response.card.id}">
                        <div class="card-inner">
                            <input type="checkbox" class="complete-card" data-card-id="${response.card.id}">
                            <div class="card-content">
                                <strong>${response.card.title}</strong>
                                <p>${response.card.description}</p>
                                ${dueDateHtml}
                            </div>
                        </div>
                        <div class="card-actions">
                            <span class="delete-card"><i class="fas fa-times"></i></span>
                        </div>
                    </div>`;
                form.siblings('.cards-container').append(newCard);
                form.find('input[name="card_title"]').val('');
                form.find('textarea[name="card_description"]').val('');
                form.find('input[name="due_date"]').val(''); // Reset trường hạn chót
                form.hide();
                form.siblings('.add-card-btn').show();

                showToast('success', 'Đã thêm thẻ!');
            } else {
                showToast('error', response.message);
            }
        },
        error: function() {
            addCardBtn.removeClass('loading');
            showToast('error', 'Đã có lỗi xảy ra!');
        }
    });
});

        // Xử lý sắp xếp thẻ
        $(document).on('click', '.sort-cards', function(e) {
            e.preventDefault();
            const list = $(this).closest('.list');
            const listId = list.data('list-id');
            const sortOrder = $(this).data('sort'); // 'asc' hoặc 'desc'

            // Sắp xếp tại client-side trước để cải thiện trải nghiệm
            const cardsContainer = list.find('.cards-container');
            const cards = cardsContainer.children('.card').get();
            
            cards.sort((a, b) => {
                const titleA = $(a).find('.card-content strong').text().toLowerCase();
                const titleB = $(b).find('.card-content strong').text().toLowerCase();
                return sortOrder === 'asc' ? titleA.localeCompare(titleB) : titleB.localeCompare(titleA);
            });

            // Hiệu ứng mượt mà khi sắp xếp
            cardsContainer.css('transition', 'all 0.3s ease');
            cardsContainer.empty();
            cards.forEach(card => cardsContainer.append(card));
            
            // Gửi yêu cầu cập nhật thứ tự về server
            list.addClass('loading');
            const cardIds = cards.map(card => $(card).data('card-id'));

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'sort_cards',
                    list_id: listId,
                    card_ids: cardIds,
                    sort_order: sortOrder
                },
                dataType: 'json',
                success: function(response) {
                    list.removeClass('loading');
                    if (response.success) {
                        showToast('success', `Đã sắp xếp thẻ ${sortOrder === 'asc' ? 'A-Z' : 'Z-A'}!`);
                    } else {
                        showToast('error', response.message || 'Lỗi khi sắp xếp!');
                    }
                },
                error: function() {
                    list.removeClass('loading');
                    showToast('error', 'Đã có lỗi xảy ra!');
                }
            });
        });

        // Xử lý lưu trữ danh sách
        $(document).on('click', '.archive-list', function(e) {
            e.preventDefault();
            const list = $(this).closest('.list');
            const listId = list.data('list-id');

            Swal.fire({
                title: 'Bạn có chắc chắn muốn lưu trữ danh sách này?',
                text: 'Bạn có thể khôi phục nó sau từ mục "Danh sách đã lưu trữ".',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Lưu trữ',
                cancelButtonText: 'Hủy',
                customClass: {
                    popup: 'animated fadeInDown faster',
                    confirmButton: 'btn btn-warning',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    list.addClass('loading');
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: {
                            action: 'archive_list',
                            list_id: listId
                        },
                        dataType: 'json',
                        success: function(response) {
                            list.removeClass('loading');
                            if (response.success) {
                                list.addClass('removed');
                                list.on('animationend', () => list.remove());
                                showToast('success', 'Đã lưu trữ danh sách!');
                            } else {
                                showToast('error', response.message || 'Lỗi khi lưu trữ!');
                            }
                        },
                        error: function() {
                            list.removeClass('loading');
                            showToast('error', 'Đã có lỗi xảy ra!');
                        }
                    });
                }
            });
        });

        // Xem và khôi phục danh sách đã lưu trữ
        $(document).on('click', '.view-archived-lists', function() {
            const workspaceId = <?php echo $workspace_id; ?>;

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'get_archived_lists',
                    workspace_id: workspaceId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '<div style="max-height: 300px; overflow-y: auto;"><table class="table table-striped">';
                        html += '<thead><tr><th>Tiêu đề</th><th>Ngày lưu trữ</th><th>Hành động</th></tr></thead><tbody>';
                        if (response.lists.length === 0) {
                            html += '<tr><td colspan="3" class="text-center">Chưa có danh sách nào được lưu trữ.</td></tr>';
                        } else {
                            response.lists.forEach(list => {
                                html += `<tr>
                                    <td>${list.title}</td>
                                    <td>${list.archived_at || 'Không có thông tin'}</td>
                                    <td><button class="btn btn-sm btn-success restore-list" data-list-id="${list.id}">Khôi phục</button></td>
                                </tr>`;
                            });
                        }
                        html += '</tbody></table></div>';

                        Swal.fire({
                            title: 'Danh sách đã lưu trữ',
                            html: html,
                            confirmButtonText: 'Đóng',
                            customClass: {
                                popup: 'animated fadeInDown faster',
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false,
                            width: '600px'
                        });
                    } else {
                        showToast('error', 'Lỗi khi lấy danh sách!', response.message, 2000);
                    }
                },
                error: function() {
                    showToast('error', 'Đã có lỗi xảy ra!');
                }
            });
        });

        // Xử lý khôi phục danh sách
        $(document).on('click', '.restore-list', function() {
            const listId = $(this).data('list-id');

            Swal.fire({
                title: 'Khôi phục danh sách này?',
                text: 'Danh sách sẽ được đưa trở lại không gian làm việc.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Khôi phục',
                cancelButtonText: 'Hủy',
                customClass: {
                    popup: 'animated fadeInDown faster',
                    confirmButton: 'btn btn-success',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: {
                            action: 'restore_list',
                            list_id: listId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showToast('success', 'Đã khôi phục danh sách!', 'Vui lòng tải lại trang để xem danh sách.', 2000);
                            } else {
                                showToast('error', 'Lỗi khi khôi phục!', response.message, 2000);
                            }
                        },
                        error: function() {
                            showToast('error', 'Đã có lỗi xảy ra!');
                        }
                    });
                }
            });
        });

        // Xử lý bộ lọc
        $(document).on('click', '.filter-option', function(e) {
            e.preventDefault();
            const filterType = $(this).data('filter');
            console.log('Bộ lọc được chọn:', filterType);

            if (filterType === 'custom') {
                $('#customFilterModal').modal('show');
                return;
            }

            applyFilter(filterType);
        });

        $(document).on('click', '.apply-custom-filter', function() {
            const startDate = $('#startDate').val();
            const endDate = $('#endDate').val();

            if (!startDate || !endDate) {
                showToast('error', 'Vui lòng chọn đầy đủ ngày bắt đầu và ngày kết thúc!');
                return;
            }

            if (new Date(startDate) > new Date(endDate)) {
                showToast('error', 'Ngày bắt đầu không thể lớn hơn ngày kết thúc!');
                return;
            }

            $('#customFilterModal').modal('hide');
            applyFilter('custom', startDate, endDate);
        });

        $(document).on('click', '.clear-filter', function() {
            localStorage.removeItem('filterType');
            localStorage.removeItem('startDate');
            localStorage.removeItem('endDate');
            $('#filterDropdown').html('<i class="fas fa-filter"></i> Bộ lọc');
            $('.clear-filter').hide();
            applyFilter('all');
        });

        function applyFilter(filterType, startDate = null, endDate = null) {
            console.log('Áp dụng bộ lọc:', { filterType, startDate, endDate });

            // Lưu bộ lọc vào localStorage
            localStorage.setItem('filterType', filterType);
            if (filterType === 'custom') {
                localStorage.setItem('startDate', startDate);
                localStorage.setItem('endDate', endDate);
            } else {
                localStorage.removeItem('startDate');
                localStorage.removeItem('endDate');
            }

            // Cập nhật tiêu đề dropdown
            const filterLabels = {
                all: 'Tất cả',
                today: 'Hôm nay',
                last7days: '7 ngày qua',
                last30days: '30 ngày qua',
                custom: `Tùy chỉnh (${startDate} - ${endDate})`
            };
            $('#filterDropdown').html(`<i class="fas fa-filter"></i> Bộ lọc: ${filterLabels[filterType]}`);
            $('.clear-filter').show();

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'filter_lists_and_cards',
                    workspace_id: <?php echo $workspace_id; ?>,
                    filter_type: filterType,
                    start_date: startDate,
                    end_date: endDate
                },
                dataType: 'json',
                success(response) {
                    console.log('Phản hồi từ API:', response);
                    if (response.success) {
                        // Cập nhật giao diện với danh sách đã lọc
                        const $listsContainer = $('#lists-container');
                        $listsContainer.empty();

                        if (response.lists.length === 0) {
                            $listsContainer.html('<p class="text-muted">Không có danh sách nào phù hợp với bộ lọc.</p>');
                        } else {
                            response.lists.forEach(list => {
                                const $list = $(`
                                    <div class="list" data-list-id="${list.id}">
                                        <div class="list-header">
                                            <h3>${list.title}</h3>
                                            <div class="dropdown">
                                                <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item sort-cards" href="#" data-sort="asc">Sắp xếp A-Z</a></li>
                                                    <li><a class="dropdown-item sort-cards" href="#" data-sort="desc">Sắp xếp Z-A</a></li>
                                                    <li><a class="dropdown-item archive-list" href="#">Lưu trữ danh sách này</a></li>
                                                    <li><a class="dropdown-item delete-list" href="#">Xóa danh sách này</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="cards-container">
                                            ${list.cards.map(card => `
                                                <div class="card ${card.completed ? 'completed' : ''}" data-card-id="${card.id}">
                                                    <div class="card-inner">
                                                        <input type="checkbox" class="complete-card" data-card-id="${card.id}" ${card.completed ? 'checked' : ''}>
                                                        <div class="card-content">
                                                            <strong>${card.title}</strong>
                                                            <p>${card.description}</p>
                                                        </div>
                                                    </div>
                                                    <div class="card-actions">
                                                        <span class="delete-card"><i class="fas fa-times"></i></span>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                        <div class="add-card">
                                            <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
                                            <form class="add-card-form">
                                                <input type="hidden" name="list_id" value="${list.id}">
                                                <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                                                <textarea name="card_description" placeholder="Mô tả..."></textarea>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-primary">Thêm</button>
                                                    <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                `);
                                $listsContainer.append($list);

                                // Thêm Sortable cho danh sách mới
                                new Sortable($list.find('.cards-container')[0], {
                                    group: 'shared',
                                    animation: 150,
                                    onEnd: debounce(function(evt) {
                                        const card = evt.item;
                                        const cardId = card.dataset.cardId;
                                        const newList = evt.to.closest('.list');
                                        const newListId = newList.dataset.listId;

                                        newList.classList.add('loading');

                                        $.ajax({
                                            url: 'api.php',
                                            method: 'POST',
                                            data: {
                                                action: 'update_card_list',
                                                card_id: cardId,
                                                new_list_id: newListId
                                            },
                                            dataType: 'json',
                                            success: function(response) {
                                                newList.classList.remove('loading');
                                                if (response.success) {
                                                    showToast('success', 'Đã di chuyển thẻ!');
                                                } else {
                                                    showToast('error', 'Lỗi khi di chuyển thẻ!', response.message || 'Không có thông tin lỗi');
                                                }
                                            },
                                            error: function() {
                                                newList.classList.remove('loading');
                                                showToast('error', 'Đã có lỗi xảy ra!');
                                            }
                                        });
                                    }, 300)
                                });
                            });
                        }

                        // Thêm lại nút "Tạo bảng mới"
                        const addListHtml = `
                            <div class="list">
                                <div class="add-list">
                                    <button class="add-list-btn"><i class="fas fa-plus"></i> Tạo bảng mới</button>
                                    <form class="add-list-form">
                                        <input type="text" name="list_title" placeholder="Tên bảng..." required>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">Thêm</button>
                                            <button type="button" class="btn btn-cancel cancel-list">Hủy</button>
                                        </div>
                                    </form>
                                </div>
                            </div>`;
                        $listsContainer.append(addListHtml);

                        showToast('success', 'Đã áp dụng bộ lọc!');
                    } else {
                        showToast('error', 'Lỗi khi áp dụng bộ lọc!', response.message || 'Không có thông tin lỗi', 3000);
                    }
                },
                error(xhr, status, error) {
                    console.error('Lỗi khi gửi yêu cầu:', error);
                    showToast('error', 'Đã có lỗi xảy ra!', `Chi tiết lỗi: ${xhr.status} - ${error}`, 3000);
                }
            });
        }

        // Áp dụng bộ lọc đã lưu khi tải trang
        $(document).ready(function() {
            const savedFilterType = localStorage.getItem('filterType') || 'all';
            const savedStartDate = localStorage.getItem('startDate');
            const savedEndDate = localStorage.getItem('endDate');
            if (savedFilterType !== 'all') {
                applyFilter(savedFilterType, savedStartDate, savedEndDate);
            }
        });

        // thanh tìm kiếm   
        $('#searchInput').on('input', debounce(function() {
    const query = $(this).val().trim();
    const $clearSearchBtn = $('#clearSearch');

    // Hiển thị nút xóa tìm kiếm nếu có nội dung
    if (query.length > 0) {
        $clearSearchBtn.show();
    } else {
        $clearSearchBtn.hide();
    }

    // Yêu cầu ít nhất 2 ký tự để tìm kiếm, nếu không thì tải lại danh sách ban đầu
    if (query.length < 2 && query.length !== 0) return;

    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'search_cards',
            workspace_id: <?php echo $workspace_id; ?>,
            query: query
        },
        dataType: 'json',
        success: function(response) {
            const $listsContainer = $('#lists-container');
            $listsContainer.empty();

            if (!response.success) {
                showToast('error', 'Lỗi khi tìm kiếm!', response.message);
                return;
            }

            if (response.lists.length === 0 && query.length > 0) {
                $listsContainer.html('<p class="text-muted text-center">Không tìm thấy thẻ nào.</p>');
            } else {
                response.lists.forEach(list => {
                    const $list = $(`
                        <div class="list" data-list-id="${list.id}">
                            <div class="list-header">
                                <h3>${list.title}</h3>
                                <div class="dropdown">
                                    <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item sort-cards" href="#" data-sort="asc">Sắp xếp A-Z</a></li>
                                        <li><a class="dropdown-item sort-cards" href="#" data-sort="desc">Sắp xếp Z-A</a></li>
                                        <li><a class="dropdown-item archive-list" href="#">Lưu trữ danh sách này</a></li>
                                        <li><a class="dropdown-item delete-list" href="#">Xóa danh sách này</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="cards-container">
                                ${list.cards.map(card => `
                                    <div class="card ${card.completed ? 'completed' : ''} ${card.due_date && new Date(card.due_date) < new Date() && !card.completed ? 'overdue' : ''}" data-card-id="${card.id}">
                                        <div class="card-inner">
                                            <input type="checkbox" class="complete-card" data-card-id="${card.id}" ${card.completed ? 'checked' : ''}>
                                            <div class="card-content">
                                                <strong>${card.title}</strong>
                                                <p>${card.description}</p>
                                                ${card.due_date ? `<small class="text-muted">Hạn chót: ${new Date(card.due_date).toLocaleString('vi-VN')}</small>` : ''}
                                            </div>
                                        </div>
                                        <div class="card-actions">
                                            <span class="delete-card"><i class="fas fa-times"></i></span>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                            <div class="add-card">
                                <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
                                <form class="add-card-form">
                                    <input type="hidden" name="list_id" value="${list.id}">
                                    <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                                    <textarea name="card_description" placeholder="Mô tả..."></textarea>
                                    <input type="datetime-local" name="due_date" placeholder="Hạn chót...">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Thêm</button>
                                        <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `);
                    $listsContainer.append($list);
                });
            }

            // Luôn hiển thị nút "Tạo bảng mới"
            $listsContainer.append(`
                <div class="list">
                    <div class="add-list">
                        <button class="add-list-btn"><i class="fas fa-plus"></i> Tạo bảng mới</button>
                        <form class="add-list-form">
                            <input type="text" name="list_title" placeholder="Tên bảng..." required>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Thêm</button>
                                <button type="button" class="btn btn-cancel cancel-list">Hủy</button>
                            </div>
                        </form>
                    </div>
                </div>
            `);

            // Khởi tạo lại SortableJS cho các danh sách mới
            initializeSortable();
        },
        error: function() {
            showToast('error', 'Đã có lỗi xảy ra khi tìm kiếm!');
        }
    });
}, 300));

// Xử lý thời gian gần hết hạn
$('#clearSearch').on('click', function() {
    $('#searchInput').val('');
    $(this).hide();

    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'filter_lists_and_cards',
            workspace_id: <?php echo $workspace_id; ?>,
            filter_type: 'all'
        },
        dataType: 'json',
        success: function(response) {
            const $listsContainer = $('#lists-container');
            $listsContainer.empty();

            if (response.success) {
                response.lists.forEach(list => {
                    const $list = $(`
                        <div class="list" data-list-id="${list.id}">
                            <div class="list-header">
                                <h3>${list.title}</h3>
                                <div class="dropdown">
                                    <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item sort-cards" href="#" data-sort="asc">Sắp xếp A-Z</a></li>
                                        <li><a class="dropdown-item sort-cards" href="#" data-sort="desc">Sắp xếp Z-A</a></li>
                                        <li><a class="dropdown-item archive-list" href="#">Lưu trữ danh sách này</a></li>
                                        <li><a class="dropdown-item delete-list" href="#">Xóa danh sách này</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="cards-container">
                                ${list.cards.map(card => {
                                    const now = new Date();
                                    const dueDate = card.due_date ? new Date(card.due_date.replace(' ', 'T')) : null; // Đảm bảo định dạng ISO
                                    const isCompleted = card.completed;
                                    const isOverdue = dueDate && dueDate < now && !isCompleted;
                                    const timeDiff = dueDate ? dueDate - now : null;
                                    const hoursUntilDue = timeDiff ? timeDiff / (1000 * 60 * 60) : null;
                                    const isNearingDue = dueDate && !isCompleted && !isOverdue && hoursUntilDue !== null && hoursUntilDue <= 24 && hoursUntilDue >= 0;

                                    // Debug: In ra thông tin để kiểm tra
                                    console.log(`Card: ${card.title}, Due Date: ${card.due_date}, Hours Until Due: ${hoursUntilDue}, Is Nearing Due: ${isNearingDue}`);

                                    return `
                                        <div class="card ${isCompleted ? 'completed' : ''} ${isOverdue ? 'overdue' : ''} ${isNearingDue ? 'nearing-due' : ''}" data-card-id="${card.id}">
                                            <div class="card-inner">
                                                <input type="checkbox" class="complete-card" data-card-id="${card.id}" ${card.completed ? 'checked' : ''}>
                                                <div class="card-content">
                                                    <strong>${card.title}</strong>
                                                    <p>${card.description}</p>
                                                    ${card.due_date ? `<small class="text-muted">Hạn chót: ${new Date(card.due_date).toLocaleString('vi-VN')}</small>` : ''}
                                                </div>
                                            </div>
                                            <div class="card-actions">
                                                <span class="delete-card"><i class="fas fa-times"></i></span>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                            <div class="add-card">
                                <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
                                <form class="add-card-form">
                                    <input type="hidden" name="list_id" value="${list.id}">
                                    <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                                    <textarea name="card_description" placeholder="Mô tả..."></textarea>
                                    <input type="datetime-local" name="due_date" placeholder="Hạn chót...">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Thêm</button>
                                        <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `);
                    $listsContainer.append($list);
                });

                $listsContainer.append(`
                    <div class="list">
                        <div class="add-list">
                            <button class="add-list-btn"><i class="fas fa-plus"></i> Tạo bảng mới</button>
                            <form class="add-list-form">
                                <input type="text" name="list_title" placeholder="Tên bảng..." required>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Thêm</button>
                                    <button type="button" class="btn btn-cancel cancel-list">Hủy</button>
                                </div>
                            </form>
                        </div>
                    </div>
                `);
            }

            initializeSortable();
        }
    });
});
// $('#searchInput').on('input', function() {
//     const searchTerm = $(this).val().trim();

//     if (searchTerm.length > 0) {
//         $('#clearSearch').show();
//     } else {
//         $('#clearSearch').hide();
//         $('#clearSearch').trigger('click');
//         return;
//     }

//     $.ajax({
//         url: 'api.php',
//         method: 'POST',
//         data: {
//             action: 'search_cards',
//             workspace_id: <?php echo $workspace_id; ?>,
//             query: searchTerm
//         },
//         dataType: 'json',
//         success: function(response) {
//             const $listsContainer = $('#lists-container');
//             $listsContainer.empty();

//             if (response.success && response.lists.length > 0) {
//                 response.lists.forEach(list => {
//                     const $list = $(`
//                         <div class="list" data-list-id="${list.id}">
//                             <div class="list-header">
//                                 <h3>${list.title}</h3>
//                                 <div class="dropdown">
//                                     <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
//                                     <ul class="dropdown-menu">
//                                         <li><a class="dropdown-item sort-cards" href="#" data-sort="asc">Sắp xếp A-Z</a></li>
//                                         <li><a class="dropdown-item sort-cards" href="#" data-sort="desc">Sắp xếp Z-A</a></li>
//                                         <li><a class="dropdown-item archive-list" href="#">Lưu trữ danh sách này</a></li>
//                                         <li><a class="dropdown-item delete-list" href="#">Xóa danh sách này</a></li>
//                                     </ul>
//                                 </div>
//                             </div>
//                             <div class="cards-container">
//                                 ${list.cards.map(card => {
//                                     const now = new Date();
//                                     const dueDate = card.due_date ? new Date(card.due_date) : null;
//                                     const isCompleted = card.completed;
//                                     const isOverdue = dueDate && dueDate < now && !isCompleted;
//                                     const timeDiff = dueDate ? dueDate - now : null;
//                                     const hoursUntilDue = timeDiff ? timeDiff / (1000 * 60 * 60) : null;
//                                     const isNearingDue = dueDate && !isCompleted && !isOverdue && hoursUntilDue !== null && hoursUntilDue <= 24 && hoursUntilDue >= 0;

//                                     return `
//                                         <div class="card ${isCompleted ? 'completed' : ''} ${isOverdue ? 'overdue' : ''} ${isNearingDue ? 'nearing-due' : ''}" data-card-id="${card.id}">
//                                             <div class="card-inner">
//                                                 <input type="checkbox" class="complete-card" data-card-id="${card.id}" ${card.completed ? 'checked' : ''}>
//                                                 <div class="card-content">
//                                                     <strong>${card.title}</strong>
//                                                     <p>${card.description}</p>
//                                                     ${card.due_date ? `<small class="text-muted">Hạn chót: ${new Date(card.due_date).toLocaleString('vi-VN')}</small>` : ''}
//                                                 </div>
//                                             </div>
//                                             <div class="card-actions">
//                                                 <span class="delete-card"><i class="fas fa-times"></i></span>
//                                             </div>
//                                         </div>
//                                     `;
//                                 }).join('')}
//                             </div>
//                             <div class="add-card">
//                                 <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
//                                 <form class="add-card-form">
//                                     <input type="hidden" name="list_id" value="${list.id}">
//                                     <input type="text" name="card_title" placeholder="Tên thẻ..." required>
//                                     <textarea name="card_description" placeholder="Mô tả..."></textarea>
//                                     <input type="datetime-local" name="due_date" placeholder="Hạn chót...">
//                                     <div class="d-flex gap-2">
//                                         <button type="submit" class="btn btn-primary">Thêm</button>
//                                         <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
//                                     </div>
//                                 </form>
//                             </div>
//                         </div>
//                     `);
//                     $listsContainer.append($list);
//                 });

//                 $listsContainer.append(`
//                     <div class="list">
//                         <div class="add-list">
//                             <button class="add-list-btn"><i class="fas fa-plus"></i> Tạo bảng mới</button>
//                             <form class="add-list-form">
//                                 <input type="text" name="list_title" placeholder="Tên bảng..." required>
//                                 <div class="d-flex gap-2">
//                                     <button type="submit" class="btn btn-primary">Thêm</button>
//                                     <button type="button" class="btn btn-cancel cancel-list">Hủy</button>
//                                 </div>
//                             </form>
//                         </div>
//                     </div>
//                 `);
//             } else {
//                 $listsContainer.append('<p>Không tìm thấy kết quả nào.</p>');
//             }

//             initializeSortable();
//         },
//         error: function() {
//             console.log('Lỗi khi tìm kiếm.');
//         }
//     });
// });

// xóa thông báo
$(document).on('click', '.delete-notification', function() {
    const $item = $(this).closest('.notification-item');
    const notificationId = $item.data('notification-id');
    const workspaceId = <?php echo $workspace_id; ?>;

    Swal.fire({
        title: 'Xóa thông báo này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'delete_notification',
                    notification_id: notificationId,
                    workspace_id: workspaceId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $item.remove();
                        fetchNotifications(1); // Cập nhật lại danh sách
                        showToast('success', response.message || 'Đã xóa thông báo!');
                    } else {
                        showToast('error', 'Lỗi khi xóa thông báo!', response.message || 'Không có thông tin lỗi');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('error', 'Lỗi khi xóa thông báo!', 'Chi tiết lỗi: ' + error, 3000);
                }
            });
        }
    });
});

// Xem người được chia sẻ
$(document).on('click', '.view-workspace-collaborators', function() {
    $('#viewCollaboratorsModal').modal('show');

    // Gọi API để lấy danh sách người được chia sẻ
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'get_workspace_collaborators',
            workspace_id: <?php echo $workspace_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            const $collaboratorsList = $('#collaborators-list');
            $collaboratorsList.empty();

            if (response.success && response.collaborators.length > 0) {
                response.collaborators.forEach(collaborator => {
                    const isOwner = collaborator.access_level === 'owner';
                    const actionButton = isOwner
                        ? '<td>-</td>' // Không hiển thị nút hủy cho chủ sở hữu
                        : `<td><button class="btn btn-sm btn-danger remove-collaborator" data-user-id="${collaborator.user_id}">Hủy chia sẻ</button></td>`;

                    $collaboratorsList.append(`
                        <tr>
                            <td>${collaborator.email}</td>
                            <td>${isOwner ? 'Chủ sở hữu' : (collaborator.access_level === 'edit' ? 'Chỉnh sửa' : 'Xem')}</td>
                            <td>${collaborator.created_at ? new Date(collaborator.created_at).toLocaleString('vi-VN') : '-'}</td>
                            ${actionButton}
                        </tr>
                    `);
                });
            } else {
                $collaboratorsList.append('<tr><td colspan="4">Không có người được chia sẻ.</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            console.log('Lỗi khi lấy danh sách người được chia sẻ:', error);
            $('#collaborators-list').html('<tr><td colspan="4">Lỗi khi tải dữ liệu.</td></tr>');
        }
    });
});

// Xử lý hủy chia sẻ
$(document).on('click', '.remove-collaborator', function() {
    const userId = $(this).data('user-id');

    Swal.fire({
        title: 'Bạn có chắc chắn muốn hủy chia sẻ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Hủy chia sẻ',
        cancelButtonText: 'Hủy',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'remove_collaborator',
                    workspace_id: <?php echo $workspace_id; ?>,
                    user_id: userId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Thành công!', response.message, 'success');
                        // Tải lại danh sách người được chia sẻ
                        $('.view-workspace-collaborators').trigger('click');
                    } else {
                        Swal.fire('Lỗi!', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Lỗi!', 'Lỗi khi hủy chia sẻ.', 'error');
                }
            });
        }
    });
});

// Xử lý form chia sẻ
$('#shareWorkspaceForm').on('submit', function(e) {
    e.preventDefault();

    const userEmail = $('#userEmail').val();
    const accessLevel = $('#accessLevel').val();

    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'share_workspace',
            workspace_id: <?php echo $workspace_id; ?>,
            user_email: userEmail,
            access_level: accessLevel
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire('Thành công!', response.message, 'success');
                // Tải lại danh sách người được chia sẻ
                $('.view-workspace-collaborators').trigger('click');
                // Reset form
                $('#shareWorkspaceForm')[0].reset();
            } else {
                Swal.fire('Lỗi!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Lỗi!', 'Lỗi khi gửi yêu cầu chia sẻ.', 'error');
        }
    });
});
function loadListsAndCards() {
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'filter_lists_and_cards',
            workspace_id: <?php echo $workspace_id; ?>,
            filter_type: 'all'
        },
        dataType: 'json',
        success: function(response) {
            const $listsContainer = $('#lists-container');
            $listsContainer.empty();

            if (response.success) {
                response.lists.forEach(list => {
                    const $list = $(`
                        <div class="list" data-list-id="${list.id}">
                            <div class="list-header">
                                <h3>${list.title}</h3>
                                <div class="dropdown">
                                    <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item sort-cards" href="#" data-sort="asc">Sắp xếp A-Z</a></li>
                                        <li><a class="dropdown-item sort-cards" href="#" data-sort="desc">Sắp xếp Z-A</a></li>
                                        <li><a class="dropdown-item archive-list" href="#">Lưu trữ danh sách này</a></li>
                                        <li><a class="dropdown-item delete-list" href="#">Xóa danh sách này</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="cards-container">
                                ${list.cards.map(card => {
                                    const now = new Date();
                                    const dueDate = card.due_date ? new Date(card.due_date) : null;
                                    const isCompleted = card.completed;
                                    const isOverdue = dueDate && dueDate < now && !isCompleted;
                                    const timeDiff = dueDate ? dueDate - now : null;
                                    const hoursUntilDue = timeDiff ? timeDiff / (1000 * 60 * 60) : null;
                                    const isNearingDue = dueDate && !isCompleted && !isOverdue && hoursUntilDue !== null && hoursUntilDue <= 24 && hoursUntilDue >= 0;

                                    // Debug: In ra thông tin để kiểm tra
                                    console.log(`Card: ${card.title}, Due Date: ${card.due_date}, Now: ${now}, Hours Until Due: ${hoursUntilDue}, Is Nearing Due: ${isNearingDue}`);

                                    return `
                                        <div class="card ${isCompleted ? 'completed' : ''} ${isOverdue ? 'overdue' : ''} ${isNearingDue ? 'nearing-due' : ''}" data-card-id="${card.id}">
                                            <div class="card-inner">
                                                <input type="checkbox" class="complete-card" data-card-id="${card.id}" ${card.completed ? 'checked' : ''}>
                                                <div class="card-content">
                                                    <strong>${card.title}</strong>
                                                    <p>${card.description}</p>
                                                    ${card.due_date ? `<small class="text-muted">Hạn chót: ${new Date(card.due_date).toLocaleString('vi-VN')}</small>` : ''}
                                                </div>
                                            </div>
                                            <div class="card-actions">
                                                <span class="edit-card"><i class="fas fa-edit"></i></span>
                                                <span class="delete-card"><i class="fas fa-times"></i></span>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                            <div class="add-card">
                                <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
                                <form class="add-card-form">
                                    <input type="hidden" name="list_id" value="${list.id}">
                                    <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                                    <textarea name="card_description" placeholder="Mô tả..."></textarea>
                                    <input type="datetime-local" name="due_date" placeholder="Hạn chót...">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Thêm</button>
                                        <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `);
                    $listsContainer.append($list);
                });

                $listsContainer.append(`
                    <div class="list">
                        <div class="add-list">
                            <button class="add-list-btn"><i class="fas fa-plus"></i> Tạo bảng mới</button>
                            <form class="add-list-form">
                                <input type="text" name="list_title" placeholder="Tên bảng..." required>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Thêm</button>
                                    <button type="button" class="btn btn-cancel cancel-list">Hủy</button>
                                </div>
                            </form>
                        </div>
                    </div>
                `);
            }

            initializeSortable();
        },
        error: function() {
            console.log('Lỗi khi tải danh sách.');
        }
    });
}

// Hàm kiểm tra và gửi thông báo qua Gmail cho thẻ gần hết hạn
function checkAndNotifyNearingDueCards() {
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'get_nearing_due_cards',
            workspace_id: <?php echo $workspace_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.cards.length > 0) {
                response.cards.forEach(card => {
                    sendGmailNotification(card);
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Lỗi khi lấy thẻ gần hết hạn:', error);
        }
    });
}

// Hàm gửi thông báo qua Gmail
// Hàm gửi thông báo qua Gmail
function sendGmailNotification(card) {
    const now = new Date();
    const dueDate = new Date(card.due_date);
    const timeDiff = dueDate - now;
    const hoursUntilDue = timeDiff / (1000 * 60 * 60);

    if (hoursUntilDue <= 24 && hoursUntilDue >= 0 && !card.completed) {
        $.ajax({
            url: 'api.php',
            method: 'POST',
            data: {
                action: 'send_gmail_notification',
                card_id: card.id,
                card_title: card.title,
                due_date: card.due_date,
                workspace_id: <?php echo $workspace_id; ?>
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('success', `Đã gửi thông báo qua Gmail cho thẻ "${card.title}"!`);
                } else {
                    showToast('error', 'Lỗi khi gửi thông báo!', response.message);
                }
            },
            error: function(xhr, status, error) {
                showToast('error', 'Lỗi khi gửi thông báo!', `Chi tiết: ${error}`);
            }
        });
    }
}
// Tự động kiểm tra mỗi 1 giờ (3600000ms)
setInterval(checkAndNotifyNearingDueCards, 3600000);

// Gọi lần đầu khi tải trang
$(document).ready(function() {
    checkAndNotifyNearingDueCards();
});
    </script>
</body>
</html>