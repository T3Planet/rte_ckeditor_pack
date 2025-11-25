<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\ViewHelpers;

use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ModuleViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', '', true);
        $this->registerArgument('preset', 'int', '', false);
        $this->registerArgument('isToolbar', 'bool', '', false);
    }

    /**
     * This method returns an bool based on the key
     *
     * @return bool
     */
    public function render(): bool
    {
        $key = $this->arguments['key'];
        $presetUid = (int)$this->arguments['preset'] ?? 0;
        $isToolbar = $this->arguments['isToolbar'] ? true : false;
        
        // Use FeatureRepository with UID
        $featureRepository = GeneralUtility::makeInstance(FeatureRepository::class);
        $feature = $featureRepository->findByPresetUidAndConfigKey($presetUid, $key);
        
        if ($feature) {
            return $feature->isEnable();
        }
        return false;
        
        // Fallback to old Configuration table for backward compatibility
        // $configurationRepository = GeneralUtility::makeInstance(ConfigurationRepository::class);
        // $results = $configurationRepository->findInvisibleRecord($key);
        // $record = array_filter($results, function ($record) use ($presetKey, $isToolbar) {
        //     $presetArray = GeneralUtility::trimExplode(',', $record->getPreset(), true);
        //     return $isToolbar
        //         ? !in_array($presetKey, $presetArray, true)
        //         : in_array($presetKey, $presetArray, true);
        // });
        
        // return $record ? $record[0]->isEnable() : false;
    }
}
