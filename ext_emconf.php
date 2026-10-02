<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Monitoring: scheduler and log insights',
    'description' => 'Adds scheduler, system log and log file insights to the t3monitoring_client endpoint',
    'category' => 'services',
    'author' => 'Kai Seliger',
    'author_email' => 'kai@yesjoar.com',
    'author_company' => 'yesjoar',
    'state' => 'beta',
    'version' => '0.2.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '12.4.0-14.3.99',
            't3monitoring_client' => '11.0.0-11.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'scheduler' => '12.4.0-14.3.99',
        ],
    ],
];
