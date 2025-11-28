<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\ViewHelpers;

use T3Planet\RteCkeditorPack\Domain\Repository\ConfigurationRepository;
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
        $this->registerArgument('preset', 'string', '', false);
        $this->registerArgument('isToolbar', 'bool', '', false);
        $this->registerArgument('fieldConfiguration', 'bool', '', false);

    }

    /**
     * This method returns an bool based on the key
     *
     * @return bool
     */
    public function render(): bool
    {
        $key = $this->arguments['key'];
        $preset = $this->arguments['preset'] ?? '';
        $isToolbar = $this->arguments['isToolbar'] ? true : false;
        $fieldConfiguration = $this->arguments['fieldConfiguration'] ? true : false;

        $configurationRepository = GeneralUtility::makeInstance(ConfigurationRepository::class);
        
        if (!$preset) {
            $record = $configurationRepository->findBy(['configKey' => $key])->getFirst();
            if ($record && $fieldConfiguration) {
                $fields = $record->getFields();
                $fieldsTrimmed = trim($fields);
                if (empty($fieldsTrimmed) || $fieldsTrimmed === '0' || $fieldsTrimmed === '0.0') {
                    return false;
                }
                $firstChar = substr($fieldsTrimmed, 0, 1);
                return $firstChar === '{' || $firstChar === '[';
            }
        
            return $record ? $record->isEnable() : false;
        }
        $results = $configurationRepository->findInvisibleRecord($key, $preset, $isToolbar);
        $record = array_filter($results, function ($record) use ($preset, $isToolbar) {
            $presetArray = GeneralUtility::trimExplode(',', $record->getPreset(), true);
            return $isToolbar
                ? !in_array($preset, $presetArray, true)
                : in_array($preset, $presetArray, true);
        });
        return $record ? $record[0]->isEnable() : false;

    }
}
