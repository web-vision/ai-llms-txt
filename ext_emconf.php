<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'LLMS TXT Generator',
    'description' => 'TYPO3 extension for generating llms.txt links according to llmstxt.org specification to control Large Language Model crawling policies.',
    'category' => 'be',
    'author' => 'web-vision GmbH',
    'author_email' => 'hello@web-vision.de',
    'state' => 'beta',
    'version' => '0.2.1',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.3.99',
            'backend' => '12.4.0-14.3.99',
            'extbase' => '12.4.0-14.3.99',
            'fluid' => '12.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
