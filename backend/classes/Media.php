<?php
/**
 * Media Management Class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';

class Media {
    private $db;
    private $conn;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->auth = new Auth();
        
        // Create upload directory if it doesn't exist
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
    }
    
    /**
     * Upload file
     */
    public function upload($file, $alt_text = null, $caption = null) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Generate unique filename
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = UPLOAD_PATH . $filename;
            $file_url = UPLOAD_URL . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                return ['success' => false, 'message' => 'Failed to upload file'];
            }
            
            // Get file info
            $file_size = filesize($file_path);
            $mime_type = mime_content_type($file_path);
            $file_type = $this->getFileType($mime_type);
            
            // Get current user
            $current_user = $this->auth->getCurrentUser();
            
            // Insert into database
            $query = "INSERT INTO media (
                        filename, original_name, file_path, file_url, 
                        file_type, file_size, mime_type, alt_text, 
                        caption, uploaded_by
                     ) VALUES (
                        :filename, :original_name, :file_path, :file_url,
                        :file_type, :file_size, :mime_type, :alt_text,
                        :caption, :uploaded_by
                     )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':filename', $filename);
            $stmt->bindParam(':original_name', $file['name']);
            $stmt->bindParam(':file_path', $file_path);
            $stmt->bindParam(':file_url', $file_url);
            $stmt->bindParam(':file_type', $file_type);
            $stmt->bindParam(':file_size', $file_size);
            $stmt->bindParam(':mime_type', $mime_type);
            $stmt->bindParam(':alt_text', $alt_text);
            $stmt->bindParam(':caption', $caption);
            $stmt->bindParam(':uploaded_by', $current_user['id']);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'data' => [
                        'id' => $id,
                        'filename' => $filename,
                        'original_name' => $file['name'],
                        'file_url' => $file_url,
                        'file_type' => $file_type,
                        'file_size' => $file_size,
                        'mime_type' => $mime_type
                    ]
                ];
            }
            
            // Clean up file if database insert failed
            unlink($file_path);
            return ['success' => false, 'message' => 'Failed to save file info'];
            
        } catch (Exception $e) {
            error_log("Media upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
    }
    
    /**
     * Get all media files
     */
    public function getAll($filters = []) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $where_conditions = [];
            $params = [];
            
            // Build WHERE clause based on filters
            if (!empty($filters['file_type'])) {
                $where_conditions[] = "m.file_type = :file_type";
                $params[':file_type'] = $filters['file_type'];
            }
            
            if (!empty($filters['search'])) {
                $where_conditions[] = "(m.original_name LIKE :search OR m.alt_text LIKE :search OR m.caption LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['uploaded_by'])) {
                $where_conditions[] = "m.uploaded_by = :uploaded_by";
                $params[':uploaded_by'] = $filters['uploaded_by'];
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $query = "SELECT m.*, u.first_name, u.last_name
                     FROM media m
                     LEFT JOIN users u ON m.uploaded_by = u.id
                     {$where_clause}
                     ORDER BY m.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $media = $stmt->fetchAll();
            
            // Add uploader name
            foreach ($media as &$item) {
                $item['uploader_name'] = $item['first_name'] . ' ' . $item['last_name'];
            }
            
            return ['success' => true, 'data' => $media];
            
        } catch (Exception $e) {
            error_log("Media getAll error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch media files'];
        }
    }
    
    /**
     * Get single media file
     */
    public function getById($id) {
        try {
            $query = "SELECT m.*, u.first_name, u.last_name
                     FROM media m
                     LEFT JOIN users u ON m.uploaded_by = u.id
                     WHERE m.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Media file not found'];
            }
            
            $media = $stmt->fetch();
            $media['uploader_name'] = $media['first_name'] . ' ' . $media['last_name'];
            
            return ['success' => true, 'data' => $media];
            
        } catch (Exception $e) {
            error_log("Media getById error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch media file'];
        }
    }
    
    /**
     * Update media file info
     */
    public function update($id, $data) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE media SET 
                        alt_text = :alt_text,
                        caption = :caption
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':alt_text', $data['alt_text'] ?? null);
            $stmt->bindParam(':caption', $data['caption'] ?? null);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Media file updated'];
            }
            
            return ['success' => false, 'message' => 'Failed to update media file'];
            
        } catch (Exception $e) {
            error_log("Media update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update media file'];
        }
    }
    
    /**
     * Delete media file
     */
    public function delete($id) {
        if (!$this->auth->hasRole('admin')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            // Get file info first
            $media = $this->getById($id);
            if (!$media['success']) {
                return $media;
            }
            
            // Delete from database
            $query = "DELETE FROM media WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Delete physical file
                if (file_exists($media['data']['file_path'])) {
                    unlink($media['data']['file_path']);
                }
                
                return ['success' => true, 'message' => 'Media file deleted'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete media file'];
            
        } catch (Exception $e) {
            error_log("Media delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete media file'];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error'];
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size too large'];
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_FILE_TYPES);
        
        if (!in_array($file_extension, $allowed_extensions)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mime_types = [
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            // Videos
            'video/mp4', 'video/webm', 'video/quicktime',
            // Documents
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Get file type category
     */
    private function getFileType($mime_type) {
        if (strpos($mime_type, 'image/') === 0) {
            return 'image';
        } elseif (strpos($mime_type, 'video/') === 0) {
            return 'video';
        } else {
            return 'document';
        }
    }
    
    /**
     * Get media statistics
     */
    public function getStats() {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "SELECT 
                        COUNT(*) as total_files,
                        SUM(file_size) as total_size,
                        COUNT(CASE WHEN file_type = 'image' THEN 1 END) as total_images,
                        COUNT(CASE WHEN file_type = 'video' THEN 1 END) as total_videos,
                        COUNT(CASE WHEN file_type = 'document' THEN 1 END) as total_documents
                     FROM media";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $stats = $stmt->fetch();
            
            return ['success' => true, 'data' => $stats];
            
        } catch (Exception $e) {
            error_log("Media getStats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch media statistics'];
        }
    }
}
?>