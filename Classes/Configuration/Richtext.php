<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Configuration;

use T3Planet\RteCkeditorPack\Utility\ProcessingConfigurationUtility;
use TYPO3\CMS\Core\Configuration\Richtext as CoreRichtext;

/**
 * Extended Richtext class for TYPO3 v12 and v13
 * 
 * This class extends the core Richtext class to apply custom processing
 * configuration from the database before the configuration is returned.
 * 
 * For TYPO3 v14, use the AfterRichtextConfigurationPreparedEvent instead.
 */
class Richtext extends CoreRichtext
{
    /**
     * Override getConfiguration to apply custom processing config
     */
    public function getConfiguration(string $table, string $field, int $pid, string $recordType, array $tcaFieldConf): array
    {
        $configuration = parent::getConfiguration($table, $field, $pid, $recordType, $tcaFieldConf);
        $configuration = ProcessingConfigurationUtility::applyProcessingConfig($configuration);
        return $configuration;
    }
}

