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
 * On TYPO3 12/13 the preview event carries an array record that can be mutated, and
 * listeners may provide the full preview via setPreviewContent() (skips default renderer).
 * On TYPO3 14+ the record is a RecordInterface; stripping is handled after render by
 * StripCollaborationMarkupFromPreviewListener (AfterPageContentPreviewRenderedEvent).
 *
 * Math/Chem: do NOT rewrite bodytext to preview HTML (that double-processes and used to
 * inject a broken <?xml...> prefix). Prefer setPreviewContent() with the safe formula markup.
 */
class StripCollaborationMarkupBeforePreviewListener
{
    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() !== 'tt_content') {
            return;
        }

        // Another listener already provided the preview HTML.
        if ($event->getPreviewContent() !== null) {
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
        $hasMath = $mathRenderer->containsMathMarkup($bodytext);
        $hasCollab = $this->containsCollaborationMarkup($bodytext);

        if (!$hasMath && !$hasCollab) {
            return;
        }

        // Math/Chem (and collab+math): render a complete, safe preview and hand it to the page module.
        // Propagation stops once preview content is set, so StandardContentPreviewRenderer
        // cannot strip the formula <img> tags via renderText()/strip_tags().
        if ($hasMath) {
            $previewHtml = GeneralUtility::makeInstance(RteImagePreviewRenderer::class)
                ->buildPreviewHtmlFromBodytext($bodytext);
            if ($previewHtml !== '') {
                $event->setPreviewContent($previewHtml);
            }
            return;
        }

        // Collaboration markers only: strip from the record so the default renderer stays clean.
        $record['bodytext'] = RteMarkupTransformationUtility::stripCollaborationMarkup($bodytext);
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
