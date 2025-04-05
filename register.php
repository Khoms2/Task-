<?php
session_start();

// Nếu người dùng đã đăng nhập, chuyển hướng đến trang chủ
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .register-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .register-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #172b4d;
        }
        .form-control {
            border-radius: 3px;
            border: 1px solid #dfe1e6;
            padding: 10px;
            font-size: 14px;
        }
        .form-control:focus {
            box-shadow: inset 0 0 0 2px #0079bf;
            border-color: #0079bf;
        }
        .btn-primary {
            background-color: #0079bf;
            border: none;
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border-radius: 3px;
        }
        .btn-primary:hover {
            background-color: #005ea6;
        }
        .text-center a {
            color: #0079bf;
            text-decoration: none;
        }
        .text-center a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
    <img src="./img/logo2.png" alt="task-manager" style="width: 100px; display: block; margin: 0 auto 1rem;">
        <h2><i class="fas fa-user-plus me-2"></i> Đăng ký</h2>
        <form id="register-form">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Nhập email của bạn" required>
            </div>
            <div class="mb-3">
                <label for="name" class="form-label">Tên</label>
                <input type="text" class="form-control" id="name" name="name" placeholder="Nhập tên của bạn" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Nhập mật khẩu" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
            </div>
            <button type="submit" class="btn btn-primary">Đăng ký</button>
            <div class="text-center mt-3">
                <a href="login.php">Đã có tài khoản? Đăng nhập ngay!</a>
            </div>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS và Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#register-form').on('submit', function(e) {
                e.preventDefault();

                const email = $('#email').val();
                const name = $('#name').val();
                const password = $('#password').val();
                const confirm_password = $('#confirm_password').val();

                // Kiểm tra mật khẩu và xác nhận mật khẩu có khớp không
                if (password !== confirm_password) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Đăng ký thất bại!',
                        text: 'Mật khẩu và xác nhận mật khẩu không khớp.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        customClass: {
                            popup: 'animated fadeInRight faster'
                        }
                    });
                    return;
                }

                $.ajax({
                    url: 'auth.php',
                    method: 'POST',
                    data: {
                        action: 'register',
                        email: email,
                        name: name,
                        password: password
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Đăng ký thành công!',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500,
                                customClass: {
                                    popup: 'animated fadeInRight faster'
                                }
                            }).then(() => {
                                window.location.href = 'login.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Đăng ký thất bại!',
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
                            title: 'Lỗi!',
                            text: 'Đã có lỗi xảy ra, vui lòng thử lại. Chi tiết: ' + error,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            customClass: {
                                popup: 'animated fadeInRight faster'
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>