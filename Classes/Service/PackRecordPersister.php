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
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes Pack records through DataHandler so core workspace versioning applies automatically.
 *
 * Pack config is site-global (TCA rootLevel=1). Records must live on pid=0 so workspace
 * drafts are not bound to a single page (e.g. page 1) in the Workspaces module.
 */
class PackRecordPersister
{
    public const TABLE_PRESET = 'tx_rteckeditorpack_domain_model_preset';
    public const TABLE_FEATURE = 'tx_rteckeditorpack_domain_model_feature';
    public const TABLE_TOOLBARGROUPS = 'tx_rteckeditorpack_domain_model_toolbargroups';

    /** Global root page for Pack tables (TCA rootLevel = 1). */
    public const ROOT_PID = 0;

    private const REGISTRY_NAMESPACE = 'rte_ckeditor_pack';
    private const REGISTRY_ROOT_LEVEL_KEY = 'recordsOnRootLevel';

    /** @var list<string> */
    public const TABLES = [
        self::TABLE_PRESET,
        self::TABLE_FEATURE,
        self::TABLE_TOOLBARGROUPS,
    ];

    /** @var list<string> */
    private array $errors = [];

    private static bool $rootLevelEnsured = false;

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
     * Move legacy Pack rows off page PIDs onto root so workspace changes apply site-wide.
     *
     * Cheap after the first successful run: request-static skip + optional sys_registry flag.
     * Not hooked on every backend request — only Pack module / Pack writes call this.
     *
     * @return int Number of rows updated across all Pack tables
     */
    public function ensureRecordsOnRootLevel(): int
    {
        if (self::$rootLevelEnsured) {
            return 0;
        }
        self::$rootLevelEnsured = true;

        if ($this->isRootLevelMarkedDone()) {
            return 0;
        }

        $moved = 0;
        foreach (self::TABLES as $table) {
            try {
                $connection = $this->connectionPool->getConnectionForTable($table);
                $offRoot = (int)$connection->fetchOne(
                    sprintf('SELECT COUNT(*) FROM %s WHERE pid <> %d', $table, self::ROOT_PID)
                );
                if ($offRoot <= 0) {
                    continue;
                }
                $moved += $connection->executeStatement(
                    sprintf('UPDATE %s SET pid = %d WHERE pid <> %d', $table, self::ROOT_PID, self::ROOT_PID)
                );
            } catch (\Throwable) {
                // Table may not exist yet during early install / unit tests without DB.
            }
        }

        // Writes always force pid=0 afterwards, so this one-time heal stays done.
        $this->markRootLevelDone();

        return $moved;
    }

    private function isRootLevelMarkedDone(): bool
    {
        try {
            $registry = $this->resolveRegistry();
            return $registry !== null
                && (bool)$registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_ROOT_LEVEL_KEY, false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function markRootLevelDone(): void
    {
        try {
            $this->resolveRegistry()?->set(self::REGISTRY_NAMESPACE, self::REGISTRY_ROOT_LEVEL_KEY, true);
        } catch (\Throwable) {
            // Ignore registry write failures (unit tests / missing DB connection).
        }
    }

    private function resolveRegistry(): ?Registry
    {
        try {
            $registry = GeneralUtility::makeInstance(Registry::class);
            return $registry instanceof Registry ? $registry : null;
        } catch (\Throwable) {
            // Unit tests / early boot may lack DI for Registry(ConnectionPool).
            return null;
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function create(string $table, array $fields): int
    {
        $this->assertPackTable($table);
        $this->ensureRecordsOnRootLevel();
        $fields['pid'] = self::ROOT_PID;

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

        $this->ensureRecordsOnRootLevel();
        unset($fields['uid']);
        // Keep / force root pid so workspace versions stay global, not page-bound.
        $fields['pid'] = self::ROOT_PID;
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
