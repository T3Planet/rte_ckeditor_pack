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

class RevisionHistoryRepository
{
    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_revisionhistory';

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
            ->fetchAssociative();
    }

    /**
     * @param $data
     */
    public function saveRevision($data): void
    {
        //Insert record after truncate...
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)
            ->insert(
                self::TABLE_NAME,
                $data,
            );
    }

    public function fetchRevisionsById(string $id)
    {
        return $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['content_id' => $id],
                [],
                ['current_version' => 'ASC', 'created_at' => 'ASC']
            )->fetchAllAssociative();
    }

    public function updateRevisions(string $revisionId, array $data): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                $data,
                [
                    'id' => $revisionId,
                ],
            );
    }

    public function replaceSubstNEWwithIDs(string $oldString, string $newString): void
    {

        $query = '
            UPDATE 
            ' . self::TABLE_NAME . '
            SET 
                content_id = REPLACE(content_id, ?, ?)
            WHERE 
                content_id LIKE ?
        ';

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
        $connection->executeStatement(
            $query,
            [$oldString, $newString, '%' . $oldString . '%']
        );
    }
}
