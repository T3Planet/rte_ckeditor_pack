<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\DataProvider\Cards;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class CardData
{
    protected string $localLanguage;

    protected string $btnTitle;

    protected array $modules;

    public function __construct()
    {
        // Initialize the local language
        $this->localLanguage = 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:';

        $this->btnTitle =  LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack');

        $this->modules = [
            'TrackChanges' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.track_changes', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.track_changes', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.track_changes.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-document-edit',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'medium',
            ],
            'Comments' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.comments', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.comments', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.comments.description', 'rte_ckeditor_pack'),
                'icon' => 'content-messages',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'medium',
            ],
            'ImportWord' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.import_from_word', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.import_from_word', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.import_from_word.description', 'rte_ckeditor_pack'),
                'icon' => 'mimetypes-word',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'medium',
            ],
            'RevisionHistory' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.revision_history', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.revision_history', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.revision_history.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-history',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'medium',
            ],
            'ExportPdf' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.export_to_pdf', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.export_to_pdf', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.export_to_pdf.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-file-pdf',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'large',
            ],
            'ExportWord' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.export_to_word', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.export_to_word', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.export_to_word.description', 'rte_ckeditor_pack'),
                'icon' => 'mimetypes-word',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'large',
            ],
            'AIAssistant' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.ai_assistant', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.ai_assistant', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.ai_assistant.description', 'rte_ckeditor_pack'),
                'icon' => 'ai_assistant',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
                'coming_soon' => true,
            ],
            'Footnotes' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.footnotes', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.footnotes', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.footnotes.description', 'rte_ckeditor_pack'),
                'icon' => 'rte_footnotes',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'medium',
            ],
            'WProofreader' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.spell_and_grammar_check', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.spell_and_grammar_check', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.spell_and_grammar_check.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-check-badge-alt',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'small',
            ],
            'Pagination' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.pagination', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.pagination', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.pagination.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-pagetree',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'small',
            ],
            'MultiLevelList' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.multi_level_list', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.multi_level_list', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.multi_level_list.description', 'rte_ckeditor_pack'),
                'icon' => 'rte_multiLevelList',
                'modalSize' => 'small',
                'buttonTitle' => $this->btnTitle,
            ],
            'Mention' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.mention', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.mention', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.mention.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-pagetree',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'small',
            ],
            'RealTimeCollaboration' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.real_time_collaboration', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.real_time_collaboration', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.premium.real_time_collaboration.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-list',
                'data_confirmation' => 'false',
                'buttonTitle' => $this->btnTitle,
                'modalSize' => 'small',
            ],
            'SlashCommand' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.slash_commands', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.slash_commands', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.slash_commands.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-link',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'large',
            ],
            'Template' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.templates', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.templates', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.templates.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-viewmode-tiles',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'large',
            ],
            'PasteFromOfficeEnhanced' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.paste_from_office_enhanced', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.paste_from_office_enhanced', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.paste_from_office_enhanced.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-file-openoffice',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.checkbox.enable', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'FormatPainter' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.format_painter', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.format_painter', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.format_painter.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-brush',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.checkbox.enable', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'DocumentOutline' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.document_outline', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.document_outline', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.document_outline.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-document-view',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'FullScreen' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.full_screen_mode', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.full_screen_mode', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.full_screen_mode.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-fullscreen',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'small',
            ],
            'MergeFields' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.merge_fields', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.merge_fields', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.merge_fields.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-variable-add',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'large',
            ],
            'TableOfContents' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.table_contents', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.table_contents', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.table_contents.description', 'rte_ckeditor_pack'),
                'icon' => 'content-menu-pages',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.checkbox.enable', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'CaseChange' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.case_change', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.case_change', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.productivity.case_change.description', 'rte_ckeditor_pack'),
                'icon' => 'rte_caseChange',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'small',
            ],
            'Typo3Image' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.file_management.typo3Image', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.file_management.typo3Image', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.file_management.typo3Image.description', 'rte_ckeditor_pack'),
                'icon' => 'content-image',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.checkbox.enable', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Images' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.images', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.images', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.images.description', 'rte_ckeditor_pack'),
                'icon' => 'content-image',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Image' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.auto_image', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.auto_image', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.auto_image.description', 'rte_ckeditor_pack'),
                'icon' => 'content-image',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Indentation' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.block_indentation', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.block_indentation', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.block_indentation.description', 'rte_ckeditor_pack'),
                'icon' => 'content-beside-text-img-above-center',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Bookmark' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.bookmark', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.bookmark', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.bookmark.description', 'rte_ckeditor_pack'),
                'icon' => 'rte_bookmark',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'small',
            ],
            'Font' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.fonts', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.fonts', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.fonts.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-file-text',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'HighLight' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.highlight', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.highlight', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.highlight.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-open',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Heading' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.heading', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.heading', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.heading.description', 'rte_ckeditor_pack'),
                'icon' => 'content-header',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Alignment' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.alignment', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.alignment', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.alignment.description', 'rte_ckeditor_pack'),
                'icon' => 'content-beside-text-img-above-center',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Style' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.style', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.style', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.style.description', 'rte_ckeditor_pack'),
                'icon' => 'content-beside-text-img-above-center',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'PageBreak' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.page_break', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.page_break', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.page_break.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-insert',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'TextTransformation' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.text_transformation', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.text_transformation', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.text_transformation.description', 'rte_ckeditor_pack'),
                'icon' => 'form-text',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'ListProperties' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.document_list', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.document_list', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.document_list.description', 'rte_ckeditor_pack'),
                'icon' => 'apps-clipboard-list',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'WordCount' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.word_count', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.word_count', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.word_count.description', 'rte_ckeditor_pack'),
                'icon' => 'content-widget-number',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'small',
            ],
            'HtmlEmbed' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.htmlEmbed', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.htmlEmbed', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.htmlEmbed.description', 'rte_ckeditor_pack'),
                'icon' => 'content-carousel-html',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Code' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.code', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.code', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.code.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-code',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'CodeBlock' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.codeBlock', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.codeBlock', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.codeBlock.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-file-html',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'BlockToolbar' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.blockToolbar', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.blockToolbar', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.blockToolbar.description', 'rte_ckeditor_pack'),
                'icon' => 'apps-toolbar-menu-actions',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'BalloonToolbar' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.balloonToolbar', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.balloonToolbar', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.balloonToolbar.description', 'rte_ckeditor_pack'),
                'icon' => 'apps-toolbar-menu-workspace',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Markdown' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.markdown', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.markdown', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.markdown.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-barcode-read',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'MediaEmbed' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.mediaEmbed', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.mediaEmbed', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.mediaEmbed.description', 'rte_ckeditor_pack'),
                'icon' => 'content-textmedia',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'ShowBlocks' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.showBlocks', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.showBlocks', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.showBlocks.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-extension',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'TextPartLanguage' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.textPartLanguage', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.textPartLanguage', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.textPartLanguage.description', 'rte_ckeditor_pack'),
                'icon' => 'install-manage-language',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Menubar' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.menuBar', 'rte_ckeditor_pack'),
                'subtitle' => '',
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.menuBar.description', 'rte_ckeditor_pack'),
                'icon' => 'content-menu-related',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            'Emoji' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.emoji', 'rte_ckeditor_pack'),
                'subtitle' => '',
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.emoji.description', 'rte_ckeditor_pack'),
                'icon' => 'rte_emoji',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'small',
            ],
            'LineHeight' => [
                'title' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.lineHeight', 'rte_ckeditor_pack'),
                'subtitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.lineHeight', 'rte_ckeditor_pack'),
                'description' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.ckeditor5Plugins.lineHeight.description', 'rte_ckeditor_pack'),
                'icon' => 'actions-chevron-expand',
                'buttonTitle' => LocalizationUtility::translate($this->localLanguage . 'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
                'modalSize' => 'medium',
            ],
            // 'MathEquations' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.premium.math_equations', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.premium.math_equations', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.premium.math_equations.description', 'rte_ckeditor_pack'),
            //     'icon' => 'apps-toolbar-menu-systeminformation',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
            //     'coming_soon' => true,
            //     'modalSize' => 'small',
            // ],
            // 'CkBox' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.file_management.ckbox', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.file_management.ckbox', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.file_management.ckbox.description', 'rte_ckeditor_pack'),
            //     'icon' => 'form-image-upload',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
            //     'coming_soon' => true,
            //     'modalSize' => 'small',
            // ],
            // 'FindAndReplace' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.find_and_replace', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.find_and_replace', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.find_and_replace.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-replace',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
            // ],
            // 'Notification' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.premium.notifications', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.premium.notifications', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.premium.notifications.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-list',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
            //     'coming_soon' => true,
            //     'modalSize' => 'small',
            // ],
            // 'TextStyles' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.text_style', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.text_style', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.text_style.description', 'rte_ckeditor_pack'),
            //     'icon' => 'content-header',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
            //     'modalSize' => 'medium',
            // ],
            // 'SelectAll' =>[
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.select_all', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.select_all', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.select_all.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-selection-elements-all',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
            // ],
            // 'SpecialCharacters' =>[
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.special_characters', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.special_characters', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.special_characters.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-heart',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
            // ],
            // 'Autosave' =>[
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.autosave', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.autosave', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.autosave.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-save-add',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
            //     'modalSize' => 'medium',
            // ],
            // 'SourceEditing' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.sourceEditing', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.sourceEditing', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.sourceEditing.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-file-edit',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
            //     'modalSize' => 'medium',
            // ],
            // 'CollaborativeDocumentEditor' =>  [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.CollaborativeDocumentEditor', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.CollaborativeDocumentEditor', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.CollaborativeDocumentEditor.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-chat',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
            //     'modalSize' => 'small',
            //     'coming_soon' => true
            // ],
            // 'FeatureRichEditor' =>  [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.FeatureRichEditor', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.FeatureRichEditor', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.FeatureRichEditor.description', 'rte_ckeditor_pack'),
            //     'icon' => 'module-rte-ckeditor',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
            //     'modalSize' => 'small',
            //     'coming_soon' => true,
            // ],
            // 'DocumentEditor' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.DocumentEditor', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.DocumentEditor', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.DocumentEditor.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-document-synchronize',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
            //     'modalSize' => 'small',
            //     'coming_soon' => true
            // ],
            // 'CollaborativeArticleEditor' => [
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.CollaborativeArticleEditor', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.CollaborativeArticleEditor', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.layout.CollaborativeArticleEditor.description', 'rte_ckeditor_pack'),
            //     'icon' => 'actions-document-edit',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.coming-soon', 'rte_ckeditor_pack'),
            //     'modalSize' => 'small',
            //     'coming_soon' => true
            // ],
            // 'RestrictedEditingMode' =>[
            //     'title' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.restrictedEditing', 'rte_ckeditor_pack'),
            //     'subtitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.restrictedEditing', 'rte_ckeditor_pack'),
            //     'description' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.ckeditor5Plugins.restrictedEditing.description', 'rte_ckeditor_pack'),
            //     'icon' => 'overlay-restricted',
            //     'buttonTitle' => LocalizationUtility::translate($this->localLanguage.'ckeditorKit.tab.card.btn_title', 'rte_ckeditor_pack'),
            //     'modalSize' => 'small',
            //     'coming_soon' => true,
            // ],

        ];

    }

    public function getDetailsByKey(string $key): ?array
    {
        return $this->modules[$key] ?? null;
    }

    public function getIconByKey(string $key): string
    {
        if (array_key_exists($key, $this->modules)) {
            return $this->modules[$key] ? $this->modules[$key]['icon'] : '';
        }
        return '';
    }
}
