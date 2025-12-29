<?php
// messages.php - User messaging system
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

// Initialize variables
$success = '';
$error = '';
$selected_user_id = $_GET['user_id'] ?? 0;
$search_query = $_GET['search'] ?? '';
$unread_count = 0;

// Get current user data
$conn = getDBConnection();
$current_user_id = $_SESSION['user_id'];

// Get current user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// FIRST: Check what columns your messages table actually has
$check_table = $conn->query("DESCRIBE messages");
$message_columns = [];
$timestamp_column = null;

if ($check_table) {
    while ($row = $check_table->fetch_assoc()) {
        $message_columns[] = $row['Field'];
        
        // Check for timestamp columns
        if (in_array($row['Field'], ['created_at', 'timestamp', 'date_created', 'sent_at', 'date_sent', 'time'])) {
            $timestamp_column = $row['Field'];
        }
    }
    
    // If no timestamp column found, use the first available date/time column
    if (!$timestamp_column) {
        foreach ($message_columns as $col) {
            if (stripos($col, 'date') !== false || stripos($col, 'time') !== false) {
                $timestamp_column = $col;
                break;
            }
        }
    }
}

// If still no timestamp column, we'll use a default
if (!$timestamp_column) {
    $timestamp_column = 'created_at';
}

// Debug: Show what columns we found
error_log("Found columns: " . implode(', ', $message_columns));
error_log("Using timestamp column: " . $timestamp_column);

// Get unread messages count - check if is_read column exists
$unread_query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ?";
if (in_array('is_read', $message_columns)) {
    $unread_query .= " AND is_read = 0";
}
if (in_array('deleted_by_receiver', $message_columns)) {
    $unread_query .= " AND deleted_by_receiver = 0";
}

$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $current_user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'] ?? 0;

// Handle sending new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message']);
    $attachment_name = '';
    
    if (!empty($message) && $receiver_id > 0) {
        // Handle file upload if present
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($_FILES['attachment']['type'], $allowed_types) && 
                $_FILES['attachment']['size'] <= $max_size) {
                
                $upload_dir = 'uploads/messages/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $attachment_name = uniqid() . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $attachment_name;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                    // File uploaded successfully
                }
            }
        }
        
        // Build INSERT query dynamically based on available columns
        $insert_cols = ['sender_id', 'receiver_id', 'message'];
        $insert_vals = [$current_user_id, $receiver_id, $message];
        $placeholders = '?, ?, ?';
        
        if (in_array('attachment', $message_columns) && $attachment_name) {
            $insert_cols[] = 'attachment';
            $insert_vals[] = $attachment_name;
            $placeholders .= ', ?';
        }
        
        // Add timestamp if column exists
        if ($timestamp_column && in_array($timestamp_column, $message_columns)) {
            $insert_cols[] = $timestamp_column;
            $insert_vals[] = date('Y-m-d H:i:s');
            $placeholders .= ', ?';
        }
        
        $col_list = implode(', ', $insert_cols);
        $query = "INSERT INTO messages ($col_list) VALUES ($placeholders)";
        
        $insert_stmt = $conn->prepare($query);
        $types = str_repeat('s', count($insert_vals));
        $insert_stmt->bind_param($types, ...$insert_vals);
        
        if ($insert_stmt->execute()) {
            $success = "Message sent successfully!";
            $selected_user_id = $receiver_id;
        } else {
            $error = "Failed to send message. Please try again.";
        }
    } else {
        $error = "Please enter a message and select a recipient.";
    }
}

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id']);
    $delete_type = $_POST['delete_type'];
    
    if ($delete_type === 'sender') {
        $delete_stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
        $delete_stmt->bind_param("ii", $message_id, $current_user_id);
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND receiver_id = ?");
        $delete_stmt->bind_param("ii", $message_id, $current_user_id);
    }
    
    $delete_stmt->execute();
    $success = "Message deleted successfully!";
}

// Mark messages as read when viewing conversation
if ($selected_user_id > 0 && in_array('is_read', $message_columns)) {
    $mark_read_stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
    ");
    $mark_read_stmt->bind_param("ii", $current_user_id, $selected_user_id);
    $mark_read_stmt->execute();
}

// SIMPLIFIED: Get conversations with basic query
$conversations_query = "
    SELECT DISTINCT
        u.id as user_id,
        u.username,
        u.full_name,
        u.email
    FROM users u
    WHERE u.id != ?
    AND u.id IN (
        SELECT sender_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id FROM messages WHERE sender_id = ?
    )
    ORDER BY u.full_name
";

// Add search filter if provided
if (!empty($search_query)) {
    $conversations_query = "
        SELECT DISTINCT
            u.id as user_id,
            u.username,
            u.full_name,
            u.email
        FROM users u
        WHERE u.id != ?
        AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
        AND u.id IN (
            SELECT sender_id FROM messages WHERE receiver_id = ?
            UNION
            SELECT receiver_id FROM messages WHERE sender_id = ?
        )
        ORDER BY u.full_name
    ";
}

$conversations_stmt = $conn->prepare($conversations_query);

if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $conversations_stmt->bind_param("isssii", 
        $current_user_id, $search_param, $search_param, $search_param,
        $current_user_id, $current_user_id);
} else {
    $conversations_stmt->bind_param("iii", 
        $current_user_id, $current_user_id, $current_user_id);
}

$conversations_stmt->execute();
$conversations_result = $conversations_stmt->get_result();
$conversations = [];

while ($row = $conversations_result->fetch_assoc()) {
    // Get last message for each conversation
    $last_msg_query = "
        SELECT message, $timestamp_column as msg_time 
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY $timestamp_column DESC 
        LIMIT 1
    ";
    
    $last_msg_stmt = $conn->prepare($last_msg_query);
    $last_msg_stmt->bind_param("iiii", 
        $row['user_id'], $current_user_id, 
        $current_user_id, $row['user_id']);
    $last_msg_stmt->execute();
    $last_msg_result = $last_msg_stmt->get_result();
    $last_msg = $last_msg_result->fetch_assoc();
    
    $row['last_message_preview'] = $last_msg['message'] ?? 'No messages yet';
    $row['last_message_time'] = $last_msg['msg_time'] ?? null;
    
    // Get unread count
    $unread_query = "SELECT COUNT(*) as unread_count FROM messages WHERE sender_id = ? AND receiver_id = ?";
    if (in_array('is_read', $message_columns)) {
        $unread_query .= " AND is_read = 0";
    }
    
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("ii", $row['user_id'], $current_user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    $unread_row = $unread_result->fetch_assoc();
    $row['unread_count'] = $unread_row['unread_count'] ?? 0;
    
    $conversations[] = $row;
}

// Get all active users for new message (excluding current user)
$users_query = "SELECT id, username, full_name, email FROM users WHERE id != ? AND status = 'active'";
if (!empty($search_query)) {
    $users_query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
}

$users_query .= " ORDER BY full_name ASC";
$users_stmt = $conn->prepare($users_query);

if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $users_stmt->bind_param("isss", $current_user_id, $search_param, $search_param, $search_param);
} else {
    $users_stmt->bind_param("i", $current_user_id);
}

$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Get messages for selected conversation - SIMPLIFIED
$messages = [];
if ($selected_user_id > 0) {
    // Build the WHERE clause based on available columns
    $where_clause = "(sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
    
    $messages_query = "
        SELECT 
            m.*,
            s.username as sender_username,
            s.full_name as sender_full_name,
            r.username as receiver_username,
            r.full_name as receiver_full_name
        FROM messages m
        LEFT JOIN users s ON m.sender_id = s.id
        LEFT JOIN users r ON m.receiver_id = r.id
        WHERE $where_clause
        ORDER BY " . (in_array($timestamp_column, $message_columns) ? "m.$timestamp_column" : "m.id") . " ASC
    ";
    
    $messages_stmt = $conn->prepare($messages_query);
    $messages_stmt->bind_param("iiii", $current_user_id, $selected_user_id, $selected_user_id, $current_user_id);
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();
    $messages = $messages_result->fetch_all(MYSQLI_ASSOC);
}

// Close statements
$stmt->close();
$unread_stmt->close();
if (isset($conversations_stmt)) $conversations_stmt->close();
if (isset($users_stmt)) $users_stmt->close();
if (isset($messages_stmt)) $messages_stmt->close();
if (isset($insert_stmt)) $insert_stmt->close();
if (isset($delete_stmt)) $delete_stmt->close();
if (isset($mark_read_stmt)) $mark_read_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #764ba2;
            --sidebar-bg: #1a202c;
            --sidebar-hover: #2d3748;
            --card-bg: #ffffff;
            --text-light: #718096;
            --text-dark: #2d3748;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --danger-color: #f56565;
            --info-color: #4299e1;
            --message-sent-bg: #e3f2fd;
            --message-received-bg: #f5f5f5;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            color: var(--text-dark);
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 100;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 30px 25px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .avatar-small {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.4rem;
            color: white;
        }
        
        .user-info h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .user-info span {
            font-size: 0.85rem;
            color: var(--text-light);
            background: rgba(255, 255, 255, 0.1);
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .sidebar-menu {
            padding: 25px 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 25px;
            color: #cbd5e0;
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: var(--sidebar-hover);
            color: white;
            border-left-color: var(--primary-color);
        }
        
        .menu-item i {
            width: 20px;
            font-size: 1.2rem;
        }
        
        .menu-item span {
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--text-dark);
            font-size: 1.3rem;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .notification-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Messages Container */
        .messages-container {
            animation: slideUp 0.5s ease-out;
            display: flex;
            gap: 30px;
            height: calc(100vh - 180px);
        }
        
        /* Conversations Sidebar */
        .conversations-sidebar {
            width: 350px;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .conversations-header {
            padding: 25px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .conversations-header h2 {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 5px;
            text-decoration: none;
            color: inherit;
        }
        
        .conversation-item:hover {
            background: #f8fafc;
        }
        
        .conversation-item.active {
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-left: 4px solid var(--primary-color);
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            position: relative;
        }
        
        .online-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: var(--success-color);
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-info h4 {
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .conversation-time {
            font-size: 0.8rem;
            color: var(--text-light);
            white-space: nowrap;
        }
        
        .conversation-preview {
            font-size: 0.9rem;
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unread-badge {
            background: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            padding: 25px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .chat-user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .chat-user-details h3 {
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .chat-user-details p {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .chat-actions {
            display: flex;
            gap: 10px;
        }
        
        .chat-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: #f8fafc;
            color: var(--text-light);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-action-btn:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .messages-list {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
            background: #fafafa;
        }
        
        .message-item {
            display: flex;
            gap: 10px;
            max-width: 70%;
        }
        
        .message-item.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message-item.received {
            align-self: flex-start;
        }
        
        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .message-content {
            padding: 15px;
            border-radius: 15px;
            position: relative;
            min-width: 150px;
        }
        
        .message-item.sent .message-content {
            background: var(--message-sent-bg);
            border-bottom-right-radius: 5px;
        }
        
        .message-item.received .message-content {
            background: var(--message-received-bg);
            border-bottom-left-radius: 5px;
        }
        
        .message-text {
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--text-dark);
        }
        
        .message-time {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 8px;
            text-align: right;
        }
        
        .message-input-area {
            padding: 25px;
            border-top: 2px solid #e2e8f0;
        }
        
        .message-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .message-input-container {
            flex: 1;
            position: relative;
        }
        
        .message-input {
            width: 100%;
            min-height: 45px;
            max-height: 150px;
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 25px;
            font-size: 14px;
            resize: none;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Empty States */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 40px;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--text-light);
            margin-bottom: 20px;
        }
        
        .new-conversation-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .new-conversation-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
            <div class="user-profile">
                <div class="avatar-small">
                    <?php echo strtoupper(substr($current_user['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?></h4>
                    <span><?php echo htmlspecialchars($current_user['role'] ?? 'Member'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="profile.php" class="menu-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="users.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="analytics.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="messages.php" class="menu-item active">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
                <?php if ($unread_count > 0): ?>
                <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Messages</h1>
            <div class="header-actions">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </div>
        </div>
        
        <?php if (!empty($success)): ?>
        <div style="background: var(--success-color); color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; animation: fadeIn 0.5s;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div style="background: var(--danger-color); color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; animation: fadeIn 0.5s;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <!-- Messages Container -->
        <div class="messages-container">
            <!-- Conversations Sidebar -->
            <div class="conversations-sidebar">
                <div class="conversations-header">
                    <h2><i class="fas fa-comments"></i> Conversations</h2>
                    <form method="GET" class="search-container">
                        <input type="text" name="search" class="search-input" placeholder="Search conversations..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <i class="fas fa-search search-icon"></i>
                    </form>
                </div>
                
                <div class="conversations-list">
                    <?php if (!empty($conversations)): ?>
                        <?php foreach ($conversations as $conv): 
                            $is_active = $conv['user_id'] == $selected_user_id;
                            $last_message_time = $conv['last_message_time'] ? date('H:i', strtotime($conv['last_message_time'])) : '';
                        ?>
                        <a href="?user_id=<?php echo $conv['user_id']; ?>&search=<?php echo urlencode($search_query); ?>" class="conversation-item <?php echo $is_active ? 'active' : ''; ?>">
                            <div class="conversation-avatar">
                                <?php echo strtoupper(substr($conv['username'] ?? 'U', 0, 1)); ?>
                                <div class="online-indicator"></div>
                            </div>
                            <div class="conversation-info">
                                <h4>
                                    <?php echo htmlspecialchars($conv['full_name'] ?? $conv['username']); ?>
                                    <?php if ($conv['last_message_time']): ?>
                                    <span class="conversation-time"><?php echo date('H:i', strtotime($conv['last_message_time'])); ?></span>
                                    <?php endif; ?>
                                </h4>
                                <p class="conversation-preview">
                                    <?php 
                                    $preview = $conv['last_message_preview'] ?? 'No messages yet';
                                    echo strlen($preview) > 40 ? substr($preview, 0, 40) . '...' : $preview;
                                    ?>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>No Conversations</h3>
                        <p>Start a new conversation to send messages.</p>
                        <button class="new-conversation-btn" onclick="openNewMessageModal()">
                            <i class="fas fa-plus"></i> New Message
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 20px; border-top: 2px solid #e2e8f0;">
                    <button class="new-conversation-btn" onclick="openNewMessageModal()" style="width: 100%;">
                        <i class="fas fa-plus"></i> New Message
                    </button>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area">
                <?php if ($selected_user_id > 0 && isset($users[array_search($selected_user_id, array_column($users, 'id'))])): 
                    $selected_user = $users[array_search($selected_user_id, array_column($users, 'id'))];
                ?>
                <div class="chat-header">
                    <div class="chat-user-info">
                        <div class="chat-user-avatar">
                            <?php echo strtoupper(substr($selected_user['username'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div class="chat-user-details">
                            <h3><?php echo htmlspecialchars($selected_user['full_name'] ?? $selected_user['username']); ?></h3>
                            <p><?php echo htmlspecialchars($selected_user['email']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="messages-list" id="messagesList">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="message-item <?php echo $message['sender_id'] == $current_user_id ? 'sent' : 'received'; ?>">
                            <div class="message-avatar">
                                <?php echo strtoupper(substr($message['sender_id'] == $current_user_id ? 
                                    $current_user['username'] : $selected_user['username'], 0, 1)); ?>
                            </div>
                            <div class="message-content">
                                <div class="message-text">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </div>
                                <div class="message-time">
                                    <?php 
                                    // Try to get timestamp from available columns
                                    $timestamp = null;
                                    if (isset($message['created_at'])) $timestamp = $message['created_at'];
                                    elseif (isset($message['timestamp'])) $timestamp = $message['timestamp'];
                                    elseif (isset($message['date_created'])) $timestamp = $message['date_created'];
                                    elseif (isset($message['sent_at'])) $timestamp = $message['sent_at'];
                                    
                                    if ($timestamp) {
                                        echo date('H:i', strtotime($timestamp));
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-alt"></i>
                        <h3>No Messages Yet</h3>
                        <p>Start the conversation by sending a message.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="message-input-area">
                    <form method="POST" class="message-form" id="messageForm">
                        <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                        
                        <div class="message-input-container">
                            <textarea name="message" class="message-input" placeholder="Type your message here..." 
                                      rows="1" oninput="autoResize(this)" required></textarea>
                        </div>
                        
                        <button type="submit" name="send_message" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Select a Conversation</h3>
                    <p>Choose a conversation from the sidebar or start a new one.</p>
                    <button class="new-conversation-btn" onclick="openNewMessageModal()">
                        <i class="fas fa-plus"></i> Start New Conversation
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New Message Modal (Simplified) -->
    <div class="new-message-modal" id="newMessageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 15px; width: 90%; max-width: 500px; max-height: 90vh; overflow: hidden; box-shadow: var(--shadow);">
            <div style="padding: 25px; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.4rem; color: var(--text-dark);"><i class="fas fa-plus"></i> New Message</h3>
                <button onclick="closeNewMessageModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--text-light); cursor: pointer;">&times;</button>
            </div>
            <div style="padding: 25px; max-height: 60vh; overflow-y: auto;">
                <div class="search-container" style="margin-bottom: 20px;">
                    <input type="text" id="userSearch" class="search-input" placeholder="Search users..." onkeyup="filterUserList()">
                    <i class="fas fa-search search-icon"></i>
                </div>
                
                <div id="userList">
                    <?php foreach ($users as $user): ?>
                    <div class="user-list-item" onclick="selectUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>')" 
                         style="display: flex; align-items: center; gap: 15px; padding: 15px; border-radius: 10px; cursor: pointer; transition: var(--transition);">
                        <div class="conversation-avatar">
                            <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div class="conversation-info">
                            <h4><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h4>
                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (window.innerWidth <= 992) {
            menuToggle.style.display = 'block';
        }
        
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        // Auto-resize textarea
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
        }
        
        // New message modal functions
        function openNewMessageModal() {
            document.getElementById('newMessageModal').style.display = 'flex';
        }
        
        function closeNewMessageModal() {
            document.getElementById('newMessageModal').style.display = 'none';
        }
        
        function selectUser(userId, userName) {
            window.location.href = `messages.php?user_id=${userId}`;
        }
        
        function filterUserList() {
            const input = document.getElementById('userSearch').value.toLowerCase();
            const items = document.querySelectorAll('.user-list-item');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(input) ? 'flex' : 'none';
            });
        }
        
        // Scroll to bottom of messages
        function scrollToBottom() {
            const messagesList = document.getElementById('messagesList');
            if (messagesList) {
                messagesList.scrollTop = messagesList.scrollHeight;
            }
        }
        
        // Auto-scroll to bottom on page load
        window.addEventListener('load', scrollToBottom);
    </script>
</body>
</html>