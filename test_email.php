<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/SMTP.php';

echo "<h2>Email Configuration Test</h2>";
echo "<pre>";

$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';

    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ethos.cpstn@gmail.com';
    $mail->Password   = 'ntwhcojthfgakjxr';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('ethos.cpstn@gmail.com', 'HanapBahay Test');
    $mail->addAddress('ethos.cpstn@gmail.com', 'Test Recipient');

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - ' . date('Y-m-d H:i:s');
    $mail->Body    = '<h3>This is a test email</h3><p>If you receive this, your email configuration is working correctly.</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
    $mail->AltBody = 'This is a test email. Sent at: ' . date('Y-m-d H:i:s');

    $mail->send();
    echo "\n\n<strong style='color:green;'>✅ SUCCESS! Email sent successfully.</strong>\n";
    echo "Check your inbox at: ethos.cpston@gmail.com\n";
} catch (Exception $e) {
    echo "\n\n<strong style='color:red;'>❌ FAILED! Email could not be sent.</strong>\n";
    echo "Error: {$mail->ErrorInfo}\n";
    echo "Exception: {$e->getMessage()}\n";
}

echo "</pre>";
?>
