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
        $this->registerArgument('fieldConfiguration', 'bool', '', false);

    }

    public function render(): bool
    {
        $key = $this->arguments['key'];
        $presetUid = (int)$this->arguments['preset'] ?? 0;
        $isToolbar = $this->arguments['isToolbar'] ? true : false;
        $fieldConfiguration = $this->arguments['fieldConfiguration'] ? true : false;

        $featureRepository = GeneralUtility::makeInstance(FeatureRepository::class);
        $record = $featureRepository->findByPresetUidAndConfigKey($presetUid, $key);
        
        if ($record && $fieldConfiguration) {
            $fields = $record->getFields();
            $fieldsTrimmed = trim($fields);
            if (empty($fieldsTrimmed) || $fieldsTrimmed === '0' || $fieldsTrimmed === '0.0') {
                return false;
            }
            $firstChar = substr($fieldsTrimmed, 0, 1);
            return $firstChar === '{' || $firstChar === '[';
        }
        if($isToolbar && $record){
            return $record->isEnable() ? false : true;
        }
        return $record ? $record->isEnable() : false;

    }
}
