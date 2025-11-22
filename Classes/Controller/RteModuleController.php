<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use T3Planet\RteCkeditorPack\DataProvider\BaseToolBar;
use T3Planet\RteCkeditorPack\DataProvider\Modules;
use T3Planet\RteCkeditorPack\Domain\Model\Configuration;
use T3Planet\RteCkeditorPack\Domain\Model\ToolbarGroups;
use T3Planet\RteCkeditorPack\Domain\Repository\ConfigurationRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use T3Planet\RteCkeditorPack\Service\TokenUrlValidator;
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
        PersistenceManager $persistenceManager,
        ToolbarGroupsRepository $groupsRepository,
    ) {
        $this->configurationRepository = $configurationRepository;
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

        $availablePresets = $this->baseToolBar->findAvailablePresets();
        $activePreset = array_key_first($availablePresets);
        $attributes = $this->request->getParsedBody();

        if ($attributes) {
            if (array_key_exists('licenseKey', $attributes)) {
                $this->generalSettings($attributes);
                $currentModule = 'settings';
            } elseif ($attributes && array_key_exists('position', $attributes) || array_key_exists('modules', $attributes)) {
                $this->updateModules($attributes);
                $currentModule = isset($attributes['active_tab']) ? $attributes['active_tab'] : 'features';
                $activePreset =  isset($attributes['activePreset']) ? $attributes['activePreset'] : $activePreset;
            }
            if (array_key_exists('activePresets', $attributes)) {
                $activePreset =  $attributes['activePresets'];
                $currentModule = 'features';
            }
            $this->cache->flush();
        }

        $extSettings = $this->configurationRepository->findConfiguration('FeatureConfiguration');
        $settingsArray = GeneralUtility::makeInstance(Modules::class)->getSettings();

        $this->moduleTemplate->assignMultiple([
            'availableModules' => $availableModules,
            'currentModule' => $currentModule,
            'settingFields' => $settingsArray,
            'toolBarConfiguration' => $this->baseToolBar->findEnableToolbarItems($activePreset),
            'availablePresets' => $availablePresets,
            'activePreset' => $activePreset,
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

        if (isset($data['additionalParams'])) {
            $configuration = $data['additionalParams'] ? json_decode($data['additionalParams'], true) : '';
            $assign['additionalParams'] = $data['additionalParams'] ?? '';
            $moduleKey = $configuration['config_key'];
            $assign['configuration'] = $configuration;
        }

        if ($moduleKey) {
            $record = $this->configurationRepository->findByConfigKey($moduleKey)->getFirst();
            if ($record) {
                $assign['record'] = json_decode($record->getFields(), true);
                $assign['record']['enable'] = $record->getEnable();
                $assign['record']['configKey'] = $record->getConfigKey();
            }
            $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($moduleKey);
            $assign['fields'] = $moduleConfiguration['fields'] ?? [];
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

        try {
            if ($configKey) {
                if ($configKey === 'AIAssistant') {
                    if (isset($data['config']['ai']['openAI']['apiUrl']) && $data['config']['ai']['openAI']['apiUrl'] === '') {
                        unset($data['config']['ai']['openAI']['apiUrl']);
                    }
                }
                $fieldData = isset($data['config']) ? json_encode($data['config']) : '';
                $record = $this->configurationRepository->findByConfigKey($configKey)->getFirst();

                if (!$record) {
                    $record = GeneralUtility::makeInstance(Configuration::class);
                    $record->setConfigKey($configKey);
                    $this->configurationRepository->add($record);
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

                    $fields = $this->configurationRepository->findConfiguration('FeatureConfiguration');

                    if (isset($fields['webSocketUrl']) && $fields['webSocketUrl']) {
                        $fieldConfiguration['cloudServices'] = ['webSocketUrl' => $fields['webSocketUrl']];
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
                $record->setEnable($enable);
                $record->setFields($fieldData);
                $this->configurationRepository->update($record);
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
        $selectedPreset = $data['activePreset'] ?? '';

        if ($toolBarItems && $selectedPreset) {
            $this->groupsRepository->updateToolBarItems($toolBarItems, $selectedPreset);
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

        if (!empty($updatedModules)) {

            try {

                foreach ($updatedModules as $module => $value) {
                    $enable = $value == 'true' ? true : false;
                    $record = $this->configurationRepository->findByConfigKey($module)->getFirst();
                    $key = $module;
                    if (!$record) {
                        $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($module, true);
                        if ($moduleConfiguration) {
                            if (isset($moduleConfiguration['configuration']['config_key'])) {
                                $key = $moduleConfiguration['configuration']['config_key'];
                                $record = $this->configurationRepository->findByConfigKey($key)->getFirst();
                            }
                        }
                    }
                    if (!$record) {
                        $record = GeneralUtility::makeInstance(Configuration::class);
                        $record->setConfigKey($key);
                        $record->setEnable($enable);
                        $record->setPreset($selectedPreset);
                        $this->configurationRepository->add($record);
                    } else {

                        $existingPreset = $record->getPreset() ? GeneralUtility::trimExplode(',', $record->getPreset()) : [];

                        if ($enable) {
                            if ($module === 'SourceEditing') {
                                $realTime = $this->configurationRepository->findByConfigKey('RealTimeCollaboration')->getFirst();
                                if ($realTime->isEnable()) {
                                    $enable = false;
                                    $notification['title'] = 'ckeditorKit.plugin.realtime_collaboration';
                                    $notification['message'] = 'ckeditorKit.plugin.realtime_collaboration.message';
                                    $notification['severity'] = 1;
                                    $this->notification->addFlashNotification($notification);
                                }
                            }
                            if ($module === 'RealTimeCollaboration') {
                                $fieldConfiguration = [];
                                $fields = $this->configurationRepository->findConfiguration('FeatureConfiguration');

                                if (isset($fields['webSocketUrl']) && $fields['webSocketUrl']) {
                                    $fieldConfiguration['cloudServices'] = ['webSocketUrl' => $fields['webSocketUrl']];
                                }
                                if (isset($data['config']['allow']['presenceList']) && $data['config']['allow']['presenceList'] === '1') {
                                    $fieldConfiguration['presenceList'] = ['container' => null];
                                }
                                $fieldConfiguration['removePlugins'] = ['SourceEditing'];
                                $fieldData = json_encode($fieldConfiguration);
                                $record->setFields($fieldData);
                                $notification['title'] = 'ckeditorKit.plugin.realtime_collaboration';
                                $notification['message'] = 'ckeditorKit.plugin.realtime_collaboration.message';
                                $notification['severity'] = 1;
                                $this->notification->addFlashNotification($notification);
                            }
                            if ($module === 'Menubar') {
                                $fieldConfiguration['menuBar'] = [
                                    'isVisible' => true,
                                ];
                                $fieldData = json_encode($fieldConfiguration);
                                $record->setFields($fieldData);
                            }
                            if (!empty($existingPreset)) {
                                if (!in_array($selectedPreset, $existingPreset, true)) {
                                    $existingPreset[] = $selectedPreset;
                                }
                            } else {
                                $existingPreset[] = $selectedPreset;
                            }
                            $record->setEnable($enable);
                        } else {
                            if ($module === 'Menubar') {
                                $record->setFields('');
                            }
                            if ($data['position'] && isset($moduleConfiguration['configuration']['toolBarItems'])) {
                                $toolBar = $moduleConfiguration['configuration']['toolBarItems'];
                                $toolBarItemArray = array_filter(array_map('trim', explode(',', $toolBar)));
                                $toolBarItems = array_filter(array_map('trim', explode(',', $data['position'])));
                                $match = array_intersect($toolBarItemArray, $toolBarItems);
                                if (!$match) {
                                    $key = array_search($selectedPreset, $existingPreset, true);
                                    if ($key !== false) {
                                        unset($existingPreset[$key]);
                                    }
                                    // Remove Item from toolBar
                                    $this->baseToolBar->updateToolBar($module);
                                }
                            } elseif (!isset($moduleConfiguration['configuration']['toolBarItems'])) {
                                $key = array_search($selectedPreset, $existingPreset, true);
                                if ($key !== false) {
                                    unset($existingPreset[$key]);
                                }
                                // Remove Item from toolBar
                                $this->baseToolBar->updateToolBar($module);
                            }
                            if (isset($data['operation'])) {
                                $enable = $existingPreset ? true : false;
                            }
                            $record->setEnable($enable);
                        }

                        $record->setPreset(implode(',', $existingPreset));
                        $this->configurationRepository->update($record);
                    }

                    $this->persistenceManager->persistAll();
                    $this->cache->flush();
                }

                $notification['title'] = 'ckeditorKit.operation.success';
                $notification['message'] = 'ckeditorKit.module_update.success.message';
                $notification['severity'] = 0;
                $this->notification->addFlashNotification($notification);
                return true;
            } catch (\Exception) {
                $notification['title'] = 'ckeditorKit.operation.error';
                $notification['message'] = 'ckeditorKit.module_update.error.message';
                $notification['severity'] = 2;
                $this->notification->addFlashNotification($notification);
                return false;
            }
        } elseif (!$toolBarItems) {

            $notification['title'] =  'ckeditorKit.no_module_update';
            $notification['message'] = 'ckeditorKit.no_module_update.no_changes';
            $notification['severity'] = -1;
            $this->notification->addFlashNotification($notification);
        }

        return false;
    }

    private function generalSettings(array $data): bool
    {
        $record = $this->configurationRepository->findByConfigKey('FeatureConfiguration')->getFirst();

        if (isset($data['tokenUrl']) && $data['tokenUrl']) {
            $status = $this->validator->validateUrl($data['tokenUrl']);
            if (!$status) {
                $notification['title'] = 'ckeditorKit.operation.error.invalid_token';
                $notification['message'] = 'ckeditorKit.operation.error.invalid_token.message';
                $notification['severity'] = 2;
                $this->notification->addFlashNotification($notification);
                return false;
            }
        }

        try {
            if ($record) {
                $record->setEnable(true);
                $record->setFields(json_encode($data));
                $this->configurationRepository->update($record);
            } else {
                $configuration = GeneralUtility::makeInstance(Configuration::class);
                $configuration->setConfigKey('FeatureConfiguration');
                $configuration->setFields(json_encode($data));
                $configuration->setEnable(true);
                $this->configurationRepository->add($configuration);
            }
            $this->cache->flush();
            $this->persistenceManager->persistAll();
            $notification['title'] =  'ckeditorKit.operation.success';
            $notification['message'] = 'ckeditorKit.general_settings.success.message';
            $notification['severity'] = 0;
            $this->notification->addFlashNotification($notification);
            return true;
        } catch (\Exception) {
            $notification['title'] = 'ckeditorKit.operation.error';
            $notification['message'] = 'ckeditorKit.general_settings.error.message';
            $notification['severity'] = 2;
            $this->notification->addFlashNotification($notification);
            return false;
        }
    }

    public function getToolBar(ServerRequestInterface $request): ResponseInterface
    {
        $assign['groups'] = $this->groupsRepository->findAll();
        $activeFeaturItems = $this->baseToolBar->findEnableToolbarItems()['activeFeaturItems'];
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

    public function addPreset(ServerRequestInterface $request): ResponseInterface
    {
        $attributes = $request->getParsedBody();
        $availablePresets = $this->baseToolBar->findAvailablePresets();

        if ($attributes && trim($attributes['presetName']) != '') {
            $presetName = str_replace(' ', '_', trim(strtolower($attributes['presetName'])) ?? null);
            if (!in_array($presetName, array_keys($availablePresets))) {
                $this->groupsRepository->insertToolBarPreset($presetName, ['preset' => $presetName]);
                $notification['title'] = 'ckeditorKit.operation.success';
                $notification['message'] = 'ckeditorKit.presert.success.message';
                $notification['severity'] = 0;
                $this->notification->addFlashNotification($notification);
            } else {
                $notification['title'] = 'ckeditorKit.operation.error';
                $notification['message'] = 'ckeditorKit.presert.error.message';
                $notification['severity'] = 2;
                $this->notification->addFlashNotification($notification);
            }
        }
        $this->moduleTemplate = $this->initializeModuleTemplate($request);
        $this->moduleTemplate->assignMultiple([
            'availablePresets' => $this->groupsRepository->findPresets(),
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
        ]);
        return $this->moduleTemplate->renderResponse('RteModule/NewPreset');
    }

    private function manageToken(array $configArray): array
    {
        $settings = $this->configurationRepository->findByConfigKey('FeatureConfiguration')->getFirst();
        if ($settings && $settings->getFields()) {
            $featureConfiguration = json_decode($settings->getFields(), true);
            if ($featureConfiguration && $featureConfiguration['tokenUrl']) {
                $configArray['tokenUrl'] = $featureConfiguration['tokenUrl'];
            }
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
