<?php
session_start();
include('../includes/config.php');

if(!isset($_SESSION['user_id'])) {   
    header('location:../index.php');
    exit();
}

// Get user role
try {
    $sql = "SELECT role FROM event_management.users WHERE id = :id";
    $query = $dbh->prepare($sql);
    $query->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $query->execute();
    $userRole = $query->fetchColumn();
} catch (PDOException $e) {
    die("Error checking user role: " . $e->getMessage());
}

// Handle POST request for password update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    $userId = $_SESSION['user_id'];
    
    // If superuser is changing another user's password
    if ($userRole === 'super_user' && isset($_POST['target_user_id'])) {
        $userId = $_POST['target_user_id'];
        // Skip current password verification for superuser
        $skipCurrentPassword = true;
    } else {
        $skipCurrentPassword = false;
        $currentPassword = $_POST['currentPassword'];
    }

    try {
        if (!$skipCurrentPassword) {
            // Verify current password for regular users
            $sql = "SELECT password FROM event_management.users WHERE id = :id";
            $query = $dbh->prepare($sql);
            $query->bindParam(':id', $userId, PDO::PARAM_INT);
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                echo json_encode(['status' => 'error', 'message' => 'User not found']);
                exit();
            }

            if (!password_verify($currentPassword, $result['password'])) {
                echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
                exit();
            }
        }

        if ($newPassword !== $confirmPassword) {
            echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
            exit();
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            echo json_encode(['status' => 'error', 'message' => 'New password must be at least 8 characters long']);
            exit();
        }

        if (!preg_match('/[A-Z]/', $newPassword)) {
            echo json_encode(['status' => 'error', 'message' => 'New password must contain at least one uppercase letter']);
            exit();
        }

        if (!preg_match('/[a-z]/', $newPassword)) {
            echo json_encode(['status' => 'error', 'message' => 'New password must contain at least one lowercase letter']);
            exit();
        }

        if (!preg_match('/[0-9]/', $newPassword)) {
            echo json_encode(['status' => 'error', 'message' => 'New password must contain at least one number']);
            exit();
        }

        if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            echo json_encode(['status' => 'error', 'message' => 'New password must contain at least one special character']);
            exit();
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE event_management.users SET password = :password WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $query->bindParam(':id', $userId, PDO::PARAM_INT);
        
        if ($query->execute()) {
            // Log the password change
            try {
                $logSql = "INSERT INTO event_management.user_logs (user_id, action, details, created_at) VALUES (:user_id, :action, :details, NOW())";
                $logQuery = $dbh->prepare($logSql);
                
                if ($userRole === 'super_user') {
                    // For superuser changing another user's password
                    $logQuery->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $logQuery->bindValue(':action', 'superuser_password_change', PDO::PARAM_STR);
                    $logQuery->bindValue(':details', "Changed password for user ID: $userId", PDO::PARAM_STR);
                } else {
                    // For regular user changing their own password
                    $logQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $logQuery->bindValue(':action', 'password_change', PDO::PARAM_STR);
                    $logQuery->bindValue(':details', 'User changed their password', PDO::PARAM_STR);
                }
                
                $logQuery->execute();
            } catch (PDOException $e) {
                error_log("Error logging password change: " . $e->getMessage());
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update password']);
        }
        exit();
    } catch (PDOException $e) {
        error_log("Database error in change_password.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

// Get all users for superuser dropdown
$users = [];
if ($userRole === 'super_user') {
    try {
        $sql = "SELECT id, full_name, email FROM event_management.users WHERE role != 'super_user'";
        $query = $dbh->prepare($sql);
        $query->execute();
        $users = $query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .password-form {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.1);
        }

        .invalid-feedback {
            display: none;
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .was-validated .form-control:invalid ~ .invalid-feedback {
            display: block;
        }

        .form-actions {
            margin-top: 2rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .password-requirements {
            margin-top: 1rem;
            padding: 1rem;
            background-color: var(--light-bg);
            border-radius: 4px;
        }

        .password-requirements h4 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .password-requirements li {
            margin-bottom: 0.25rem;
            color: var(--text-muted);
        }

        .password-requirements li.valid {
            color: var(--success-color);
        }

        .password-requirements li.valid::before {
            content: "✓";
            margin-right: 0.5rem;
        }

        .user-select {
            margin-bottom: 1.5rem;
        }
        .user-select select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }
    </style>
</head>
<body class="light-mode">
    <!-- Sidebar -->
    <div class="sidebar">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <header class="top-nav">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            <div class="user-info">
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </div>
                <div class="user-profile">
                    <img src="../assets/images/admin-avatar.jpg" alt="User">
                    <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
            </div>
        </header>

        <!-- Password Change Content -->
        <div class="content-wrapper">
            <div class="content-header">
                <h1>Change Password</h1>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Settings
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form id="passwordForm" class="password-form needs-validation" novalidate>
                        <!-- Hidden username field for accessibility -->
                        <input type="text" name="username" autocomplete="username" style="display:none">
                        
                        <?php if ($userRole === 'super_user'): ?>
                        <div class="form-group user-select">
                            <label for="target_user_id" class="form-label">Select User *</label>
                            <select class="form-control" id="target_user_id" name="target_user_id" required>
                                <option value="">Select a user</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a user</div>
                        </div>
                        <?php endif; ?>

                        <?php if ($userRole !== 'super_user'): ?>
                        <div class="form-group">
                            <label for="currentPassword" class="form-label">Current Password *</label>
                            <input type="password" class="form-control" id="currentPassword" name="currentPassword" autocomplete="current-password" required>
                            <div class="invalid-feedback">Please enter your current password</div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="newPassword" class="form-label">New Password *</label>
                            <input type="password" class="form-control" id="newPassword" name="newPassword" autocomplete="new-password" required>
                            <div class="invalid-feedback">Please enter a new password</div>
                        </div>

                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">Confirm New Password *</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" autocomplete="new-password" required>
                            <div class="invalid-feedback">Please confirm your new password</div>
                        </div>

                        <div class="password-requirements">
                            <h4>Password Requirements:</h4>
                            <ul>
                                <li id="length">At least 8 characters long</li>
                                <li id="uppercase">Contains at least one uppercase letter</li>
                                <li id="lowercase">Contains at least one lowercase letter</li>
                                <li id="number">Contains at least one number</li>
                                <li id="special">Contains at least one special character</li>
                            </ul>
                        </div>

                        <div class="form-actions">
                            <a href="settings.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Password validation
            function validatePassword(password) {
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password)
                };

                Object.keys(requirements).forEach(key => {
                    const element = $(`#${key}`);
                    if (requirements[key]) {
                        element.addClass('valid');
                    } else {
                        element.removeClass('valid');
                    }
                });

                return Object.values(requirements).every(Boolean);
            }

            // Handle form submission
            $('#passwordForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    $(this).addClass('was-validated');
                    return;
                }

                const newPassword = $('#newPassword').val();
                const confirmPassword = $('#confirmPassword').val();

                if (!validatePassword(newPassword)) {
                    alert('Please ensure your new password meets all requirements');
                    return;
                }

                if (newPassword !== confirmPassword) {
                    alert('New passwords do not match');
                    return;
                }

                const formData = new FormData(this);
                
                $.ajax({
                    url: 'change_password.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            window.location.href = 'settings.php';
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        alert('Error: ' + (xhr.responseJSON?.message || 'Failed to change password'));
                    }
                });
            });

            // Real-time password validation
            $('#newPassword').on('input', function() {
                validatePassword($(this).val());
            });
        });
    </script>
</body>
</html> 