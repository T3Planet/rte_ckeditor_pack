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
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Utility\ExtensionConfigurationUtility;
use T3Planet\RteCkeditorPack\Configuration\EditorConfigurationBuilder;
use T3Planet\RteCkeditorPack\Configuration\MentionConfigurationBuilder;
use T3Planet\RteCkeditorPack\Configuration\AIConfigurationBuilder;
use T3Planet\RteCkeditorPack\Configuration\SettingConfigurationHandler;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use T3Planet\RteCkeditorPack\Utility\ProcessingConfigurationUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ApplicationType;
use Psr\Http\Message\ServerRequestInterface;
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
            $configuration = $event->getConfiguration();
            $context = $this->resolveEditorContext($data, $configuration);
            $pageTs = $this->getPageTsConfiguration(
                $context['table'],
                $context['field'],
                $context['pid'],
                $context['recordType'],
            );
            $this->selectedPreset = $pageTs['fieldSpecificPreset']
                ?? $pageTs['generalPreset']
                ?? ($configuration[ProcessingConfigurationUtility::RICH_TEXT_PRESET_METADATA_KEY] ?? null)
                ?? 'default';
            unset($pageTs['fieldSpecificPreset']);
            unset($pageTs['generalPreset']);
            $configuration['importModules'][] = '@t3planet/RteCkeditorPack/ckeditor5-error';
            $configuration['importModules'][] = '@t3planet/RteCkeditorPack/export-download-adapter.js';
            $collaborationContext = $this->buildCollaborationContext($data, $context);
            if ($this->hasEnabledCollaborationChannelFeature()) {
                $configuration = $this->ensureCollaborationChannelConfiguration($configuration, $collaborationContext);
            }
            // Canonical FormEngine/VE field id for Non-RTC Comments / Track Changes storage.
            $configuration = $this->ensureCollaborationRteIdConfiguration($configuration, $collaborationContext);
            if ($this->needsCollaborationUserIdentity()) {
                $configuration = $this->ensureCollaborationUserConfiguration($configuration);
            }
            
            // Get preset UID from preset key
            $preset = $this->presetRepository->findByUsage($this->selectedPreset);
            
            $presetUid = $preset ? $preset->getUid() : 0;
            
            // Get enabled features for this preset
            $enabledFeatures = [];
            if ($presetUid > 0) {
                $enabledFeatures = $this->featureRepository->findEnabledByPresetUid($presetUid);
                $configuration = $this->addToolbarItems($configuration,$preset->getToolbarItems());
                if ($this->isCollaborationFeatureEnabled('RestrictedEditingMode')) {
                    $configuration = $this->normalizeRestrictedEditingToolbarPlaceholder($configuration);
                }
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
            if ($this->isBackendRequest()) {
                $this->pageRenderer->addInlineSetting(null, 'ckeditor5Premium', $configuration);
            }
            $editorConfigBuilder = GeneralUtility::makeInstance(EditorConfigurationBuilder::class);
            $configuration = $editorConfigBuilder->addImportantSettings($configuration);
            $configuration = $this->normalizeImportModules($configuration);
            unset($configuration[ProcessingConfigurationUtility::RICH_TEXT_PRESET_METADATA_KEY]);
            unset($configuration[ProcessingConfigurationUtility::RICH_TEXT_EDITOR_CONTEXT_KEY]);
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

        // Restricted editing and Real-time Collaboration cannot share one editor schema.
        // Prefer Restricted editing when both are enabled so its toolbar/plugin actually load.
        if ($recordConfigKey === 'RealTimeCollaboration' && $this->isCollaborationFeatureEnabled('RestrictedEditingMode')) {
            return $configuration;
        }

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
                            $configuration = $editorConfigBuilder->addHtmlSupportSettings(
                                $configuration,
                                $fieldConfigArray['htmlSupport']
                            );
                        }
                        break;

                    default:
                        $configuration = $this->processFieldConfiguration($fieldValues, $fieldConfigArray,
                            $configuration,
                        $recordConfigKey);
                        break;
                }
            }

            if ($recordConfigKey === 'RestrictedEditingMode') {
                $configuration = $this->ensureRestrictedEditingDefaults($configuration);
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

        // Source editing cannot run with RTC (also remove Restricted Editing exports if present).
        $removePlugins = $configuration['removePlugins'] ?? [];
        if (!is_array($removePlugins)) {
            $removePlugins = GeneralUtility::trimExplode(',', (string)$removePlugins, true);
        }
        foreach (['SourceEditing', 'SourceEditingEnhanced'] as $pluginName) {
            if (!in_array($pluginName, $removePlugins, true)) {
                $removePlugins[] = $pluginName;
            }
        }
        $configuration['removePlugins'] = $removePlugins;

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
     * Check Collaboration Mode.
     * Restricted editing changes the editor schema and cannot load with RTC plugins.
     * When both Pack features are enabled, prefer Restricted editing.
     */
    private function isEnableRealTimeCollaboration(): bool
    {
        if ($this->isCollaborationFeatureEnabled('RestrictedEditingMode')) {
            return false;
        }

        return $this->isCollaborationFeatureEnabled('RealTimeCollaboration');
    }

    /**
     * Whether a stable collaboration.channelId is required for the active preset.
     *
     * Channel IDs are used by realtime collaboration stores and by CKEditor AI chat
     * history (AI throws ai-chat-missing-channel-id without one).
     */
    private function hasEnabledCollaborationChannelFeature(): bool
    {
        foreach (['RealTimeCollaboration', 'Comments', 'RevisionHistory', 'TrackChanges', 'ToggleAi'] as $configKey) {
            if ($this->isCollaborationFeatureEnabled($configKey)) {
                return true;
            }
        }

        return false;
    }

    private function isCollaborationFeatureEnabled(string $configKey): bool
    {
        $preset = $this->resolveActivePreset();
        if (!$preset) {
            return false;
        }

        $feature = $this->featureRepository->findByPresetUidAndConfigKey($preset->getUid(), $configKey);

        return $feature !== null && $feature->isEnable();
    }

    private function resolveActivePreset(): ?Preset
    {
        return $this->presetRepository->findByUsage($this->selectedPreset)
            ?? $this->presetRepository->findByPresetKey($this->selectedPreset);
    }

    private function isBackendRequest(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface
            && ApplicationType::fromRequest($request)->isBackend();
    }

    /**
     * Caches the configuration for a specified duration.
     */
    private function cacheConfiguration(string $cacheIdentifier, array $configuration): void
    {
        $this->cache->set($cacheIdentifier, $configuration, [], 3600); // Cache for 1 hour
    }

    /**
     * Visual Editor expects importModules as arrays with a module key (see TextViewHelper).
     */
    private function normalizeImportModules(array $configuration): array
    {
        if (!isset($configuration['importModules']) || !is_array($configuration['importModules'])) {
            return $configuration;
        }

        $normalized = [];
        foreach ($configuration['importModules'] as $importModule) {
            if (is_string($importModule)) {
                if ($importModule !== '') {
                    $normalized[] = ['module' => $importModule, 'exports' => ['default']];
                }
                continue;
            }
            if (is_array($importModule) && isset($importModule['module']) && is_string($importModule['module'])) {
                $normalized[] = $importModule;
            }
        }

        $configuration['importModules'] = $normalized;
        return $configuration;
    }

    /**
     * Normalize editor context from FormEngine or Visual Editor event data.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $configuration
     * @return array{table: string, field: string, pid: int, recordType: string, recordUid: int}
     */
    private function resolveEditorContext(array $data, array $configuration = []): array
    {
        $editorContext = $configuration[ProcessingConfigurationUtility::RICH_TEXT_EDITOR_CONTEXT_KEY] ?? [];
        if (!is_array($editorContext)) {
            $editorContext = [];
        }

        $recordUid = (int)(
            $data['recordUid']
            ?? $data['databaseRow']['uid']
            ?? $data['uid']
            ?? 0
        );

        return [
            'table' => (string)($data['tableName'] ?? $data['table'] ?? $editorContext['table'] ?? ''),
            'field' => (string)($data['fieldName'] ?? $data['field'] ?? $editorContext['field'] ?? ''),
            'pid' => (int)($data['effectivePid'] ?? $data['pid'] ?? $data['databaseRow']['pid'] ?? $editorContext['pid'] ?? 0),
            'recordType' => (string)($data['recordTypeValue'] ?? $data['CType'] ?? $data['databaseRow']['CType'] ?? $editorContext['recordType'] ?? ''),
            'recordUid' => $recordUid,
        ];
    }

    /**
     * Build normalized collaboration context shared by FormEngine and Visual Editor.
     *
     * @param array<string, mixed> $data
     * @param array{table: string, field: string, pid: int, recordType: string, recordUid: int} $context
     * @return array<string, mixed>
     */
    private function buildCollaborationContext(array $data, array $context): array
    {
        $databaseRow = $data['databaseRow'] ?? null;
        if (!is_array($databaseRow) && isset($data['uid'])) {
            $databaseRow = $data;
        }

        return array_merge($data, [
            'tableName' => $context['table'],
            'fieldName' => $context['field'],
            'effectivePid' => $context['pid'],
            'recordTypeValue' => $context['recordType'],
            'recordUid' => $context['recordUid'],
            'databaseRow' => is_array($databaseRow) ? $databaseRow : ($data['databaseRow'] ?? []),
        ]);
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
        } elseif ($recordConfigKey === 'RestrictedEditingMode') {
            $configuration = $this->addRestrictedEditingModules($configuration, $moduleConfiguration, $record);
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

        if ($recordConfigKey === 'MathEquations') {
            $configuration = $this->ensureMathEquationsDefaults($configuration);
        }

        return $configuration;
    }

    /**
     * Load StandardEditingMode or RestrictedEditingMode from the feature Mode dropdown.
     * CKEditor cannot load both plugins in the same editor instance.
     *
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $moduleConfiguration
     * @param object $record
     * @return array<string, mixed>
     */
    private function addRestrictedEditingModules(array $configuration, array $moduleConfiguration, $record): array
    {
        $mode = 'standard';
        $fieldsJson = method_exists($record, 'getFields') ? $record->getFields() : null;
        if (is_string($fieldsJson) && $fieldsJson !== '') {
            $fields = json_decode($fieldsJson, true);
            if (is_array($fields)) {
                $modeRaw = $fields['restrictedEditing']['mode'] ?? 'standard';
                // SELECT may initially store the options map; only a string value is a real choice.
                $mode = is_string($modeRaw) && $modeRaw !== '' ? $modeRaw : 'standard';
            }
        }

        $exports = $mode === 'restricted' ? 'RestrictedEditingMode' : 'StandardEditingMode';
        $moduleConfiguration['module'] = [
            [
                'library' => '@ckeditor/ckeditor5-restricted-editing',
                'exports' => $exports,
            ],
        ];

        $configuration = $this->resolveRestrictedEditingToolbarItem($configuration, $mode);

        return $this->addImportModules($configuration, $moduleConfiguration);
    }

    /**
     * Collapse any legacy restrictedEditing* toolbar variants to the single Pack placeholder.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function normalizeRestrictedEditingToolbarPlaceholder(array $configuration): array
    {
        return $this->rewriteRestrictedEditingToolbarItems($configuration, 'restrictedEditing');
    }

    /**
     * Map the Pack placeholder to the CKEditor toolbar component for the selected mode.
     * Standard → mark regions; Restricted → navigate regions.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function resolveRestrictedEditingToolbarItem(array $configuration, string $mode): array
    {
        $ckItem = $mode === 'restricted' ? 'restrictedEditing' : 'restrictedEditingException';

        return $this->rewriteRestrictedEditingToolbarItems($configuration, $ckItem);
    }

    /**
     * Replace all restrictedEditing* string toolbar entries with a single target item.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function rewriteRestrictedEditingToolbarItems(array $configuration, string $targetItem): array
    {
        $items = $configuration['toolbar']['items'] ?? null;
        if (!is_array($items)) {
            return $configuration;
        }

        $replaced = false;
        $newItems = [];
        foreach ($items as $item) {
            if (is_string($item) && str_starts_with($item, 'restrictedEditing')) {
                if (!$replaced) {
                    $newItems[] = $targetItem;
                    $replaced = true;
                }
                continue;
            }
            $newItems[] = $item;
        }

        $configuration['toolbar']['items'] = $newItems;

        return $configuration;
    }

    /**
     * Strip pack-only "mode" from editor config (not a CKEditor RestrictedEditing option).
     * allowedCommands / allowedAttributes use CKEditor defaults unless set via YAML/TS.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function ensureRestrictedEditingDefaults(array $configuration): array
    {
        if (!isset($configuration['restrictedEditing']) || !is_array($configuration['restrictedEditing'])) {
            return $configuration;
        }

        unset($configuration['restrictedEditing']['mode']);

        if ($configuration['restrictedEditing'] === []) {
            unset($configuration['restrictedEditing']);
        }

        return $configuration;
    }

    /**
     * Ensure MathType saves/renders reliably in TYPO3 by defaulting to image mode
     * while keeping MathML service defaults available.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function ensureMathEquationsDefaults(array $configuration): array
    {
        if (!isset($configuration['mathTypeParameters']) || !is_array($configuration['mathTypeParameters'])) {
            $configuration['mathTypeParameters'] = [];
        }
        if (!isset($configuration['mathTypeParameters']['editorParameters']) || !is_array($configuration['mathTypeParameters']['editorParameters'])) {
            $configuration['mathTypeParameters']['editorParameters'] = [];
        }
        if (!isset($configuration['mathTypeParameters']['editorParameters']['language'])) {
            $configuration['mathTypeParameters']['editorParameters']['language'] = 'en';
        }
        // Prefer image output for TYPO3 FE; MathML remains editable via data-mathml.
        if (!isset($configuration['mathTypeParameters']['editorParameters']['wiriseditorsavemode'])) {
            $configuration['mathTypeParameters']['editorParameters']['wiriseditorsavemode'] = 'image';
        }
        if (!isset($configuration['mathTypeParameters']['serviceProviderProperties']) || !is_array($configuration['mathTypeParameters']['serviceProviderProperties'])) {
            $configuration['mathTypeParameters']['serviceProviderProperties'] = [
                'URI' => 'https://www.wiris.net/demo/plugins/app',
                'server' => 'https://www.wiris.net',
            ];
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
        $hasChannel = isset($configuration['collaboration']['channelId'])
            && is_string($configuration['collaboration']['channelId'])
            && $configuration['collaboration']['channelId'] !== '';
        $hasDocument = isset($configuration['cloudServices']['documentId'])
            && is_string($configuration['cloudServices']['documentId'])
            && $configuration['cloudServices']['documentId'] !== '';

        // Preserve explicitly configured IDs (tests / custom YAML).
        if ($hasChannel && $hasDocument) {
            return $configuration;
        }

        $baseChannelId = $hasChannel
            ? $configuration['collaboration']['channelId']
            : ChannelIdUtility::buildChannelIdFromData($data);

        // Fingerprint forces a fresh Cloud document when the editor schema changes
        // (e.g. Restricted editing / Comments). Prevents:
        // realtimecollaborationclient-init-connection-failed /
        // mapping-model-position-view-parent-not-found against stale RTC docs.
        $fingerprint = $this->buildCollaborationSchemaFingerprint();
        $channelId = 'ckdoc-' . substr(hash('sha1', $baseChannelId . '|' . $fingerprint), 0, 40);

        $configuration['collaboration']['channelId'] = $channelId;
        if (!$hasDocument) {
            $configuration['cloudServices']['documentId'] = $channelId;
        }

        return $configuration;
    }

    /**
     * Schema-affecting features that must invalidate RTC cloud document identity.
     */
    private function buildCollaborationSchemaFingerprint(): string
    {
        $parts = ['rtc-map-v1'];
        foreach (
            [
                'RestrictedEditingMode',
                'MathEquations',
                'TrackChanges',
                'Comments',
                'RevisionHistory',
                'RealTimeCollaboration',
            ] as $configKey
        ) {
            if ($this->isCollaborationFeatureEnabled($configKey)) {
                $parts[] = $configKey;
            }
        }

        return implode('+', $parts);
    }

    /**
     * Shared storage key for Non-RTC Comments (FormEngine + Visual Editor).
     * Must match textarea name: data[table][uid][field].
     *
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function ensureCollaborationRteIdConfiguration(array $configuration, array $data): array
    {
        $existing = $configuration['collaboration']['rteId'] ?? null;
        if (is_string($existing) && $existing !== '' && str_starts_with($existing, 'data[')) {
            return $configuration;
        }

        $table = (string)($data['tableName'] ?? $data['table'] ?? '');
        $field = (string)($data['fieldName'] ?? $data['field'] ?? '');
        $uid = (int)($data['recordUid']
            ?? $data['databaseRow']['uid']
            ?? $data['uid']
            ?? 0);

        if ($table === '' || $field === '' || $uid <= 0) {
            return $configuration;
        }

        $configuration['collaboration']['rteId'] = sprintf('data[%s][%d][%s]', $table, $uid, $field);

        return $configuration;
    }

    /**
     * Whether Comments / Track Changes / Revision History / RTC need a local user identity.
     *
     * Non-RTC Comments require Users.me; without it CKEditor throws unexpected-error
     * (can't access property "id", me is null) when adding a comment marker.
     */
    private function needsCollaborationUserIdentity(): bool
    {
        foreach (['RealTimeCollaboration', 'Comments', 'RevisionHistory', 'TrackChanges'] as $configKey) {
            if ($this->isCollaborationFeatureEnabled($configKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Provide stable collaboration user identity (required for Non-RTC Comments/Track Changes
     * and for presence list, especially in Visual Editor).
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function ensureCollaborationUserConfiguration(array $configuration): array
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return $configuration;
        }

        $userId = (int)($backendUser->user['uid'] ?? 0);
        if ($userId <= 0) {
            return $configuration;
        }

        $userName = trim((string)($backendUser->user['realName'] ?? ''));
        if ($userName === '') {
            $userName = (string)($backendUser->user['username'] ?? ('User ' . $userId));
        }

        $collaborationUser = [
            'id' => (string)$userId,
            'name' => $userName,
        ];

        $account = BackendUtility::getRecord('be_users', $userId);
        if ($account && !empty($account['avatar'])) {
            $fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
            $fileObjects = $fileRepository->findByRelation('be_users', 'avatar', $userId);
            if ($fileObjects && isset($fileObjects[0]) && $fileObjects[0]->getPublicUrl()) {
                $collaborationUser['avatar'] = (string)$fileObjects[0]->getPublicUrl();
            }
        }

        $configuration['collaboration']['userId'] = (string)$userId;
        $configuration['collaboration']['userName'] = $userName;
        $configuration['collaboration']['users'] = [$collaborationUser];

        return $configuration;
    }

}
