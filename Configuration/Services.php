<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use T3Planet\RteCkeditorPack\EventListener\ProcessingConfigurationModifier;
use TYPO3\CMS\Core\Configuration\Event\AfterRichtextConfigurationPreparedEvent;
use TYPO3\CMS\Core\Information\Typo3Version;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder): void {
    if ((new Typo3Version())->getMajorVersion() < 14) {
        return;
    }

    $container->services()
        ->set(ProcessingConfigurationModifier::class)
        ->autowire()
        ->autoconfigure()
        ->tag('event.listener', [
            'identifier' => 'rte_ckeditor_pack_processing_configuration_modifier',
            'event' => AfterRichtextConfigurationPreparedEvent::class,
        ]);
};
