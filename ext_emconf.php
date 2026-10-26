<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'LLMS TXT Generator',
    'description' => 'Generates an llms.txt file according to the llmstxt.org specification and serves pages as Markdown, giving Large Language Models a structured, machine-readable view of your site.',
    'category' => 'fe',
    'author' => 'web-vision GmbH',
    'author_email' => 'hello@web-vision.de',
    'state' => 'stable',
    'version' => '0.4.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'backend' => '13.4.0-14.3.99',
            'extbase' => '13.4.0-14.3.99',
            'fluid' => '13.4.0-14.3.99',
            'frontend' => '13.4.0-14.3.99',
            'seo' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
