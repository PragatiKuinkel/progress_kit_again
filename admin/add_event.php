<?php
global $dbh;
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
                event_name, description, start_date, end_date, location, status, is_public, user_id, assigned_users
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['event_name'],
            $_POST['description'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['location'],
            $_POST['status'],
            $_POST['is_public'] ?? 0,
            $_SESSION['user_id'],
            $assigned_users_csv
        ]);

        echo json_encode(['success' => true, 'message' => 'Event created successfully']);
    } catch (PDOException $e) {
        error_log("Error creating event: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error creating event', 'error' => $e->getMessage()]);
    }
    exit();
}

// Fetch users for the autocomplete dropdown
try {
    $stmt = $dbh->query("
        SELECT id, full_name, email, role
        FROM users
        WHERE role IN ('user', 'super_user')
        ORDER BY full_name
    ");
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
    <title>Add New Event - EventPro</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <style>
        .content-wrapper {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .content-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 30px;
        }

        .card-body {
            padding: 30px;
        }

        .form-section {
            background: var(--input-bg);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
            min-width: 250px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
        }

        .form-control-lg, .form-select-lg {
            font-size: 16px;
            padding: 12px 15px;
            width: 100%;
            min-width: 250px;
        }

        .invalid-feedback {
            font-size: 13px;
            margin-top: 5px;
        }

        .form-check {
            margin-top: 10px;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 0.2em;
        }

        .form-check-label {
            font-size: 15px;
            color: var(--text-color);
            margin-left: 8px;
        }

        .form-actions {
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 10px 20px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-lg {
            padding: 12px 24px;
            font-size: 16px;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary-color);
            border: none;
        }

        .btn-secondary:hover {
            background: var(--secondary-dark);
            transform: translateY(-1px);
        }

        /* Select2 Customization */
        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            min-height: 45px;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            padding: 5px 10px;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background: var(--primary-color);
            border: none;
            border-radius: 4px;
            color: white;
            padding: 2px 8px;
            margin: 2px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 15px;
            }

            .card-body {
                padding: 20px;
            }

            .form-section {
                padding: 20px;
            }

            .content-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        /* Ensure date inputs and text inputs have same width */
        input[type="text"],
        input[type="datetime-local"],
        .flatpickr-input {
            width: 100% !important;
            min-width: 250px !important;
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
                <h1><i class="fas fa-calendar-plus"></i> Add New Event</h1>
                <a href="events.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Events
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form id="addEventForm" class="needs-validation" novalidate>
                        <!-- Basic Information Section -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h3>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="event_name" class="form-label">Event Name *</label>
                                    <input type="text" class="form-control form-control-lg" id="event_name"
                                           name="event_name" required placeholder="Enter event name">
                                    <div class="invalid-feedback">Please enter the event name</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="event_status" class="form-label">Status *</label>
                                    <select class="form-select form-select-lg" id="event_status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="upcoming">Upcoming</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                    <div class="invalid-feedback">Please select the event status</div>
                                </div>

                                <div class="col-12">
                                    <label for="event_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="event_description" name="description"
                                              rows="4" placeholder="Enter event description"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time Section -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-clock"></i> Date & Time</h3>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="event_start_date" class="form-label">Start Date & Time *</label>
                                    <input type="datetime-local" class="form-control form-control-lg" id="event_start_date"
                                           name="start_date" required>
                                    <div class="invalid-feedback">Please select the start date and time</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="event_end_date" class="form-label">End Date & Time *</label>
                                    <input type="datetime-local" class="form-control form-control-lg" id="event_end_date"
                                           name="end_date" required>
                                    <div class="invalid-feedback">Please select the end date and time</div>
                                </div>
                            </div>
                        </div>

                        <!-- Location Section -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Location</h3>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label for="event_location" class="form-label">Location *</label>
                                    <input type="text" class="form-control form-control-lg" id="event_location"
                                           name="location" required placeholder="Enter event location">
                                    <div class="invalid-feedback">Please enter the event location</div>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Users Section -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-users"></i> Assigned Users</h3>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label for="event_assigned_users" class="form-label">Select Users to Assign</label>
                                    <select class="form-select form-select-lg" id="event_assigned_users"
                                            name="assigned_users[]" multiple>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                (<?php echo htmlspecialchars($user['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Is Public Section -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-eye"></i> Visibility</h3>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1">
                                <label class="form-check-label" for="is_public">Make this event public</label>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <div class="d-flex justify-content-end gap-3">
                                <a href="events.php" class="btn btn-lg btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-lg btn-primary">
                                    <i class="fas fa-plus"></i> Add Event
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 for user selection
            $('#event_assigned_users').select2({
                theme: 'bootstrap-5',
                placeholder: 'Search and select users',
                allowClear: true,
                width: '100%'
            });

            // Initialize Flatpickr for date inputs
            flatpickr("#event_start_date", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today"
            });

            flatpickr("#event_end_date", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today"
            });

            // Form validation
            const form = document.getElementById('addEventForm');
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html> 