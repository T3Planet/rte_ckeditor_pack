<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Backend\Preview\RteImagePreviewRenderer;
use T3Planet\RteCkeditorPack\Utility\MathMlFrontendRenderer;
use T3Planet\RteCkeditorPack\Utility\RteMarkupTransformationUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Strips CKEditor comment/suggestion markers from bodytext before page-module preview.
 *
 * On TYPO3 12/13 the preview event carries an array record that can be mutated.
 * On TYPO3 14+ the record is a RecordInterface; stripping is handled after render by
 * StripCollaborationMarkupFromPreviewListener (AfterPageContentPreviewRenderedEvent).
 */
class StripCollaborationMarkupBeforePreviewListener
{
    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() !== 'tt_content') {
            return;
        }

        $record = $event->getRecord();
        if (!is_array($record)) {
            return;
        }

        $bodytext = $record['bodytext'] ?? null;
        if (!is_string($bodytext) || $bodytext === '') {
            return;
        }

        $mathRenderer = GeneralUtility::makeInstance(MathMlFrontendRenderer::class);
        $changed = false;

        if ($this->containsCollaborationMarkup($bodytext)) {
            $bodytext = RteMarkupTransformationUtility::stripCollaborationMarkup($bodytext);
            $changed = true;
        }

        if ($mathRenderer->containsMathMarkup($bodytext)) {
            $bodytext = GeneralUtility::makeInstance(RteImagePreviewRenderer::class)
                ->buildPreviewHtmlFromBodytext($bodytext);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $record['bodytext'] = $bodytext;
        $event->setRecord($record);
    }

    private function containsCollaborationMarkup(string $content): bool
    {
        return str_contains($content, 'comment-start')
            || str_contains($content, 'comment-end')
            || str_contains($content, 'suggestion-start')
            || str_contains($content, 'suggestion-end');
    }
}
