<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Configuration\MentionConfigurationBuilder;
use T3Planet\RteCkeditorPack\Configuration\SettingConfigurationHandler;
use T3Planet\RteCkeditorPack\DataProvider\Modules;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Model\ToolbarGroups;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use T3Planet\RteCkeditorPack\EventListener\RteConfigurationModifier;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for EventListener\RteConfigurationModifier
 *
 * Most of the listener's behavior is exposed through private helpers.
 * We use TYPO3 TestingFramework's getAccessibleMock + _call() to exercise
 * them directly without going through the full __invoke flow (which
 * requires the BeforePrepareConfigurationForEditorEvent class and a
 * BackendUtility/DB context).
 */
class RteConfigurationModifierTest extends BaseTestCase
{
    /** @var SettingConfigurationHandler|MockObject */
    protected $mockedSettingsConfigHandler;

    /** @var FeatureRepository|MockObject */
    protected $mockedFeatureRepository;

    /** @var PresetRepository|MockObject */
    protected $mockedPresetRepository;

    /** @var ToolbarGroupsRepository|MockObject */
    protected $mockedToolbarGroupsRepository;

    /** @var Modules|MockObject */
    protected $mockedModules;

    /** @var CacheManager|MockObject */
    protected $mockedCacheManager;

    /** @var FrontendInterface|MockObject */
    protected $mockedCache;

    /** @var PageRenderer|MockObject */
    protected $mockedPageRenderer;

    protected function setUp(): void
    {
        $this->mockedSettingsConfigHandler = $this->createMock(SettingConfigurationHandler::class);
        $this->mockedFeatureRepository = $this->createMock(FeatureRepository::class);
        $this->mockedPresetRepository = $this->createMock(PresetRepository::class);
        $this->mockedToolbarGroupsRepository = $this->createMock(ToolbarGroupsRepository::class);
        $this->mockedModules = $this->createMock(Modules::class);

        $this->mockedCache = $this->createMock(FrontendInterface::class);
        $this->mockedCacheManager = $this->createMock(CacheManager::class);
        $this->mockedCacheManager->method('getCache')
            ->with('rte_ckeditor_config')
            ->willReturn($this->mockedCache);

        $this->mockedPageRenderer = $this->createMock(PageRenderer::class);

        GeneralUtility::setSingletonInstance(CacheManager::class, $this->mockedCacheManager);
        GeneralUtility::setSingletonInstance(PageRenderer::class, $this->mockedPageRenderer);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @return RteConfigurationModifier|AccessibleObjectInterface
     */
    private function createAccessibleModifier()
    {
        return $this->getAccessibleMock(
            RteConfigurationModifier::class,
            null,
            [
                $this->mockedSettingsConfigHandler,
                $this->mockedFeatureRepository,
                $this->mockedPresetRepository,
                $this->mockedToolbarGroupsRepository,
                $this->mockedModules,
            ]
        );
    }

    /**
     * Invoke a private method via Reflection. The listener's helpers are
     * declared private, so TestingFramework's _call() cannot reach them.
     *
     * @return mixed
     */
    private function invokePrivate(object $instance, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($instance, $method);
        return $reflection->invokeArgs($instance, $args);
    }

    #[Test]
    public function constructorAssignsDefaults(): void
    {
        $modifier = $this->createAccessibleModifier();

        self::assertFalse($modifier->_get('premium'));
        self::assertSame('default', $modifier->_get('selectedPreset'));
        self::assertSame(['Menubar', 'TextTransformation'], $modifier->_get('invisibleFeatures'));
        self::assertSame($this->mockedCache, $modifier->_get('cache'));
        self::assertSame($this->mockedPageRenderer, $modifier->_get('pageRenderer'));
    }

    #[Test]
    public function invokeShortCircuitsWhenEventDataIsEmpty(): void
    {
        if (!class_exists(\TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent::class)) {
            self::markTestSkipped('BeforePrepareConfigurationForEditorEvent class not available');
        }

        $modifier = $this->createAccessibleModifier();

        $eventClass = \TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent::class;
        $event = $this->createMock($eventClass);
        $event->method('getData')->willReturn([]);
        $event->expects(self::never())->method('setConfiguration');

        ($modifier)($event);
    }

    #[Test]
    public function addToolbarItemsReturnsConfigurationUnchangedWhenInputEmpty(): void
    {
        $modifier = $this->createAccessibleModifier();

        $configuration = ['toolbar' => ['items' => ['bold']]];
        $result = $this->invokePrivate($modifier, 'addToolbarItems', [$configuration, '']);

        self::assertSame($configuration, $result);
    }

    #[Test]
    public function addToolbarItemsBuildsPlainItemsList(): void
    {
        $modifier = $this->createAccessibleModifier();

        $configuration = ['toolbar' => ['items' => []]];
        $result = $this->invokePrivate($modifier, 'addToolbarItems', [$configuration, 'bold,italic,underline']);

        self::assertSame(['bold', 'italic', 'underline'], $result['toolbar']['items']);
        self::assertTrue($result['toolbar']['shouldNotGroupWhenFull']);
    }

    #[Test]
    public function addToolbarItemsPreservesSingleCharacterSeparators(): void
    {
        $modifier = $this->createAccessibleModifier();

        $configuration = ['toolbar' => ['items' => []]];
        $result = $this->invokePrivate($modifier, 'addToolbarItems', [$configuration, 'bold,|,italic,-,underline']);

        self::assertSame(['bold', '|', 'italic', '-', 'underline'], $result['toolbar']['items']);
    }

    #[Test]
    public function addToolbarItemsExpandsGroupReferenceViaGroupRepository(): void
    {
        $group = new ToolbarGroups();
        $group->setLabel('My group');
        $group->setTooltip('A tooltip');
        $group->setIcon('paragraph');
        $group->setItems('bold,italic');

        $this->mockedToolbarGroupsRepository->expects(self::once())
            ->method('findByUid')
            ->with(7)
            ->willReturn($group);

        $modifier = $this->createAccessibleModifier();

        $configuration = ['toolbar' => ['items' => []]];
        $result = $this->invokePrivate($modifier, 'addToolbarItems', [$configuration, 'Group-7,underline']);

        self::assertCount(2, $result['toolbar']['items']);
        self::assertSame([
            'label' => 'My group',
            'tooltip' => 'A tooltip',
            'icon' => 'paragraph',
            'items' => ['bold', 'italic'],
        ], $result['toolbar']['items'][0]);
        self::assertSame('underline', $result['toolbar']['items'][1]);
    }

    #[Test]
    public function addToolbarItemsReplacesCustomIconWhenGroupIconIsOther(): void
    {
        $group = new ToolbarGroups();
        $group->setLabel('Custom');
        $group->setTooltip('');
        $group->setIcon('other');
        $group->setCustomIcon('<svg/>');
        $group->setItems('bold');

        $this->mockedToolbarGroupsRepository->method('findByUid')->willReturn($group);

        $modifier = $this->createAccessibleModifier();
        $configuration = ['toolbar' => ['items' => []]];
        $result = $this->invokePrivate($modifier, 'addToolbarItems', [$configuration, 'Group-1']);

        self::assertSame('<svg/>', $result['toolbar']['items'][0]['icon']);
    }

    #[Test]
    public function ensureCollaborationChannelConfigurationAddsChannelIdAndDocumentIdWhenMissing(): void
    {
        $modifier = $this->createAccessibleModifier();

        // Use effectivePid=0 so ChannelIdUtility takes the "default" site path
        // and never tries to instantiate SiteFinder (which requires 2 ctor args).
        $data = [
            'tableName' => 'tt_content',
            'fieldName' => 'bodytext',
            'effectivePid' => 0,
            'databaseRow' => ['uid' => 42],
        ];

        $result = $this->invokePrivate($modifier, 'ensureCollaborationChannelConfiguration', [[], $data]);

        self::assertArrayHasKey('channelId', $result['collaboration']);
        self::assertNotSame('', $result['collaboration']['channelId']);
        self::assertStringStartsWith('ckdoc-', $result['collaboration']['channelId']);
        self::assertSame(
            $result['collaboration']['channelId'],
            $result['cloudServices']['documentId']
        );
    }

    #[Test]
    public function ensureCollaborationChannelConfigurationPreservesExistingChannelId(): void
    {
        $modifier = $this->createAccessibleModifier();

        $existing = [
            'collaboration' => ['channelId' => 'ckdoc-fixed'],
            'cloudServices' => ['documentId' => 'ckdoc-fixed'],
        ];

        $result = $this->invokePrivate($modifier, 'ensureCollaborationChannelConfiguration', [$existing, []]);

        self::assertSame('ckdoc-fixed', $result['collaboration']['channelId']);
        self::assertSame('ckdoc-fixed', $result['cloudServices']['documentId']);
    }

    #[Test]
    public function ensureCollaborationRteIdConfigurationBuildsCanonicalFieldId(): void
    {
        $modifier = $this->createAccessibleModifier();

        $data = [
            'tableName' => 'tt_content',
            'fieldName' => 'bodytext',
            'recordUid' => 141,
        ];

        $result = $this->invokePrivate($modifier, 'ensureCollaborationRteIdConfiguration', [[], $data]);

        self::assertSame('data[tt_content][141][bodytext]', $result['collaboration']['rteId']);
    }

    #[Test]
    public function hasRealTimeOrNonRealTimeDetectsRealTime(): void
    {
        $modifier = $this->createAccessibleModifier();
        self::assertTrue($this->invokePrivate($modifier, 'hasRealTimeOrNonRealTime', [['RealTime' => []]]));
    }

    #[Test]
    public function hasRealTimeOrNonRealTimeDetectsNonRealTime(): void
    {
        $modifier = $this->createAccessibleModifier();
        self::assertTrue($this->invokePrivate($modifier, 'hasRealTimeOrNonRealTime', [['NonRealTime' => []]]));
    }

    #[Test]
    public function hasRealTimeOrNonRealTimeReturnsFalseWhenNeitherKeyPresent(): void
    {
        $modifier = $this->createAccessibleModifier();
        self::assertFalse($this->invokePrivate($modifier, 'hasRealTimeOrNonRealTime', [['Other' => []]]));
    }

    #[Test]
    public function mergeAndUnsetModulesRemovesRealtimeKeysAndMergesSpecificModules(): void
    {
        $modifier = $this->createAccessibleModifier();

        $modules = [
            'RealTime' => ['rt-1'],
            'NonRealTime' => ['nrt-1'],
            'OtherKey' => 'kept',
        ];
        $specificModules = ['spec-1', 'spec-2'];

        $result = $this->invokePrivate($modifier, 'mergeAndUnsetModules', [$modules, $specificModules]);

        self::assertArrayNotHasKey('RealTime', $result);
        self::assertArrayNotHasKey('NonRealTime', $result);
        self::assertContains('spec-1', $result);
        self::assertContains('spec-2', $result);
        self::assertSame('kept', $result['OtherKey']);
    }

    #[Test]
    public function processFieldConfigurationBuildsMentionConfigurationViaBuilder(): void
    {
        $expected = ['feeds' => [['marker' => '@', 'feed' => ['Alice']]]];

        $mentionBuilder = $this->createMock(MentionConfigurationBuilder::class);
        $mentionBuilder->expects(self::once())
            ->method('buildConfiguration')
            ->willReturn($expected);

        GeneralUtility::addInstance(MentionConfigurationBuilder::class, $mentionBuilder);

        $modifier = $this->createAccessibleModifier();
        $result = $this->invokePrivate(
            $modifier,
            'processFieldConfiguration',
            [null, ['mention' => ['marker' => '@']], [], 'Mention']
        );

        self::assertSame($expected, $result['mention']);
    }

    #[Test]
    public function processFieldConfigurationReturnsConfigurationUnchangedWhenFieldArrayIsEmpty(): void
    {
        $modifier = $this->createAccessibleModifier();
        $existing = ['existing' => 'value'];

        $result = $this->invokePrivate(
            $modifier,
            'processFieldConfiguration',
            [null, [], $existing, 'SomethingElse']
        );

        self::assertSame($existing, $result);
    }

    #[Test]
    public function processFieldConfigurationAssignsExplodedStringForStringFieldValues(): void
    {
        $modifier = $this->createAccessibleModifier();

        $result = $this->invokePrivate(
            $modifier,
            'processFieldConfiguration',
            ['bold,italic,underline', ['fontFamily' => ['options' => 'whatever']], [], 'Font']
        );

        self::assertArrayHasKey('fontFamily', $result);
        self::assertSame(['bold', 'italic', 'underline'], $result['fontFamily']);
    }

    #[Test]
    public function resolveRestrictedEditingToolbarItemMapsStandardModeToExceptionItem(): void
    {
        $modifier = $this->createAccessibleModifier();
        $configuration = [
            'toolbar' => [
                'items' => ['bold', 'restrictedEditing', 'italic'],
            ],
        ];

        $result = $this->invokePrivate(
            $modifier,
            'resolveRestrictedEditingToolbarItem',
            [$configuration, 'standard']
        );

        self::assertSame(['bold', 'restrictedEditingException', 'italic'], $result['toolbar']['items']);
    }

    #[Test]
    public function resolveRestrictedEditingToolbarItemMapsRestrictedModeToNavigationItem(): void
    {
        $modifier = $this->createAccessibleModifier();
        $configuration = [
            'toolbar' => [
                'items' => ['bold', 'restrictedEditingException', 'italic'],
            ],
        ];

        $result = $this->invokePrivate(
            $modifier,
            'resolveRestrictedEditingToolbarItem',
            [$configuration, 'restricted']
        );

        self::assertSame(['bold', 'restrictedEditing', 'italic'], $result['toolbar']['items']);
    }

    #[Test]
    public function rewriteRestrictedEditingToolbarItemsCollapsesLegacyVariantsToSingleItem(): void
    {
        $modifier = $this->createAccessibleModifier();
        $configuration = [
            'toolbar' => [
                'items' => [
                    'bold',
                    'restrictedEditing',
                    'restrictedEditingException',
                    'restrictedEditing:dropdown',
                    '|',
                    'italic',
                ],
            ],
        ];

        $result = $this->invokePrivate(
            $modifier,
            'rewriteRestrictedEditingToolbarItems',
            [$configuration, 'restrictedEditing']
        );

        self::assertSame(['bold', 'restrictedEditing', '|', 'italic'], $result['toolbar']['items']);
    }

    #[Test]
    public function ensureRestrictedEditingDefaultsStripsPackOnlyModeKey(): void
    {
        $modifier = $this->createAccessibleModifier();
        $configuration = [
            'restrictedEditing' => [
                'mode' => 'restricted',
                'allowedCommands' => ['bold'],
            ],
        ];

        $result = $this->invokePrivate($modifier, 'ensureRestrictedEditingDefaults', [$configuration]);

        self::assertArrayNotHasKey('mode', $result['restrictedEditing']);
        self::assertSame(['bold'], $result['restrictedEditing']['allowedCommands']);
    }

    #[Test]
    public function ensureRestrictedEditingDefaultsRemovesEmptyRestrictedEditingNode(): void
    {
        $modifier = $this->createAccessibleModifier();
        $configuration = [
            'restrictedEditing' => [
                'mode' => 'standard',
            ],
        ];

        $result = $this->invokePrivate($modifier, 'ensureRestrictedEditingDefaults', [$configuration]);

        self::assertArrayNotHasKey('restrictedEditing', $result);
    }

    #[Test]
    public function addRestrictedEditingModulesLoadsStandardEditingModeByDefault(): void
    {
        $modifier = $this->createAccessibleModifier();
        $feature = new class () {
            public function getFields(): string
            {
                return '';
            }
        };

        $configuration = [
            'toolbar' => ['items' => ['restrictedEditing']],
            'importModules' => [],
        ];

        $result = $this->invokePrivate(
            $modifier,
            'addRestrictedEditingModules',
            [$configuration, ['module' => []], $feature]
        );

        self::assertSame('restrictedEditingException', $result['toolbar']['items'][0]);
        self::assertContains(
            [
                'module' => '@ckeditor/ckeditor5-restricted-editing',
                'exports' => ['StandardEditingMode'],
            ],
            $result['importModules']
        );
    }

    #[Test]
    public function addRestrictedEditingModulesLoadsRestrictedEditingModeWhenConfigured(): void
    {
        $modifier = $this->createAccessibleModifier();
        $feature = new class () {
            public function getFields(): string
            {
                return json_encode(['restrictedEditing' => ['mode' => 'restricted']], JSON_THROW_ON_ERROR);
            }
        };

        $configuration = [
            'toolbar' => ['items' => ['restrictedEditing']],
            'importModules' => [],
        ];

        $result = $this->invokePrivate(
            $modifier,
            'addRestrictedEditingModules',
            [$configuration, ['module' => []], $feature]
        );

        self::assertSame('restrictedEditing', $result['toolbar']['items'][0]);
        self::assertContains(
            [
                'module' => '@ckeditor/ckeditor5-restricted-editing',
                'exports' => ['RestrictedEditingMode'],
            ],
            $result['importModules']
        );
    }

    #[Test]
    public function isEnableRealTimeCollaborationDoesNotBlockWhenRestrictedEditingIsEnabled(): void
    {
        $preset = $this->createMock(Preset::class);
        $preset->method('getUid')->willReturn(1);
        $this->mockedPresetRepository->method('findByUsage')->willReturn($preset);

        $enabledFeature = $this->createMock(Feature::class);
        $enabledFeature->method('isEnable')->willReturn(true);

        $this->mockedFeatureRepository->method('findByPresetUidAndConfigKey')
            ->willReturnCallback(
                static fn(int $uid, string $key): ?Feature => in_array(
                    $key,
                    ['RealTimeCollaboration', 'RestrictedEditingMode'],
                    true
                ) ? $enabledFeature : null
            );

        $modifier = $this->createAccessibleModifier();

        self::assertTrue($this->invokePrivate($modifier, 'isEnableRealTimeCollaboration'));
    }

    #[Test]
    public function buildCollaborationSchemaFingerprintIncludesRestrictedEditingMode(): void
    {
        $preset = $this->createMock(Preset::class);
        $preset->method('getUid')->willReturn(1);
        $this->mockedPresetRepository->method('findByUsage')->willReturn($preset);

        $rtc = $this->createMock(Feature::class);
        $rtc->method('isEnable')->willReturn(true);

        $restricted = $this->createMock(Feature::class);
        $restricted->method('isEnable')->willReturn(true);
        $restricted->method('getFields')->willReturn(
            json_encode(['restrictedEditing' => ['mode' => 'restricted']], JSON_THROW_ON_ERROR)
        );

        $this->mockedFeatureRepository->method('findByPresetUidAndConfigKey')
            ->willReturnCallback(
                static function (int $uid, string $key) use ($rtc, $restricted): ?Feature {
                    return match ($key) {
                        'RealTimeCollaboration' => $rtc,
                        'RestrictedEditingMode' => $restricted,
                        default => null,
                    };
                }
            );

        $modifier = $this->createAccessibleModifier();
        $fingerprint = $this->invokePrivate($modifier, 'buildCollaborationSchemaFingerprint');

        self::assertStringContainsString('RealTimeCollaboration', $fingerprint);
        self::assertStringContainsString('RestrictedEditingMode:restricted', $fingerprint);
        self::assertStringStartsWith('rtc-map-v2', $fingerprint);
    }
}
