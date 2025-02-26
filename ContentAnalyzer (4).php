<?php
// includes/ContentAnalyzer.php

class ContentAnalyzer {
    // Enhanced keywords for better category detection
    private $categories = [
       'Politics' => [
            // Indonesian and English keywords for Politics
            'politik', 'pemerintah', 'pemilu', 'presiden', 'demokrasi', 'parlemen', 'voting', 
            'campaign', 'partai', 'legislatif', 'gubernur', 'bupati', 'walikota', 'menteri', 
            'kabinet', 'konstitusi', 'undang-undang', 'propaganda', 'korupsi', 'rakyat', 
            'negara', 'perpolitikan', 'perang', 'konflik', 'oposisi', 'koalisi', 'kampanye',
            'pemilihan', 'kebijakan', 'dpr', 'dprd', 'mpr', 'parpol', 'subsidi', 'demonstrasi',
            'referendum', 'anggaran', 'pancasila', 'uu', 'kpu', 'pilkada', 'pilpres', 'debat',
            'legislation', 'government', 'president', 'election', 'vote', 'policy', 'politics',
            'party', 'parliament', 'opposition', 'coalition', 'corruption', 'democracy', 'ballot',
            'political', 'referendum', 'candidates', 'lobby', 'congress', 'senate', 'civil rights',
            'vote count', 'political campaign'
        ],
        
        'Technology' => [
            // Indonesian and English keywords for Technology
            'teknologi', 'komputer', 'software', 'hardware', 'internet', 'aplikasi', 'digital', 
            'kode', 'programming', 'ai', 'artificial intelligence', 'smartphone', 'website', 
            'online', 'startup', 'gadget', 'elektronik', 'inovasi', 'robot', 'otomasi', 
            'programming', 'coding', 'developer', 'engineer', 'algoritma', 'database', 'server',
            'cloud', 'cyber', 'app', 'mobile', 'web', 'program', 'komputing', 'interface',
            'perangkat lunak', 'sistem operasi', 'microsoft', 'google', 'apple', 'android',
            'ios', 'windows', 'laptop', 'pc', 'processor', 'ram', 'storage', 'virtual reality',
            'vr', 'augmented reality', 'ar', 'chip', 'firmware', 'encryption', 'password',
            'security', 'keamanan digital', 'hacker', 'coding', 'network', 'jaringan', 'update',
            'social media', 'media sosial', 'platform', 'game', 'gaming', 'tech', 'software engineer',
            'machine learning', 'blockchain', 'iot', 'internet of things', 'cloud computing', 
            'big data', 'data science', 'tech news', 'web development', 'cybersecurity', 'ai research',
            'openai', 'chatgpt', 'gpt-4', 'dall-e', 'stable diffusion', 'midjourney', 'deep learning',
            'neural networks', 'tensorflow', 'python', 'keras', 'automated systems', 'nvidia', 'quantum computing',
            'artificial general intelligence', 'ai ethics', 'data privacy', 'augmented intelligence', 'chatbot',
            // Added AI tools
            'deepseak', 'ngrok', 'claude', 'midjourney', 'huggingface', 'bard', 'bing ai', 'openai api',
            'chatgpt api', 'deepmind', 'alpha fold', 'cortex', 'eleutherai', 'cerebras', 'cogito', 'stable diffusion',
            'x.ai', 'replit ai', 'leo ai', 'datasaur', 'perplexity ai', 'pathfinder', 'nvidia ai', 'sentient ai'
        ],
        
        'Entertainment' => [
            // Indonesian and English keywords for Entertainment
            'film', 'musik', 'konser', 'selebriti', 'artis', 'aktor', 'aktris', 'televisi', 
            'tv', 'show', 'hiburan', 'drama', 'komedi', 'penyanyi', 'lagu', 'band', 'bioskop', 
            'serial', 'sinetron', 'idol', 'bintang', 'panggung', 'teater', 'reality show',
            'movie', 'cinema', 'hollywood', 'netflix', 'streaming', 'tiktok', 'youtube',
            'youtuber', 'influencer', 'celebrity', 'entertainer', 'penghargaan', 'award',
            'oscar', 'festival', 'live', 'perform', 'pertunjukan', 'k-pop', 'pop', 'rock',
            'jazz', 'dangdut', 'anime', 'manga', 'kartun', 'podcast', 'box office', 'rating',
            'music festival', 'concert tour', 'tv show', 'awards', 'entertainment industry', 
            'celebrity gossip', 'tiktok influencer', 'comedy show', 'live performances', 'broadway'
        ],
        
        'Sports' => [
            // Indonesian and English keywords for Sports
            'olahraga', 'sepak bola', 'bola', 'basket', 'tenis', 'atlet', 'pertandingan', 
            'turnamen', 'kejuaraan', 'liga', 'stadion', 'pemain', 'pelatih', 'tim', 'juara', 
            'medali', 'kompetisi', 'perlombaan', 'gol', 'poin', 'skor', 'piala dunia', 'olimpiade',
            'football', 'soccer', 'basketball', 'baseball', 'volleyball', 'badminton', 'silat',
            'martial arts', 'boxing', 'tinju', 'swimming', 'renang', 'running', 'marathon',
            'athlete', 'coach', 'referee', 'wasit', 'transfer', 'club', 'gym', 'fitness',
            'stadium', 'world cup', 'euro', 'champions', 'premier league', 'serie a', 'la liga',
            'rugby', 'ice hockey', 'e-sports', 'motorsport', 'f1', 'motogp', 'tour de france', 'olympics', 'tennis grand slam'
        ],
        
        'Science' => [
            // Indonesian and English keywords for Science
            'sains', 'ilmu', 'penelitian', 'eksperimen', 'laboratorium', 'penemuan', 'inovasi', 
            'fisika', 'kimia', 'biologi', 'astronomi', 'ilmuwan', 'teori', 'hipotesis', 'bukti', 
            'analisis', 'riset', 'metode', 'atom', 'molekul', 'galaksi', 'spesies', 'science',
            'scientific', 'discovery', 'experiment', 'theory', 'hypothesis', 'nasa', 'spacecraft',
            'telescope', 'microscope', 'genome', 'dna', 'evolution', 'particle', 'quantum',
            'relativity', 'planet', 'solar system', 'tata surya', 'biotechnology', 'genetika',
            'vaccine', 'vaksin', 'virus', 'bacteria', 'climate change', 'perubahan iklim',
            'carbon', 'karbon', 'energy', 'energi', 'fossils', 'dinosaur', 'species', 'botany',
            'zoology', 'geology', 'neuroscience', 'exoplanet', 'physics', 'chemistry', 'biology',
            'nanotechnology', 'stem cell', 'genomics', 'neuron', 'space exploration', 'black hole',
            'supernova', 'gravitational waves', 'dark matter', 'quantum physics', 'astronaut', 'space station'
        ],
        
        'Health' => [
            // Indonesian and English keywords for Health
            'kesehatan', 'fitness', 'wellness', 'penyakit', 'obat', 'dokter', 'rumah sakit', 
            'virus', 'diet', 'nutrisi', 'vitamin', 'vaksin', 'imunisasi', 'pandemi', 
            'epidemi', 'medis', 'terapi', 'gizi', 'sehat', 'sakit', 'pasien', 'pengobatan',
            'medical', 'medicine', 'hospital', 'clinic', 'klinik', 'pharmacy', 'apotek',
            'medication', 'surgery', 'operasi', 'mental health', 'kesehatan mental', 'anxiety',
            'depression', 'depresi', 'stress', 'diabetes', 'cancer', 'kanker', 'heart disease',
            'penyakit jantung', 'stroke', 'cholesterol', 'kolesterol', 'blood pressure',
            'tekanan darah', 'fitness', 'workout', 'exercise', 'rehabilitation', 'rehab',
            'nutrition', 'healthy eating', 'healthcare', 'pills', 'prescription', 'health issues'
        ]
    ];
    
    /**
     * Analyze content and determine categories with improved detection
     * 
     * @param string $content The post content to analyze
     * @return array List of category names that match the content
     */
    public function analyzeContent($content) {
        $content = strtolower($content);
        $matchedCategories = [];
        
        // Log the content for debugging
        error_log("Analyzing content: " . substr($content, 0, 100) . "...");
        
        foreach ($this->categories as $category => $keywords) {
            $matches = 0;
            $matchedKeywords = [];
            
            foreach ($keywords as $keyword) {
                $keywordLower = strtolower($keyword);
                // Improved word boundary detection for better accuracy
                // This checks for full words rather than partial matches
                if (preg_match('/\b' . preg_quote($keywordLower, '/') . '\b/iu', $content)) {
                    $matches++;
                    $matchedKeywords[] = $keyword;
                }
            }
            
            // Get a relevance score based on matches and content length
            $contentWords = str_word_count($content);
            $relevanceScore = $contentWords > 0 ? ($matches / sqrt($contentWords)) * 10 : 0;
            
            // Log match details for debugging
            if ($matches > 0) {
                error_log("Category {$category}: {$matches} keyword matches, relevance score: {$relevanceScore}");
                error_log("Matched keywords: " . implode(', ', $matchedKeywords));
                
                // If we have matches, add this category with its score
                $matchedCategories[$category] = [
                    'score' => $relevanceScore,
                    'matches' => $matches,
                    'keywords' => $matchedKeywords
                ];
            }
        }
        
        // Sort by relevance score (descending)
        uasort($matchedCategories, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return top 3 categories at most
        $topCategories = array_slice(array_keys($matchedCategories), 0, 3);
        error_log("Top categories detected: " . implode(', ', $topCategories));
        
        return $topCategories;
    }
    
    /**
     * Debug analysis of content
     * 
     * @param string $content The content to analyze
     * @return array Debug information about keyword matches
     */
    public function debugAnalysis($content) {
        $content = strtolower($content);
        $results = [];
        
        foreach ($this->categories as $category => $keywords) {
            $matches = [];
            foreach ($keywords as $keyword) {
                $keywordLower = strtolower($keyword);
                if (preg_match('/\b' . preg_quote($keywordLower, '/') . '\b/iu', $content)) {
                    $matches[] = $keyword;
                }
            }
            
            if (!empty($matches)) {
                $results[$category] = [
                    'count' => count($matches),
                    'keywords' => $matches
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get category ID by name, creating it if it doesn't exist
     * 
     * @param PDO $pdo Database connection
     * @param string $categoryName Name of the category
     * @return int Category ID
     */
    public function getCategoryIdByName($pdo, $categoryName) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->execute([$categoryName]);
            $id = $stmt->fetchColumn();
            
            if (!$id) {
                // Create new category if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$categoryName]);
                $id = $pdo->lastInsertId();
                error_log("Created new category: {$categoryName} with ID {$id}");
            }
            
            return $id;
        } catch (PDOException $e) {
            error_log("Error getting/creating category: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Assign categories to a post based on content analysis with improved error handling
     * 
     * @param PDO $pdo Database connection
     * @param int $postId Post ID
     * @param string $content Post content
     * @return array List of assigned category names
     */
    public function assignCategoriesToPost($pdo, $postId, $content) {
        $categories = $this->analyzeContent($content);
        $assignedCategories = [];
        
        try {
            // Begin transaction to ensure all categories are assigned
            $pdo->beginTransaction();
            
            // First, clear any existing categories for this post to prevent duplicates
            $clearStmt = $pdo->prepare("DELETE FROM post_categories WHERE post_id = ?");
            $clearStmt->execute([$postId]);
            error_log("Cleared existing categories for post {$postId}");
            
            foreach ($categories as $category) {
                $categoryId = $this->getCategoryIdByName($pdo, $category);
                
                // Link post to category
                $stmt = $pdo->prepare("INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)");
                $stmt->execute([$postId, $categoryId]);
                $assignedCategories[] = $category;
                
                error_log("Assigned category {$category} (ID: {$categoryId}) to post {$postId}");
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Double-check the assignments
            $checkStmt = $pdo->prepare("
                SELECT c.id, c.name 
                FROM categories c
                JOIN post_categories pc ON c.id = pc.category_id
                WHERE pc.post_id = ?
            ");
            $checkStmt->execute([$postId]);
            $actualCategories = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Verified categories for post {$postId}: " . json_encode($actualCategories));
            
            return $assignedCategories;
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error in assignCategoriesToPost: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get categories for a post
     * 
     * @param PDO $pdo Database connection
     * @param int $postId Post ID
     * @return array List of categories
     */
    public function getCategoriesForPost($pdo, $postId) {
        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.name
                FROM categories c
                JOIN post_categories pc ON c.id = pc.category_id
                WHERE pc.post_id = ?
            ");
            $stmt->execute([$postId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting categories for post: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all available categories
     * 
     * @param PDO $pdo Database connection
     * @return array List of all categories
     */
    public function getAllCategories($pdo) {
        try {
            $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting all categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get posts for a category
     * 
     * @param PDO $pdo Database connection
     * @param int $categoryId Category ID
     * @param string $userIdentifier User identifier for filtering
     * @param int $limit Maximum number of posts to return
     * @return array List of posts
     */
    public function getPostsForCategory($pdo, $categoryId, $userIdentifier, $limit = 10) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    p.id, 
                    p.content, 
                    p.file_url, 
                    p.file_type, 
                    p.created_at, 
                    u.username,
                    COUNT(DISTINCT CASE WHEN i.action = 'like' THEN i.id END) as likes,
                    COUNT(DISTINCT CASE WHEN i.action = 'dislike' THEN i.id END) as dislikes
                FROM posts p
                JOIN post_categories pc ON p.id = pc.post_id
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN interactions i ON p.id = i.post_id
                WHERE pc.category_id = ?
                AND p.id NOT IN (
                    SELECT post_id FROM interactions 
                    WHERE user_identifier = ? AND action = 'not_interested'
                )
                GROUP BY p.id, p.content, p.file_url, p.file_type, p.created_at, u.username
                ORDER BY p.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$categoryId, $userIdentifier, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting posts for category: " . $e->getMessage());
            return [];
        }
    }
}