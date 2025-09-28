<?php
/**
 * Admin Panel - Main Dashboard
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();

// Check if user is authenticated
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$current_user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen">
        <!-- Sidebar -->
        <div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
             class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 text-white transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0">
            
            <!-- Logo -->
            <div class="flex items-center justify-center h-16 bg-gray-800">
                <h1 class="text-xl font-bold">Adil GFX Admin</h1>
            </div>
            
            <!-- Navigation -->
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="index.php" class="flex items-center px-4 py-2 text-sm font-medium bg-gray-800 rounded-lg">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard
                    </a>
                    
                    <a href="pages.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-file-alt mr-3"></i>
                        Pages
                    </a>
                    
                    <a href="portfolio.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-briefcase mr-3"></i>
                        Portfolio
                    </a>
                    
                    <a href="services.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-cogs mr-3"></i>
                        Services
                    </a>
                    
                    <a href="blog.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-blog mr-3"></i>
                        Blog
                    </a>
                    
                    <a href="testimonials.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-star mr-3"></i>
                        Testimonials
                    </a>
                    
                    <a href="forms.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-envelope mr-3"></i>
                        Forms & Leads
                    </a>
                    
                    <a href="media.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-images mr-3"></i>
                        Media Library
                    </a>
                    
                    <a href="settings.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-cog mr-3"></i>
                        Settings
                    </a>
                    
                    <?php if ($auth->hasRole('admin')): ?>
                    <a href="users.php" class="flex items-center px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-users mr-3"></i>
                        Users
                    </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700 lg:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h2 class="ml-4 text-xl font-semibold text-gray-800 lg:ml-0">Dashboard</h2>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <a href="<?php echo APP_URL; ?>" target="_blank" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-external-link-alt"></i>
                            View Site
                        </a>
                        
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                                <i class="fas fa-user-circle text-xl mr-2"></i>
                                <?php echo htmlspecialchars($current_user['full_name']); ?>
                                <i class="fas fa-chevron-down ml-2"></i>
                            </button>
                            
                            <div x-show="open" @click.away="open = false" 
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i>
                                    Profile
                                </a>
                                <a href="../api/auth.php?action=logout" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-briefcase text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Portfolio Items</p>
                                <p class="text-2xl font-semibold text-gray-900" id="portfolio-count">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-envelope text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">New Leads</p>
                                <p class="text-2xl font-semibold text-gray-900" id="leads-count">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-blog text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Blog Posts</p>
                                <p class="text-2xl font-semibold text-gray-900" id="blog-count">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-images text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Media Files</p>
                                <p class="text-2xl font-semibold text-gray-900" id="media-count">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Form Submissions -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Recent Form Submissions</h3>
                        </div>
                        <div class="p-6">
                            <div id="recent-submissions" class="space-y-4">
                                <div class="text-center text-gray-500">Loading...</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <a href="portfolio.php?action=create" class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Portfolio
                                </a>
                                
                                <a href="blog.php?action=create" class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    New Blog Post
                                </a>
                                
                                <a href="media.php?action=upload" class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                    <i class="fas fa-upload mr-2"></i>
                                    Upload Media
                                </a>
                                
                                <a href="forms.php" class="flex items-center justify-center px-4 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                                    <i class="fas fa-envelope mr-2"></i>
                                    View Leads
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Load dashboard data
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardStats();
            loadRecentSubmissions();
        });
        
        async function loadDashboardStats() {
            try {
                const response = await fetch('../api/dashboard.php?action=stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('portfolio-count').textContent = data.data.portfolio_count || 0;
                    document.getElementById('leads-count').textContent = data.data.leads_count || 0;
                    document.getElementById('blog-count').textContent = data.data.blog_count || 0;
                    document.getElementById('media-count').textContent = data.data.media_count || 0;
                }
            } catch (error) {
                console.error('Error loading dashboard stats:', error);
            }
        }
        
        async function loadRecentSubmissions() {
            try {
                const response = await fetch('../api/forms.php?action=submissions&limit=5');
                const data = await response.json();
                
                const container = document.getElementById('recent-submissions');
                
                if (data.success && data.data.length > 0) {
                    container.innerHTML = data.data.map(submission => `
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900">${submission.name || 'Anonymous'}</p>
                                <p class="text-sm text-gray-600">${submission.form_type} - ${submission.email || 'No email'}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">${new Date(submission.created_at).toLocaleDateString()}</p>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(submission.status)}">
                                    ${submission.status}
                                </span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="text-center text-gray-500">No recent submissions</div>';
                }
            } catch (error) {
                console.error('Error loading recent submissions:', error);
                document.getElementById('recent-submissions').innerHTML = '<div class="text-center text-red-500">Error loading submissions</div>';
            }
        }
        
        function getStatusColor(status) {
            switch (status) {
                case 'new': return 'bg-blue-100 text-blue-800';
                case 'read': return 'bg-yellow-100 text-yellow-800';
                case 'replied': return 'bg-green-100 text-green-800';
                case 'archived': return 'bg-gray-100 text-gray-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }
    </script>
</body>
</html>