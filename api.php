<?php
/**
 * Standalone API endpoint for DescriptionWithAI module
 * This file can be accessed directly to bypass Omeka-S routing restrictions
 */

// Suppress PHP warnings for clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

// Increase execution time for AI processing
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', '300');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get the Omeka-S root path (3 levels up from modules/DescriptionWithAI/)
    $omekaPath = dirname(dirname(dirname(__FILE__)));
    
    // Bootstrap Omeka-S
    require_once $omekaPath . '/bootstrap.php';
    $application = \Omeka\Mvc\Application::init(require $omekaPath . '/application/config/application.config.php');
    
    // Get service manager
    $serviceManager = $application->getServiceManager();
    
    // Get the matching service from our module
    $matchingService = $serviceManager->get('DescriptionWithAI\Service\MatchingService');
    
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
    
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    
    if (empty($description)) {
        http_response_code(400);
        echo json_encode(['error' => 'Description is required']);
        exit;
    }
    
    // Find matching objects using the module service
    $matches = $matchingService->findMatchingObjects($title, $description);
    
    if (empty($matches)) {
        echo json_encode([
            'matchFound' => false,
            'reason' => 'No found objects in database'
        ]);
        exit;
    }
    
    // Return the best match
    $bestMatch = $matches[0];
    
    echo json_encode([
        'matchFound' => true,
        'itemId' => $bestMatch['item_id'],
        'score' => $bestMatch['similarity_score'],
        'explanation' => $bestMatch['explanation'],
        'isRandomSuggestion' => $bestMatch['is_random_suggestion'] ?? false,
        'title' => $bestMatch['title'],
        'description' => $bestMatch['description'],
        'aiDescription' => $bestMatch['ai_description'],
        'finderName' => $bestMatch['finder_name'],
        'finderPhone' => $bestMatch['contact_phone'],
        'placeFound' => $bestMatch['location'],
        'dateFound' => $bestMatch['created']
    ]);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
