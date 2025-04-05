<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Nếu dùng Composer, nếu không hãy include các file PHPMailer thủ công

function sendDeadlineReminder($email, $taskTitle, $deadline) {
    $mail = new PHPMailer(true);

    try {
        // Cấu hình SMTP Gmail
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'dongtao805@gmail.com'; // Thay bằng email của bạn
        $mail->Password = '123'; // Thay bằng mật khẩu ứng dụng Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Cấu hình email gửi đi
        $mail->setFrom('your-email@gmail.com', 'Task Manager');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Nhắc nhở deadline: $taskTitle";
        $mail->Body = "<h3>Bạn có nhiệm vụ '$taskTitle' sắp đến hạn vào $deadline</h3><p>Vui lòng hoàn thành đúng thời hạn.</p>";

        // Gửi email
        $mail->send();
        echo "📧 Email đã được gửi thành công đến $email!";
    } catch (Exception $e) {
        echo "❌ Gửi email thất bại: {$mail->ErrorInfo}";
    }
}
?>
