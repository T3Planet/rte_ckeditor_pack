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
}
