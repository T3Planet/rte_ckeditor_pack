<?php

use T3Planet\RteCkeditorPack\Form\Element\RichTextElement;
use T3Planet\RteCkeditorPack\Form\Element\RichTextElementV12;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\RichTextElement as CoreElem;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_pack'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/editor.css';
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_emoji'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/emoji.css';
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_notification'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/notification.css';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
     = \T3Planet\RteCkeditorPack\Database\RteImagesDbHook::class;

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
$majorVersion = $versionInformation->getMajorVersion();
// Only include page.tsconfig if TYPO3 version is below 12 so that it is not imported twice.
if ($majorVersion < 12) {
    ExtensionManagementUtility::addPageTSConfig(
        '@import "EXT:rte_ckeditor_pack/Configuration/page.tsconfig"',
    );
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['rte_ckeditor_config'] = [
    'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
    'options' => [
        'defaultLifetime' => 3600,
    ],
];

// Load version-specific RichTextElement implementation
if ($majorVersion >= 13) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreElem::class] = [
        'className' => RichTextElement::class,
    ];
} else {
    // TYPO3 v12
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreElem::class] = [
        'className' => RichTextElementV12::class,
    ];
}
