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

// Get announcements from the database
try {
    // First, let's check if we have any announcements at all
    $check_stmt = $dbh->query("SELECT COUNT(*) as count FROM announcements");
    $announcement_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Debug: Log the count
    error_log("Total announcements in database: " . $announcement_count);

    // Get announcements with more detailed information
    $stmt = $dbh->prepare("
        SELECT 
            a.*,
            CASE 
                WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1
                ELSE 0
            END as is_new
        FROM announcements a
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log the fetched announcements
    error_log("Fetched announcements count: " . count($announcements));
    if (count($announcements) > 0) {
        error_log("First announcement details: " . print_r($announcements[0], true));
    }

} catch (PDOException $e) {
    error_log("Announcements error: " . $e->getMessage());
    $errors[] = "Error fetching announcements: " . $e->getMessage();
    $announcements = [];
}

// Debug: Check if we have any errors
if (!empty($errors)) {
    error_log("Errors occurred: " . implode(", ", $errors));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - EventPro Superuser</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .announcements-container {
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .refresh-btn {
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: var(--primary-dark);
        }

        .announcements-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .announcement-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .announcement-card.new {
            border-left: 4px solid var(--primary-color);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .announcement-title {
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            font-size: 1.2rem;
        }

        .announcement-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .announcement-date {
            white-space: nowrap;
        }

        .announcement-author {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .announcement-author i {
            color: var(--primary-color);
        }

        .announcement-content {
            color: var(--text-color);
            line-height: 1.6;
            font-size: 1rem;
            margin-top: 10px;
        }

        .new-badge {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .no-announcements {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        .no-announcements i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--border-color);
        }

        @media (max-width: 768px) {
            .announcements-grid {
                padding: 0 15px;
            }

            .search-container {
                flex-direction: column;
                padding: 0 15px;
            }

            .refresh-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .debug-info h4 {
            margin-top: 0;
            color: #495057;
        }

        .debug-info pre {
            background: white;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
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
                    <h1>Admin Announcements</h1>
                    <p class="page-subtitle">Latest updates from the Admin team</p>
                </div>
                <button id="theme-toggle" class="theme-toggle">
                    <i class="fas fa-moon"></i>
                    <span>Dark Mode</span>
                </button>
            </div>

            <!-- Debug Information (only show if there are errors) -->
            <?php if (!empty($errors)): ?>
                <div class="debug-info">
                    <h4>Debug Information</h4>
                    <p>Total announcements in database: <?php echo $announcement_count; ?></p>
                    <p>Fetched announcements count: <?php echo count($announcements); ?></p>
                    <?php if (count($announcements) > 0): ?>
                        <p>First announcement details:</p>
                        <pre><?php print_r($announcements[0]); ?></pre>
                    <?php endif; ?>
                    <p>Errors:</p>
                    <pre><?php print_r($errors); ?></pre>
                </div>
            <?php endif; ?>

            <!-- Search and Refresh -->
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search announcements..." id="searchInput">
                <button class="refresh-btn" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>
            </div>

            <!-- Announcements Grid -->
            <div class="announcements-container">
                <?php if (empty($announcements)): ?>
                    <div class="no-announcements">
                        <i class="fas fa-bullhorn"></i>
                        <h3>No Announcements Yet</h3>
                        <p>There are no announcements to display at this time.</p>
                        <?php if ($announcement_count > 0): ?>
                            <p class="text-muted">Note: There are <?php echo $announcement_count; ?> announcements in the database, but none are visible. This might be due to visibility settings or permissions.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="announcements-grid">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-card <?php echo $announcement['is_new'] ? 'new' : ''; ?>" 
                                 data-title="<?php echo htmlspecialchars($announcement['description']); ?>"
                                 data-content="<?php echo htmlspecialchars($announcement['description']); ?>">
                                <div class="announcement-header">
                                    <h3 class="announcement-title">Announcement #<?php echo $announcement['id']; ?></h3>
                                    <div class="announcement-meta">
                                        <?php if ($announcement['is_new']): ?>
                                            <span class="new-badge">New</span>
                                        <?php endif; ?>
                                        <span class="announcement-date">
                                            <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="announcement-content">
                                    <?php echo nl2br(htmlspecialchars($announcement['description'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Search functionality
            $('#searchInput').on('keyup', function() {
                const searchText = $(this).val().toLowerCase();
                $('.announcement-card').each(function() {
                    const title = $(this).data('title').toLowerCase();
                    const content = $(this).data('content').toLowerCase();
                    const matches = title.includes(searchText) || content.includes(searchText);
                    $(this).toggle(matches);
                });
            });

            // Refresh functionality
            $('#refreshBtn').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true);
                btn.find('i').addClass('fa-spin');

                // Simulate refresh (replace with actual AJAX call)
                setTimeout(function() {
                    location.reload();
                }, 1000);
            });

            // Auto-refresh every 5 minutes
            setInterval(function() {
                location.reload();
            }, 300000);
        });
    </script>
</body>
</html> 