<?php
// Start session and initialize user identifier cookie if not exists
session_start();
if (!isset($_COOKIE['user_identifier'])) {
    $identifier = md5(uniqid());
    setcookie('user_identifier', $identifier, time() + (86400 * 30), "/");
    $_COOKIE['user_identifier'] = $identifier; // Set for immediate use
}

include 'includes/db.php';
include 'includes/auth.php';
include 'includes/FileHandler.php';
include 'includes/ContentAnalyzer.php';

// Turn on error reporting in development mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize ContentAnalyzer
$contentAnalyzer = new ContentAnalyzer();

// Process posting directly from index page (including guest mode)
$postSuccess = null;
$postError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'];
    $uploadedFile = $_FILES['file'] ?? null;
    $isGuestPost = isset($_POST['guest_post']) && $_POST['guest_post'] == 1;

    // Post can be done if user is logged in OR in guest mode
    if (isLoggedIn() || $isGuestPost) {
        try {
            $fileData = null;
            if ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $fileHandler = new FileHandler($pdo);
                $fileData = $fileHandler->handleFileUpload($uploadedFile);
            }

            // For guests, user_id will be null but we store user_identifier in cookie
            $userId = isLoggedIn() ? $_SESSION['user_id'] : null;

            $stmt = $pdo->prepare("INSERT INTO posts (content, file_url, file_type, user_id) VALUES (:content, :file_url, :file_type, :user_id)");
            $stmt->execute([
                'content' => $content,
                'file_url' => $fileData ? $fileData['url'] : null,
                'file_type' => $fileData ? $fileData['type'] : null,
                'user_id' => $userId
            ]);
            
            // Get the new post ID
            $postId = $pdo->lastInsertId();
            
            // Auto-categorize the post
            $assignedCategories = $contentAnalyzer->assignCategoriesToPost($pdo, $postId, $content);
            
            $postSuccess = "Post created successfully" . 
                      (!empty($assignedCategories) ? 
                       " and categorized as: " . implode(', ', $assignedCategories) : 
                       " (No categories detected)");
                       
            // Clear the form data to prevent resubmission
            $_POST = array();
        } catch (Exception $e) {
            $postError = $e->getMessage();
        }
    } else {
        $postError = "You must be logged in or using guest mode to post.";
    }
}

// Initialize not_interested array if it doesn't exist
if (!isset($_SESSION['not_interested'])) {
    $_SESSION['not_interested'] = [];
}

// Clean up old not_interested posts (optional, after 30 days)
if (isset($_SESSION['not_interested_time'])) {
    foreach ($_SESSION['not_interested_time'] as $postId => $timestamp) {
        if (time() - $timestamp > 30 * 24 * 60 * 60) { // 30 days
            $key = array_search($postId, $_SESSION['not_interested']);
            if ($key !== false) {
                unset($_SESSION['not_interested'][$key]);
                unset($_SESSION['not_interested_time'][$postId]);
            }
        }
    }
}

// Get selected categories from URL
$selectedCategories = isset($_GET['categories']) ? 
    (is_array($_GET['categories']) ? $_GET['categories'] : [$_GET['categories']]) : 
    [];

// Convert string IDs to integers if needed
$selectedCategoryIds = array_map('intval', $selectedCategories);

// Debugging for selected categories
if (!empty($selectedCategoryIds)) {
    error_log("Filtering by categories: " . implode(', ', $selectedCategoryIds));
}

// Get all available categories for the filter section
$allCategories = $contentAnalyzer->getAllCategories($pdo);

// Fetch trending posts with category information
$trendingQuery = "
    SELECT 
        p.id, 
        p.content, 
        p.file_url,
        p.file_type,
        u.username,
        COALESCE(SUM(CASE WHEN i.action = 'like' THEN 1 ELSE 0 END), 0) AS likes,
        COALESCE(SUM(CASE WHEN i.action = 'dislike' THEN 1 ELSE 0 END), 0) AS dislikes,
        COUNT(DISTINCT c.id) as comment_count,
        MAX(CASE WHEN i.user_identifier = :user_identifier AND i.action IN ('like', 'dislike') THEN i.action ELSE NULL END) as user_action,
        EXISTS(SELECT 1 FROM interactions WHERE post_id = p.id AND user_identifier = :user_identifier AND action = 'not_interested') as is_not_interested,
        GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ',') as categories,
        GROUP_CONCAT(DISTINCT cat.id ORDER BY cat.name SEPARATOR ',') as category_ids
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN interactions i ON p.id = i.post_id
    LEFT JOIN post_categories pc ON p.id = pc.post_id
    LEFT JOIN categories cat ON pc.category_id = cat.id
    LEFT JOIN comments c ON p.id = c.post_id
    GROUP BY p.id, p.content, p.file_url, p.file_type, u.username
    ORDER BY likes DESC
    LIMIT 5
";

try {
    $stmt = $pdo->prepare($trendingQuery);
    $stmt->execute(['user_identifier' => $_COOKIE['user_identifier']]);
    $trendingPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching trending posts: " . $e->getMessage());
    $trendingPosts = [];
}

// Query for posts
try {
    if (empty($selectedCategoryIds)) {
        // If no category filter, use normal query
        $postsQuery = "
    SELECT 
        p.id, 
        p.content, 
        p.file_url, 
        p.file_type, 
        p.created_at, 
        u.username,
        COUNT(DISTINCT CASE WHEN i.action = 'like' THEN i.id END) as likes,
        COUNT(DISTINCT CASE WHEN i.action = 'dislike' THEN i.id END) as dislikes,
        COUNT(DISTINCT c.id) as comment_count,
        MAX(CASE WHEN i.user_identifier = :user_identifier AND i.action IN ('like', 'dislike') THEN i.action ELSE NULL END) as user_action,
        GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ',') as categories,
        GROUP_CONCAT(DISTINCT cat.id ORDER BY cat.name SEPARATOR ',') as category_ids
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN interactions i ON p.id = i.post_id
    LEFT JOIN post_categories pc ON p.id = pc.post_id
    LEFT JOIN categories cat ON pc.category_id = cat.id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE p.id NOT IN (
        SELECT post_id FROM interactions 
        WHERE user_identifier = :user_identifier AND action = 'not_interested'
    )
    GROUP BY p.id, p.content, p.file_url, p.file_type, p.created_at, u.username
    ORDER BY p.created_at DESC
";
        
        $stmt = $pdo->prepare($postsQuery);
        $stmt->execute(['user_identifier' => $_COOKIE['user_identifier']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    else {
        // NOTICE: We're getting categories by name that match
        // This could be a problem with URL category
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$selectedCategoryIds[0]]);
        $categoryName = $stmt->fetchColumn();
        
        error_log("Looking for posts in category: " . $categoryName);

        // Simple query using category name to address ID issues
$postsQuery = "
    SELECT 
        p.id, 
        p.content, 
        p.file_url, 
        p.file_type, 
        p.created_at, 
        u.username,
        COUNT(DISTINCT CASE WHEN i.action = 'like' THEN i.id END) as likes,
        COUNT(DISTINCT CASE WHEN i.action = 'dislike' THEN i.id END) as dislikes,
        COUNT(DISTINCT c.id) as comment_count,
        MAX(CASE WHEN i.user_identifier = :user_identifier AND i.action IN ('like', 'dislike') THEN i.action ELSE NULL END) as user_action,
        GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ',') as categories,
        GROUP_CONCAT(DISTINCT cat.id ORDER BY cat.name SEPARATOR ',') as category_ids
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN interactions i ON p.id = i.post_id
    LEFT JOIN post_categories pc ON p.id = pc.post_id
    LEFT JOIN categories cat ON pc.category_id = cat.id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE p.id NOT IN (
        SELECT post_id FROM interactions 
        WHERE user_identifier = :user_identifier AND action = 'not_interested'
    )
    GROUP BY p.id, p.content, p.file_url, p.file_type, p.created_at, u.username
    ORDER BY p.created_at DESC
";
        
        $stmt = $pdo->prepare($postsQuery);
        $stmt->execute([
            'user_identifier' => $_COOKIE['user_identifier'],
            'category_name' => $categoryName
        ]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($posts) . " posts in category: " . $categoryName);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching posts: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    error_log("Query: " . (isset($postsQuery) ? $postsQuery : 'Query not defined'));
    $posts = [];
}

// Function to display different file types
function displayFile($fileUrl, $fileType) {
    if (!$fileUrl) return '';
    
    if (strpos($fileType, 'image/') === 0) {
        return "<img src='" . htmlspecialchars($fileUrl) . "' class='card-image' alt='Post Image'>";
    } else if (strpos($fileType, 'video/') === 0) {
        return "<video class='w-100' controls>
                    <source src='" . htmlspecialchars($fileUrl) . "' type='" . htmlspecialchars($fileType) . "'>
                    Your browser does not support the video tag.
                </video>";
    } else if (strpos($fileType, 'audio/') === 0) {
        return "<audio class='w-100' controls>
                    <source src='" . htmlspecialchars($fileUrl) . "' type='" . htmlspecialchars($fileType) . "'>
                    Your browser does not support the audio tag.
                </audio>";
    } else {
        $fileHandler = new FileHandler($GLOBALS['pdo']);
        $icon = $fileHandler->getFileTypeIcon($fileType);
        return "<div class='text-center p-3 border rounded'>
                    <i class='fas " . $icon . " fa-3x'></i>
                    <br>
                    <a href='" . htmlspecialchars($fileUrl) . "' class='btn btn-sm btn-primary mt-2' download>
                        Download File
                    </a>
                </div>";
    }
}

function getTimeAgo($timestamp) {
    // Set timezone to Jakarta, Indonesia
    date_default_timezone_set('Asia/Jakarta');
    
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . " seconds ago";
    } else if ($diff < 3600) {
        return floor($diff / 60) . " minutes ago";
    } else if ($diff < 86400) {
        return floor($diff / 3600) . " hours ago";
    } else if ($diff < 2592000) {
        return floor($diff / 86400) . " days ago";
    } else if ($diff < 31536000) {
        return floor($diff / 2592000) . " months ago";
    } else {
        return floor($diff / 31536000) . " years ago";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Link to the external CSS file -->
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Modern Header Design -->
    <header class="py-3 shadow-sm mb-4">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-md-4">
            <a href="index.php" class="text-decoration-none">
              <h1 class="mb-0 d-flex align-items-center">
                <i class="fas fa-network-wired me-2"></i>
                <span>ConnectHub</span>
              </h1>
            </a>
          </div>
          <div class="col-md-4 my-2 my-md-0">
            <div class="d-flex justify-content-center">
              <a href="index.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-home"></i> Home
              </a>
              <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="categoriesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fas fa-tags"></i> Categories
                </button>
                <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="categoriesDropdown">
                  <li><a class="dropdown-item" href="categories.php">
                    <i class="fas fa-th-large me-2"></i> Browse All Categories
                  </a></li>
                  <li><a class="dropdown-item" href="select_categories.php">
                    <i class="fas fa-check-square me-2"></i> Select Multiple Categories
                  </a></li>
                  <?php if (isLoggedIn()): ?>
                  <li><a class="dropdown-item" href="admin_categories.php">
                    <i class="fas fa-cog me-2"></i> Manage Categories
                  </a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider"></li>
                  <li class="dropdown-header">Popular Categories</li>
                  <?php 
                  // Show some popular categories
                  foreach (array_slice($allCategories, 0, 5) as $cat): 
                    // Get appropriate icon
                    $icon = 'tag';
                    $catLower = strtolower($cat['name']);
                    if (strpos($catLower, 'politic') !== false) $icon = 'landmark';
                    elseif (strpos($catLower, 'tech') !== false) $icon = 'microchip';
                    elseif (strpos($catLower, 'entertain') !== false) $icon = 'film';
                    elseif (strpos($catLower, 'sport') !== false) $icon = 'futbol';
                    elseif (strpos($catLower, 'health') !== false) $icon = 'heartbeat';
                  ?>
                    <li>
                      <a class="dropdown-item" href="index.php?categories[]=<?= $cat['id'] ?>">
                        <i class="fas fa-<?= $icon ?> me-2"></i> <?= htmlspecialchars($cat['name']) ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
          <div class="col-md-4 d-flex justify-content-end">
            <?php if (isLoggedIn()): ?>
              <a href="post.php" class="btn btn-primary me-2">
                <i class="fas fa-plus"></i> <span class="d-none d-md-inline">New Post</span>
              </a>
              <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fas fa-user"></i>
                  <span class="d-none d-md-inline">
                    <?php 
                    // Get current user's username
                    if (isset($_SESSION['user_id'])) {
                      $currentUser = getUserById($pdo, $_SESSION['user_id']);
                      echo htmlspecialchars($currentUser['display_name'] ?: $currentUser['username']);
                    }
                    ?>
                  </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="profileDropdown">
                  <li>
                    <a class="dropdown-item" href="profile.php?username=<?= htmlspecialchars($currentUser['username']) ?>">
                      <i class="fas fa-user me-2"></i> My Profile
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="edit_profile.php">
                      <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <a class="dropdown-item" href="logout.php">
                      <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                  </li>
                </ul>
              </div>
            <?php else: ?>
              <a href="login.php" class="btn btn-primary me-2">Login</a>
              <a href="signup.php" class="btn btn-outline-primary">Sign Up</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </header>

    <!-- Enhanced Trending Section -->
    <div id="trending-section" class="mb-5">
      <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="section-title mb-0">
            <i class="fas fa-fire text-danger me-2"></i>Trending Posts
          </h2>
        </div>
        
        <div class="card shadow">
          <div class="card-body p-0">
            <div class="carousel-container position-relative">
              <button class="nav-button prev-button">&lt;</button>
              <button class="nav-button next-button">&gt;</button>
              <!-- Post items will be loaded here by JavaScript -->
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="container">
        <!-- Category Filter Card -->
        <?php if (!empty($allCategories)): ?>
        <div class="filter-card card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter by Categories</h5>
            </div>
            <div class="card-body">
                <form action="index.php" method="GET" id="categoryFilterForm">
                    <div class="row">
                        <?php foreach ($allCategories as $category): ?>
                        <div class="col-md-3 col-sm-4 col-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input category-checkbox" type="checkbox" 
                                       name="categories[]" value="<?= $category['id'] ?>" 
                                       id="category_<?= $category['id'] ?>"
                                       <?= in_array($category['id'], $selectedCategoryIds) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="category_<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <?php if (!empty($selectedCategoryIds)): ?>
                            <a href="index.php" class="btn btn-outline-secondary">Clear Filters</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($selectedCategoryIds)): ?>
                        <div class="active-filters">
                            <span class="text-muted">Active filters:</span>
                            <?php 
                            $activeCategories = [];
                            foreach ($selectedCategoryIds as $catId) {
                                foreach ($allCategories as $cat) {
                                    if ($cat['id'] == $catId) {
                                        $activeCategories[] = $cat['name'];
                                        break;
                                    }
                                }
                            }
                            echo htmlspecialchars(implode(', ', $activeCategories));
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($postSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($postSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($postError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($postError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="main-feed">
            <?php if (isLoggedIn()): ?>
                <!-- Form posting for logged in users -->
                <div class="post-card mb-4">
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <div class="card-content">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-2">
                                    <?php 
                                    $currentUser = getUserById($pdo, $_SESSION['user_id']);
                                    if ($currentUser['profile_pic']): 
                                    ?>
                                        <img src="<?= htmlspecialchars($currentUser['profile_pic']) ?>" alt="Profile" class="rounded-circle" width="40" height="40">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                            <i class="fas fa-user text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-0">
                                    <?= htmlspecialchars($currentUser['display_name'] ?: $currentUser['username']) ?>
                                </h5>
                            </div>
                            <textarea name="content" class="form-control mb-3" 
                                    placeholder="What's on your mind?" required></textarea>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="flex-grow-1 me-3">
                                    <div class="input-group">
                                        <input type="file" name="file" class="form-control" id="file-upload">
                                        <label class="input-group-text" for="file-upload"><i class="fas fa-paperclip"></i></label>
                                    </div>
                                    <small class="text-muted">Images, Videos, Audio, Documents</small>
                                </div>
                                <!-- Hidden field to mark this as inline posting -->
                                <input type="hidden" name="inline_post" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Share
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Show login/signup options and Guest Mode -->
                <div class="alert alert-info text-center mb-4">
                    <p>Please <a href="login.php" class="alert-link">login</a> or <a href="signup.php" class="alert-link">sign up</a> to post.</p>
                    <button id="guestModeBtn" class="btn btn-secondary mt-2">
                        <i class="fas fa-user-secret"></i> Continue as Guest
                    </button>
                </div>
                
                <!-- Guest Mode Posting Form (hidden by default) -->
                <div id="guestPostForm" class="post-card mb-4" style="display: none;">
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <div class="card-content">
                            <div class="mb-3">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i> 
                                    You are posting as a guest. Your username will appear as "Anonymous".
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-2">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                        <i class="fas fa-user-secret text-secondary"></i>
                                    </div>
                                </div>
                                <h5 class="mb-0">Anonymous</h5>
                            </div>
                            <textarea name="content" class="form-control mb-3" 
                                    placeholder="What's on your mind?" required></textarea>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="flex-grow-1 me-3">
                                    <div class="input-group">
                                        <input type="file" name="file" class="form-control" id="guest-file-upload">
                                        <label class="input-group-text" for="guest-file-upload"><i class="fas fa-paperclip"></i></label>
                                    </div>
                                    <small class="text-muted">Images, Videos, Audio, Documents</small>
                                </div>
                                <input type="hidden" name="guest_post" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Share
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (empty($posts)): ?>
                <div class="alert alert-info text-center">
                    <?php if (!empty($selectedCategoryIds)): ?>
                        No posts found in the selected categories. <a href="index.php" class="alert-link">View all posts</a>
                    <?php else: ?>
                        No posts available. Be the first to create a post!
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($posts as $post): ?>
                <!-- Enhanced Post Card Design -->
                <div class="post-card" data-post-id="<?= $post['id'] ?>">
                  <?php if ($post['file_url']): ?>
                    <div class="post-media">
                      <?= displayFile($post['file_url'], $post['file_type']) ?>
                    </div>
                  <?php endif; ?>
                  
                  <div class="card-content">
                    <div class="d-flex align-items-center mb-3">
                      <?php if ($post['username']): ?>
                        <a href="profile.php?username=<?= htmlspecialchars($post['username']) ?>" class="text-decoration-none">
                          <div class="d-flex align-items-center">
                            <div class="user-avatar me-2">
                              <?php if (isset($post['profile_pic']) && $post['profile_pic']): ?>
                                <img src="<?= htmlspecialchars($post['profile_pic']) ?>" alt="Profile" class="rounded-circle" width="40" height="40">
                              <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                  <i class="fas fa-user text-secondary"></i>
                                </div>
                              <?php endif; ?>
                            </div>
                            <div>
                              <h5 class="mb-0"><?= htmlspecialchars($post['username']) ?></h5>
                              <small class="text-muted"><?= getTimeAgo($post['created_at']) ?></small>
                            </div>
                          </div>
                        </a>
                      <?php else: ?>
                        <div class="d-flex align-items-center">
                          <div class="user-avatar me-2">
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                              <i class="fas fa-user-secret text-secondary"></i>
                            </div>
                          </div>
                          <div>
                            <h5 class="mb-0">Anonymous</h5>
                            <small class="text-muted"><?= getTimeAgo($post['created_at']) ?></small>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                    
                    <div class="post-text-content collapsed">
                      <?= nl2br(htmlspecialchars($post['content'])) ?>
                    </div>
                    <button class="read-more-btn">Read More</button>
                  </div>
                  
                  <?php if (!empty($post['categories'])): ?>
                  <div class="card-categories">
                    <?php 
                    $categoryNames = explode(',', $post['categories']);
                    $categoryIds = explode(',', $post['category_ids']);
                    foreach ($categoryNames as $index => $category): 
                      // Get appropriate class based on category name
                      $categoryClass = 'category-badge';
                      $catLower = strtolower($category);
                      foreach (['politics', 'technology', 'entertainment', 'sports', 'health', 'science', 'business', 'education'] as $mainCat) {
                        if (strpos($catLower, $mainCat) !== false) {
                          $categoryClass .= ' category-' . $mainCat;
                          break;
                        }
                      }
                      
                      // Get appropriate icon based on category
                      $icon = 'tag';
                      if (strpos($catLower, 'politic') !== false) $icon = 'landmark';
                      elseif (strpos($catLower, 'tech') !== false) $icon = 'microchip';
                      elseif (strpos($catLower, 'entertain') !== false) $icon = 'film';
                      elseif (strpos($catLower, 'sport') !== false) $icon = 'futbol';
                      elseif (strpos($catLower, 'health') !== false) $icon = 'heartbeat';
                      elseif (strpos($catLower, 'science') !== false) $icon = 'flask';
                      elseif (strpos($catLower, 'business') !== false) $icon = 'briefcase';
                      elseif (strpos($catLower, 'education') !== false) $icon = 'graduation-cap';
                    ?>
                      <a href="index.php?categories[]=<?= $categoryIds[$index] ?>" class="<?= $categoryClass ?> text-decoration-none">
                        <i class="fas fa-<?= $icon ?> fa-xs"></i> <?= htmlspecialchars($category) ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                  
                  <div class="card-actions">
                    <div class="d-flex justify-content-between align-items-center">
                      <div data-post-id="<?= $post['id'] ?>" class="interaction-buttons">
                        <button onclick="handleInteraction(<?= $post['id'] ?>, 'like')" 
                                class="btn <?= ($post['user_action'] === 'like') ? 'btn-success' : 'btn-outline-primary' ?> btn-sm me-2 like-btn">
                          <i class="fas fa-thumbs-up"></i> <span class="like-count"><?= $post['likes'] ?></span>
                        </button>
                        <button onclick="handleInteraction(<?= $post['id'] ?>, 'dislike')" 
                                class="btn <?= ($post['user_action'] === 'dislike') ? 'btn-danger' : 'btn-outline-secondary' ?> btn-sm me-2 dislike-btn">
                          <i class="fas fa-thumbs-down"></i> <span class="dislike-count"><?= $post['dislikes'] ?></span>
                        </button>
                        <button class="btn btn-outline-primary btn-sm me-2 comment-btn" onclick="toggleComments(<?= $post['id'] ?>)">
                          <i class="fas fa-comments"></i> <span class="comment-count" data-post-id="<?= $post['id'] ?>"><?= $post['comment_count'] ?? 0 ?></span>
                        </button>
                      </div>
                      <div>
                        <button onclick="handleNotInterested(<?= $post['id'] ?>)" class="btn btn-outline-danger btn-sm not-interested-btn">
                          <i class="fas fa-times"></i> <span class="d-none d-md-inline">Not Interested</span>
                        </button>
                        <?php if (isLoggedIn() && isset($_SESSION['user_id']) && $_SESSION['user_id'] == ($post['user_id'] ?? 0)): ?>
                        <a href="edit_post.php?id=<?= $post['id'] ?>" class="btn btn-outline-secondary btn-sm ms-2">
                          <i class="fas fa-edit"></i> <span class="d-none d-md-inline">Edit</span>
                        </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <!-- Comments Section -->
                  <div class="comments-section" data-post-id="<?= $post['id'] ?>">
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center mb-2 px-3">
                      <h6 class="mb-0"><i class="fas fa-comments text-primary"></i> Comments</h6>
                      <button class="btn btn-sm btn-outline-primary toggle-comments rounded-pill" data-post-id="<?= $post['id'] ?>">
                        <i class="fas fa-chevron-down"></i> Show Comments
                      </button>
                    </div>
                    
                    <!-- Comment Form -->
                    <div class="comment-form px-3 mb-3" style="display: none;">
                      <form onsubmit="return addComment(event, <?= $post['id'] ?>)">
                        <div class="input-group">
                          <input type="text" class="form-control comment-input" placeholder="Write a comment...">
                          <button class="btn btn-primary" type="submit">
                            <i class="fas fa-paper-plane"></i>
                          </button>
                        </div>
                      </form>
                    </div>
                    
                    <!-- Comments List -->
                    <div class="comments-list px-3" id="comments-<?= $post['id'] ?>" style="display: none;">
                      <!-- Comments will be loaded here -->
                    </div>
                    
                    <!-- Comments Pagination -->
                    <div class="comments-pagination text-center mt-2 pb-2" id="pagination-<?= $post['id'] ?>" style="display: none;">
                      <button class="btn btn-sm btn-outline-primary rounded-pill load-more-comments" data-post-id="<?= $post['id'] ?>" data-page="2">
                        <i class="fas fa-plus-circle"></i> Load More Comments
                      </button>
                    </div>
                  </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bootstrap JS and other scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Make trending posts data available to JavaScript -->
    <script>
        const trendingPosts = <?php echo json_encode($trendingPosts); ?>;
    </script>
    
    <!-- Link to the external JavaScript file -->
    <script src="assets/js/scripts.js"></script>
    
    <script>
      // Additional script to make the top buttons control the carousel
      document.addEventListener('DOMContentLoaded', function() {
        const prevButtonTop = document.querySelector('.prev-button-top');
        const nextButtonTop = document.querySelector('.next-button-top');
        const prevButton = document.querySelector('.prev-button');
        const nextButton = document.querySelector('.next-button');
        
        if (prevButtonTop && prevButton) {
          prevButtonTop.addEventListener('click', function() {
            prevButton.click();
          });
        }
        
        if (nextButtonTop && nextButton) {
          nextButtonTop.addEventListener('click', function() {
            nextButton.click();
          });
        }
        
        // Show guest post form when the button is clicked
        const guestModeBtn = document.getElementById('guestModeBtn');
        const guestPostForm = document.getElementById('guestPostForm');
        
        if (guestModeBtn && guestPostForm) {
          guestModeBtn.addEventListener('click', function() {
            this.closest('.alert').style.display = 'none';
            guestPostForm.style.display = 'block';
          });
        }
      });
    </script>
</body>
</html>