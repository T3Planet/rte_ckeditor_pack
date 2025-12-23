<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class to apply processing configuration from database
 */
class ProcessingConfigurationUtility
{
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
     * Try to detect the preset name from configuration or backend context
     * 
     * @param array $configuration
     * @return string
     */
    private static function detectPresetName(array $configuration): string
    {
        if (isset($configuration['preset']) && is_string($configuration['preset'])) {
            return $configuration['preset'];
        }
        return 'default';
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

