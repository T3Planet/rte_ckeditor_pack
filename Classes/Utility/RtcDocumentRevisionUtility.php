<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Stable RTC Cloud document generation per Live record.
 *
 * Bumped only on workspace publish so normal saves keep the same Cloud room
 * (comments / suggestions stay), while published HTML gets a fresh Live room.
 */
final class RtcDocumentRevisionUtility
{
    private const REGISTRY_NAMESPACE = 'tx_rteckeditorpack';

    public static function currentRevision(string $table, int $liveUid): int
    {
        if ($table === '' || $liveUid <= 0) {
            return 0;
        }

        try {
            $registry = GeneralUtility::makeInstance(Registry::class);
            if (!$registry instanceof Registry) {
                return 0;
            }

            return (int)$registry->get(self::REGISTRY_NAMESPACE, self::registryKey($table, $liveUid), 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function bumpRevision(string $table, int $liveUid): int
    {
        if ($table === '' || $liveUid <= 0) {
            return 0;
        }

        try {
            $registry = GeneralUtility::makeInstance(Registry::class);
            if (!$registry instanceof Registry) {
                return 0;
            }

            $next = self::currentRevision($table, $liveUid) + 1;
            $registry->set(self::REGISTRY_NAMESPACE, self::registryKey($table, $liveUid), $next);

            return $next;
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function registryKey(string $table, int $liveUid): string
    {
        return 'rtc_doc_rev_' . $table . '_' . $liveUid;
    }
}
