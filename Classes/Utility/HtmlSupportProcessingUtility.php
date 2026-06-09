<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Syncs CKEditor General HTML Support rules into TYPO3 RTE processing configuration.
 */
final class HtmlSupportProcessingUtility
{
    private const BLOCK_LEVEL_TAGS = [
        'address',
        'article',
        'aside',
        'audio',
        'blockquote',
        'details',
        'div',
        'embed',
        'fieldset',
        'figure',
        'figcaption',
        'footer',
        'form',
        'header',
        'hr',
        'iframe',
        'main',
        'nav',
        'object',
        'oembed',
        'ol',
        'pre',
        'section',
        'summary',
        'table',
        'ul',
        'video',
    ];

    /**
     * Apply all HTML-support-related processing rules in one pass.
     */
    public static function syncProcessing(array $configuration): array
    {
        $configuration = self::applyHtmlSupportAllowRules($configuration);
        $configuration = self::applyEmbedTagRules($configuration);

        return $configuration;
    }

    /**
     * Merge htmlSupport.allow definitions into processing.allowTags / allowAttributes.
     */
    private static function applyHtmlSupportAllowRules(array $configuration): array
    {
        $allowRules = self::collectHtmlSupportAllowRules($configuration);
        if ($allowRules === []) {
            return $configuration;
        }

        if (!isset($configuration['processing']) || !is_array($configuration['processing'])) {
            $configuration['processing'] = [];
        }

        $allowTags = self::normalizeStringList($configuration['processing']['allowTags'] ?? []);
        $allowTagsOutside = self::normalizeStringList($configuration['processing']['allowTagsOutside'] ?? []);
        $allowAttributes = self::normalizeStringList($configuration['processing']['allowAttributes'] ?? []);

        foreach ($allowRules as $rule) {
            $tagName = trim((string)($rule['name'] ?? ''));
            if ($tagName === '') {
                continue;
            }

            $allowTags[] = $tagName;
            if (self::isBlockLevelTag($tagName)) {
                $allowTagsOutside[] = $tagName;
            }

            $allowAttributes = array_merge(
                $allowAttributes,
                self::resolveAttributesForRule($tagName, $rule['attributes'] ?? null)
            );
        }

        $configuration['processing']['allowTags'] = array_values(array_unique($allowTags));
        $configuration['processing']['allowTagsOutside'] = array_values(array_unique($allowTagsOutside));
        $configuration['processing']['allowAttributes'] = array_values(array_unique($allowAttributes));

        return $configuration;
    }

    /**
     * Ensure embed-related tags are allowed in TYPO3 processing.
     */
    private static function applyEmbedTagRules(array $configuration): array
    {
        if (!isset($configuration['processing']) || !is_array($configuration['processing'])) {
            $configuration['processing'] = [];
        }

        $allowTags = self::normalizeStringList($configuration['processing']['allowTags'] ?? []);
        $allowTagsOutside = self::normalizeStringList($configuration['processing']['allowTagsOutside'] ?? []);
        $allowAttributes = self::normalizeStringList($configuration['processing']['allowAttributes'] ?? []);

        $allowTags[] = 'oembed';
        $allowTagsOutside[] = 'oembed';
        $allowAttributes[] = 'url';

        $allowTags[] = 'iframe';
        $allowTagsOutside[] = 'iframe';
        $allowAttributes = array_merge(
            $allowAttributes,
            self::resolveAttributesForRule('iframe', true)
        );

        $configuration['processing']['allowTags'] = array_values(array_unique($allowTags));
        $configuration['processing']['allowTagsOutside'] = array_values(array_unique($allowTagsOutside));
        $configuration['processing']['allowAttributes'] = array_values(array_unique($allowAttributes));

        return $configuration;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function collectHtmlSupportAllowRules(array $configuration): array
    {
        $rules = [];

        foreach (self::collectHtmlSupportConfigBlocks($configuration) as $htmlSupport) {
            if (!isset($htmlSupport['allow']) || !is_array($htmlSupport['allow'])) {
                continue;
            }

            foreach ($htmlSupport['allow'] as $rule) {
                if (is_array($rule)) {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function collectHtmlSupportConfigBlocks(array $configuration): array
    {
        if (isset($configuration['htmlSupport']) && is_array($configuration['htmlSupport'])) {
            return [$configuration['htmlSupport']];
        }

        if (
            isset($configuration['editor']['config']['htmlSupport'])
            && is_array($configuration['editor']['config']['htmlSupport'])
        ) {
            return [$configuration['editor']['config']['htmlSupport']];
        }

        return [];
    }

    /**
     * @param array<int, string>|string $values
     * @return list<string>
     */
    private static function normalizeStringList(array|string $values): array
    {
        if (is_string($values)) {
            $values = GeneralUtility::trimExplode(',', $values, true);
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $values
        )));
    }

    private static function isBlockLevelTag(string $tagName): bool
    {
        return in_array(strtolower($tagName), self::BLOCK_LEVEL_TAGS, true);
    }

    /**
     * @return list<string>
     */
    private static function resolveAttributesForRule(string $tagName, mixed $attributes): array
    {
        if ($attributes === true) {
            return match (strtolower($tagName)) {
                'iframe' => [
                    'src',
                    'title',
                    'width',
                    'height',
                    'frameborder',
                    'allow',
                    'allowfullscreen',
                    'loading',
                    'sandbox',
                    'name',
                    'id',
                    'class',
                    'style',
                ],
                'video' => [
                    'src',
                    'width',
                    'height',
                    'controls',
                    'autoplay',
                    'loop',
                    'muted',
                    'poster',
                    'preload',
                    'class',
                    'id',
                    'style',
                ],
                'audio' => [
                    'src',
                    'controls',
                    'autoplay',
                    'loop',
                    'muted',
                    'preload',
                    'class',
                    'id',
                    'style',
                ],
                'oembed' => ['url', 'class', 'id', 'style'],
                default => ['class', 'id', 'title', 'style'],
            };
        }

        if (!is_array($attributes)) {
            return [];
        }

        $resolved = [];
        foreach ($attributes as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $resolved[] = trim($value);
                continue;
            }

            if (is_string($key)) {
                $resolved[] = trim($key);
            }
        }

        return $resolved;
    }
}
