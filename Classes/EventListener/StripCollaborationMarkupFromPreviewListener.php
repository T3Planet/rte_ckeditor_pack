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
use TYPO3\CMS\Backend\View\Event\AfterPageContentPreviewRenderedEvent;

/**
 * Removes CKEditor comment/suggestion markers from page-module previews.
 *
 * Markers must stay in the database for the editor. Core's preview HTML sanitizer
 * encodes unknown tags (e.g. comment-start) as visible text. Custom preview
 * renderers (including third-party ones) may skip our RteImagePreviewRenderer,
 * so strip after every preview is rendered.
 */
final readonly class StripCollaborationMarkupFromPreviewListener
{
    public function __invoke(AfterPageContentPreviewRenderedEvent $event): void
    {
        if ($event->getTable() !== 'tt_content') {
            return;
        }

        $previewContent = $event->getPreviewContent();
        if ($previewContent === '' || !$this->containsCollaborationMarkup($previewContent)) {
            return;
        }

        $event->setPreviewContent(
            RteMarkupTransformationUtility::stripCollaborationMarkup($previewContent)
        );
    }

    private function containsCollaborationMarkup(string $content): bool
    {
        return str_contains($content, 'comment-start')
            || str_contains($content, 'comment-end')
            || str_contains($content, 'suggestion-start')
            || str_contains($content, 'suggestion-end');
    }
}
