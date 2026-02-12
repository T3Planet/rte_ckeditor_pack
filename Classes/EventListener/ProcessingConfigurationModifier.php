<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Utility\ProcessingConfigurationUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterRichtextConfigurationPreparedEvent;

/**
 * Event listener to modify RTE processing configuration from database (TYPO3 v14 only)
 * 
 * This listener allows overriding the Processing.yaml configuration
 * with custom processing settings stored in the preset database table.
 * 
 * For TYPO3 v12 and v13, the Richtext class is extended instead.
 */
#[AsEventListener('rte_ckeditor_pack/processing-configuration-modifier')]
final class ProcessingConfigurationModifier
{
    /**
     * Modify the RTE configuration by applying custom processing config from database
     */
    public function __invoke(AfterRichtextConfigurationPreparedEvent $event): void
    {
        $configuration = $event->getConfiguration();
        $configuration = ProcessingConfigurationUtility::applyProcessingConfig($configuration);
        $event->setConfiguration($configuration);
    }
}
