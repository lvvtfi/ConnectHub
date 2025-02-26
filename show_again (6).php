<?php
session_start();
include 'includes/db.php';

// Get post ID from URL
$postId = $_GET['id'] ?? null;
$userIdentifier = $_COOKIE['user_identifier'] ?? '';

// Validate input
if (!$postId || !filter_var($postId, FILTER_VALIDATE_INT) || !$userIdentifier) {
    header("Location: index.php");
    exit;
}

try {
    // Remove from interactions table
    $stmt = $pdo->prepare("
        DELETE FROM interactions 
        WHERE post_id = :postId 
        AND user_identifier = :userIdentifier 
        AND action = 'not_interested'
    ");
    
    $stmt->execute([
        'postId' => $postId,
        'userIdentifier' => $userIdentifier
    ]);

    // Remove from session array for frontend filtering
    if (isset($_SESSION['not_interested'])) {
        $key = array_search($postId, $_SESSION['not_interested']);
        if ($key !== false) {
            unset($_SESSION['not_interested'][$key]);
        }
    }

    // Remove timestamp
    if (isset($_SESSION['not_interested_time'][$postId])) {
        unset($_SESSION['not_interested_time'][$postId]);
    }

} catch (PDOException $e) {
    error_log("Database error in show_again.php: " . $e->getMessage());
}

// Redirect back to previous page or index
header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
exit;
?>