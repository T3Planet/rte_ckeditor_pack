<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

call_user_func(function () {
    $extensionKey = 'rte_ckeditor_pack';

    ExtensionManagementUtility::addStaticFile(
        $extensionKey,
        'Configuration/TypoScript',
        'CKEditor plugin: rte_ckeditor_pack'
    );
});
