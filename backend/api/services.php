<?php
/**
 * Services API Endpoints
 */

require_once __DIR__ . '/../classes/Services.php';

$services = new Services();

switch ($method) {
    case 'GET':
        if ($id) {
            // Get single service
            if (is_numeric($id)) {
                $result = $services->getById($id);
            } else {
                $result = $services->getBySlug($id);
            }
            sendResponse($result);
        } elseif ($action === 'packages') {
            // Get service packages
            if (!$id) {
                sendError('Service ID is required');
            }
            $packages = $services->getServicePackages($id);
            sendResponse(['success' => true, 'data' => $packages]);
        } else {
            // Get all services
            $include_packages = isset($data['include_packages']) ? (bool)$data['include_packages'] : true;
            $result = $services->getAll($include_packages);
            sendResponse($result);
        }
        break;
        
    case 'POST':
        if ($action === 'packages') {
            // Create service package
            $result = $services->createPackage($data);
            sendResponse($result, $result['success'] ? 201 : 400);
        } else {
            // Create new service
            $result = $services->create($data);
            sendResponse($result, $result['success'] ? 201 : 400);
        }
        break;
        
    case 'PUT':
        if (!$id) {
            sendError('ID is required');
        }
        
        if ($action === 'packages') {
            // Update service package
            $result = $services->updatePackage($id, $data);
        } else {
            // Update service
            $result = $services->update($id, $data);
        }
        sendResponse($result);
        break;
        
    case 'DELETE':
        if (!$id) {
            sendError('ID is required');
        }
        
        if ($action === 'packages') {
            // Delete service package
            $result = $services->deletePackage($id);
        } else {
            // Delete service
            $result = $services->delete($id);
        }
        sendResponse($result);
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>