<?php
session_start();
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/config.php';
include 'includes/ContentAnalyzer.php';

// Initialize user identifier cookie if not exists
if (!isset($_COOKIE['user_identifier'])) {
    $identifier = md5(uniqid());
    setcookie('user_identifier', $identifier, time() + (86400 * 30), "/");
    $_COOKIE['user_identifier'] = $identifier; // Set for immediate use
}

$contentAnalyzer = new ContentAnalyzer(GEMINI_API_KEY);

// Get all categories
$allCategories = $contentAnalyzer->getAllCategories($pdo);

// Get posts count for each category
foreach ($allCategories as $key => $category) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.id) as post_count
        FROM post_categories pc
        JOIN posts p ON pc.post_id = p.id
        WHERE pc.category_id = ?
        AND p.id NOT IN (
            SELECT post_id FROM interactions 
            WHERE user_identifier = ? AND action = 'not_interested'
        )
    ");
    
    $stmt->execute([$category['id'], $_COOKIE['user_identifier']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $allCategories[$key]['post_count'] = $result['post_count'];
    
    // Get most recent post for each category
    $stmt = $pdo->prepare("
        SELECT p.id, p.content, p.file_url, p.file_type, p.created_at
        FROM post_categories pc
        JOIN posts p ON pc.post_id = p.id
        WHERE pc.category_id = ?
        AND p.id NOT IN (
            SELECT post_id FROM interactions 
            WHERE user_identifier = ? AND action = 'not_interested'
        )
        ORDER BY p.created_at DESC
        LIMIT 1
    ");
    
    $stmt->execute([$category['id'], $_COOKIE['user_identifier']]);
    $allCategories[$key]['recent_post'] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Sort categories by post count (most popular first)
usort($allCategories, function($a, $b) {
    return $b['post_count'] - $a['post_count'];
});

// Function to display different file types (copied from index.php)
function displayFile($fileUrl, $fileType) {
    if (!$fileUrl) return '';
    
    if (strpos($fileType, 'image/') === 0) {
        return "<img src='" . htmlspecialchars($fileUrl) . "' class='card-img-top' alt='Post Image' style='height: 180px; object-fit: cover;'>";
    } else if (strpos($fileType, 'video/') === 0) {
        return "<div class='card-img-top d-flex justify-content-center align-items-center bg-light' style='height: 180px;'>
                    <i class='fas fa-video fa-3x text-primary'></i>
                </div>";
    } else if (strpos($fileType, 'audio/') === 0) {
        return "<div class='card-img-top d-flex justify-content-center align-items-center bg-light' style='height: 180px;'>
                    <i class='fas fa-music fa-3x text-primary'></i>
                </div>";
    } else if ($fileType) {
        $fileHandler = new FileHandler($GLOBALS['pdo']);
        $icon = $fileHandler->getFileTypeIcon($fileType);
        return "<div class='card-img-top d-flex justify-content-center align-items-center bg-light' style='height: 180px;'>
                    <i class='fas {$icon} fa-3x text-primary'></i>
                </div>";
    } else {
        return "<div class='card-img-top d-flex justify-content-center align-items-center bg-light' style='height: 180px;'>
                    <i class='fas fa-comment-alt fa-3x text-primary'></i>
                </div>";
    }
}

// Get background colors for categories
$categoryColors = [
    'bg-primary', 'bg-success', 'bg-danger', 'bg-warning', 'bg-info',
    'bg-secondary', 'bg-dark', 'bg-primary bg-opacity-75', 'bg-success bg-opacity-75',
    'bg-danger bg-opacity-75', 'bg-warning bg-opacity-75', 'bg-info bg-opacity-75'
];

// Get icons for categories
function getCategoryIcon($category) {
    $catLower = strtolower($category);
    if (strpos($catLower, 'politic') !== false) return 'landmark';
    elseif (strpos($catLower, 'tech') !== false) return 'microchip';
    elseif (strpos($catLower, 'entertain') !== false) return 'film';
    elseif (strpos($catLower, 'sport') !== false) return 'futbol';
    elseif (strpos($catLower, 'health') !== false) return 'heartbeat';
    elseif (strpos($catLower, 'science') !== false) return 'flask';
    elseif (strpos($catLower, 'business') !== false) return 'briefcase';
    elseif (strpos($catLower, 'education') !== false) return 'graduation-cap';
    elseif (strpos($catLower, 'travel') !== false) return 'plane';
    elseif (strpos($catLower, 'food') !== false) return 'utensils';
    elseif (strpos($catLower, 'fashion') !== false) return 'tshirt';
    elseif (strpos($catLower, 'art') !== false) return 'palette';
    return 'tag';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Categories - ConnectHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                            <button class="btn btn-primary dropdown-toggle" type="button" id="categoriesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-tags"></i> Categories
                            </button>
                            <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="categoriesDropdown">
                                <li><a class="dropdown-item active" href="categories.php">
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

    <div class="container">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title"><i class="fas fa-tags me-2 text-primary"></i>Browse by Category</h2>
                        <p class="card-text">
                            Explore posts by category. Select multiple categories to see posts that belong to any of them.
                            All posts are automatically categorized based on their content.
                        </p>
                        <div class="d-flex justify-content-end">
                            <a href="select_categories.php" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Multi-Category Filter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
            <?php foreach ($allCategories as $index => $category): ?>
                <div class="col">
                    <div class="card category-card h-100">
                        <div class="category-header <?= $categoryColors[$index % count($categoryColors)] ?> d-flex align-items-center justify-content-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-<?= getCategoryIcon($category['name']) ?> fa-2x me-2"></i>
                                <h3 class="mb-0 fs-4"><?= htmlspecialchars($category['name']) ?></h3>
                            </div>
                        </div>
                        
                        <?php if (isset($category['recent_post']) && $category['recent_post']): ?>
                            <?= displayFile($category['recent_post']['file_url'], $category['recent_post']['file_type']) ?>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">
                                        <span class="badge bg-primary rounded-pill">
                                            <i class="fas fa-file-alt me-1"></i> <?= $category['post_count'] ?> posts
                                        </span>
                                    </h5>
                                    <small class="text-muted"><?= date('M d, Y', strtotime($category['recent_post']['created_at'])) ?></small>
                                </div>
                                <p class="card-text text-truncate-2">
                                    <?= nl2br(htmlspecialchars(substr($category['recent_post']['content'], 0, 100))) ?>
                                    <?= strlen($category['recent_post']['content']) > 100 ? '...' : '' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="card-img-top d-flex justify-content-center align-items-center bg-light" style="height: 180px;">
                                <div class="text-center">
                                    <i class="fas fa-folder-open fa-3x text-secondary mb-3"></i>
                                    <p class="text-muted mb-0">No posts yet</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <span class="badge bg-secondary rounded-pill">0 posts</span>
                                </h5>
                                <p class="card-text text-muted">No posts yet in this category. Be the first to create one!</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-footer bg-white border-top-0">
                            <a href="index.php?categories[]=<?= $category['id'] ?>" class="btn btn-primary w-100">
                                <i class="fas fa-eye me-1"></i> View Posts
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h3 class="mb-0"><i class="fas fa-lightbulb me-2 text-warning"></i>Did You Know?</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">Posts on ConnectHub are automatically categorized using our smart content analysis system. The system detects keywords and phrases in your post to assign the most relevant categories.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animation effect on category cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.category-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px)';
                    this.style.boxShadow = '0 15px 30px rgba(0,0,0,0.1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
        });
    </script>
</body>
</html>
