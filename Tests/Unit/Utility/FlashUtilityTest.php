<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Utility\FlashUtility;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for utility class FlashUtility
 */
class FlashUtilityTest extends BaseTestCase
{
    /** @var FlashUtility|AccessibleObjectInterface|MockObject */
    protected $flashUtility;

    /** @var PageRenderer|MockObject */
    protected $mockedPageRenderer;

    protected function setUp(): void
    {
        $this->mockedPageRenderer = $this->createMock(PageRenderer::class);
        $this->flashUtility = $this->getAccessibleMock(
            FlashUtility::class,
            null,
            [],
            '',
            false
        );

        // Mock GeneralUtility::makeInstance for PageRenderer (it's a singleton)
        GeneralUtility::setSingletonInstance(PageRenderer::class, $this->mockedPageRenderer);
        $this->flashUtility->_set('pageRenderer', $this->mockedPageRenderer);
    }

    #[Test]
    public function addFlashNotificationDoesNothingWhenResponseIsEmpty(): void
    {
        $this->mockedPageRenderer->expects(self::never())
            ->method('getJavaScriptRenderer');

        $this->flashUtility->addFlashNotification([]);
    }

    #[Test]
    public function addFlashNotificationDoesNothingWhenTitleIsMissing(): void
    {
        $this->mockedPageRenderer->expects(self::never())
            ->method('getJavaScriptRenderer');

        $this->flashUtility->addFlashNotification(['message' => 'Test message']);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }
}

