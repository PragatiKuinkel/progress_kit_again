<?php
require_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailVerification {
    private $db;
    private $mailer;
    private $baseUrl;

    public function __construct($db) {
        $this->db = $db;
        $this->baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        
        // Initialize PHPMailer
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    }

    public function sendVerificationEmail($userId, $email) {
        try {
            // Generate verification token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store token in database
            $stmt = $this->db->prepare("INSERT INTO verification_tokens (user_id, token, token_type, expires_at) VALUES (?, ?, 'email_verification', ?)");
            $stmt->execute([$userId, $token, $expiresAt]);
            
            // Prepare email
            $verificationLink = $this->baseUrl . "/verify_email.php?token=" . $token;
            
            $this->mailer->addAddress($email);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Verify Your Email Address';
            $this->mailer->Body = "
                <h2>Email Verification</h2>
                <p>Please click the following link to verify your email address:</p>
                <p><a href='$verificationLink'>$verificationLink</a></p>
                <p>This link will expire in 24 hours.</p>
            ";
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordResetCode($userId, $email) {
        try {
            // Generate 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store code in database
            $stmt = $this->db->prepare("INSERT INTO verification_tokens (user_id, token, token_type, expires_at) VALUES (?, ?, 'password_reset', ?)");
            $stmt->execute([$userId, $code, $expiresAt]);
            
            // Prepare email
            $this->mailer->addAddress($email);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Reset Code';
            $this->mailer->Body = "
                <h2>Password Reset Code</h2>
                <p>Your password reset code is: <strong>$code</strong></p>
                <p>This code will expire in 10 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
            ";
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function verifyEmailToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id, expires_at, used_at 
                FROM verification_tokens 
                WHERE token = ? AND token_type = 'email_verification' AND used_at IS NULL
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            if (strtotime($result['expires_at']) < time()) {
                return false;
            }
            
            // Mark token as used
            $stmt = $this->db->prepare("UPDATE verification_tokens SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            // Update user's email verification status
            $stmt = $this->db->prepare("UPDATE users SET email_verified = TRUE WHERE id = ?");
            $stmt->execute([$result['user_id']]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Token verification failed: " . $e->getMessage());
            return false;
        }
    }

    public function verifyPasswordResetCode($code) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id, expires_at, used_at 
                FROM verification_tokens 
                WHERE token = ? AND token_type = 'password_reset' AND used_at IS NULL
            ");
            $stmt->execute([$code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            if (strtotime($result['expires_at']) < time()) {
                return false;
            }
            
            // Mark token as used
            $stmt = $this->db->prepare("UPDATE verification_tokens SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$code]);
            
            return $result['user_id'];
        } catch (PDOException $e) {
            error_log("Code verification failed: " . $e->getMessage());
            return false;
        }
    }
} 