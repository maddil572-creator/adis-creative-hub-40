<?php
/**
 * Blog Management Class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';

class Blog {
    private $db;
    private $conn;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->auth = new Auth();
    }
    
    /**
     * Get all blog posts
     */
    public function getAll($filters = []) {
        try {
            $where_conditions = [];
            $params = [];
            
            // Build WHERE clause based on filters
            if (!empty($filters['category'])) {
                $where_conditions[] = "bp.category = :category";
                $params[':category'] = $filters['category'];
            }
            
            if (!empty($filters['is_published'])) {
                $where_conditions[] = "bp.is_published = :is_published";
                $params[':is_published'] = $filters['is_published'];
            }
            
            if (!empty($filters['is_featured'])) {
                $where_conditions[] = "bp.is_featured = :is_featured";
                $params[':is_featured'] = $filters['is_featured'];
            }
            
            if (!empty($filters['search'])) {
                $where_conditions[] = "(bp.title LIKE :search OR bp.excerpt LIKE :search OR bp.content LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['author_id'])) {
                $where_conditions[] = "bp.author_id = :author_id";
                $params[':author_id'] = $filters['author_id'];
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $query = "SELECT bp.*, 
                            u.first_name, u.last_name,
                            m.file_url as featured_image_url,
                            m.alt_text as featured_image_alt
                     FROM blog_posts bp
                     LEFT JOIN users u ON bp.author_id = u.id
                     LEFT JOIN media m ON bp.featured_image = m.id
                     {$where_clause}
                     ORDER BY bp.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $posts = $stmt->fetchAll();
            
            // Process JSON fields and author name
            foreach ($posts as &$post) {
                $post['tags'] = json_decode($post['tags'], true) ?? [];
                $post['author_name'] = $post['first_name'] . ' ' . $post['last_name'];
                
                // Calculate reading time if not set
                if (!$post['reading_time']) {
                    $post['reading_time'] = $this->calculateReadingTime($post['content']);
                }
            }
            
            return ['success' => true, 'data' => $posts];
            
        } catch (Exception $e) {
            error_log("Blog getAll error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch blog posts'];
        }
    }
    
    /**
     * Get single blog post
     */
    public function getById($id) {
        try {
            $query = "SELECT bp.*, 
                            u.first_name, u.last_name,
                            m.file_url as featured_image_url,
                            m.alt_text as featured_image_alt
                     FROM blog_posts bp
                     LEFT JOIN users u ON bp.author_id = u.id
                     LEFT JOIN media m ON bp.featured_image = m.id
                     WHERE bp.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Blog post not found'];
            }
            
            $post = $stmt->fetch();
            
            // Process JSON fields
            $post['tags'] = json_decode($post['tags'], true) ?? [];
            $post['author_name'] = $post['first_name'] . ' ' . $post['last_name'];
            
            // Calculate reading time if not set
            if (!$post['reading_time']) {
                $post['reading_time'] = $this->calculateReadingTime($post['content']);
            }
            
            // Increment views for public access
            if (!$this->auth->isAuthenticated()) {
                $this->incrementViews($id);
            }
            
            return ['success' => true, 'data' => $post];
            
        } catch (Exception $e) {
            error_log("Blog getById error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch blog post'];
        }
    }
    
    /**
     * Get blog post by slug
     */
    public function getBySlug($slug) {
        try {
            $query = "SELECT bp.*, 
                            u.first_name, u.last_name,
                            m.file_url as featured_image_url,
                            m.alt_text as featured_image_alt
                     FROM blog_posts bp
                     LEFT JOIN users u ON bp.author_id = u.id
                     LEFT JOIN media m ON bp.featured_image = m.id
                     WHERE bp.slug = :slug AND bp.is_published = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Blog post not found'];
            }
            
            $post = $stmt->fetch();
            
            // Process JSON fields
            $post['tags'] = json_decode($post['tags'], true) ?? [];
            $post['author_name'] = $post['first_name'] . ' ' . $post['last_name'];
            
            // Calculate reading time if not set
            if (!$post['reading_time']) {
                $post['reading_time'] = $this->calculateReadingTime($post['content']);
            }
            
            // Increment views
            $this->incrementViews($post['id']);
            
            return ['success' => true, 'data' => $post];
            
        } catch (Exception $e) {
            error_log("Blog getBySlug error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch blog post'];
        }
    }
    
    /**
     * Create new blog post
     */
    public function create($data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Validate required fields
            if (empty($data['title']) || empty($data['content'])) {
                return ['success' => false, 'message' => 'Title and content are required'];
            }
            
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['title']);
            }
            
            // Check if slug already exists
            if ($this->slugExists($data['slug'])) {
                return ['success' => false, 'message' => 'Slug already exists'];
            }
            
            // Get current user as author
            $current_user = $this->auth->getCurrentUser();
            $author_id = $data['author_id'] ?? $current_user['id'];
            
            // Calculate reading time
            $reading_time = $this->calculateReadingTime($data['content']);
            
            $query = "INSERT INTO blog_posts (
                        title, slug, excerpt, content, featured_image, author_id,
                        category, tags, meta_description, meta_keywords,
                        is_published, is_featured, published_at, reading_time
                     ) VALUES (
                        :title, :slug, :excerpt, :content, :featured_image, :author_id,
                        :category, :tags, :meta_description, :meta_keywords,
                        :is_published, :is_featured, :published_at, :reading_time
                     )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':slug', $data['slug']);
            $stmt->bindParam(':excerpt', $data['excerpt'] ?? null);
            $stmt->bindParam(':content', $data['content']);
            $stmt->bindParam(':featured_image', $data['featured_image'] ?? null);
            $stmt->bindParam(':author_id', $author_id);
            $stmt->bindParam(':category', $data['category'] ?? null);
            $stmt->bindParam(':tags', json_encode($data['tags'] ?? []));
            $stmt->bindParam(':meta_description', $data['meta_description'] ?? null);
            $stmt->bindParam(':meta_keywords', $data['meta_keywords'] ?? null);
            $stmt->bindParam(':is_published', $data['is_published'] ?? 0);
            $stmt->bindParam(':is_featured', $data['is_featured'] ?? 0);
            $stmt->bindParam(':published_at', $data['is_published'] ? date('Y-m-d H:i:s') : null);
            $stmt->bindParam(':reading_time', $reading_time);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return ['success' => true, 'message' => 'Blog post created', 'id' => $id];
            }
            
            return ['success' => false, 'message' => 'Failed to create blog post'];
            
        } catch (Exception $e) {
            error_log("Blog create error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create blog post'];
        }
    }
    
    /**
     * Update blog post
     */
    public function update($id, $data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Check if post exists
            $existing = $this->getById($id);
            if (!$existing['success']) {
                return $existing;
            }
            
            // Validate slug if changed
            if (!empty($data['slug']) && $data['slug'] !== $existing['data']['slug']) {
                if ($this->slugExists($data['slug'], $id)) {
                    return ['success' => false, 'message' => 'Slug already exists'];
                }
            }
            
            // Calculate reading time if content changed
            $reading_time = $existing['data']['reading_time'];
            if (isset($data['content']) && $data['content'] !== $existing['data']['content']) {
                $reading_time = $this->calculateReadingTime($data['content']);
            }
            
            // Set published_at if publishing for first time
            $published_at = $existing['data']['published_at'];
            if (isset($data['is_published']) && $data['is_published'] && !$published_at) {
                $published_at = date('Y-m-d H:i:s');
            }
            
            $query = "UPDATE blog_posts SET 
                        title = :title,
                        slug = :slug,
                        excerpt = :excerpt,
                        content = :content,
                        featured_image = :featured_image,
                        category = :category,
                        tags = :tags,
                        meta_description = :meta_description,
                        meta_keywords = :meta_keywords,
                        is_published = :is_published,
                        is_featured = :is_featured,
                        published_at = :published_at,
                        reading_time = :reading_time,
                        updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':title', $data['title'] ?? $existing['data']['title']);
            $stmt->bindParam(':slug', $data['slug'] ?? $existing['data']['slug']);
            $stmt->bindParam(':excerpt', $data['excerpt'] ?? $existing['data']['excerpt']);
            $stmt->bindParam(':content', $data['content'] ?? $existing['data']['content']);
            $stmt->bindParam(':featured_image', $data['featured_image'] ?? $existing['data']['featured_image']);
            $stmt->bindParam(':category', $data['category'] ?? $existing['data']['category']);
            $stmt->bindParam(':tags', json_encode($data['tags'] ?? $existing['data']['tags']));
            $stmt->bindParam(':meta_description', $data['meta_description'] ?? $existing['data']['meta_description']);
            $stmt->bindParam(':meta_keywords', $data['meta_keywords'] ?? $existing['data']['meta_keywords']);
            $stmt->bindParam(':is_published', $data['is_published'] ?? $existing['data']['is_published']);
            $stmt->bindParam(':is_featured', $data['is_featured'] ?? $existing['data']['is_featured']);
            $stmt->bindParam(':published_at', $published_at);
            $stmt->bindParam(':reading_time', $reading_time);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Blog post updated'];
            }
            
            return ['success' => false, 'message' => 'Failed to update blog post'];
            
        } catch (Exception $e) {
            error_log("Blog update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update blog post'];
        }
    }
    
    /**
     * Delete blog post
     */
    public function delete($id) {
        if (!$this->auth->hasRole('admin')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "DELETE FROM blog_posts WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Blog post deleted'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete blog post'];
            
        } catch (Exception $e) {
            error_log("Blog delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete blog post'];
        }
    }
    
    /**
     * Get blog categories
     */
    public function getCategories() {
        try {
            $query = "SELECT DISTINCT category FROM blog_posts WHERE category IS NOT NULL AND category != '' ORDER BY category";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return ['success' => true, 'data' => $categories];
            
        } catch (Exception $e) {
            error_log("Blog getCategories error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch categories'];
        }
    }
    
    /**
     * Calculate reading time in minutes
     */
    private function calculateReadingTime($content) {
        $word_count = str_word_count(strip_tags($content));
        $reading_time = ceil($word_count / 200); // Average reading speed: 200 words per minute
        return max(1, $reading_time); // Minimum 1 minute
    }
    
    /**
     * Generate unique slug
     */
    private function generateSlug($title, $id = null) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug, $id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     */
    private function slugExists($slug, $exclude_id = null) {
        $query = "SELECT id FROM blog_posts WHERE slug = :slug";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':slug', $slug);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Increment view count
     */
    private function incrementViews($id) {
        try {
            $query = "UPDATE blog_posts SET views = views + 1 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Blog incrementViews error: " . $e->getMessage());
        }
    }
}
?>