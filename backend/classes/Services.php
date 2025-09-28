<?php
/**
 * Services Management Class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';

class Services {
    private $db;
    private $conn;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->auth = new Auth();
    }
    
    /**
     * Get all services with packages
     */
    public function getAll($include_packages = true) {
        try {
            $query = "SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $services = $stmt->fetchAll();
            
            // Process JSON fields and get packages
            foreach ($services as &$service) {
                $service['features'] = json_decode($service['features'], true) ?? [];
                
                if ($include_packages) {
                    $service['packages'] = $this->getServicePackages($service['id']);
                }
            }
            
            return ['success' => true, 'data' => $services];
            
        } catch (Exception $e) {
            error_log("Services getAll error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch services'];
        }
    }
    
    /**
     * Get single service
     */
    public function getById($id) {
        try {
            $query = "SELECT * FROM services WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Service not found'];
            }
            
            $service = $stmt->fetch();
            $service['features'] = json_decode($service['features'], true) ?? [];
            $service['packages'] = $this->getServicePackages($id);
            
            return ['success' => true, 'data' => $service];
            
        } catch (Exception $e) {
            error_log("Services getById error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch service'];
        }
    }
    
    /**
     * Get service by slug
     */
    public function getBySlug($slug) {
        try {
            $query = "SELECT * FROM services WHERE slug = :slug AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Service not found'];
            }
            
            $service = $stmt->fetch();
            $service['features'] = json_decode($service['features'], true) ?? [];
            $service['packages'] = $this->getServicePackages($service['id']);
            
            return ['success' => true, 'data' => $service];
            
        } catch (Exception $e) {
            error_log("Services getBySlug error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch service'];
        }
    }
    
    /**
     * Create new service
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
            
            $query = "INSERT INTO services (
                        title, slug, description, icon, base_price, 
                        features, is_popular, is_active, sort_order
                     ) VALUES (
                        :title, :slug, :description, :icon, :base_price,
                        :features, :is_popular, :is_active, :sort_order
                     )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':slug', $data['slug']);
            $stmt->bindParam(':description', $data['description'] ?? null);
            $stmt->bindParam(':icon', $data['icon'] ?? null);
            $stmt->bindParam(':base_price', $data['base_price'] ?? null);
            $stmt->bindParam(':features', json_encode($data['features'] ?? []));
            $stmt->bindParam(':is_popular', $data['is_popular'] ?? 0);
            $stmt->bindParam(':is_active', $data['is_active'] ?? 1);
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? 0);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return ['success' => true, 'message' => 'Service created', 'id' => $id];
            }
            
            return ['success' => false, 'message' => 'Failed to create service'];
            
        } catch (Exception $e) {
            error_log("Services create error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create service'];
        }
    }
    
    /**
     * Update service
     */
    public function update($id, $data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Check if service exists
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
            
            $query = "UPDATE services SET 
                        title = :title,
                        slug = :slug,
                        description = :description,
                        icon = :icon,
                        base_price = :base_price,
                        features = :features,
                        is_popular = :is_popular,
                        is_active = :is_active,
                        sort_order = :sort_order,
                        updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':title', $data['title'] ?? $existing['data']['title']);
            $stmt->bindParam(':slug', $data['slug'] ?? $existing['data']['slug']);
            $stmt->bindParam(':description', $data['description'] ?? $existing['data']['description']);
            $stmt->bindParam(':icon', $data['icon'] ?? $existing['data']['icon']);
            $stmt->bindParam(':base_price', $data['base_price'] ?? $existing['data']['base_price']);
            $stmt->bindParam(':features', json_encode($data['features'] ?? $existing['data']['features']));
            $stmt->bindParam(':is_popular', $data['is_popular'] ?? $existing['data']['is_popular']);
            $stmt->bindParam(':is_active', $data['is_active'] ?? $existing['data']['is_active']);
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? $existing['data']['sort_order']);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Service updated'];
            }
            
            return ['success' => false, 'message' => 'Failed to update service'];
            
        } catch (Exception $e) {
            error_log("Services update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update service'];
        }
    }
    
    /**
     * Delete service
     */
    public function delete($id) {
        if (!$this->auth->hasRole('admin')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Delete service packages first
            $query = "DELETE FROM service_packages WHERE service_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Delete service
            $query = "DELETE FROM services WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Service deleted'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete service'];
            
        } catch (Exception $e) {
            error_log("Services delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete service'];
        }
    }
    
    /**
     * Get service packages
     */
    public function getServicePackages($service_id) {
        try {
            $query = "SELECT * FROM service_packages WHERE service_id = :service_id ORDER BY sort_order ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':service_id', $service_id);
            $stmt->execute();
            
            $packages = $stmt->fetchAll();
            
            // Process JSON fields
            foreach ($packages as &$package) {
                $package['features'] = json_decode($package['features'], true) ?? [];
            }
            
            return $packages;
            
        } catch (Exception $e) {
            error_log("Services getServicePackages error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create service package
     */
    public function createPackage($data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Validate required fields
            if (empty($data['service_id']) || empty($data['name']) || empty($data['price'])) {
                return ['success' => false, 'message' => 'Service ID, name, and price are required'];
            }
            
            $query = "INSERT INTO service_packages (
                        service_id, name, description, price, features,
                        delivery_time, revisions, is_popular, sort_order
                     ) VALUES (
                        :service_id, :name, :description, :price, :features,
                        :delivery_time, :revisions, :is_popular, :sort_order
                     )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':service_id', $data['service_id']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':description', $data['description'] ?? null);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':features', json_encode($data['features'] ?? []));
            $stmt->bindParam(':delivery_time', $data['delivery_time'] ?? null);
            $stmt->bindParam(':revisions', $data['revisions'] ?? null);
            $stmt->bindParam(':is_popular', $data['is_popular'] ?? 0);
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? 0);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return ['success' => true, 'message' => 'Package created', 'id' => $id];
            }
            
            return ['success' => false, 'message' => 'Failed to create package'];
            
        } catch (Exception $e) {
            error_log("Services createPackage error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create package'];
        }
    }
    
    /**
     * Update service package
     */
    public function updatePackage($id, $data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE service_packages SET 
                        name = :name,
                        description = :description,
                        price = :price,
                        features = :features,
                        delivery_time = :delivery_time,
                        revisions = :revisions,
                        is_popular = :is_popular,
                        sort_order = :sort_order,
                        updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':description', $data['description'] ?? null);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':features', json_encode($data['features'] ?? []));
            $stmt->bindParam(':delivery_time', $data['delivery_time'] ?? null);
            $stmt->bindParam(':revisions', $data['revisions'] ?? null);
            $stmt->bindParam(':is_popular', $data['is_popular'] ?? 0);
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? 0);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Package updated'];
            }
            
            return ['success' => false, 'message' => 'Failed to update package'];
            
        } catch (Exception $e) {
            error_log("Services updatePackage error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update package'];
        }
    }
    
    /**
     * Delete service package
     */
    public function deletePackage($id) {
        if (!$this->auth->hasRole('admin')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "DELETE FROM service_packages WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Package deleted'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete package'];
            
        } catch (Exception $e) {
            error_log("Services deletePackage error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete package'];
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
        $query = "SELECT id FROM services WHERE slug = :slug";
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
}
?>