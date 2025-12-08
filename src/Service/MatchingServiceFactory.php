<?php
namespace DescriptionWithAI\Service;

class MatchingServiceFactory
{
    public function __invoke($container, $requestedName, $options = null)
    {
        $textAiService = $container->get(TextAiService::class);
        $api = $container->get('Omeka\ApiManager');
        $logger = $container->get('Omeka\Logger');
        
        return new MatchingService($textAiService, $api, $logger);
    }
}