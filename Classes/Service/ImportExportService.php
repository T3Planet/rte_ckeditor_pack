<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Service;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use T3Planet\RteCkeditorPack\DataProvider\Modules;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use T3Planet\RteCkeditorPack\Utility\ConfigurationMergeUtility;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;

class ImportExportService
{
    /**
     * YAML key mapping: ConfigKey (lowercase) => YAML key
     */
    private const YAML_KEY_MAP = [
        'highlight' => 'highlight',
        'multilevellist' => 'multiLevelList',
        'realtimecollaboration' => 'collaboration',
        'sourceeditingenhanced' => 'sourceEditing',
        'pastefromofficeenhanced' => 'pasteFromOffice',
        'restrictededitingmode' => 'restrictedEditing',
        'tableofcontents' => 'tableOfContents',
        'wordcount' => 'wordCount',
        'listproperties' => 'list',
        'texttransformation' => 'typing',
    ];

    /**
     * YAML file header template
     */
    private const YAML_HEADER = "# Load default processing options\n"
        . "imports:\n"
        . "    - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Processing.yaml' }\n"
        . "    - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Editor/Base.yaml' }\n"
        . "    - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Editor/Plugins.yaml' }\n"
        . "# For complete documentation see https://ckeditor.com/docs/ckeditor5/latest/features/index.html\n";

    public function __construct(
        protected readonly FeatureRepository $featureRepository
    ) {}

    /**
     * Get TYPO3 major version
     *
     * @return int
     */
    private static function getTypo3MajorVersion(): int
    {
        return GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();
    }

    /**
     * Convert PascalCase ConfigKey to camelCase YAML key
     *
     * @param string $configKey
     * @return string
     */
    public function convertConfigKeyToYamlKey(string $configKey): string
    {
        $configKeyLower = strtolower($configKey);

        // Check if we have a specific mapping
        if (isset(self::YAML_KEY_MAP[$configKeyLower])) {
            return self::YAML_KEY_MAP[$configKeyLower];
        }

        // Convert PascalCase to camelCase
        // First letter lowercase, rest as-is
        return lcfirst($configKey);
    }

    /**
     * Build YAML configuration array from preset and features
     *
     * @param int $presetUid
     * @param string $toolbarItems Comma-separated toolbar items
     * @param array|null $baseYamlConfig Base YAML configuration for core presets (null for custom presets)
     * @return array
     */
    public function buildYamlConfiguration(int $presetUid, string $toolbarItems, ?array $baseYamlConfig = null): array
    {
        $config = [
            'editor' => [
                'config' => []
            ]
        ];

        // Start with base YAML configuration if provided (for core presets)
        if ($baseYamlConfig !== null && is_array($baseYamlConfig)) {
            $config['editor']['config'] = $baseYamlConfig;
        }

        // Merge toolbar items: use database toolbar items if available, otherwise keep YAML base
        if (!empty($toolbarItems)) {
            $toolbarItemsArray = array_filter(array_map('trim', explode(',', $toolbarItems)));
            if (!empty($toolbarItemsArray)) {
                $config['editor']['config']['toolbar'] = [
                    'items' => $toolbarItemsArray
                ];
            }
        } elseif (!isset($config['editor']['config']['toolbar']) && isset($baseYamlConfig['toolbar'])) {
            // Keep YAML toolbar if no database toolbar items
            $config['editor']['config']['toolbar'] = $baseYamlConfig['toolbar'];
        }

        // Use optimized query to fetch only enabled features at database level
        $features = $this->featureRepository->findEnabledByPresetUid($presetUid);
        $mergeUtility = null;

        foreach ($features as $feature) {
            $configKey = $feature->getConfigKey();
            $fields = $feature->getFields();
            
            if (empty($fields)) {
                continue;
            }

            $moduleConfiguration = json_decode($fields, true);
            if (!is_array($moduleConfiguration) || empty($moduleConfiguration)) {
                continue;
            }

            // Special handling for Font configKey - fontFamily and fontSize are separate in YAML
            if ($configKey === 'Font') {
                $config['editor']['config'] = array_merge($config['editor']['config'] ?? [],$moduleConfiguration);
            } else {
                // For other features, map ConfigKey to YAML key (preserves camelCase)
                $yamlKey = $this->convertConfigKeyToYamlKey($configKey);

                if (isset($moduleConfiguration[$yamlKey]) && is_array($moduleConfiguration[$yamlKey])) {
                    $moduleConfiguration = $moduleConfiguration[$yamlKey];
                }

                // If base YAML config exists and this key exists in base, merge them
                if ($baseYamlConfig !== null && isset($baseYamlConfig[$yamlKey])) {
                    if ($mergeUtility === null) {
                        $mergeUtility = GeneralUtility::makeInstance(ConfigurationMergeUtility::class);
                    }

                    $existingConfig = $config['editor']['config'][$yamlKey] ?? $baseYamlConfig[$yamlKey];
                    $config['editor']['config'][$yamlKey] = $mergeUtility->mergeRecursiveDistinct(
                        $existingConfig,
                        $moduleConfiguration
                    );
                } else {
                    $config['editor']['config'][$yamlKey] = $moduleConfiguration;
                }
            }
        }
        
        return $config;
    }

    /**
     * Load original YAML file and add only new database features
     *
     * @param string $presetKey
     * @param int $presetUid
     * @param string $toolbarItems
     * @return string
     */
    public function loadAndEnhanceYamlFile(string $presetKey, int $presetUid, string $toolbarItems): string
    {
        if (empty($presetKey) || !isset($GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets'][$presetKey])) {
            throw new \Exception('YAML file not found for preset: ' . $presetKey);
        }

        $yamlFilePath = $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets'][$presetKey];
        $absoluteFilePath = GeneralUtility::getFileAbsFileName($yamlFilePath);

        if (empty($absoluteFilePath) || !file_exists($absoluteFilePath)) {
            throw new \Exception('YAML file does not exist: ' . $absoluteFilePath);
        }

        // Load original YAML file content (needed for preserving header)
        $originalContent = file_get_contents($absoluteFilePath);
        if ($originalContent === false) {
            throw new \Exception('Failed to read YAML file: ' . $absoluteFilePath);
        }

        // Load configuration using YamlFileLoader
        $fileLoader = GeneralUtility::makeInstance(YamlFileLoader::class);
        $configuration = $fileLoader->load($yamlFilePath);

        // Migrate configuration using CKEditor5Migrator
        if (self::getTypo3MajorVersion() === 14) {
            $configuration = GeneralUtility::makeInstance(
                \TYPO3\CMS\RteCKEditor\Configuration\CKEditor5Migrator::class,
                $configuration
            )->get();
        } else {
            $configuration = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Configuration\CKEditor5Migrator::class,
                $configuration
            )->get();
        }

        $originalEditorConfig = $configuration['editor']['config'] ?? [];

        // Get database features
        $databaseFeatures = $this->getFeaturesFromDB($presetUid);
        
        // Get database toolbar items if different from YAML
        $databaseToolbarItems = null;
        if (!empty($toolbarItems)) {
            $toolbarItemsArray = array_filter(array_map('trim', explode(',', $toolbarItems)));
            $yamlToolbarItems = $originalEditorConfig['toolbar']['items'] ?? [];

            // Only use database toolbar if it's different
            if (!empty($toolbarItemsArray) && $toolbarItemsArray !== $yamlToolbarItems) {
                $databaseToolbarItems = $toolbarItemsArray;
            }
        }

        // Build enhanced config: start with original, but database features override YAML
        $enhancedConfig = $originalEditorConfig;

        // Update toolbar if database has different items
        if ($databaseToolbarItems !== null) {
            $enhancedConfig['toolbar'] = ['items' => $databaseToolbarItems];
        }

        // Override YAML features with database features if they exist in database
        // If a feature exists in both database and YAML, use database version
        foreach ($databaseFeatures as $yamlKey => $featureConfig) {
            $enhancedConfig[$yamlKey] = $featureConfig;
        }
        
        // Rebuild YAML: preserve original structure, replace editor.config section
        return $this->rebuildYamlWithNewConfig($originalContent, $enhancedConfig);
    }

    /**
     * Get database features formatted for YAML export
     *
     * @param int $presetUid
     * @return array
     */
    public function getFeaturesFromDB(int $presetUid): array
    {
        $features = $this->featureRepository->findEnabledByPresetUid($presetUid);
        $databaseFeatures = [];
        foreach ($features as $feature) {
            $configKey = $feature->getConfigKey();
            $fields = $feature->getFields();
            if (empty($fields)) {
                continue;
            }
            $moduleConfiguration = json_decode($fields, true);
            if (!is_array($moduleConfiguration) || empty($moduleConfiguration)) {
                continue;
            }
            // Handle Font configKey
            if ($configKey === 'Font') {
                $databaseFeatures = array_merge($databaseFeatures ?? [],$moduleConfiguration);
            } else {
                // Map ConfigKey to YAML key (preserves camelCase)
                $yamlKey = $this->convertConfigKeyToYamlKey($configKey);

                // Handle nested structures
                if (isset($moduleConfiguration[$yamlKey]) && is_array($moduleConfiguration[$yamlKey])) {
                    $moduleConfiguration = $moduleConfiguration[$yamlKey];
                }

                $databaseFeatures[$yamlKey] = $moduleConfiguration;
            }
        }
        return $databaseFeatures;
    }

    /**
     * Rebuild YAML content preserving original structure, replacing only editor.config
     *
     * @param string $originalContent
     * @param array $newEditorConfig
     * @return string
     */
    public function rebuildYamlWithNewConfig(string $originalContent, array $newEditorConfig): string
    {
        $lines = explode("\n", $originalContent);
        $result = [];
        $inEditorSection = false;
        $inConfigSection = false;
        $configIndent = 0;
        $editorSectionFound = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Detect start of editor section
            if ($trimmedLine === 'editor:' || str_starts_with($trimmedLine, 'editor:')) {
                $inEditorSection = true;
                $result[] = $line;
                continue;
            }

            // Detect start of config section
            if ($inEditorSection && ($trimmedLine === 'config:' || str_starts_with($trimmedLine, 'config:'))) {
                $inConfigSection = true;
                $editorSectionFound = true;
                $configIndent = strlen($line) - strlen(ltrim($line));
                $result[] = $line;

                // Add new config content
                $configYaml = Yaml::dump($newEditorConfig, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
                $indentedConfig = preg_replace('/^/m', str_repeat(' ', $configIndent + 2), $configYaml);
                $result[] = $indentedConfig;
                continue;
            }

            // Skip lines inside config section (we're replacing it)
            if ($inConfigSection) {
                $currentIndent = strlen($line) - strlen(ltrim($line));
                // Stop when we find a line at same or less indent level than config (end of config section)
                if (!empty($trimmedLine) && $currentIndent <= $configIndent) {
                    $inConfigSection = false;
                    $inEditorSection = false;
                    // Don't add this line as it's the end of editor section
                    continue;
                }
                // Skip all lines inside config
                continue;
            }

            // Add all other lines as-is
            $result[] = $line;
        }

        // If we never found an editor:config section, append it at the end
        if (!$editorSectionFound) {
            $result[] = "editor:";
            $result[] = "  config:";
            $configYaml = Yaml::dump($newEditorConfig, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
            $indentedConfig = preg_replace('/^/m', '    ', $configYaml);
            $result[] = $indentedConfig;
        }

        return implode("\n", $result);
    }

    /**
     * Format YAML content with proper structure and comments
     *
     * @param array $config
     * @param string|null $yamlHeader Custom header from YAML file (for core presets)
     * @return string
     */
    public function formatYamlContent(array $config, ?string $yamlHeader = null): string
    {
        // Use custom header from YAML file if provided, otherwise use static header
        $header = $yamlHeader ?? self::YAML_HEADER;

        // Dump the editor config part with proper indentation
        $editorConfig = $config['editor']['config'] ?? [];

        if (!empty($editorConfig)) {
            // Dump only the config part (without editor wrapper)
            $configYaml = Yaml::dump($editorConfig, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

            // Add editor and config keys with proper indentation
            $indentedConfig = preg_replace('/^/m', '    ', $configYaml);

            // If header already contains 'editor:', just append the config
            // Otherwise, add 'editor:\n  config:\n' before the config
            if (str_contains($header, 'editor:')) {
                return $header . $indentedConfig;
            }

            return $header . "editor:\n  config:\n" . $indentedConfig;
        }

        // If header already contains 'editor:', just append empty config
        if (str_contains($header, 'editor:')) {
            return $header . "  config: {}\n";
        }

        return $header . "editor:\n  config: {}\n";
    }


    public function importFeaturesFromYaml(int $presetUid, array $editorConfig): void
    {
        
        // Map YAML keys to internal config_key names where they differ
        $yamlToConfigKeyMap = [
            'typing' => 'TextTransformation',
            'sourceEditing' => 'SourceEditingEnhanced',
            'collaboration' => 'RealTimeCollaboration'
        ];
        
        $fontPayload = [];

        foreach ($editorConfig as $key => $value) {
            // Translate YAML key (e.g. 'typing') to internal config_key when needed
            $lookupKey = $yamlToConfigKeyMap[$key] ?? $key;

            $moduleConfiguration = GeneralUtility::makeInstance(Modules::class)->getItemByConfigKey($lookupKey, true);
            if (empty($moduleConfiguration)) {
                continue;
            }
            $configKey = $moduleConfiguration['configuration']['config_key'];

            // Collect all font-related keys first (fontFamily, fontSize, fontColor, fontBackgroundColor)
            if ($configKey === 'Font') {
                $fontPayload[$key] = $value;
                continue;
            }

            $wrapped = [$key => $value];
            $feature = $this->featureRepository->findByPresetUidAndConfigKey($presetUid, $configKey);
            if ($feature) {
                $feature->setEnable(true);
                $feature->setFields(json_encode($wrapped));
                $this->featureRepository->update($feature);
            } else {
                $feature = GeneralUtility::makeInstance(Feature::class);
                $feature->setPresetUid($presetUid);
                $feature->setConfigKey($configKey);
                $feature->setEnable(true);
                $feature->setFields(json_encode($wrapped));
                $this->featureRepository->add($feature);
            }
        }

        // Save Font once
        if (!empty($fontPayload)) {
            $feature = $this->featureRepository->findByPresetUidAndConfigKey($presetUid, 'Font')
                ?? GeneralUtility::makeInstance(Feature::class);

            $feature->setPresetUid($presetUid);
            $feature->setConfigKey('Font');
            $feature->setEnable(true);
            $feature->setFields(json_encode($fontPayload));
            if ($feature->getUid() ?? null) {
                $this->featureRepository->update($feature);
            } else {
                $this->featureRepository->add($feature);
            }
        }
    }
}
