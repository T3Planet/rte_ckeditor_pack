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
 * TYPO3 v14+ readonly Richtext extension for editor context metadata.
 *
 * Core Richtext is readonly on v14, so this class must be readonly as well.
 * Processing config is applied via AfterRichtextConfigurationPreparedEvent.
 */
readonly class RichtextV14 extends CoreRichtext
{
    public function getConfiguration(string $table, string $field, int $pid, string $recordType, array $tcaFieldConf): array
    {
        $configuration = parent::getConfiguration($table, $field, $pid, $recordType, $tcaFieldConf);

        return ProcessingConfigurationUtility::injectEditorContext(
            $configuration,
            $table,
            $field,
            $pid,
            $recordType,
        );
    }
}
