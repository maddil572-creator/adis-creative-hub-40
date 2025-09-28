<?php
/**
 * Portfolio Management Class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';

class Portfolio {
    private $db;
    private $conn;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->auth = new Auth();
    }
    
    /**
     * Get all portfolio items
     */
    public function getAll($filters = []) {
        try {
            $where_conditions = [];
            $params = [];
            
            // Build WHERE clause based on filters
            if (!empty($filters['category'])) {
                $where_conditions[] = "p.category = :category";
                $params[':category'] = $filters['category'];
            }
            
            if (!empty($filters['is_featured'])) {
                $where_conditions[] = "p.is_featured = :is_featured";
                $params[':is_featured'] = $filters['is_featured'];
            }
            
            if (!empty($filters['is_published'])) {
                $where_conditions[] = "p.is_published = :is_published";
                $params[':is_published'] = $filters['is_published'];
            }
            
            if (!empty($filters['search'])) {
                $where_conditions[] = "(p.title LIKE :search OR p.description LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $query = "SELECT p.*, 
                            m.file_url as featured_image_url,
                            m.alt_text as featured_image_alt
                     FROM portfolio p
                     LEFT JOIN media m ON p.featured_image = m.id
                     {$where_clause}
                     ORDER BY p.sort_order ASC, p.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $items = $stmt->fetchAll();
            
            // Process JSON fields
            foreach ($items as &$item) {
                $item['tags'] = json_decode($item['tags'], true) ?? [];
                $item['gallery_images'] = json_decode($item['gallery_images'], true) ?? [];
            }
            
            return ['success' => true, 'data' => $items];
            
        } catch (Exception $e) {
            error_log("Portfolio getAll error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch portfolio items'];
        }
    }
    
    /**
     * Get single portfolio item
     */
    public function getById($id) {
        try {
            $query = "SELECT p.*, 
                            m.file_url as featured_image_url,
                            m.alt_text as featured_image_alt
                     FROM portfolio p
                     LEFT JOIN media m ON p.featured_image = m.id
                     WHERE p.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Portfolio item not found'];
            }
            
            $item = $stmt->fetch();
            
            // Process JSON fields
            $item['tags'] = json_decode($item['tags'], true) ?? [];
            $item['gallery_images'] = json_decode($item['gallery_images'], true) ?? [];
            
            // Increment views for public access
            if (!$this->auth->isAuthenticated()) {
                $this->incrementViews($id);
            }
            
            return ['success' => true, 'data' => $item];
            
        } catch (Exception $e) {
            error_log("Portfolio getById error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch portfolio item'];
        }
    }
    
    /**
     * Get portfolio item by slug
     */
    public function getBySlug($slug) {
        try {
            $query = "SELECT p.*, 
                            m.file_url as featured_image_url,
                            m.alt_text as featured_image_alt
                     FROM portfolio p
                     LEFT JOIN media m ON p.featured_image = m.id
                     WHERE p.slug = :slug AND p.is_published = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Portfolio item not found'];
            }
            
            $item = $stmt->fetch();
            
            // Process JSON fields
            $item['tags'] = json_decode($item['tags'], true) ?? [];
            $item['gallery_images'] = json_decode($item['gallery_images'], true) ?? [];
            
            // Increment views
            $this->incrementViews($item['id']);
            
            return ['success' => true, 'data' => $item];
            
        } catch (Exception $e) {
            error_log("Portfolio getBySlug error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch portfolio item'];
        }
    }
    
    /**
     * Create new portfolio item
     */
    public function create($data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Validate required fields
            if (empty($data['title'])) {
                return ['success' => false, 'message' => 'Title is required'];
            }
            
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['title']);
            }
            
            // Check if slug already exists
            if ($this->slugExists($data['slug'])) {
                return ['success' => false, 'message' => 'Slug already exists'];
            }
            
            $query = "INSERT INTO portfolio (
                        title, slug, description, content, featured_image, 
                        gallery_images, category, tags, client_name, project_url, 
                        completion_date, is_featured, is_published, sort_order
                     ) VALUES (
                        :title, :slug, :description, :content, :featured_image,
                        :gallery_images, :category, :tags, :client_name, :project_url,
                        :completion_date, :is_featured, :is_published, :sort_order
                     )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':slug', $data['slug']);
            $stmt->bindParam(':description', $data['description'] ?? null);
            $stmt->bindParam(':content', $data['content'] ?? null);
            $stmt->bindParam(':featured_image', $data['featured_image'] ?? null);
            $stmt->bindParam(':gallery_images', json_encode($data['gallery_images'] ?? []));
            $stmt->bindParam(':category', $data['category'] ?? null);
            $stmt->bindParam(':tags', json_encode($data['tags'] ?? []));
            $stmt->bindParam(':client_name', $data['client_name'] ?? null);
            $stmt->bindParam(':project_url', $data['project_url'] ?? null);
            $stmt->bindParam(':completion_date', $data['completion_date'] ?? null);
            $stmt->bindParam(':is_featured', $data['is_featured'] ?? 0);
            $stmt->bindParam(':is_published', $data['is_published'] ?? 1);
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? 0);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return ['success' => true, 'message' => 'Portfolio item created', 'id' => $id];
            }
            
            return ['success' => false, 'message' => 'Failed to create portfolio item'];
            
        } catch (Exception $e) {
            error_log("Portfolio create error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create portfolio item'];
        }
    }
    
    /**
     * Update portfolio item
     */
    public function update($id, $data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Check if item exists
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
            
            $query = "UPDATE portfolio SET 
                        title = :title,
                        slug = :slug,
                        description = :description,
                        content = :content,
                        featured_image = :featured_image,
                        gallery_images = :gallery_images,
                        category = :category,
                        tags = :tags,
                        client_name = :client_name,
                        project_url = :project_url,
                        completion_date = :completion_date,
                        is_featured = :is_featured,
                        is_published = :is_published,
                        sort_order = :sort_order,
                        updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':title', $data['title'] ?? $existing['data']['title']);
            $stmt->bindParam(':slug', $data['slug'] ?? $existing['data']['slug']);
            $stmt->bindParam(':description', $data['description'] ?? $existing['data']['description']);
            $stmt->bindParam(':content', $data['content'] ?? $existing['data']['content']);
            $stmt->bindParam(':featured_image', $data['featured_image'] ?? $existing['data']['featured_image']);
            $stmt->bindParam(':gallery_images', json_encode($data['gallery_images'] ?? $existing['data']['gallery_images']));
            $stmt->bindParam(':category', $data['category'] ?? $existing['data']['category']);
            $stmt->bindParam(':tags', json_encode($data['tags'] ?? $existing['data']['tags']));
            $stmt->bindParam(':client_name', $data['client_name'] ?? $existing['data']['client_name']);
            $stmt->bindParam(':project_url', $data['project_url'] ?? $existing['data']['project_url']);
            $stmt->bindParam(':completion_date', $data['completion_date'] ?? $existing['data']['completion_date']);
            $stmt->bindParam(':is_featured', $data['is_featured'] ?? $existing['data']['is_featured']);
            $stmt->bindParam(':is_published', $data['is_published'] ?? $existing['data']['is_published']);
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? $existing['data']['sort_order']);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Portfolio item updated'];
            }
            
            return ['success' => false, 'message' => 'Failed to update portfolio item'];
            
        } catch (Exception $e) {
            error_log("Portfolio update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update portfolio item'];
        }
    }
    
    /**
     * Delete portfolio item
     */
    public function delete($id) {
        if (!$this->auth->hasRole('admin')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "DELETE FROM portfolio WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Portfolio item deleted'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete portfolio item'];
            
        } catch (Exception $e) {
            error_log("Portfolio delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete portfolio item'];
        }
    }
    
    /**
     * Get portfolio categories
     */
    public function getCategories() {
        try {
            $query = "SELECT DISTINCT category FROM portfolio WHERE category IS NOT NULL AND category != '' ORDER BY category";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return ['success' => true, 'data' => $categories];
            
        } catch (Exception $e) {
            error_log("Portfolio getCategories error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch categories'];
        }
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
        $query = "SELECT id FROM portfolio WHERE slug = :slug";
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
            $query = "UPDATE portfolio SET views = views + 1 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Portfolio incrementViews error: " . $e->getMessage());
        }
    }
}
?>