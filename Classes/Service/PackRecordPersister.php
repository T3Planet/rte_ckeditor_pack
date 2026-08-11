<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes Pack records through DataHandler so core workspace versioning applies automatically.
 */
class PackRecordPersister
{
    public const TABLE_PRESET = 'tx_rteckeditorpack_domain_model_preset';
    public const TABLE_FEATURE = 'tx_rteckeditorpack_domain_model_feature';
    public const TABLE_TOOLBARGROUPS = 'tx_rteckeditorpack_domain_model_toolbargroups';

    /** @var list<string> */
    public const TABLES = [
        self::TABLE_PRESET,
        self::TABLE_FEATURE,
        self::TABLE_TOOLBARGROUPS,
    ];

    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly Context $context,
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function create(string $table, array $fields): int
    {
        $this->assertPackTable($table);
        $fields['pid'] = 0;

        if (
            $table === self::TABLE_PRESET
            && ($presetKey = (string)($fields['preset_key'] ?? '')) !== ''
            && $this->livePresetKeyExists($presetKey)
        ) {
            $this->errors[] = sprintf('Preset key "%s" already exists.', $presetKey);
            return 0;
        }

        $dataHandler = $this->createDataHandler();
        $newId = 'NEW' . bin2hex(random_bytes(4));
        $dataHandler->start([$table => [$newId => $fields]], [], $this->resolveBackendUser());
        $dataHandler->process_datamap();
        $this->collectErrors($dataHandler);

        return (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(string $table, int $uid, array $fields): bool
    {
        $this->assertPackTable($table);
        if ($uid <= 0) {
            $this->errors[] = 'Invalid record uid for update.';
            return false;
        }

        unset($fields['uid'], $fields['pid']);
        $dataHandler = $this->createDataHandler();
        $dataHandler->start([$table => [$uid => $fields]], [], $this->resolveBackendUser());
        $dataHandler->process_datamap();
        $this->collectErrors($dataHandler);

        return $dataHandler->errorLog === [];
    }

    public function delete(string $table, int $uid): bool
    {
        $this->assertPackTable($table);
        if ($uid <= 0) {
            return false;
        }

        $dataHandler = $this->createDataHandler();
        $dataHandler->start([], [$table => [$uid => ['delete' => 1]]], $this->resolveBackendUser());
        $dataHandler->process_cmdmap();
        $this->collectErrors($dataHandler);

        return $dataHandler->errorLog === [];
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function upsertPresetByKey(string $presetKey, array $fields): int
    {
        $presetKey = trim($presetKey);
        if ($presetKey === '') {
            return 0;
        }

        $fields['preset_key'] = $presetKey;
        $uid = $this->findUid(self::TABLE_PRESET, ['preset_key' => $presetKey]);
        if ($uid > 0) {
            return $this->update(self::TABLE_PRESET, $uid, $fields) ? $uid : 0;
        }

        return $this->create(self::TABLE_PRESET, $fields);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function upsertFeature(int $presetUid, string $configKey, array $fields): int
    {
        if ($presetUid <= 0 || $configKey === '') {
            return 0;
        }

        $fields['preset_uid'] = $presetUid;
        $fields['config_key'] = $configKey;
        $uid = $this->findUid(self::TABLE_FEATURE, [
            'preset_uid' => $presetUid,
            'config_key' => $configKey,
        ]);
        if ($uid > 0) {
            return $this->update(self::TABLE_FEATURE, $uid, $fields) ? $uid : 0;
        }

        return $this->create(self::TABLE_FEATURE, $fields);
    }

    public function deleteFeaturesByPresetUid(int $presetUid): bool
    {
        if ($presetUid <= 0) {
            return false;
        }

        $ok = true;
        foreach ($this->findFeatureUidsByPreset($presetUid) as $uid) {
            $ok = $this->delete(self::TABLE_FEATURE, $uid) && $ok;
        }
        return $ok;
    }

    public function livePresetKeyExists(string $presetKey): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PRESET);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_PRESET)
            ->where(
                $queryBuilder->expr()->eq('preset_key', $queryBuilder->createNamedParameter($presetKey)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_oid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * @param array<string, int|string> $equals
     */
    private function findUid(string $table, array $equals): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $this->respectCurrentWorkspace($queryBuilder);

        $constraints = [];
        foreach ($equals as $column => $value) {
            $constraints[] = $queryBuilder->expr()->eq(
                $column,
                $queryBuilder->createNamedParameter(
                    $value,
                    is_int($value) ? Connection::PARAM_INT : Connection::PARAM_STR
                )
            );
        }

        return (int)$queryBuilder
            ->select('uid')
            ->from($table)
            ->where(...$constraints)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return list<int>
     */
    private function findFeatureUidsByPreset(int $presetUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FEATURE);
        $this->respectCurrentWorkspace($queryBuilder);

        return array_map(
            'intval',
            $queryBuilder
                ->select('uid')
                ->from(self::TABLE_FEATURE)
                ->where(
                    $queryBuilder->expr()->eq(
                        'preset_uid',
                        $queryBuilder->createNamedParameter($presetUid, Connection::PARAM_INT)
                    )
                )
                ->executeQuery()
                ->fetchFirstColumn()
        );
    }

    private function respectCurrentWorkspace(QueryBuilder $queryBuilder): void
    {
        $workspaceId = 0;
        try {
            $workspaceId = (int)$this->context->getPropertyFromAspect('workspace', 'id', 0);
        } catch (AspectNotFoundException) {
            // Live.
        }
        $queryBuilder->getRestrictions()->add(
            GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId)
        );
    }

    private function createDataHandler(): DataHandler
    {
        $this->errors = [];
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        return $dataHandler;
    }

    private function resolveBackendUser(): BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            throw new \RuntimeException(
                'A backend user is required to persist Pack records via DataHandler.',
                1754900000
            );
        }

        return $backendUser;
    }

    private function collectErrors(DataHandler $dataHandler): void
    {
        foreach ($dataHandler->errorLog as $message) {
            $this->errors[] = (string)$message;
        }
    }

    private function assertPackTable(string $table): void
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Table "%s" is not a Pack table.', $table),
                1754563200
            );
        }
    }
}
