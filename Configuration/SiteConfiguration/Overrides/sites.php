<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$version = (string) GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

$GLOBALS['SiteConfiguration']['site']['columns']['llmsTxtEnabled'] = [
    'label' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtEnabled',
    'description' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtEnabled.description',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 1,
        'items' => [
            [
                'label' => '',
                'labelChecked' => 'Enabled',
                'labelUnchecked' => 'Disabled',
            ],
        ],
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['llmsTxtTitle'] = [
    'label' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtTitle',
    'description' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtTitle.description',
    'config' => [
        'type' => 'input',
        'placeholder' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtTitle.placeholder',
        'eval' => 'trim',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['llmsTxtDescription'] = [
    'label' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtDescription',
    'description' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtDescription.description',
    'config' => [
        'type' => 'text',
        'rows' => 3,
        'eval' => 'trim',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['llmsTxtAdditionalInfo'] = [
    'label' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtAdditionalInfo',
    'description' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtAdditionalInfo.description',
    'config' => [
        'type' => 'text',
        'rows' => 10,
        'renderType' => $version >= '13' ? 'codeEditor' : 'text',
        'eval' => 'trim',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['llmsTxtContactEmail'] = [
    'label' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtContactEmail',
    'description' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtContactEmail.description',
    'config' => [
        'type' => 'input',
        'eval' => 'trim,email',
        'placeholder' => 'contact@example.com',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['llmsTxtKeywords'] = [
    'label' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtKeywords',
    'description' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtKeywords.description',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'placeholder' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtKeywords.placeholder',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['llmsTxtMaxDepth'] = [
    'label' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtMaxDepth',
    'description' => 'LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.llmsTxtMaxDepth.description',
    'config' => [
        'type' => 'number',
        'default' => 2,
        'range' => [
            'lower' => 1,
            'upper' => 5,
        ],
    ],
];

if (!isset($GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'])) {
    $GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] = '';
}

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] .= ',
    --div--;LLL:EXT:ai_llms_txt/Resources/Private/Language/locallang.xlf:site.tab.llmstxt,
        llmsTxtEnabled,
        llmsTxtTitle,
        llmsTxtDescription,
        llmsTxtAdditionalInfo,
        llmsTxtContactEmail,
        llmsTxtKeywords,
        llmsTxtMaxDepth
';
