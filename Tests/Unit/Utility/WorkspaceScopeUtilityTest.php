<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Utility\WorkspaceScopeUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class WorkspaceScopeUtilityTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function collaborationSegmentIsLiveWhenWorkspaceIsZero(): void
    {
        unset($GLOBALS['BE_USER']);
        $context = new Context();
        $context->setAspect('workspace', new WorkspaceAspect(0));
        GeneralUtility::setSingletonInstance(Context::class, $context);

        self::assertSame(0, WorkspaceScopeUtility::currentWorkspaceId());
        self::assertSame('live', WorkspaceScopeUtility::collaborationSegment());
    }

    #[Test]
    public function collaborationSegmentUsesNumericDraftWorkspace(): void
    {
        unset($GLOBALS['BE_USER']);
        $context = new Context();
        $context->setAspect('workspace', new WorkspaceAspect(3));
        GeneralUtility::setSingletonInstance(Context::class, $context);

        self::assertSame(3, WorkspaceScopeUtility::currentWorkspaceId());
        self::assertSame(3, WorkspaceScopeUtility::collaborationSegment());
    }

    #[Test]
    public function currentWorkspaceIdFallsBackToBackendUserWorkspace(): void
    {
        $context = new Context();
        $context->setAspect('workspace', new WorkspaceAspect(0));
        GeneralUtility::setSingletonInstance(Context::class, $context);

        $GLOBALS['BE_USER'] = new class () {
            public int $workspace = 7;
        };

        self::assertSame(7, WorkspaceScopeUtility::currentWorkspaceId());
        self::assertSame(7, WorkspaceScopeUtility::collaborationSegment());
    }

    #[Test]
    public function scopeRteIdKeepsLiveCanonicalFieldName(): void
    {
        self::assertSame(
            'data[tt_content][10][bodytext]',
            WorkspaceScopeUtility::scopeRteId('data[tt_content][10][bodytext]', 0)
        );
    }

    #[Test]
    public function scopeRteIdAppendsDraftWorkspaceOnce(): void
    {
        $rteId = WorkspaceScopeUtility::scopeRteId('data[tt_content][10][bodytext]', 2);

        self::assertSame('data[tt_content][10][bodytext]:ws:2', $rteId);
        self::assertSame($rteId, WorkspaceScopeUtility::scopeRteId($rteId, 2));
    }

    #[Test]
    public function unscopeRteIdStripsDraftSuffix(): void
    {
        self::assertSame(
            'data[tt_content][10][bodytext]',
            WorkspaceScopeUtility::unscopeRteId('data[tt_content][10][bodytext]:ws:2')
        );
        self::assertSame(
            'data[tt_content][10][bodytext]',
            WorkspaceScopeUtility::unscopeRteId('data[tt_content][10][bodytext]')
        );
    }

    #[Test]
    public function liveRecordUidPrefersWorkspaceOverlayOid(): void
    {
        self::assertSame(141, WorkspaceScopeUtility::liveRecordUid([
            'recordUid' => 500,
            'databaseRow' => ['uid' => 500, 't3ver_oid' => 141],
        ]));
        self::assertSame(141, WorkspaceScopeUtility::liveRecordUid([
            'recordUid' => 141,
            'databaseRow' => ['uid' => 141, 't3ver_oid' => 0],
        ]));
    }
}
