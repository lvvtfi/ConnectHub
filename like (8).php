<?php
session_start();
include 'includes/db.php';

// Debug
error_log("Like request received");

// Set header untuk response JSON
header('Content-Type: application/json');

// Ambil data
$postId = $_GET['id'] ?? null;
$userIdentifier = $_COOKIE['user_identifier'] ?? '';

// Debug
error_log("Post ID: $postId, User: $userIdentifier");

// Validasi input
if (!$postId || !filter_var($postId, FILTER_VALIDATE_INT)) {
    error_log("Invalid input: post_id=$postId");
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Check existing interaction
    $checkStmt = $pdo->prepare("SELECT action FROM interactions WHERE post_id = ? AND user_identifier = ?");
    $checkStmt->execute([$postId, $userIdentifier]);
    $existingAction = $checkStmt->fetchColumn();

    error_log("Existing action: " . ($existingAction ?: 'none'));

    if ($existingAction === 'like') {
        // Remove like
        $stmt = $pdo->prepare("DELETE FROM interactions WHERE post_id = ? AND user_identifier = ?");
        $stmt->execute([$postId, $userIdentifier]);
        $newAction = null;
    } else {
        // Remove any existing interaction first
        if ($existingAction) {
            $stmt = $pdo->prepare("DELETE FROM interactions WHERE post_id = ? AND user_identifier = ?");
            $stmt->execute([$postId, $userIdentifier]);
        }
        
        // Add new like
        $stmt = $pdo->prepare("INSERT INTO interactions (post_id, user_identifier, action) VALUES (?, ?, 'like')");
        $stmt->execute([$postId, $userIdentifier]);
        $newAction = 'like';
    }

    // Get updated counts
    $countStmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN action = 'like' THEN 1 END) as likes,
            COUNT(CASE WHEN action = 'dislike' THEN 1 END) as dislikes
        FROM interactions 
        WHERE post_id = ?
    ");
    $countStmt->execute([$postId]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'likes' => (int)$counts['likes'],
        'dislikes' => (int)$counts['dislikes'],
        'userAction' => $newAction
    ];

    error_log("Sending response: " . json_encode($response));
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
?>