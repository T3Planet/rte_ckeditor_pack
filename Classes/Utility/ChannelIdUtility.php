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
 * Class ChannelIdUtility
 *
 * Utility class for building channel IDs for CKEditor collaboration features.
 */
class ChannelIdUtility
{
    private static int $instanceCounter = 0;

    /**
     * Build a channel ID from data array
     *
     * Creates a unique channel ID based on record information, field name,
     * language, workspace, site identifier, and instance identifier.
     *
     * @param array $data Data array containing record information
     * @return string Channel ID in format 'ckdoc-{hash}'
     */
    public static function buildChannelIdFromData(array $data): string
    {
        $recordIdentifier = $data['databaseRow']['uid']
            ?? $data['recordUid']
            ?? $data['inlineParentUid']
            ?? $data['effectivePid']
            ?? '0';

        // Get site identifier for domain-wise unique collaboration
        $siteIdentifier = self::getSiteIdentifier($data);

        $parts = [
            $data['tableName'] ?? 'table',
            $recordIdentifier,
            $data['fieldName'] ?? 'field',
            $data['languageId'] ?? $data['sys_language_uid'] ?? '0',
            $data['workspaceId'] ?? $data['workspace'] ?? 'live',
            $siteIdentifier,
        ];

        $instanceIdentifier = $data['domElementId']
            ?? $data['formElementName']
            ?? $data['elementIdentifier']
            ?? (++self::$instanceCounter);

        $payload = implode('|', array_map(static function ($value) {
            $value = (string)($value ?? '');
            return trim($value) !== '' ? trim($value) : '0';
        }, $parts));
        $payload .= '|' . (string)$instanceIdentifier;

        $hash = substr(hash('sha1', $payload), 0, 40);
        return 'ckdoc-' . $hash;
    }

    /**
     * Get site identifier from data array
     *
     * Attempts to resolve the TYPO3 site identifier based on the page ID from the data array.
     * Falls back to 'default' if no site can be found.
     *
     * @param array $data Data array containing record information
     * @return string Site identifier or 'default' as fallback
     */
    private static function getSiteIdentifier(array $data): string
    {
        // Try to get page ID from various sources
        $pageId = $data['effectivePid'] ?? $data['databaseRow']['pid'] ?? $data['pid'] ?? 0;
        
        // Also check if site is already provided in data
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

        // Fallback to default if no site can be determined
        return 'default';
    }
}
