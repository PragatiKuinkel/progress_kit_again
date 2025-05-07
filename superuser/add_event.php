<?php
global $dbh;
session_start();

// Check if user is logged in and is a superuser
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_user') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../includes/dbconnection.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Validate required fields
        $required_fields = ['event_name', 'start_date', 'end_date', 'location', 'status'];
        $errors = [];

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate dates
        if (strtotime($_POST['end_date']) < strtotime($_POST['start_date'])) {
            $errors[] = 'End date must be after start date';
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }

        $assigned_users_csv = isset($_POST['assigned_users']) ? implode(',', $_POST['assigned_users']) : null;

        $stmt = $dbh->prepare("
            INSERT INTO events (
                event_name, description, start_date, end_date, location, status, is_public, created_by, assigned_users
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['event_name'],
            $_POST['description'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['location'],
            $_POST['status'],
            isset($_POST['is_public']) ? 1 : 0,
            $_SESSION['user_id'],
            $assigned_users_csv
        ]);

        echo json_encode(['success' => true]);
        exit();

    } catch (PDOException $e) {
        error_log("Error adding event: " . $e->getMessage());
        echo json_encode(['success' => false, 'errors' => ['Database error occurred']]);
        exit();
    }
}

// Get users for assignment
try {
    $stmt = $dbh->query("SELECT id, full_name FROM users WHERE role != 'admin'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - EventPro Superuser</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <style>
        .form-container {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--white);
            color: var(--text-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .error-message {
            color: var(--danger-color);
            margin-top: 5px;
            font-size: 0.875rem;
        }

        .user-select {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px;
        }

        .user-option {
            display: block;
            padding: 5px 0;
        }

        .user-option input[type="checkbox"] {
            margin-right: 8px;
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
                <h1>Add New Event</h1>
            </div>

            <div class="form-container">
                <form id="addEventForm">
                    <div class="form-group">
                        <label for="event_name">Event Name</label>
                        <input type="text" id="event_name" name="event_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="datetime-local" id="start_date" name="start_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="datetime-local" id="end_date" name="end_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="upcoming">Upcoming</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_public" value="1">
                            Public Event
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="assigned_users">Assign Users</label>
                        <select class="form-control select2" id="assigned_users" name="assigned_users[]" multiple="multiple">
                            <?php
                            // Fetch non-admin users
                            $stmt = $dbh->prepare("SELECT id, full_name FROM users WHERE role != 'admin' ORDER BY full_name");
                            $stmt->execute();
                            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($users as $user) {
                                echo '<option value="' . $user['id'] . '">' . htmlspecialchars($user['full_name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            $('#addEventForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'add_event.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = 'dashboard.php';
                        } else {
                            alert(response.errors.join('\n'));
                        }
                    },
                    error: function() {
                        alert('An error occurred while saving the event.');
                    }
                });
            });

            // Initialize Select2
            $('.select2').select2({
                placeholder: 'Search and select users',
                allowClear: true,
                width: '100%'
            });
        });
    </script>
</body>
</html> 