<?php
/**
 * Authentication API Endpoints
 */

require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();

switch ($method) {
    case 'POST':
        switch ($action) {
            case 'login':
                if (empty($data['email']) || empty($data['password'])) {
                    sendError('Email and password are required');
                }
                
                $result = $auth->login($data['email'], $data['password']);
                
                if ($result['success']) {
                    $result['csrf_token'] = $auth->getCsrfToken();
                    sendResponse($result);
                } else {
                    sendError($result['message'], 401);
                }
                break;
                
            case 'logout':
                $result = $auth->logout();
                sendResponse($result);
                break;
                
            case 'change-password':
                if (!$auth->isAuthenticated()) {
                    sendError('Not authenticated', 401);
                }
                
                if (empty($data['current_password']) || empty($data['new_password'])) {
                    sendError('Current password and new password are required');
                }
                
                $result = $auth->changePassword($data['current_password'], $data['new_password']);
                sendResponse($result);
                break;
                
            case 'create-user':
                if (!$auth->hasRole('admin')) {
                    sendError('Unauthorized', 403);
                }
                
                $result = $auth->createUser($data);
                sendResponse($result);
                break;
                
            default:
                sendError('Action not found', 404);
        }
        break;
        
    case 'GET':
        switch ($action) {
            case 'me':
                if (!$auth->isAuthenticated()) {
                    sendError('Not authenticated', 401);
                }
                
                $user = $auth->getCurrentUser();
                $user['csrf_token'] = $auth->getCsrfToken();
                sendResponse(['success' => true, 'data' => $user]);
                break;
                
            case 'check':
                $is_authenticated = $auth->isAuthenticated();
                sendResponse([
                    'success' => true,
                    'authenticated' => $is_authenticated,
                    'user' => $is_authenticated ? $auth->getCurrentUser() : null
                ]);
                break;
                
            default:
                sendError('Action not found', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>