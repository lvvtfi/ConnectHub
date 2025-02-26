<?php
session_start();
include 'includes/db.php';
include 'includes/auth.php';

// Set header untuk response JSON
header('Content-Type: application/json');

// Ambil data
$commentId = $_POST['comment_id'] ?? null;
$userIdentifier = $_COOKIE['user_identifier'] ?? '';

// Validasi input
if (!$commentId || !filter_var($commentId, FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Cek apakah pengguna berhak menghapus komentar ini
    $stmt = $pdo->prepare("
        SELECT c.*, p.user_id as post_owner_id 
        FROM comments c
        JOIN posts p ON c.post_id = p.id
        WHERE c.id = ?
    ");
    
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        exit;
    }
    
    // Hanya pemilik komentar, pemilik post, atau admin yang bisa menghapus
    $canDelete = false;
    
    // Cek apakah pengguna adalah pemilik komentar
    if ($comment['user_identifier'] === $userIdentifier) {
        $canDelete = true;
    }
    // Cek apakah pengguna adalah pemilik post
    else if (isLoggedIn() && $_SESSION['user_id'] == $comment['post_owner_id']) {
        $canDelete = true;
    }
    
    if (!$canDelete) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this comment']);
        exit;
    }
    
    // Hapus komentar
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
?>