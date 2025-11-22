<?php

defined('TYPO3_MODE') || defined('TYPO3') || die();

(static function (): void {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
        'rte_ckeditor_pack',
        'Configuration/TSconfig/Page/rte_preset.tsconfig',
        'RTE CKEditor Pack :: Config RTE Preset'
    );
})();
