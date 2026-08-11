<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Service\PackDraftIndicator;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class PackDraftIndicatorTest extends BaseTestCase
{
    #[Test]
    public function getUiDataIsEmptyOnLive(): void
    {
        $context = new Context();
        $context->setAspect('workspace', new WorkspaceAspect(0));
        $subject = new PackDraftIndicator($context, $this->createMock(ConnectionPool::class));

        $data = $subject->getUiData(1);

        self::assertFalse($data['workspaceDraftMode']);
        self::assertFalse($data['showDraftLegend']);
        self::assertSame('', $data['liveToolbarItemsCsv']);
        self::assertSame('', $data['liveEnabledFeaturesCsv']);
        self::assertSame('', $data['draftChangedFeaturesCsv']);
        self::assertSame([], $data['draftOnlyFeatures']);
        self::assertSame([], $data['draftDisabledFeatures']);
        self::assertSame([], $data['draftChangedFeatures']);
        self::assertSame([], $data['draftDifferingToolbarItems']);
        self::assertSame([], $data['draftLegendModules']);
    }

    #[Test]
    public function getUiDataDiffsDraftFromLive(): void
    {
        $context = new Context();
        $context->setAspect('workspace', new WorkspaceAspect(5));
        $subject = $this->getMockBuilder(PackDraftIndicator::class)
            ->setConstructorArgs([$context, $this->createMock(ConnectionPool::class)])
            ->onlyMethods(['fetchFeatureSnapshots', 'fetchPresetToolbarItems', 'resolveWorkspaceColor'])
            ->getMock();

        $subject->method('resolveWorkspaceColor')->with(5)->willReturn('blue');
        $subject->method('fetchFeatureSnapshots')->willReturnMap([
            [42, 0, [
                'Bold' => ['enable' => true, 'fields' => ''],
                'Font' => ['enable' => true, 'fields' => '{"size":"12"}'],
                'Style' => ['enable' => true, 'fields' => '{"definitions":[]}'],
            ]],
            [42, 5, [
                'Bold' => ['enable' => true, 'fields' => ''],
                'Emoji' => ['enable' => true, 'fields' => ''],
                'Style' => ['enable' => true, 'fields' => '{"definitions":[{"name":"Lead"}]}'],
            ]],
        ]);
        $subject->method('fetchPresetToolbarItems')->willReturnMap([
            [42, 0, ['bold', 'italic']],
            [42, 5, ['bold', 'emoji']],
        ]);

        $data = $subject->getUiData(42);

        self::assertTrue($data['workspaceDraftMode']);
        self::assertTrue($data['showDraftLegend']);
        self::assertSame('var(--typo3-state-blue-bg, #1a6dcc)', $data['workspaceAccentCss']);
        self::assertSame('bold,italic', $data['liveToolbarItemsCsv']);
        self::assertSame('Bold,Font,Style', $data['liveEnabledFeaturesCsv']);
        self::assertSame('Font,Style,Emoji', $data['draftChangedFeaturesCsv']);
        self::assertSame(['Emoji' => true], $data['draftOnlyFeatures']);
        self::assertSame(['Font' => true], $data['draftDisabledFeatures']);
        self::assertSame(
            ['Font' => true, 'Style' => true, 'Emoji' => true],
            $data['draftChangedFeatures']
        );
        self::assertSame(['italic' => true, 'emoji' => true], $data['draftDifferingToolbarItems']);
        self::assertSame([], $data['draftLegendModules']);
    }

    #[Test]
    public function modulesWithDraftChangesMapsTabsThatContainChangedFeatures(): void
    {
        $context = new Context();
        $context->setAspect('workspace', new WorkspaceAspect(5));
        $subject = new PackDraftIndicator($context, $this->createMock(ConnectionPool::class));

        $modules = [
            'plugins' => [
                'key' => 'plugins',
                'cards' => [
                    ['configuration' => ['config_key' => 'Bold']],
                ],
            ],
            'premium' => [
                'key' => 'premium',
                'cards' => [
                    ['configuration' => ['config_key' => 'Style']],
                    ['configuration' => ['config_key' => 'Emoji']],
                ],
            ],
        ];

        $map = $subject->modulesWithDraftChanges($modules, ['Style' => true]);

        self::assertSame(['premium' => true], $map);
    }
}
