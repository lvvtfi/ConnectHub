<?php
session_start();
include 'includes/db.php';

// Set header untuk response JSON
header('Content-Type: application/json');

// Ambil data
$postId = $_POST['post_id'] ?? null;
$content = $_POST['content'] ?? null;
$userIdentifier = $_COOKIE['user_identifier'] ?? '';
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Validasi input
if (!$postId || !filter_var($postId, FILTER_VALIDATE_INT) || !$content || !$userIdentifier) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Verify post exists to avoid foreign key constraint errors
    $checkStmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $checkStmt->execute([$postId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Post not found");
    }

    // Insert komentar baru
    $stmt = $pdo->prepare("
        INSERT INTO comments (post_id, user_id, user_identifier, content)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$postId, $userId, $userIdentifier, $content]);
    $commentId = $pdo->lastInsertId();
    
    // Ambil informasi tambahan untuk response
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.content, 
            c.created_at,
            u.username,
            u.display_name,
            u.profile_pic
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $response = [
        'success' => true,
        'comment' => [
            'id' => $comment['id'],
            'content' => $comment['content'],
            'created_at' => $comment['created_at'],
            'username' => $comment['username'] ?: 'Anonymous',
            'display_name' => $comment['display_name'] ?: $comment['username'] ?: 'Anonymous',
            'profile_pic' => $comment['profile_pic'] ?: null,
            'time_ago' => getTimeAgo($comment['created_at']),
            'can_delete' => true
        ]
    ];
    
    echo json_encode($response);

} catch (PDOException $e) {
    // Log the full error details for debugging
    error_log("Database error in add_comment.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Fungsi untuk menghitung waktu relatif (English phrases, Indonesian timezone)
function getTimeAgo($timestamp) {
    // Set timezone to Indonesia (WIB - UTC+7)
    date_default_timezone_set('Asia/Jakarta');
    
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    // Handle negative time differences (future dates or clock sync issues)
    if ($diff < 0) {
        return "just now";
    }
    
    if ($diff < 60) {
        return $diff == 1 ? "1 second ago" : "$diff seconds ago";
    } else if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    } else if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    } else if ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days == 1 ? "1 day ago" : "$days days ago";
    } else if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months == 1 ? "1 month ago" : "$months months ago";
    } else {
        $years = floor($diff / 31536000);
        return $years == 1 ? "1 year ago" : "$years years ago";
    }
}
?>