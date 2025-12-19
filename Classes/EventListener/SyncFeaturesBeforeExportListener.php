<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Utility\YamlLoadrUtility;
use T3Planet\RteCkeditorPack\Utility\ConfigurationMergeUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Service that syncs features before preset export
 */
class SyncFeaturesBeforeExportListener
{
    public function __construct(
        protected readonly FeatureRepository $featureRepository,
        protected readonly PresetRepository $presetRepository,
        protected readonly PersistenceManager $persistenceManager
    ) {
    }

    /**
     * Sync features for the preset before export
     *
     * @param Preset $preset
     * @return void
     */
    public function syncPresetFeatures(Preset $preset): void
    {
        $presetUid = $preset->getUid();

        try {
            if ($presetUid > 0) {
                // Get preset key to load YAML configuration
                $presetKey = $preset->getPresetKey();
                // Load YAML configuration
                $yamlLoader = GeneralUtility::makeInstance(YamlLoadrUtility::class);
                $yamlConfig = $yamlLoader->loadYamlConfiguration($presetKey);
                if (empty($yamlConfig) && isset($yamlConfig['editor']['config'])) {
                    throw new \Exception('YAML configuration not found for preset: ' . $presetKey);
                }
                $yamlConfiguration = $yamlConfig['editor']['config'];
                $mergeUtility = GeneralUtility::makeInstance(ConfigurationMergeUtility::class);
                $toolBariteams = $yamlConfiguration['toolbar']['items'];
                $syncData = $mergeUtility->syncToolBar($toolBariteams, $preset->getToolbarItems());
                $preset->setToolbarItems($syncData);
                $this->presetRepository->update($preset);
                
                $features = $this->featureRepository->findByPresetUid($presetUid);
                if (empty($features)) {
                    return;
                }

                foreach ($features as $feature) {
                    $yamlFeatureConfig = [];
                    $configKey = $feature->getConfigKey();
                    if ($configKey == 'Mention') {
                        continue;
                    }
                    
                    $moduleConfiguration = $feature->getFields() ? json_decode($feature->getFields(), true) : [];
                    if (empty($moduleConfiguration)) {
                        continue;
                    }
                    
                    $configKeyLower = strtolower($configKey);
                    // Special handling for Font configKey - check 4 font items
                    if ($configKey === 'Font') {
                        $fontItems = ['fontFamily', 'fontSize'];
                        $fontConfig = [];
                        foreach ($fontItems as $item) {
                            if (array_key_exists($item, $yamlConfiguration)) {
                                $fontConfig[$item] = $yamlConfiguration[$item];
                            }
                        }
                        $syncData = $mergeUtility->mergeOptionArrays($fontConfig, $moduleConfiguration);
                    } else {
                        if (!array_key_exists($configKeyLower, $yamlConfiguration)) {
                            continue;
                        }
                        $yamlFeatureConfig[$configKeyLower] = $yamlConfiguration[$configKeyLower];
                        $syncData = $mergeUtility->mergeRecursiveDistinct($yamlFeatureConfig, $moduleConfiguration);
                    }
                    if (empty($syncData)) {
                        continue;
                    }
                    $feature->setFields(json_encode($syncData));
                    $this->featureRepository->update($feature);
                }

                $this->persistenceManager->persistAll();
                
                // Flush cache
                $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('rte_ckeditor_config');
                $cache->flush();
            } else {
                throw new \Exception('Invalid preset UID');
            }
        } catch (\Exception $e) {
            // Log error but don't stop export
            // Error is silently ignored to allow export to proceed
        }
    }
}
