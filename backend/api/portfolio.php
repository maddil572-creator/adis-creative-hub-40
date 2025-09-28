<?php
/**
 * Portfolio API Endpoints
 */

require_once __DIR__ . '/../classes/Portfolio.php';

$portfolio = new Portfolio();

switch ($method) {
    case 'GET':
        if ($id) {
            // Get single portfolio item
            if (is_numeric($id)) {
                $result = $portfolio->getById($id);
            } else {
                $result = $portfolio->getBySlug($id);
            }
            sendResponse($result);
        } elseif ($action === 'categories') {
            // Get categories
            $result = $portfolio->getCategories();
            sendResponse($result);
        } else {
            // Get all portfolio items
            $filters = [
                'category' => $data['category'] ?? null,
                'is_featured' => $data['is_featured'] ?? null,
                'is_published' => $data['is_published'] ?? 1,
                'search' => $data['search'] ?? null
            ];
            
            $result = $portfolio->getAll($filters);
            sendResponse($result);
        }
        break;
        
    case 'POST':
        // Create new portfolio item
        $result = $portfolio->create($data);
        sendResponse($result, $result['success'] ? 201 : 400);
        break;
        
    case 'PUT':
        // Update portfolio item
        if (!$id) {
            sendError('ID is required');
        }
        
        $result = $portfolio->update($id, $data);
        sendResponse($result);
        break;
        
    case 'DELETE':
        // Delete portfolio item
        if (!$id) {
            sendError('ID is required');
        }
        
        $result = $portfolio->delete($id);
        sendResponse($result);
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>