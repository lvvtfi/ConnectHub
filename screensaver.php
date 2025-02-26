<?php
// Include the database connection file
include 'includes/db.php';

// Retrieve the user identifier from the cookie, or set to empty if not found
$userIdentifier = $_COOKIE['user_identifier'] ?? '';

// Validate that the user identifier is a 32-character hexadecimal string
if (!preg_match('/^[a-f0-9]{32}$/', $userIdentifier)) {
    // If the identifier is invalid, send a 400 Bad Request response with an error message
    http_response_code(400);
    echo json_encode(['error' => 'User identifier is missing or invalid']);
    exit;
}

// SQL query to select posts with the count of likes and dislikes,
// excluding posts marked as 'not_interested' by the user
$query = "
    SELECT p.id, p.content, p.image_url,
           SUM(CASE WHEN i.action = 'like' THEN 1 ELSE 0 END) AS likes,
           SUM(CASE WHEN i.action = 'dislike' THEN 1 ELSE 0 END) AS dislikes
    FROM posts p
    LEFT JOIN interactions i ON p.id = i.post_id
    LEFT JOIN interactions u ON p.id = u.post_id AND u.user_identifier = :user AND u.action = 'not_interested'
    WHERE u.post_id IS NULL
    GROUP BY p.id
    ORDER BY likes DESC
    LIMIT 5
";

// Prepare and execute the query with the user identifier as a parameter
$stmt = $pdo->prepare($query);
$stmt->execute(['user' => $userIdentifier]);

// Fetch the results as an associative array
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return the posts as a JSON response
echo json_encode($posts);
?>
