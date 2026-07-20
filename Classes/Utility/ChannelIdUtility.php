<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;

/**
 * Utility class for building channel IDs for CKEditor collaboration features.
 */
class ChannelIdUtility
{
    /**
     * Build a channel ID from data array
     *
     * Creates a stable channel ID based on record information, field name,
     * language, workspace, and site identifier. DOM-specific identifiers are
     * intentionally excluded so Visual Editor and FormEngine share one channel.
     *
     * @param array<string, mixed> $data Data array containing record information
     * @return string Channel ID in format 'ckdoc-{hash}'
     */
    public static function buildChannelIdFromData(array $data): string
    {
        $siteIdentifier = self::getSiteIdentifier($data);

        $parts = [
            $data['tableName'] ?? $data['table'] ?? 'table',
            self::resolveRecordIdentifier($data),
            $data['fieldName'] ?? $data['field'] ?? 'field',
            self::resolveLanguageId($data),
            $data['workspaceId'] ?? $data['workspace'] ?? 'live',
            $siteIdentifier,
        ];

        $payload = implode('|', array_map(static function ($value) {
            $value = (string)($value ?? '');
            return trim($value) !== '' ? trim($value) : '0';
        }, $parts));

        $hash = substr(hash('sha1', $payload), 0, 40);
        return 'ckdoc-' . $hash;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function resolveRecordIdentifier(array $data): string
    {
        $candidates = [
            $data['recordUid'] ?? null,
            $data['databaseRow']['uid'] ?? null,
            $data['uid'] ?? null,
            $data['inlineParentUid'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '' || (string)$candidate === '0') {
                continue;
            }

            return (string)$candidate;
        }

        return (string)($data['effectivePid'] ?? $data['pid'] ?? $data['databaseRow']['pid'] ?? '0');
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function resolveLanguageId(array $data): string
    {
        $languageId = $data['languageId']
            ?? $data['sys_language_uid']
            ?? $data['databaseRow']['sys_language_uid']
            ?? '0';

        if (is_array($languageId)) {
            $languageId = $languageId[0] ?? '0';
        }

        return (string)(int)$languageId;
    }

    /**
     * Get site identifier from data array
     *
     * @param array<string, mixed> $data
     */
    private static function getSiteIdentifier(array $data): string
    {
        $pageId = $data['effectivePid'] ?? $data['databaseRow']['pid'] ?? $data['pid'] ?? 0;

        if (isset($data['site']) && is_object($data['site']) && method_exists($data['site'], 'getIdentifier')) {
            try {
                return $data['site']->getIdentifier();
            } catch (\Exception $e) {
                // Continue to try SiteFinder
            }
        }

        if ($pageId > 0) {
            try {
                $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
                $site = $siteFinder->getSiteByPageId((int)$pageId);
                return $site->getIdentifier();
            } catch (SiteNotFoundException $e) {
                // Site not found, fall back to default
            }
        }

        return 'default';
    }
}
