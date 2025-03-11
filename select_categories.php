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

// Group categories by first letter for better organization
$categoriesByLetter = [];
foreach ($allCategories as $category) {
    $firstLetter = strtoupper(substr($category['name'], 0, 1));
    if (!isset($categoriesByLetter[$firstLetter])) {
        $categoriesByLetter[$firstLetter] = [];
    }
    $categoriesByLetter[$firstLetter][] = $category;
}
ksort($categoriesByLetter); // Sort by letter

// Get post counts for each category
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
    
    // Add post count to the category in the grouped array
    foreach ($categoriesByLetter as $letter => &$cats) {
        foreach ($cats as &$cat) {
            if ($cat['id'] === $category['id']) {
                $cat['post_count'] = $result['post_count'];
                break 2;
            }
        }
    }
}

// Get selected categories from session or URL
$selectedCategories = isset($_GET['categories']) ? 
    (is_array($_GET['categories']) ? $_GET['categories'] : [$_GET['categories']]) : 
    [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Categories - Social Media</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
        }
        .category-section {
            margin-bottom: 2rem;
        }
        .category-letter {
            background-color: #3498db;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        .category-checkbox {
            cursor: pointer;
        }
        .category-item {
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .category-item:hover {
            background-color: #f8f9fa;
        }
        .category-item.selected {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        .sticky-buttons {
            position: sticky;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-top: 1px solid #dee2e6;
            padding: 15px 0;
            z-index: 1000;
        }
        .alphabet-nav {
            position: sticky;
            top: 10px;
            z-index: 1000;
        }
        .alphabet-link {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            margin: 2px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.2s ease;
        }
        .alphabet-link:hover {
            background-color: #e9ecef;
        }
        .alphabet-link.active {
            background-color: #007bff;
            color: white;
        }
        .badge-count {
            min-width: 30px;
        }
    </style>
</head>
<body>
    <header class="py-3 bg-white shadow-sm mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="mb-0">Select Categories</h1>
            <div>
                <a href="index.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="categories.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-list"></i> Categories
                </a>
                <?php if (isLoggedIn()): ?>
                    <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary me-2">Login</a>
                    <a href="signup.php" class="btn btn-outline-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container mb-5 pb-5">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title">Select Multiple Categories</h2>
                        <p class="card-text">
                            Select multiple categories to view posts from any of those categories.
                            When you're done selecting, click the "View Selected Categories" button to see matching posts.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 col-lg-2 d-none d-md-block">
                <div class="alphabet-nav">
                    <div class="card">
                        <div class="card-body p-2">
                            <div class="d-flex flex-wrap justify-content-center">
                                <?php foreach (array_keys($categoriesByLetter) as $letter): ?>
                                <a href="#letter-<?= $letter ?>" class="alphabet-link">
                                    <?= $letter ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <form id="categoryForm" action="index.php" method="GET">
                    <?php foreach ($categoriesByLetter as $letter => $categories): ?>
                        <div class="category-section" id="letter-<?= $letter ?>">
                            <div class="d-flex align-items-center mb-3">
                                <div class="category-letter"><?= $letter ?></div>
                                <h3 class="mb-0">Categories starting with <?= $letter ?></h3>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($categories as $category): ?>
                                        <div class="list-group-item category-item <?= in_array($category['id'], $selectedCategories) ? 'selected' : '' ?>">
                                            <div class="form-check d-flex justify-content-between align-items-center">
                                                <div>
                                                    <input class="form-check-input category-checkbox" type="checkbox" 
                                                        name="categories[]" value="<?= $category['id'] ?>" 
                                                        id="category_<?= $category['id'] ?>"
                                                        <?= in_array($category['id'], $selectedCategories) ? 'checked' : '' ?>>
                                                    <label class="form-check-label ms-2" 
                                                        for="category_<?= $category['id'] ?>">
                                                        <?= htmlspecialchars($category['name']) ?>
                                                    </label>
                                                </div>
                                                <span class="badge bg-secondary rounded-pill badge-count">
                                                    <?= $category['post_count'] ?? 0 ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="sticky-buttons">
                        <div class="container">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="selected-count">0</span> categories selected
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary me-2" id="clearBtn">
                                        Clear All
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="viewBtn" disabled>
                                        View Selected Categories
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
        const selectedCountEl = document.querySelector('.selected-count');
        const viewBtn = document.getElementById('viewBtn');
        const clearBtn = document.getElementById('clearBtn');
        const categoryForm = document.getElementById('categoryForm');
        
        // Update selected count
        function updateSelectedCount() {
            const checkedCount = document.querySelectorAll('.category-checkbox:checked').length;
            selectedCountEl.textContent = checkedCount;
            
            // Update item styles
            categoryCheckboxes.forEach(checkbox => {
                const item = checkbox.closest('.category-item');
                if (checkbox.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
            
            // Enable/disable view button
            viewBtn.disabled = checkedCount === 0;
        }
        
        // Add event listeners
        categoryCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        clearBtn.addEventListener('click', function() {
            categoryCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        });
        
        // Initialize count
        updateSelectedCount();
        
        // Make alphabet links active when scrolling
        const sectionElements = document.querySelectorAll('.category-section');
        const alphabetLinks = document.querySelectorAll('.alphabet-link');
        
        window.addEventListener('scroll', function() {
            let currentSection = '';
            
            sectionElements.forEach(section => {
                const sectionTop = section.offsetTop;
                if (window.scrollY >= sectionTop - 200) {
                    currentSection = section.getAttribute('id');
                }
            });
            
            alphabetLinks.forEach(link => {
                const linkHref = link.getAttribute('href').substring(1); // Remove #
                if (linkHref === currentSection) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    });
    </script>
</body>
</html>
