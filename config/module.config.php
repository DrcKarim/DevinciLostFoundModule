<?php
namespace DescriptionWithAI;

return [
    'service_manager' => [
        'factories' => [
            Service\TextAiService::class => Service\TextAiServiceFactory::class,
        ],
    ],
];
