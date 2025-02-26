<?php
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/FileHandler.php';
include 'includes/ContentAnalyzer.php';

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$fileHandler = new FileHandler($pdo);
$contentAnalyzer = new ContentAnalyzer();
$error = null;
$success = null;
$assignedCategories = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging - cek apakah form terkirim dengan benar
    error_log("POST request received: " . json_encode($_POST));
    error_log("FILES array: " . json_encode($_FILES));
    
    $content = $_POST['content'] ?? '';
    $uploadedFile = $_FILES['file'] ?? null;

    try {
        $fileData = null;
        
        // Cek apakah ada file yang diupload
        if ($uploadedFile && isset($uploadedFile['name']) && !empty($uploadedFile['name'])) {
            error_log("Processing uploaded file: " . $uploadedFile['name']);
            
            try {
                $fileData = $fileHandler->handleFileUpload($uploadedFile);
                if ($fileData) {
                    error_log("File upload successful: " . json_encode($fileData));
                }
            } catch (Exception $e) {
                // Tangkap error file upload
                error_log("File upload error: " . $e->getMessage());
                $error = "File upload error: " . $e->getMessage();
                // Lanjutkan proses posting tanpa file
            }
        } else {
            error_log("No file uploaded or file entry is empty");
        }

        // Jika tidak ada error atau error hanya pada file, tetap buat post
        if ($error === null || strpos($error, "File upload error") === 0) {
            $stmt = $pdo->prepare("INSERT INTO posts (content, file_url, file_type, user_id) VALUES (:content, :file_url, :file_type, :user_id)");
            $stmt->execute([
                'content' => $content,
                'file_url' => $fileData ? $fileData['url'] : null,
                'file_type' => $fileData ? $fileData['type'] : null,
                'user_id' => $_SESSION['user_id']
            ]);
            
            // Get the new post ID
            $postId = $pdo->lastInsertId();
            error_log("New post created with ID: " . $postId);
            
            // Auto-categorize the post
            $assignedCategories = $contentAnalyzer->assignCategoriesToPost($pdo, $postId, $content);
            error_log("Categories assigned: " . implode(', ', $assignedCategories));
            
            $success = "Post created successfully" . 
                      (!empty($assignedCategories) ? 
                       " and categorized as: " . implode(', ', $assignedCategories) : 
                       " (No categories detected)");
            
            // Hanya redirect jika tidak ada error sama sekali
            if ($error === null) {
                header("Location: index.php");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Error creating post: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

// Get all available categories for manual selection (optional feature)
$allCategories = $contentAnalyzer->getAllCategories($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        .debug-info pre {
            margin: 0;
            padding: 10px;
            background-color: #f1f1f1;
            border-radius: 3px;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Create Post</h2>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <a href="index.php" class="btn btn-sm btn-primary ms-2">Go to Home</a>
            </div>
        <?php endif; ?>
        
        <form action="post.php" method="POST" enctype="multipart/form-data">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea name="content" id="content" class="form-control" 
                                placeholder="What's on your mind?" rows="5" required></textarea>
                        <small class="text-muted">Your post will be automatically categorized based on its content.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file" class="form-label">Upload File (Optional)</label>
                        <input type="file" name="file" id="file" class="form-control">
                        <small class="text-muted">Allowed files: Images (jpg, png, gif), Videos (mp4), Audio (mp3), Documents (pdf, doc, docx, txt)</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Post Now
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Upload Directory Status - For Debugging -->
        <div class="debug-info mt-4">
            <h5>Upload Directory Status</h5>
            <?php
            $uploadDir = 'uploads/';
            echo "<p>Upload directory: <code>$uploadDir</code></p>";
            
            if (file_exists($uploadDir)) {
                echo "<p class='text-success'>✓ Directory exists</p>";
                
                if (is_writable($uploadDir)) {
                    echo "<p class='text-success'>✓ Directory is writable</p>";
                } else {
                    echo "<p class='text-danger'>✗ Directory is not writable</p>";
                }
            } else {
                echo "<p class='text-danger'>✗ Directory does not exist</p>";
                
                // Try to create it
                if (mkdir($uploadDir, 0755, true)) {
                    echo "<p class='text-success'>✓ Directory created successfully</p>";
                } else {
                    echo "<p class='text-danger'>✗ Failed to create directory</p>";
                }
            }
            
            // Check PHP upload settings
            echo "<h5 class='mt-3'>PHP Upload Settings</h5>";
            echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
            echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
            echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
            
            // Check allowed file types
            echo "<h5 class='mt-3'>Allowed File Types</h5>";
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM allowed_file_types");
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    echo "<p>$count file types allowed in database</p>";
                    
                    $stmt = $pdo->query("SELECT mime_type, extension FROM allowed_file_types LIMIT 10");
                    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<ul>";
                    foreach ($types as $type) {
                        echo "<li>" . htmlspecialchars($type['mime_type']) . " (." . htmlspecialchars($type['extension']) . ")</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='text-danger'>No allowed file types defined in database</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='text-danger'>Error checking allowed types: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>
    </div>
    
    <script>
    // Basic client-side validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const fileInput = document.getElementById('file');
        
        form.addEventListener('submit', function(e) {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File is too large. Maximum size is 5MB.');
                }
                
                // Basic extension check
                const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'pdf', 'doc', 'docx', 'txt'];
                const extension = file.name.split('.').pop().toLowerCase();
                
                if (!allowedExtensions.includes(extension)) {
                    e.preventDefault();
                    alert('File type not allowed. Allowed extensions: ' + allowedExtensions.join(', '));
                }
            }
        });
    });
    </script>
</body>
</html>
