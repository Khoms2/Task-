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

// Xử lý các yêu cầu
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'login':
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập email và mật khẩu']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Lưu thông tin người dùng vào session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                echo json_encode(['success' => true, 'message' => 'Đăng nhập thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi đăng nhập: ' . $e->getMessage()]);
        }
        break;

    case 'register':
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        // Kiểm tra các trường bắt buộc
        if (empty($email) || empty($name) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
            exit();
        }

        // Kiểm tra định dạng email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
            exit();
        }

        // Kiểm tra độ dài mật khẩu (tối thiểu 6 ký tự)
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự']);
            exit();
        }

        try {
            // Kiểm tra xem email đã tồn tại chưa
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'Email đã được sử dụng']);
                exit();
            }

            // Mã hóa mật khẩu
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Thêm người dùng mới vào cơ sở dữ liệu
            $stmt = $pdo->prepare("INSERT INTO users (email, name, password, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $name, $hashed_password]);

            echo json_encode(['success' => true, 'message' => 'Đăng ký thành công']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi đăng ký: ' . $e->getMessage()]);
        }
        break;

    case 'logout':
        // Xóa tất cả dữ liệu session
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Đăng xuất thành công']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        break;
}
?>