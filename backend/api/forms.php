<?php
/**
 * Forms and Leads API Endpoints
 */

require_once __DIR__ . '/../classes/Forms.php';

$forms = new Forms();

switch ($method) {
    case 'POST':
        switch ($action) {
            case 'submit':
                // Handle form submission
                $form_type = $data['form_type'] ?? '';
                if (empty($form_type)) {
                    sendError('Form type is required');
                }
                
                $result = $forms->submitForm($form_type, $data);
                sendResponse($result, $result['success'] ? 201 : 400);
                break;
                
            case 'newsletter':
                // Newsletter subscription
                $email = $data['email'] ?? '';
                $name = $data['name'] ?? null;
                $source = $data['source'] ?? 'api';
                
                if (empty($email)) {
                    sendError('Email is required');
                }
                
                $result = $forms->subscribeNewsletter($email, $name, $source);
                sendResponse($result, $result['success'] ? 201 : 400);
                break;
                
            default:
                sendError('Action not found', 404);
        }
        break;
        
    case 'GET':
        switch ($action) {
            case 'submissions':
                // Get form submissions
                $filters = [
                    'form_type' => $data['form_type'] ?? null,
                    'status' => $data['status'] ?? null,
                    'search' => $data['search'] ?? null,
                    'date_from' => $data['date_from'] ?? null,
                    'date_to' => $data['date_to'] ?? null
                ];
                
                $result = $forms->getSubmissions($filters);
                sendResponse($result);
                break;
                
            case 'submission':
                // Get single submission
                if (!$id) {
                    sendError('Submission ID is required');
                }
                
                $result = $forms->getSubmission($id);
                sendResponse($result);
                break;
                
            case 'newsletter':
                // Get newsletter subscribers
                $status = $data['status'] ?? 'active';
                $result = $forms->getNewsletterSubscribers($status);
                sendResponse($result);
                break;
                
            case 'export':
                // Export submissions to CSV
                $filters = [
                    'form_type' => $data['form_type'] ?? null,
                    'status' => $data['status'] ?? null,
                    'date_from' => $data['date_from'] ?? null,
                    'date_to' => $data['date_to'] ?? null
                ];
                
                $result = $forms->exportToCSV($filters);
                sendResponse($result);
                break;
                
            default:
                sendError('Action not found', 404);
        }
        break;
        
    case 'PUT':
        if ($action === 'status' && $id) {
            // Update submission status
            $status = $data['status'] ?? '';
            if (empty($status)) {
                sendError('Status is required');
            }
            
            $result = $forms->updateStatus($id, $status);
            sendResponse($result);
        } else {
            sendError('Invalid request');
        }
        break;
        
    case 'DELETE':
        if ($action === 'submission' && $id) {
            // Delete submission
            $result = $forms->deleteSubmission($id);
            sendResponse($result);
        } else {
            sendError('Invalid request');
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>