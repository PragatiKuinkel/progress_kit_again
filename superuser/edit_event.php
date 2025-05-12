<?php
session_start();

// Check if user is logged in and is an super_user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_user') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/dbconnection.php';

// Initialize variables
$errors = [];
$success_message = '';
$event = null;
$assigned_users = [];

// Get event ID from URL
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($event_id <= 0) {
    header('Location: events.php');
    exit();
}

// Fetch event details
try {
    $stmt = $dbh->prepare("
        SELECT e.*
        FROM events e
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        header('Location: events.php');
        exit();
    }

    // Debug: Log the event data
    error_log("Event ID being fetched: " . $event_id);
    error_log("Fetched event data: " . print_r($event, true));

} catch (PDOException $e) {
    error_log("Error fetching event details: " . $e->getMessage());
    $errors[] = "Error fetching event details: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $event_name = trim($_POST['event_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Validate form data
    if (empty($event_name)) {
        $errors[] = "Event name is required";
    }
    if (empty($start_date)) {
        $errors[] = "Start date is required";
    }
    if (empty($end_date)) {
        $errors[] = "End date is required";
    }
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    if (empty($status)) {
        $errors[] = "Status is required";
    }

    // Validate dates
    if (!empty($start_date) && !empty($end_date)) {
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        
        if ($start_timestamp === false || $end_timestamp === false) {
            $errors[] = "Invalid date format";
        } elseif ($end_timestamp < $start_timestamp) {
            $errors[] = "End date must be after start date";
        }
    }

    if (empty($errors)) {
        try {
            // Start transaction
            $dbh->beginTransaction();

            // Update event
            $stmt = $dbh->prepare("
                UPDATE events 
                SET event_name = ?, 
                    description = ?, 
                    start_date = ?, 
                    end_date = ?, 
                    location = ?, 
                    status = ?, 
                    is_public = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $event_name,
                $description,
                $start_date,
                $end_date,
                $location,
                $status,
                $is_public,
                $event_id
            ]);

            // Commit transaction
            $dbh->commit();
            
            $success_message = "Event updated successfully!";
            
            // Refresh event data
            $stmt = $dbh->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $dbh->rollBack();
            $errors[] = "Error updating event: " . $e->getMessage();
        }
    }
}

// Remove user selection since event_users table doesn't exist
$users = [];
$assigned_users = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Progress Kit</title>
    <link rel="stylesheet" href="../assets/css/superuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="datetime-local"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group.checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group.checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .user-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .user-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-option:hover {
            background: var(--light-bg);
        }

        .user-option input[type="checkbox"] {
            margin: 0;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--border-color);
            color: var(--text-color);
        }

        .btn-secondary:hover {
            background: var(--text-muted);
        }

        .error-message {
            color: var(--danger-color);
            margin-bottom: 15px;
        }

        .success-message {
            color: var(--success-color);
            margin-bottom: 15px;
        }

        .required-field::after {
            content: " *";
            color: var(--danger-color);
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
                <div class="page-header">
                    <h1>Edit Event</h1>
                </div>
            </div>

            <div class="form-container">
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="event_name" class="required-field">Event Name</label>
                        <input type="text" id="event_name" name="event_name" 
                               value="<?php echo htmlspecialchars($event['event_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="status" class="required-field">Status</label>
                        <select id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="upcoming" <?php echo ($event['status'] ?? '') === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="ongoing" <?php echo ($event['status'] ?? '') === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                            <option value="completed" <?php echo ($event['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($event['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="start_date" class="required-field">Start Date & Time</label>
                        <input type="datetime-local" id="start_date" name="start_date" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'] ?? '')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date" class="required-field">End Date & Time</label>
                        <input type="datetime-local" id="end_date" name="end_date" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_date'] ?? '')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="location" class="required-field">Location</label>
                        <input type="text" id="location" name="location" 
                               value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_public" name="is_public" 
                               <?php echo ($event['is_public'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="is_public">Make this event public</label>
                    </div>

                    <div class="form-actions">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Date validation
            $('#start_date, #end_date').on('change', function() {
                const startDate = new Date($('#start_date').val());
                const endDate = new Date($('#end_date').val());
                
                if (endDate < startDate) {
                    alert('End date must be after start date');
                    $(this).val('');
                }
            });
        });
    </script>
</body>
</html> 