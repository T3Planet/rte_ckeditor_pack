<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\DataProvider;

use T3Planet\RteCkeditorPack\Domain\Repository\ConfigurationRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
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
        'findAndReplace',
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
        'alignment',
        'specialCharacters',
        'style',
        'heading',
    ];

    public function __construct(
        protected readonly ConfigurationRepository $configurationRepository,
        protected readonly ToolbarGroupsRepository $toolBarRepository
    ) {}

    public function findEnableToolbarItems(string $activePreset = ''): array
    {
        // Items enable as a feature
        $enabledModule = $this->configurationRepository->findByEnable(true);

        $toolBarItemArrayRaw = [];
        foreach ($enabledModule as $module) {
            $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)
                ->getItemByConfigKey($module->getConfigKey());
            if (isset($moduleConfiguration['configuration']['toolBarItems'])) {
                $toolBarItemArrayRaw[] = GeneralUtility::trimExplode(
                    ',',
                    $moduleConfiguration['configuration']['toolBarItems']
                );
            }
        }

        $activeFeaturesToolbarItems = array_merge([], ...$toolBarItemArrayRaw);

        $activeItemArray = [];
        $toolbar = GeneralUtility::makeInstance(ToolbarIcons::class);

        if ($activeFeaturesToolbarItems) {
            foreach ($activeFeaturesToolbarItems as $value) {
                $label = LocalizationUtility::translate('toolbar.item.' . $value, 'rte_ckeditor_pack') ?? $value;
                $icon = $toolbar->getIconByName($value);
                $activeItemArray[] = [
                    'icon' => $icon,
                    'toolBar' => $value,
                    'label' => $label,
                ];
            }
        }

        // Items visible in toolbar
        $toolBarItems = $this->toolBarRepository->fetchToolBarItems($activePreset);

        $toolBarItemArray = [];
        if ($toolBarItems) {
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
                    $label = LocalizationUtility::translate('toolbar.item.' . $value, 'rte_ckeditor_pack') ?? $value;
                    $icon = $toolbar->getIconByName($value);
                    $toolBarItemArray[] = [
                        'icon' => $icon,
                        'toolBar' => $value,
                        'label' => $label,
                    ];
                }
            }
        } else {
            $yamlLoadr = GeneralUtility::makeInstance(YamlLoadrUtility::class);
            $toolBarItemArray = $yamlLoadr->fetchToolBar($activePreset);
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

                $label = LocalizationUtility::translate('toolbar.item.' . $dftItem, 'rte_ckeditor_pack') ?? $dftItem;
                $icon = $toolbar->getIconByName($dftItem);
                $activeItemArray[] = [
                    'icon' => $icon,
                    'toolBar' => $dftItem,
                    'label' => $label,
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

    public function updateToolBar(string $configKey): void
    {
        $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($configKey);

        if (isset($moduleConfiguration['configuration']) && isset($moduleConfiguration['configuration']['toolBarItems'])) {
            $moduleToolBarItems = explode(',', $moduleConfiguration['configuration']['toolBarItems']);
            $records = $this->toolBarRepository->findPresets($moduleToolBarItems);
            if ($records) {
                foreach ($records as $presetRaw) {
                    $toolBarItems = explode(',', $presetRaw['items']);
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
                    $toolBar = implode(',', $toolBarItems);
                    $this->toolBarRepository->updateToolBarItems($toolBar, $presetRaw['preset']);
                }
            }
        }
    }

    public function findAvailablePresets(): array
    {
        $availablePresets = $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets'] ?? [];
        $allPresets = $this->toolBarRepository->findPresets([], 'preset');
        $presetKeys = array_column($allPresets, 'preset');
        $missingPresets = array_diff($presetKeys, array_keys($availablePresets));
        $availablePresets = array_merge($availablePresets, array_fill_keys($missingPresets, ''));
        return $availablePresets;
    }

    public function getDefaultToolBarItems(): array
    {
        return self::TOOLBAR_ITEMS ?? [];
    }

}
