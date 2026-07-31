<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Utility\RteMarkupTransformationUtility;
use TYPO3\CMS\Core\Html\Event\AfterTransformTextForPersistenceEvent;
use TYPO3\CMS\Core\Html\Event\AfterTransformTextForRichTextEditorEvent;
use TYPO3\CMS\Core\Html\Event\BeforeTransformTextForPersistenceEvent;
use TYPO3\CMS\Core\Html\Event\BeforeTransformTextForRichTextEditorEvent;

/**
 * Keep collaboration markers as real HTML tags for Comments / Track Changes.
 *
 * TYPO3's RTE htmlSanitize step encodes unknown tags as text entities, which makes
 * markers show as visible "&lt;comment-start&gt;…" in the editor. Restore them so:
 * - the database stores <comment-start>…</comment-end>
 * - the editor receives real markers that Comments can turn into highlights
 */
final readonly class RestoreCollaborationMarkersListener
{
    public function restoreForEditor(BeforeTransformTextForRichTextEditorEvent $event): void
    {
        $event->setHtmlContent(
            RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($event->getHtmlContent())
        );
    }

    public function restoreForEditorAfter(AfterTransformTextForRichTextEditorEvent $event): void
    {
        $event->setHtmlContent(
            RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($event->getHtmlContent())
        );
    }

    public function restoreForPersistence(BeforeTransformTextForPersistenceEvent $event): void
    {
        $event->setHtmlContent(
            RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($event->getHtmlContent())
        );
    }

    public function restoreForPersistenceAfter(AfterTransformTextForPersistenceEvent $event): void
    {
        // Critical: htmlSanitize runs before this event and re-encodes unknown tags.
        $event->setHtmlContent(
            RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($event->getHtmlContent())
        );
    }
}
