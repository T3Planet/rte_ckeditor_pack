<?php

use T3Planet\RteCkeditorPack\Controller\CommentsController;
use T3Planet\RteCkeditorPack\Controller\RteModuleController;

return [
    'save_comments' => [
        'path' => '/save/comments',
        'target' => CommentsController::class . '::saveCommentsAction',
    ],
    'fetch_comments' => [
        'path' => '/fetch/comments',
        'target' => CommentsController::class . '::fetchCommentsAction',
    ],
    'get_ckeditorKit_settings' => [
        'path' => '/get/ckeditorKit-settings',
        'target' => RteModuleController::class . '::getCkeditorSettings',
    ],
    'get_ckeditorKit_coming_soon' => [
        'path' => '/get/ckeditorKit-coming-soon',
        'target' => RteModuleController::class . '::getCkeditorComingSoon',
    ],
    'toolbar_configuration' => [
        'path' => '/get/ckeditorKit-toolbar',
        'target' => RteModuleController::class . '::getToolBar',
    ],
    'new_preset' => [
        'path' => '/manage/preset',
        'target' => RteModuleController::class . '::managePreset',
    ],
    'save_feature_configuration' => [
        'path' => '/ckeditor/feature/configuration',
        'target' => RteModuleController::class . '::saveSettings',
    ],
    'sync_preset' => [
        'path' => '/preset/sync',
        'target' => RteModuleController::class . '::syncPreset',
    ],
    'reset_preset' => [
        'path' => '/preset/reset',
        'target' => RteModuleController::class . '::resetPreset',
    ],
];
