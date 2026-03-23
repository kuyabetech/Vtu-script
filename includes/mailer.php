<?php
// includes/mailer.php - Email sending using PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    
    /**
     * Send email using PHPMailer
     */
    public static function send($to, $subject, $message, $from = null, $from_name = null) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'] ?? '';
            $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;
            
            // Sender
            $from = $from ?? $_ENV['MAIL_FROM'] ?? 'noreply@vtuplatform.com';
            $from_name = $from_name ?? $_ENV['MAIL_FROM_NAME'] ?? SITE_NAME;
            $mail->setFrom($from, $from_name);
            
            // Recipient
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);
            
            $mail->send();
            
            // Log to database
            self::logEmail($to, $subject, 'sent');
            
            return true;
            
        } catch (Exception $e) {
            self::logEmail($to, $subject, 'failed', $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send welcome email
     */
    public static function sendWelcome($email, $username) {
        $subject = "Welcome to " . SITE_NAME;
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .button { display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to " . SITE_NAME . "!</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($username) . ",</h2>
                    <p>Thank you for registering with " . SITE_NAME . "!</p>
                    <p>You can now:</p>
                    <ul>
                        <li>Buy airtime for all networks</li>
                        <li>Purchase data bundles</li>
                        <li>Pay electricity bills</li>
                        <li>Subscribe to cable TV</li>
                    </ul>
                    <p>
                        <a href='" . SITE_URL . "/auth/login.php' class='button'>Login to Your Account</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::send($email, $subject, $message);
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordReset($email, $username, $token) {
        $subject = "Password Reset Request - " . SITE_NAME;
        
        $reset_link = SITE_URL . "/auth/reset_password.php?token=" . $token;
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .button { display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($username) . ",</h2>
                    <p>We received a request to reset your password.</p>
                    <p>Click the button below to reset it:</p>
                    <p>
                        <a href='" . $reset_link . "' class='button'>Reset Password</a>
                    </p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <p>This link will expire in 1 hour.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::send($email, $subject, $message);
    }
    
    /**
     * Send transaction receipt
     */
    public static function sendReceipt($email, $username, $transaction) {
        $subject = "Transaction Receipt - " . SITE_NAME;
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .success { color: #10b981; }
                .pending { color: #f59e0b; }
                .failed { color: #ef4444; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Transaction Receipt</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($username) . ",</h2>
                    <p>Your transaction has been processed successfully.</p>
                    
                    <div class='details'>
                        <h3>Transaction Details</h3>
                        <p><strong>Reference:</strong> " . $transaction['transaction_id'] . "</p>
                        <p><strong>Type:</strong> " . ucfirst($transaction['type']) . "</p>
                        <p><strong>Amount:</strong> " . format_money($transaction['amount']) . "</p>
                        <p><strong>Status:</strong> <span class='" . $transaction['status'] . "'>" . ucfirst($transaction['status']) . "</span></p>
                        <p><strong>Date:</strong> " . date('M d, Y h:i A', strtotime($transaction['created_at'])) . "</p>
                    </div>
                    
                    <p>Thank you for using " . SITE_NAME . "!</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::send($email, $subject, $message);
    }
    
    /**
     * Log email to database
     */
    private static function logEmail($to, $subject, $status, $error = null) {
        require_once __DIR__ . '/db_connection.php';
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO email_queue (to_email, subject, status, error_message, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $to, $subject, $status, $error);
        $stmt->execute();
    }
}
?>