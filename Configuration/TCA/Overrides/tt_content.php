<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

defined('TYPO3') or die();

/**
 * TCA override for tt_content table.
 */
call_user_func(
    static function (): void {
        /** @var string[] $cleanSoftReferences */
        $cleanSoftReferences = explode(
            ',',
            (string)$GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref']
        );

        // Remove obsolete soft reference key 'images', the references from RTE content to the original
        // images are handled with the key 'rtehtmlarea_images'
        $cleanSoftReferences   = array_diff($cleanSoftReferences, ['images']);
        $cleanSoftReferences[] = 'rtehtmlarea_images';

        // Set up soft reference index parsing for RTE images
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref'] = implode(
            ',',
            $cleanSoftReferences
        );

        // Register preview renderer
        $GLOBALS['TCA']['tt_content']['types']['text']['previewRenderer']
            = \T3Planet\RteCkeditorPack\Backend\Preview\RteImagePreviewRenderer::class;
    }
);

// Configure Default Permissions
// $GLOBALS['TCA']['be_groups']['columns']['custom_options']['config']['default'] =
// 'rte_editor:ImportWord,rte_editor:ExportWord,rte_editor:ExportPdf,rte_editor:Comments,rte_editor:CaseChange,rte_editor:Comments,rte_editor:FormatPainter,
// rte_editor:Template,rte_editor:MultiLevelList,rte_editor:TrackChanges,rte_editor:TableOfContents,rte_editor:RevisionHistory,rte_editor:AIAssistant';
