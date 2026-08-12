<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use T3Planet\RteCkeditorPack\Configuration\EditorConfigurationBuilder;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class to apply processing configuration from database
 */
class ProcessingConfigurationUtility
{
    /**
     * Internal metadata key forwarded via editor.config for Visual Editor compatibility.
     */
    public const RICH_TEXT_PRESET_METADATA_KEY = '_rtePreset';

    /**
     * Internal metadata key with table/field context for cross-editor collaboration channel IDs.
     */
    public const RICH_TEXT_EDITOR_CONTEXT_KEY = '_rteEditorContext';

    /**
     * Apply custom processing configuration from database to the configuration array
     * 
     * @param array $configuration The RTE configuration array
     * @return array Modified configuration with custom processing config applied
     */
    public static function applyProcessingConfig(array $configuration): array
    {
        $presetRepository = GeneralUtility::makeInstance(PresetRepository::class);
        $presetName = self::detectPresetName($configuration);
        
        $preset = null;
        if ($presetName) {
            $preset = $presetRepository->findByPresetKey($presetName);
            if (!$preset) {
                $preset = $presetRepository->findByUsage($presetName);
            }
        }
        
        if ($preset && $preset->getProcessingConfig()) {
            $customProcessingConfig = json_decode($preset->getProcessingConfig(), true);
            
            if (is_array($customProcessingConfig) && !empty($customProcessingConfig)) {
                $customProcessingConfig = self::convertStringsToArrays($customProcessingConfig);
                
                $customProcessingConfig = self::cleanEmptyStringValues($customProcessingConfig);
                
                if (!empty($customProcessingConfig)) {
                    if (!isset($configuration['processing']['mode'])){
                        $configuration['processing']['mode'] ='default';
                    }
                    // Merge custom processing config with existing configuration
                    if (isset($configuration['processing']) && is_array($configuration['processing'])) {
                        // Use smart merge that preserves arrays when custom value is empty string
                        $configuration['processing'] = self::smartMergeProcessingConfig(
                            $configuration['processing'],
                            $customProcessingConfig
                        );
                    } else {
                        $configuration['processing'] = $customProcessingConfig;
                    }
                    $configuration['proc.'] = self::convertPlainArrayToTypoScriptArray(
                        $configuration['processing']
                    );
                }
            }
        }
        
        return $configuration;
    }

    /**
     * Apply database processing config and HTML-support processing rules.
     */
    public static function applyAll(array $configuration): array
    {
        $configuration = self::applyProcessingConfig($configuration);
        $configuration = self::applyHtmlSupportConfig($configuration);
        // Marker allowTags + GHS disallow only (no editor height/CSS defaults).
        $configuration = GeneralUtility::makeInstance(EditorConfigurationBuilder::class)
            ->ensureCollaborationMarkerProcessing($configuration);
        $configuration = self::applyMathEquationsProcessing($configuration);
        $configuration = self::applyRestrictedEditingProcessing($configuration);

        return self::ensureEditorConfigurationStructure($configuration);
    }

    /**
     * Keep restricted-editing exception markup through TYPO3 RTE processing.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function applyRestrictedEditingProcessing(array $configuration): array
    {
        if (!self::isRestrictedEditingEnabled($configuration)) {
            return $configuration;
        }

        if (!isset($configuration['processing']) || !is_array($configuration['processing'])) {
            $configuration['processing'] = [];
        }

        $configuration['processing']['allowTags'] = array_values(array_unique(array_merge(
            ['span', 'div'],
            (array)($configuration['processing']['allowTags'] ?? [])
        )));
        $configuration['processing']['allowAttributes'] = array_values(array_unique(array_merge(
            ['class'],
            (array)($configuration['processing']['allowAttributes'] ?? [])
        )));

        $configuration = self::ensureDefaultProcessingMode($configuration);

        return self::rebuildProcFromProcessing($configuration);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function isRestrictedEditingEnabled(array $configuration): bool
    {
        if (isset($configuration['restrictedEditing']) && is_array($configuration['restrictedEditing'])) {
            return true;
        }

        foreach ($configuration['importModules'] ?? [] as $import) {
            $exports = $import['exports'] ?? [];
            if (is_string($exports)) {
                $exports = GeneralUtility::trimExplode(',', $exports, true);
            }
            foreach ((array)$exports as $export) {
                if (
                    str_contains((string)$export, 'RestrictedEditing')
                    || str_contains((string)$export, 'StandardEditingMode')
                ) {
                    return true;
                }
            }
        }

        $toolbarItems = $configuration['toolbar']['items'] ?? [];
        foreach ((array)$toolbarItems as $item) {
            if (!is_string($item)) {
                continue;
            }
            if (str_starts_with($item, 'restrictedEditing')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Allow MathML tags/attributes when Math Equations is enabled so formulas survive RTE processing.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function applyMathEquationsProcessing(array $configuration): array
    {
        if (!self::isMathEquationsEnabled($configuration)) {
            return $configuration;
        }

        $mathTags = [
            'math', 'semantics', 'annotation', 'annotation-xml', 'mrow', 'mi', 'mn', 'mo', 'ms', 'mtext',
            'mspace', 'mfrac', 'msqrt', 'mroot', 'msub', 'msup', 'msubsup', 'munder', 'mover', 'munderover',
            'mtable', 'mtr', 'mtd', 'menclose', 'mstyle', 'mpadded', 'mphantom', 'mfenced', 'mmultiscripts',
            'mprescripts', 'none', 'merror', 'mglyph', 'mlabeledtr',
        ];
        $mathAttributes = [
            'xmlns', 'encoding', 'displaystyle', 'mathvariant', 'mathsize', 'mathcolor', 'mathbackground',
            'linebreak', 'stretchy', 'fence', 'separator', 'lspace', 'rspace', 'maxsize', 'minsize',
            'movablelimits', 'accent', 'accentunder', 'columnspan', 'rowspan', 'class', 'style', 'id',
            'data-mathml', 'alt', 'role', 'aria-label',
        ];

        if (!isset($configuration['processing']) || !is_array($configuration['processing'])) {
            $configuration['processing'] = [];
        }

        $configuration['processing']['allowTags'] = array_values(array_unique(array_merge(
            $mathTags,
            (array)($configuration['processing']['allowTags'] ?? [])
        )));
        $configuration['processing']['allowAttributes'] = array_values(array_unique(array_merge(
            $mathAttributes,
            (array)($configuration['processing']['allowAttributes'] ?? [])
        )));
        $configuration['processing']['allowTagsOutside'] = array_values(array_unique(array_merge(
            ['math', 'img'],
            (array)($configuration['processing']['allowTagsOutside'] ?? [])
        )));

        // Core Processing.yaml denies img on the DB HTMLparser; MathType image mode needs it.
        $configuration = self::removeDeniedTags($configuration, ['img']);

        $configuration = self::ensureDefaultProcessingMode($configuration);

        return self::rebuildProcFromProcessing($configuration);
    }

    /**
     * Drop listed tags from processing denyTags / HTMLparser_db.denyTags (CSV or list).
     *
     * @param array<string, mixed> $configuration
     * @param list<string> $tags
     * @return array<string, mixed>
     */
    private static function removeDeniedTags(array $configuration, array $tags): array
    {
        $tags = array_map('strtolower', $tags);

        $stripFromList = static function (mixed $value) use ($tags): mixed {
            if (is_string($value)) {
                $parts = GeneralUtility::trimExplode(',', strtolower($value), true);
                $parts = array_values(array_diff($parts, $tags));
                return implode(',', $parts);
            }
            if (is_array($value)) {
                return array_values(array_filter(
                    $value,
                    static fn ($item): bool => !in_array(strtolower((string)$item), $tags, true)
                ));
            }
            return $value;
        };

        if (isset($configuration['processing']['denyTags'])) {
            $configuration['processing']['denyTags'] = $stripFromList($configuration['processing']['denyTags']);
        }
        if (isset($configuration['processing']['HTMLparser_db']['denyTags'])) {
            $configuration['processing']['HTMLparser_db']['denyTags'] = $stripFromList(
                $configuration['processing']['HTMLparser_db']['denyTags']
            );
        }

        return $configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function isMathEquationsEnabled(array $configuration): bool
    {
        // Prefer active editor config (works in unit tests / no Extbase context).
        if (self::configurationDeclaresMathType($configuration)) {
            return true;
        }

        try {
            $presetRepository = GeneralUtility::makeInstance(PresetRepository::class);
            $featureRepository = GeneralUtility::makeInstance(FeatureRepository::class);

            $presetName = self::detectPresetName($configuration);
            $preset = null;
            if ($presetName) {
                $preset = $presetRepository->findByPresetKey($presetName) ?? $presetRepository->findByUsage($presetName);
            }

            $presetUid = $preset?->getUid();
            if ($presetUid !== null) {
                $feature = $featureRepository->findByPresetUidAndConfigKey((int)$presetUid, 'MathEquations');
                if ($feature !== null) {
                    return $feature->isEnable();
                }
            }

            $features = $featureRepository->findByConfigKey('MathEquations');
            foreach ($features as $feature) {
                if ($feature->isEnable()) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Unit tests and early boot may lack Extbase persistence.
            return false;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function configurationDeclaresMathType(array $configuration): bool
    {
        if (!empty($configuration['mathTypeParameters']) || !empty($configuration['editor']['config']['mathTypeParameters'])) {
            return true;
        }

        $toolbar = $configuration['editor']['config']['toolbar']
            ?? $configuration['toolbar']
            ?? null;
        if (!is_array($toolbar)) {
            return false;
        }

        $flatItems = [];
        array_walk_recursive($toolbar, static function ($value) use (&$flatItems): void {
            if (is_string($value)) {
                $flatItems[] = $value;
            }
        });

        return in_array('MathType', $flatItems, true) || in_array('ChemType', $flatItems, true);
    }

    /**
     * Ensure editor structure required by Visual Editor and database-only presets.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function ensureEditorConfigurationStructure(array $configuration): array
    {
        if (!isset($configuration['editor']) || !is_array($configuration['editor'])) {
            $configuration['editor'] = [];
        }
        if (!isset($configuration['editor']['config']) || !is_array($configuration['editor']['config'])) {
            $configuration['editor']['config'] = [];
        }
        if (!isset($configuration['editor']['externalPlugins']) || !is_array($configuration['editor']['externalPlugins'])) {
            $configuration['editor']['externalPlugins'] = [];
        }

        if (
            isset($configuration['preset'])
            && is_string($configuration['preset'])
            && $configuration['preset'] !== ''
            && !isset($configuration['editor']['config'][self::RICH_TEXT_PRESET_METADATA_KEY])
        ) {
            $configuration['editor']['config'][self::RICH_TEXT_PRESET_METADATA_KEY] = $configuration['preset'];
        }

        return $configuration;
    }

    /**
     * Attach table/field context to editor config (used by Visual Editor and FormEngine).
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function injectEditorContext(
        array $configuration,
        string $table,
        string $field,
        int $pid,
        string $recordType,
    ): array {
        $configuration = self::ensureEditorConfigurationStructure($configuration);
        $configuration['editor']['config'][self::RICH_TEXT_EDITOR_CONTEXT_KEY] = [
            'table' => $table,
            'field' => $field,
            'pid' => $pid,
            'recordType' => $recordType,
        ];

        return $configuration;
    }

    /**
     * Merge General HTML Support from the database and sync embed tags to processing.
     */
    public static function applyHtmlSupportConfig(array $configuration): array
    {
        $htmlSupport = self::resolveHtmlSupportFromDatabase($configuration);
        if ($htmlSupport !== null) {
            $configuration = GeneralUtility::makeInstance(EditorConfigurationBuilder::class)
                ->addHtmlSupportSettings($configuration, $htmlSupport);
        }

        if (!isset($configuration['processing']) || !is_array($configuration['processing'])) {
            $configuration['processing'] = [];
        }

        if ($htmlSupport !== null || self::hasHtmlSupportConfiguration($configuration)) {
            $configuration = HtmlSupportProcessingUtility::syncProcessing($configuration);
            $configuration = self::ensureDefaultProcessingMode($configuration);

            return self::rebuildProcFromProcessing($configuration);
        }

        return $configuration;
    }

    /**
     * Ensure RteHtmlParser receives a transformation mode (matches core Richtext fallback).
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function ensureDefaultProcessingMode(array $configuration): array
    {
        if (!isset($configuration['processing']) || !is_array($configuration['processing'])) {
            $configuration['processing'] = [];
        }

        $hasProcessingMode = isset($configuration['processing']['mode']) && $configuration['processing']['mode'] !== '';
        $hasProcessingOverruleMode = isset($configuration['processing']['overruleMode'])
            && $configuration['processing']['overruleMode'] !== '';

        if (!$hasProcessingMode && !$hasProcessingOverruleMode) {
            $configuration['processing']['mode'] = 'default';
        }

        return $configuration;
    }

    /**
     * Rebuild proc. TypoScript array from the processing configuration array.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function rebuildProcFromProcessing(array $configuration): array
    {
        if (is_array($configuration['processing'] ?? null)) {
            $configuration['proc.'] = self::convertPlainArrayToTypoScriptArray($configuration['processing']);
        }

        if (
            !isset($configuration['proc.']['mode'])
            && !isset($configuration['proc.']['overruleMode'])
        ) {
            $configuration['proc.']['overruleMode'] = 'default';
        }

        return $configuration;
    }
    
    /**
     * Try to detect the preset name from configuration or backend context
     * 
     * @param array $configuration
     * @return string
     */
    private static function detectPresetName(array $configuration): string
    {
        if (isset($configuration['preset']) && is_string($configuration['preset']) && $configuration['preset'] !== '') {
            return $configuration['preset'];
        }

        return 'default';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveHtmlSupportFromDatabase(array $configuration): ?array
    {
        $presetRepository = GeneralUtility::makeInstance(PresetRepository::class);
        $featureRepository = GeneralUtility::makeInstance(FeatureRepository::class);
        $presetName = self::detectPresetName($configuration);

        $preset = $presetRepository->findByPresetKey($presetName)
            ?? $presetRepository->findByUsage($presetName);

        if ($preset === null) {
            return null;
        }

        $presetUid = $preset->getUid();
        if ($presetUid === null || $presetUid <= 0) {
            return null;
        }

        foreach ($featureRepository->findEnabledByPresetUid($presetUid) as $feature) {
            if ($feature->getConfigKey() !== 'HtmlSupport' || !$feature->isEnable()) {
                continue;
            }

            $fields = $feature->getFields();
            if ($fields === '') {
                return null;
            }

            $decoded = json_decode($fields, true);
            if (!is_array($decoded['htmlSupport'] ?? null)) {
                return null;
            }

            return $decoded['htmlSupport'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function hasHtmlSupportConfiguration(array $configuration): bool
    {
        if (isset($configuration['htmlSupport']) && is_array($configuration['htmlSupport'])) {
            return true;
        }

        return isset($configuration['editor']['config']['htmlSupport'])
            && is_array($configuration['editor']['config']['htmlSupport']);
    }
    
    
    /**
     * Convert comma-separated strings to arrays for specific processing config keys
     * 
     * Keys like allowTags, allowTagsOutside, allowAttributes, allowedClasses should be arrays,
     * but might come from the database as comma-separated strings
     * 
     * @param array $config
     * @return array
     */
    private static function convertStringsToArrays(array $config): array
    {
        // Keys that should be arrays (comma-separated strings will be converted)
        $arrayKeys = ['allowTags', 'allowTagsOutside', 'allowAttributes', 'allowedClasses'];
        
        foreach ($arrayKeys as $key) {
            if (isset($config[$key]) && is_string($config[$key])) {
                $value = trim($config[$key]);
                if ($value === '') {
                    unset($config[$key]);
                } else {
                    $config[$key] = GeneralUtility::trimExplode(',', $value, true);
                }
            }
        }
        
        return $config;
    }
    
    /**
     * Clean up empty string values from configuration
     * Empty strings should not overwrite arrays, so we remove them
     * 
     * @param array $config
     * @return array
     */
    private static function cleanEmptyStringValues(array $config): array
    {
        $cleaned = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $cleaned[$key] = self::cleanEmptyStringValues($value);
                // Only include if not empty after cleaning
                if (!empty($cleaned[$key])) {
                    $cleaned[$key] = $cleaned[$key];
                } else {
                    unset($cleaned[$key]);
                }
            } elseif ($value !== '' && $value !== null) {
                // Only include non-empty, non-null values
                $cleaned[$key] = $value;
            }
            // Skip empty strings - they would overwrite arrays
        }
        return $cleaned;
    }
    
    /**
     * Smart merge that handles empty strings vs arrays correctly
     * 
     * If the default has an array and custom has empty string, keep the array
     * If the default has an array and custom has an array, merge them
     * If the default has a value and custom has a value, use custom
     * 
     * @param array $defaultConfig
     * @param array $customConfig
     * @return array
     */
    private static function smartMergeProcessingConfig(array $defaultConfig, array $customConfig): array
    {
        $merged = $defaultConfig;
        
        foreach ($customConfig as $key => $customValue) {
            if (is_array($customValue)) {
                // If both are arrays, merge recursively
                if (isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = self::smartMergeProcessingConfig($merged[$key], $customValue);
                } else {
                    $merged[$key] = $customValue;
                }
            } elseif ($customValue !== '' && $customValue !== null) {
                // Only overwrite with non-empty values
                $merged[$key] = $customValue;
            }
        }
        
        return $merged;
    }
    
    /**
     * Convert plain array to TypoScript array format (with dots)
     * 
     * This matches the conversion done in TYPO3 core's Richtext class
     * 
     * @param array $plainArray
     * @return array
     */
    private static function convertPlainArrayToTypoScriptArray(array $plainArray): array
    {
        $typoScriptArray = [];
        foreach ($plainArray as $key => $value) {
            if (is_array($value)) {
                if (!isset($typoScriptArray[$key])) {
                    $typoScriptArray[$key] = 1;
                }
                $typoScriptArray[$key . '.'] = self::convertPlainArrayToTypoScriptArray($value);
            } else {
                $typoScriptArray[$key] = $value ?? '';
            }
        }
        return $typoScriptArray;
    }
}

