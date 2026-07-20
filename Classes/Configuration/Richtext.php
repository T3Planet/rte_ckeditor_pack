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
 * TYPO3 v12/v13 Richtext extension for processing config and editor context metadata.
 */
class Richtext extends CoreRichtext
{
    public function getConfiguration(string $table, string $field, int $pid, string $recordType, array $tcaFieldConf): array
    {
        $configuration = parent::getConfiguration($table, $field, $pid, $recordType, $tcaFieldConf);
        $configuration = ProcessingConfigurationUtility::injectEditorContext(
            $configuration,
            $table,
            $field,
            $pid,
            $recordType,
        );

        return ProcessingConfigurationUtility::applyAll($configuration);
    }
}
