<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/EmailVerification.php';

$emailVerification = new EmailVerification($dbh);
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
    } else {
        try {
            // Check if email exists
            $stmt = $dbh->prepare("SELECT id, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Send password reset code
                if ($emailVerification->sendPasswordResetCode($user['id'], $user['email'])) {
                    $message = 'A password reset code has been sent to your email.';
                    $success = true;
                } else {
                    $message = 'Failed to send reset code. Please try again later.';
                }
            } else {
                $message = 'No account found with this email address.';
            }
        } catch (PDOException $e) {
            error_log("Database error in forgot_password.php: " . $e->getMessage());
            $message = 'An error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Event Management System</title>
    <link rel="stylesheet" href="assets/css/forgot_password.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="forgot-password-container">
        <div class="forgot-password-box">
            <div class="forgot-password-header">
                <h2>Forgot Password</h2>
                <p>Enter your email address to receive a password reset code.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form action="" method="POST" class="forgot-password-form">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="Enter your email">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reset Code
                    </button>
                </form>
            <?php else: ?>
                <div class="reset-code-info">
                    <p>Please check your email for the reset code. The code will expire in 10 minutes.</p>
                    <a href="reset_password.php" class="btn btn-primary">
                        <i class="fas fa-key"></i> Enter Reset Code
                    </a>
                </div>
            <?php endif; ?>

            <div class="back-to-login">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html> 