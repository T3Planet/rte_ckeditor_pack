<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Repository;

use T3Planet\RteCkeditorPack\Utility\WorkspaceScopeUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class CommentsRepository
{
    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_comment';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function checkExisting($id, ?int $workspaceId = null)
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        $rows = $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['id'],
                self::TABLE_NAME,
                $this->withWorkspace(['id' => $id], $workspaceId)
            )
            ->fetchAllAssociative();

        // Draft may still be viewing the Live row until copy-on-write.
        if ($rows === [] && $workspaceId > 0) {
            $rows = $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
                ->select(
                    ['id'],
                    self::TABLE_NAME,
                    $this->withWorkspace(['id' => $id], 0)
                )
                ->fetchAllAssociative();
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveComment($data): void
    {
        if (!isset($data['workspace_id'])) {
            $data['workspace_id'] = WorkspaceScopeUtility::currentWorkspaceId();
        }
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
            ->insert(
                self::TABLE_NAME,
                $data,
            );
    }

    /**
     * Load a thread for the active workspace.
     *
     * Draft workspaces overlay Live: if this workspace has no rows for the
     * thread yet, Live comments are returned (same idea as unchanged content).
     * Once the draft owns any row for that thread, only draft rows are used.
     */
    public function fetchCommentsByThreatId(string $id, ?int $workspaceId = null)
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        $rows = $this->fetchThreadRows($id, $workspaceId);

        // Live editor with published HTML markers but draft rows that never promoted.
        if ($rows === [] && $workspaceId === 0 && $id !== '') {
            $this->promoteThreadsToLive([$id]);
            $rows = $this->fetchThreadRows($id, 0);
        }

        // Unchanged draft workspace → show Live thread.
        if ($rows === [] && $workspaceId > 0 && $id !== '') {
            $rows = $this->fetchThreadRows($id, 0);
        }

        return $rows;
    }

    public function getComment(string $commentId, string $threadId, ?int $workspaceId = null): array|bool
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        $row = $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                $this->withWorkspace(['thread_id' => $threadId, 'id' => $commentId], $workspaceId),
            )->fetchAssociative();

        if (($row === false || $row === []) && $workspaceId > 0) {
            $row = $this->connectionPool
                ->getConnectionForTable(self::TABLE_NAME)
                ->select(
                    ['*'],
                    self::TABLE_NAME,
                    $this->withWorkspace(['thread_id' => $threadId, 'id' => $commentId], 0),
                )->fetchAssociative();
        }

        return $row;
    }

    /**
     * Copy a Live thread into the draft workspace when the draft does not own it yet.
     * Required before add/update/delete/resolve so Live is not mutated from a draft.
     */
    public function ensureDraftThread(string $threadId, int $workspaceId, ?string $draftRteId = null): void
    {
        if ($workspaceId <= 0 || $threadId === '') {
            return;
        }

        if ($this->fetchThreadRows($threadId, $workspaceId) !== []) {
            return;
        }

        $liveRows = $this->fetchThreadRows($threadId, 0);
        if ($liveRows === []) {
            return;
        }

        foreach ($liveRows as $row) {
            $liveRteId = WorkspaceScopeUtility::unscopeRteId((string)($row['rte_id'] ?? ''));
            $data = [
                'content_id' => (int)($row['content_id'] ?? 0),
                'rte_id' => $draftRteId ?? WorkspaceScopeUtility::scopeRteId($liveRteId, $workspaceId),
                'user_id' => (int)($row['user_id'] ?? 0),
                'thread_id' => (string)($row['thread_id'] ?? $threadId),
                'id' => (string)($row['id'] ?? ''),
                'content' => (string)($row['content'] ?? ''),
                'created_at' => (int)($row['created_at'] ?? time()),
                'resolved_at' => $row['resolved_at'] ?? null,
                'resolved_by' => $row['resolved_by'] ?? null,
                'workspace_id' => $workspaceId,
            ];
            if ($data['id'] === '') {
                continue;
            }
            $this->saveComment($data);
        }
    }

    public function updateComment(string $commentId, string $threadId, string $content, ?int $workspaceId = null): void
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        if ($workspaceId > 0) {
            $this->ensureDraftThread($threadId, $workspaceId);
        }

        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'content' => $content,
                ],
                $this->withWorkspace(
                    [
                        'id' => $commentId,
                        'thread_id' => $threadId,
                    ],
                    $workspaceId
                ),
            );
    }

    public function deleteComment(string $commentId, string $threadId, ?int $workspaceId = null): void
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        if ($workspaceId > 0) {
            $this->ensureDraftThread($threadId, $workspaceId);
        }

        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->delete(
                self::TABLE_NAME,
                $this->withWorkspace(
                    [
                        'id' => $commentId,
                        'thread_id' => $threadId,
                    ],
                    $workspaceId
                ),
            );
    }

    /**
     * Mark comments as resolved (archived)
     */
    public function markThreadAsResolved(string $threadId, int $resolvedAt, ?int $resolvedBy = null, ?int $workspaceId = null): void
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        if ($workspaceId > 0) {
            $this->ensureDraftThread($threadId, $workspaceId);
        }

        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'resolved_at' => $resolvedAt,
                    'resolved_by' => $resolvedBy,
                ],
                $this->withWorkspace(['thread_id' => $threadId], $workspaceId)
            );
    }

    /**
     * Mark comments as unresolved (reopen from archive)
     */
    public function markThreadAsUnresolved(string $threadId, ?int $workspaceId = null): void
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        if ($workspaceId > 0) {
            $this->ensureDraftThread($threadId, $workspaceId);
        }

        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'resolved_at' => null,
                    'resolved_by' => null,
                ],
                $this->withWorkspace(['thread_id' => $threadId], $workspaceId)
            );
    }

    /**
     * Fetch only unresolved comments by thread ID
     *
     * @return list<array<string, mixed>>
     */
    public function fetchUnresolvedCommentsByThreadId(string $id, ?int $workspaceId = null): array
    {
        $rows = $this->fetchCommentsByThreatId($id, $workspaceId);
        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => ($row['resolved_at'] ?? null) === null
        ));
    }

    /**
     * Fetch all comments including resolved (for archive).
     * Draft overlays Live for threads the draft does not own yet.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllCommentsByRteId(string $rteId, ?int $workspaceId = null): array
    {
        $workspaceId = $this->resolveWorkspaceId($workspaceId);
        $draftRows = $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                $this->withWorkspace(['rte_id' => $rteId], $workspaceId),
            )
            ->fetchAllAssociative();

        if ($workspaceId <= 0) {
            return $draftRows;
        }

        $liveRteId = WorkspaceScopeUtility::unscopeRteId($rteId);
        $liveRows = $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                $this->withWorkspace(['rte_id' => $liveRteId], 0),
            )
            ->fetchAllAssociative();

        return $this->overlayCommentRows($draftRows, $liveRows);
    }

    /**
     * Move draft-workspace comments onto Live when the content record is published.
     *
     * @return int Number of comment rows promoted
     */
    public function promoteWorkspaceCommentsToLive(int $workspaceId, string $table, int $liveUid): int
    {
        if ($workspaceId <= 0 || $liveUid <= 0 || $table === '') {
            return 0;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();

        $rtePrefix = sprintf('data[%s][%d][', $table, $liveUid);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'workspace_id',
                    $queryBuilder->createNamedParameter($workspaceId, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'content_id',
                        $queryBuilder->createNamedParameter($liveUid, Connection::PARAM_INT)
                    ),
                    $queryBuilder->expr()->like(
                        'rte_id',
                        $queryBuilder->createNamedParameter($rtePrefix . '%')
                    )
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            return 0;
        }

        return $this->promoteRowsToLive($rows);
    }

    /**
     * Promote comment rows for known thread ids onto Live (workspace_id = 0).
     *
     * @param list<string> $threadIds
     */
    public function promoteThreadsToLive(array $threadIds, ?int $preferredWorkspaceId = null): int
    {
        $threadIds = array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): string => trim((string)$id),
            $threadIds
        ))));
        if ($threadIds === []) {
            return 0;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();

        $constraints = [
            $queryBuilder->expr()->in(
                'thread_id',
                $queryBuilder->createNamedParameter($threadIds, Connection::PARAM_STR_ARRAY)
            ),
            $queryBuilder->expr()->gt(
                'workspace_id',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            ),
        ];
        if ($preferredWorkspaceId !== null && $preferredWorkspaceId > 0) {
            $constraints[] = $queryBuilder->expr()->eq(
                'workspace_id',
                $queryBuilder->createNamedParameter($preferredWorkspaceId, Connection::PARAM_INT)
            );
        }

        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(...$constraints)
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            // Prefer matching workspace when provided; otherwise any draft workspace.
            if ($preferredWorkspaceId !== null && $preferredWorkspaceId > 0) {
                return $this->promoteThreadsToLive($threadIds, null);
            }
            return 0;
        }

        return $this->promoteRowsToLive($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function promoteRowsToLive(array $rows): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
        $promoted = 0;
        foreach ($rows as $row) {
            $commentId = (string)($row['id'] ?? '');
            $liveRteId = WorkspaceScopeUtility::unscopeRteId((string)($row['rte_id'] ?? ''));
            $liveUid = (int)($row['content_id'] ?? 0);
            if ($commentId !== '') {
                $connection->delete(self::TABLE_NAME, [
                    'id' => $commentId,
                    'workspace_id' => 0,
                ]);
            }
            $connection->update(
                self::TABLE_NAME,
                [
                    'workspace_id' => 0,
                    'rte_id' => $liveRteId,
                    'content_id' => $liveUid,
                ],
                ['uid' => (int)$row['uid']]
            );
            $promoted++;
        }

        return $promoted;
    }

    /**
     * Fetch only resolved comments (for archive view)
     *
     * @return list<array<string, mixed>>
     */
    public function fetchResolvedComments(string $rteId, ?int $workspaceId = null): array
    {
        $rows = $this->fetchAllCommentsByRteId($rteId, $workspaceId);
        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => ($row['resolved_at'] ?? null) !== null
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchThreadRows(string $threadId, int $workspaceId): array
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                $this->withWorkspace(['thread_id' => $threadId], $workspaceId),
            )->fetchAllAssociative();
    }

    /**
     * Per-thread overlay: draft wins for any thread it already owns; otherwise Live.
     *
     * @param list<array<string, mixed>> $draftRows
     * @param list<array<string, mixed>> $liveRows
     * @return list<array<string, mixed>>
     */
    public static function overlayCommentRows(array $draftRows, array $liveRows): array
    {
        $ownedThreads = [];
        foreach ($draftRows as $row) {
            $threadId = (string)($row['thread_id'] ?? '');
            if ($threadId !== '') {
                $ownedThreads[$threadId] = true;
            }
        }

        $merged = $draftRows;
        foreach ($liveRows as $row) {
            $threadId = (string)($row['thread_id'] ?? '');
            if ($threadId === '' || isset($ownedThreads[$threadId])) {
                continue;
            }
            $merged[] = $row;
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>
     */
    private function withWorkspace(array $criteria, ?int $workspaceId = null): array
    {
        $criteria['workspace_id'] = $this->resolveWorkspaceId($workspaceId);
        return $criteria;
    }

    private function resolveWorkspaceId(?int $workspaceId = null): int
    {
        return $workspaceId ?? WorkspaceScopeUtility::currentWorkspaceId();
    }
}
