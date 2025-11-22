<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Configuration\SettingConfigurationHandler;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

class RteConfigurationListener
{
    public function __construct(
        protected SettingConfigurationHandler $settingsConfigHandler
    ) {}
    public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $configuration = $event->getConfiguration();
        $configuration['style']['typo3image'] = [
            'routeUrl' => (string)$uriBuilder->buildUriFromRoute('rteckeditorimage_wizard_select_image'),
        ];
        $tokenUrl = $this->settingsConfigHandler->getTokenUrl();
        $configuration['cloudServices']['tokenUrl'] = $tokenUrl;
        if (isset($configuration['importWord'])) {
            $configuration['importWord']['tokenUrl'] = $tokenUrl;
        }

        $event->setConfiguration($configuration);
    }
}
