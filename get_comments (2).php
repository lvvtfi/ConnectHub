<?php
session_start();
include 'includes/db.php';

// Set header untuk response JSON
header('Content-Type: application/json');

// Ambil data
$postId = $_GET['post_id'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Validasi input
if (!$postId || !filter_var($postId, FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Ambil total komentar untuk paginasi
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
    $countStmt->execute([$postId]);
    $totalComments = $countStmt->fetchColumn();
    
    // Ambil komentar dengan info user
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.content, 
            c.created_at,
            c.user_identifier,
            u.id as user_id,
            u.username,
            u.display_name,
            u.profile_pic
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    // Explicitly bind parameters with their types
    $stmt->bindParam(1, $postId, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format komentar untuk response
    $formattedComments = [];
    $userIdentifier = $_COOKIE['user_identifier'] ?? '';
    
    foreach ($comments as $comment) {
        $isOwner = $comment['user_identifier'] === $userIdentifier;
        $isPostOwner = false; // Implementasikan logika ini sesuai kebutuhan
        
        $formattedComments[] = [
            'id' => $comment['id'],
            'content' => $comment['content'],
            'created_at' => $comment['created_at'],
            'username' => $comment['username'] ?: 'Anonymous',
            'display_name' => $comment['display_name'] ?: $comment['username'] ?: 'Anonymous',
            'profile_pic' => $comment['profile_pic'] ?: null,
            'time_ago' => getTimeAgo($comment['created_at']),
            'can_delete' => $isOwner || $isPostOwner || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id'])
        ];
    }
    
    // Calculate pagination info
    $totalPages = ceil($totalComments / $limit);
    
    $response = [
        'success' => true,
        'comments' => $formattedComments,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_comments' => $totalComments,
            'has_more' => $page < $totalPages
        ]
    ];
    
    echo json_encode($response);

} catch (PDOException $e) {
    // Log the full error details for debugging
    error_log("Database error in get_comments.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
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