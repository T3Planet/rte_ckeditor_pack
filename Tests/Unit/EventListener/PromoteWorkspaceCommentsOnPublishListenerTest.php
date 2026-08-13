<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Domain\Repository\CommentsRepository;
use T3Planet\RteCkeditorPack\EventListener\PromoteWorkspaceCommentsOnPublishListener;
use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class PromoteWorkspaceCommentsOnPublishListenerTest extends BaseTestCase
{
    #[Test]
    public function promotesCommentsForContentRecords(): void
    {
        $eventClass = 'TYPO3\\CMS\\Workspaces\\Event\\AfterRecordPublishedEvent';
        if (!class_exists($eventClass)) {
            self::markTestSkipped('workspaces extension / AfterRecordPublishedEvent not available');
        }

        $repository = $this->createMock(CommentsRepository::class);
        $repository->expects(self::once())
            ->method('promoteWorkspaceCommentsToLive')
            ->with(3, 'tt_content', 141);

        $subject = new PromoteWorkspaceCommentsOnPublishListener($repository);
        $subject(new $eventClass('tt_content', 141, 3));
    }

    #[Test]
    public function ignoresPackConfigurationTables(): void
    {
        $eventClass = 'TYPO3\\CMS\\Workspaces\\Event\\AfterRecordPublishedEvent';
        if (!class_exists($eventClass)) {
            self::markTestSkipped('workspaces extension / AfterRecordPublishedEvent not available');
        }

        $repository = $this->createMock(CommentsRepository::class);
        $repository->expects(self::never())->method('promoteWorkspaceCommentsToLive');
        $repository->expects(self::never())->method('promoteThreadsToLive');

        $subject = new PromoteWorkspaceCommentsOnPublishListener($repository);
        $subject(new $eventClass(PackRecordPersister::TABLE_PRESET, 12, 3));
    }

    #[Test]
    public function extractsThreadIdsFromCommentMarkers(): void
    {
        $ids = PromoteWorkspaceCommentsOnPublishListener::extractCommentThreadIdsFromFields([
            'bodytext' => '<p><comment-start name="eefc5e133217649f7aee20c0c1f16b518:1"></comment-start>Hello<comment-end name="eefc5e133217649f7aee20c0c1f16b518:1"></comment-end></p>',
            'header' => 'Title',
        ]);

        self::assertSame(['eefc5e133217649f7aee20c0c1f16b518'], $ids);
    }
}
