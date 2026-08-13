<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Domain\Repository\CommentsRepository;
use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use T3Planet\RteCkeditorPack\Utility\RtcDocumentRevisionUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * When a content record is published, Non-RTC comments from that workspace become Live.
 * Real-time comments live in the CKEditor Cloud document for that workspace room;
 * published HTML (including comment markers) is already copied by core Workspaces.
 * Live RTC document revision is bumped so the next Live open seeds a fresh Cloud room.
 */
class PromoteWorkspaceCommentsOnPublishListener
{
    public function __construct(
        private readonly CommentsRepository $commentsRepository,
    ) {}

    public function __invoke(object $event): void
    {
        if (
            !method_exists($event, 'getTable')
            || !method_exists($event, 'getRecordId')
            || !method_exists($event, 'getWorkspaceId')
        ) {
            return;
        }

        $table = (string)$event->getTable();
        $liveUid = (int)$event->getRecordId();
        $workspaceId = (int)$event->getWorkspaceId();
        if ($table === '' || $liveUid <= 0 || $workspaceId <= 0) {
            return;
        }
        if (in_array($table, PackRecordPersister::TABLES, true)) {
            return;
        }

        $this->commentsRepository->promoteWorkspaceCommentsToLive($workspaceId, $table, $liveUid);

        // Also promote by thread ids found in published HTML (covers missed workspace mismatches).
        $threadIds = $this->extractCommentThreadIdsFromRecord($table, $liveUid);
        if ($threadIds !== []) {
            $this->commentsRepository->promoteThreadsToLive($threadIds, $workspaceId);
        }

        // New Live HTML must not reconnect to the pre-publish Live Cloud document.
        RtcDocumentRevisionUtility::bumpRevision($table, $liveUid);
    }

    /**
     * @return list<string>
     */
    private function extractCommentThreadIdsFromRecord(string $table, int $uid): array
    {
        try {
            $row = BackendUtility::getRecord($table, $uid);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($row)) {
            return [];
        }

        return self::extractCommentThreadIdsFromFields($row);
    }

    /**
     * @param array<string, mixed> $fields
     * @return list<string>
     */
    public static function extractCommentThreadIdsFromFields(array $fields): array
    {
        $threadIds = [];
        foreach ($fields as $value) {
            if (!is_string($value) || $value === '' || !str_contains($value, 'comment-start')) {
                continue;
            }
            if (preg_match_all('/comment-start[^>]*name=["\']([^"\':]+)/i', $value, $matches)) {
                foreach ($matches[1] as $threadId) {
                    $threadId = trim((string)$threadId);
                    if ($threadId !== '') {
                        $threadIds[] = $threadId;
                    }
                }
            }
        }

        return array_values(array_unique($threadIds));
    }
}
