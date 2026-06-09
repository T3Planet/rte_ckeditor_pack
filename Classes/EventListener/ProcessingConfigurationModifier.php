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
use TYPO3\CMS\Core\Configuration\Event\AfterRichtextConfigurationPreparedEvent;

/**
 * Applies RTE processing and HTML-support rules from the database (TYPO3 v14+ only).
 *
 * For TYPO3 v12 and v13, the Richtext class is extended instead.
 */
final class ProcessingConfigurationModifier
{
    public function __invoke(AfterRichtextConfigurationPreparedEvent $event): void
    {
        $event->setConfiguration(
            ProcessingConfigurationUtility::applyAll($event->getConfiguration())
        );
    }
}
