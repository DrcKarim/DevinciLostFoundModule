<?php
namespace DescriptionWithAI\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use DescriptionWithAI\Service\MatchingService;

class ApiController extends AbstractActionController
{
    protected $matchingService;
    protected $logger;

    public function __construct($matchingService, $logger)
    {
        $this->matchingService = $matchingService;
        $this->logger = $logger;
    }

    /**
     * API endpoint to match lost objects with found objects
     * POST /api/match-lost-object
     */
    public function matchLostObjectAction()
    {
        // Add CORS headers
        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Access-Control-Allow-Origin', '*')
            ->addHeaderLine('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->addHeaderLine('Access-Control-Allow-Headers', 'Content-Type');
        
        // Handle OPTIONS preflight request
        if ($this->getRequest()->isOptions()) {
            $response->setStatusCode(200);
            return new JsonModel([]);
        }
        
        // Only allow POST requests
        if (!$this->getRequest()->isPost()) {
            $response->setStatusCode(405);
            return new JsonModel(['error' => 'Method not allowed']);
        }

        try {
            // Get JSON input
            $input = json_decode($this->getRequest()->getContent(), true);
            
            if (!$input) {
                return new JsonModel(['error' => 'Invalid JSON input']);
            }

            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';

            if (empty($description)) {
                return new JsonModel(['error' => 'Description is required']);
            }

            $this->logger->info("API: Matching request for '$title' - '$description'");

            // Find matching objects
            $matches = $this->matchingService->findMatchingObjects($title, $description);

            if (empty($matches)) {
                return new JsonModel([
                    'matchFound' => false,
                    'reason' => 'No found objects in database'
                ]);
            }

            // Return the best match (format compatible with frontend)
            $bestMatch = $matches[0];
            
            return new JsonModel([
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
            $this->logger->error('API Error: ' . $e->getMessage());
            $this->getResponse()->setStatusCode(500);
            return new JsonModel(['error' => 'Internal server error']);
        }
    }

    /**
     * Get all found objects (for debugging/admin)
     * GET /api/found-objects  
     */
    public function foundObjectsAction()
    {
        try {
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $items = $api->search('items', ['limit' => 100])->getContent();
            
            $objects = [];
            foreach ($items as $item) {
                $title = $item->value('dcterms:title');
                $description = $item->value('dcterms:description');
                
                if ($title && $description) {
                    $objects[] = [
                        'id' => $item->id(),
                        'title' => $title->value(),
                        'description' => $description->value(),
                        'created' => $item->created()->format('Y-m-d H:i:s')
                    ];
                }
            }
            
            return new JsonModel([
                'objects' => $objects,
                'total' => count($objects)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('API Error: ' . $e->getMessage());
            return new JsonModel(['error' => 'Failed to fetch objects']);
        }
    }
}