<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Service;

use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Utility\ConfigurationMergeUtility;
use T3Planet\RteCkeditorPack\Utility\YamlLoadrUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Reusable YAML ↔ DB preset sync used by the backend module, export, and CLI.
 */
class PresetSyncService
{
    protected FrontendInterface $cache;

    public function __construct(
        protected readonly PresetRepository $presetRepository,
        protected readonly FeatureRepository $featureRepository,
        protected readonly PersistenceManager $persistenceManager,
        protected readonly YamlLoadrUtility $yamlLoader,
        protected readonly ConfigurationMergeUtility $mergeUtility,
        CacheManager $cacheManager,
    ) {
        $this->cache = $cacheManager->getCache('rte_ckeditor_config');
    }

    /**
     * Sync a single preset by UID or preset key.
     */
    public function syncPreset(int|string $presetUidOrKey, SyncMode $mode = SyncMode::Additive): SyncResult
    {
        try {
            $preset = $this->resolvePreset($presetUidOrKey);
            if ($preset === null) {
                return SyncResult::failure(
                    sprintf('Preset not found: %s', (string)$presetUidOrKey),
                    $mode,
                    is_string($presetUidOrKey) ? $presetUidOrKey : '',
                    is_int($presetUidOrKey) ? $presetUidOrKey : null,
                    [[
                        'title' => 'ckeditorKit.operation.error',
                        'message' => 'ckeditorKit.preset.sync.error.message',
                        'severity' => 2,
                    ]]
                );
            }

            $skipReason = $this->resolveSkipReason($preset, $mode);
            if ($skipReason !== null) {
                return SyncResult::skipped(
                    $preset->getPresetKey(),
                    (int)$preset->getUid(),
                    $mode,
                    $skipReason,
                    $preset->getIsCustom()
                        ? [[
                            'title' => 'ckeditorKit.preset.sync.skipped.custom',
                            'severity' => 1,
                        ]]
                        : []
                );
            }

            return match ($mode) {
                SyncMode::Reset => $this->resetPreset($preset),
                SyncMode::Strict, SyncMode::Ordered, SyncMode::Additive => $this->applyFromYaml($preset, $mode),
            };
        } catch (\Throwable $e) {
            return SyncResult::failure(
                $e->getMessage(),
                $mode,
                is_string($presetUidOrKey) ? $presetUidOrKey : '',
                is_int($presetUidOrKey) ? $presetUidOrKey : null,
                [[
                    'title' => 'ckeditorKit.operation.error',
                    'message' => $mode === SyncMode::Reset
                        ? 'ckeditorKit.preset.reset.error.message'
                        : 'ckeditorKit.preset.sync.error.message',
                    'severity' => 2,
                ]]
            );
        }
    }

    /**
     * Sync all presets stored in the database.
     *
     * Custom DB-only presets and presets without a registered YAML source are skipped.
     *
     * @return array{success: bool, synced: int, unchanged: int, skipped: int, failed: int, results: list<SyncResult>}
     */
    public function syncAll(SyncMode $mode = SyncMode::Additive): array
    {
        $results = [];
        $synced = 0;
        $unchanged = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->presetRepository->findAll() as $preset) {
            if (!$preset instanceof Preset) {
                continue;
            }

            $result = $this->syncPreset((int)$preset->getUid(), $mode);
            $results[] = $result;
            if ($result->skipped) {
                $skipped++;
            } elseif ($result->success && !$result->changed) {
                $unchanged++;
            } elseif ($result->success) {
                $synced++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'synced' => $synced,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Custom presets and missing YAML registrations are not syncable.
     */
    protected function resolveSkipReason(Preset $preset, SyncMode $mode): ?string
    {
        if ($preset->getIsCustom()) {
            return 'Custom database-only preset; synchronization is not applicable';
        }

        if ($mode !== SyncMode::Reset && !$this->yamlLoader->hasRegisteredPreset($preset->getPresetKey())) {
            return 'No registered YAML source; preset skipped';
        }

        return null;
    }

    protected function resolvePreset(int|string $presetUidOrKey): ?Preset
    {
        if (is_int($presetUidOrKey) || (is_string($presetUidOrKey) && ctype_digit($presetUidOrKey))) {
            $uid = (int)$presetUidOrKey;
            if ($uid <= 0) {
                return null;
            }
            $preset = $this->presetRepository->findByUid($uid);
            return $preset instanceof Preset ? $preset : null;
        }

        $key = trim((string)$presetUidOrKey);
        if ($key === '') {
            return null;
        }

        return $this->presetRepository->findByPresetKey($key);
    }

    protected function resetPreset(Preset $preset): SyncResult
    {
        $presetUid = (int)$preset->getUid();
        $presetKey = $preset->getPresetKey();

        $preset->setToolbarItems('');
        $this->presetRepository->update($preset);

        $this->featureRepository->removeByPresetId($presetUid);
        // removeByPresetId already persists when features exist; always persist toolbar clear.
        $this->persistenceManager->persistAll();
        $this->cache->flush();

        return SyncResult::success(
            $presetKey,
            $presetUid,
            SyncMode::Reset,
            'Preset reset successfully',
            [[
                'title' => 'ckeditorKit.operation.success',
                'message' => 'ckeditorKit.preset.reset.success.message',
                'severity' => 0,
            ]]
        );
    }

    protected function applyFromYaml(Preset $preset, SyncMode $mode): SyncResult
    {
        $presetUid = (int)$preset->getUid();
        $presetKey = $preset->getPresetKey();
        $notifications = [];

        $yamlConfig = $this->yamlLoader->loadYamlConfiguration($presetKey);
        if (empty($yamlConfig) || !isset($yamlConfig['editor']['config'])) {
            throw new \RuntimeException('YAML configuration not found for preset: ' . $presetKey, 1753770002);
        }

        $yamlConfiguration = $yamlConfig['editor']['config'];
        $yamlToolbarItems = $yamlConfiguration['toolbar']['items'] ?? [];
        if (!is_array($yamlToolbarItems)) {
            $yamlToolbarItems = [];
        }

        $toolbarString = match ($mode) {
            SyncMode::Strict => implode(',', array_values(array_filter($yamlToolbarItems, 'is_string'))),
            SyncMode::Ordered => $this->mergeUtility->syncToolbarOrdered(
                $yamlToolbarItems,
                $preset->getToolbarItems()
            ),
            default => $this->mergeUtility->syncToolBar($yamlToolbarItems, $preset->getToolbarItems()),
        };
        $changed = $toolbarString !== $preset->getToolbarItems();
        if ($changed) {
            $preset->setToolbarItems($toolbarString);
            $this->presetRepository->update($preset);
        }

        $successMessage = match ($mode) {
            SyncMode::Strict => 'Preset applied from YAML (strict)',
            SyncMode::Ordered => 'Preset synced (ordered)',
            default => 'Preset synced (additive)',
        };

        $features = $this->featureRepository->findByPresetUid($presetUid);
        foreach ($features as $feature) {
            if (!$feature instanceof Feature) {
                continue;
            }
            $changed = $this->syncFeature($feature, $yamlConfiguration, $mode, $notifications) || $changed;
        }

        if (!$changed) {
            return SyncResult::unchanged(
                $presetKey,
                $presetUid,
                $mode,
                'Nothing to sync',
                [[
                    'title' => 'ckeditorKit.operation.success',
                    'message' => 'ckeditorKit.preset.sync.nothing.message',
                    'severity' => 3,
                ]]
            );
        }

        $this->persistenceManager->persistAll();
        $this->cache->flush();

        $notifications[] = [
            'title' => 'ckeditorKit.operation.success',
            'message' => 'ckeditorKit.preset.sync.success.message',
            'severity' => 0,
        ];

        return SyncResult::success(
            $presetKey,
            $presetUid,
            $mode,
            $successMessage,
            $notifications
        );
    }

    /**
     * @param array<string, mixed> $yamlConfiguration
     * @param list<array{title: string, message?: string, severity: int}> $notifications
     */
    protected function syncFeature(
        Feature $feature,
        array $yamlConfiguration,
        SyncMode $mode,
        array &$notifications
    ): bool {
        $configKey = $feature->getConfigKey();
        if ($configKey === 'Mention') {
            $notifications[] = [
                'title' => 'ckeditorKit.preset.sync.mention',
                'severity' => 3,
            ];
            return false;
        }

        $moduleConfiguration = $feature->getFields() !== ''
            ? (json_decode($feature->getFields(), true) ?: [])
            : [];

        if ($mode !== SyncMode::Strict && empty($moduleConfiguration)) {
            return false;
        }

        $configKeyLower = strtolower($configKey);
        $syncData = [];

        if ($configKey === 'Font') {
            $fontItems = ['fontFamily', 'fontSize'];
            $fontConfig = [];
            foreach ($fontItems as $item) {
                if (array_key_exists($item, $yamlConfiguration)) {
                    $fontConfig[$item] = $yamlConfiguration[$item];
                }
            }
            if ($mode === SyncMode::Strict) {
                $syncData = $fontConfig;
            } else {
                $syncData = $this->mergeUtility->mergeOptionArrays($fontConfig, $moduleConfiguration);
            }
        } else {
            if (!array_key_exists($configKeyLower, $yamlConfiguration)) {
                return false;
            }
            $yamlFeatureConfig = [$configKeyLower => $yamlConfiguration[$configKeyLower]];
            if ($mode === SyncMode::Strict) {
                $syncData = $yamlFeatureConfig;
            } else {
                $syncData = $this->mergeUtility->mergeRecursiveDistinct($yamlFeatureConfig, $moduleConfiguration);
            }
        }

        if (empty($syncData)) {
            return false;
        }

        if ($syncData == $moduleConfiguration) {
            return false;
        }

        $feature->setFields(json_encode($syncData));
        $this->featureRepository->update($feature);
        return true;
    }
}
