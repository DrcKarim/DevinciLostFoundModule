<?php
namespace DevinciLostFoundModule;

use Omeka\Module\AbstractModule;
use Omeka\Api\Representation\ItemRepresentation;
use Laminas\EventManager\Event;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function attachListeners($sharedEventManager)
    {
        // When a new item is created
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.create.post',
            [$this, 'generateDescriptionFromImage']
        );
    }

    public function generateDescriptionFromImage(Event $event)
    {
        $response = $event->getParam('response');
        $item = $response->getContent();

        /** @var ItemRepresentation $item */

        // Get image media
        $media = $item->media();
        if (!count($media)) {
            return;
        }

        $imageUrl = null;

        foreach ($media as $m) {
            if (strpos($m->mediaType(), 'image/') === 0) {
                $imageUrl = $m->originalUrl();
                break;
            }
        }

        if (!$imageUrl) {
            return;
        }

        // Call AI service
        $service = $this->getServiceLocator()->get('DevinciLostFoundModule_ImageAiService');
        $generatedDescription = $service->describeImage($imageUrl);

        if (!$generatedDescription) {
            return;
        }

        // Update item with the new description
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $api->update('items', $item->id(), [
            'o:resource_template' => $item->resourceTemplate() ? $item->resourceTemplate()->id() : null,
            'o:resource_class' => $item->resourceClass() ? $item->resourceClass()->id() : null,

            'dcterms:description' => [
                [
                    'type' => 'literal',
                    'property_id' => 4,  // dcterms:description ID
                    'value' => $generatedDescription
                ]
            ]
        ], [], ['isPartial' => true]);
    }
}
