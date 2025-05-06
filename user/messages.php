<?php
global $dbh;
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/dbconnection.php';

// Fetch existing messages
try {
    $stmt = $dbh->prepare("
        SELECT m.*, u.full_name, u.role as sender_role
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Progress Kit</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Main Layout */
        body {
            height: 100vh;
            overflow: hidden;
            margin: 0;
            display: flex;
            background-color: var(--light-bg);
        }

        .sidebar {
            height: 100vh;
            overflow-y: auto;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* Chat Container */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--white);
            margin: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Messages Area */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column-reverse;
        }

        .message {
            max-width: 80%;
            margin-bottom: 16px;
            padding: 12px 16px;
            border-radius: 12px;
            background: var(--light-bg);
            position: relative;
        }

        .message.admin {
            background: var(--primary-color);
            color: white;
            margin-left: auto;
        }

        .message.user {
            background: var(--white);
            border: 1px solid var(--border-color);
            margin-right: auto;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 0.85em;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sender-name {
            font-weight: 600;
        }

        .sender-role {
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.1);
        }

        .message-time {
            color: var(--text-muted);
            font-size: 0.8em;
        }

        .message-content {
            word-break: break-word;
            line-height: 1.5;
        }

        /* Input Area */
        .input-area {
            padding: 16px;
            border-top: 1px solid var(--border-color);
            background: var(--white);
        }

        .message-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1em;
            resize: none;
            min-height: 24px;
            max-height: 120px;
            transition: border-color 0.3s;
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .send-button {
            padding: 12px 24px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .send-button:hover {
            background: var(--primary-hover);
        }

        /* Scrollbar Styling */
        .messages-area::-webkit-scrollbar {
            width: 6px;
        }

        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .messages-area::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        .messages-area::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .message {
                max-width: 90%;
            }

            .message-form {
                flex-direction: column;
            }

            .send-button {
                width: 100%;
                justify-content: center;
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
    <!-- Messages Content -->
    <div class="content-wrapper">
        <div class="content-header">
            <h1>Messages</h1>
        </div>

        <div class="chat-container">
            <div class="messages-area" id="messagesContainer">
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['sender_role'] === 'admin' ? 'admin' : 'user'; ?>">
                        <div class="message-header">
                            <div class="sender-info">
                                <span class="sender-name"><?php echo htmlspecialchars($message['full_name']); ?></span>
                                <span class="sender-role"><?php echo ucfirst($message['sender_role']); ?></span>
                            </div>
                            <span class="message-time"><?php echo date('g:i A, M j', strtotime($message['created_at'])); ?></span>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="input-area">
                <form id="messageForm" class="message-form">
                    <textarea 
                        class="message-input" 
                        name="message" 
                        placeholder="Type your message here..." 
                        rows="1"
                        required
                    ></textarea>
                    <button type="submit" class="send-button">
                        <i class="fas fa-paper-plane"></i>
                        Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    const messagesContainer = $('#messagesContainer');
    let lastMessageId = <?php echo !empty($messages) ? $messages[0]['id'] : 0; ?>;
    
    // Auto-resize textarea
    $('.message-input').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Handle message submission
    $('#messageForm').on('submit', function(e) {
        e.preventDefault();
        const messageInput = $(this).find('.message-input');
        const message = messageInput.val().trim();
        
        if (!message) return;
        
        $.ajax({
            url: 'send_message.php',
            method: 'POST',
            data: { message: message },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    appendMessage(response.message, response.formatted_time);
                    messageInput.val('').trigger('input');
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Failed to send message. Please try again.');
            }
        });
    });

    // Function to append a new message
    function appendMessage(message, formattedTime) {
        const messageHtml = `
            <div class="message ${message.sender_role === 'admin' ? 'admin' : 'user'}">
                <div class="message-header">
                    <div class="sender-info">
                        <span class="sender-name">${escapeHtml(message.full_name)}</span>
                        <span class="sender-role">${message.sender_role.charAt(0).toUpperCase() + message.sender_role.slice(1)}</span>
                    </div>
                    <span class="message-time">${formattedTime}</span>
                </div>
                <div class="message-content">
                    ${escapeHtml(message.message).replace(/\n/g, '<br>')}
                </div>
            </div>
        `;
        messagesContainer.prepend(messageHtml);
    }

    // Function to escape HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Poll for new messages
    function checkNewMessages() {
        $.ajax({
            url: 'check_new_messages.php',
            method: 'GET',
            data: { last_id: lastMessageId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.messages.length > 0) {
                    response.messages.forEach(function(message) {
                        appendMessage(message, message.formatted_time);
                    });
                    lastMessageId = response.messages[0].id;
                }
            }
        });
    }

    // Check for new messages every 5 seconds
    setInterval(checkNewMessages, 5000);
});
</script>
</body>
</html>