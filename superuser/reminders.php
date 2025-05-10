<?php
session_start();

// Check if user is logged in and is a superuser
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_user') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/dbconnection.php';

// Initialize variables
$errors = [];
$success_message = '';
$user_id = $_SESSION['user_id'];

// Get reminders for the superuser
try {
    // Get events with deadlines
    $stmt = $dbh->prepare("
        SELECT 
            e.*,
            DATEDIFF(e.event_date, CURDATE()) as days_remaining,
            TIMESTAMPDIFF(HOUR, NOW(), e.event_date) as hours_remaining
        FROM events e
        WHERE e.created_by = ?
        AND e.event_date >= NOW()
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$user_id]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Reminders error: " . $e->getMessage());
    $errors[] = "Error fetching reminders";
    $reminders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminders - EventPro Superuser</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reminders-container {
            padding: 20px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--white);
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .reminders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .reminder-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .reminder-card.urgent {
            border-left: 4px solid var(--danger-color);
        }

        .reminder-card.warning {
            border-left: 4px solid var(--warning-color);
        }

        .reminder-card.normal {
            border-left: 4px solid var(--info-color);
        }

        .reminder-icon {
            font-size: 24px;
            color: var(--primary-color);
        }

        .reminder-content {
            flex: 1;
        }

        .reminder-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .reminder-description {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .reminder-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.875rem;
        }

        .reminder-time {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-muted);
        }

        .reminder-time i {
            color: var(--warning-color);
        }

        .reminder-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-urgent {
            background-color: var(--danger-color);
            color: white;
        }

        .status-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .status-normal {
            background-color: var(--info-color);
            color: white;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: var(--white);
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .toast i {
            font-size: 20px;
        }

        .toast.urgent i {
            color: var(--danger-color);
        }

        .toast.warning i {
            color: var(--warning-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .reminders-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filter-btn {
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
                <h1>Reminders & Upcoming Deadlines</h1>
            </div>

            <!-- Filters -->
            <div class="filters">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="urgent">Due Soon</button>
                <button class="filter-btn" data-filter="upcoming">Upcoming</button>
            </div>

            <!-- Reminders Grid -->
            <div class="reminders-container">
                <div class="reminders-grid">
                    <?php foreach ($reminders as $reminder): 
                        $isUrgent = $reminder['hours_remaining'] <= 12;
                        $isWarning = $reminder['hours_remaining'] <= 24;
                        $cardClass = $isUrgent ? 'urgent' : ($isWarning ? 'warning' : 'normal');
                        $statusClass = $isUrgent ? 'status-urgent' : ($isWarning ? 'status-warning' : 'status-normal');
                    ?>
                        <div class="reminder-card <?php echo $cardClass; ?>" data-hours="<?php echo $reminder['hours_remaining']; ?>">
                            <div class="reminder-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="reminder-content">
                                <h3 class="reminder-title"><?php echo htmlspecialchars($reminder['title']); ?></h3>
                                <p class="reminder-description"><?php echo htmlspecialchars($reminder['description']); ?></p>
                                <div class="reminder-meta">
                                    <div class="reminder-time">
                                        <i class="fas fa-clock"></i>
                                        <?php if ($reminder['hours_remaining'] < 24): ?>
                                            Due in <?php echo $reminder['hours_remaining']; ?> hours
                                        <?php else: ?>
                                            Due in <?php echo $reminder['days_remaining']; ?> days
                                        <?php endif; ?>
                                    </div>
                                    <span class="reminder-status <?php echo $statusClass; ?>">
                                        <?php echo $isUrgent ? 'Urgent' : ($isWarning ? 'Warning' : 'Upcoming'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Filter functionality
            $('.filter-btn').on('click', function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                
                const filter = $(this).data('filter');
                $('.reminder-card').hide();
                
                if (filter === 'all') {
                    $('.reminder-card').show();
                } else if (filter === 'urgent') {
                    $('.reminder-card[data-hours]').each(function() {
                        if (parseInt($(this).data('hours')) <= 12) {
                            $(this).show();
                        }
                    });
                } else if (filter === 'upcoming') {
                    $('.reminder-card[data-hours]').each(function() {
                        if (parseInt($(this).data('hours')) > 12) {
                            $(this).show();
                        }
                    });
                }
            });

            // Check for urgent reminders every minute
            setInterval(function() {
                $('.reminder-card[data-hours]').each(function() {
                    const hours = parseInt($(this).data('hours'));
                    if (hours <= 1) {
                        showToast($(this).find('.reminder-title').text(), 'urgent');
                    } else if (hours <= 6) {
                        showToast($(this).find('.reminder-title').text(), 'warning');
                    }
                });
            }, 60000);

            // Toast notification function
            function showToast(message, type) {
                const toast = $('<div>')
                    .addClass('toast')
                    .addClass(type)
                    .html(`
                        <i class="fas fa-bell"></i>
                        <div>
                            <strong>${message}</strong>
                            <p>${type === 'urgent' ? 'Due within an hour!' : 'Due within 6 hours!'}</p>
                        </div>
                    `);
                
                $('.toast-container').append(toast);
                
                setTimeout(function() {
                    toast.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html> 