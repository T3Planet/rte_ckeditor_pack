<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\DataProvider;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\DataProvider\ToolbarIcons;
use TYPO3\TestingFramework\Core\BaseTestCase;

class ToolbarIconsRestrictedEditingTest extends BaseTestCase
{
    #[Test]
    public function restrictedEditingIconsMapToDedicatedIconIdentifiers(): void
    {
        $toolbarIcons = new ToolbarIcons();

        self::assertSame('rte_restrictedEditing', $toolbarIcons->getIconByName('restrictedEditing'));
        self::assertSame('rte_restrictedEditingException', $toolbarIcons->getIconByName('restrictedEditingException'));
        self::assertSame('rte_restrictedEditing', $toolbarIcons->getIconByName('menuBar:restrictedEditing'));
        self::assertSame(
            'rte_restrictedEditingException',
            $toolbarIcons->getIconByName('menuBar:restrictedEditingException')
        );
    }

    #[Test]
    public function restrictedEditingToolbarItemsAreMarkedPremium(): void
    {
        $toolbarIcons = new ToolbarIcons();

        self::assertTrue($toolbarIcons->isPremiumToolbarItem('restrictedEditing'));
        self::assertTrue($toolbarIcons->isPremiumToolbarItem('restrictedEditingException'));
    }
}
