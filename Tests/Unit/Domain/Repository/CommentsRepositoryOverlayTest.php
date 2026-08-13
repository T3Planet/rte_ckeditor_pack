<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Domain\Repository\CommentsRepository;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class CommentsRepositoryOverlayTest extends BaseTestCase
{
    #[Test]
    public function overlayUsesLiveWhenDraftHasNoRowsForThread(): void
    {
        $draft = [
            ['thread_id' => 'draft-only', 'id' => 'c1', 'content' => 'draft'],
        ];
        $live = [
            ['thread_id' => 'live-only', 'id' => 'c2', 'content' => 'live'],
            ['thread_id' => 'draft-only', 'id' => 'c3', 'content' => 'should-hide'],
        ];

        $merged = CommentsRepository::overlayCommentRows($draft, $live);

        self::assertCount(2, $merged);
        self::assertSame('c1', $merged[0]['id']);
        self::assertSame('c2', $merged[1]['id']);
    }

    #[Test]
    public function overlayReturnsLiveWhenDraftEmpty(): void
    {
        $live = [
            ['thread_id' => 't1', 'id' => 'a', 'content' => 'live'],
        ];

        self::assertSame($live, CommentsRepository::overlayCommentRows([], $live));
    }
}
