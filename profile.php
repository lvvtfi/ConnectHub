<?php
// Start session and include necessary files
session_start();
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/ContentAnalyzer.php';

// Initialize user identifier cookie if not exists
if (!isset($_COOKIE['user_identifier'])) {
    $identifier = md5(uniqid());
    setcookie('user_identifier', $identifier, time() + (86400 * 30), "/");
    $_COOKIE['user_identifier'] = $identifier;
}

// Get username from URL parameter
$username = isset($_GET['username']) ? $_GET['username'] : null;

// If no username provided, redirect to homepage
if (!$username) {
    header('Location: index.php');
    exit;
}

// Get user data
$stmt = $pdo->prepare("SELECT id, username, display_name, bio, location, profile_pic, cover_pic, website, created_at FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user not found, redirect to homepage
if (!$user) {
    header('Location: index.php');
    exit;
}

// Check if the logged-in user is viewing their own profile
$isOwnProfile = isLoggedIn() && $_SESSION['user_id'] == $user['id'];

// Get user stats
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) AS post_count,
        (SELECT COUNT(*) FROM user_followers WHERE follower_id = ?) AS following_count,
        (SELECT COUNT(*) FROM user_followers WHERE user_id = ?) AS follower_count
");
$stmt->execute([$user['id'], $user['id'], $user['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if logged-in user is following this profile
$isFollowing = false;
if (isLoggedIn() && !$isOwnProfile) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_followers WHERE user_id = ? AND follower_id = ?");
    $stmt->execute([$user['id'], $_SESSION['user_id']]);
    $isFollowing = $stmt->fetchColumn() > 0;
}

// Get user's posts with categories
$stmt = $pdo->prepare("
    SELECT 
        p.id, 
        p.content, 
        p.file_url, 
        p.file_type, 
        p.created_at,
        COUNT(DISTINCT CASE WHEN i.action = 'like' THEN i.id END) as likes,
        COUNT(DISTINCT CASE WHEN i.action = 'dislike' THEN i.id END) as dislikes,
        MAX(CASE WHEN i.user_identifier = ? AND i.action IN ('like', 'dislike') THEN i.action ELSE NULL END) as user_action,
        GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ',') as categories,
        GROUP_CONCAT(DISTINCT c.id ORDER BY c.name SEPARATOR ',') as category_ids
    FROM posts p
    LEFT JOIN interactions i ON p.id = i.post_id
    LEFT JOIN post_categories pc ON p.id = pc.post_id
    LEFT JOIN categories c ON pc.category_id = c.id
    WHERE p.user_id = ?
    AND p.id NOT IN (
        SELECT post_id FROM interactions 
        WHERE user_identifier = ? AND action = 'not_interested'
    )
    GROUP BY p.id, p.content, p.file_url, p.file_type, p.created_at
    ORDER BY p.created_at DESC
");
$stmt->execute([$_COOKIE['user_identifier'], $user['id'], $_COOKIE['user_identifier']]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to display file content (reused from your existing code)
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

// Process follow/unfollow actions
if (isLoggedIn() && isset($_POST['action']) && !$isOwnProfile) {
    if ($_POST['action'] == 'follow') {
        try {
            $stmt = $pdo->prepare("INSERT INTO user_followers (user_id, follower_id) VALUES (?, ?)");
            $stmt->execute([$user['id'], $_SESSION['user_id']]);
            $isFollowing = true;
        } catch (PDOException $e) {
            // Likely a duplicate entry, ignore
        }
    } elseif ($_POST['action'] == 'unfollow') {
        $stmt = $pdo->prepare("DELETE FROM user_followers WHERE user_id = ? AND follower_id = ?");
        $stmt->execute([$user['id'], $_SESSION['user_id']]);
        $isFollowing = false;
    }
}

// Function to get time ago
function getTimeAgo($timestamp) {
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
    <title><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?> - Profile</title>
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

    <div class="container">
        <div class="profile-header">
            <?php if ($user['cover_pic']): ?>
                <img src="<?= htmlspecialchars($user['cover_pic']) ?>" class="cover-image" alt="Cover Image">
            <?php endif; ?>
            
            <div class="profile-picture-container">
                <?php if ($user['profile_pic']): ?>
                    <img src="<?= htmlspecialchars($user['profile_pic']) ?>" class="profile-picture" alt="Profile Picture">
                <?php else: ?>
                    <div class="profile-picture d-flex justify-content-center align-items-center">
                        <i class="fas fa-user fa-4x text-secondary"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-actions">
                <?php if ($isOwnProfile): ?>
                    <a href="edit_profile.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                <?php elseif (isLoggedIn()): ?>
                    <?php if ($isFollowing): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="unfollow">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-user-minus"></i> Unfollow
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="follow">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Follow
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="messages.php?user=<?= $user['id'] ?>" class="btn btn-outline-primary">
                        <i class="fas fa-envelope"></i> Message
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-info">
            <h1><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></h1>
            <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>
            
            <div class="profile-stats">
                <div class="stat-box">
                    <div class="stat-count"><?= number_format($stats['post_count']) ?></div>
                    <div class="stat-label">Posts</div>
                </div>
                <div class="stat-box">
                    <div class="stat-count"><?= number_format($stats['follower_count']) ?></div>
                    <div class="stat-label">Followers</div>
                </div>
                <div class="stat-box">
                    <div class="stat-count"><?= number_format($stats['following_count']) ?></div>
                    <div class="stat-label">Following</div>
                </div>
            </div>
            
            <?php if ($user['bio']): ?>
                <div class="user-bio">
                    <?= nl2br(htmlspecialchars($user['bio'])) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($user['location']): ?>
                <div class="user-location">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['location']) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($user['website']): ?>
                <div class="user-website">
                    <i class="fas fa-link"></i> <a href="<?= htmlspecialchars($user['website']) ?>" target="_blank"><?= htmlspecialchars($user['website']) ?></a>
                </div>
            <?php endif; ?>
            
            <div class="user-joined">
                <i class="fas fa-calendar-alt"></i> Joined <?= date('F Y', strtotime($user['created_at'])) ?>
            </div>
        </div>
        
        <div class="user-posts-section">
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts" type="button" role="tab" aria-controls="posts" aria-selected="true">
                        <i class="fas fa-th-list me-1"></i> Posts
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="media-tab" data-bs-toggle="tab" data-bs-target="#media" type="button" role="tab" aria-controls="media" aria-selected="false">
                        <i class="fas fa-images me-1"></i> Media
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="likes-tab" data-bs-toggle="tab" data-bs-target="#likes" type="button" role="tab" aria-controls="likes" aria-selected="false">
                        <i class="fas fa-heart me-1"></i> Likes
                    </button>
                </li>
            </ul>
            
            <div class="tab-content profile-tab-content" id="profileTabsContent">
                <div class="tab-pane fade show active" id="posts" role="tabpanel" aria-labelledby="posts-tab">
                    <?php if (empty($posts)): ?>
                        <div class="alert alert-info text-center">
                            No posts from this user yet.
                        </div>
                    <?php else: ?>
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
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                        <?php if ($user['profile_pic']): ?>
                                            <img src="<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile" class="rounded-circle" width="40" height="40">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                            <i class="fas fa-user text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                        </div>
                                        <div>
                                        <h5 class="mb-0"><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></h5>
                                        <small class="text-muted"><?= getTimeAgo($post['created_at']) ?></small>
                                        </div>
                                    </div>
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
                                            <button class="btn btn-outline-primary btn-sm comment-btn" onclick="toggleComments(<?= $post['id'] ?>)">
                                                <i class="fas fa-comments"></i> Comments
                                            </button>
                                        </div>
                                        <div>
                                            <button onclick="handleNotInterested(<?= $post['id'] ?>)" class="btn btn-outline-danger btn-sm not-interested-btn">
                                                <i class="fas fa-times"></i> <span class="d-none d-md-inline">Not Interested</span>
                                            </button>
                                            <?php if ($isOwnProfile): ?>
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
                    <?php endif; ?>
                </div>
                
                <div class="tab-pane fade" id="media" role="tabpanel" aria-labelledby="media-tab">
                    <div class="row">
                        <?php 
                        $mediaPosts = array_filter($posts, function($post) {
                            return !empty($post['file_url']) && (
                                strpos($post['file_type'], 'image/') === 0 || 
                                strpos($post['file_type'], 'video/') === 0
                            );
                        });
                        
                        if (empty($mediaPosts)): 
                        ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center">
                                    No media posts from this user yet.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($mediaPosts as $post): ?>
                                <div class="col-md-4 col-sm-6 mb-4">
                                    <div class="card h-100">
                                        <a href="post_detail.php?id=<?= $post['id'] ?>">
                                            <?php if (strpos($post['file_type'], 'image/') === 0): ?>
                                                <img src="<?= htmlspecialchars($post['file_url']) ?>" class="card-img-top" alt="Media" style="height: 200px; object-fit: cover;">
                                            <?php elseif (strpos($post['file_type'], 'video/') === 0): ?>
                                                <div class="position-relative" style="height: 200px; background-color: #000;">
                                                    <div class="position-absolute top-50 start-50 translate-middle text-white">
                                                        <i class="fas fa-play-circle fa-3x"></i>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                        <div class="card-body">
                                            <p class="card-text small text-truncate">
                                                <?= htmlspecialchars($post['content']) ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><?= getTimeAgo($post['created_at']) ?></small>
                                                <div>
                                                    <span class="badge bg-primary"><i class="fas fa-thumbs-up"></i> <?= $post['likes'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="likes" role="tabpanel" aria-labelledby="likes-tab">
                    <!-- We'll skip this implementation for now -->
                    <div class="alert alert-info text-center">
                        Liked posts will appear here.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and other scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        // Initialize Read More functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeReadMore();
        });
    </script>
</body>
</html>
