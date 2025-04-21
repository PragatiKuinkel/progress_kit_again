<?php
global $dbh;
session_start();
require_once '../includes/dbconnection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please login again.'
    ]);
    exit();
}

$lastId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

try {
    $stmt = $dbh->prepare("
        SELECT m.*, u.full_name, u.role as sender_role
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.id > ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the time for each message
    foreach ($messages as &$message) {
        $message['formatted_time'] = date('g:i A, M j', strtotime($message['created_at']));
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
} catch (PDOException $e) {
    error_log("Error checking new messages: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check for new messages.',
        'error' => $e->getMessage()
    ]);
} 