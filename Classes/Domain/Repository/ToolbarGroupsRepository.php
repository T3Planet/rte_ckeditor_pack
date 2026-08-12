<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Repository;

use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ToolbarGroupsRepository extends Repository
{
    private const TOOLBAR_TABLE_NAME = PackRecordPersister::TABLE_PRESET;

    protected ?PackRecordPersister $packRecordPersister = null;

    protected ?Context $context = null;

    public function injectPackRecordPersister(PackRecordPersister $packRecordPersister): void
    {
        $this->packRecordPersister = $packRecordPersister;
    }

    public function injectContext(Context $context): void
    {
        $this->context = $context;
    }

    public function updateToolBarItems(string $items, string $activePreset): bool
    {
        $normalized = [];
        $seen = [];
        foreach (array_map('trim', explode(',', $items)) as $item) {
            if ($item === '|' || $item === '-') {
                $normalized[] = $item;
                continue;
            }
            if ($item === '' || isset($seen[$item])) {
                continue;
            }
            $seen[$item] = true;
            $normalized[] = $item;
        }

        return $this->insertToolBarPreset($activePreset, [
            'preset_key' => $activePreset,
            'toolbar_items' => implode(',', $normalized),
        ]);
    }

    public function findPresets(array $toolBarItems = [], string $fields = '*'): array
    {
        $queryBuilder = $this->getQueryBuilder(self::TOOLBAR_TABLE_NAME);
        $workspaceId = 0;
        try {
            $workspaceId = (int)($this->context ?? GeneralUtility::makeInstance(Context::class))
                ->getPropertyFromAspect('workspace', 'id', 0);
        } catch (AspectNotFoundException) {
            // Live.
        }
        $queryBuilder->getRestrictions()->add(
            GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId)
        );

        $queryBuilder->select($fields)->from(self::TOOLBAR_TABLE_NAME);

        if ($toolBarItems !== []) {
            $constraints = [];
            foreach ($toolBarItems as $item) {
                $constraints[] = $queryBuilder->expr()->inSet(
                    'toolbar_items',
                    $queryBuilder->createNamedParameter($item)
                );
            }
            $queryBuilder->where($queryBuilder->expr()->or(...$constraints));
        }

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        // WorkspaceRestriction returns live + draft rows; overlay is required on every
        // TYPO3 version that supports workspaces (same pattern as PackDraftIndicator).
        if ($workspaceId <= 0) {
            return $rows;
        }

        $overlaid = [];
        foreach ($rows as $row) {
            BackendUtility::workspaceOL(self::TOOLBAR_TABLE_NAME, $row);
            if (is_array($row)) {
                $overlaid[] = $row;
            }
        }

        return $overlaid;
    }

    public function insertToolBarPreset(string $activePreset, array $fieldData): bool
    {
        $persister = $this->packRecordPersister
            ?? GeneralUtility::makeInstance(PackRecordPersister::class);

        try {
            return $persister->upsertPresetByKey($activePreset, $fieldData) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function getQueryBuilder(string $tableName): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
    }
}
