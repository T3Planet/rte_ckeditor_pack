<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature',
        'label' => 'config_key',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'rootLevel' => 1,
        'delete' => 'deleted',
        'hideTable' => true,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'config_key',
        'iconfile' => 'EXT:rte_ckeditor_pack/Resources/Public/Icons/tx_rteckeditorpack_domain_model_feature.gif',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature.tab.general,
                enable, config_key, fields, toolbar_item,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                hidden, deleted
            ',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
        'deleted' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.deleted',
            'config' => [
                'type' => 'check',
            ],
        ],
        'preset_uid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'sorting' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'enable' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature.enable',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'config_key' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature.config_key',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
                'default' => '',
            ],
        ],
        'fields' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature.fields',
            'description' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature.fields.description',
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 15,
                'eval' => 'trim',
                'default' => '',
                'enableRichtext' => false,
            ],
        ],
        'toolbar_item' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature.toolbar_items',
            'description' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_feature.toolbar_items.description',
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 5,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
    ],
];

