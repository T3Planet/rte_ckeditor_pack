<?php

use T3Planet\RteCkeditorPack\Form\Element\RichTextElement;
use T3Planet\RteCkeditorPack\Form\Element\RichTextElementV12;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\RichTextElement as CoreElem;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_pack'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/editor.css';
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_emoji'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/emoji.css';
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_notification'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/notification.css';

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
$majorVersion = $versionInformation->getMajorVersion();

// Add TYPO3 v14 specific stylesheet
if ($majorVersion >= 14) {
    $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_pack_v14'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/editor-fourteen.css';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
     = \T3Planet\RteCkeditorPack\Database\RteImagesDbHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['rte_ckeditor_config'] = [
    'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
    'options' => [
        'defaultLifetime' => 3600,
    ],
];

// Register Fluid ViewHelper namespace for ckit
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ckit'] = [
    'T3Planet\\RteCkeditorPack\\ViewHelpers',
];

switch ($majorVersion) {
    case 12:
        // TYPO3 v12
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreElem::class] = [
            'className' => RichTextElementV12::class,
        ];
        break;
    case 13:
    case 14:
    default:
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreElem::class] = [
            'className' => RichTextElement::class,
        ];
        break;
}
