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

class SuggestionRepository
{
    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_suggestions';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function checkExisting($id)
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['id' => $id]
            )
            ->fetchAllAssociative();
    }

    /**
     * @param $data
     */
    public function saveSuggestion($data): void
    {
        //Insert record after truncate...
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
            ->insert(
                self::TABLE_NAME,
                $data,
            );
    }

    public function fetchSuggestionsById(string $id)
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['id' => $id],
            )->fetchAssociative();
    }

    public function getSuggestion(string $suggestionId): array|bool
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['id' => $suggestionId],
            )->fetchAssociative();
    }

    public function updateSuggestion(string $suggestionId, string $hasComments): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'has_comments' => (string)$hasComments,
                ],
                [
                    'id' => $suggestionId,
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
}
