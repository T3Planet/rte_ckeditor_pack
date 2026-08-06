<?php

use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use T3Planet\RteCkeditorPack\Form\Element\RichTextElement;
use T3Planet\RteCkeditorPack\Form\Element\RichTextElementV12;
use TYPO3\CMS\RteCKEditor\Form\Element\RichTextElement as CoreElem;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_pack'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/editor.css';
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['rte_ckeditor_notification'] = 'EXT:rte_ckeditor_pack/Resources/Public/Css/notification.css';

// Premium feature permission options shown in BE group access lists
$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'] = [
    'rte_editor' => [
        'header' => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:feature.permissions_header',
        'items' => [
            'TrackChanges' => ['TrackChanges', 'actions-document-edit'],
            'TableOfContents' => ['TableOfContents', 'content-menu-pages'],
            'RevisionHistory' => ['RevisionHistory', 'actions-history'],
            'MultiLevelList' => ['MultiLevelList', 'apps-pagetree-category-expand-all'],
            'Template' => ['Template', 'actions-viewmode-tiles'],
            'ImportWord' => ['ImportWord', 'mimetypes-word'],
            'FormatPainter' => ['FormatPainter', 'actions-brush'],
            'ExportWord' => ['ExportWord', 'mimetypes-word'],
            'ExportPdf' => ['ExportPdf', 'actions-file-pdf'],
            'Comments' => ['Comments', 'content-messages'],
            'CaseChange' => ['CaseChange', 'actions-exchange'],
            'ToggleAi' => ['ToggleAi', 'rte_toggleAi'],
            'AiQuickActions' => ['AiQuickActions', 'rte_aiQuickActions'],
            'DocumentOutline' => ['DocumentOutline', 'actions-document-view'],
            'SlashCommand' => ['SlashCommand', 'actions-link'],
            'PasteFromOfficeEnhanced' => ['PasteFromOfficeEnhanced', 'actions-file-openoffice'],
            'Bookmark' => ['Bookmark', 'rte_bookmark'],
            'Pagination' => ['Pagination', 'actions-pagetree'],
            'MergeFields' => ['MergeFields', 'actions-variable-add'],
            'Footnotes' => ['Footnotes', 'rte_footnotes'],
            'RestrictedEditingMode' => ['RestrictedEditingMode', 'rte_restrictedEditing'],
        ],
    ],
];

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
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Richtext::class] = [
            'className' => \T3Planet\RteCkeditorPack\Configuration\Richtext::class,
        ];
        // Transform-text events are v13+; restore escaped comment/suggestion markers here.
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][RteHtmlParser::class] = [
            'className' => \T3Planet\RteCkeditorPack\Html\RteHtmlParser::class,
        ];
        break;
    case 13:
        // TYPO3 v13: custom Richtext processing + custom RichTextElement (intentional fall-through).
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Richtext::class] = [
            'className' => \T3Planet\RteCkeditorPack\Configuration\Richtext::class,
        ];
    case 14:
    default:
        if ($majorVersion >= 14) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Richtext::class] = [
                'className' => \T3Planet\RteCkeditorPack\Configuration\RichtextV14::class,
            ];
        }
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreElem::class] = [
            'className' => RichTextElement::class,
        ];
        break;
}
