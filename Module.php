<?php
namespace DescriptionWithAI;

use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Omeka\Api\Representation\ItemRepresentation;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function attachListeners($shared)
    {
        $shared->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.create.post',
            [$this, 'processTextDescription']
        );
    }
public function processTextDescription(Event $event)
{
    try {
        // Increase PHP execution time for AI processing
        set_time_limit(300); // 5 minutes
        
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\\Logger');
        $logger->info("AI module triggered");

        // 1) Get ENTITY from event
        $response = $event->getParam('response');
        if (!$response) {
            $logger->error("No response found in event");
            return;
        }
        
        $entity = $response->getContent();
        if (!$entity) {
            $logger->error("No entity found in response");
            return;
        }

        // 2) Convert entity → representation
        $api = $services->get('Omeka\ApiManager');
        $item = $api->read('items', $entity->getId())->getContent();

    $logger->info("Item ID: " . $item->id());

    // 3) Extract user description
    $desc = $item->value('dcterms:description');
    if (!$desc) {
        $logger->info("No description found.");
        return;
    }

    $text = $desc->value();
        $logger->info("User description: " . $text);

        // 4) Call AI service with timeout handling
        $logger->info("Starting AI processing for item ID: " . $item->id());
        
        $ai = $services->get(Service\TextAiService::class);
        
        // Set a reasonable timeout for AI processing
        $aiSummary = null;
        $startTime = microtime(true);
        
        try {
            // Use a shorter timeout to prevent hanging
            ini_set('default_socket_timeout', 30);
            $aiSummary = $ai->summarizeText($text);
        } catch (Exception $aiException) {
            $logger->error("AI processing exception: " . $aiException->getMessage());
        }
        
        $processingTime = round(microtime(true) - $startTime, 2);

        if (!$aiSummary || empty(trim($aiSummary))) {
            $logger->info("AI processing failed or returned empty result after {$processingTime} seconds. Item will be saved without AI summary.");
            $aiSummary = "Automatic description pending - manual review";
        } else {
            $logger->info("AI processing completed successfully in {$processingTime} seconds");
        }

        $logger->info("AI summary: " . $aiSummary);

        // 5) READ EXISTING o:data (stored as literal JSON)
        $dataLiteral = $item->value('o:data'); // Returns ValueRepresentation or null
        $data = $dataLiteral ? json_decode($dataLiteral->value(), true) : [];

        // 6) Add our AI result
        $data['ai_description'] = $aiSummary;

        // 7) SAVE BACK into item
        // o:data expects a literal JSON string
        $api->update('items', $item->id(), [
            'o:data' => json_encode($data)
        ], [], ['isPartial' => true]);

        $logger->info("AI description saved into o:data successfully.");
        
    } catch (\Exception $e) {
        // Log the error but don't break the item creation
        if (isset($logger)) {
            $logger->error("DescriptionWithAI module error: " . $e->getMessage());
            $logger->error("Stack trace: " . $e->getTraceAsString());
        }
    }
}


}
