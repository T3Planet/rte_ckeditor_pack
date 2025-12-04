<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Configuration;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AIConfigurationBuilder
 *
 * Handles the configuration building for CKEditor AI feature.
 */
class AIConfigurationBuilder
{
    /**
     * Build AI configuration from field configuration array
     * Process AI configuration with proper boolean value handling
     * Ensures user settings properly override defaults, especially for nested boolean values
     *
     * @param array $fieldConfigArray Field configuration array
     * @param array $configuration Current configuration array
     * @return array Updated configuration array
     */
    public function buildConfiguration(array $fieldConfigArray, array $configuration): array
    {
        if (!isset($fieldConfigArray['ai'])) {
            return $configuration;
        }

        $aiConfig = $fieldConfigArray['ai'];

        // Normalize user config first before merging
        $aiConfig = $this->normalizeConfiguration($aiConfig);

        // Merge AI configuration, ensuring user values override defaults
        if (isset($configuration['ai'])) {
            $configuration['ai'] = $this->mergeConfiguration($configuration['ai'], $aiConfig);
        } else {
            $configuration['ai'] = $aiConfig;
        }

        // Final normalization after merge
        if (isset($configuration['ai'])) {
            $configuration['ai'] = $this->normalizeConfiguration($configuration['ai']);
        }

        return $configuration;
    }

    /**
     * Recursively merge AI configuration with proper boolean handling
     * Ensures user values properly override defaults, especially for nested boolean values
     *
     * @param array $default Default configuration
     * @param array $user User configuration
     * @return array Merged configuration
     */
    private function mergeConfiguration(array $default, array $user): array
    {
        foreach ($user as $key => $value) {
            // Skip null or empty arrays
            if ($value === null || (is_array($value) && empty($value))) {
                continue;
            }

            if (is_array($value) && isset($default[$key]) && is_array($default[$key])) {
                // Special handling for 'chat' key to prevent duplicate nesting
                if ($key === 'chat') {
                    // For chat, merge models and context separately
                    if (isset($value['models']) && is_array($value['models'])) {
                        if (isset($default[$key]['models']) && is_array($default[$key]['models'])) {
                            $default[$key]['models'] = $this->mergeConfiguration($default[$key]['models'], $value['models']);
                        } else {
                            $default[$key]['models'] = $value['models'];
                        }
                    }
                    if (isset($value['context']) && is_array($value['context'])) {
                        if (isset($default[$key]['context']) && is_array($default[$key]['context'])) {
                            $default[$key]['context'] = $this->mergeConfiguration($default[$key]['context'], $value['context']);
                        } else {
                            $default[$key]['context'] = $value['context'];
                        }
                    }
                } else {
                    // Recursively merge other nested arrays
                    $default[$key] = $this->mergeConfiguration($default[$key], $value);
                }
            } else {
                // Direct override for non-array values (including booleans)
                // Handle empty strings - if user sets empty string, remove from config (use Cloud Services default)
                if ($value === '' && ($key === 'defaultModelId' || $key === 'displayedModels')) {
                    unset($default[$key]);
                } else {
                    $default[$key] = $value;
                }
            }
        }
        return $default;
    }

    /**
     * Normalize AI configuration values (convert strings to proper types)
     *
     * @param array $config Configuration to normalize
     * @return array Normalized configuration
     */
    private function normalizeConfiguration(array $config): array
    {
        // Normalize models configuration
        if (isset($config['chat']['models'])) {
            $models = &$config['chat']['models'];
            
            // Convert modelSelectorAlwaysVisible from string "1"/"0" to boolean
            if (isset($models['modelSelectorAlwaysVisible'])) {
                $models['modelSelectorAlwaysVisible'] = (bool)(int)$models['modelSelectorAlwaysVisible'];
            }
            
            // Convert displayedModels from string to array
            if (isset($models['displayedModels'])) {
                if (is_string($models['displayedModels'])) {
                    $displayedModels = GeneralUtility::trimExplode(',', $models['displayedModels'], true);
                    $models['displayedModels'] = array_filter($displayedModels); // Remove empty values
                }
            }
            
            // Remove defaultModelId if empty or not set (use Cloud Services default)
            if (isset($models['defaultModelId'])) {
                if ($models['defaultModelId'] === '' || $models['defaultModelId'] === null || trim($models['defaultModelId']) === '') {
                    unset($models['defaultModelId']);
                }
            }
            
            // If models object is empty after normalization, set it to empty array (like test.html)
            if (empty($models)) {
                $config['chat']['models'] = [];
            }
        }
        
        // Normalize context configuration
        if (isset($config['chat']['context'])) {
            $context = &$config['chat']['context'];
            $this->normalizeContextConfiguration($context);
        }

        // Clean up any duplicate chat keys (should not happen, but safety check)
        if (isset($config['chat']['chat'])) {
            unset($config['chat']['chat']);
        }

        return $config;
    }

    /**
     * Normalize context configuration (convert string "1"/"0" to boolean)
     *
     * @param array $context Context configuration to normalize
     * @return void
     */
    private function normalizeContextConfiguration(array &$context): void
    {
        foreach ($context as $key => &$value) {
            // Skip non-array values and sources array
            if ($key === 'sources' || !is_array($value)) {
                continue;
            }

            if (isset($value['enabled'])) {
                // Convert string "1"/"0" to boolean
                if (is_string($value['enabled'])) {
                    $value['enabled'] = (bool)(int)$value['enabled'];
                } elseif (is_numeric($value['enabled'])) {
                    $value['enabled'] = (bool)$value['enabled'];
                }
            } else {
                // Recursively normalize nested arrays (for deeply nested structures)
                $this->normalizeContextConfiguration($value);
            }
        }
    }
}
