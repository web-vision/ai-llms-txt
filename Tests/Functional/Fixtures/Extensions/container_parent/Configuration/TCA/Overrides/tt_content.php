<?php

defined('TYPO3') or die();

// As b13/container defines it: the uid of the container a content element is a child of
$GLOBALS['TCA']['tt_content']['columns']['tx_container_parent'] = [
    'label' => 'Container',
    'config' => [
        'type' => 'number',
    ],
];
