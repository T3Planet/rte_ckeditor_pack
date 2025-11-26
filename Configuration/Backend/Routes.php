<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use T3Planet\RteCkeditorPack\Controller\RteModuleController;

/**
 * Definitions of routes
 */
return [
    'rteckeditorimage_wizard_select_image' => [
        'path' => '/rte/wizard/selectimage',
        'target' => \T3Planet\RteCkeditorPack\Controller\SelectImageController::class . '::mainAction',
        'parameters' => [
            'mode' => 'file',
        ],
    ],
    'dashboard' => [
        'path' => '/ckeditor/premium',
        'target' => RteModuleController::class . '::mainAction',
    ],
    'toolbar_groups' => [
        'path' => '/ckeditor/toolbar-groups',
        'target' => RteModuleController::class . '::saveToolBarGroups',
    ]
];
