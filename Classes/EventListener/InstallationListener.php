<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\DataProvider\Modules;
use T3Planet\RteCkeditorPack\Domain\Repository\ConfigurationRepository;
use TYPO3\CMS\Core\Package\Event\AfterPackageActivationEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class InstallationListener
{
    public function __construct(
        protected ConfigurationRepository $configurationRepository,
    ) {}

    public function __invoke(AfterPackageActivationEvent $event)
    {

        if ($event->getPackageKey() === 'rte_ckeditor_pack') {
            $this->executeInstall();
        }
    }

    private function executeInstall(): void
    {
        $allModules = GeneralUtility::makeInstance(Modules::class)->getAllItems();
        if ($allModules) {
            $rows = [];
            foreach ($allModules as $module) {
                if (isset($module['config_key'])) {
                    $rows[] = [
                        'crdate' => time(),
                        'enable' => isset($module['default']) ? $module['default'] : false,
                        'config_key' => $module['config_key'],
                    ];
                }
            }
            $this->configurationRepository->insertMultipleRows($rows);
        }
    }
}
