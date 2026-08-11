<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\EventListener\FlushPackCacheOnWorkspacePublishListener;
use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class FlushPackCacheOnWorkspacePublishListenerTest extends BaseTestCase
{
    #[Test]
    public function flushesCacheForPackTablesOnly(): void
    {
        $eventClass = 'TYPO3\\CMS\\Workspaces\\Event\\AfterRecordPublishedEvent';
        if (!class_exists($eventClass)) {
            self::markTestSkipped('workspaces extension / AfterRecordPublishedEvent not available');
        }

        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())->method('flush');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('hasCache')->with('rte_ckeditor_config')->willReturn(true);
        $cacheManager->method('getCache')->with('rte_ckeditor_config')->willReturn($cache);

        $subject = new FlushPackCacheOnWorkspacePublishListener($cacheManager);
        $subject(new $eventClass(PackRecordPersister::TABLE_PRESET, 12, 3));
    }

    #[Test]
    public function ignoresNonPackTables(): void
    {
        $eventClass = 'TYPO3\\CMS\\Workspaces\\Event\\AfterRecordPublishedEvent';
        if (!class_exists($eventClass)) {
            self::markTestSkipped('workspaces extension / AfterRecordPublishedEvent not available');
        }

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::never())->method('hasCache');

        $subject = new FlushPackCacheOnWorkspacePublishListener($cacheManager);
        $subject(new $eventClass('tt_content', 1, 3));
    }
}
