<?php
namespace DescriptionWithAI;

return [
    'service_manager' => [
        'factories' => [
            Service\TextAiService::class => Service\TextAiServiceFactory::class,
            Service\MatchingService::class => Service\MatchingServiceFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\ApiController::class => Controller\ApiControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'api-match-lost-object' => [
                'type' => 'Literal',
                'options' => [
                    'route' => '/api/match-lost-object',
                    'defaults' => [
                        'controller' => Controller\ApiController::class,
                        'action' => 'matchLostObject',
                    ],
                ],
            ],
            'api-found-objects' => [
                'type' => 'Literal', 
                'options' => [
                    'route' => '/api/found-objects',
                    'defaults' => [
                        'controller' => Controller\ApiController::class,
                        'action' => 'foundObjects',
                    ],
                ],
            ],
        ],
    ],
];
