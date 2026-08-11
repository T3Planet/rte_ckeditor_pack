<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Draft-workspace markers for the Pack Features UI.
 */
class PackDraftIndicator
{
    private const WORKSPACE_COLORS = [
        'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'blue', 'indigo', 'purple', 'magenta',
    ];

    /** Hex fallbacks when --typo3-state-*-bg is unavailable (TYPO3 12 / 13). */
    private const WORKSPACE_COLOR_HEX = [
        'red' => '#c83c3c',
        'orange' => '#f28522',
        'yellow' => '#e8b000',
        'lime' => '#6da300',
        'green' => '#3d8b40',
        'teal' => '#1a8a8c',
        'blue' => '#1a6dcc',
        'indigo' => '#4c5fd7',
        'purple' => '#8a3ffc',
        'magenta' => '#d1278a',
    ];

    public function __construct(
        private readonly Context $context,
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Template variables for draft indicators (empty when Live).
     *
     * @return array{
     *   workspaceDraftMode: bool,
     *   workspaceAccentCss: string,
     *   liveToolbarItemsCsv: string,
     *   liveEnabledFeaturesCsv: string,
     *   draftChangedFeaturesCsv: string,
     *   draftOnlyFeatures: array<string, true>,
     *   draftDisabledFeatures: array<string, true>,
     *   draftChangedFeatures: array<string, true>,
     *   draftDifferingToolbarItems: array<string, true>,
     *   showDraftLegend: bool,
     *   draftLegendModules: array<string, true>
     * }
     */
    public function getUiData(int $presetUid): array
    {
        $empty = [
            'workspaceDraftMode' => false,
            'workspaceAccentCss' => 'var(--typo3-state-orange-bg, #f28522)',
            'liveToolbarItemsCsv' => '',
            'liveEnabledFeaturesCsv' => '',
            'draftChangedFeaturesCsv' => '',
            'draftOnlyFeatures' => [],
            'draftDisabledFeatures' => [],
            'draftChangedFeatures' => [],
            'draftDifferingToolbarItems' => [],
            'showDraftLegend' => false,
            'draftLegendModules' => [],
        ];

        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId <= 0 || $presetUid <= 0) {
            return $empty;
        }

        $liveFeatures = $this->fetchFeatureSnapshots($presetUid, 0);
        $draftFeatures = $this->fetchFeatureSnapshots($presetUid, $workspaceId);

        $liveEnabled = [];
        foreach ($liveFeatures as $configKey => $snapshot) {
            if ($snapshot['enable']) {
                $liveEnabled[$configKey] = true;
            }
        }
        $draftEnabled = [];
        foreach ($draftFeatures as $configKey => $snapshot) {
            if ($snapshot['enable']) {
                $draftEnabled[$configKey] = true;
            }
        }

        $draftOnlyFeatures = array_diff_key($draftEnabled, $liveEnabled);
        $draftDisabledFeatures = array_diff_key($liveEnabled, $draftEnabled);
        $draftChangedFeatures = $this->diffChangedFeatures($liveFeatures, $draftFeatures);

        $color = $this->resolveWorkspaceColor($workspaceId);
        $liveToolbar = $this->fetchPresetToolbarItems($presetUid, 0);
        $draftToolbar = $this->fetchPresetToolbarItems($presetUid, $workspaceId);
        $draftDifferingToolbarItems = [];
        foreach (array_unique(array_merge($liveToolbar, $draftToolbar)) as $item) {
            if (in_array($item, $liveToolbar, true) !== in_array($item, $draftToolbar, true)) {
                $draftDifferingToolbarItems[$item] = true;
            }
        }

        return [
            'workspaceDraftMode' => true,
            'workspaceAccentCss' => sprintf(
                'var(--typo3-state-%s-bg, %s)',
                $color,
                self::WORKSPACE_COLOR_HEX[$color] ?? self::WORKSPACE_COLOR_HEX['orange']
            ),
            'liveToolbarItemsCsv' => implode(',', $liveToolbar),
            'liveEnabledFeaturesCsv' => implode(',', array_keys($liveEnabled)),
            'draftChangedFeaturesCsv' => implode(',', array_keys($draftChangedFeatures)),
            'draftOnlyFeatures' => $draftOnlyFeatures,
            'draftDisabledFeatures' => $draftDisabledFeatures,
            'draftChangedFeatures' => $draftChangedFeatures,
            'draftDifferingToolbarItems' => $draftDifferingToolbarItems,
            'showDraftLegend' => $draftChangedFeatures !== []
                || $draftDifferingToolbarItems !== [],
            'draftLegendModules' => [],
        ];
    }

    /**
     * Map module tab keys that contain at least one draft-changed feature.
     *
     * @param array<string|int, array{key?: string, cards?: list<array<string, mixed>>}> $groupedModules
     * @param array<string, true> $draftChangedFeatures
     * @return array<string, true>
     */
    public function modulesWithDraftChanges(array $groupedModules, array $draftChangedFeatures): array
    {
        if ($draftChangedFeatures === []) {
            return [];
        }

        $modules = [];
        foreach ($groupedModules as $module) {
            if (!is_array($module)) {
                continue;
            }
            $moduleKey = (string)($module['key'] ?? '');
            if ($moduleKey === '') {
                continue;
            }
            foreach ($module['cards'] ?? [] as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $configKey = (string)($card['configuration']['config_key'] ?? '');
                if ($configKey !== '' && isset($draftChangedFeatures[$configKey])) {
                    $modules[$moduleKey] = true;
                    break;
                }
            }
        }

        return $modules;
    }

    private function getWorkspaceId(): int
    {
        try {
            return (int)$this->context->getPropertyFromAspect('workspace', 'id', 0);
        } catch (AspectNotFoundException) {
            return 0;
        }
    }

    protected function resolveWorkspaceColor(int $workspaceId): string
    {
        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_workspace');
            $color = $queryBuilder
                ->select('color')
                ->from('sys_workspace')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($workspaceId, Connection::PARAM_INT)
                    )
                )
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne();
        } catch (\Throwable) {
            return 'orange';
        }

        $color = is_string($color) ? $color : '';
        return in_array($color, self::WORKSPACE_COLORS, true) ? $color : 'orange';
    }

    /**
     * @return array<string, array{enable: bool, fields: string}>
     */
    protected function fetchFeatureSnapshots(int $presetUid, int $workspaceId): array
    {
        $rows = $this->fetchWorkspaceRows(PackRecordPersister::TABLE_FEATURE, $workspaceId, [
            'preset_uid' => $presetUid,
        ]);

        $snapshots = [];
        foreach ($rows as $row) {
            $configKey = (string)($row['config_key'] ?? '');
            if ($configKey === '') {
                continue;
            }
            $snapshots[$configKey] = [
                'enable' => (int)($row['enable'] ?? 0) === 1,
                'fields' => $this->normalizeFields((string)($row['fields'] ?? '')),
            ];
        }

        return $snapshots;
    }

    /**
     * @param array<string, array{enable: bool, fields: string}> $live
     * @param array<string, array{enable: bool, fields: string}> $draft
     * @return array<string, true>
     */
    private function diffChangedFeatures(array $live, array $draft): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($live), array_keys($draft))) as $configKey) {
            $liveSnap = $live[$configKey] ?? ['enable' => false, 'fields' => ''];
            $draftSnap = $draft[$configKey] ?? ['enable' => false, 'fields' => ''];
            if (
                $liveSnap['enable'] !== $draftSnap['enable']
                || $liveSnap['fields'] !== $draftSnap['fields']
            ) {
                $changed[$configKey] = true;
            }
        }

        return $changed;
    }

    private function normalizeFields(string $fields): string
    {
        $fields = trim($fields);
        if ($fields === '') {
            return '';
        }

        $decoded = json_decode($fields, true);
        if (!is_array($decoded)) {
            return $fields;
        }

        return (string)json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    protected function fetchPresetToolbarItems(int $presetUid, int $workspaceId): array
    {
        $rows = $this->fetchWorkspaceRows(PackRecordPersister::TABLE_PRESET, $workspaceId, [
            'uid' => $presetUid,
        ]);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return [];
        }

        return GeneralUtility::trimExplode(',', (string)($row['toolbar_items'] ?? ''), true);
    }

    /**
     * @param array<string, int|string> $equals
     * @return list<array<string, mixed>>
     */
    private function fetchWorkspaceRows(string $table, int $workspaceId, array $equals): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $workspaceId));

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

        $rows = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(...$constraints)
            ->executeQuery()
            ->fetchAllAssociative();

        if ($workspaceId <= 0) {
            return $rows;
        }

        $overlaid = [];
        foreach ($rows as $row) {
            BackendUtility::workspaceOL($table, $row);
            if (is_array($row)) {
                $overlaid[] = $row;
            }
        }

        return $overlaid;
    }
}
