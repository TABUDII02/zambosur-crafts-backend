<?php
/**
 * Router script for PHP built-in server
 * This handles all URL routing for the API
 */

// Route the request to index.php
$url = parse_url($_SERVER['REQUEST_URI']);
$path = $url['path'];

// Remove the leading path to the backend folder
$script = __DIR__ . '/index.php';

// Serve the file if it exists
if (file_exists($script)) {
    // Set the request URI so index.php can parse it
    $_SERVER['REQUEST_URI'] = $path;
    require $script;
} else {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
}
