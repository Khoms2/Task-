Api
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
        $mail->Password = 'ahqn tjge obkp kzow'; // Thay bằng App Password mới
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Bật debug để ghi log
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('phpmailer_debug.log', "$level: $str\n", FILE_APPEND);
        };

        // Người gửi
        $mail->setFrom('dongtao805@gmail.com', 'Task Manager');

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

<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Đường dẫn tới autoload của Composer

function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    try {
        // Cấu hình SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'dongtao805@gmail.comcom'; // Thay bằng email của bạn
        $mail->Password = 'ahqn tjge obkp kzow';    // Thay bằng App Password của bạn
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Người gửi và người nhận
        $mail->setFrom('dongtao805@gmail.comcom', 'Task Manager');
        $mail->addAddress($to);

        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => "Email sending failed: " . $mail->ErrorInfo];
    }
}
?>



