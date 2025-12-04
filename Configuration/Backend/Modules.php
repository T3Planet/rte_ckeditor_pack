<?php

use T3Planet\RteCkeditorPack\Controller\RteModuleController;

return [
    'ckeditor_premium' => [
        'access' => 'user',
        'position' => ['top'],
        'path' => '/ckeditor/premium',
        'iconIdentifier' => 'ckeditor_module',
        'labels' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf',
        'parent' => 'web',
        'extensionName' => 'RteCkeditorPack',
        'inheritNavigationComponentFromMainModule' => false,
        'controllerActions' => [
            RteModuleController::class => [
                'main',
                'settings'
            ]
        ],
    ],
];
