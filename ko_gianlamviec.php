<?php
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
            border-radius: 3px;
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
        <button class="btn btn-light btn-sm"><i class="fas fa-lock me-1"></i> Riêng tư</button>
    </div>

    <!-- Navbar -->
    <div class="navbar">
        <div class="navbar-left">
            <button class="btn btn-sm btn-outline-primary share-workspace"><i class="fas fa-share-alt"></i> Chia sẻ</button>
            <button class="btn btn-sm btn-outline-info view-workspace-collaborators"><i class="fas fa-users"></i> Xem người được chia sẻ</button>
        </div>
        <div class="navbar-right">
            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-robot"></i> Tự động hóa</button>
            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter"></i> Bộ lọc</button>
        </div>
    </div>

    <!-- Board -->
    <div class="board">
        <!-- Hiển thị các danh sách -->
        <?php foreach ($lists as $list): ?>
            <div class="list" data-list-id="<?php echo $list['id']; ?>">
                <div class="list-header">
                    <h3><?php echo htmlspecialchars($list['title']); ?></h3>
                    <div class="dropdown">
                        <span class="list-actions" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></span>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item sort-cards" href="#">Sắp xếp theo tiêu đề</a></li>
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
                        $completed = isset($card['completed']) ? $card['completed'] : 0;
                    ?>
                        <div class="card <?php echo $completed ? 'completed' : ''; ?>" data-card-id="<?php echo $card['id']; ?>">
                            <div class="card-inner">
                                <input type="checkbox" class="complete-card" <?php echo $completed ? 'checked' : ''; ?>>
                                <div class="card-content">
                                    <strong><?php echo htmlspecialchars($card['title']); ?></strong>
                                    <p><?php echo htmlspecialchars($card['description']); ?></p>
                                </div>
                            </div>
                            <div class="card-actions">
                                <span class="delete-card"><i class="fas fa-times"></i></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Nút thêm thẻ -->
                <div class="add-card">
                    <button class="add-card-btn"><i class="fas fa-plus"></i> Thêm thẻ</button>
                    <form class="add-card-form">
                        <input type="hidden" name="list_id" value="<?php echo $list['id']; ?>">
                        <input type="text" name="card_title" placeholder="Tên thẻ..." required>
                        <textarea name="card_description" placeholder="Mô tả..."></textarea>
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
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã di chuyển thẻ!',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Lỗi khi di chuyển thẻ!',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            }
                        },
                        error: function() {
                            newList.classList.remove('loading');
                            Swal.fire({
                                icon: 'error',
                                title: 'Đã có lỗi xảy ra!',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
                        }
                    });
                }, 300)
            });
        });

        // Đánh dấu thẻ hoàn thành
        $(document).on('change', '.complete-card', debounce(function() {
            const card = $(this).closest('.card');
            const cardId = card.data('card-id');
            const completed = $(this).is(':checked') ? 1 : 0;

            card.closest('.list').classList.add('loading');

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'toggle_card_completion',
                    card_id: cardId,
                    completed: completed
                },
                dataType: 'json',
                success: function(response) {
                    card.closest('.list').classList.remove('loading');
                    if (response.success) {
                        if (completed) {
                            card.addClass('completed');
                            Swal.fire({
                                icon: 'success',
                                title: 'Đã đánh dấu hoàn thành!',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
                        } else {
                            card.removeClass('completed');
                            Swal.fire({
                                icon: 'info',
                                title: 'Đã bỏ đánh dấu hoàn thành!',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi khi cập nhật trạng thái!',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    }
                },
                error: function() {
                    card.closest('.list').classList.remove('loading');
                    Swal.fire({
                        icon: 'error',
                        title: 'Đã có lỗi xảy ra!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        customClass: {
                            popup: 'animated fadeInRight faster'
                        }
                    });
                }
            });
        }, 300));

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
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã xóa thẻ!',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: response.message || 'Lỗi khi xóa thẻ!',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            list.removeClass('loading');
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi khi xóa thẻ!',
                                text: 'Chi tiết lỗi: ' + error,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
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
                text: 'Tất cả các thẻ trong danh sách sẽ bị xóa vĩnh viễn. Bạn có chắc chắn muốn tiếp tục?',
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
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã xóa danh sách!',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: response.message || 'Lỗi khi xóa danh sách!',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            list.removeClass('loading');
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi khi xóa danh sách!',
                                text: 'Chi tiết lỗi: ' + error,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
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
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã gửi lời mời!',
                                    text: `Đã mời ${email} với quyền ${accessLevel === 'edit' ? 'chỉnh sửa' : 'chỉ xem'}.`,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 2000,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Lỗi khi gửi lời mời!',
                                    text: response.message,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 2000,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi khi gửi lời mời!',
                                text: 'Chi tiết lỗi: ' + error,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
                        }
                    });
                }
            });
        });

        // Xem danh sách người được chia sẻ không gian làm việc
        $(document).on('click', '.view-workspace-collaborators', function() {
            const workspaceId = <?php echo $workspace_id; ?>;

            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: {
                    action: 'get_workspace_collaborators',
                    workspace_id: workspaceId,
                    page: 1 // Hỗ trợ phân trang nếu cần
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '<div style="max-height: 300px; overflow-y: auto;"><table class="table table-striped">';
                        html += '<thead><tr><th>Email</th><th>Quyền</th><th>Ngày mời</th></tr></thead><tbody>';
                        if (response.collaborators.length === 0) {
                            html += '<tr><td colspan="3" class="text-center">Chưa có người được chia sẻ.</td></tr>';
                        } else {
                            response.collaborators.forEach(collaborator => {
                                html += `<tr>
                                    <td>${collaborator.email}</td>
                                    <td>${collaborator.access_level === 'edit' ? 'Có thể chỉnh sửa' : 'Chỉ xem'}</td>
                                    <td>${collaborator.created_at || 'Không có thông tin'}</td>
                                </tr>`;
                            });
                        }
                        html += '</tbody></table></div>';
                        Swal.fire({
                            title: 'Danh sách người được chia sẻ',
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
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi khi lấy danh sách!',
                            text: response.message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi khi lấy danh sách!',
                        text: 'Chi tiết lỗi: ' + error,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        customClass: {
                            popup: 'animated fadeInRight faster'
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
                    list_title: listTitle
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
                                            <li><a class="dropdown-item sort-cards" href="#">Sắp xếp theo tiêu đề</a></li>
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
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Đã di chuyển thẻ!',
                                                toast: true,
                                                position: 'top-end',
                                                showConfirmButton: false,
                                                timer: 1500,
                                                customClass: {
                                                    popup: 'animated fadeInRight faster'
                                                }
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Lỗi khi di chuyển thẻ!',
                                                toast: true,
                                                position: 'top-end',
                                                showConfirmButton: false,
                                                timer: 1500,
                                                customClass: {
                                                    popup: 'animated fadeInRight faster'
                                                }
                                            });
                                        }
                                    },
                                    error: function() {
                                        newList.classList.remove('loading');
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Đã có lỗi xảy ra!',
                                            toast: true,
                                            position: 'top-end',
                                            showConfirmButton: false,
                                            timer: 1500,
                                            customClass: {
                                                popup: 'animated fadeInRight faster'
                                            }
                                        });
                                    }
                                });
                            }, 300)
                        });

                        Swal.fire({
                            icon: 'success',
                            title: 'Đã thêm danh sách!',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: response.message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    }
                },
                error: function() {
                    addListBtn.removeClass('loading');
                    Swal.fire({
                        icon: 'error',
                        title: 'Đã có lỗi xảy ra!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        customClass: {
                            popup: 'animated fadeInRight faster'
                        }
                    });
                }
            });
        });

        // Xử lý thêm thẻ mới
        $(document).on('submit', '.add-card-form', function(e) {
            e.preventDefault();

            const form = $(this);
            const listId = form.find('input[name="list_id"]').val();
            const cardTitle = form.find('input[name="card_title"]').val();
            const cardDescription = form.find('textarea[name="card_description"]').val();
            const addCardBtn = form.siblings('.add-card-btn');

            addCardBtn.addClass('loading');

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'add_card',
                    list_id: listId,
                    card_title: cardTitle,
                    card_description: cardDescription
                },
                dataType: 'json',
                success: function(response) {
                    addCardBtn.removeClass('loading');
                    if (response.success) {
                        const newCard = `
                            <div class="card added" data-card-id="${response.card.id}">
                                <div class="card-inner">
                                    <input type="checkbox" class="complete-card">
                                    <div class="card-content">
                                        <strong>${response.card.title}</strong>
                                        <p>${response.card.description}</p>
                                    </div>
                                </div>
                                <div class="card-actions">
                                    <span class="delete-card"><i class="fas fa-times"></i></span>
                                </div>
                            </div>`;
                        form.siblings('.cards-container').append(newCard);
                        form.find('input[name="card_title"]').val('');
                        form.find('textarea[name="card_description"]').val('');
                        form.hide();
                        form.siblings('.add-card-btn').show();

                        Swal.fire({
                            icon: 'success',
                            title: 'Đã thêm thẻ!',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: response.message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    }
                },
                error: function() {
                    addCardBtn.removeClass('loading');
                    Swal.fire({
                        icon: 'error',
                        title: 'Đã có lỗi xảy ra!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        customClass: {
                            popup: 'animated fadeInRight faster'
                        }
                    });
                }
            });
        });

        // Xử lý sắp xếp thẻ
        $(document).on('click', '.sort-cards', function(e) {
            e.preventDefault();
            const list = $(this).closest('.list');
            const listId = list.data('list-id');

            list.classList.add('loading');

            $.ajax({
                url: 'api.php',
                method: 'POST',
                data: {
                    action: 'sort_cards',
                    list_id: listId
                },
                dataType: 'json',
                success: function(response) {
                    list.classList.remove('loading');
                    if (response.success) {
                        const cardsContainer = list.find('.cards-container');
                        cardsContainer.empty();
                        response.cards.forEach(card => {
                            const completed = card.completed || 0;
                            const cardHtml = `
                                <div class="card added ${completed ? 'completed' : ''}" data-card-id="${card.id}">
                                    <div class="card-inner">
                                        <input type="checkbox" class="complete-card" ${completed ? 'checked' : ''}>
                                        <div class="card-content">
                                            <strong>${card.title}</strong>
                                            <p>${card.description}</p>
                                        </div>
                                    </div>
                                    <div class="card-actions">
                                        <span class="delete-card"><i class="fas fa-times"></i></span>
                                    </div>
                                </div>`;
                            cardsContainer.append(cardHtml);
                        });

                        Swal.fire({
                            icon: 'success',
                            title: 'Đã sắp xếp thẻ!',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: response.message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    }
                },
                error: function() {
                    list.classList.remove('loading');
                    Swal.fire({
                        icon: 'error',
                        title: 'Đã có lỗi xảy ra!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        customClass: {
                            popup: 'animated fadeInRight faster'
                        }
                    });
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
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Lưu trữ',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    list.classList.add('loading');
                    $.ajax({
                        url: 'api.php',
                        method: 'POST',
                        data: {
                            action: 'archive_list',
                            list_id: listId
                        },
                        dataType: 'json',
                        success: function(response) {
                            list.classList.remove('loading');
                            if (response.success) {
                                list.addClass('removed');
                                list.on('animationend', () => list.remove());
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã lưu trữ danh sách!',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: response.message,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    customClass: {
                                        popup: 'animated fadeInRight faster'
                                    }
                                });
                            }
                        },
                        error: function() {
                            list.classList.remove('loading');
                            Swal.fire({
                                icon: 'error',
                                title: 'Đã có lỗi xảy ra!',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            });
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>