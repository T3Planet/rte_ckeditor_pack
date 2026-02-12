<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\EventListener;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use T3Planet\RteCkeditorPack\DataProvider\Modules;
use T3Planet\RteCkeditorPack\Utility\ChannelIdUtility;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Utility\ExtensionConfigurationUtility;
use T3Planet\RteCkeditorPack\Configuration\EditorConfigurationBuilder;
use T3Planet\RteCkeditorPack\Configuration\MentionConfigurationBuilder;
use T3Planet\RteCkeditorPack\Configuration\AIConfigurationBuilder;
use T3Planet\RteCkeditorPack\Configuration\SettingConfigurationHandler;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent;

class RteConfigurationModifier
{
    protected $cache;
    protected $pageRenderer;
    protected bool $premium;
    protected string $selectedPreset;
    protected array $invisibleFeatures;

    public function __construct(
        protected SettingConfigurationHandler $settingsConfigHandler,
        protected FeatureRepository $featureRepository,
        protected PresetRepository $presetRepository,
        protected ToolbarGroupsRepository $groupRepository,
        protected Modules $modules
    ) {
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('rte_ckeditor_config');
        $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $this->premium = false;
        $this->selectedPreset = 'default';
        $this->invisibleFeatures = ['Menubar', 'TextTransformation'];
    }

    public function __invoke(BeforePrepareConfigurationForEditorEvent $event): void
    {

        $data = $event->getData();
        if ($data) {
            $pageTs = $this->getPageTsConfiguration($data['tableName'], $data['fieldName'], $data['effectivePid'], $data['recordTypeValue']);
            $this->selectedPreset = $pageTs['fieldSpecificPreset'] ?? $pageTs['generalPreset'] ?? 'default';
            unset($pageTs['fieldSpecificPreset']);
            unset($pageTs['generalPreset']);

            $configuration = $event->getConfiguration();
            $configuration['importModules'][] = '@t3planet/RteCkeditorPack/ckeditor5-error';
            $configuration = $this->ensureCollaborationChannelConfiguration($configuration, $data);
            
            // Get preset UID from preset key
            $preset = $this->presetRepository->findByUsage($this->selectedPreset);
            
            $presetUid = $preset ? $preset->getUid() : 0;
            
            // Get enabled features for this preset
            $enabledFeatures = [];
            if ($presetUid > 0) {
                $enabledFeatures = $this->featureRepository->findEnabledByPresetUid($presetUid);
                $configuration = $this->addToolbarItems($configuration,$preset->getToolbarItems());
            }

            if ($enabledFeatures) {
                foreach ($enabledFeatures as $feature) {
                    // Add configuration based on the feature
                    $configuration = $this->processRecordConfiguration($configuration, $feature);
                }
            }
            // Add extension settings and cache the configuration
            if (!$this->premium) {
                $configuration['licenseKey'] = 'GPL';
            } else {
                $this->addExtensionSettings($configuration);
            }
            $this->pageRenderer->addInlineSetting(null, 'ckeditor5Premium', $configuration);
            $editorConfigBuilder = GeneralUtility::makeInstance(EditorConfigurationBuilder::class);
            $configuration = $editorConfigBuilder->addImportantSettings($configuration);
            $event->setConfiguration($configuration);
        }

    }

    /**
     * Processes the configuration for a given feature.
     */
    private function processRecordConfiguration(array $configuration, $feature): array
    {
        if (!$feature->isEnable()) {
            return $configuration;
        }

        $availbleItems = $configuration['toolbar']['items'] ?? [];
        $recordConfigKey = $feature->getConfigKey();

        if ($recordConfigKey) {
            $rec = $this->modules->getItemByConfigKey($recordConfigKey);
            $moduleConfiguration = isset($rec['configuration']) ? $rec['configuration'] : $rec;

            $toolBarItem = isset($moduleConfiguration['toolBarItems']) ? $moduleConfiguration['toolBarItems'] : '';
            if (isset($moduleConfiguration['is_premium']) && $moduleConfiguration['is_premium']) {

                $toolBarItemArray = GeneralUtility::trimExplode(',', $toolBarItem);
                $availbleItems = array_filter($availbleItems, 'is_string');
                $intersection = array_intersect($toolBarItemArray, $availbleItems);

                if ($toolBarItemArray && !$intersection) {
                    return $configuration;
                }
                $this->premium = true;

                if (!$this->checkPermission($recordConfigKey)) {
                    foreach ($intersection as $value) {
                        $keys = array_keys($configuration['toolbar']['items'], $value);
                        foreach ($keys as $key) {
                            unset($configuration['toolbar']['items'][$key]);
                        }
                    }
                    $configuration['toolbar']['items'] =  array_values($configuration['toolbar']['items']);
                    return $configuration;
                }
            }
            // Merge default_config if it exists (for AI Assistant and other features)
            if (isset($moduleConfiguration['default_config'])) {
                $configuration = array_merge_recursive($configuration, $moduleConfiguration['default_config']);
            }

            // Feature is already tied to the correct preset, so no need to check preset array
            if (isset($moduleConfiguration['module'])) {
                $configuration = $this->processModuleConfiguration($configuration, $moduleConfiguration, $recordConfigKey, $feature);
            }

            $fieldConfig = $feature->getFields();

            if ($fieldConfig) {
                $fieldConfigArray = json_decode($fieldConfig, true);
                $fieldValues = $fieldConfigArray[array_key_first($fieldConfigArray)];
                    
                switch ($recordConfigKey) {
                    case 'Images':
                        unset($fieldValues['exports']);
                        $configuration[array_key_first($fieldConfigArray)] = $fieldValues;
                        break;

                    case 'Style':
                    case 'Indentation':
                        array_walk_recursive($fieldConfigArray, function (&$value, $key) {
                            if ($key === 'classes') {
                                $value = array_filter(array_map('trim', explode(',', $value)));
                            }
                        });

                        if ($fieldConfigArray) {
                            foreach ($fieldConfigArray as $key => $config) {
                                $configuration[$key] = $fieldConfigArray[$key];
                            }
                        }
                        break;

                    case 'ToggleAi':
                        $aiBuilder = GeneralUtility::makeInstance(AIConfigurationBuilder::class);
                        $configuration = $aiBuilder->buildConfiguration($fieldConfigArray, $configuration);
                        break;

                    case 'HtmlSupport':
                        if (isset($fieldConfigArray['htmlSupport']) && is_array($fieldConfigArray['htmlSupport'])) {
                            $editorConfigBuilder = GeneralUtility::makeInstance(EditorConfigurationBuilder::class);
                            $configuration = $editorConfigBuilder->addHtmlSupportSettings($configuration,$fieldConfigArray['htmlSupport']);
                        }
                        break;

                    default:
                        $configuration = $this->processFieldConfiguration($fieldValues, $fieldConfigArray,
                            $configuration,
                        $recordConfigKey);
                        break;
                }
            }
        }
        
        return $configuration;
    }

    private function addToolbarItems(array $configuration, string $presetToolBarItems): array
    {
        $toolBarItems = [];
        if ($presetToolBarItems) {
            // Get toolbar items from preset table's toolbar_items column
            $toolBarItems = GeneralUtility::trimExplode(',', $presetToolBarItems, true);
        }

        if ($toolBarItems) {
            $configuration['toolbar']['items'] = [];
            foreach ($toolBarItems as $item) {
                if ($configuration && isset($configuration['toolbar']['items'])) {
                    if (strlen($item) > 1) {
                        if (!in_array($item, $configuration['toolbar']['items'])) {
                            if (str_starts_with($item, 'Group-')) {
                                $groupId = (int)GeneralUtility::trimExplode('Group-', $item)[1];
                                $group = $this->groupRepository->findByUid($groupId);
                                if ($group) {
                                    if ($group->getIcon() == 'other') {
                                        $group->setIcon($group->getCustomIcon());
                                    }
                                    $configuration['toolbar']['items'][] = [
                                        'label' => $group->getLabel(),
                                        'tooltip' => $group->getTooltip(),
                                        'icon' => $group->getIcon(),
                                        'items' => $group->getItemValues(),
                                    ];
                                }
                            } else {
                                $configuration['toolbar']['items'][] = trim($item);
                            }
                        }
                    } else {
                        $configuration['toolbar']['items'][] = trim($item);
                    }
                }
            }
            $configuration['toolbar']['shouldNotGroupWhenFull'] = true;
        }

        return $configuration;
    }

    /**
     * Adds import modules to the configuration.
     */
    private function addImportModules(array $configuration, array $moduleConfiguration): array
    {

        $modules = $moduleConfiguration['module'];

        // Handle real-time and non-real-time modules
        if ($this->hasRealTimeOrNonRealTime($modules)) {
            $realTimeModules = $modules['RealTime'] ?? [];
            $nonRealTimeModules = $modules['NonRealTime'] ?? [];

            if ($this->isEnableRealTimeCollaboration() && !empty($realTimeModules)) {

                $modules = $this->mergeAndUnsetModules($modules, $realTimeModules);
            } elseif (!empty($nonRealTimeModules)) {

                $modules = $this->mergeAndUnsetModules($modules, $nonRealTimeModules);
            }

        }

        // Add import modules to the configuration
        foreach ($modules as $import) {

            $configuration['importModules'][] = isset($import['exports'])
                ? [
                    'module' => $import['library'],
                    'exports' => GeneralUtility::trimExplode(',', $import['exports']),
                ]
                : $import['library'] ?? '';
        }

        return $configuration;
    }

    /**
    * Adds import modules to the configuration.
    */
    private function addRealTimeModules(array $configuration, array $moduleConfiguration, $record): array
    {

        $fields = json_decode($record->getFields(), true);
        $presenceList = $fields['allow']['presenceList'] ?? '0';

        foreach ($moduleConfiguration['module'] as $import) {
            if (!empty($import['exports'])) {
                $exportArray = array_diff(
                    GeneralUtility::trimExplode(',', $import['exports']),
                    $presenceList === '0' ? ['PresenceList'] : []
                );

                $configuration['importModules'][] = [
                    'module' => $import['library'],
                    'exports' => $exportArray,
                ];
            } else {
                $configuration['importModules'][] = $import['library'];
            }
        }

        return $configuration;

    }

    private function addImageModules(array $configuration, array $moduleConfiguration, $record): array
    {
        $fields = json_decode($record->getFields(), true);
        $extraPlugins = isset($fields['image']['exports']) ? $fields['image']['exports'] : [];

        foreach ($moduleConfiguration['module'] as $import) {
            if (!empty($import['exports'])) {

                $exportArray = GeneralUtility::trimExplode(',', $import['exports']);

                if ($import['library'] === '@ckeditor/ckeditor5-image' && $extraPlugins) {

                    $enablePlugins = array_keys(array_filter($extraPlugins, function ($value) {
                        return $value === '1';
                    }));

                    $exportArray = array_merge($exportArray, $enablePlugins);
                }

                $configuration['importModules'][] = [
                    'module' => $import['library'],
                    'exports' => $exportArray,
                ];
            } else {
                $configuration['importModules'][] = $import['library'];
            }
        }

        return $configuration;

    }

    /**
     * Check if RealTime or NonRealTime exists in modules.
     */
    private function hasRealTimeOrNonRealTime(array $modules): bool
    {
        return array_key_exists('RealTime', $modules) || array_key_exists('NonRealTime', $modules);
    }

    /**
     * Merge and unset RealTime or NonRealTime modules.
     */
    private function mergeAndUnsetModules(array $modules, array $specificModules): array
    {
        unset($modules['RealTime'], $modules['NonRealTime']);
        return array_merge($specificModules, $modules);
    }

    /**
     * Adds extension settings like license key to the configuration.
     */
    private function addExtensionSettings(array &$configuration): void
    {
        $licenseKey = ExtensionConfigurationUtility::get('licenseKey', '');
        if ($licenseKey) {
            $configuration['licenseKey'] = $licenseKey;
        }
        $webSocketUrl = ExtensionConfigurationUtility::get('webSocketUrl', '');
        if ($webSocketUrl) {
            $configuration['cloudServices']['webSocketUrl'] = $webSocketUrl;
        }
    }

    /**
     * Check Collaboration Mode
     */
    private function isEnableRealTimeCollaboration(): bool
    {
        // Get preset UID from preset key
        $preset = $this->presetRepository->findByPresetKey($this->selectedPreset);
        if (!$preset) {
            return false;
        }
        
        $presetUid = $preset->getUid();
        $feature = $this->featureRepository->findByPresetUidAndConfigKey($presetUid, 'RealTimeCollaboration');
        
        if (!$feature || !$feature->isEnable()) {
            return false;
        }

        return true;
    }

    /**
     * Caches the configuration for a specified duration.
     */
    private function cacheConfiguration(string $cacheIdentifier, array $configuration): void
    {
        $this->cache->set($cacheIdentifier, $configuration, [], 3600); // Cache for 1 hour
    }

    /**
     * Load PageTS configuration for the RTE
     *
     * Return RTE section of page TS, taking into account overloading via table, field and record type
     *
     * @param string $table The table the field is in
     * @param string $field Field name
     * @param int $pid Real page id
     * @param string $recordType Record type value
     */
    protected function getPageTsConfiguration(string $table, string $field, int $pid, string $recordType): array
    {
        // Load page TSconfig configuration
        $fullPageTsConfig = $this->getRtePageTsConfigOfPid($pid);
        $defaultPageTsConfigOverrides = $fullPageTsConfig['default.'] ?? null;

        $defaultPageTsConfigOverrides['generalPreset'] = $fullPageTsConfig['default.']['preset'] ?? null;

        $fieldSpecificPageTsConfigOverrides = $fullPageTsConfig['config.'][$table . '.'][$field . '.'] ?? null;
        unset($fullPageTsConfig['default.'], $fullPageTsConfig['config.']);

        // First use RTE.*
        $rtePageTsConfiguration = $fullPageTsConfig;

        // Then overload with RTE.default.*
        if (is_array($defaultPageTsConfigOverrides)) {
            ArrayUtility::mergeRecursiveWithOverrule($rtePageTsConfiguration, $defaultPageTsConfigOverrides);
        }

        $rtePageTsConfiguration['fieldSpecificPreset'] = $fieldSpecificPageTsConfigOverrides['types.'][$recordType . '.']['preset'] ??
            $fieldSpecificPageTsConfigOverrides['preset'] ?? null;

        // Then overload with RTE.config.tt_content.bodytext
        if (is_array($fieldSpecificPageTsConfigOverrides)) {
            $fieldSpecificPageTsConfigOverridesWithoutType = $fieldSpecificPageTsConfigOverrides;
            unset($fieldSpecificPageTsConfigOverridesWithoutType['types.']);
            ArrayUtility::mergeRecursiveWithOverrule($rtePageTsConfiguration, $fieldSpecificPageTsConfigOverridesWithoutType);

            // Then overload with RTE.config.tt_content.bodytext.types.textmedia
            if (
                $recordType
                && isset($fieldSpecificPageTsConfigOverrides['types.'][$recordType . '.'])
                && is_array($fieldSpecificPageTsConfigOverrides['types.'][$recordType . '.'])
            ) {
                ArrayUtility::mergeRecursiveWithOverrule(
                    $rtePageTsConfiguration,
                    $fieldSpecificPageTsConfigOverrides['types.'][$recordType . '.']
                );
            }
        }

        unset($rtePageTsConfiguration['preset']);

        return $rtePageTsConfiguration;
    }

    private function processModuleConfiguration($configuration, $moduleConfiguration, $recordConfigKey, $record): array
    {
        if ($recordConfigKey === 'RealTimeCollaboration') {
            $this->premium = true;
            $configuration = $this->addRealTimeModules($configuration, $moduleConfiguration, $record);
        } elseif ($recordConfigKey === 'Images') {
            $configuration = $this->addImageModules($configuration, $moduleConfiguration, $record);
        } else {
            if (isset($moduleConfiguration['hidden_premium']) && $moduleConfiguration['hidden_premium']) {
                if ($this->checkPermission($recordConfigKey)) {
                    $this->premium = true;
                    $configuration = $this->addImportModules($configuration, $moduleConfiguration);
                }
            } else {
                $configuration = $this->addImportModules($configuration, $moduleConfiguration);
            }
        }
        return $configuration;

    }

    private function processFieldConfiguration($fieldValues, array $fieldConfigArray, array $configuration, string $recordConfigKey): array
    {
        if ($recordConfigKey === 'Mention') {
            $mentionBuilder = GeneralUtility::makeInstance(MentionConfigurationBuilder::class);
            $configuration['mention'] = $mentionBuilder->buildConfiguration($fieldConfigArray);
            return $configuration;
        }

        $mainKey = array_key_first($fieldConfigArray);

        if ($mainKey === null) {
            return $configuration;
        }

        if (is_string($fieldValues)) {
            $configuration[$mainKey] = GeneralUtility::trimExplode(',', $fieldValues, true);

        } elseif (count($fieldConfigArray) > 1) {
            $multiFieldConfig = [];
            // For the field type MULTIFIELD, example Font
            foreach ($fieldConfigArray as $key => $fields) {

                foreach ($fields as $fieldKey => $field) {
                    if (is_string($field) && strpos($field, ',')) {
                        $multiFieldConfig[$key][$fieldKey] = GeneralUtility::trimExplode(',', $field);

                    } else {
                        $multiFieldConfig[$key][$fieldKey] = $field;
                    }
                }
            }
            $configuration = array_merge($configuration, $multiFieldConfig);

        } else {
            $configuration[$mainKey] = $fieldConfigArray[$mainKey];
        }
        return $configuration;
    }


    /**
    * Return RTE section of page TS
    *
    * @param int $pid Page ts of given pid
    * @return array RTE section of pageTs of given pid
    */
    protected function getRtePageTsConfigOfPid(int $pid): array
    {
        return BackendUtility::getPagesTSconfig($pid)['RTE.'] ?? [];
    }

    /**
     * Return RTE permission status
     *
     * @param string $module
     * @return bool RTE permission
     */
    protected function checkPermission(string $module): bool
    {
        return $GLOBALS['BE_USER']->check('custom_options', 'rte_editor' . ':' . $module);
    }


    private function ensureCollaborationChannelConfiguration(array $configuration, array $data): array
    {
        $channelId = $configuration['collaboration']['channelId'] ?? null;
        if (!$channelId) {
            $channelId = ChannelIdUtility::buildChannelIdFromData($data);
            $configuration['collaboration']['channelId'] = $channelId;
        }

        if (!isset($configuration['cloudServices']['documentId'])) {
            $configuration['cloudServices']['documentId'] = $channelId;
        }

        return $configuration;
    }

}
