<?php
// comment_test.php - For debugging the comment system
session_start();
include 'includes/db.php';

// Set user identifier if not exists
if (!isset($_COOKIE['user_identifier'])) {
    $identifier = md5(uniqid());
    setcookie('user_identifier', $identifier, time() + (86400 * 30), "/");
    $_COOKIE['user_identifier'] = $identifier;
}

// Get a post ID to test with
try {
    $stmt = $pdo->query("SELECT id FROM posts ORDER BY id DESC LIMIT 1");
    $testPostId = $stmt->fetchColumn();
} catch (PDOException $e) {
    $testPostId = 1; // Fallback
}

// Check database tables
function checkDatabaseTables($pdo) {
    $results = [];
    
    // Check comments table
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE comments");
        $results['comments_table'] = "Exists";
        $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['table_structure'] = $tableInfo['Create Table'] ?? 'Unknown';
    } catch (PDOException $e) {
        $results['comments_table'] = "Error: " . $e->getMessage();
    }
    
    // Check for any existing comments
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM comments");
        $results['comment_count'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $results['comment_count'] = "Error: " . $e->getMessage();
    }
    
    return $results;
}

$dbStatus = checkDatabaseTables($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment System Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        .comment-item { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <h1>Comment System Test</h1>
        <p>This page helps diagnose issues with the comment system.</p>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Database Status</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Comments Table
                                <span class="badge <?= $dbStatus['comments_table'] === 'Exists' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $dbStatus['comments_table'] ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Existing Comments
                                <span class="badge bg-info"><?= $dbStatus['comment_count'] ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Test Comment Submission</h5>
                    </div>
                    <div class="card-body">
                        <form id="test-form">
                            <input type="hidden" id="post-id" value="<?= $testPostId ?>">
                            <div class="mb-3">
                                <label for="content" class="form-label">Comment</label>
                                <textarea id="content" class="form-control" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" id="submit-btn">Submit Comment</button>
                        </form>
                        
                        <div class="mt-4" id="result-section" style="display: none;">
                            <h5>Result:</h5>
                            <pre id="result-content"></pre>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Comments (Post ID: <?= $testPostId ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div id="comments-container">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('test-form');
            const resultSection = document.getElementById('result-section');
            const resultContent = document.getElementById('result-content');
            const commentsContainer = document.getElementById('comments-container');
            const postId = document.getElementById('post-id').value;
            
            // Load existing comments
            loadComments();
            
            // Handle form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const content = document.getElementById('content').value;
                if (!content.trim()) return;
                
                const submitBtn = document.getElementById('submit-btn');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Submitting...';
                
                // Create form data
                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', content);
                
                // Send the request
                fetch('add_comment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    // Show result
                    resultSection.style.display = 'block';
                    resultContent.textContent = JSON.stringify(data, null, 2);
                    
                    // Reset form
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    
                    if (data.success) {
                        // Clear input
                        document.getElementById('content').value = '';
                        // Reload comments
                        loadComments();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    
                    // Show error
                    resultSection.style.display = 'block';
                    resultContent.textContent = 'JavaScript Error: ' + error.message;
                    
                    // Reset button
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            });
            
            // Function to load comments
            function loadComments() {
                commentsContainer.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;
                
                fetch(`get_comments.php?post_id=${postId}&page=1`)
                .then(response => response.json())
                .then(data => {
                    console.log('Comments data:', data);
                    
                    if (data.success) {
                        if (data.comments.length === 0) {
                            commentsContainer.innerHTML = '<div class="alert alert-info">No comments found for this post.</div>';
                        } else {
                            commentsContainer.innerHTML = '';
                            data.comments.forEach(comment => {
                                commentsContainer.appendChild(createCommentElement(comment));
                            });
                        }
                    } else {
                        commentsContainer.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load comments'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentsContainer.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                });
            }
            
            // Function to create comment element
            function createCommentElement(comment) {
                const div = document.createElement('div');
                div.className = 'comment-item';
                
                div.innerHTML = `
                    <h6>${comment.display_name || 'Anonymous'}</h6>
                    <div class="text-muted small mb-2">${comment.time_ago}</div>
                    <p>${comment.content}</p>
                `;
                
                return div;
            }
        });
    </script>
</body>
</html>
