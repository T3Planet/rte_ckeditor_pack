<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use T3Planet\RteCkeditorPack\EventListener\FlushPackCacheOnWorkspacePublishListener;
use T3Planet\RteCkeditorPack\EventListener\PromoteWorkspaceCommentsOnPublishListener;
use T3Planet\RteCkeditorPack\EventListener\ProcessingConfigurationModifier;
use T3Planet\RteCkeditorPack\EventListener\RestoreCollaborationMarkersListener;
use T3Planet\RteCkeditorPack\EventListener\StripCollaborationMarkupFromPreviewListener;
use TYPO3\CMS\Backend\View\Event\AfterPageContentPreviewRenderedEvent;
use TYPO3\CMS\Core\Configuration\Event\AfterRichtextConfigurationPreparedEvent;
use TYPO3\CMS\Core\Html\Event\AfterTransformTextForPersistenceEvent;
use TYPO3\CMS\Core\Html\Event\AfterTransformTextForRichTextEditorEvent;
use TYPO3\CMS\Core\Html\Event\BeforeTransformTextForPersistenceEvent;
use TYPO3\CMS\Core\Html\Event\BeforeTransformTextForRichTextEditorEvent;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Workspaces\Event\AfterRecordPublishedEvent;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder): void {
    $majorVersion = (new Typo3Version())->getMajorVersion();

    // Soft Workspaces publish hook (event class exists only when workspaces is installed).
    if (class_exists(AfterRecordPublishedEvent::class)) {
        $container->services()
            ->set(FlushPackCacheOnWorkspacePublishListener::class)
            ->autowire()
            ->autoconfigure()
            ->tag('event.listener', [
                'identifier' => 'rte-ckeditor-pack/flush-cache-on-workspace-publish',
                'event' => AfterRecordPublishedEvent::class,
            ]);
        $container->services()
            ->set(PromoteWorkspaceCommentsOnPublishListener::class)
            ->autowire()
            ->autoconfigure()
            ->tag('event.listener', [
                'identifier' => 'rte-ckeditor-pack/promote-comments-on-workspace-publish',
                'event' => AfterRecordPublishedEvent::class,
            ]);
    }

    // Transform-text events exist since TYPO3 v13. On v12, RteHtmlParser XCLASS handles restore.
    if (
        class_exists(BeforeTransformTextForRichTextEditorEvent::class)
        && class_exists(AfterTransformTextForRichTextEditorEvent::class)
        && class_exists(BeforeTransformTextForPersistenceEvent::class)
        && class_exists(AfterTransformTextForPersistenceEvent::class)
    ) {
        $container->services()
            ->set(RestoreCollaborationMarkersListener::class)
            ->autowire()
            ->autoconfigure()
            ->tag('event.listener', [
                'identifier' => 'rte-ckeditor-pack/restore-collaboration-markers-for-editor',
                'method' => 'restoreForEditor',
                'event' => BeforeTransformTextForRichTextEditorEvent::class,
            ])
            ->tag('event.listener', [
                'identifier' => 'rte-ckeditor-pack/restore-collaboration-markers-for-editor-after',
                'method' => 'restoreForEditorAfter',
                'event' => AfterTransformTextForRichTextEditorEvent::class,
            ])
            ->tag('event.listener', [
                'identifier' => 'rte-ckeditor-pack/restore-collaboration-markers-for-persistence',
                'method' => 'restoreForPersistence',
                'event' => BeforeTransformTextForPersistenceEvent::class,
            ])
            ->tag('event.listener', [
                'identifier' => 'rte-ckeditor-pack/restore-collaboration-markers-for-persistence-after',
                'method' => 'restoreForPersistenceAfter',
                'event' => AfterTransformTextForPersistenceEvent::class,
            ]);
    }

    if ($majorVersion < 14) {
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
