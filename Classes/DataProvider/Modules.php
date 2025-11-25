<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider;

use T3Planet\RteCkeditorPack\DataProvider\Cards\CardData;
use T3Planet\RteCkeditorPack\DataProvider\Cards\Tabs;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\AIFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\AlignmentFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\BalloonToolbarFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\BlockToolbarFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\CaseChangeFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\CodeBlockFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\CollaborationFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\ExportPdfFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\ExportWordFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\FeatureInterface;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\FontFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\FootnotesFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\HeadingFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\HighlightFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\ImageFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\ImportWordFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\IndentFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\LanguageFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\MentionFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\MenuBarFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\MergeFieldsFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\PaginationFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\SlashCommandFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\StyleFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\TemplateFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\TransformationsFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\WordCountFeature;
use T3Planet\RteCkeditorPack\DataProvider\CkFeatures\WProofreaderFeature;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\SettingsConfiguration;
use T3Planet\RteCkeditorPack\Domain\Repository\ConfigurationRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Modules
{
    protected array $ckeditorModules = [];

    protected array $ckeditorSettings = [];

    protected ConfigurationRepository $configurationRepository;

    public function __construct()
    {
        $this->configurationRepository = GeneralUtility::makeInstance(ConfigurationRepository::class);

        // Create a ModuleDetails object
        $cardDetails = new CardData();

        // Dynamically fetch module details using their unique keys
        $this->ckeditorModules = [
            [
                'configuration' => [
                    'enable' => 0,
                    'config_key' => 'FeatureConfiguration',
                ],
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('ToggleAi'),
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'ToggleAi',
                    'module' => [
                        [
                            'library' => '@t3planet/RteCkeditorPack/ai-sidebar',
                            'exports' => 'AISidebar'
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-cloud-services',
                            'exports' => 'CloudServices'
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-ai',
                            'exports' => 'AIChat,AIEditorIntegration,AIQuickActions,AIReviewMode'
                        ],
                    ],
                    'toolBarItems' => 'toggleAi,aiQuickActions',
                ],
                'fields' => $this->getFieldsFromFeature(AIFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('ImportWord'),
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'ImportWord',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-cloud-services',
                            'exports' => 'CloudServices',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-import-word',
                            'exports' => 'ImportWord',
                        ],
                    ],
                    'toolBarItems' => 'ImportWord',
                ],
                'fields' => $this->getFieldsFromFeature(ImportWordFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('ExportPdf'),
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'ExportPdf',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-cloud-services',
                            'exports' => 'CloudServices',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-export-pdf',
                            'exports' => 'ExportPdf',
                        ],
                    ],
                    'toolBarItems' => 'ExportPdf',
                ],
                'fields' => $this->getFieldsFromFeature(ExportPdfFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('ExportWord'),
                'configuration' => [
                    'config_key' => 'ExportWord',
                    'default' => false,
                    'is_premium' => true,
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-cloud-services',
                            'exports' => 'CloudServices',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-export-word',
                            'exports' => 'ExportWord',
                        ],
                    ],
                    'toolBarItems' => 'ExportWord',
                ],
                'fields' => $this->getFieldsFromFeature(ExportWordFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('Footnotes'),
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'Footnotes',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-footnotes',
                            'exports' => 'Footnotes, FootnotesProperties',
                        ],
                    ],
                    'toolBarItems' => 'insertFootnote,footnotesStyle',
                ],
                'fields' => $this->getFieldsFromFeature(FootnotesFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('WProofreader'),
                'configuration' => [
                    'default' => false,
                    'config_key' => 'WProofreader',
                    'module' => [
                        [
                            'library' => '@t3planet/RteCkeditorPack/spell-check',
                            'exports' => 'WProofreader',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(WProofreaderFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('Pagination'),
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'Pagination',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-pagination',
                            'exports' => 'Pagination',
                        ],
                    ],
                    'toolBarItems' => 'previousPage,nextPage,pageNavigation',
                ],
                'fields' => $this->getFieldsFromFeature(PaginationFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('Mention'),
                'configuration' => [
                    'default' => false,
                    'config_key' => 'Mention',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-mention',
                            'exports' => 'Mention',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(MentionFeature::class),
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('Notification'),
                'configuration' => [
                    'default' => false,
                    'config_key' => 'Notification',
                ],
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('MathEquations'),
                'configuration' => [
                    'default' => false,
                    'config_key' => 'MathEquations',
                ],
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('MultiLevelList'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'MultiLevelList',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-list-multi-level',
                            'exports' => 'MultiLevelList',
                        ],
                    ],
                    'toolBarItems' => 'multiLevelList',
                ],
            ],
            [
                'tab' => Tabs::STANDALONE,
                'details' => $cardDetails->getDetailsByKey('Bookmark'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'Bookmark',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-bookmark',
                            'exports' => 'Bookmark',
                        ],
                    ],
                    'toolBarItems' => 'bookmark',
                ],
            ],
            [
                'tab' => Tabs::COLLABORATION,
                'details' => $cardDetails->getDetailsByKey('RealTimeCollaboration'),
                'is_toggle' => 0,
                'configuration' => [
                    'default' => false,
                    'config_key' => 'RealTimeCollaboration',
                    'module' => [
                        [
                            'library' => '@t3planet/RteCkeditorPack/realtime-adapter.js',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-cloud-services',
                            'exports' => 'CloudServices',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-real-time-collaboration',
                            'exports' => 'RealTimeCollaborativeEditing,PresenceList',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(CollaborationFeature::class),
            ],
            [
                'tab' => Tabs::COLLABORATION,
                'details' => $cardDetails->getDetailsByKey('Comments'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'config_key' => 'Comments',
                    'is_premium' => true,
                    'module' => [
                        'RealTime' => [
                            [
                                'library' => '@ckeditor/ckeditor5-real-time-collaboration',
                                'exports' => 'RealTimeCollaborativeComments',
                            ],
                        ],
                        'NonRealTime' => [
                            [
                                'library' => '@t3planet/RteCkeditorPack/user-adapter.js',
                            ],
                            [
                                'library' => '@t3planet/RteCkeditorPack/comments-adapter.js',
                                'exports' => 'CommentsAdapter',
                            ],
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-comments',
                            'exports' => 'Comments',
                        ],
                    ],
                    'toolBarItems' => 'comment, commentsArchive',
                ],
            ],
            [
                'tab' => Tabs::COLLABORATION,
                'details' => $cardDetails->getDetailsByKey('RevisionHistory'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'RevisionHistory',
                    'module' => [
                        'RealTime' => [
                            [
                                'library' => '@ckeditor/ckeditor5-real-time-collaboration',
                                'exports' => 'RealTimeCollaborativeRevisionHistory',
                            ],
                        ],
                        'NonRealTime' => [
                            [
                                'library' => '@t3planet/RteCkeditorPack/revision-history-tracker-adapter.js',
                            ],
                            [
                                'library' => '@t3planet/RteCkeditorPack/user-adapter.js',
                            ],
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-revision-history',
                            'exports' => 'RevisionHistory',
                        ],

                    ],
                    'toolBarItems' => 'revisionHistory',
                ],
            ],
            [
                'tab' => Tabs::COLLABORATION,
                'details' => $cardDetails->getDetailsByKey('TrackChanges'),
                'is_toggle' => 1,
                'configuration' => [
                    'config_key' => 'TrackChanges',
                    'default' => false,
                    'is_premium' => true,
                    'module' => [
                        'RealTime' => [
                            [
                                'library' => '@ckeditor/ckeditor5-real-time-collaboration',
                                'exports' => 'RealTimeCollaborativeComments, RealTimeCollaborativeTrackChanges',
                            ],
                        ],
                        'NonRealTime' => [
                            [
                                'library' => '@t3planet/RteCkeditorPack/user-adapter.js',
                            ],
                            [
                                'library' => '@t3planet/RteCkeditorPack/comments-adapter.js',
                                'exports' => 'CommentsAdapter',
                            ],
                            [
                                'library' => '@t3planet/RteCkeditorPack/track-changes-integration.js',
                            ],
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-comments',
                            'exports' => 'Comments',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-track-changes',
                            'exports' => 'TrackChanges',
                        ],
                    ],
                    'toolBarItems' => 'trackChanges',
                ],
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('SlashCommand'),
                'configuration' => [
                    'config_key' => 'SlashCommand',
                    'default' => false,
                    'hidden_premium' => true,
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-mention',
                            'exports' => 'Mention',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-slash-command',
                            'exports' => 'SlashCommand,SlashCommandConfig,SlashCommandEditing,SlashCommandUI',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(SlashCommandFeature::class),
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('Template'),
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'Template',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-template',
                            'exports' => 'Template',
                        ],
                    ],
                    'toolBarItems' => 'insertTemplate',
                ],
                'fields' => $this->getFieldsFromFeature(TemplateFeature::class),
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('CaseChange'),
                'configuration' => [
                    'config_key' => 'CaseChange',
                    'default' => false,
                    'is_premium' => true,
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-case-change',
                            'exports' => 'CaseChange',
                        ],
                    ],
                    'toolBarItems' => 'caseChange',
                ],
                'fields' => $this->getFieldsFromFeature(CaseChangeFeature::class),
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('MergeFields'),
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'MergeFields',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-image',
                            'exports' => 'ImageUtils, ImageEditing',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-mention',
                            'exports' => 'Mention',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-merge-fields',
                            'exports' => 'MergeFields',
                        ],
                    ],
                    'toolBarItems' => 'insertMergeField,previewMergeFields',
                ],
                'fields' => $this->getFieldsFromFeature(MergeFieldsFeature::class),
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('DocumentOutline'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'hidden_premium' => true,
                    'config_key' => 'DocumentOutline',
                    'module' => [
                        [
                            'library' => '@t3planet/RteCkeditorPack/document-outline',
                        ],
                        [
                            'library' => '@ckeditor/ckeditor5-document-outline',
                            'exports' => 'DocumentOutline',
                        ],
                    ],
                ],
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('PasteFromOfficeEnhanced'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'hidden_premium' => true,
                    'config_key' => 'PasteFromOfficeEnhanced',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-paste-from-office-enhanced',
                            'exports' => 'PasteFromOfficeEnhanced',
                        ],
                    ],
                ],
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('FormatPainter'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'FormatPainter',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-format-painter',
                            'exports' => 'FormatPainter',
                        ],
                    ],
                    'toolBarItems' => 'FormatPainter',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Images'),
                'configuration' => [
                    'config_key' => 'Images',
                    'default' => true,
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-image',
                            'exports' => 'Image, ImageUpload, ImageToolbar, ImageCaption, ImageStyle',
                        ],
                        [
                            'library' => '@t3planet/RteCkeditorPack/typo3-image',
                        ],
                    ],
                    'toolBarItems' => 'insertImage',
                ],
                'fields' => $this->getFieldsFromFeature(ImageFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('BalloonToolbar'),
                'configuration' => [
                    'config_key' => 'BalloonToolbar',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-ui',
                            'exports' => 'BalloonToolbar',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(BalloonToolbarFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Indentation'),
                'configuration' => [
                    'default' => true,
                    'config_key' => 'Indentation',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-indent',
                            'exports' => 'Indent,IndentBlock',
                        ],
                    ],
                    'toolBarItems' => 'outdent,indent',
                ],
                'fields' => $this->getFieldsFromFeature(IndentFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('BlockToolbar'),
                'configuration' => [
                    'config_key' => 'BlockToolbar',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-ui',
                            'exports' => 'BlockToolbar',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(BlockToolbarFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Code'),
                'is_toggle' => 1,
                'configuration' => [
                    'config_key' => 'Code',
                    'default' => true,
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-basic-styles',
                            'exports' => 'Code',
                        ],
                    ],
                    'toolBarItems' => 'code',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('CodeBlock'),
                'configuration' => [
                    'default' => true,
                    'config_key' => 'CodeBlock',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-code-block',
                            'exports' => 'CodeBlock',
                        ],
                    ],
                    'toolBarItems' => 'codeBlock',
                ],
                'fields' => $this->getFieldsFromFeature(CodeBlockFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Emoji'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'Emoji',
                    'module' => [
                        [
                            'library' => '@t3planet/RteCkeditorPack/ckeditor5-emoji',
                        ],
                    ],
                    'toolBarItems' => 'emoji',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Font'),
                'configuration' => [
                    'default' => true,
                    'config_key' => 'Font',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-font',
                            'exports' => 'Font',
                        ],
                    ],
                    'toolBarItems' => 'fontFamily,fontSize,fontColor,fontBackgroundColor',
                ],
                'fields' => [
                    '' => $this->getFieldsFromFeature(FontFeature::class),
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('FullScreen'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'FullScreen',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-fullscreen',
                            'exports' => 'Fullscreen',
                        ],
                    ],
                    'toolBarItems' => 'fullscreen',
                ],
                'fields' => $this->getFieldsFromFeature(MenuBarFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Heading'),
                'configuration' => [
                    'default' => true,
                    'config_key' => 'Heading',
                    'toolBarItems' => 'heading',
                ],
                'fields' => $this->getFieldsFromFeature(HeadingFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('HighLight'),
                'configuration' => [
                    'config_key' => 'HighLight',
                    'default' => true,
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-highlight',
                            'exports' => 'Highlight',
                        ],
                    ],
                    'toolBarItems' => 'Highlight',
                ],
                'fields' => $this->getFieldsFromFeature(HighlightFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('HtmlEmbed'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'HtmlEmbed',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-html-embed',
                            'exports' => 'HtmlEmbed',
                        ],
                    ],
                    'toolBarItems' => 'htmlEmbed',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('LineHeight'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'LineHeight',
                    'module' => [
                        [
                            'library' => '@t3planet/RteCkeditorPack/line-height',
                            'exports' => 'LineHeight',
                        ],
                    ],
                    'toolBarItems' => 'lineHeight',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Markdown'),
                'is_toggle' => 1,
                'configuration' => [
                    'config_key' => 'Markdown',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-markdown-gfm',
                            'exports' => 'Markdown',
                        ],
                    ],
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('MediaEmbed'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'MediaEmbed',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-media-embed',
                            'exports' => 'MediaEmbed',
                        ],
                    ],
                    'toolBarItems' => 'mediaEmbed',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Menubar'),
                'is_toggle' => 1,
                'configuration' => [
                    'config_key' => 'Menubar',
                ],
                'fields' => $this->getFieldsFromFeature(MenuBarFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('PageBreak'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'PageBreak',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-page-break',
                            'exports' => 'PageBreak',
                        ],
                    ],
                    'toolBarItems' => 'pageBreak',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('ShowBlocks'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'ShowBlocks',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-show-blocks',
                            'exports' => 'ShowBlocks',
                        ],
                    ],
                    'toolBarItems' => 'showBlocks',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Style'),
                'configuration' => [
                    'default' => true,
                    'config_key' => 'Style',
                    'toolBarItems' => 'style',
                ],
                'fields' => $this->getFieldsFromFeature(StyleFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('Alignment'),
                'configuration' => [
                    'default' => true,
                    'config_key' => 'Alignment',
                    'toolBarItems' => 'alignment',
                ],
                'fields' => $this->getFieldsFromFeature(AlignmentFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('TextStyles'),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('TextTransformation'),
                'configuration' => [
                    'config_key' => 'TextTransformation',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-typing',
                            'exports' => 'TextTransformation',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(TransformationsFeature::class),
            ],
             [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('TextPartLanguage'),
                'configuration' => [
                    'default' => true,
                    'config_key' => 'TextPartLanguage',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-language',
                            'exports' => 'TextPartLanguage',
                        ],
                    ],
                    'toolBarItems' => 'textPartLanguage',
                ],
                'fields' => $this->getFieldsFromFeature(LanguageFeature::class),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('ListProperties'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => true,
                    'config_key' => 'ListProperties',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-list',
                            'exports' => 'ListProperties,List,TodoList',
                        ],
                    ],
                    'toolBarItems' => 'TodoList',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('WordCount'),
                'is_toggle' => 0,
                'configuration' => [
                    'config_key' => 'WordCount',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-word-count',
                            'exports' => 'WordCount',
                        ],
                    ],
                ],
                'fields' => $this->getFieldsFromFeature(WordCountFeature::class),
            ],
            [
                'tab' => Tabs::PRODUCTIVITY,
                'details' => $cardDetails->getDetailsByKey('TableOfContents'),
                'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'is_premium' => true,
                    'config_key' => 'TableOfContents',
                    'module' => [
                        [
                            'library' => '@ckeditor/ckeditor5-document-outline',
                            'exports' => 'TableOfContents',
                        ],
                    ],
                    'toolBarItems' => 'TableOfContents',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('RestrictedEditingMode'),
                // 'is_toggle' => 1,
                'configuration' => [
                    'default' => false,
                    'config_key' => 'RestrictedEditingMode',
                ],
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('FindAndReplace'),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('SpecialCharacters'),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('SelectAll'),
            ],
            [
                'tab' => Tabs::CORE,
                'details' => $cardDetails->getDetailsByKey('SourceEditing'),
            ],
        ];
    }

    public function getSettings(): array
    {
        return $this->ckeditorSettings = SettingsConfiguration::getSettings();
    }

    public function getAllItems(): array
    {
        $enabledConfigurations = array_map(function ($item) {
            return $item['configuration'];
        }, array_filter($this->ckeditorModules, function ($item) {
            return isset($item['configuration']);
        }));

        return array_values($enabledConfigurations);
    }

    public function getGroupedModulesByTabs(): array
    {
        $groupedModules = [];
        // Group modules by tabs
        foreach ($this->ckeditorModules as $module) {
            $tabKey = $module['tab'] ?? Tabs::STANDALONE;
            if (!isset($groupedModules[$tabKey])) {
                $groupedModules[$tabKey] = [
                    'label' => Tabs::getLabel($tabKey),
                    'key' => $tabKey,
                    'cards' => [],
                ];
            }
            $groupedModules[$tabKey]['cards'][] = $module;
        }
        return $groupedModules;
    }

    public function getItemByConfigKey(string $configKey, bool $toolBar = false): array
    {
        foreach ($this->ckeditorModules as $item) {
            if (isset($item['configuration']['config_key']) && strtolower($item['configuration']['config_key']) == strtolower($configKey)) {
                return $item;
            }
            if ($toolBar) {
                if (isset($item['configuration']['toolBarItems']) && str_contains($item['configuration']['toolBarItems'], $configKey)) {
                    return $item;
                }
            }
        }
        return [];
    }

    /**
     * Get fields from feature class if it exists
     *
     * @param string $featureClass
     * @return array
     */
    private function getFieldsFromFeature(string $featureClass): array
    {
        if (!class_exists($featureClass)) {
            return [];
        }

        try {
            /** @var FeatureInterface $feature */
            $feature = GeneralUtility::makeInstance($featureClass);
            return $feature->getConfiguration();
        } catch (\Throwable $e) {
            return [];
        }
    }

}
