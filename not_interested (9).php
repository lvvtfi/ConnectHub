<?php
include 'includes/db.php';
session_start();

// Get post ID from URL
$postId = $_GET['id'] ?? null;
$userIdentifier = $_COOKIE['user_identifier'] ?? '';

// Validate input
if (!$postId || !filter_var($postId, FILTER_VALIDATE_INT) || !$userIdentifier) {
    header("Location: index.php");
    exit;
}

try {
    // Insert into interactions table
    $stmt = $pdo->prepare("
        INSERT INTO interactions (post_id, user_identifier, action)
        VALUES (:postId, :userIdentifier, 'not_interested')
        ON DUPLICATE KEY UPDATE action = 'not_interested'
    ");
    
    $stmt->execute([
        'postId' => $postId,
        'userIdentifier' => $userIdentifier
    ]);

    // Add to session array for frontend filtering
    if (!isset($_SESSION['not_interested'])) {
        $_SESSION['not_interested'] = [];
    }
    if (!in_array($postId, $_SESSION['not_interested'])) {
        $_SESSION['not_interested'][] = $postId;
    }

    // Store timestamp
    if (!isset($_SESSION['not_interested_time'])) {
        $_SESSION['not_interested_time'] = [];
    }
    $_SESSION['not_interested_time'][$postId] = time();

} catch (PDOException $e) {
    error_log("Database error in not_interested.php: " . $e->getMessage());
}

// Redirect back to previous page or index
header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
exit;
?>