<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use T3Planet\RteCkeditorPack\DataProvider\ToolbarIcons;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\RteCKEditor\Configuration\CKEditor5Migrator;

class YamlLoadrUtility
{

    public function fetchToolBarItems(string $presetName): string
    {
        $configuration = $this->loadConfigurationFromPreset($presetName);

        $configuration = GeneralUtility::makeInstance(
            CKEditor5Migrator::class,
            $configuration
        )->get();

        if (isset($configuration['editor']['config']) && isset($configuration['editor']['config']['toolbar']['items'])) {

            $items = $configuration['editor']['config']['toolbar']['items'];
            if($items){
               return implode(',',$items);
            }
        }
        return '';
    }


    public function fetchToolBar(string $presetName): array
    {
        $activeItemArray = [];
        $configuration = $this->loadConfigurationFromPreset($presetName);
        if(self::getTypo3MajorVersion() === 14){
            $configuration = GeneralUtility::makeInstance(
            \TYPO3\CMS\RteCKEditor\Configuration\CKEditor5Migrator::class,
            $configuration
            )->get();
        } else{
            $configuration = GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Configuration\CKEditor5Migrator::class,
            $configuration
            )->get();
        }

        if (isset($configuration['editor']['config']) && isset($configuration['editor']['config']['toolbar']['items'])) {

            $items = $configuration['editor']['config']['toolbar']['items'];
            $toolbarIcons = GeneralUtility::makeInstance(ToolbarIcons::class);
            foreach ($items as $value) {
                $icon = $toolbarIcons->getIconByName($value);
                $labelkey = $value;
                if (strpos($value, ':')) {
                    $labelkey = str_replace(':', '_', $value);
                }
                $label = LocalizationUtility::translate('toolbar.item.' . $labelkey, 'rte_ckeditor_pack') ?? $value;
                $activeItemArray[] = [
                    'icon' => $icon,
                    'toolBar' => $value,
                    'label' => $label,
                ];

            }
        }

        return $activeItemArray;

    }

    /**
     * Load a configuration preset from an external resource (currently only YAML is supported).
     * This is the default behaviour and can be overridden by page TSconfig.
     *
     * @return array the parsed configuration
     */
    protected function loadConfigurationFromPreset(string $presetName = ''): array
    {
        $configuration = [];
        if (!empty($presetName) && isset($GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets'][$presetName])) {
            $fileLoader = GeneralUtility::makeInstance(YamlFileLoader::class);
            $configuration = $fileLoader->load($GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets'][$presetName]);
            // For future versions, you should however rely on the "processing" key and not the "proc" key.
            if (is_array($configuration['processing'] ?? null)) {
                $configuration['proc.'] = $this->convertPlainArrayToTypoScriptArray($configuration['processing']);
            }
        }
        return $configuration;
    }

    /**
     * Load YAML configuration for a preset and migrate to CKEditor5 format
     *
     * @param string $presetKey
     * @return array
     */
    public function loadYamlConfiguration(string $presetKey): array
    {
        $configuration =  $this->loadConfigurationFromPreset($presetKey);
        return GeneralUtility::makeInstance(
            CKEditor5Migrator::class,
            $configuration
        )->get();
    }

    /**
     * Returns an array with Typoscript the old way (with dot)
     * Since the functionality in YAML is without the dots, but the new configuration is used without the dots
     * this functionality adds also an explicit = 1 to the arrays
     *
     * @param array $plainArray An array
     * @return array array with TypoScript as usual (with dot)
     */
    protected function convertPlainArrayToTypoScriptArray(array $plainArray)
    {
        $typoScriptArray = [];
        foreach ($plainArray as $key => $value) {
            if (is_array($value)) {
                if (!isset($typoScriptArray[$key])) {
                    $typoScriptArray[$key] = 1;
                }
                $typoScriptArray[$key . '.'] = $this->convertPlainArrayToTypoScriptArray($value);
            } else {
                $typoScriptArray[$key] = $value ?? '';
            }
        }
        return $typoScriptArray;
    }


    /**
     * Get TYPO3 major version
     *
     * @return int
     */
    public static function getTypo3MajorVersion(): int
    {
        return GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();
    }


}
