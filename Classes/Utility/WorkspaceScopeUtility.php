<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Shared Live vs draft workspace identity for Pack collaboration storage.
 */
final class WorkspaceScopeUtility
{
    public static function currentWorkspaceId(): int
    {
        try {
            $fromContext = (int)GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('workspace', 'id', 0);
            if ($fromContext > 0) {
                return $fromContext;
            }
        } catch (AspectNotFoundException) {
            // Fall through — Visual Editor FE may lack a workspace aspect.
        }

        // Backend user workspace is authoritative in FormEngine and Visual Editor.
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (is_object($backendUser) && isset($backendUser->workspace)) {
            $fromUser = (int)$backendUser->workspace;
            if ($fromUser > 0) {
                return $fromUser;
            }
        }

        return 0;
    }

    /**
     * Channel / config segment. Live stays "live" so existing Cloud documents remain stable.
     *
     * @return int|string
     */
    public static function collaborationSegment(?int $workspaceId = null): int|string
    {
        $workspaceId ??= self::currentWorkspaceId();

        return $workspaceId > 0 ? $workspaceId : 'live';
    }

    /**
     * Draft workspaces get a ":ws:{id}" suffix. Live keeps the canonical field name
     * so existing comment rows continue to match.
     */
    public static function scopeRteId(string $rteId, ?int $workspaceId = null): string
    {
        $workspaceId ??= self::currentWorkspaceId();
        if ($rteId === '' || $workspaceId <= 0 || str_contains($rteId, ':ws:')) {
            return $rteId;
        }

        return $rteId . ':ws:' . $workspaceId;
    }

    /**
     * Strip the draft suffix so a published comment matches the Live field key.
     */
    public static function unscopeRteId(string $rteId): string
    {
        return (string)preg_replace('/:ws:\d+$/', '', $rteId);
    }

    /**
     * Live record uid for workspace versions (t3ver_oid), otherwise the row uid.
     * Keeps comment/RTC keys stable across Live and draft versions of the same record.
     *
     * @param array<string, mixed> $data
     */
    public static function liveRecordUid(array $data): int
    {
        $row = $data['databaseRow'] ?? null;
        if (!is_array($row)) {
            $row = $data;
        }

        $oid = (int)($row['t3ver_oid'] ?? 0);
        if ($oid > 0) {
            return $oid;
        }

        foreach ([$data['recordUid'] ?? null, $row['uid'] ?? null, $data['uid'] ?? null] as $candidate) {
            $uid = (int)$candidate;
            if ($uid > 0) {
                return $uid;
            }
        }

        return 0;
    }
}
