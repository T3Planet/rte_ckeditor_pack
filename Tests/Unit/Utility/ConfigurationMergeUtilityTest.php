<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Utility\ConfigurationMergeUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class ConfigurationMergeUtilityTest extends BaseTestCase
{
    private ConfigurationMergeUtility $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ConfigurationMergeUtility();
    }

    #[Test]
    public function orderedSyncInsertsMissingItemBeforeNextYamlNeighbour(): void
    {
        $result = $this->subject->syncToolbarOrdered(
            ['bold', 'horizontalLine', 'italic'],
            'bold,customButton,italic'
        );

        self::assertSame('bold,customButton,horizontalLine,italic', $result);
    }

    #[Test]
    public function orderedSyncKeepsMissingYamlItemsInYamlOrder(): void
    {
        $result = $this->subject->syncToolbarOrdered(
            ['bold', 'horizontalLine', 'link', 'italic'],
            'bold,customButton,italic'
        );

        self::assertSame('bold,customButton,horizontalLine,link,italic', $result);
    }

    #[Test]
    public function orderedSyncPreservesRepeatedYamlSeparators(): void
    {
        $result = $this->subject->syncToolbarOrdered(
            ['heading', '|', 'bold', '|', 'italic'],
            'heading,bold,customButton,italic'
        );

        self::assertSame('heading,|,bold,customButton,|,italic', $result);
    }

    #[Test]
    public function orderedSyncAppendsItemsWithoutFollowingNeighbour(): void
    {
        $result = $this->subject->syncToolbarOrdered(
            ['bold', 'italic', 'horizontalLine', 'sourceEditing'],
            'bold,customButton,italic'
        );

        self::assertSame('bold,customButton,italic,horizontalLine,sourceEditing', $result);
    }

    #[Test]
    public function orderedSyncDoesNotCreateAdjacentSeparatorsWhenDbOrderDiffers(): void
    {
        $result = $this->subject->syncToolbarOrdered(
            ['code', 'highlight', '|', 'bold', 'italic', '|', 'clipboard', 'undo', 'redo'],
            'bold,italic,|,clipboard,undo,redo,code,highlight'
        );

        self::assertSame('bold,italic,|,clipboard,undo,redo,code,highlight', $result);
        self::assertStringNotContainsString('|,|', $result);
    }

    #[Test]
    public function orderedSyncDoesNotCreateAdjacentSeparatorsWhenInsertingMissingItems(): void
    {
        $result = $this->subject->syncToolbarOrdered(
            ['code', 'highlight', '|', 'bold', 'italic', '|', 'clipboard', 'undo', 'redo'],
            'bold,italic,|,clipboard,undo,redo'
        );

        self::assertSame('bold,italic,code,highlight,|,clipboard,undo,redo', $result);
        self::assertStringNotContainsString('|,|', $result);
    }

    #[Test]
    public function orderedSyncCollapsesExistingAdjacentSeparators(): void
    {
        $result = $this->subject->syncToolbarOrdered(
            ['bold', '|', 'italic'],
            'bold,|,|,italic'
        );

        self::assertSame('bold,|,italic', $result);
    }
}
