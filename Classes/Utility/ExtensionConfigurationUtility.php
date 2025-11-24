<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExtensionConfigurationUtility
{
    private const EXTENSION_KEY = 'rte_ckeditor_pack';

    /**
     * Get all extension configuration
     *
     * @return array
     */
    public static function getAll(): array
    {
        try {
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            return $extensionConfiguration->get(self::EXTENSION_KEY) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get a specific configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $config = self::getAll();
        return $config[$key] ?? $default;
    }

    /**
     * Set extension configuration values
     *
     * @param array $configuration Array of key-value pairs to set
     * @return bool True on success, false on failure
     */
    public static function set(array $configuration): bool
    {
        try {
            $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
            
            // Get current configuration
            $currentConfig = $configurationManager->getConfigurationValueByPath('EXTENSIONS/' . self::EXTENSION_KEY) ?? [];
            
            // Merge with new configuration
            $newConfig = array_merge($currentConfig, $configuration);
            
            // Set the configuration
            $configurationManager->setLocalConfigurationValueByPath(
                'EXTENSIONS/' . self::EXTENSION_KEY,
                $newConfig
            );
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

