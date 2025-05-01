<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/dbconnection.php';

// Initialize variables
$errors = [];
$success_message = '';
$users = [];
$total_users = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    try {
        $stmt = $dbh->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$_POST['role'], $_POST['user_id']]);
        $success_message = "User role updated successfully";
    } catch (PDOException $e) {
        error_log("Update role error: " . $e->getMessage());
        $errors[] = "Error updating user role";
    }
}

// Add this at the top of the file after the session check
if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $userId = $_POST['user_id'];
    
    try {
        // Start transaction
        $dbh->beginTransaction();
        
        // Get user data before moving
        $sql = "SELECT * FROM users WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $userId, PDO::PARAM_INT);
        $query->execute();
        $userData = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            // Insert into deleted_users table
            $sql = "INSERT INTO deleted_users (user_id, full_name, email, phone, role, deleted_at) 
                    VALUES (:user_id, :full_name, :email, :phone, :role, NOW())";
            $query = $dbh->prepare($sql);
            $query->bindParam(':user_id', $userData['id'], PDO::PARAM_INT);
            $query->bindParam(':full_name', $userData['full_name'], PDO::PARAM_STR);
            $query->bindParam(':email', $userData['email'], PDO::PARAM_STR);
            $query->bindParam(':phone', $userData['phone'], PDO::PARAM_STR);
            $query->bindParam(':role', $userData['role'], PDO::PARAM_STR);
            $query->execute();
            
            // Delete from users table
            $sql = "DELETE FROM users WHERE id = :id";
            $query = $dbh->prepare($sql);
            $query->bindParam(':id', $userId, PDO::PARAM_INT);
            $query->execute();
            
            $dbh->commit();
            echo json_encode(['status' => 'success', 'message' => 'User moved to deleted users successfully']);
        } else {
            throw new Exception('User not found');
        }
    } catch (Exception $e) {
        $dbh->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Build query for user list
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total users count
$count_query = "SELECT COUNT(*) FROM (" . $query . ") as total";
$stmt = $dbh->prepare($count_query);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();

// Calculate pagination
$total_pages = ceil($total_users / $per_page);
$offset = ($current_page - 1) * $per_page;

// Get users with pagination
$query .= " ORDER BY created_at DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

try {
    $stmt = $dbh->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if roles are being fetched correctly
    error_log("Users fetched: " . print_r($users, true));
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $errors[] = "Error fetching users from database";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - EventPro Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .dataTables_wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .dataTables_wrapper .row {
            margin: 0;
            width: 100%;
        }

        .dataTables_wrapper .col-sm-12 {
            padding: 0;
        }

        .table {
            width: 100%;
            min-width: 800px;
            margin-bottom: 0;
        }

        .table th,
        .table td {
            white-space: nowrap;
            padding: 12px;
        }

        .role-form {
            display: flex;
            gap: 10px;
            align-items: center;
            min-width: 200px;
            white-space: nowrap;
        }
        
        .role-form select {
            width: 150px;
            padding: 4px 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 14px;
        }
        
        .role-form select option {
            padding: 4px 8px;
            white-space: nowrap;
        }
        
        .role-form button {
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .table td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .table th {
            white-space: nowrap;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            z-index: 1;
        }

        /* DataTables specific styles */
        .dataTables_length,
        .dataTables_filter {
            margin-bottom: 15px;
            white-space: nowrap;
        }

        .dataTables_info,
        .dataTables_paginate {
            margin-top: 15px;
            white-space: nowrap;
        }

        /* Ensure the select box expands to show full text */
        .form-select {
            width: 150px !important;
        }
        
        /* Make sure the dropdown shows full text */
        .form-select option {
            width: 150px !important;
        }

        /* Fix for DataTables wrapper */
        .dataTables_wrapper .row:first-child {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .dataTables_length {
            margin: 0;
            padding: 0;
        }

        .dataTables_filter {
            margin: 0;
            padding: 0;
        }

        .dataTables_length label,
        .dataTables_filter label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            padding: 5px 10px;
            background: var(--input-bg);
            border-radius: 4px;
        }

        .dataTables_length select {
            margin: 0 5px;
            padding: 4px 8px;
        }

        .dataTables_filter input {
            margin-left: 8px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* Ensure proper spacing for the table */
        .table-responsive {
            margin-top: 20px;
            padding: 0 15px;
        }

        /* Ensure pagination stays in one line */
        .pagination {
            flex-wrap: nowrap;
            margin: 0;
        }

        .page-link {
            white-space: nowrap;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dataTables_wrapper .row:first-child {
                flex-direction: column;
                gap: 10px;
            }

            .dataTables_length,
            .dataTables_filter {
                width: 100%;
            }
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
        <div class="content-wrapper">
            <div class="content-header">
                <h1>User Management</h1>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New User
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="table-responsive">
                <table id="usersTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th style="min-width: 200px;">Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                                <td>
                                    <form method="POST" class="role-form">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
                                        <select name="role" class="form-select">
                                            <option value="admin" <?php echo (isset($user['role']) && $user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            <option value="super_user" <?php echo (isset($user['role']) && $user['role'] === 'super_user') ? 'selected' : ''; ?>>Super User</option>
                                            <option value="user" <?php echo (isset($user['role']) && $user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-danger delete-user" data-id="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            const table = $('#usersTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[3, 'desc']], // Sort by role
                language: {
                    search: "Search users:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                    infoEmpty: "Showing 0 to 0 of 0 users",
                    infoFiltered: "(filtered from _MAX_ total users)"
                }
            });

            // Handle delete user button click
            $('.delete-user').click(function() {
                const userId = $(this).data('id');
                if (confirm('Are you sure you want to move this user to deleted users?')) {
                    $.ajax({
                        url: 'users.php',
                        method: 'POST',
                        data: {
                            action: 'delete_user',
                            user_id: userId
                        },
                        success: function(response) {
                            const data = JSON.parse(response);
                            if (data.status === 'success') {
                                alert(data.message);
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while processing your request.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 