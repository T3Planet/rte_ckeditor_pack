<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_configuration',
        'label' => 'config_key',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'rootLevel' => 1,
        'delete' => 'deleted',
        'hideTable' => true,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'config_key',
        'iconfile' => 'EXT:rte_ckeditor_pack/Resources/Public/Icons/tx_rteckeditorpack_domain_model_configuration.gif',
    ],
    'types' => [
        '1' => ['showitem' => 'enable, config_key,fields,preset,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden'],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
            ],
        ],
        'config_key' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_configuration.config_key',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'preset' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_configuration.preset',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'enable' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_configuration.enable',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'fields' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_configuration.fields',
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 15,
                'eval' => 'trim',
                'default' => '',
            ],
        ],

    ],
];
