<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Repository;

use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ConfigurationRepository extends Repository
{
    protected ?QuerySettingsInterface $querySettings = null;

    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_configuration';

    public function injectQuerySettings(QuerySettingsInterface $querySettings): void
    {
        $this->querySettings = $querySettings;
    }

    public function initializeObject(): void
    {
        $this->setDefaultQuerySettings($this->querySettings->setRespectStoragePage(false));
    }

    public function findInvisibleRecord(string $key): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('configKey', $key));
        return $query->execute()->toarray();
    }

    public function insertMultipleRows(array $rows): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_NAME);

        try {
            foreach ($rows as $row) {
                $connection->insert(self::TABLE_NAME, $row);
            }
        } catch (DBALException $e) {
            return false;
        }
        return true;
    }

    public function findConfiguration(string $key): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('configKey', $key));
        $record = $query->execute()->getFirst();

        if ($record && $record->getFields()) {
            return json_decode($record->getFields(), true);
        }
        return [];
    }

    public function fetchConfiguration(string $key): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_NAME);
        $record =  $connection
            ->select(
                ['*'],
                self::TABLE_NAME,
                ['config_key' => $key],
            )->fetchAllAssociative();

        if ($record && isset($record[0]['fields']) && $record[0]['fields'] != '') {
            return json_decode($record[0]['fields'], true);
        }
        return [];

    }

    public function findEnable(): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('enable', 1));
        $query->setOrderings(['configKey' => QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray() ?? [];
    }

}
