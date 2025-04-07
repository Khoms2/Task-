<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// Kiểm tra đăng nhập
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
    die("Lỗi kết nối: " . $e->getMessage());
}

// Lấy workspace
$workspace_id = 1;
$stmt = $pdo->prepare("SELECT * FROM workspaces WHERE id = ?");
$stmt->execute([$workspace_id]);
$workspace = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$workspace) {
    die("Không tìm thấy không gian làm việc.");
}

// Lấy danh sách
$stmt = $pdo->prepare("SELECT * FROM lists WHERE workspace_id = ? AND archived = 0");
$stmt->execute([$workspace_id]);
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tác vụ - Task Manager</title>
    <link rel="icon" href="./img/logo2.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
        .card-actions .delete-card, .card-actions .edit-card {
            color: #5e6c84;
            font-size: 12px;
            padding: 4px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 4px;
        }
        .card-actions .delete-card:hover, .card-actions .edit-card:hover {
            color: #172b4d;
            background-color: #091e4214;
        }
        .card.nearing-due {
            background-color: #ffe5b4;
            border-left: 4px solid #ff9500;
        }
        .card.overdue {
            border-left: 4px solid #dc3545;
            background-color: #fff3f3;
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
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 7px;
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
        #searchInput {
            width: 300px;
            border-radius: 20px;
            padding: 5px 10px 5px 30px;
            font-size: 14px;
        }
        #searchInput:focus {
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        #clearSearch {
            position: absolute;
            right: 35px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            cursor: pointer;
            color: #5e6c84;
        }
        #clearSearch:hover {
            color: #172b4d;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(10px); }
        }
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
        .animated { animation-duration: 0.5s; }
        .fadeInDown { animation-name: fadeInDown; }
        .fadeInRight { animation-name: fadeInRight; }
        .faster { animation-duration: 0.3s; }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translate3d(0, -20px, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }
        @keyframes fadeInRight {
            from { opacity: 0; transform: translate3d(20px, 0, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-briefcase me-2"></i> <?php echo htmlspecialchars($workspace['name']); ?></h1>
        <div>
            <span class="text-light me-3">Xin chào, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <button class="btn btn-light btn-sm logout-btn"><i class="fas fa-sign-out-alt me-1"></i> Đăng xuất</button>
        </div>
    </div>

    <div class="navbar">
        <div class="navbar-left">
            <button class="btn btn-sm btn-outline-primary share-workspace"><i class="fas fa-share-alt"></i> Chia sẻ</button>
            <button class="btn btn-sm btn-outline-info view-workspace-collaborators"><i class="fas fa-users"></i> Xem người được chia sẻ</button>
            <div style="position: relative; width: 300px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm thẻ...">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: gray;"></i>
                <i class="fas fa-times" id="clearSearch" title="Xóa tìm kiếm"></i>
            </div>
        </div>
        <div class="navbar-right">
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
            <button class="btn btn-sm btn-outline-danger clear-filter me-2" style="display: none;"><i class="fas fa-times"></i> Xóa bộ lọc</button>
        </div>
    </div>

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

    <div class="modal fade" id="viewCollaboratorsModal" tabindex="-1" aria-labelledby="viewCollaboratorsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewCollaboratorsModalLabel">Danh sách người được chia sẻ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
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

    <div class="board" id="lists-container">
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
                <div class="cards-container">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM cards WHERE list_id = ?");
                    $stmt->execute([$list['id']]);
                    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cards as $card):
                        if (!isset($card['id']) || !is_numeric($card['id']) || $card['id'] <= 0) {
                            error_log("Invalid card ID: " . json_encode($card));
                            continue;
                        }
                        $completed = $card['completed'] ?? 0;
                        $isOverdue = $card['due_date'] && strtotime($card['due_date']) < time() && !$completed;
                        $timeDiff = $card['due_date'] ? strtotime($card['due_date']) - time() : null;
                        $hoursUntilDue = $timeDiff ? $timeDiff / 3600 : null;
                        $isNearingDue = $card['due_date'] && !$completed && !$isOverdue && $hoursUntilDue <= 24 && $hoursUntilDue >= 0;
                    ?>
                        <div class="card <?php echo $completed ? 'completed' : ''; ?> <?php echo $isOverdue ? 'overdue' : ''; ?> <?php echo $isNearingDue ? 'nearing-due' : ''; ?>" data-card-id="<?php echo htmlspecialchars($card['id']); ?>">
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
                                <span class="edit-card"><i class="fas fa-edit"></i></span>
                                <span class="delete-card"><i class="fas fa-times"></i></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="add-card">
                    <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/vi.min.js"></script>
    <script>
        // Hàm debounce
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        // Hàm hiển thị toast
        function showToast(icon, title, text = '', timer = 1500) {
            Swal.fire({
                icon,
                title,
                text,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer,
                customClass: { popup: 'animated fadeInRight faster' }
            });
        }

        // Khởi tạo Sortable
        function initializeSortable() {
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
                            data: { action: 'update_card_list', card_id: cardId, new_list_id: newListId },
                            dataType: 'json',
                            success: response => {
                                newList.classList.remove('loading');
                                response.success ? showToast('success', 'Đã di chuyển thẻ!') : showToast('error', 'Lỗi khi di chuyển thẻ!', response.message);
                            },
                            error: () => {
                                newList.classList.remove('loading');
                                showToast('error', 'Đã có lỗi xảy ra!');
                            }
                        });
                    }, 300)
                });
            });
        }

        // Đánh dấu thẻ hoàn thành
        $(document).on('change', '.complete-card', debounce(function() {
            const $checkbox = $(this);
            const $card = $checkbox.closest('.card');
            const cardId = $card.data('card-id');
            const completed = $checkbox.is(':checked') ? 1 : 0;
            const $list = $card.closest('.list');

            $list.addClass('loading');
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'toggle_card_completion', card_id: cardId, completed: completed },
                dataType: 'json',
                success: response => {
                    $list.removeClass('loading');
                    if (response.success) {
                        $checkbox.prop('checked', response.completed === 1);
                        response.completed === 1 ? $card.addClass('completed') : $card.removeClass('completed');
                        showToast(response.completed === 1 ? 'success' : 'info', response.completed === 1 ? 'Đã đánh dấu hoàn thành!' : 'Đã bỏ đánh dấu hoàn thành!');
                        loadListsAndCards();
                    } else {
                        showToast('error', 'Lỗi khi cập nhật trạng thái!', response.message, 3000);
                        $checkbox.prop('checked', !completed);
                    }
                },
                error: () => {
                    $list.removeClass('loading');
                    showToast('error', 'Đã có lỗi xảy ra!');
                    $checkbox.prop('checked', !completed);
                }
            });
        }, 300));

        // Xóa thẻ
        $(document).on('click', '.delete-card', function() {
            const $card = $(this).closest('.card');
            const cardId = $card.data('card-id');
            const $list = $card.closest('.list');

            Swal.fire({
                title: 'Xóa thẻ này?',
                text: 'Thẻ sẽ bị xóa vĩnh viễn.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            }).then(result => {
                if (result.isConfirmed) {
                    $list.addClass('loading');
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: { action: 'delete_card', card_id: cardId },
                        dataType: 'json',
                        success: response => {
                            $list.removeClass('loading');
                            if (response.success) {
                                $card.addClass('removed');
                                setTimeout(() => $card.remove(), 300);
                                showToast('success', 'Đã xóa thẻ!');
                            } else {
                                showToast('error', 'Lỗi khi xóa thẻ!', response.message);
                            }
                        },
                        error: () => {
                            $list.removeClass('loading');
                            showToast('error', 'Đã có lỗi xảy ra!');
                        }
                    });
                }
            });
        });

        // Xóa danh sách
        $(document).on('click', '.delete-list', function(e) {
            e.preventDefault();
            const $list = $(this).closest('.list');
            const listId = $list.data('list-id');

            Swal.fire({
                title: 'Xóa danh sách này?',
                text: 'Tất cả thẻ trong danh sách sẽ bị xóa.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            }).then(result => {
                if (result.isConfirmed) {
                    $list.addClass('loading');
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: { action: 'delete_list', list_id: listId },
                        dataType: 'json',
                        success: response => {
                            $list.removeClass('loading');
                            if (response.success) {
                                $list.addClass('removed');
                                setTimeout(() => $list.remove(), 300);
                                showToast('success', 'Đã xóa danh sách!');
                            } else {
                                showToast('error', 'Lỗi khi xóa danh sách!', response.message);
                            }
                        },
                        error: () => {
                            $list.removeClass('loading');
                            showToast('error', 'Đã có lỗi xảy ra!');
                        }
                    });
                }
            });
        });

        // Chia sẻ workspace
        $(document).on('click', '.share-workspace', function() {
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
                preConfirm: () => {
                    const email = Swal.getPopup().querySelector('#collaborator-email').value;
                    const accessLevel = Swal.getPopup().querySelector('#access-level').value;
                    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        Swal.showValidationMessage('Vui lòng nhập email hợp lệ');
                        return false;
                    }
                    return { email, accessLevel };
                }
            }).then(result => {
                if (result.isConfirmed) {
                    const { email, accessLevel } = result.value;
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: { action: 'share_workspace', workspace_id: <?php echo $workspace_id; ?>, user_email: email, access_level: accessLevel },
                        dataType: 'json',
                        success: response => {
                            response.success ? showToast('success', 'Đã gửi lời mời!', `Đã mời ${email}.`) : showToast('error', 'Lỗi khi gửi lời mời!', response.message);
                        },
                        error: () => showToast('error', 'Đã có lỗi xảy ra!')
                    });
                }
            });
        });

        // Lấy thông báo
        function fetchNotifications(page = 1) {
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'get_notifications', workspace_id: <?php echo $workspace_id; ?>, page: page },
                dataType: 'json',
                success: response => {
                    if (response.success) {
                        const $notificationList = $('#notification-list');
                        const $unreadCount = $('#unread-count');
                        const unreadCount = response.unread_count;

                        if (unreadCount > 0) $unreadCount.text(unreadCount).show();
                        else $unreadCount.hide();

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

                                $notificationList.append(`
                                    <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-notification-id="${notification.id}">
                                        <i class="${iconClass}"></i>
                                        <div class="content">
                                            <div>${notification.message}</div>
                                            <div class="time">${timeAgo}</div>
                                        </div>
                                    </div>
                                `);
                            });
                        }
                    } else {
                        showToast('error', 'Lỗi khi lấy thông báo!', response.message);
                    }
                },
                error: () => showToast('error', 'Đã có lỗi xảy ra!')
            });
        }

        // Đánh dấu tất cả thông báo đã đọc
        $(document).on('click', '.mark-all-read', function() {
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'mark_all_notifications_as_read', workspace_id: <?php echo $workspace_id; ?> },
                dataType: 'json',
                success: response => {
                    if (response.success) {
                        fetchNotifications();
                        showToast('success', 'Đã đánh dấu tất cả đã đọc!');
                    } else {
                        showToast('error', 'Lỗi khi đánh dấu!', response.message);
                    }
                },
                error: () => showToast('error', 'Đã có lỗi xảy ra!')
            });
        });

        // Đăng xuất
        $(document).on('click', '.logout-btn', function() {
            Swal.fire({
                title: 'Bạn có chắc chắn muốn đăng xuất?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Đăng xuất',
                cancelButtonText: 'Hủy'
            }).then(result => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'auth.php',
                        method: 'POST',
                        data: { action: 'logout' },
                        dataType: 'json',
                        success: response => {
                            if (response.success) {
                                showToast('success', 'Đăng xuất thành công!');
                                setTimeout(() => window.location.href = 'login.php', 1500);
                            } else {
                                showToast('error', 'Lỗi khi đăng xuất!', response.message);
                            }
                        },
                        error: () => showToast('error', 'Đã có lỗi xảy ra!')
                    });
                }
            });
        });

        // Hiển thị form thêm danh sách/thẻ
        $(document).on('click', '.add-list-btn', function() {
            $(this).hide().siblings('.add-list-form').show().find('input[name="list_title"]').focus();
        });
        $(document).on('click', '.add-card-btn', function() {
            $(this).hide().siblings('.add-card-form').show().find('input[name="card_title"]').focus();
        });

        // Hủy thêm danh sách/thẻ
        $(document).on('click', '.cancel-list', function() {
            const $form = $(this).closest('.add-list-form');
            $form.hide().siblings('.add-list-btn').show();
            $form.find('input[name="list_title"]').val('');
        });
        $(document).on('click', '.cancel-card', function() {
            const $form = $(this).closest('.add-card-form');
            $form.hide().siblings('.add-card-btn').show();
            $form.find('input[name="card_title"], textarea[name="card_description"], input[name="due_date"]').val('');
        });

        // Thêm danh sách
        $(document).on('submit', '.add-list-form', function(e) {
            e.preventDefault();
            const $form = $(this);
            const listTitle = $form.find('input[name="list_title"]').val();
            const $addListBtn = $form.siblings('.add-list-btn');

            $addListBtn.addClass('loading');
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'add_list', list_title: listTitle, workspace_id: <?php echo $workspace_id; ?> },
                dataType: 'json',
                success: response => {
                    $addListBtn.removeClass('loading');
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
                                        <input type="datetime-local" name="due_date" placeholder="Hạn chót...">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">Thêm</button>
                                            <button type="button" class="btn btn-cancel cancel-card">Hủy</button>
                                        </div>
                                    </form>
                                </div>
                            </div>`;
                        $form.closest('.list').before(newList);
                        $form.find('input[name="list_title"]').val('');
                        $form.hide().siblings('.add-list-btn').show();
                        initializeSortable();
                        showToast('success', 'Đã thêm danh sách!');
                    } else {
                        showToast('error', 'Lỗi khi thêm danh sách!', response.message);
                    }
                },
                error: () => {
                    $addListBtn.removeClass('loading');
                    showToast('error', 'Đã có lỗi xảy ra!');
                }
            });
        });

        // Thêm thẻ
        $(document).on('submit', '.add-card-form', function(e) {
            e.preventDefault();
            const $form = $(this);
            const listId = $form.find('input[name="list_id"]').val();
            const cardTitle = $form.find('input[name="card_title"]').val();
            const cardDescription = $form.find('textarea[name="card_description"]').val();
            const dueDate = $form.find('input[name="due_date"]').val();
            const $addCardBtn = $form.siblings('.add-card-btn');

            $addCardBtn.addClass('loading');
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'add_card', list_id: listId, card_title: cardTitle, card_description: cardDescription, due_date: dueDate },
                dataType: 'json',
                success: response => {
                    $addCardBtn.removeClass('loading');
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
                                    <span class="edit-card"><i class="fas fa-edit"></i></span>
                                    <span class="delete-card"><i class="fas fa-times"></i></span>
                                </div>
                            </div>`;
                        $form.siblings('.cards-container').append(newCard);
                        $form.find('input[name="card_title"], textarea[name="card_description"], input[name="due_date"]').val('');
                        $form.hide().siblings('.add-card-btn').show();
                        showToast('success', 'Đã thêm thẻ!');
                    } else {
                        showToast('error', 'Lỗi khi thêm thẻ!', response.message);
                    }
                },
                error: () => {
                    $addCardBtn.removeClass('loading');
                    showToast('error', 'Đã có lỗi xảy ra!');
                }
            });
        });

        // Sửa thẻ
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
            }).then(result => {
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
                        success: response => {
                            if (response.success) {
                                $card.find('.card-content strong').text(response.card.title);
                                $card.find('.card-content p').text(response.card.description);
                                $card.find('.card-content small').text(response.card.due_date ? `Hạn chót: ${new Date(response.card.due_date).toLocaleString('vi-VN')}` : '');
                                showToast('success', 'Đã cập nhật thẻ!');
                            } else {
                                showToast('error', 'Lỗi khi cập nhật thẻ!', response.message);
                            }
                        },
                        error: () => showToast('error', 'Đã có lỗi xảy ra!')
                    });
                }
            });
        });

        // Sắp xếp thẻ
        $(document).on('click', '.sort-cards', function(e) {
            e.preventDefault();
            const $list = $(this).closest('.list');
            const listId = $list.data('list-id');
            const sortOrder = $(this).data('sort');
            const $cardsContainer = $list.find('.cards-container');
            const cards = $cardsContainer.children('.card').get();

            cards.sort((a, b) => {
                const titleA = $(a).find('.card-content strong').text().toLowerCase();
                const titleB = $(b).find('.card-content strong').text().toLowerCase();
                return sortOrder === 'asc' ? titleA.localeCompare(titleB) : titleB.localeCompare(titleA);
            });

            $cardsContainer.empty();
            cards.forEach(card => $cardsContainer.append(card));

            $list.addClass('loading');
            const cardIds = cards.map(card => $(card).data('card-id'));
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'sort_cards', list_id: listId, card_ids: cardIds, sort_order: sortOrder },
                dataType: 'json',
                success: response => {
                    $list.removeClass('loading');
                    response.success ? showToast('success', `Đã sắp xếp ${sortOrder === 'asc' ? 'A-Z' : 'Z-A'}!`) : showToast('error', 'Lỗi khi sắp xếp!', response.message);
                },
                error: () => {
                    $list.removeClass('loading');
                    showToast('error', 'Đã có lỗi xảy ra!');
                }
            });
        });

        // Lưu trữ danh sách
        $(document).on('click', '.archive-list', function(e) {
            e.preventDefault();
            const $list = $(this).closest('.list');
            const listId = $list.data('list-id');

            Swal.fire({
                title: 'Lưu trữ danh sách này?',
                text: 'Bạn có thể khôi phục sau.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Lưu trữ',
                cancelButtonText: 'Hủy'
            }).then(result => {
                if (result.isConfirmed) {
                    $list.addClass('loading');
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: { action: 'archive_list', list_id: listId },
                        dataType: 'json',
                        success: response => {
                            $list.removeClass('loading');
                            if (response.success) {
                                $list.addClass('removed');
                                setTimeout(() => $list.remove(), 300);
                                showToast('success', 'Đã lưu trữ danh sách!');
                            } else {
                                showToast('error', 'Lỗi khi lưu trữ!', response.message);
                            }
                        },
                        error: () => {
                            $list.removeClass('loading');
                            showToast('error', 'Đã có lỗi xảy ra!');
                        }
                    });
                }
            });
        });

        // Xem danh sách đã lưu trữ
        $(document).on('click', '.view-archived-lists', function() {
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'get_archived_lists', workspace_id: <?php echo $workspace_id; ?> },
                dataType: 'json',
                success: response => {
                    if (response.success) {
                        let html = '<div style="max-height: 300px; overflow-y: auto;"><table class="table table-striped">';
                        html += '<thead><tr><th>Tiêu đề</th><th>Ngày lưu trữ</th><th>Hành động</th></tr></thead><tbody>';
                        if (response.lists.length === 0) {
                            html += '<tr><td colspan="3" class="text-center">Chưa có danh sách nào được lưu trữ.</td></tr>';
                        } else {
                            response.lists.forEach(list => {
                                html += `
                                    <tr>
                                        <td>${list.title}</td>
                                        <td>${list.archived_at ? new Date(list.archived_at).toLocaleString('vi-VN') : 'Không có thông tin'}</td>
                                        <td><button class="btn btn-sm btn-success restore-list" data-list-id="${list.id}">Khôi phục</button></td>
                                    </tr>`;
                            });
                        }
                        html += '</tbody></table></div>';

                        Swal.fire({
                            title: 'Danh sách đã lưu trữ',
                            html: html,
                            confirmButtonText: 'Đóng',
                            width: '600px'
                        });
                    } else {
                        showToast('error', 'Lỗi khi lấy danh sách!', response.message);
                    }
                },
                error: () => showToast('error', 'Đã có lỗi xảy ra!')
            });
        });

        // Khôi phục danh sách
        $(document).on('click', '.restore-list', function() {
            const listId = $(this).data('list-id');
            Swal.fire({
                title: 'Khôi phục danh sách này?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Khôi phục',
                cancelButtonText: 'Hủy'
            }).then(result => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: { action: 'restore_list', list_id: listId },
                        dataType: 'json',
                        success: response => {
                            if (response.success) {
                                showToast('success', 'Đã khôi phục danh sách!', 'Tải lại trang để xem.');
                                Swal.close();
                            } else {
                                showToast('error', 'Lỗi khi khôi phục!', response.message);
                            }
                        },
                        error: () => showToast('error', 'Đã có lỗi xảy ra!')
                    });
                }
            });
        });

        // Bộ lọc
        $(document).on('click', '.filter-option', function(e) {
            e.preventDefault();
            const filterType = $(this).data('filter');
            if (filterType === 'custom') {
                $('#customFilterModal').modal('show');
                return;
            }
            applyFilter(filterType);
        });

        $(document).on('click', '.apply-custom-filter', function() {
            const startDate = $('#startDate').val();
            const endDate = $('#endDate').val();

            if (!startDate || !endDate || new Date(startDate) > new Date(endDate)) {
                showToast('error', 'Vui lòng chọn khoảng thời gian hợp lệ!');
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
            localStorage.setItem('filterType', filterType);
            if (filterType === 'custom') {
                localStorage.setItem('startDate', startDate);
                localStorage.setItem('endDate', endDate);
            } else {
                localStorage.removeItem('startDate');
                localStorage.removeItem('endDate');
            }

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
                data: { action: 'filter_lists_and_cards', workspace_id: <?php echo $workspace_id; ?>, filter_type: filterType, start_date: startDate, end_date: endDate },
                dataType: 'json',
                success: response => {
                    const $listsContainer = $('#lists-container');
                    $listsContainer.empty();

                    if (response.success) {
                        if (response.lists.length === 0) {
                            $listsContainer.html('<p class="text-muted">Không có danh sách nào phù hợp.</p>');
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
                                                            ${card.due_date ? `<small class="text-muted">Hạn chót: ${new Date(card.due_date).toLocaleString('vi-VN')}</small>` : ''}
                                                        </div>
                                                    </div>
                                                    <div class="card-actions">
                                                        <span class="edit-card"><i class="fas fa-edit"></i></span>
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
                        initializeSortable();
                        showToast('success', 'Đã áp dụng bộ lọc!');
                    } else {
                        showToast('error', 'Lỗi khi áp dụng bộ lọc!', response.message);
                    }
                },
                error: () => showToast('error', 'Đã có lỗi xảy ra!')
            });
        }

        // Tìm kiếm
        $('#searchInput').on('input', debounce(function() {
            const query = $(this).val().trim();
            const $clearSearchBtn = $('#clearSearch');

            if (query.length > 0) $clearSearchBtn.show();
            else $clearSearchBtn.hide();

            if (query.length < 2 && query.length !== 0) return;

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'search_cards', workspace_id: <?php echo $workspace_id; ?>, query: query },
                dataType: 'json',
                success: response => {
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
                                        ${list.cards.map(card => {
                                            const now = new Date();
                                            const dueDate = card.due_date ? new Date(card.due_date) : null;
                                            const isCompleted = card.completed;
                                            const isOverdue = dueDate && dueDate < now && !isCompleted;
                                            const timeDiff = dueDate ? dueDate - now : null;
                                            const hoursUntilDue = timeDiff ? timeDiff / (1000 * 60 * 60) : null;
                                            const isNearingDue = dueDate && !isCompleted && !isOverdue && hoursUntilDue <= 24 && hoursUntilDue >= 0;

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
                    }

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

                    initializeSortable();
                },
                error: () => showToast('error', 'Đã có lỗi xảy ra khi tìm kiếm!')
            });
        }, 300));

        // Xóa tìm kiếm
        $('#clearSearch').on('click', function() {
            $('#searchInput').val('').trigger('input');
            $(this).hide();
        });

        // Xem người được chia sẻ
        $(document).on('click', '.view-workspace-collaborators', function() {
            $('#viewCollaboratorsModal').modal('show');
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'get_workspace_collaborators', workspace_id: <?php echo $workspace_id; ?> },
                dataType: 'json',
                success: response => {
                    const $collaboratorsList = $('#collaborators-list');
                    $collaboratorsList.empty();

                    if (response.success && response.collaborators.length > 0) {
                        response.collaborators.forEach(collaborator => {
                            const isOwner = collaborator.access_level === 'owner';
                            const actionButton = isOwner ? '<td>-</td>' : `<td><button class="btn btn-sm btn-danger remove-collaborator" data-user-id="${collaborator.user_id}">Hủy chia sẻ</button></td>`;
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
                error: () => $('#collaborators-list').html('<tr><td colspan="4">Lỗi khi tải dữ liệu.</td></tr>')
            });
        });

        // Hủy chia sẻ
        $(document).on('click', '.remove-collaborator', function() {
            const userId = $(this).data('user-id');
            Swal.fire({
                title: 'Hủy chia sẻ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Hủy chia sẻ',
                cancelButtonText: 'Hủy'
            }).then(result => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: { action: 'remove_collaborator', workspace_id: <?php echo $workspace_id; ?>, user_id: userId },
                        dataType: 'json',
                        success: response => {
                            if (response.success) {
                                showToast('success', 'Đã hủy chia sẻ!');
                                $('.view-workspace-collaborators').trigger('click');
                            } else {
                                showToast('error', 'Lỗi khi hủy chia sẻ!', response.message);
                            }
                        },
                        error: () => showToast('error', 'Đã có lỗi xảy ra!')
                    });
                }
            });
        });

        // Tải lại danh sách và thẻ
        function loadListsAndCards() {
            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: { action: 'filter_lists_and_cards', workspace_id: <?php echo $workspace_id; ?>, filter_type: 'all' },
                dataType: 'json',
                success: response => {
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
                                            const isNearingDue = dueDate && !isCompleted && !isOverdue && hoursUntilDue <= 24 && hoursUntilDue >= 0;

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
                        initializeSortable();
                    }
                },
                error: () => showToast('error', 'Lỗi khi tải danh sách!')
            });
        }

        // Kiểm tra và gửi thông báo Gmail  Gửi rất là khủng hoảng
//         function checkAndNotifyNearingDueCards() {
//             $.ajax({
//                 url: 'api.php',
//                 method: 'POST',
//                 data: { action: 'get_nearing_due_cards', workspace_id: <?php echo $workspace_id; ?> },
//                 dataType: 'json',
//                 success: response => {
//                     if (response.success) {
//                         response.cards.forEach(card => {
//                             const now = new Date();
//                             const dueDate = new Date(card.due_date);
//                             const timeDiff = dueDate - now;
//                             const hoursUntilDue = timeDiff / (1000 * 60 * 60);

//                             if (hoursUntilDue <= 24 && hoursUntilDue >= 0 && !card.completed) {
//                                 $.ajax({
//                                     url: 'api.php',
//                                     method: 'POST',
//                                     data: {
//                                         action: 'send_gmail_notification',
//                                         card_id: card.id,
//                                         card_title: card.title,
//                                         due_date: card.due_date,
//                                         workspace_id: <?php echo $workspace_id; ?>
//                                     },
//                                     dataType: 'json',
//                                     success: response => {
//                                         if (response.success) {
//                                             showToast('success', `Đã gửi thông báo cho "${card.title}"!`);
//                                         }
//                                     }
//                                 });
//                             }
//                         });
//                     }
//                 }
//             });
//         }

   
//         $(document).ready(function() {
//             moment.locale('vi');
//             initializeSortable();
//             fetchNotifications();
//             setInterval(() => fetchNotifications(1), 10000);
//             checkAndNotifyNearingDueCards();
//             setInterval(checkAndNotifyNearingDueCards, 3600000);

//             const savedFilterType = localStorage.getItem('filterType') || 'all';
//             const savedStartDate = localStorage.getItem('startDate');
//             const savedEndDate = localStorage.getItem('endDate');
//             if (savedFilterType !== 'all') applyFilter(savedFilterType, savedStartDate, savedEndDate);
//         });
//         function checkNearingDueCards() {
//     fetch('api.php', {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/x-www-form-urlencoded',
//         },
//         body: new URLSearchParams({
//             'action': 'get_nearing_due_cards',
//             'workspace_id': 1
//         })
//     })
//     .then(response => response.json())
//     .then(data => {
//         if (data.success && data.cards.length > 0) {
//             data.cards.forEach(card => {
   
//                 fetch('api.php', {
//                     method: 'POST',
//                     headers: {
//                         'Content-Type': 'application/x-www-form-urlencoded',
//                     },
//                     body: new URLSearchParams({
//                         'action': 'send_gmail_notification',
//                         'workspace_id': 1,
//                         'card_id': card.id
//                     })
//                 })
//                 .then(response => response.json())
//                 .then(result => {
//                     console.log('Gửi thông báo Gmail:', result);
//                 })
//                 .catch(error => console.error('Lỗi khi gửi thông báo Gmail:', error));
//             });
//         }
//     })
//     .catch(error => console.error('Lỗi khi lấy thẻ sắp hết hạn:', error));
// }

// Gọi hàm này định kỳ, ví dụ mỗi 5 phút
setInterval(checkNearingDueCards, 30000); // 300000ms = 5 phút
    </script>
</body>
</html>