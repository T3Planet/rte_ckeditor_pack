<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class PackRecordPersisterTest extends BaseTestCase
{
    #[Test]
    public function createRejectsUnknownTable(): void
    {
        $subject = new PackRecordPersister(new Context(), $this->createMock(ConnectionPool::class));

        $this->expectException(\InvalidArgumentException::class);
        $subject->create('pages', ['title' => 'x']);
    }

    #[Test]
    public function createRejectsDuplicateLivePresetKey(): void
    {
        $subject = $this->getAccessibleMock(
            PackRecordPersister::class,
            ['livePresetKeyExists'],
            [new Context(), $this->createMock(ConnectionPool::class)]
        );
        $subject->method('livePresetKeyExists')->with('default')->willReturn(true);

        $uid = $subject->create(PackRecordPersister::TABLE_PRESET, [
            'preset_key' => 'default',
            'toolbar_items' => '',
        ]);

        self::assertSame(0, $uid);
        self::assertNotEmpty($subject->getErrors());
    }

    #[Test]
    public function updateRejectsInvalidUid(): void
    {
        $subject = new PackRecordPersister(new Context(), $this->createMock(ConnectionPool::class));

        self::assertFalse($subject->update(PackRecordPersister::TABLE_FEATURE, 0, ['enable' => 1]));
        self::assertNotEmpty($subject->getErrors());
    }

    #[Test]
    public function upsertFeatureRejectsInvalidKeys(): void
    {
        $subject = new PackRecordPersister(new Context(), $this->createMock(ConnectionPool::class));

        self::assertSame(0, $subject->upsertFeature(0, 'Font', ['enable' => 1]));
        self::assertSame(0, $subject->upsertFeature(1, '', ['enable' => 1]));
    }
}
