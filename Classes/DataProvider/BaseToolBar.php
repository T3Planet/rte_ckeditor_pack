<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\DataProvider;

use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use T3Planet\RteCkeditorPack\Utility\YamlLoadrUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class BaseToolBar
{
    private const TOOLBAR_ITEMS = [
        'bold',
        'italic',
        'underline',
        'strikethrough',
        'subscript',
        'superscript',
        'softhyphen',
        'undo',
        'redo',
        'removeFormat',
        'link',
        'insertTable',
        'tableColumn',
        'tableRow',
        'mergeTableCells',
        'TableProperties',
        'TableCellProperties',
        'selectAll',
        'sourceEditing',
        'horizontalLine',
        'bulletedList',
        'numberedList',
        'blockQuote',
    ];

    public function __construct(
        protected readonly PresetRepository $presetRepository,
        protected readonly FeatureRepository $featureRepository,
        protected readonly ToolbarGroupsRepository $toolBarRepository,
        protected readonly PackRecordPersister $packRecordPersister,
    ) {}

    public function findEnableToolbarItems(int $activePresetUid = 0): array
    {
        // Items enabled as features - Get from FeatureRepository using preset UID
        $toolBarItemArrayRaw = [];
        
        if ($activePresetUid > 0) {
            // Get enabled features for this preset
            $enabledFeatures = $this->featureRepository->findEnabledByPresetUid($activePresetUid);
            
            foreach ($enabledFeatures as $feature) {
                $configKey = $feature->getConfigKey();
                $featureToolbarItems = [];

                // Get toolbar items from feature's toolbarItems field
                $storedToolbarItems = $feature->getToolbarItems();
                if ($storedToolbarItems) {
                    $featureToolbarItems = array_merge(
                        $featureToolbarItems,
                        GeneralUtility::trimExplode(',', $storedToolbarItems, true)
                    );
                }
                
                // Also get from Modules.php configuration
                $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)
                    ->getItemByConfigKey($configKey);
                if (isset($moduleConfiguration['configuration']['toolBarItems'])) {
                    $featureToolbarItems = array_merge(
                        $featureToolbarItems,
                        GeneralUtility::trimExplode(
                            ',',
                            $moduleConfiguration['configuration']['toolBarItems'],
                            true
                        )
                    );
                }

                foreach ($featureToolbarItems as $value) {
                    $toolBarItemArrayRaw[] = [
                        'toolBar' => $value,
                        'configKey' => $configKey,
                    ];
                }
            }
        }

        $activeItemArray = [];
        $toolbar = GeneralUtility::makeInstance(ToolbarIcons::class);

        if ($toolBarItemArrayRaw) {
            foreach ($toolBarItemArrayRaw as $featureToolbarItem) {
                $value = $featureToolbarItem['toolBar'];
                $label = LocalizationUtility::translate(
                    'toolbar.item.' . str_replace(':', '_', $value),
                    'rte_ckeditor_pack'
                ) ?? $value;
                $icon = $toolbar->getIconByName($value);
                $activeItemArray[] = [
                    'icon' => $icon,
                    'toolBar' => $value,
                    'label' => $label,
                    'is_premium' => $toolbar->isPremiumToolbarItem($value),
                    'configKey' => $featureToolbarItem['configKey'],
                ];
            }
        }

        // Items visible in toolbar - Get from preset table's toolbar_items column
        $toolBarItemArray = [];
        
        if ($activePresetUid > 0) {
            // Get preset by UID
            $preset = $this->presetRepository->findByUid(uid: $activePresetUid);
            
            if ($preset && $preset->getToolbarItems()) {
                // Get toolbar items from preset table
                $toolBarItems = GeneralUtility::trimExplode(',', $preset->getToolbarItems(), true);
                $toolBarItems = $this->collapseRestrictedEditingToolbarItems($toolBarItems);
                
                foreach ($toolBarItems as $value) {
                    if (str_starts_with($value, 'Group-')) {
                        $groupId = (int)GeneralUtility::trimExplode('Group-', $value)[1];
                        $group = $this->toolBarRepository->findByUid($groupId);
                        if ($group) {
                            $toolBarItemArray[] = [
                                'icon' => 'rte_group',
                                'toolBar' => $value,
                                'label' => $group->getLabel(),
                                'items' =>  $group->getItems(),
                            ];
                        }
                    } else {
                        $label = LocalizationUtility::translate(
                            'toolbar.item.' . str_replace(':', '_', $value),
                            'rte_ckeditor_pack'
                        ) ?? $value;
                        $icon = $toolbar->getIconByName($value);
                        $toolBarItemArray[] = [
                            'icon' => $icon,
                            'toolBar' => $value,
                            'label' => $label,
                            'is_premium' => $toolbar->isPremiumToolbarItem($value)
                        ];
                    }
                }
            } else {
                // Fallback to YAML if preset not found or toolbar_items is empty
                $presetKey = $preset ? $preset->getPresetKey() : '';
                $yamlLoadr = GeneralUtility::makeInstance(YamlLoadrUtility::class);
                $toolBarItemArray = $yamlLoadr->fetchToolBar($presetKey);
            }
        } else {
            // Fallback to YAML if no preset UID provided
            $yamlLoadr = GeneralUtility::makeInstance(YamlLoadrUtility::class);
            $toolBarItemArray = $yamlLoadr->fetchToolBar('');
        }

        $groups = $this->toolBarRepository->findAll();
        if ($groups) {
            foreach ($groups as $group) {
                $activeItemArray[] = [
                    'icon' => 'rte_group',
                    'label' => $group->getLabel(),
                    'toolBar' => 'Group-' . $group->getUid(),
                    'items' =>  $group->getItems(),
                ];
            }
        }

        // Default ToolBar Items which is not active in selected preset
        $defaultToolBar = $this->getDefaultToolBarItems();
        if ($defaultToolBar) {
            foreach ($defaultToolBar as $dftItem) {

                $label = LocalizationUtility::translate(
                    'toolbar.item.' . str_replace(':', '_', $dftItem),
                    'rte_ckeditor_pack'
                ) ?? $dftItem;
                $icon = $toolbar->getIconByName($dftItem);
                $activeItemArray[] = [
                    'icon' => $icon,
                    'toolBar' => $dftItem,
                    'label' => $label,
                    'is_premium' => $toolbar->isPremiumToolbarItem($dftItem)
                ];

            }
        }
        if ($toolBarItemArray) {
            $toolBarsToRemove = array_column($toolBarItemArray, 'toolBar');
            $activeItemArray = array_filter($activeItemArray, function ($item) use ($toolBarsToRemove) {
                return !in_array($item['toolBar'], $toolBarsToRemove);
            });
        }
        // Filter duplicated Items
        $uniqueItems = [];
        $seenToolBars = [];
        foreach ($activeItemArray as $item) {
            if (!in_array($item['toolBar'], $seenToolBars, true)) {
                $seenToolBars[] = $item['toolBar'];
                $uniqueItems[] = $item;
            }
        }
        
        return [
            'activeFeaturItems' => $uniqueItems,
            'visibleToolBarItems' => $toolBarItemArray,
        ];

    }

    public function updateToolBar(string $configKey, int $presetUid): void
    {
        $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($configKey);

        if (isset($moduleConfiguration['configuration']) && isset($moduleConfiguration['configuration']['toolBarItems'])) {
            $moduleToolBarItems = explode(',', $moduleConfiguration['configuration']['toolBarItems']);
            $preset = $this->presetRepository->findBy(['uid' => $presetUid])->getFirst();
            if ($preset) {
                $toolBarItems = explode(',', $preset->getToolbarItems());
                if ($configKey === 'RestrictedEditingMode') {
                    $toolBarItems = array_values(array_filter(
                        $toolBarItems,
                        static fn(string $item): bool => !str_starts_with(trim($item), 'restrictedEditing')
                    ));
                } else {
                    $matchingIndices = [];
                    foreach ($moduleToolBarItems as $childValue) {
                        $index = array_search($childValue, $toolBarItems, true);
                        if ($index !== false) {
                            $matchingIndices[] = $index;
                        }
                    }
                    if ($matchingIndices) {
                        foreach ($matchingIndices as $value) {
                            unset($toolBarItems[$value]);
                        }
                    }
                }
                $toolBar = implode(',', $toolBarItems);
                $this->packRecordPersister->update(
                    PackRecordPersister::TABLE_PRESET,
                    (int)$preset->getUid(),
                    ['toolbar_items' => $toolBar]
                );
            }
        }
    }

    /**
     * Pack exposes one Restricted editing toolbar item; collapse legacy variants in the UI.
     *
     * @param list<string> $items
     * @return list<string>
     */
    private function collapseRestrictedEditingToolbarItems(array $items): array
    {
        $replaced = false;
        $result = [];
        foreach ($items as $item) {
            if (str_starts_with($item, 'restrictedEditing')) {
                if (!$replaced) {
                    $result[] = 'restrictedEditing';
                    $replaced = true;
                }
                continue;
            }
            $result[] = $item;
        }

        return $result;
    }

    public function findAvailablePresets(): array
    {
        // Get presets from TYPO3 config
        $typo3Presets = $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets'] ?? [];
        
        // Get presets from new preset table and create mapping
        $presets = $this->presetRepository->findAll();
        $presetMap = [];
        foreach ($presets as $preset) {
            $presetKey = $preset->getPresetKey();
            $presetMap[$presetKey] = [
                'uid' => $preset->getUid(),
                'key' => $presetKey,
                'is_custom' => $preset->getIsCustom(),
                'hidden' => $preset->getHidden() ? 1 : 0,
                'usage_source' => $preset->getUsageSource(),
            ];
        }
        
        $corePresets = [];
        $customPresets = [];
        
        foreach ($typo3Presets as $presetKey => $presetConfig) {
            if (!isset($presetMap[$presetKey])) {

                $yamlLoadr = GeneralUtility::makeInstance(YamlLoadrUtility::class);
                $toolBarItems = $yamlLoadr->fetchToolBarItems($presetKey);
                
                try {
                    $uid = $this->packRecordPersister->create(
                        PackRecordPersister::TABLE_PRESET,
                        [
                            'preset_key' => $presetKey,
                            'is_custom' => 0,
                            'hidden' => 0,
                            'toolbar_items' => $toolBarItems,
                        ]
                    );
                    if ($uid <= 0) {
                        continue;
                    }

                    // Add to core presets with new UID
                    $corePresets[$presetKey] = [
                        'uid' => $uid,
                        'key' => $presetKey,
                        'is_custom' => false,
                        'hidden' => 1,
                        'usage_source' => 0,
                    ];
                } catch (\Exception) {}
            } else {
                $presetData = $presetMap[$presetKey];
                $presetData['is_custom'] = false;
                $corePresets[$presetKey] = $presetData;
            }
        }
        
        foreach ($presetMap as $presetKey => $presetData) {
            if (!isset($corePresets[$presetKey])) {
                $presetData['is_custom'] = true;
                $customPresets[$presetKey] = $presetData;
            }
        }
        
        return [
            'core' => $corePresets,
            'custom' => $customPresets,
        ];
    }


    public function getDefaultToolBarItems(): array
    {
        return self::TOOLBAR_ITEMS ?? [];
    }

}
