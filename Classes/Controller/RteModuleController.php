<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use T3Planet\RteCkeditorPack\DataProvider\BaseToolBar;
use T3Planet\RteCkeditorPack\DataProvider\Modules;
use T3Planet\RteCkeditorPack\Domain\Model\Configuration;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Model\ToolbarGroups;
use T3Planet\RteCkeditorPack\Domain\Repository\ConfigurationRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use T3Planet\RteCkeditorPack\Service\TokenUrlValidator;
use T3Planet\RteCkeditorPack\Utility\ExtensionConfigurationUtility;
use T3Planet\RteCkeditorPack\Utility\FlashUtility;
use T3Planet\RteCkeditorPack\Utility\UriBuilderUtility;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class RteModuleController extends ActionController
{
    protected ModuleTemplate $moduleTemplate;

    protected UriBuilderUtility $urlBuilder;

    protected FlashUtility $notification;

    protected ConfigurationRepository $configurationRepository;

    protected FeatureRepository $featureRepository;

    protected PresetRepository $presetRepository;

    protected $dependencyRepository;

    protected $modulesRepository;

    protected ToolbarGroupsRepository $groupsRepository;

    protected PersistenceManager $persistenceManager;

    protected $cache;

    protected TokenUrlValidator $validator;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly BaseToolBar $baseToolBar,
        ConfigurationRepository $configurationRepository,
        FeatureRepository $featureRepository,
        PresetRepository $presetRepository,
        PersistenceManager $persistenceManager,
        ToolbarGroupsRepository $groupsRepository,
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->featureRepository = $featureRepository;
        $this->presetRepository = $presetRepository;
        $this->persistenceManager = $persistenceManager;
        $this->groupsRepository = $groupsRepository;
        $this->urlBuilder = GeneralUtility::makeInstance(UriBuilderUtility::class);
        $this->notification = GeneralUtility::makeInstance(FlashUtility::class);
        $this->validator = GeneralUtility::makeInstance(TokenUrlValidator::class);
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('rte_ckeditor_config');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_be.xlf');
    }

    public function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
    }

    public function mainAction(): ResponseInterface
    {
        $availableModules = GeneralUtility::makeInstance(Modules::class)->getGroupedModulesByTabs();
        $currentModule = $this->request->getAttribute('moduleData')->getModuleIdentifier();

        if ($currentModule) {
            $currentModule = str_replace('ckeditor_', '', $currentModule);
        }
        if (isset($this->request->getQueryParams()['current_module'])) {
            $currentModule = $this->request->getQueryParams()['current_module'] ?? '';
            $notification = $this->request->getQueryParams()['notification'] ?? [];
            $this->notification->addFlashNotification($notification);
        }

        $presetsData = $this->baseToolBar->findAvailablePresets();
        $corePresets = $presetsData['core'] ?? [];
        $customPresets = $presetsData['custom'] ?? [];
        
        // Merge for backward compatibility and easier access
        $availablePresets = array_merge($corePresets, $customPresets);
        
        // Get first preset key and extract UID
        $firstPresetKey = array_key_first($availablePresets);
        $activePresetUid = $availablePresets[$firstPresetKey]['uid'] ?? 0;
        $activePresetKey = $firstPresetKey;
        
        $attributes = $this->request->getParsedBody();

        if ($attributes) {
            if (array_key_exists('licenseKey', $attributes)) {
                $this->generalSettings($attributes);
                $currentModule = 'settings';
            } elseif ($attributes && array_key_exists('position', $attributes) || array_key_exists('modules', $attributes)) {
                $this->updateModules($attributes);
                $currentModule = isset($attributes['active_tab']) ? $attributes['active_tab'] : 'features';
                if (isset($attributes['activePreset'])) {
                    // Handle both UID (int) and preset key (string) from attributes
                    $presetValue = $attributes['activePreset'];
                    if (is_numeric($presetValue)) {
                        // It's a UID, find the preset key
                        $activePresetUid = (int)$presetValue;
                        foreach ($availablePresets as $key => $presetData) {
                            if (isset($presetData['uid']) && $presetData['uid'] === $activePresetUid) {
                                $activePresetKey = $key;
                                break;
                            }
                        }
                    } else {
                        // It's a preset key, get the UID
                        $activePresetKey = $presetValue;
                        $activePresetUid = $availablePresets[$presetValue]['uid'] ?? 0;
                    }
                }
            }
            if (array_key_exists('activePresets', $attributes)) {
                $presetValue = $attributes['activePresets'];
                if (is_numeric($presetValue)) {
                    // It's a UID, find the preset key
                    $activePresetUid = (int)$presetValue;
                    foreach ($availablePresets as $key => $presetData) {
                        if (isset($presetData['uid']) && $presetData['uid'] === $activePresetUid) {
                            $activePresetKey = $key;
                            break;
                        }
                    }
                } else {
                    // It's a preset key, get the UID
                    $activePresetKey = $presetValue;
                    $activePresetUid = $availablePresets[$presetValue]['uid'] ?? 0;
                }
                $currentModule = 'features';
            }
            $this->cache->flush();
        }

        $extSettings = ExtensionConfigurationUtility::getAll();
        $settingsArray = GeneralUtility::makeInstance(Modules::class)->getSettings();
        
        // Group presets by is_custom for optgroups
        $groupedPresets = [
            'yaml' => $corePresets, // YAML-based presets (TYPO3 Core)
            'custom' => $customPresets, // Custom presets
        ];

        $this->moduleTemplate->assignMultiple([
            'availableModules' => $availableModules,
            'currentModule' => $currentModule,
            'settingFields' => $settingsArray,
            'toolBarConfiguration' => $this->baseToolBar->findEnableToolbarItems($activePresetUid),
            'availablePresets' => $availablePresets, // Keep for backward compatibility
            'groupedPresets' => $groupedPresets, // New grouped structure
            'activePreset' => $activePresetUid,
            'extSettings' => $extSettings,
        ]);
        
        $this->preparePageRenderer();
        return $this->moduleTemplate->renderResponse('RteModule/Index');
    }

    public function getCkeditorSettings(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getQueryParams();
        $assign = [];
        $moduleKey = $data['moduleKey'] ?? '';
        $selectedPresetUid = isset($data['selectedPreset']) && is_numeric($data['selectedPreset']) ? (int)$data['selectedPreset'] : 0;
        
        if (isset($data['additionalParams'])) {
            $configuration = $data['additionalParams'] ? json_decode($data['additionalParams'], true) : '';
            $assign['additionalParams'] = $data['additionalParams'] ?? '';
            if (isset($configuration['config_key'])) {
                $moduleKey = $configuration['config_key'];
            }
            $assign['configuration'] = $configuration;
        }
        
        if ($moduleKey && $selectedPresetUid > 0) {
            // Get preset by UID (do not create if not exists)
            $preset = $this->presetRepository->findByUid($selectedPresetUid);
            if ($preset) {
                $feature = $this->featureRepository->findByPresetUidAndConfigKey($selectedPresetUid, $moduleKey);
                if ($feature) {
                    $assign['record'] = json_decode($feature->getFields(), true) ?: [];
                    $assign['record']['enable'] = $feature->isEnable();
                    $assign['record']['configKey'] = $feature->getConfigKey();
                }
            }
            
            $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($moduleKey);
            $assign['fields'] = $moduleConfiguration['fields'] ?? [];
        }

        if ($selectedPresetUid > 0) {
            $assign['selectedPreset'] = $selectedPresetUid;
        }

        $this->moduleTemplate = $this->initializeModuleTemplate($request);

        if (isset($data['notification'])) {
            foreach ($data['notification'] as $notification) {
                $this->notification->addFlashNotification($notification);
            }
        }

        $this->moduleTemplate->assignMultiple($assign);

        return $this->moduleTemplate->renderResponse('RteModule/Settings');
    }

    public function getCkeditorComingSoon(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getQueryParams();
        $arguments = $data['additionalParams'] ?? '';

        $this->moduleTemplate = $this->initializeModuleTemplate($request);
        if ($arguments) {
            $arguments = json_decode($arguments, true);
            $assign = [
                'templateFile' => $arguments['templateName'] ?? '',
                'module' => $arguments['Module'] ?? '',
                'additionalParams' => $data['additionalParams'],
            ];

            $this->moduleTemplate->assignMultiple($assign);
        }
        return $this->moduleTemplate->renderResponse('RteModule/ComingSoon');
    }

    public function saveSettings(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $configKey = $data['configKey'] ?? '';
        $enable = $data['enable'] === '1' ? true : false;
        $notification = [];
        $presetUid = isset($data['preset']) && is_numeric($data['preset']) ? (int)$data['preset'] : 0;
        
        try {
            if ($configKey && $presetUid > 0) {
                $preset = $this->presetRepository->findByUid($presetUid);

                if (!$preset) {
                    throw new \Exception('Preset not found');
                }

                if ($configKey === 'AIAssistant') {
                    if (isset($data['config']['ai']['openAI']['apiUrl']) && $data['config']['ai']['openAI']['apiUrl'] === '') {
                        unset($data['config']['ai']['openAI']['apiUrl']);
                    }
                }
                $fieldData = isset($data['config']) ? json_encode($data['config']) : '';

                // Get feature from new feature table
                $feature = $this->featureRepository->findByPresetUidAndConfigKey($presetUid, $configKey);
                
                if (!$feature) {
                    $feature = GeneralUtility::makeInstance(Feature::class);
                    $feature->setPresetUid($presetUid);
                    $feature->setConfigKey($configKey);
                    $this->featureRepository->add($feature);
                    $this->persistenceManager->persistAll();
                }

                if ($configKey === 'ImportWord'  || $configKey === 'ExportPdf' || $configKey === 'ExportWord') {
                    $configArray = &$data['config'][array_key_first($data['config'])] ?? [];
                    if ($configArray) {
                        $configArray = $this->manageToken($configArray);
                        $mainKey = array_key_first($data['config']);
                        $this->normalizeStylesheetsConfiguration($data['config'], $mainKey);
                        $fieldData = isset($data['config']) ? json_encode($data['config']) : '';
                    }
                }

                if ($configKey === 'Indentation' && $data['config']) {
                    foreach (['indentBlock', 'outdentBlock'] as $key) {
                        if (isset($data['config'][$key])) {
                            $data['config'][$key] = $this->manageIndent($data['config'][$key], $key);
                        }
                    }
                    $fieldData = json_encode($data['config']);
                }

                if ($configKey === 'RealTimeCollaboration' && $enable) {
                    $fieldConfiguration = [];

                    $webSocketUrl = ExtensionConfigurationUtility::get('webSocketUrl', '');
                    if ($webSocketUrl) {
                        $fieldConfiguration['cloudServices'] = ['webSocketUrl' => $webSocketUrl];
                    }

                    if (isset($data['config']['allow']['presenceList']) && $data['config']['allow']['presenceList'] === '1') {
                        $fieldConfiguration['presenceList'] = ['container' => null];
                    }

                    $fieldConfiguration['removePlugins'] = ['SourceEditing'];

                    // Merge existing field data with new configuration
                    $existingFieldData = json_decode($fieldData, true) ?: [];
                    $fieldData = json_encode(array_merge($existingFieldData, $fieldConfiguration));
                    $notification[] = [
                        'title' => 'ckeditorKit.plugin.realtime_collaboration',
                        'message' => 'ckeditorKit.plugin.realtime_collaboration.message',
                        'severity' => 1,
                    ];
                }

                // Update feature in new table
                $feature->setEnable($enable);
                $feature->setFields($fieldData);
                $this->featureRepository->update($feature);
                $this->cache->flush();
                $this->persistenceManager->persistAll();
            }

            // Remove Item from toolBar
            if (!$enable) {
                $this->baseToolBar->updateToolBar($configKey);
            }
            $notification[] = [
                'title' => 'ckeditorKit.operation.success',
                'message' => 'ckeditorKit.plugin.setting_save.success.message',
                'severity' => 0,
            ];
        } catch (\Exception $e) {
            $notification[] = [
                'title' => 'ckeditorKit.operation.error',
                'message' => 'ckeditorKit.plugin.setting_save.error.message',
                'severity' => 2,
            ];
        }

        return new JsonResponse([
            'notifications' => $notification,
        ]);
    }

    private function updateModules(array $data): bool
    {
        $toolBarItems = $data['position'] ?? '';
        $selectedPresetUid = isset($data['activePreset']) && is_numeric($data['activePreset']) ? (int)$data['activePreset'] : 0;
        
        if ($toolBarItems && $selectedPresetUid > 0) {
            // Update toolbar items in preset table
            $preset = $this->presetRepository->findByUid($selectedPresetUid);
            if ($preset) {
                $preset->setToolbarItems($toolBarItems);
                $this->presetRepository->update($preset);
                $this->persistenceManager->persistAll();
            }
            $this->cache->flush();
        }

        $updatedModules = $data['modules'] ?? [];
        $enableModules = isset($data['enable']) && $data['enable'] ? GeneralUtility::trimExplode(',', $data['enable']) : [];
        $disabledModules = isset($data['disabled']) && $data['disabled'] ? GeneralUtility::trimExplode(',', $data['disabled']) : [];

        if ($enableModules) {
            foreach ($enableModules as $module) {
                if ($module === 'RealTimeCollaboration') {
                    $updatedModules['SourceEditing'] = false;
                }
                $updatedModules[$module] = true;
            }
        }

        if ($disabledModules) {
            foreach ($disabledModules as $module) {
                $updatedModules[$module] = false;
            }
        }

        if (!empty($updatedModules) && $selectedPresetUid > 0) {
            try {
                // Get preset to ensure it exists
                $preset = $this->presetRepository->findByUid($selectedPresetUid);
                if (!$preset) {
                    throw new \Exception('Preset not found');
                }

                foreach ($updatedModules as $module => $value) {
                    $enable = $value == 'true' ? true : false;
                    
                    // Get module configuration to find the correct config_key
                    $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($module, true);
                    $configKey = $module;
                    if ($moduleConfiguration && isset($moduleConfiguration['configuration']['config_key'])) {
                        $configKey = $moduleConfiguration['configuration']['config_key'];
                    }

                    // Get or create feature for this preset and module
                    $feature = $this->featureRepository->findByPresetUidAndConfigKey($selectedPresetUid, $configKey);
                    
                    if (!$feature) {
                        // Create new feature
                        $feature = GeneralUtility::makeInstance(Feature::class);
                        $feature->setPresetUid($selectedPresetUid);
                        $feature->setConfigKey($configKey);
                        $feature->setEnable($enable);
                        $feature->setFields('');
                        $this->featureRepository->add($feature);
                        $this->persistenceManager->persistAll();
                    }

                    if ($enable) {
                        // Handle special cases when enabling
                        if ($module === 'SourceEditing') {
                            // Check if RealTimeCollaboration is enabled for this preset
                            $realTimeFeature = $this->featureRepository->findByPresetUidAndConfigKey($selectedPresetUid, 'RealTimeCollaboration');
                            if ($realTimeFeature && $realTimeFeature->isEnable()) {
                                $enable = false;
                                $notification['title'] = 'ckeditorKit.plugin.realtime_collaboration';
                                $notification['message'] = 'ckeditorKit.plugin.realtime_collaboration.message';
                                $notification['severity'] = 1;
                                $this->notification->addFlashNotification($notification);
                            }
                        }
                        
                        if ($module === 'RealTimeCollaboration') {
                            $fieldConfiguration = [];
                            $webSocketUrl = ExtensionConfigurationUtility::get('webSocketUrl', '');
                            if ($webSocketUrl) {
                                $fieldConfiguration['cloudServices'] = ['webSocketUrl' => $webSocketUrl];
                            }
                            if (isset($data['config']['allow']['presenceList']) && $data['config']['allow']['presenceList'] === '1') {
                                $fieldConfiguration['presenceList'] = ['container' => null];
                            }
                            $fieldConfiguration['removePlugins'] = ['SourceEditing'];
                            $fieldData = json_encode($fieldConfiguration);
                            $feature->setFields($fieldData);
                            
                            $notification['title'] = 'ckeditorKit.plugin.realtime_collaboration';
                            $notification['message'] = 'ckeditorKit.plugin.realtime_collaboration.message';
                            $notification['severity'] = 1;
                            $this->notification->addFlashNotification($notification);
                        }
                        
                        if ($module === 'Menubar') {
                            $fieldConfiguration = ['menuBar' => ['isVisible' => true]];
                            $fieldData = json_encode($fieldConfiguration);
                            $feature->setFields($fieldData);
                        }
                        
                        $feature->setEnable($enable);
                    } else {
                        // Handle disabling
                        if ($module === 'Menubar') {
                            $feature->setFields('');
                        }
                        
                        // Check if toolbar items should be removed
                        if ($data['position'] && isset($moduleConfiguration['configuration']['toolBarItems'])) {
                            $toolBar = $moduleConfiguration['configuration']['toolBarItems'];
                            $toolBarItemArray = array_filter(array_map('trim', explode(',', $toolBar)));
                            $toolBarItems = array_filter(array_map('trim', explode(',', $data['position'])));
                            $match = array_intersect($toolBarItemArray, $toolBarItems);
                            if (!$match) {
                                // Remove Item from toolBar
                                $this->baseToolBar->updateToolBar($module);
                            }
                        } elseif (!isset($moduleConfiguration['configuration']['toolBarItems'])) {
                            // Remove Item from toolBar
                            $this->baseToolBar->updateToolBar($module);
                        }
                        
                        $feature->setEnable(false);
                    }

                    $this->featureRepository->update($feature);
                    $this->persistenceManager->persistAll();
                    $this->cache->flush();
                }

                $notification['title'] = 'ckeditorKit.operation.success';
                $notification['message'] = 'ckeditorKit.module_update.success.message';
                $notification['severity'] = 0;
                $this->notification->addFlashNotification($notification);
                return true;
            } catch (\Exception $e) {
                $notification['title'] = 'ckeditorKit.operation.error';
                $notification['message'] = 'ckeditorKit.module_update.error.message';
                $notification['severity'] = 2;
                $this->notification->addFlashNotification($notification);
                return false;
            }
        } elseif (!$toolBarItems) {
            $notification['title'] = 'ckeditorKit.no_module_update';
            $notification['message'] = 'ckeditorKit.no_module_update.no_changes';
            $notification['severity'] = -1;
            $this->notification->addFlashNotification($notification);
        }

        return false;
    }

    private function generalSettings(array $data): bool
    {
        return false;
        // $record = $this->configurationRepository->findByConfigKey('FeatureConfiguration')->getFirst();

        // if (isset($data['tokenUrl']) && $data['tokenUrl']) {
        //     $status = $this->validator->validateUrl($data['tokenUrl']);
        //     if (!$status) {
        //         $notification['title'] = 'ckeditorKit.operation.error.invalid_token';
        //         $notification['message'] = 'ckeditorKit.operation.error.invalid_token.message';
        //         $notification['severity'] = 2;
        //         $this->notification->addFlashNotification($notification);
        //         return false;
        //     }
        // }

        // try {
        //     if ($record) {
        //         $record->setEnable(true);
        //         $record->setFields(json_encode($data));
        //         $this->configurationRepository->update($record);
        //     } else {
        //         $configuration = GeneralUtility::makeInstance(Configuration::class);
        //         $configuration->setConfigKey('FeatureConfiguration');
        //         $configuration->setFields(json_encode($data));
        //         $configuration->setEnable(true);
        //         $this->configurationRepository->add($configuration);
        //     }
        //     $this->cache->flush();
        //     $this->persistenceManager->persistAll();
        //     $notification['title'] =  'ckeditorKit.operation.success';
        //     $notification['message'] = 'ckeditorKit.general_settings.success.message';
        //     $notification['severity'] = 0;
        //     $this->notification->addFlashNotification($notification);
        //     return true;
        // } catch (\Exception) {
        //     $notification['title'] = 'ckeditorKit.operation.error';
        //     $notification['message'] = 'ckeditorKit.general_settings.error.message';
        //     $notification['severity'] = 2;
        //     $this->notification->addFlashNotification($notification);
        //     return false;
        // }
    }

   
    public function getToolBar(ServerRequestInterface $request): ResponseInterface
    {
        $assign['groups'] = $this->groupsRepository->findAll();
        // Get preset UID from query params or use 0 (will fallback to YAML)
        $presetUid = isset($request->getQueryParams()['presetUid']) && is_numeric($request->getQueryParams()['presetUid']) 
            ? (int)$request->getQueryParams()['presetUid'] 
            : 0;
        $activeFeaturItems = $this->baseToolBar->findEnableToolbarItems($presetUid)['activeFeaturItems'];
        $toolBars = array_column($activeFeaturItems, 'toolBar');
        $assign['toolBarItems'] = $toolBars;
        $assign['activeItems'] = implode(',', $toolBars);
        $assign['returnUrl'] = $request->getAttribute('normalizedParams')->getRequestUri();
        $assign['toolBarIcons'] = GeneralUtility::makeInstance(ToolbarGroups::class)->getToolBarIconValues();
        $this->moduleTemplate = $this->initializeModuleTemplate($request);
        $this->moduleTemplate->assignMultiple($assign);
        $notification = isset($request->getQueryParams()['notification']) ? $request->getQueryParams()['notification'] : [];
        if ($notification) {
            $this->notification->addFlashNotification($notification);
        }

        return $this->moduleTemplate->renderResponse('RteModule/ToolBarGroups');
    }

    public function saveToolBarGroups(ServerRequestInterface $request): RedirectResponse
    {
        $data = $request->getParsedBody();
        $notification = [];
        if (isset($data['group']) && $data['group']) {
            try {
                foreach ($data['group'] as $group) {
                    if (!isset($group['uid'])) {

                        $groupObject = GeneralUtility::makeInstance(ToolbarGroups::class);
                        $groupObject->setLabel($group['label']);
                        $groupObject->setIcon($group['icon'] ?? '');
                        $groupObject->setTooltip($group['tooltip'] ?? '');
                        $groupObject->setCustomIcon($group['customIcon'] ?? '');
                        $groupObject->setItems(isset($group['items']) ? implode(',', $group['items']) : '');
                        $this->groupsRepository->add($groupObject);
                    } else {
                        $originalRecord = $this->groupsRepository->findByUid((int)$group['uid']);
                        $originalRecord->setLabel($group['label']);
                        $originalRecord->setIcon($group['icon'] ?? '');
                        $originalRecord->setTooltip($group['tooltip'] ?? '');
                        $originalRecord->setCustomIcon($group['customIcon'] ?? '');
                        $originalRecord->setItems(isset($group['items']) ? implode(',', $group['items']) : '');
                        $this->groupsRepository->update($originalRecord);
                    }
                    $this->persistenceManager->persistAll();
                }
                $notification = [
                    'title' => 'ckeditorKit.operation.success',
                    'message' => 'ckeditorKit.toolbar.group.success.message',
                    'severity' => 0,
                ];
            } catch (\Exception $e) {
                $notification = [
                    'title' => 'ckeditorKit.operation.error',
                    'message' => $e->getMessage(),
                    'severity' => 2,
                ];
            }
        }

        $uri = $this->urlBuilder->generateBackendUrl('ajax_toolbar_configuration', ['notification' => $notification]);
        return new RedirectResponse($uri);
    }

    public function managePreset(ServerRequestInterface $request): ResponseInterface
    {
        $attributes = $request->getParsedBody();
        $presetsData = $this->baseToolBar->findAvailablePresets();
        $corePresets = $presetsData['core'] ?? [];
        $customPresets = $presetsData['custom'] ?? [];
        $availablePresets = array_merge($corePresets, $customPresets);
        
        if ($attributes && trim($attributes['presetName']) != '') {
            $presetName = str_replace(' ', '_', trim(strtolower($attributes['presetName'])) ?? null);
            
            // Check if preset already exists (in TYPO3 config or database)
            if (!in_array($presetName, array_keys($availablePresets))) {
                // Check if preset exists in database
                $existingPreset = $this->presetRepository->findByPresetKey($presetName);
                
                if (!$existingPreset) {
                    // Create new preset in the new preset table
                    try {
                        $preset = GeneralUtility::makeInstance(Preset::class);
                        $preset->setPresetKey($presetName);
                        $preset->setIsCustom(true); // Custom preset created by user
                        $preset->setHidden(false); // Default: active (use CKEditor Pack)
                        $this->presetRepository->add($preset);
                        $this->persistenceManager->persistAll();
                        
                        $notification['title'] = 'ckeditorKit.operation.success';
                        $notification['message'] = 'ckeditorKit.presert.success.message';
                        $notification['severity'] = 0;
                        $this->notification->addFlashNotification($notification);
                    } catch (\Exception $e) {
                        $notification['title'] = 'ckeditorKit.operation.error';
                        $notification['message'] = 'ckeditorKit.presert.error.message';
                        $notification['severity'] = 2;
                        $this->notification->addFlashNotification($notification);
                    }
                } else {
                    // Preset already exists in database
                    $notification['title'] = 'ckeditorKit.operation.error';
                    $notification['message'] = 'ckeditorKit.presert.error.message';
                    $notification['severity'] = 2;
                    $this->notification->addFlashNotification($notification);
                }
            } else {
                // Preset already exists (in TYPO3 config or available presets)
                $notification['title'] = 'ckeditorKit.operation.error';
                $notification['message'] = 'ckeditorKit.presert.error.message';
                $notification['severity'] = 2;
                $this->notification->addFlashNotification($notification);
            }
        }
        $this->moduleTemplate = $this->initializeModuleTemplate($request);
        
        // Get presets grouped by core and custom
        $presetsData = $this->baseToolBar->findAvailablePresets();
        $corePresets = $presetsData['core'] ?? [];
        $customPresets = $presetsData['custom'] ?? [];
        
        // Convert to array format for template (with preset_key as key)
        // Usage is derived from hidden: hidden=0 (active) → usage=1 (CKEditor Pack), hidden=1 (inactive) → usage=0 (YAML)
        $corePresetsArray = [];
        foreach ($corePresets as $presetKey => $presetData) {
            $preset = null;
            $hidden = $presetData['hidden'] ?? 1; // Default to 1 (inactive/use YAML) if not set
            if ($presetData['uid'] > 0) {
                $preset = $this->presetRepository->findByUid($presetData['uid']);
                if ($preset) {
                    $hidden = $preset->getHidden() ? 1 : 0;
                }
            }
            $corePresetsArray[] = [
                'uid' => $presetData['uid'],
                'preset_key' => $presetData['key'],
                'is_custom' => $presetData['is_custom'],
                'hidden' => $hidden,
            ];
        }
        
        $customPresetsArray = [];
        foreach ($customPresets as $presetKey => $presetData) {
            $preset = $this->presetRepository->findByUid($presetData['uid']);
            $hidden = 0;
            if ($preset) {
                $hidden = $preset->getHidden() ? 1 : 0;
            }
            $customPresetsArray[] = [
                'uid' => $presetData['uid'],
                'preset_key' => $presetData['key'],
                'is_custom' => $presetData['is_custom'],
                'hidden' => $hidden,
            ];
        }
        
        $this->moduleTemplate->assignMultiple([
            'corePresets' => $corePresetsArray,
            'customPresets' => $customPresetsArray,
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
        ]);
        return $this->moduleTemplate->renderResponse('RteModule/NewPreset');
    }

    private function manageToken(array $configArray): array
    {
        $tokenUrl = ExtensionConfigurationUtility::get('tokenUrl', '');
        if ($tokenUrl) {
            $configArray['tokenUrl'] = $tokenUrl;
        }
        return $configArray;
    }

   

    private function manageIndent(array $indentBlock, string $key): array
    {
        $type = $key === 'indentBlock' ? 'indentType' : 'outdentType';
        if (isset($indentBlock[$type]) && $indentBlock[$type] === '1') {
            unset($indentBlock['classes']);
        } else {
            unset($indentBlock['offset']);
            unset($indentBlock['unit']);
        }
        return $indentBlock;
    }

    private function normalizeStylesheetsConfiguration(array &$configuration, string $mainKey): void
    {
        if (($mainKey === 'exportPdf' || $mainKey === 'exportWord') && isset($configuration[$mainKey]['stylesheets'])) {
            $stylesheets = $configuration[$mainKey]['stylesheets'];

            if (!is_array($stylesheets)) {
                if (is_string($stylesheets)) {
                    $configuration[$mainKey]['stylesheets'] = GeneralUtility::trimExplode(',', $stylesheets, true);
                } else {
                    $configuration[$mainKey]['stylesheets'] = [];
                }
            } else {
                // Ensure all values are strings and filter out empty values
                $configuration[$mainKey]['stylesheets'] = array_values(array_filter(
                    array_map('trim', array_filter($stylesheets, 'is_string')),
                    fn($value) => $value !== ''
                ));
            }
        }
    }

    protected function initializeModuleTemplate(ServerRequestInterface $request): ModuleTemplate
    {
        return $this->moduleTemplateFactory->create($request);
    }

    protected function preparePageRenderer(): void
    {
        $this->pageRenderer->addCssFile('EXT:dashboard/Resources/Public/Css/dashboard.css');
        $this->pageRenderer->addCssFile('EXT:backend/Resources/Public/Css/backend.css');
        $this->pageRenderer->addCssFile('EXT:rte_ckeditor_pack/Resources/Public/Css/dashboard.css');
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/global-button.js');
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/wizard-manipulation.js');
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/multi-record-selection.js');
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/module-functionality.js');
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/user-adapter.js');
    }
}
