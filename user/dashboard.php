<?php
session_start();

// Check if user is logged in and is a superuser
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/dbconnection.php';

// Initialize variables
$errors = [];
$success_message = '';
$user_id = $_SESSION['user_id'];
$events = []; // Initialize events array

// Get events with user information
try {
    $stmt = $dbh->prepare("
        SELECT 
            e.*,
            u.full_name as created_by_name
        FROM events e
        LEFT JOIN users u ON e.user_id = u.id
        ORDER BY e.start_date DESC
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $errors[] = "Error fetching events data";
    $events = []; // Ensure events is an empty array even on error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - EventPro Superuser</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-responsive {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 20px;
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1rem;
        }

        .table th {
            background-color: var(--light-bg);
            color: var(--text-color);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
            border-radius: 4px;
        }

        .btn-info {
            background-color: var(--info-color);
            color: white;
            border: none;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
            border: none;
        }

        .btn-info:hover, .btn-danger:hover {
            opacity: 0.9;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-upcoming {
            background-color: var(--info-color);
            color: white;
        }

        .status-ongoing {
            background-color: var(--warning-color);
            color: white;
        }

        .status-completed {
            background-color: var(--success-color);
            color: white;
        }

        .status-cancelled {
            background-color: var(--danger-color);
            color: white;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin: 0 20px 20px;
        }

        .form-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--white);
            color: var(--text-color);
        }

        .no-events {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .table-responsive {
                padding: 10px;
            }
            
            .table th,
            .table td {
                padding: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }

            .content-header {
                flex-direction: column;
                gap: 10px;
            }

            .filters {
                flex-direction: column;
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
                <div class="page-header">
                    <h1>Dashboard</h1>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <select id="statusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select id="organizerFilter" class="form-select">
                    <option value="">All Organizers</option>
                    <?php
                    // Fetch unique organizers
                    $organizers = array_unique(array_column($events, 'created_by_name'));
                    foreach ($organizers as $organizer) {
                        if ($organizer) {
                            echo "<option value='$organizer'>$organizer</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- Events Table -->
            <div class="table-responsive">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($events)): ?>
                    <div class="no-events">
                        <i class="fas fa-calendar-times fa-3x"></i>
                        <h3>No Events Found</h3>
                        <p>There are no events in the system yet.</p>
                    </div>
                <?php else: ?>
                    <table id="eventsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Description</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    <td><?php echo htmlspecialchars($event['description']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($event['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($event['end_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                                    <td>
                                        <?php if ($event['status'] === 'active'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php elseif ($event['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($event['created_by_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#eventsTable').DataTable({
                responsive: true,
                order: [[2, 'desc']] // Sort by date by default
            });

            // Status filter
            $('#statusFilter').on('change', function() {
                table.column(5).search(this.value).draw();
            });

            // Organizer filter
            $('#organizerFilter').on('change', function() {
                table.column(6).search(this.value).draw();
            });
        });
    </script>
</body>
</html> 