<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'LLMS TXT Generator',
    'description' => 'TYPO3 extension for generating llms.txt links according to llmstxt.org specification to control Large Language Model crawling policies.',
    'category' => 'be',
    'author' => 'web-vision GmbH',
    'author_email' => 'hello@web-vision.de',
    'state' => 'beta',
    'version' => '0.1.6',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-14.0.0',
            'backend' => '12.0.0-14.0.0',
            'extbase' => '12.0.0-14.0.0',
            'fluid' => '12.0.0-14.0.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
