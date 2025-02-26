<?php
// Start session and include necessary files
session_start();
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/FileHandler.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get user data
$stmt = $pdo->prepare("SELECT id, username, display_name, bio, location, profile_pic, cover_pic, website FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize variables for success/error messages
$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = $_POST['display_name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $location = $_POST['location'] ?? '';
    $website = $_POST['website'] ?? '';
    
    // Validate website URL if provided
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid website URL (include http:// or https://)";
    } else {
        try {
            // Create file handler
            $fileHandler = new FileHandler($pdo);
            
            // Handle profile picture upload
            $profile_pic = $user['profile_pic']; // Default to current
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $fileData = $fileHandler->handleFileUpload($_FILES['profile_pic']);
                    if ($fileData) {
                        $profile_pic = $fileData['url'];
                    }
                } catch (Exception $e) {
                    $error = "Profile picture upload failed: " . $e->getMessage();
                }
            }
            
            // Handle cover photo upload
            $cover_pic = $user['cover_pic']; // Default to current
            if (isset($_FILES['cover_pic']) && $_FILES['cover_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $fileData = $fileHandler->handleFileUpload($_FILES['cover_pic']);
                    if ($fileData) {
                        $cover_pic = $fileData['url'];
                    }
                } catch (Exception $e) {
                    $error = "Cover photo upload failed: " . $e->getMessage();
                }
            }
            
            // If no errors, update user profile
            if (empty($error)) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET display_name = ?, bio = ?, location = ?, website = ?, 
                        profile_pic = ?, cover_pic = ?, profile_updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $display_name, 
                    $bio, 
                    $location, 
                    $website,
                    $profile_pic,
                    $cover_pic,
                    $_SESSION['user_id']
                ]);
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT id, username, display_name, bio, location, profile_pic, cover_pic, website FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get username for profile link
$username = getUserById($pdo, $_SESSION['user_id'])['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Social Media</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        .profile-pic-preview, .cover-pic-preview {
            width: 100%;
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            position: relative;
            overflow: hidden;
        }
        
        .profile-pic-preview {
            height: 200px;
            border-radius: 50%;
            margin: 0 auto;
            max-width: 200px;
        }
        
        .cover-pic-preview {
            height: 150px;
            border-radius: 4px;
        }
        
        .preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #6c757d;
        }
        
        .form-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <header class="py-3 bg-white shadow-sm mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h1 class="mb-0">Social Media</h1>
                </div>
                <div class="col-md-4 my-2 my-md-0">
                    <div class="d-flex justify-content-center">
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-home"></i> Home
                        </a>
                        <a href="categories.php" class="btn btn-outline-primary">
                            <i class="fas fa-tags"></i> Categories
                        </a>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-end">
                    <a href="profile.php?username=<?= htmlspecialchars($username) ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <div class="form-card">
                    <div class="list-group">
                        <a href="#profile-section" class="list-group-item list-group-item-action">
                            <i class="fas fa-user"></i> Profile Information
                        </a>
                        <a href="#photo-section" class="list-group-item list-group-item-action">
                            <i class="fas fa-image"></i> Profile & Cover Photos
                        </a>
                        <a href="#bio-section" class="list-group-item list-group-item-action">
                            <i class="fas fa-info-circle"></i> Bio & Details
                        </a>
                        <a href="profile.php?username=<?= htmlspecialchars($username) ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-eye"></i> View Profile
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-card form-section" id="profile-section">
                        <h3 class="form-section-title">Profile Information</h3>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="display_name" class="form-label">Display Name</label>
                            <input type="text" class="form-control" id="display_name" name="display_name" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" maxlength="50">
                            <small class="text-muted">This is the name displayed on your posts and profile</small>
                        </div>
                    </div>
                    
                    <div class="form-card form-section" id="photo-section">
                        <h3 class="form-section-title">Profile & Cover Photos</h3>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Profile Picture</label>
                                <div class="profile-pic-preview mb-2">
                                    <?php if ($user['profile_pic']): ?>
                                        <img src="<?= htmlspecialchars($user['profile_pic']) ?>" class="preview-image" alt="Profile Picture" id="profile-preview">
                                    <?php else: ?>
                                        <div class="upload-icon">
                                            <i class="fas fa-user fa-4x"></i>
                                        </div>
                                        <img src="" class="preview-image d-none" alt="Profile Picture" id="profile-preview">
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" name="profile_pic" id="profile_pic" accept="image/*">
                                <small class="text-muted">Recommended size: 400x400 pixels</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Cover Photo</label>
                                <div class="cover-pic-preview mb-2">
                                    <?php if ($user['cover_pic']): ?>
                                        <img src="<?= htmlspecialchars($user['cover_pic']) ?>" class="preview-image" alt="Cover Photo" id="cover-preview">
                                    <?php else: ?>
                                        <div class="upload-icon">
                                            <i class="fas fa-image fa-4x"></i>
                                        </div>
                                        <img src="" class="preview-image d-none" alt="Cover Photo" id="cover-preview">
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" name="cover_pic" id="cover_pic" accept="image/*">
                                <small class="text-muted">Recommended size: 1200x400 pixels</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-card form-section" id="bio-section">
                        <h3 class="form-section-title">Bio & Details</h3>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4" maxlength="500"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            <small class="text-muted">Tell others a bit about yourself (max 500 characters)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" value="<?= htmlspecialchars($user['website'] ?? '') ?>" maxlength="255">
                            <small class="text-muted">Include http:// or https://</small>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="profile.php?username=<?= htmlspecialchars($username) ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and other scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Preview profile picture before upload
        const profileInput = document.getElementById('profile_pic');
        const profilePreview = document.getElementById('profile-preview');
        const profilePreviewContainer = document.querySelector('.profile-pic-preview');
        
        if (profileInput && profilePreview) {
            profileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        profilePreview.src = e.target.result;
                        profilePreview.classList.remove('d-none');
                        const uploadIcon = profilePreviewContainer.querySelector('.upload-icon');
                        if (uploadIcon) {
                            uploadIcon.style.display = 'none';
                        }
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Preview cover photo before upload
        const coverInput = document.getElementById('cover_pic');
        const coverPreview = document.getElementById('cover-preview');
        const coverPreviewContainer = document.querySelector('.cover-pic-preview');
        
        if (coverInput && coverPreview) {
            coverInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        coverPreview.src = e.target.result;
                        coverPreview.classList.remove('d-none');
                        const uploadIcon = coverPreviewContainer.querySelector('.upload-icon');
                        if (uploadIcon) {
                            uploadIcon.style.display = 'none';
                        }
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Smooth scroll to sections
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    });
    </script>
</body>
</html>
