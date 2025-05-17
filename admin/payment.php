<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../includes/dbconnection.php';

// Initialize variables
$bill = null;
$vendor = null;
$error = null;
$success = null;

// Get bill ID from URL
$billId = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

try {
    // Get bill and vendor information
    $stmt = $dbh->prepare("
        SELECT b.*, u.full_name as vendor_name
        FROM bills b
        JOIN users u ON b.vendor_id = u.id
        WHERE b.id = :id AND b.status = 'accepted'
    ");
    $stmt->bindParam(':id', $billId, PDO::PARAM_INT);
    $stmt->execute();
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        throw new Exception("Bill not found or not accepted");
    }

} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());
    $error = $e->getMessage();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        if (empty($_POST['payment_reference'])) {
            throw new Exception("Payment reference number is required");
        }

        // For now, just show success message
        // In the future, this would integrate with Stride payment gateway
        $success = "Payment submitted successfully!";
        
        // In the future, you would:
        // 1. Update bill status to 'paid'
        // 2. Record payment details
        // 3. Send notification to vendor
        // 4. Generate receipt

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - EventPro</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="light-mode">
    <!-- Sidebar -->
    <div class="sidebar">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <div class="header-left">
                <h1>Make Payment</h1>
            </div>
            <div class="header-right">
                <a href="view_bills.php?vendor_id=<?php echo $bill['vendor_id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Bills
                </a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <div class="content-body">
            <div class="payment-form-container">
                <form action="payment.php?bill_id=<?php echo $billId; ?>" method="POST" class="payment-form">
                    <div class="form-group">
                        <label for="bill_id">Bill ID</label>
                        <input type="text" id="bill_id" value="<?php echo htmlspecialchars($bill['id']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="vendor_name">Vendor Name</label>
                        <input type="text" id="vendor_name" value="<?php echo htmlspecialchars($bill['vendor_name']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="text" id="amount" value="$<?php echo htmlspecialchars($bill['amount']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="stride">Stride</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_reference">Payment Reference Number</label>
                        <input type="text" name="payment_reference" id="payment_reference" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-money-bill"></i> Submit Payment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <style>
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header-right {
            display: flex;
            align-items: center;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--secondary-color);
            color: var(--text-color);
            border: none;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn-secondary:hover {
            background: var(--secondary-hover);
        }

        .payment-form-container {
            max-width: 600px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .payment-form {
            display: grid;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--input-bg);
            color: var(--text-color);
        }

        .form-group input[readonly] {
            background: var(--card-bg);
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        @media (max-width: 768px) {
            .header-left {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .payment-form-container {
                padding: 15px;
            }
        }
    </style>
</body>
</html> 