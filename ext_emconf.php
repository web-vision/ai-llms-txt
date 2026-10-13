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
            'typo3' => '12.0.0-14.0.0',
            'backend' => '12.0.0-14.0.0',
            'extbase' => '12.0.0-14.0.0',
            'fluid' => '12.0.0-14.0.0',
            'frontend' => '12.0.0-14.0.0',
            'seo' => '12.0.0-14.0.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
