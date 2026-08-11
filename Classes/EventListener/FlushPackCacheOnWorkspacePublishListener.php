<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * Flush Pack RTE cache when a Pack record is published to Live.
 */
class FlushPackCacheOnWorkspacePublishListener
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {}

    public function __invoke(object $event): void
    {
        if (!method_exists($event, 'getTable')) {
            return;
        }
        if (!in_array((string)$event->getTable(), PackRecordPersister::TABLES, true)) {
            return;
        }
        if ($this->cacheManager->hasCache('rte_ckeditor_config')) {
            $this->cacheManager->getCache('rte_ckeditor_config')->flush();
        }
    }
}
