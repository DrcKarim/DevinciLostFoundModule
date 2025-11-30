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
    $logger = $this->getServiceLocator()->get('Omeka\Logger');
    $logger->info("AI module triggered");

    // 1) Get ENTITY from event
    $entity = $event->getParam('response')->getContent();

    // 2) Convert entity → representation
    $api = $this->getServiceLocator()->get('Omeka\ApiManager');
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

    // 4) Call AI service
    $ai = $this->getServiceLocator()->get(Service\TextAiService::class);
    $aiSummary = $ai->summarizeText($text);

    if (!$aiSummary) {
        $logger->error("AI returned null.");
        return;
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
}


}
