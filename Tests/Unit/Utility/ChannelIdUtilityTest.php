<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Utility\ChannelIdUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class ChannelIdUtilityTest extends BaseTestCase
{
    #[Test]
    public function workspaceVersionUsesLiveRecordOid(): void
    {
        $fromVersionRow = ChannelIdUtility::buildChannelIdFromData([
            'tableName' => 'tt_content',
            'fieldName' => 'bodytext',
            'effectivePid' => 0,
            'workspaceId' => 2,
            'recordUid' => 500,
            'databaseRow' => ['uid' => 500, 't3ver_oid' => 141],
        ]);
        $fromLiveUidInDraft = ChannelIdUtility::buildChannelIdFromData([
            'tableName' => 'tt_content',
            'fieldName' => 'bodytext',
            'effectivePid' => 0,
            'workspaceId' => 2,
            'recordUid' => 141,
            'databaseRow' => ['uid' => 141, 't3ver_oid' => 0],
        ]);

        self::assertSame($fromVersionRow, $fromLiveUidInDraft);
    }

    #[Test]
    public function liveAndDraftChannelsDiffer(): void
    {
        $base = [
            'tableName' => 'tt_content',
            'fieldName' => 'bodytext',
            'effectivePid' => 0,
            'databaseRow' => ['uid' => 141, 't3ver_oid' => 0],
        ];

        self::assertNotSame(
            ChannelIdUtility::buildChannelIdFromData($base + ['workspaceId' => 'live']),
            ChannelIdUtility::buildChannelIdFromData($base + ['workspaceId' => 2])
        );
    }
}
