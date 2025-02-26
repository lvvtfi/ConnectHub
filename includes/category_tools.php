<?php
// Utility functions for fixing category issues
// Save as includes/category_tools.php

class CategoryTools {
    private $pdo;
    private $contentAnalyzer;
    
    public function __construct($pdo, $contentAnalyzer) {
        $this->pdo = $pdo;
        $this->contentAnalyzer = $contentAnalyzer;
    }
    
    /**
     * Check database structure for category tables
     * 
     * @return array Status information
     */
    public function checkDatabaseStructure() {
        $results = [
            'status' => 'ok',
            'messages' => []
        ];
        
        try {
            // Check categories table
            $this->pdo->query("SELECT id, name FROM categories LIMIT 1");
            $results['messages'][] = "Categories table exists and is accessible.";
            
            // Check post_categories table
            $this->pdo->query("SELECT post_id, category_id FROM post_categories LIMIT 1");
            $results['messages'][] = "Post_categories table exists and is accessible.";
            
            // Check foreign keys
            $stmt = $this->pdo->query("
                SELECT 
                    CONSTRAINT_NAME, 
                    TABLE_NAME, 
                    COLUMN_NAME, 
                    REFERENCED_TABLE_NAME, 
                    REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE 
                    TABLE_SCHEMA = DATABASE() AND
                    REFERENCED_TABLE_NAME IS NOT NULL AND
                    TABLE_NAME = 'post_categories'
            ");
            
            $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($foreignKeys) >= 2) {
                $results['messages'][] = "Foreign key constraints are properly set up.";
                foreach ($foreignKeys as $fk) {
                    $results['messages'][] = "- {$fk['CONSTRAINT_NAME']}: {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}";
                }
            } else {
                $results['status'] = 'warning';
                $results['messages'][] = "Warning: Foreign key constraints may not be properly set up.";
            }
            
        } catch (PDOException $e) {
            $results['status'] = 'error';
            $results['messages'][] = "Error checking database structure: " . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Fix database structure issues if needed
     * 
     * @return array Result of the operation
     */
    public function fixDatabaseStructure() {
        $results = [
            'status' => 'ok',
            'messages' => []
        ];
        
        try {
            // Check if categories table exists
            $categoriesExists = false;
            try {
                $this->pdo->query("SELECT 1 FROM categories LIMIT 1");
                $categoriesExists = true;
                $results['messages'][] = "Categories table already exists.";
            } catch (PDOException $e) {
                $results['messages'][] = "Categories table doesn't exist, will create it.";
            }
            
            // Create categories table if needed
            if (!$categoriesExists) {
                $this->pdo->exec("
                    CREATE TABLE `categories` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `name` varchar(50) NOT NULL,
                      `created_at` timestamp NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `name` (`name`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
                ");
                $results['messages'][] = "Created categories table.";
            }
            
            // Check if post_categories table exists
            $postCategoriesExists = false;
            try {
                $this->pdo->query("SELECT 1 FROM post_categories LIMIT 1");
                $postCategoriesExists = true;
                $results['messages'][] = "Post_categories table already exists.";
            } catch (PDOException $e) {
                $results['messages'][] = "Post_categories table doesn't exist, will create it.";
            }
            
            // Create post_categories table if needed
            if (!$postCategoriesExists) {
                $this->pdo->exec("
                    CREATE TABLE `post_categories` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `post_id` int(11) NOT NULL,
                      `category_id` int(11) NOT NULL,
                      `created_at` timestamp NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `post_category` (`post_id`,`category_id`),
                      KEY `category_id` (`category_id`),
                      CONSTRAINT `post_categories_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
                      CONSTRAINT `post_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
                ");
                $results['messages'][] = "Created post_categories table.";
            }
            
            // Add default categories if needed
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM categories");
            $categoryCount = $stmt->fetchColumn();
            
            if ($categoryCount == 0) {
                $defaultCategories = [
                    'Politics', 'Technology', 'Entertainment', 'Sports',
                    'Health', 'Education', 'Business', 'Science',
                    'Travel', 'Food', 'Fashion', 'Art'
                ];
                
                $stmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                
                foreach ($defaultCategories as $category) {
                    $stmt->execute([$category]);
                }
                
                $results['messages'][] = "Added " . count($defaultCategories) . " default categories.";
            }
            
        } catch (PDOException $e) {
            $results['status'] = 'error';
            $results['messages'][] = "Error fixing database structure: " . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Remove invalid post-category relations
     * 
     * @return array Result of the operation
     */
    public function cleanupInvalidRelations() {
        $results = [
            'status' => 'ok',
            'messages' => [],
            'deleted' => 0
        ];
        
        try {
            // Remove relations for non-existent posts
            $stmt = $this->pdo->prepare("
                DELETE FROM post_categories
                WHERE post_id NOT IN (SELECT id FROM posts)
            ");
            $stmt->execute();
            $deletedPosts = $stmt->rowCount();
            
            if ($deletedPosts > 0) {
                $results['messages'][] = "Removed $deletedPosts relations for non-existent posts.";
                $results['deleted'] += $deletedPosts;
            }
            
            // Remove relations for non-existent categories
            $stmt = $this->pdo->prepare("
                DELETE FROM post_categories
                WHERE category_id NOT IN (SELECT id FROM categories)
            ");
            $stmt->execute();
            $deletedCategories = $stmt->rowCount();
            
            if ($deletedCategories > 0) {
                $results['messages'][] = "Removed $deletedCategories relations for non-existent categories.";
                $results['deleted'] += $deletedCategories;
            }
            
            if ($results['deleted'] == 0) {
                $results['messages'][] = "No invalid relations found.";
            }
            
        } catch (PDOException $e) {
            $results['status'] = 'error';
            $results['messages'][] = "Error cleaning up invalid relations: " . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Recategorize all posts or a specific post
     * 
     * @param int|null $postId Optional post ID to recategorize, null for all posts
     * @return array Result of the operation
     */
    public function recategorizePosts($postId = null) {
        $results = [
            'status' => 'ok',
            'messages' => [],
            'categorized' => 0,
            'empty' => 0,
            'total' => 0
        ];
        
        try {
            // Get posts to recategorize
            if ($postId) {
                $stmt = $this->pdo->prepare("SELECT id, content FROM posts WHERE id = ?");
                $stmt->execute([$postId]);
            } else {
                $stmt = $this->pdo->query("SELECT id, content FROM posts");
            }
            
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results['total'] = count($posts);
            
            // Begin transaction
            $this->pdo->beginTransaction();
            
            foreach ($posts as $post) {
                // Clear existing categories for this post
                $stmt = $this->pdo->prepare("DELETE FROM post_categories WHERE post_id = ?");
                $stmt->execute([$post['id']]);
                
                // Analyze and categorize
                $categories = $this->contentAnalyzer->analyzeContent($post['content']);
                
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        // Get or create the category
                        $categoryId = $this->getOrCreateCategory($category);
                        
                        // Link post to category
                        $stmt = $this->pdo->prepare("
                            INSERT INTO post_categories (post_id, category_id)
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$post['id'], $categoryId]);
                    }
                    $results['categorized']++;
                    $categoryList = implode(', ', $categories);
                    $results['messages'][] = "Post ID {$post['id']} categorized as: $categoryList";
                } else {
                    $results['empty']++;
                }
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            if ($results['categorized'] > 0) {
                $results['messages'][] = "Successfully categorized {$results['categorized']} of {$results['total']} posts.";
            } else {
                $results['status'] = 'warning';
                $results['messages'][] = "No posts were categorized.";
            }
            
            if ($results['empty'] > 0) {
                $results['messages'][] = "{$results['empty']} posts had no detectable categories.";
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->pdo->rollBack();
            
            $results['status'] = 'error';
            $results['messages'][] = "Error recategorizing posts: " . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Get all posts with their categories
     * 
     * @param int $limit Maximum number of posts to return
     * @param int $offset Offset for pagination
     * @return array Posts with category information
     */
    public function getPostsWithCategories($limit = 20, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id, 
                    p.content, 
                    p.created_at,
                    u.username,
                    GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ',') as categories,
                    GROUP_CONCAT(c.id ORDER BY c.name SEPARATOR ',') as category_ids
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN post_categories pc ON p.id = pc.post_id
                LEFT JOIN categories c ON pc.category_id = c.id
                GROUP BY p.id, p.content, p.created_at, u.username
                ORDER BY p.created_at DESC
                LIMIT ?, ?
            ");
            
            $stmt->bindValue(1, (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting posts with categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get or create a category by name
     * 
     * @param string $categoryName Name of the category
     * @return int Category ID
     */
    private function getOrCreateCategory($categoryName) {
        // Try to get existing category
        $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$categoryName]);
        $categoryId = $stmt->fetchColumn();
        
        if (!$categoryId) {
            // Create new category
            $stmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$categoryName]);
            $categoryId = $this->pdo->lastInsertId();
        }
        
        return $categoryId;
    }
    
    /**
     * Get stats about categories
     * 
     * @return array Category statistics
     */
    public function getCategoryStats() {
        $stats = [
            'totalCategories' => 0,
            'totalPosts' => 0,
            'categorizedPosts' => 0,
            'uncategorizedPosts' => 0,
            'categoriesWithPosts' => 0,
            'emptyCategories' => 0,
            'topCategories' => []
        ];
        
        try {
            // Get total categories
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM categories");
            $stats['totalCategories'] = $stmt->fetchColumn();
            
            // Get total posts
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM posts");
            $stats['totalPosts'] = $stmt->fetchColumn();
            
            // Get categorized and uncategorized posts
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT post_id) FROM post_categories
            ");
            $stats['categorizedPosts'] = $stmt->fetchColumn();
            $stats['uncategorizedPosts'] = $stats['totalPosts'] - $stats['categorizedPosts'];
            
            // Get categories with and without posts
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(DISTINCT c.id) as total_categories,
                    COUNT(DISTINCT CASE WHEN pc.id IS NOT NULL THEN c.id END) as categories_with_posts
                FROM categories c
                LEFT JOIN post_categories pc ON c.id = pc.category_id
            ");
            $categoryCounts = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['categoriesWithPosts'] = $categoryCounts['categories_with_posts'];
            $stats['emptyCategories'] = $stats['totalCategories'] - $stats['categoriesWithPosts'];
            
            // Get top categories by post count
            $stmt = $this->pdo->query("
                SELECT c.id, c.name, COUNT(DISTINCT pc.post_id) as post_count
                FROM categories c
                JOIN post_categories pc ON c.id = pc.category_id
                GROUP BY c.id, c.name
                ORDER BY post_count DESC
                LIMIT 10
            ");
            $stats['topCategories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting category stats: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Get posts for a specific category
     * 
     * @param int $categoryId Category ID
     * @param int $limit Maximum number of posts to return
     * @return array Posts in this category
     */
    public function getPostsForCategory($categoryId, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id, 
                    p.content, 
                    p.created_at,
                    u.username
                FROM posts p
                JOIN post_categories pc ON p.id = pc.post_id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE pc.category_id = ?
                ORDER BY p.created_at DESC
                LIMIT ?
            ");
            
            $stmt->bindValue(1, (int)$categoryId, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting posts for category: " . $e->getMessage());
            return [];
        }
    }
}
