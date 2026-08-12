<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Backend\Preview;

use T3Planet\RteCkeditorPack\Utility\MathMlFrontendRenderer;
use T3Planet\RteCkeditorPack\Utility\RteMarkupTransformationUtility;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RteImagePreviewRenderer extends StandardContentPreviewRenderer
{
    private bool $reachedLimit = false;
    private int $totalLength = 0;

    /** @var \DOMNode[] */
    private array $toRemove = [];

    /**
     * Dedicated method for rendering preview body HTML for the page module only.
     * Receives the GridColumnItem that contains the record for which a preview should be
     * rendered and returned.
     *
     * @param GridColumnItem $item
     *
     * @return string
     */
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $html = $this->resolveBodytextFromItem($item);

        // Sanitize HTML (replaces invalid chars with U+FFFD).
        // - Invalid control chars: [\x00-\x08\x0B\x0C\x0E-\x1F]
        // - UTF-16 surrogates: \xED[\xA0-\xBF].
        // - Non-characters U+FFFE and U+FFFF: \xEF\xBF[\xBE\xBF]
        $html = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF].|\xEF\xBF[\xBE\xBF]/',
            "\xEF\xBF\xBD",
            $html
        ) ?? $html;

        $rendered = $this->renderTextWithHtml($html);

        return $this->linkPreviewContent($rendered, $item) . '<br />';
    }

    public function buildPreviewHtmlFromBodytext(string $input): string
    {
        return $this->renderTextWithHtml($input);
    }

    /**
     * Processing of larger amounts of text (usually from RTE/bodytext fields) with word wrapping etc.
     *
     * @param string $input Input string
     * @return string Output string
     */
    protected function renderTextWithHtml(string $input): string
    {
        $input = RteMarkupTransformationUtility::stripCollaborationMarkup($input);
        $input = GeneralUtility::makeInstance(MathMlFrontendRenderer::class)
            ->prepareForBackendPreview($input);

        // Allow only safe tags in preview, to prevent possible HTML mismatch
        $input = strip_tags($input, '<img><p><br><span>');

        return $this->truncate($input, 1500);
    }

    private function linkPreviewContent(string $rendered, GridColumnItem $item): string
    {
        if ($this->getTypo3MajorVersion() >= 14) {
            return $this->linkEditContent($rendered, $item->getRecord());
        }

        $row = $this->resolveRowArray($item);

        return $this->linkEditContent($rendered, $row, 'tt_content');
    }

    private function resolveBodytextFromItem(GridColumnItem $item): string
    {
        if ($this->getTypo3MajorVersion() >= 14) {
            $record = $item->getRecord();
            if (is_object($record) && method_exists($record, 'has') && method_exists($record, 'get')) {
                if ($record->has('bodytext')) {
                    $value = $record->get('bodytext');
                    return is_string($value) ? $value : '';
                }
            }

            return (string)($this->resolveRowArray($item)['bodytext'] ?? '');
        }

        return (string)($this->resolveRowArray($item)['bodytext'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRowArray(GridColumnItem $item): array
    {
        if (method_exists($item, 'getRow')) {
            $row = $item->getRow();
            if (is_array($row) && $row !== []) {
                return $row;
            }
        }

        $record = $item->getRecord();
        if (is_array($record)) {
            return $record;
        }

        if (is_object($record) && method_exists($record, 'toArray')) {
            $row = $record->toArray();
            return is_array($row) ? $row : [];
        }

        return [];
    }

    /**
     * Truncates the given text, but preserves HTML tags.
     *
     * @param string $html
     * @param int    $length
     *
     * @return string
     *
     * @see https://stackoverflow.com/questions/16583676/shorten-text-without-splitting-words-or-breaking-html-tags
     */
    private function truncate(string $html, int $length): string
    {
        $this->reachedLimit = false;
        $this->totalLength = 0;
        $this->toRemove = [];

        // Set error level
        $internalErrors = libxml_use_internal_errors(true);

        // Wrap in a container: LIBXML_HTML_NOIMPLIED with multiple roots is unreliable
        // and "<?xml encoding>" prefixes break browser parsing of page-module previews.
        $dom = new \DOMDocument();
        $dom->loadHTML(
            '<div id="rte-ckeditor-pack-preview-wrap">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD
        );

        // Restore error level
        libxml_use_internal_errors($internalErrors);

        $wrap = $dom->getElementById('rte-ckeditor-pack-preview-wrap');
        if ($wrap === null) {
            return $html;
        }

        $toRemove = $this->walk($wrap, $length);

        // Remove any nodes that exceed limit
        foreach ($toRemove as $child) {
            if ($child->parentNode !== null) {
                $child->parentNode->removeChild($child);
            }
        }

        $result = '';
        foreach ($wrap->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    /**
     * @return \DOMNode[]
     */
    private function walk(\DOMNode $node, int $maxLength): array
    {
        if ($this->reachedLimit) {
            $this->toRemove[] = $node;
        } else {
            // Only text nodes should have a text, so do the splitting here
            if (($node instanceof \DOMText) && ($node->nodeValue !== null)) {
                $this->totalLength += $nodeLen = mb_strlen($node->nodeValue);

                if ($this->totalLength > $maxLength) {
                    $node->nodeValue = mb_substr(
                        $node->nodeValue,
                        0,
                        $nodeLen - ($this->totalLength - $maxLength)
                    ) . '...';

                    $this->reachedLimit = true;
                }
            }

            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    $this->walk($child, $maxLength);
                }
            }
        }

        return $this->toRemove;
    }

    /**
     * Get TYPO3 major version
     *
     * @return int
     */
    private function getTypo3MajorVersion(): int
    {
        return GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();
    }
}
