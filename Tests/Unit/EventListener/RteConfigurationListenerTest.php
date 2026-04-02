<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Configuration\SettingConfigurationHandler;
use T3Planet\RteCkeditorPack\EventListener\RteConfigurationListener;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for event listener RteConfigurationListener
 */
class RteConfigurationListenerTest extends BaseTestCase
{
    /** @var RteConfigurationListener */
    protected $listener;

    /** @var SettingConfigurationHandler|MockObject */
    protected $mockedSettingsConfigHandler;

    /** @var UriBuilder|MockObject */
    protected $mockedUriBuilder;

    protected function setUp(): void
    {
        $this->mockedSettingsConfigHandler = $this->createMock(SettingConfigurationHandler::class);
        $this->mockedUriBuilder = $this->createMock(UriBuilder::class);

        // Mock GeneralUtility::makeInstance for UriBuilder (it's a singleton)
        GeneralUtility::setSingletonInstance(UriBuilder::class, $this->mockedUriBuilder);

        $this->listener = new RteConfigurationListener($this->mockedSettingsConfigHandler);
    }

    #[Test]
    public function invokeSetsImageRouteUrl(): void
    {
        // Skip test if event class is not available (e.g., rte_ckeditor extension not loaded in tests)
        if (!class_exists(\TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent::class)) {
            self::markTestSkipped('AfterPrepareConfigurationForEditorEvent class not available');
        }

        $configuration = [];
        $eventClass = \TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent::class;
        $event = $this->createMock($eventClass);

        $this->mockedUriBuilder->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('rteckeditorimage_wizard_select_image')
            ->willReturn('/typo3/rteckeditorimage/wizard/select-image');

        $this->mockedSettingsConfigHandler->expects(self::once())
            ->method('getTokenUrl')
            ->willReturn('https://example.com/token');

        $event->expects(self::once())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $event->expects(self::once())
            ->method('setConfiguration')
            ->with(self::callback(function ($config) {
                return isset($config['style']['typo3image']['routeUrl'])
                    && isset($config['cloudServices']['tokenUrl']);
            }));

        ($this->listener)($event);
    }

    #[Test]
    public function invokeSetsTokenUrl(): void
    {
        if (!class_exists(\TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent::class)) {
            self::markTestSkipped('AfterPrepareConfigurationForEditorEvent class not available');
        }

        $configuration = [];
        $tokenUrl = 'https://example.com/token';
        $event = $this->createEventDouble([]);

        $this->mockedUriBuilder->expects(self::once())
            ->method('buildUriFromRoute')
            ->willReturn('/typo3/rteckeditorimage/wizard/select-image');

        $this->mockedSettingsConfigHandler->expects(self::once())
            ->method('getTokenUrl')
            ->willReturn($tokenUrl);

        $event->expects(self::once())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $event->expects(self::once())
            ->method('setConfiguration')
            ->with(self::callback(function ($config) use ($tokenUrl) {
                return isset($config['cloudServices']['tokenUrl'])
                    && $config['cloudServices']['tokenUrl'] === $tokenUrl;
            }));

        ($this->listener)($event);
    }

    #[Test]
    public function invokeSetsImportWordTokenUrlWhenPresent(): void
    {
        if (!class_exists(\TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent::class)) {
            self::markTestSkipped('AfterPrepareConfigurationForEditorEvent class not available');
        }

        $configuration = [
            'importWord' => [],
        ];
        $tokenUrl = 'https://example.com/token';
        $eventClass = \TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent::class;
        $event = $this->createMock($eventClass);

        $this->mockedUriBuilder->expects(self::once())
            ->method('buildUriFromRoute')
            ->willReturn('/typo3/rteckeditorimage/wizard/select-image');

        $this->mockedSettingsConfigHandler->expects(self::once())
            ->method('getTokenUrl')
            ->willReturn($tokenUrl);

        $event->expects(self::once())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $event->expects(self::once())
            ->method('setConfiguration')
            ->with(self::callback(function ($config) use ($tokenUrl) {
                return isset($config['importWord']['tokenUrl'])
                    && $config['importWord']['tokenUrl'] === $tokenUrl;
            }));

        ($this->listener)($event);
    }

    #[Test]
    public function invokeDoesNotSetImportWordTokenUrlWhenNotPresent(): void
    {
        if (!class_exists(\TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent::class)) {
            self::markTestSkipped('AfterPrepareConfigurationForEditorEvent class not available');
        }

        $configuration = [];
        $tokenUrl = 'https://example.com/token';
        $eventClass = \TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent::class;
        $event = $this->createMock($eventClass);

        $this->mockedUriBuilder->expects(self::once())
            ->method('buildUriFromRoute')
            ->willReturn('/typo3/rteckeditorimage/wizard/select-image');

        $this->mockedSettingsConfigHandler->expects(self::once())
            ->method('getTokenUrl')
            ->willReturn($tokenUrl);

        $event->expects(self::once())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $event->expects(self::once())
            ->method('setConfiguration')
            ->with(self::callback(function ($config) {
                return !isset($config['importWord']);
            }));

        ($this->listener)($event);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }
}

