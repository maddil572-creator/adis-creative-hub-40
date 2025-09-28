<?php
/**
 * API Router - Main entry point for all API requests
 */

header('Content-Type: application/json');

// Include configuration and classes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Set CORS headers
setCorsHeaders();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parse the request
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = dirname($script_name);

// Remove base path from request URI
$path = str_replace($base_path, '', $request_uri);
$path = trim($path, '/');

// Remove query string
$path = strtok($path, '?');

// Split path into segments
$segments = explode('/', $path);

// Remove 'api' from segments if present
if ($segments[0] === 'api') {
    array_shift($segments);
}

$endpoint = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? '';

$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$data = array_merge($_GET, $_POST, $input);

// Response helper function
function sendResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

// Error handler
function sendError($message, $status_code = 400) {
    sendResponse(['success' => false, 'message' => $message], $status_code);
}

try {
    // Route to appropriate handler
    switch ($endpoint) {
        case 'auth':
            require_once __DIR__ . '/auth.php';
            break;
            
        case 'portfolio':
            require_once __DIR__ . '/portfolio.php';
            break;
            
        case 'services':
            require_once __DIR__ . '/services.php';
            break;
            
        case 'blog':
            require_once __DIR__ . '/blog.php';
            break;
            
        case 'testimonials':
            require_once __DIR__ . '/testimonials.php';
            break;
            
        case 'forms':
            require_once __DIR__ . '/forms.php';
            break;
            
        case 'media':
            require_once __DIR__ . '/media.php';
            break;
            
        case 'pages':
            require_once __DIR__ . '/pages.php';
            break;
            
        case 'settings':
            require_once __DIR__ . '/settings.php';
            break;
            
        case 'dashboard':
            require_once __DIR__ . '/dashboard.php';
            break;
            
        default:
            sendError('Endpoint not found', 404);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendError('Internal server error', 500);
}
?>