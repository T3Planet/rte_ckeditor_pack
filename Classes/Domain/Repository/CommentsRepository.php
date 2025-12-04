<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;

class CommentsRepository
{
    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_comment';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function checkExisting($id)
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['id'],
                self::TABLE_NAME,
                ['id' => $id]
            )
            ->fetchAllAssociative();
    }

    /**
     * @param $data
     */
    public function saveComment($data): void
    {
        //Insert record after truncate...
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
            ->insert(
                self::TABLE_NAME,
                $data,
            );
    }

    public function fetchCommentsByThreatId(string $id)
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['thread_id' => $id],
            )->fetchAllAssociative();
    }

    public function getComment(string $commentId, string $threadId): array|bool
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['thread_id' => $threadId, 'id' => $commentId],
            )->fetchAssociative();
    }

    public function updateComment(string $commentId, string $threadId, string $content): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'content' => $content,
                ],
                [
                    'id' => $commentId,
                    'thread_id' => $threadId,
                ],
            );
    }

    public function deleteComment(string $commentId, string $threadId): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->delete(
                self::TABLE_NAME,
                [
                    'id' => $commentId,
                    'thread_id' => $threadId,
                ],
            );
    }

    /**
     * Mark comments as resolved (archived)
     * @param string $threadId
     * @param int $resolvedAt
     * @param int|null $resolvedBy
     */
    public function markThreadAsResolved(string $threadId, int $resolvedAt, ?int $resolvedBy = null): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'resolved_at' => $resolvedAt,
                    'resolved_by' => $resolvedBy,
                ],
                [
                    'thread_id' => $threadId,
                ]
            );
    }

    /**
     * Mark comments as unresolved (reopen from archive)
     * @param string $threadId
     */
    public function markThreadAsUnresolved(string $threadId): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'resolved_at' => null,
                    'resolved_by' => null,
                ],
                [
                    'thread_id' => $threadId,
                ]
            );
    }

    /**
     * Fetch only unresolved comments by thread ID
     * @param string $id
     * @return array
     */
    public function fetchUnresolvedCommentsByThreadId(string $id): array
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                [
                    'thread_id' => $id,
                    'resolved_at' => null,
                ],
            )->fetchAllAssociative();
    }

    /**
     * Fetch all comments including resolved (for archive)
     * @param string $rteId
     * @return array
     */
    public function fetchAllCommentsByRteId(string $rteId): array
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['rte_id' => $rteId],
            )->fetchAllAssociative();
    }

    /**
     * Fetch only resolved comments (for archive view)
     * @param string $rteId
     * @return array
     */
    public function fetchResolvedComments(string $rteId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        
        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('rte_id', $queryBuilder->createNamedParameter($rteId)),
                $queryBuilder->expr()->isNotNull('resolved_at')
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
