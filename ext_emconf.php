<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'LLMS TXT Generator',
    'description' => 'Generates an llms.txt file according to the llmstxt.org specification and serves pages as Markdown, giving Large Language Models a structured, machine-readable view of your site.',
    'category' => 'fe',
    'author' => 'web-vision GmbH',
    'author_email' => 'hello@web-vision.de',
    'state' => 'beta',
    'version' => '0.2.2',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
            'backend' => '12.4.0-12.4.99',
            'extbase' => '12.4.0-12.4.99',
            'fluid' => '12.4.0-12.4.99',
            'frontend' => '12.4.0-12.4.99',
            'seo' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
