<?php
namespace DescriptionWithAI\Controller;

class ApiControllerFactory
{
    public function __invoke($container, $requestedName, $options = null)
    {
        $matchingService = $container->get(\DescriptionWithAI\Service\MatchingService::class);
        $logger = $container->get('Omeka\Logger');
        
        return new ApiController($matchingService, $logger);
    }
}