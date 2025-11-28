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

class HasConfigurationViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', 'Configuration key to check', true);
    }

    /**
     * This method returns true if configuration exists and has saved fields/data
     *
     * @return bool
     */
    public function render(): bool
    {
        $key = $this->arguments['key'];
        $configurationRepository = GeneralUtility::makeInstance(ConfigurationRepository::class);
        
        $record = $configurationRepository->findBy(['configKey' => $key])->getFirst();
        
        // Return true if record exists and has fields saved (non-empty and not '0')
        if ($record) {
            $fields = $record->getFields();
            $fieldsTrimmed = trim($fields);
            
            // Check if fields is not empty, not '0', and contains actual configuration data
            // In database, '0' means no configuration saved, while JSON string means configuration exists
            if (empty($fieldsTrimmed) || $fieldsTrimmed === '0' || $fieldsTrimmed === '0.0') {
                return false;
            }
            
            // Check if fields contains JSON structure (starts with { or [)
            $firstChar = substr($fieldsTrimmed, 0, 1);
            return $firstChar === '{' || $firstChar === '[';
        }
        
        return false;
    }
}

