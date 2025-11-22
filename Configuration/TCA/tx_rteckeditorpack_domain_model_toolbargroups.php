<?php

return [
    'ctrl' => [
        'title' => 'Toolbar groups',
        'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbargroups',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'rootLevel' => 1,
        'delete' => 'deleted',
        'hideTable' => true,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'label',
        'iconfile' => 'EXT:rte_ckeditor_pack/Resources/Public/Icons/tx_rteckeditorpack_domain_model_toolbar_groups.gif',
    ],
    'types' => [
        '1' => ['showitem' => 'label, tooltip,icon,items,custom_icon,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden'],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
            ],
        ],
        'label' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbargroups.label',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'tooltip' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbargroups.tooltip',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'icon' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbargroups.icon',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'items' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbargroups.items',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'custom_icon' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbargroups.custom_icon',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
    ],
];
