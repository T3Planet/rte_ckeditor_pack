<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Controller;

use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Cache\CacheManager;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\DataProvider\Modules;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use T3Planet\RteCkeditorPack\Utility\FlashUtility;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use T3Planet\RteCkeditorPack\DataProvider\BaseToolBar;
use T3Planet\RteCkeditorPack\Utility\YamlLoadrUtility;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use T3Planet\RteCkeditorPack\Service\TokenUrlValidator;
use T3Planet\RteCkeditorPack\Service\ImportExportService;
use T3Planet\RteCkeditorPack\Utility\UriBuilderUtility;
use T3Planet\RteCkeditorPack\Domain\Model\ToolbarGroups;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use T3Planet\RteCkeditorPack\Utility\ConfigurationMergeUtility;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Utility\ExtensionConfigurationUtility;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use T3Planet\RteCkeditorPack\EventListener\SyncFeaturesBeforeExportListener;
use Symfony\Component\Yaml\Yaml;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

class RteModuleController extends ActionController
{

    protected ModuleTemplate $moduleTemplate;

    protected UriBuilderUtility $urlBuilder;

    protected FlashUtility $notification;

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
        FeatureRepository $featureRepository,
        PresetRepository $presetRepository,
        PersistenceManager $persistenceManager,
        ToolbarGroupsRepository $groupsRepository,
        protected readonly ImportExportService $importExportService,
        protected readonly SyncFeaturesBeforeExportListener $syncFeaturesService,
    ) {
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
        if (isset($this->request->getQueryParams()['current_module'])) {
            $currentModule = $this->request->getQueryParams()['current_module'] ?? '';
            $notification = $this->request->getQueryParams()['notification'] ?? [];
            $this->notification->addFlashNotification($notification);
        }

        $presetsData = $this->baseToolBar->findAvailablePresets();
        $corePresets = $presetsData['core'] ?? [];
        $customPresets = $presetsData['custom'] ?? [];
        $availablePresets = array_merge($corePresets, $customPresets);

        // Get first preset key and extract UID
        $firstPresetKey = array_key_first($availablePresets);
        $activePresetUid = $availablePresets[$firstPresetKey]['uid'] ?? 0;
        $attributes = $this->request->getParsedBody();

        if ($attributes) {
            if ($attributes && array_key_exists('position', $attributes) || array_key_exists('modules', $attributes)) {
                $this->updateModules($attributes);
                $currentModule = isset($attributes['active_tab']) ? $attributes['active_tab'] : 'features';
            }
            if (array_key_exists('activePresets', $attributes)) {
                $currentModule = 'features';
            }
            $this->cache->flush();
        }
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $sessionKey = 'rte_ckeditor_pack_activePreset';
        $params = $this->request->getQueryParams();
        if ($params && isset($params['activePreset']) && $params['activePreset']) {
            $activePresetUid = (int)$params['activePreset'];
            $backendUser->setAndSaveSessionData($sessionKey, $activePresetUid);
        } else {
            if($backendUser->getSessionData($sessionKey)){
                $activePresetUid = $backendUser->getSessionData($sessionKey);
            }
        }
        $groupedPresets = [
            'yaml' => $corePresets,
            'custom' => $customPresets,
        ];
        $this->moduleTemplate->assignMultiple([
            'availableModules' => $availableModules,
            'currentModule' => $currentModule ?? 'features',
            'toolBarConfiguration' => $this->baseToolBar->findEnableToolbarItems((int)$activePresetUid),
            'availablePresets' => $availablePresets, // Keep for backward compatibility
            'groupedPresets' => $groupedPresets, // New grouped structure
            'activePreset' => $activePresetUid,
            'extSettings' =>  ExtensionConfigurationUtility::getAll()
        ]);

        $this->preparePageRenderer();
        return $this->moduleTemplate->renderResponse('RteModule/Index');
    }

    public function settingsAction(): ResponseInterface
    {
        $notification = [];
        $validate = true;
        $data = $this->request->getParsedBody();
        if (isset($data['tokenUrl']) && !empty($data['tokenUrl']) && filter_var($data['tokenUrl'], FILTER_VALIDATE_URL)) {
            $status = $this->validator->validateUrl($data['tokenUrl']);
            if (!$status) {
                $validate = false;
                $notification['title'] = 'ckeditorKit.operation.error.invalid_token';
                $notification['message'] = 'ckeditorKit.operation.error.invalid_token.message';
                $notification['severity'] = 2;
                $this->notification->addFlashNotification($notification);
            }
        }
        if($data && $validate){
            try {
                // Prepare configuration array - only include allowed fields from form data
                $allowedFields = [
                    'licenseKey', 'authType', 'environmentId', 'accessKey', 'apiKey',
                    'organizationId', 'tokenUrl', 'webSocketUrl', 'apiBaseUrl'
                ];
                $configuration = array_intersect_key($data, array_flip($allowedFields));
                $success = ExtensionConfigurationUtility::set($configuration);
                if ($success) {
                    $this->cache->flush();
                    $notification['title'] = 'ckeditorKit.operation.success';
                    $notification['message'] = 'ckeditorKit.general_settings.success.message';
                    $notification['severity'] = 0;
                    $this->notification->addFlashNotification($notification);
                } else {
                    throw new \Exception('Failed to save extension configuration');
                }
            } catch (\Exception $e) {
                $notification['title'] = 'ckeditorKit.operation.error';
                $notification['message'] = 'ckeditorKit.general_settings.error.message';
                $notification['severity'] = 2;
                $this->notification->addFlashNotification($notification);
            }
        }

        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $extSettings = $configurationManager->getConfigurationValueByPath('EXTENSIONS/rte_ckeditor_pack') ?? [];

        $this->moduleTemplate->assignMultiple([
            'extSettings' => $extSettings,
        ]);

        $this->preparePageRenderer();
        return $this->moduleTemplate->renderResponse('RteModule/ExtSettings');
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
            if (!isset($assign['configuration']) && isset($moduleConfiguration['configuration'])) {
                $assign['configuration'] = $moduleConfiguration['configuration'];
            }
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
        $preset = $this->presetRepository->findByUid($presetUid);
        
        if (!$preset) {
            throw new \Exception('Preset not found');
        }

        try {
            if ($configKey) {
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
                $this->baseToolBar->updateToolBar($configKey, $presetUid);
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
                $feature = null;
                foreach ($updatedModules as $module => $value) {
                    $enable = $value == 'true' ? true : false;

                    // Get module configuration to find the correct config_key
                    $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($module, true);
                    $configKey = $module;

                    if ($moduleConfiguration && isset($moduleConfiguration['configuration']['config_key'])) {
                        $configKey = $moduleConfiguration['configuration']['config_key'];
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
                    }

                    if ($feature) {
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
                                    $this->baseToolBar->updateToolBar($module, $selectedPresetUid);
                                }
                            } elseif (isset($moduleConfiguration['configuration']['toolBarItems'])) {
                                // Remove Item from toolBar
                                $this->baseToolBar->updateToolBar($configKey, $selectedPresetUid);
                            }
                            if (!isset($data['operation'])) {
                                $feature->setEnable(false);
                            }
                        }
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
                        $preset->setIsCustom(true);
                        $this->presetRepository->add($preset);
                        $this->persistenceManager->persistAll();
                        $newPresetUid = $preset->getUid();
                        $backendUser = $GLOBALS['BE_USER'] ?? null;
                        if ($backendUser && $newPresetUid) {
                            $sessionKey = 'rte_ckeditor_pack_activePreset';
                            $backendUser->setAndSaveSessionData($sessionKey, $newPresetUid);
                        }
                        $notification[] = [
                            'title' => 'ckeditorKit.operation.success',
                            'message' => 'ckeditorKit.presert.success.message',
                            'severity' => 0,
                        ];
                    } catch (\Exception $e) {
                        $notification[] = [
                            'title' => 'ckeditorKit.operation.error',
                            'message' => 'ckeditorKit.presert.error.message',
                            'severity' => 2,
                        ];
                    }
                } else {
                    $notification[] = [
                        'title' => 'ckeditorKit.operation.error',
                        'message' => 'ckeditorKit.presert.error.message',
                        'severity' => 2,
                    ];
                }
            } else {
                $notification[] = [
                    'title' => 'ckeditorKit.operation.error',
                    'message' => 'ckeditorKit.presert.error.message',
                    'severity' => 2,
                ];
            }
            return new JsonResponse([
                'notifications' => $notification,
            ]);
        }
        $this->moduleTemplate = $this->initializeModuleTemplate($request);
        $presetsData = $this->baseToolBar->findAvailablePresets();
        $corePresets = $presetsData['core'] ?? [];
        $customPresets = $presetsData['custom'] ?? [];
        $ajaxUrl = $this->urlBuilder->generateBackendUrl('ajax_new_preset');
        $this->moduleTemplate->assignMultiple([
            'corePresets' => $corePresets,
            'customPresets' => $customPresets,
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
            'ajaxUrl' => $ajaxUrl,
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


    /**
     * Sync preset toolbar items from YAML configuration
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function syncPreset(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $presetUid = isset($data['presetUid']) && is_numeric($data['presetUid']) ? (int)$data['presetUid'] : 0;
        $notification = [];

        try {
            if ($presetUid > 0) {
                $preset = $this->presetRepository->findByUid($presetUid);

                if (!$preset) {
                    throw new \Exception('Preset not found');
                }
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
                $syncData = $mergeUtility->syncToolBar($toolBariteams,$preset->getToolbarItems());
                $preset->setToolbarItems($syncData);
                $this->presetRepository->update($preset);
                
                $features = $this->featureRepository->findByPresetUid($presetUid);
                if (empty($features)) {
                    return new JsonResponse([
                        'notifications' => [[
                            'title' => 'ckeditorKit.preset.sync.error.no_feature',
                            'severity' => 3,
                        ]]
                    ]);
                }

                foreach ($features as $feature) {
                    $yamlFeatureConfig = [];
                    $configKey = $feature->getConfigKey();
                    if($configKey == 'Mention'){
                        $notification[] = [
                            'title' => 'ckeditorKit.preset.sync.mention',
                            'severity' => 3,
                        ];
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
                $this->cache->flush();

                $notification[] = [
                    'title' => 'ckeditorKit.operation.success',
                    'message' => 'ckeditorKit.preset.sync.success.message',
                    'severity' => 0,
                ];
            } else {
                throw new \Exception('Invalid preset UID');
            }
        } catch (\Exception $e) {
            $notification[] = [
                'title' => 'ckeditorKit.operation.error',
                'message' => 'ckeditorKit.preset.sync.error.message',
                'severity' => 2,
            ];
        }

        return new JsonResponse([
            'notifications' => $notification,
        ]);
    }


    /**
     * Export preset configuration as YAML file
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function exportPreset(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $presetUid = isset($data['presetUid']) && is_numeric($data['presetUid']) ? (int)$data['presetUid'] : 0;

        try {
            // Early validation before database queries
            if ($presetUid <= 0) {
                throw new \Exception('Invalid preset UID');
            }

            $preset = $this->presetRepository->findByUid($presetUid);
            
            if (!$preset) {
                throw new \Exception('Preset not found');
            }

            $presetKey = $preset->getPresetKey();
            $isCustom = $preset->getIsCustom();
            
            // Checking for the preset is custom or not
            if (!$isCustom) {
                // Sync features before export
                $this->syncFeaturesService->syncPresetFeatures($preset);
                // Load original YAML file content as-is
                $yamlContent = $this->importExportService->loadAndEnhanceYamlFile($presetKey, $presetUid, $preset->getToolbarItems());
            } else {
                // For custom presets, build from database only
                $yamlConfig = $this->importExportService->buildYamlConfiguration($presetUid, $preset->getToolbarItems(), null);
                $yamlContent = $this->importExportService->formatYamlContent($yamlConfig, null);
            }
            
            // Get response and stream factories (cached by GeneralUtility)
            $responseFactory = GeneralUtility::makeInstance(ResponseFactoryInterface::class);
            $streamFactory = GeneralUtility::makeInstance(StreamFactoryInterface::class);
            
            // Create stream with YAML content
            $stream = $streamFactory->createStream($yamlContent);
            $contentLength = strlen($yamlContent);
            $filename = $presetKey . '.yaml';
            
            // Return file download response with optimized header building
            return $responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/x-yaml; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Content-Length', (string)$contentLength)
                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0')
                ->withBody($stream);
        } catch (\Exception $e) {
            // Return error as JSON response
            return new JsonResponse([
                'notifications' => [[
                    'title' => 'ckeditorKit.operation.error',
                    'message' => $e->getMessage(),
                    'severity' => 2,
                ]]
            ]);
        }
    }


    /**
     * Import preset configuration from YAML file
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function importPreset(ServerRequestInterface $request): ResponseInterface
    {

        $uploadedFiles = $request->getUploadedFiles();
        if ($uploadedFiles) {
            try {

                $yamlFile = $uploadedFiles['yamlFile'] ?? null;
                
                if (!$yamlFile || !($yamlFile instanceof UploadedFileInterface)) {
                    throw new \Exception('No file uploaded');
                }
                
                if ($yamlFile->getError() !== UPLOAD_ERR_OK) {
                    throw new \Exception('File upload error: ' . $yamlFile->getError());
                }
                
                // Get file content
                $fileContent = $yamlFile->getStream()->getContents();
                $fileName = $yamlFile->getClientFilename();
            
                // Extract preset key from filename (remove .yaml/.yml extension)
                $presetKey = pathinfo($fileName, PATHINFO_FILENAME);
                $presetKey = str_replace(' ', '_', trim(strtolower($presetKey)));
                
                if (empty($presetKey)) {
                    throw new \Exception('Invalid filename');
                }
                
                // Parse YAML content
                $yamlConfig = Yaml::parse($fileContent);
                
                if (empty($yamlConfig) || !isset($yamlConfig['editor']['config'])) {
                    throw new \Exception('Invalid YAML configuration: missing editor.config');
                }
            
                $editorConfig = $yamlConfig['editor']['config'];
                
                // Extract toolbar items
                $toolbarItems = [];
                if (isset($editorConfig['toolbar']['items']) && is_array($editorConfig['toolbar']['items'])) {
                    $toolbarItems = $editorConfig['toolbar']['items'];
                }
                $toolbarItemsString = !empty($toolbarItems) ? implode(',', $toolbarItems) : '';
                
                // Check if preset already exists - if so, update it
                $existingPreset = $this->presetRepository->findByPresetKey($presetKey);
                $isUpdate = false;
            
                if ($existingPreset) {
                    // Update existing preset
                    $preset = $existingPreset;
                    $preset->setToolbarItems($toolbarItemsString);
                    $this->presetRepository->update($preset);
                    $presetUid = $preset->getUid();
                    $isUpdate = true;
                    // Remove existing features before importing new ones
                    $this->featureRepository->removeByPresetId($presetUid);
                    $this->persistenceManager->persistAll();
                } else {
                    // Check if preset exists in TYPO3 config (core preset)
                    $presetsData = $this->baseToolBar->findAvailablePresets();
                    $availablePresets = array_merge($presetsData['core'] ?? [], $presetsData['custom'] ?? []);

                    if (in_array($presetKey, array_keys($availablePresets))) {
                        throw new \Exception('Cannot update core preset from YAML. Please use a different name.');
                    }
                    
                    // Create new preset
                    $preset = GeneralUtility::makeInstance(Preset::class);
                    $preset->setPresetKey($presetKey);
                    $preset->setIsCustom(true);
                    $preset->setToolbarItems($toolbarItemsString);
                    $this->presetRepository->add($preset);
                    $this->persistenceManager->persistAll();
                    $presetUid = $preset->getUid();
                }

                $toolbarItemArray = explode(',', $toolbarItemsString);
                foreach ($toolbarItemArray as $value) {
                    
                    if(strlen($value) > 1 && $presetUid){

                        $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($value, true);

                        if ($moduleConfiguration && isset($moduleConfiguration['configuration']['config_key'])) {
                            $configKey = $moduleConfiguration['configuration']['config_key'];
                            // Get or create feature for this preset and module
                            $feature = $this->featureRepository->findByPresetUidAndConfigKey($presetUid, $configKey);
                            if (!$feature) {
                                // Create new feature
                                $feature = GeneralUtility::makeInstance(Feature::class);
                                $feature->setPresetUid($presetUid);
                                $feature->setConfigKey($configKey);
                                $feature->setEnable(true);
                                $this->featureRepository->add($feature);
                                $this->persistenceManager->persistAll();
                            }
                        }
                    }
                }
            
                // Process features from YAML config
                $this->importExportService->importFeaturesFromYaml($presetUid, $editorConfig);
                $this->persistenceManager->persistAll();
                $this->cache->flush();
            
                $notification[] = [
                    'title' => 'ckeditorKit.operation.success',
                    'message' => $isUpdate
                        ? 'ckeditorKit.preset.import.update.success.message'
                        : 'ckeditorKit.preset.import.success.message',
                    'severity' => 0,
                ];

            } catch (\Exception $e) {
                $notification[] = [
                    'title' => 'ckeditorKit.operation.error',
                    'message' => $e->getMessage(),
                    'severity' => 2,
                ];
            }
            
            return new JsonResponse([
                'notifications' => $notification,
            ]);
        }
        $this->moduleTemplate = $this->initializeModuleTemplate($request);
        $ajaxUrl = $this->urlBuilder->generateBackendUrl('ajax_new_preset');
        $this->moduleTemplate->assignMultiple([
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
            'ajaxUrl' => $ajaxUrl,
        ]);
        return $this->moduleTemplate->renderResponse('RteModule/ImportPreset');
        
    }

    /**
     * Check if preset exists by name
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function checkPresetExists(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $presetKey = isset($data['presetKey']) ? trim(strtolower($data['presetKey'])) : '';
        
        $exists = false;
        $isCore = false;
        
        if (!empty($presetKey)) {
            // Normalize preset key (same as import does)
            $presetKey = str_replace(' ', '_', $presetKey);
            
            // Check if it's a core preset
            $presetsData = $this->baseToolBar->findAvailablePresets();
            $availablePresets = array_merge($presetsData['core'] ?? [], $presetsData['custom'] ?? []);
            
            if (in_array($presetKey, array_keys($availablePresets))) {
                $exists = true;
                $isCore = in_array($presetKey, array_keys($presetsData['core'] ?? []));
            } else {
                // Check database
                $existingPreset = $this->presetRepository->findByPresetKey($presetKey);
                if ($existingPreset) {
                    $exists = true;
                }
            }
        }
        
        return new JsonResponse([
            'exists' => $exists,
            'isCore' => $isCore,
            'presetKey' => $presetKey,
        ]);
    }

    /**
     * Reset preset toolbar items from YAML configuration
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function resetPreset(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $presetUid = isset($data['presetUid']) && is_numeric($data['presetUid']) ? (int)$data['presetUid'] : 0;
        $notification = [];
        try {
            if ($presetUid > 0) {
                $preset = $this->presetRepository->findByUid($presetUid);

                if (!$preset) {
                    throw new \Exception('Preset not found');
                }
                $preset->setToolbarItems('');
                $this->presetRepository->update($preset);

                if ($this->featureRepository->removeByPresetId($presetUid)) {
                    $this->persistenceManager->persistAll();
                    $this->cache->flush();
                    $notification[] = [
                        'title' => 'ckeditorKit.operation.success',
                        'message' => 'ckeditorKit.preset.reset.success.message',
                        'severity' => 0,
                    ];
                } else {
                    $notification[] = [
                        'title' => 'ckeditorKit.operation.error',
                        'message' => 'ckeditorKit.preset.reset.error.message',
                        'severity' => 2,
                    ];
                }
            } else {
                throw new \Exception('Invalid preset UID');
            }
        } catch (\Exception $e) {
            $notification[] = [
                'title' => 'ckeditorKit.operation.error',
                'message' => 'ckeditorKit.preset.reset.error.message',
                'severity' => 2,
            ];
        }
        return new JsonResponse([
            'notifications' => $notification,
        ]);
    }

    /**
     * Render processing configuration form
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function processingConfigAction(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getQueryParams();
        $presetUid = isset($data['selectedPreset']) && is_numeric($data['selectedPreset']) ? (int)$data['selectedPreset'] : 0;
        
        if ($presetUid <= 0) {
            throw new \Exception('Invalid preset UID');
        }

        $preset = $this->presetRepository->findByUid($presetUid);
        if (!$preset) {
            throw new \Exception('Preset not found');
        }

        $processing = $request->getParsedBody()['processing'] ?? [];
        if($processing){
            $preset->setProcessingConfig(json_encode($processing));
            $this->presetRepository->update($preset);
            $this->persistenceManager->persistAll();
            $this->cache->flush();
            $this->notification->addFlashNotification([
                'title' => 'ckeditorKit.operation.success',
                'message' => 'ckeditorKit.plugin.setting_save.success.message',
                'severity' => 0,
            ]);
        }

        $processingConfig = $preset->getProcessingConfig();
        $configArray = [];
        if ($processingConfig) {
            $configArray = json_decode($processingConfig, true);
        }

        $this->moduleTemplate = $this->initializeModuleTemplate($request);
        $this->moduleTemplate->assignMultiple([
            'presetUid' => $presetUid,
            'config' => $configArray,
        ]);

        return $this->moduleTemplate->renderResponse('RteModule/ProcessingConfig');
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
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/import-export.js');
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/wizard-manipulation.js');
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/multi-record-selection.js');
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/module-functionality.js');
        $this->pageRenderer->loadJavaScriptModule('@t3planet/RteCkeditorPack/user-adapter.js');
    }
}
