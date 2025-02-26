<?php
// Start the session if it's not already started
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Optional: Unset the user identifier cookie if you want to log them out completely
// If you want users to remain identified for viewing content but just not logged in, 
// comment out the following lines
/*
if (isset($_COOKIE['user_identifier'])) {
    setcookie('user_identifier', '', time() - 3600, '/');
    unset($_COOKIE['user_identifier']);
}
*/

// Redirect to the homepage
header('Location: index.php');
exit;
?>
