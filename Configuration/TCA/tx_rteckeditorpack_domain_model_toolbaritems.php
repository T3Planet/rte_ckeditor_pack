<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbaritems',
        'label' => 'preset',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'rootLevel' => 1,
        'delete' => 'deleted',
        'hideTable' => true,
        'searchFields' => 'preset',
        'iconfile' => 'EXT:rte_ckeditor_pack/Resources/Public/Icons/tx_rteckeditorpack_domain_model_toolbaritems.gif',
    ],
    'types' => [
        '1' => ['showitem' => 'preset,items'],
    ],
    'columns' => [
        'preset' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbaritems.preset',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'items' => [
            'exclude' => true,
            'label' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_db.xlf:tx_rteckeditorpack_domain_model_toolbaritems.items',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
    ],
];
