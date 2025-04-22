<?php
// Database configuration
$dbHost = 'localhost';
$dbName = 'event_management';
$dbUser = 'root';
$dbPass = '';

try {
    $dbh = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to log user actions
function logUserAction($action, $details) {
    global $dbh;
    try {
        $sql = "INSERT INTO user_logs (user_id, action, details, created_at) VALUES (:user_id, :action, :details, NOW())";
        $query = $dbh->prepare($sql);
        $query->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $query->bindParam(':action', $action, PDO::PARAM_STR);
        $query->bindParam(':details', $details, PDO::PARAM_STR);
        $query->execute();
    } catch(PDOException $e) {
        error_log("Error logging user action: " . $e->getMessage());
    }
}
?>
