<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Register Premium feature permission options shown in BE group access lists
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
            'AIAssistant' => ['AIAssistant', 'ai_assistant'],
            'DocumentOutline' => ['DocumentOutline', 'actions-document-view'],
            'SlashCommand' => ['SlashCommand', 'actions-link'],
            'PasteFromOfficeEnhanced' => ['PasteFromOfficeEnhanced', 'actions-file-openoffice'],
            'Bookmark' => ['Bookmark', 'rte_bookmark'],
            'Pagination' => ['Pagination', 'actions-pagetree'],
            'MergeFields' => ['MergeFields', 'actions-variable-add'],
            'Footnotes' => ['Footnotes', 'rte_footnotes'],
        ],
    ],
];

$pageRenderer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
$pageRenderer->addInlineLanguageLabelFile('EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_notifications.xlf');
$pageRenderer->addInlineLanguageLabelFile('EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf');
