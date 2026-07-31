<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use T3Planet\RteCkeditorPack\EventListener\ProcessingConfigurationModifier;
use T3Planet\RteCkeditorPack\EventListener\StripCollaborationMarkupFromPreviewListener;
use TYPO3\CMS\Backend\View\Event\AfterPageContentPreviewRenderedEvent;
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

    // AfterPageContentPreviewRenderedEvent exists since TYPO3 14.1 only.
    if (!class_exists(AfterPageContentPreviewRenderedEvent::class)) {
        return;
    }

    $container->services()
        ->set(StripCollaborationMarkupFromPreviewListener::class)
        ->autowire()
        ->autoconfigure()
        ->tag('event.listener', [
            'identifier' => 'rte-ckeditor-pack/strip-collaboration-markup-from-preview',
            'event' => AfterPageContentPreviewRenderedEvent::class,
        ]);
};
