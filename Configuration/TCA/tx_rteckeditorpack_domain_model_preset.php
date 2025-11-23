<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_preset',
        'label' => 'preset_key',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'rootLevel' => 1,
        'delete' => 'deleted',
        'hideTable' => false,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'preset_key',
        'iconfile' => 'EXT:rte_ckeditor_pack/Resources/Public/Icons/tx_rteckeditorpack_domain_model_preset.gif',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_preset.tab.general,
                preset_key,
                --div--;LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_preset.tab.features,
                features,
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
        'preset_key' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_preset.preset_key',
            'description' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_preset.preset_key.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required,unique',
                'default' => '',
            ],
        ],
        'features' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_preset.features',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_rteckeditorpack_domain_model_feature',
                'foreign_field' => 'preset_uid',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'levelLinksPosition' => 'top',
                    'useSortable' => true,
                    'showPossibleLocalizationRecords' => false,
                    'showRemovedLocalizationRecords' => false,
                    'showAllLocalizationLink' => false,
                    'showSynchronizationLink' => false,
                    'enabledControls' => [
                        'new' => true,
                        'dragdrop' => true,
                        'sort' => true,
                        'hide' => false,
                        'delete' => true,
                        'localize' => false,
                    ],
                ],
            ],
        ],
    ],
];

