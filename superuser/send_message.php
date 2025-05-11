<?php
global $dbh;
session_start();
require_once '../includes/dbconnection.php';

if ($_POST) {
    $message = $_POST['message'] ?? '';

    if (!empty($message) && isset($_SESSION['user_id'])) {
        try {
            // Set the role to 'superuser' for superuser messages
            $stmt = $dbh->prepare("INSERT INTO messages (sender_id, sender_role, sender_name, message, created_at) VALUES (?, 'superuser', ?, ?, NOW())");
            $stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['full_name'],
                $message
            ]);
            
            // Fetch the inserted message with full details for immediate display
            $messageId = $dbh->lastInsertId();
            $stmt = $dbh->prepare("
                SELECT m.*, u.full_name, u.role as sender_role
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$messageId]);
            $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => $newMessage,
                'formatted_time' => date('g:i A, M j', strtotime($newMessage['created_at']))
            ]);
            exit();
        } catch (PDOException $e) {
            error_log("Error sending message: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send message. Please try again.',
                'error' => $e->getMessage()
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => empty($message) ? 'Message cannot be empty.' : 'Session expired. Please login again.'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
} 