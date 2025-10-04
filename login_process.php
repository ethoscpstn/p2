<?php
// login_process.php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php-error.log');

session_start();
require 'mysql_connect.php';

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;

function sendLoginCode($conn, $user_id, $email, $name) {
    $code   = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    $stmt = $conn->prepare("UPDATE tbadmin 
                            SET verification_code=?, code_expiry=?, login_attempts=0, lock_until=NULL 
                            WHERE id=?");
    $stmt->bind_param("ssi", $code, $expiry, $user_id);
    $stmt->execute();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ethos.cpstn@gmail.com';
        $mail->Password   = 'ntwhcojthfgakjxr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('ethos.cpstn@gmail.com', 'HanapBahay');
        $mail->addAddress($email, $name);
        $mail->Subject = 'Your HanapBahay Verification Code';
        $mail->Body    = "Your code is: $code\nThis will expire in 10 minutes.";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, email, password, role, first_name, last_name 
                            FROM tbadmin WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $stored = $user['password'];
        $ok = false;

        // Check if password looks like a password_hash()
        $info = password_get_info($stored);
        if (!empty($info['algo'])) {
            // Modern hash
            $ok = password_verify($password, $stored);
        } else {
            // Legacy MD5
            if (hash_equals($stored, md5($password))) {
                $ok = true;
                // Auto-upgrade to password_hash()
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $conn->prepare("UPDATE tbadmin SET password=? WHERE id=?");
                $up->bind_param("si", $newHash, $user['id']);
                $up->execute();
                $up->close();
            }
        }

        if ($ok) {
            // Store pending info only (2FA not yet verified)
            $_SESSION['pending_user_id'] = $user['id'];
            $_SESSION['pending_role']    = $user['role'];
            $_SESSION['pending_email']   = $user['email'];

            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            sendLoginCode($conn, $user['id'], $user['email'], $name);

            header("Location: verify_code");
            exit();
        } else {
            $_SESSION['login_error'] = "Incorrect password.";
        }
    } else {
        $_SESSION['login_error'] = "Account not found.";
    }

    header("Location: LoginModule");
    exit();
} else {
    // If accessed directly (not POST), redirect to login
    header("Location: LoginModule");
    exit();
}