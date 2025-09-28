<?php
/**
 * Forms and Leads Management Class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Email.php';

class Forms {
    private $db;
    private $conn;
    private $auth;
    private $email;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->auth = new Auth();
        $this->email = new Email();
    }
    
    /**
     * Handle form submission
     */
    public function submitForm($form_type, $data, $send_notification = true) {
        try {
            // Validate required fields based on form type
            $validation = $this->validateFormData($form_type, $data);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Get client info
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Insert form submission
            $query = "INSERT INTO form_submissions (
                        form_type, name, email, phone, subject, message, 
                        form_data, ip_address, user_agent
                     ) VALUES (
                        :form_type, :name, :email, :phone, :subject, :message,
                        :form_data, :ip_address, :user_agent
                     )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':form_type', $form_type);
            $stmt->bindParam(':name', $data['name'] ?? null);
            $stmt->bindParam(':email', $data['email'] ?? null);
            $stmt->bindParam(':phone', $data['phone'] ?? null);
            $stmt->bindParam(':subject', $data['subject'] ?? null);
            $stmt->bindParam(':message', $data['message'] ?? null);
            $stmt->bindParam(':form_data', json_encode($data));
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            
            if ($stmt->execute()) {
                $submission_id = $this->conn->lastInsertId();
                
                // Send notification email if enabled
                if ($send_notification) {
                    $this->sendNotificationEmail($form_type, $data, $submission_id);
                }
                
                // Handle specific form types
                $this->handleSpecificFormType($form_type, $data);
                
                return [
                    'success' => true, 
                    'message' => 'Form submitted successfully',
                    'id' => $submission_id
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to submit form'];
            
        } catch (Exception $e) {
            error_log("Forms submitForm error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to submit form'];
        }
    }
    
    /**
     * Get all form submissions
     */
    public function getSubmissions($filters = []) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $where_conditions = [];
            $params = [];
            
            // Build WHERE clause based on filters
            if (!empty($filters['form_type'])) {
                $where_conditions[] = "form_type = :form_type";
                $params[':form_type'] = $filters['form_type'];
            }
            
            if (!empty($filters['status'])) {
                $where_conditions[] = "status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['search'])) {
                $where_conditions[] = "(name LIKE :search OR email LIKE :search OR subject LIKE :search OR message LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where_conditions[] = "created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $query = "SELECT * FROM form_submissions {$where_clause} ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $submissions = $stmt->fetchAll();
            
            // Process JSON fields
            foreach ($submissions as &$submission) {
                $submission['form_data'] = json_decode($submission['form_data'], true) ?? [];
            }
            
            return ['success' => true, 'data' => $submissions];
            
        } catch (Exception $e) {
            error_log("Forms getSubmissions error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch submissions'];
        }
    }
    
    /**
     * Get single form submission
     */
    public function getSubmission($id) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "SELECT * FROM form_submissions WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Submission not found'];
            }
            
            $submission = $stmt->fetch();
            $submission['form_data'] = json_decode($submission['form_data'], true) ?? [];
            
            return ['success' => true, 'data' => $submission];
            
        } catch (Exception $e) {
            error_log("Forms getSubmission error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch submission'];
        }
    }
    
    /**
     * Update submission status
     */
    public function updateStatus($id, $status) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $valid_statuses = ['new', 'read', 'replied', 'archived'];
            if (!in_array($status, $valid_statuses)) {
                return ['success' => false, 'message' => 'Invalid status'];
            }
            
            $query = "UPDATE form_submissions SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Status updated'];
            }
            
            return ['success' => false, 'message' => 'Failed to update status'];
            
        } catch (Exception $e) {
            error_log("Forms updateStatus error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update status'];
        }
    }
    
    /**
     * Delete form submission
     */
    public function deleteSubmission($id) {
        if (!$this->auth->hasRole('admin')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "DELETE FROM form_submissions WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Submission deleted'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete submission'];
            
        } catch (Exception $e) {
            error_log("Forms deleteSubmission error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete submission'];
        }
    }
    
    /**
     * Export submissions to CSV
     */
    public function exportToCSV($filters = []) {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $submissions = $this->getSubmissions($filters);
            if (!$submissions['success']) {
                return $submissions;
            }
            
            $filename = 'form_submissions_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = UPLOAD_PATH . $filename;
            
            $file = fopen($filepath, 'w');
            
            // Write CSV header
            fputcsv($file, [
                'ID', 'Form Type', 'Name', 'Email', 'Phone', 'Subject', 
                'Message', 'Status', 'IP Address', 'Created At'
            ]);
            
            // Write data rows
            foreach ($submissions['data'] as $submission) {
                fputcsv($file, [
                    $submission['id'],
                    $submission['form_type'],
                    $submission['name'],
                    $submission['email'],
                    $submission['phone'],
                    $submission['subject'],
                    $submission['message'],
                    $submission['status'],
                    $submission['ip_address'],
                    $submission['created_at']
                ]);
            }
            
            fclose($file);
            
            return [
                'success' => true,
                'message' => 'CSV exported successfully',
                'filename' => $filename,
                'url' => UPLOAD_URL . $filename
            ];
            
        } catch (Exception $e) {
            error_log("Forms exportToCSV error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to export CSV'];
        }
    }
    
    /**
     * Newsletter subscription
     */
    public function subscribeNewsletter($email, $name = null, $source = null) {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Check if already subscribed
            $query = "SELECT id, status FROM newsletter_subscribers WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $subscriber = $stmt->fetch();
                if ($subscriber['status'] === 'active') {
                    return ['success' => false, 'message' => 'Email already subscribed'];
                } else {
                    // Reactivate subscription
                    $query = "UPDATE newsletter_subscribers SET 
                             status = 'active', 
                             name = :name,
                             source = :source,
                             subscribed_at = NOW(),
                             unsubscribed_at = NULL
                             WHERE id = :id";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':source', $source);
                    $stmt->bindParam(':id', $subscriber['id']);
                    $stmt->execute();
                    
                    return ['success' => true, 'message' => 'Subscription reactivated'];
                }
            }
            
            // New subscription
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            
            $query = "INSERT INTO newsletter_subscribers (email, name, source, ip_address) 
                     VALUES (:email, :name, :source, :ip_address)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':source', $source);
            $stmt->bindParam(':ip_address', $ip_address);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Successfully subscribed to newsletter'];
            }
            
            return ['success' => false, 'message' => 'Failed to subscribe'];
            
        } catch (Exception $e) {
            error_log("Forms subscribeNewsletter error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to subscribe'];
        }
    }
    
    /**
     * Get newsletter subscribers
     */
    public function getNewsletterSubscribers($status = 'active') {
        if (!$this->auth->hasRole('editor')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        try {
            $query = "SELECT * FROM newsletter_subscribers WHERE status = :status ORDER BY subscribed_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            $subscribers = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $subscribers];
            
        } catch (Exception $e) {
            error_log("Forms getNewsletterSubscribers error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch subscribers'];
        }
    }
    
    /**
     * Validate form data based on form type
     */
    private function validateFormData($form_type, $data) {
        switch ($form_type) {
            case 'contact':
                if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
                    return ['success' => false, 'message' => 'Name, email, and message are required'];
                }
                break;
                
            case 'newsletter':
                if (empty($data['email'])) {
                    return ['success' => false, 'message' => 'Email is required'];
                }
                break;
                
            case 'pricing_estimator':
                if (empty($data['email'])) {
                    return ['success' => false, 'message' => 'Email is required'];
                }
                break;
                
            case 'lead_magnet':
                if (empty($data['name']) || empty($data['email'])) {
                    return ['success' => false, 'message' => 'Name and email are required'];
                }
                break;
        }
        
        // Validate email format if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Send notification email
     */
    private function sendNotificationEmail($form_type, $data, $submission_id) {
        try {
            $subject = "New {$form_type} form submission";
            $message = "New form submission received:\n\n";
            $message .= "Form Type: {$form_type}\n";
            $message .= "Submission ID: {$submission_id}\n\n";
            
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $message .= ucfirst($key) . ": {$value}\n";
            }
            
            $this->email->send(SMTP_FROM_EMAIL, $subject, $message);
            
        } catch (Exception $e) {
            error_log("Forms sendNotificationEmail error: " . $e->getMessage());
        }
    }
    
    /**
     * Handle specific form types
     */
    private function handleSpecificFormType($form_type, $data) {
        switch ($form_type) {
            case 'newsletter':
                $this->subscribeNewsletter($data['email'], $data['name'] ?? null, 'form_submission');
                break;
        }
    }
}
?>