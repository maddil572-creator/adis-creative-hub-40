<?php
/**
 * Installation Script for Portfolio Backend
 * Run this once to set up the database and initial configuration
 */

// Check if already installed
if (file_exists(__DIR__ . '/.installed')) {
    die('System already installed. Delete .installed file to reinstall.');
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$errors = [];
$success = [];

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    $errors[] = 'PHP 7.4 or higher is required. Current version: ' . PHP_VERSION;
}

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Required PHP extension '{$ext}' is not loaded";
    }
}

// Check if .env file exists
if (!file_exists(__DIR__ . '/.env')) {
    $errors[] = '.env file not found. Copy .env.example to .env and configure your settings.';
}

// Check upload directory
if (!is_dir(UPLOAD_PATH)) {
    if (!mkdir(UPLOAD_PATH, 0755, true)) {
        $errors[] = 'Cannot create upload directory: ' . UPLOAD_PATH;
    } else {
        $success[] = 'Upload directory created: ' . UPLOAD_PATH;
    }
} else {
    if (!is_writable(UPLOAD_PATH)) {
        $errors[] = 'Upload directory is not writable: ' . UPLOAD_PATH;
    } else {
        $success[] = 'Upload directory is writable: ' . UPLOAD_PATH;
    }
}

// Test database connection and create tables
if (empty($errors)) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $success[] = 'Database connection successful';
        
        // Read and execute schema
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        if ($schema) {
            // Split by semicolon and execute each statement
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^(--|\/\*|SET|START|COMMIT)/', $statement)) {
                    $conn->exec($statement);
                }
            }
            
            $success[] = 'Database tables created successfully';
        } else {
            $errors[] = 'Could not read database schema file';
        }
        
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Install Composer dependencies
if (empty($errors) && file_exists(__DIR__ . '/composer.json')) {
    $composer_output = [];
    $composer_return = 0;
    
    exec('cd ' . __DIR__ . ' && composer install --no-dev --optimize-autoloader 2>&1', $composer_output, $composer_return);
    
    if ($composer_return === 0) {
        $success[] = 'Composer dependencies installed';
    } else {
        $errors[] = 'Composer installation failed. Please run "composer install" manually.';
    }
}

// Create .htaccess for API
$htaccess_content = '
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Prevent access to sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>
';

if (file_put_contents(__DIR__ . '/.htaccess', $htaccess_content)) {
    $success[] = '.htaccess file created';
} else {
    $errors[] = 'Could not create .htaccess file';
}

// Mark as installed if no errors
if (empty($errors)) {
    file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
    $success[] = 'Installation completed successfully!';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Backend Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen py-12">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 flex items-center justify-center rounded-full bg-blue-600 mb-4">
                    <i class="fas fa-cog text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900">Portfolio Backend Installation</h1>
                <p class="text-gray-600 mt-2">Setting up your portfolio management system</p>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <h3 class="font-bold mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Installation Errors
                </h3>
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <h3 class="font-bold mb-2">
                    <i class="fas fa-check-circle mr-2"></i>
                    Installation Progress
                </h3>
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($success as $item): ?>
                    <li><?php echo htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (empty($errors)): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
                <h3 class="font-bold mb-2">
                    <i class="fas fa-info-circle mr-2"></i>
                    Next Steps
                </h3>
                <ol class="list-decimal list-inside space-y-2">
                    <li>Access the admin panel: <a href="admin/login.php" class="underline font-semibold">admin/login.php</a></li>
                    <li>Default login: <code class="bg-gray-200 px-2 py-1 rounded">admin@adilgfx.com</code> / <code class="bg-gray-200 px-2 py-1 rounded">admin123</code></li>
                    <li><strong class="text-red-600">⚠️ Change the default password immediately!</strong></li>
                    <li>Configure your .env file with your actual settings</li>
                    <li>Set up your SMTP email settings for form notifications</li>
                    <li>Upload your portfolio content and customize your pages</li>
                </ol>
            </div>
            
            <div class="text-center">
                <a href="admin/login.php" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Access Admin Panel
                </a>
            </div>
            <?php else: ?>
            <div class="text-center">
                <p class="text-gray-600 mb-4">Please fix the errors above and refresh this page to continue.</p>
                <button onclick="location.reload()" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fas fa-redo mr-2"></i>
                    Retry Installation
                </button>
            </div>
            <?php endif; ?>
            
            <div class="mt-8 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
                <p>Portfolio Backend System v1.0</p>
                <p>Compatible with Hostinger PHP + MySQL hosting</p>
            </div>
        </div>
    </div>
</body>
</html>