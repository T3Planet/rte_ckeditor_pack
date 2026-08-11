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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Removes CKEditor comment/suggestion markers from page-module previews.
 *
 * Markers must stay in the database for the editor. Core's preview HTML sanitizer
 * encodes unknown tags (e.g. comment-start) as visible text. Custom preview
 * renderers (including third-party ones) may skip our RteImagePreviewRenderer,
 * so strip after every preview is rendered.
 */
class StripCollaborationMarkupFromPreviewListener
{
    public function __invoke(object $event): void
    {
        if (!method_exists($event, 'getTable') || $event->getTable() !== 'tt_content') {
            return;
        }

        if (!method_exists($event, 'getPreviewContent') || !method_exists($event, 'setPreviewContent')) {
            return;
        }

        $previewContent = (string)$event->getPreviewContent();
        $bodytext = $this->resolveBodytext($event);

        $mathRenderer = GeneralUtility::makeInstance(MathMlFrontendRenderer::class);
        $previewHasFormulas = str_contains($previewContent, 'Wirisformula')
            || str_contains($previewContent, 'rte-ckeditor-pack-formula');
        $previewIsBroken = str_contains($previewContent, 'wiriseditorsavemode')
            || $mathRenderer->containsMathMarkup($previewContent);
        $needsMathFix = $bodytext !== ''
            && $mathRenderer->containsMathMarkup($bodytext)
            && ($previewIsBroken || !$previewHasFormulas);

        if ($needsMathFix) {
            $previewContent = $this->replacePreviewContentWithMathSafeHtml(
                $previewContent,
                $bodytext
            );
        }

        if ($previewContent !== '' && $this->containsCollaborationMarkup($previewContent)) {
            $previewContent = RteMarkupTransformationUtility::stripCollaborationMarkup($previewContent);
        }

        $event->setPreviewContent($previewContent);
    }

    private function resolveBodytext(object $event): string
    {
        if (!method_exists($event, 'getRecord')) {
            return '';
        }

        $record = $event->getRecord();
        if (is_array($record)) {
            return is_string($record['bodytext'] ?? null) ? $record['bodytext'] : '';
        }

        if (is_object($record) && method_exists($record, 'has') && method_exists($record, 'get')) {
            if ($record->has('bodytext')) {
                $value = $record->get('bodytext');
                return is_string($value) ? $value : '';
            }
        }

        return '';
    }

    private function replacePreviewContentWithMathSafeHtml(string $previewContent, string $bodytext): string
    {
        $renderer = GeneralUtility::makeInstance(RteImagePreviewRenderer::class);
        $fixedBody = $renderer->buildPreviewHtmlFromBodytext($bodytext);

        if ($fixedBody === '') {
            return $previewContent;
        }

        if (preg_match('#(<div class="element-preview-content">)(.*?)(</div>)#s', $previewContent)) {
            return (string)preg_replace(
                '#(<div class="element-preview-content">)(.*?)(</div>)#s',
                '$1' . $fixedBody . '$3',
                $previewContent,
                1
            );
        }

        if (preg_match('#(<div class="t3-page-ce-body-inner">)(.*?)(</div>)#s', $previewContent)) {
            return (string)preg_replace(
                '#(<div class="t3-page-ce-body-inner">)(.*?)(</div>)#s',
                '$1' . $fixedBody . '$3',
                $previewContent,
                1
            );
        }

        if (
            str_contains($previewContent, 'wiriseditorsavemode')
            || GeneralUtility::makeInstance(MathMlFrontendRenderer::class)->containsMathMarkup($previewContent)
        ) {
            return $fixedBody;
        }

        return $previewContent;
    }

    private function containsCollaborationMarkup(string $content): bool
    {
        return str_contains($content, 'comment-start')
            || str_contains($content, 'comment-end')
            || str_contains($content, 'suggestion-start')
            || str_contains($content, 'suggestion-end');
    }
}
