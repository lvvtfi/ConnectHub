<?php
// includes/ContentAnalyzer.php

class ContentAnalyzer {
    // Kategori untuk pencocokkan kata kunci
    private $categories = [
        'Politics' => ['ucup'],
        'Technology' => ['ujang'],
        'Entertainment' => ['eji'],
        'Sports' => ['ojan'],
        'Science' => ['afdhal'],
        'Health' => ['maul']
    ];
    
    // Pemetaan kategori Indonesia ke Inggris (untuk hasil API)
    private $categoryMapping = [
        // Indonesia => English
        'Politik' => 'Politics',
        'Teknologi' => 'Technology',
        'Hiburan' => 'Entertainment',
        'Olahraga' => 'Sports',
        'Sains' => 'Science',
        'Kesehatan' => 'Health',
        'Pendidikan' => 'Education',
        'Bisnis' => 'Business',
        'Seni' => 'Art',
        'Travel' => 'Travel',
        'Makanan' => 'Food',
        'Fashion' => 'Fashion'
    ];
    
    // Gemini API configuration
    private $gemini_api_key;
    private $use_ai;
    
    public function __construct($gemini_api_key = null) {
        // If API key is provided, use AI for categorization
        $this->gemini_api_key = $gemini_api_key;
        $this->use_ai = !empty($gemini_api_key);
        
        if ($this->use_ai) {
            error_log("AI-based categorization enabled using Google Gemini 2.0 Flash");
        } else {
            error_log("Using keyword-based categorization (AI disabled)");
        }
    }
    
    /**
     * Analyze content and determine categories using AI or keywords
     * 
     * @param string $content The post content to analyze
     * @return array List of category names that match the content
     */
    public function analyzeContent($content) {
        if ($this->use_ai) {
            $aiCategories = $this->analyzeContentWithGemini($content);
            if (!empty($aiCategories) && $aiCategories[0] !== 'Uncategorized') {
                error_log("Using AI-determined categories: " . implode(", ", $aiCategories));
                return $aiCategories;
            }
            error_log("AI categorization failed, falling back to keyword analysis");
        }
        
        // Fallback to keyword analysis if AI fails or is not enabled
        return $this->analyzeContentWithKeywords($content);
    }
    
    /**
     * Analyze content using Google Gemini API
     * 
     * @param string $content The post content to analyze
     * @return array List of category names that match the content
     */
    private function analyzeContentWithGemini($content) {
        // Truncate content if too long
        $truncated_content = substr($content, 0, 1000);
        
        try {
            error_log("Analyzing content with Gemini AI: " . substr($truncated_content, 0, 100) . "...");
            
            // Prepare the API request
            $response = $this->callGeminiAPI($truncated_content);
            
            // Check if response contains error
            if (isset($response['error'])) {
                error_log("Gemini API error: " . json_encode($response['error']));
                return ['Uncategorized'];
            }
            
            // Check for valid response structure
            if (isset($response['candidates']) && 
                !empty($response['candidates']) && 
                isset($response['candidates'][0]['content']) && 
                isset($response['candidates'][0]['content']['parts']) && 
                !empty($response['candidates'][0]['content']['parts']) && 
                isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                
                $aiResponse = $response['candidates'][0]['content']['parts'][0]['text'];
                error_log("Gemini API response: " . $aiResponse);
                
                // Parse the category predictions from the AI response
                $categories = $this->parseCategoriesFromAIResponse($aiResponse);
                
                if (empty($categories)) {
                    error_log("No categories found in AI response");
                    return ['Uncategorized'];
                }
                
                // Map categories from Indonesian to English if needed
                $mappedCategories = $this->mapCategoriesToEnglish($categories);
                
                error_log("Gemini returned categories: " . implode(", ", $categories) . 
                         " -> Mapped to: " . implode(", ", $mappedCategories));
                
                return $mappedCategories;
            } else {
                error_log("Invalid Gemini API response structure: " . json_encode($response));
                return ['Uncategorized'];
            }
        } catch (Exception $e) {
            error_log("Error in Gemini AI categorization: " . $e->getMessage());
            return ['Uncategorized'];
        }
    }
    
    /**
     * Map categories from Indonesian to English using the categoryMapping
     *
     * @param array $categories List of categories to map
     * @return array Mapped categories in English
     */
    private function mapCategoriesToEnglish($categories) {
        $mappedCategories = [];
        
        foreach ($categories as $category) {
            // Check if this category needs mapping
            if (isset($this->categoryMapping[$category])) {
                $mappedCategories[] = $this->categoryMapping[$category];
                error_log("Mapped category: $category -> " . $this->categoryMapping[$category]);
            } else {
                // If no mapping exists, keep the original category
                $mappedCategories[] = $category;
                error_log("No mapping for category: $category, keeping original");
            }
        }
        
        return $mappedCategories;
    }
    
    /**
     * Make an API call to Google Gemini
     * 
     * @param string $content Content to analyze
     * @return array API response
     */
    private function callGeminiAPI($content) {
        // Using gemini-2.0-flash model
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $this->gemini_api_key;
        
        error_log("Using Gemini API endpoint with model gemini-2.0-flash: " . $url);
        
        // PENTING: Beritahu model untuk mengembalikan kategori dalam bahasa Inggris!
        $prompt = "Analyze the following text and categorize it into one or more of these categories: Politics, Technology, Entertainment, Sports, Science, Health, Business, Education, Art, Travel, Food, Fashion. Only return the most relevant categories (maximum 3) as a comma-separated list. For example: 'Technology, Science' or 'Politics, Business'. DO NOT include any other text or explanation in your response. Content to analyze: " . $content;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 50
            ]
        ];
        
        // Debug request
        error_log("Gemini API request data: " . json_encode($data));
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
        
        // Enable verbose debugging for curl
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // Log HTTP status
        error_log("Gemini API HTTP status: " . $httpCode);
        
        // If there was an error, log the verbose output
        if ($httpCode != 200 || $error) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            error_log("Gemini API verbose log: " . $verboseLog);
            error_log("Gemini API raw response: " . $response);
        }
        
        if ($error) {
            error_log("Gemini API cURL error: " . $error);
            curl_close($ch);
            throw new Exception("cURL Error: $error");
        }
        
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            throw new Exception("Failed to parse JSON response: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
    
    /**
     * Parse the AI response to extract categories
     * 
     * @param string $aiResponse The response from the AI API
     * @return array List of category names
     */
    private function parseCategoriesFromAIResponse($aiResponse) {
        // Clean up the response
        $aiResponse = trim($aiResponse);
        
        // Split by commas to get individual categories
        $categories = array_map('trim', explode(',', $aiResponse));
        
        // Filter out any empty entries and limit to maximum of 3 categories
        $categories = array_filter($categories);
        $categories = array_slice($categories, 0, 3);
        
        error_log("AI parsed categories: " . implode(', ', $categories));
        return $categories;
    }
    
    /**
     * Original keyword-based content analysis (as fallback)
     * 
     * @param string $content The post content to analyze
     * @return array List of category names that match the content
     */
    private function analyzeContentWithKeywords($content) {
        $content = strtolower($content);
        $matchedCategories = [];
        
        // Log the content for debugging
        error_log("Analyzing content with keywords: " . substr($content, 0, 100) . "...");
        
        // Make sure $this->categories is defined and is an array
        if (!isset($this->categories) || !is_array($this->categories)) {
            error_log("Categories array is not properly defined");
            return ['Uncategorized'];
        }
        
        foreach ($this->categories as $category => $keywords) {
            $matches = 0;
            $matchedKeywords = [];
            
            if (!is_array($keywords)) {
                error_log("Keywords for category {$category} is not an array");
                continue;
            }
            
            foreach ($keywords as $keyword) {
                $keywordLower = strtolower($keyword);
                // Improved word boundary detection for better accuracy
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
        
        if (empty($topCategories)) {
            error_log("No categories detected through keyword analysis");
            return ['Uncategorized'];
        }
        
        error_log("Top categories detected: " . implode(', ', $topCategories));
        return $topCategories;
    }
    
    /**
     * Debug analysis of content
     * 
     * @param string $content The content to analyze
     * @return array Debug information about the categorization
     */
    public function debugAnalysis($content) {
        if ($this->use_ai) {
            try {
                $truncated_content = substr($content, 0, 1000);
                $response = $this->callGeminiAPI($truncated_content);
                
                // Check if response contains error
                if (isset($response['error'])) {
                    return [
                        'method' => 'Gemini AI-based categorization (failed)',
                        'error' => $response['error']['message'] ?? 'Unknown error',
                        'api_response' => $response,
                        'fallback_result' => $this->debugKeywordAnalysis($content)
                    ];
                }
                
                // Check response structure
                if (isset($response['candidates']) && 
                    !empty($response['candidates']) && 
                    isset($response['candidates'][0]['content']) && 
                    isset($response['candidates'][0]['content']['parts']) && 
                    !empty($response['candidates'][0]['content']['parts']) && 
                    isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                    
                    $categories = $this->parseCategoriesFromAIResponse($response['candidates'][0]['content']['parts'][0]['text']);
                    $mappedCategories = $this->mapCategoriesToEnglish($categories);
                    
                    return [
                        'method' => 'Gemini AI-based categorization',
                        'api_response' => $response,
                        'categories' => $categories,
                        'mapped_categories' => $mappedCategories
                    ];
                } else {
                    return [
                        'method' => 'Gemini AI-based categorization (invalid response)',
                        'api_response' => $response,
                        'error' => 'Invalid response structure',
                        'fallback_result' => $this->debugKeywordAnalysis($content)
                    ];
                }
            } catch (Exception $e) {
                return [
                    'method' => 'Gemini AI-based categorization (failed)',
                    'error' => $e->getMessage(),
                    'fallback_result' => $this->debugKeywordAnalysis($content)
                ];
            }
        } else {
            return $this->debugKeywordAnalysis($content);
        }
    }
    
    /**
     * Debug keyword analysis of content
     * 
     * @param string $content The content to analyze
     * @return array Debug information about keyword matches
     */
    private function debugKeywordAnalysis($content) {
        $content = strtolower($content);
        $results = [];
        
        // Make sure $this->categories is defined and is an array
        if (!isset($this->categories) || !is_array($this->categories)) {
            error_log("Categories array is not properly defined in debugKeywordAnalysis");
            return ['method' => 'Keyword-based categorization', 'categories' => []];
        }
        
        foreach ($this->categories as $category => $keywords) {
            $matches = [];
            
            if (!is_array($keywords)) {
                error_log("Keywords for category {$category} is not an array in debugKeywordAnalysis");
                continue;
            }
            
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
        
        return [
            'method' => 'Keyword-based categorization',
            'categories' => $results
        ];
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
     * Assign categories to a post based on content analysis
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
            
            return $assignedCategories;
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error in assignCategoriesToPost: " . $e->getMessage());
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
