<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Test case for domain model Feature
 */
class FeatureTest extends BaseTestCase
{
    protected Feature $feature;

    protected function setUp(): void
    {
        $this->feature = new Feature();
    }

    #[Test]
    public function presetUidCanBeSet(): void
    {
        $presetUid = 123;
        $this->feature->setPresetUid($presetUid);
        self::assertEquals($presetUid, $this->feature->getPresetUid());
    }

    #[Test]
    public function enableCanBeSet(): void
    {
        $enable = true;
        $this->feature->setEnable($enable);
        self::assertTrue($this->feature->getEnable());
        self::assertTrue($this->feature->isEnable());
    }

    #[Test]
    public function enableCanBeSetToFalse(): void
    {
        $this->feature->setEnable(false);
        self::assertFalse($this->feature->getEnable());
        self::assertFalse($this->feature->isEnable());
    }

    #[Test]
    public function configKeyCanBeSet(): void
    {
        $configKey = 'test_config_key';
        $this->feature->setConfigKey($configKey);
        self::assertEquals($configKey, $this->feature->getConfigKey());
    }

    #[Test]
    public function fieldsCanBeSet(): void
    {
        $fields = 'field1,field2,field3';
        $this->feature->setFields($fields);
        self::assertEquals($fields, $this->feature->getFields());
    }

    #[Test]
    public function toolbarItemsCanBeSet(): void
    {
        $toolbarItems = 'bold,italic,underline';
        $this->feature->setToolbarItems($toolbarItems);
        self::assertEquals($toolbarItems, $this->feature->getToolbarItems());
    }

    #[Test]
    public function sortingCanBeSet(): void
    {
        $sorting = 42;
        $this->feature->setSorting($sorting);
        self::assertEquals($sorting, $this->feature->getSorting());
    }

    #[Test]
    public function defaultValuesAreSet(): void
    {
        self::assertEquals(0, $this->feature->getPresetUid());
        self::assertFalse($this->feature->getEnable());
        self::assertFalse($this->feature->isEnable());
        self::assertEquals('', $this->feature->getConfigKey());
        self::assertEquals('', $this->feature->getFields());
        self::assertEquals('', $this->feature->getToolbarItems());
        self::assertEquals(0, $this->feature->getSorting());
    }
}

