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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ToolbarGroupsRepository extends Repository
{
    private const TOOLBAR_TABLE_NAME = 'tx_rteckeditorpack_domain_model_toolbaritems';

    public function updateToolBarItems(string $items, string $activePreset): bool
    {
        $itemsArray = array_map('trim', explode(',', $items));
        $uniqueStrings = [];
        $normalizedItemsArray = [];
        foreach ($itemsArray as $item) {
            if ($item === '|' || $item === '-') {
                $normalizedItemsArray[] = $item;
            } elseif (!in_array($item, $uniqueStrings, true)) {
                $uniqueStrings[] = $item;
                $normalizedItemsArray[] = $item;
            }
        }
        $normalizedItems = implode(',', $normalizedItemsArray);

        try {

            $data = [
                'preset' => $activePreset,
                'items' => $normalizedItems,
            ];
            $this->insertToolBarPreset($activePreset, $data);

        } catch (DBALException $e) {
            return false;
        }
        return true;

    }

    public function fetchToolBarItems(string $activePreset): array
    {
        $queryBuilder = $this->getQueryBuilder(self::TOOLBAR_TABLE_NAME);
        $existingRecord = $queryBuilder
                ->select('*')
                ->from(self::TOOLBAR_TABLE_NAME)
                ->where($queryBuilder->expr()->eq('preset', $queryBuilder->createNamedParameter($activePreset)))
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();

        if ($existingRecord && $existingRecord['items']) {
            return explode(',', $existingRecord['items']);
        }
        return [];
    }

    public function findPresets(array $toolBarItems = [], string $fields = '*'): array
    {
        $queryBuilder = $this->getQueryBuilder(self::TOOLBAR_TABLE_NAME);

        $constraints = [];
        if ($toolBarItems) {
            foreach ($toolBarItems as $item) {
                $constraints[] = $queryBuilder->expr()->inSet('items', $queryBuilder->createNamedParameter($item));
            }
        }

        $queryBuilder->select($fields)->from(self::TOOLBAR_TABLE_NAME);

        if ($constraints) {
            $queryBuilder->where(
                $queryBuilder->expr()->or(...$constraints)
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function insertToolBarPreset(string $activePreset, array $fieldData): bool
    {

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TOOLBAR_TABLE_NAME);
        $queryBuilder = $this->getQueryBuilder(self::TOOLBAR_TABLE_NAME);

        try {
            $existingRecord = $queryBuilder
                ->select('uid')
                    ->from(self::TOOLBAR_TABLE_NAME)
                    ->where(
                        $queryBuilder->expr()->eq('preset', $queryBuilder->createNamedParameter($activePreset))
                    )
                ->executeQuery()
                ->fetchOne();

            if ($existingRecord) {
                $connection->update(
                    self::TOOLBAR_TABLE_NAME,
                    $fieldData,
                    ['preset' => $activePreset]
                );
            } else {
                $connection->insert(
                    self::TOOLBAR_TABLE_NAME,
                    $fieldData
                );
            }
        } catch (DBALException $e) {
            return false;
        }
        return true;

    }

    protected function getQueryBuilder(string $tableName): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
    }

}
