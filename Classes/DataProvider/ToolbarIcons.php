<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\DataProvider;

class ToolbarIcons
{
    private const ICONS = [
        'comment' => 'actions-comment',
        'commentsArchive' => 'actions-chat',
        'fullscreen' => 'actions-fullscreen',
        'insertImage' => 'rte_insertImage',
        'bold' => 'rte_bold',
        'italic' => 'rte_italic',
        'underline' => 'rte_underline',
        'strikethrough' => 'rte_strikethrough',
        'code' => 'rte_code',
        'subscript' => 'rte_subscript',
        'superscript' => 'rte_superscript',
        'outdent' => 'rte_outdent',
        'indent' => 'rte_indent',
        'fontFamily' => 'rte_fontFamily',
        'fontSize' => 'rte_fontSize',
        'fontColor' => 'rte_fontColor',
        'fontBackgroundColor' => 'rte_fontBackgroundColor',
        'Highlight' => 'actions-open',
        'heading' => 'content-header',
        'alignment' => 'rte_alignment',
        'style' => 'content-beside-text-img-above-center',
        'codeBlock' => 'actions-file-html',
        'mediaEmbed' => 'rte_mediaEmbed',
        'textPartLanguage' => 'install-manage-language',
        'TodoList' => 'actions-list',
        'findAndReplace' => 'rte_findAndReplace',
        'specialCharacters' => 'rte_specialCharacters',
        'pageBreak' => 'rte_pageBreak',
        'htmlEmbed' => 'content-carousel-html',
        'selectAll' => 'rte_SelectAll',
        'showBlocks' => 'rte_showBlocks',
        'emoji' => 'rte_emoji',
        'lineHeight' => 'actions-chevron-expand',
        'sourceEditing' => 'rte_sourceEditing',
        'ImportWord' => 'mimetypes-word',
        'ExportWord' => 'mimetypes-word',
        'ExportPdf' => 'actions-file-pdf',
        'toggleAi' => 'rte_toggleAi',
        'aiQuickActions' => 'rte_aiQuickActions',
        'insertFootnote' => 'rte_footnotes',
        'footnotesStyle' => 'rte_footnotesStyle',
        'bookmark' => 'rte_bookmark',
        'multiLevelList' => 'rte_multiLevelList',
        'caseChange' => 'rte_caseChange',
        'insertTemplate' => 'actions-viewmode-tiles',
        'FormatPainter' => 'actions-brush',
        'TableOfContents' => 'content-menu-pages',
        'revisionHistory' => 'actions-history',
        'trackChanges' => 'actions-code-compare',
        'undo' => 'rte_undo',
        'redo' => 'rte_redo',
        'link' => 'rte_link',
        'insertTable' => 'rte_insertTable',
        'tableColumn' => 'rte_tableColumn',
        'tableRow' => 'rte_tableRow',
        'mergeTableCells' => 'rte_mergeTableCells',
        'insertMergeField' => 'actions-eye-link',
        'previewMergeFields' => 'actions-eye',
        'TableProperties' => 'rte_tableProperties',
        'TableCellProperties' => 'rte_tableCellProperties',
        'removeFormat' => 'rte_removeFormat',
        'blockQuote' => 'rte_quote',
        'softhyphen' => 'rte_softHyphen',
        'horizontalLine' => 'rte_horizontalLine',
        'numberedList' => 'rte_numberedList',
        'bulletedList' => 'rte_bulletedList',
        'highlight' => 'rte_highlight',
        'previousPage' => 'actions-chevron-double-left',
        'nextPage' => 'actions-chevron-double-right',
        'pageNavigation' => 'actions-pagetree',
        'clipboard' => 'actions-clipboard',
        'alignment:left' => 'rte_alignment_left',
        'alignment:center' => 'rte_alignment_center',
        'alignment:right' => 'rte_alignment_right',
        'alignment:justify' => 'rte_alignment_justify',
        'address' => 'actions-marker',
    ];

    private const PREMIUM_TOOLBAR_ITEMS = [
        'toggleAi',
        'aiQuickActions',
        'TodoList',
        'ImportWord',
        'ExportPdf',
        'ExportWord',
        'insertFootnote',
        'footnotesStyle',
        'previousPage',
        'nextPage',
        'pageNavigation',
        'multiLevelList',
        'bookmark',
        'comment',
        'commentsArchive',
        'revisionHistory',
        'trackChanges',
        'insertTemplate',
        'caseChange',
        'insertMergeField',
        'previewMergeFields',
        'FormatPainter',
        'TableOfContents',
    ];

    public function getIconByName(string $name): ?string
    {
        return self::ICONS[$name] ?? null;
    }

    public function isPremiumToolbarItem(string $name): bool
    {
        return in_array($name, self::PREMIUM_TOOLBAR_ITEMS, true);
    }
}
