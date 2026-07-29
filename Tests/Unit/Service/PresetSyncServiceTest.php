<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use T3Planet\RteCkeditorPack\Service\PresetSyncService;
use T3Planet\RteCkeditorPack\Service\SyncMode;
use T3Planet\RteCkeditorPack\Utility\ConfigurationMergeUtility;
use T3Planet\RteCkeditorPack\Utility\YamlLoadrUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\BaseTestCase;

final class PresetSyncServiceTest extends BaseTestCase
{
    /** @var PresetRepository&MockObject */
    private PresetRepository $presetRepository;

    /** @var FeatureRepository&MockObject */
    private FeatureRepository $featureRepository;

    /** @var PersistenceManager&MockObject */
    private PersistenceManager $persistenceManager;

    /** @var YamlLoadrUtility&MockObject */
    private YamlLoadrUtility $yamlLoader;

    /** @var ConfigurationMergeUtility&MockObject */
    private ConfigurationMergeUtility $mergeUtility;

    /** @var FrontendInterface&MockObject */
    private FrontendInterface $cache;

    private PresetSyncService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presetRepository = $this->createMock(PresetRepository::class);
        $this->featureRepository = $this->createMock(FeatureRepository::class);
        $this->persistenceManager = $this->createMock(PersistenceManager::class);
        $this->yamlLoader = $this->createMock(YamlLoadrUtility::class);
        $this->mergeUtility = $this->createMock(ConfigurationMergeUtility::class);
        $this->cache = $this->createMock(FrontendInterface::class);

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCache')->with('rte_ckeditor_config')->willReturn($this->cache);

        $this->subject = new PresetSyncService(
            $this->presetRepository,
            $this->featureRepository,
            $this->persistenceManager,
            $this->yamlLoader,
            $this->mergeUtility,
            $cacheManager,
        );
    }

    #[Test]
    public function syncPresetFailsWhenPresetMissing(): void
    {
        $this->presetRepository->method('findByPresetKey')->with('missing')->willReturn(null);

        $result = $this->subject->syncPreset('missing', SyncMode::Additive);

        self::assertFalse($result->success);
        self::assertStringContainsString('Preset not found', $result->message);
    }

    #[Test]
    public function additiveSyncMergesToolbarAndPersists(): void
    {
        $preset = $this->createPreset(5, 'default', 'bold');
        $this->presetRepository->method('findByUid')->with(5)->willReturn($preset);
        $this->yamlLoader->method('hasRegisteredPreset')->with('default')->willReturn(true);

        $this->yamlLoader->method('loadYamlConfiguration')->with('default')->willReturn([
            'editor' => [
                'config' => [
                    'toolbar' => ['items' => ['bold', 'italic']],
                ],
            ],
        ]);

        $this->mergeUtility->expects(self::once())
            ->method('syncToolBar')
            ->with(['bold', 'italic'], 'bold')
            ->willReturn('bold,italic');

        $this->featureRepository->method('findByPresetUid')->with(5)->willReturn([]);
        $this->presetRepository->expects(self::once())->method('update')->with($preset);
        $this->persistenceManager->expects(self::once())->method('persistAll');
        $this->cache->expects(self::once())->method('flush');

        $result = $this->subject->syncPreset(5, SyncMode::Additive);

        self::assertTrue($result->success);
        self::assertSame('bold,italic', $preset->getToolbarItems());
        self::assertSame(SyncMode::Additive, $result->mode);
    }

    #[Test]
    public function orderedSyncPreservesBackendItemsAndUsesOrderedMerge(): void
    {
        $preset = $this->createPreset(6, 'ordered', 'bold,customButton,italic');
        $this->presetRepository->method('findByUid')->with(6)->willReturn($preset);
        $this->yamlLoader->method('hasRegisteredPreset')->with('ordered')->willReturn(true);
        $this->yamlLoader->method('loadYamlConfiguration')->with('ordered')->willReturn([
            'editor' => [
                'config' => [
                    'toolbar' => ['items' => ['bold', 'horizontalLine', 'italic']],
                ],
            ],
        ]);

        $this->mergeUtility->expects(self::once())
            ->method('syncToolbarOrdered')
            ->with(
                ['bold', 'horizontalLine', 'italic'],
                'bold,customButton,italic'
            )
            ->willReturn('bold,customButton,horizontalLine,italic');

        $this->featureRepository->method('findByPresetUid')->with(6)->willReturn([]);

        $result = $this->subject->syncPreset(6, SyncMode::Ordered);

        self::assertTrue($result->success);
        self::assertSame('bold,customButton,horizontalLine,italic', $preset->getToolbarItems());
        self::assertSame(SyncMode::Ordered, $result->mode);
    }

    #[Test]
    public function strictSyncWritesYamlToolbarVerbatim(): void
    {
        $preset = $this->createPreset(7, 'editing', 'old,items');
        $this->presetRepository->method('findByPresetKey')->with('editing')->willReturn($preset);
        $this->yamlLoader->method('hasRegisteredPreset')->with('editing')->willReturn(true);

        $this->yamlLoader->method('loadYamlConfiguration')->with('editing')->willReturn([
            'editor' => [
                'config' => [
                    'toolbar' => ['items' => ['heading', 'bold', 'link']],
                    'wordcount' => ['displayWords' => true],
                ],
            ],
        ]);

        $this->mergeUtility->expects(self::never())->method('syncToolBar');

        $feature = $this->createFeature('WordCount', '{"wordcount":{"displayWords":false}}');
        $this->featureRepository->method('findByPresetUid')->with(7)->willReturn([$feature]);
        $this->featureRepository->expects(self::once())->method('update')->with($feature);
        $this->cache->expects(self::once())->method('flush');

        $result = $this->subject->syncPreset('editing', SyncMode::Strict);

        self::assertTrue($result->success);
        self::assertSame('heading,bold,link', $preset->getToolbarItems());
        self::assertSame(
            '{"wordcount":{"displayWords":true}}',
            $feature->getFields()
        );
        self::assertSame(SyncMode::Strict, $result->mode);
    }

    #[Test]
    public function resetClearsToolbarAndRemovesFeatures(): void
    {
        $preset = $this->createPreset(3, 'camino', 'a,b,c');
        $this->presetRepository->method('findByUid')->with(3)->willReturn($preset);
        $this->featureRepository->expects(self::once())
            ->method('removeByPresetId')
            ->with(3)
            ->willReturn(true);
        $this->cache->expects(self::once())->method('flush');

        $result = $this->subject->syncPreset(3, SyncMode::Reset);

        self::assertTrue($result->success);
        self::assertSame('', $preset->getToolbarItems());
        self::assertSame(SyncMode::Reset, $result->mode);
    }

    #[Test]
    public function customDatabasePresetIsSkippedForReset(): void
    {
        $preset = $this->createPreset(8, 'nst3ai', 'custom,toolbar');
        $preset->setIsCustom(true);
        $this->presetRepository->method('findByUid')->with(8)->willReturn($preset);

        $this->presetRepository->expects(self::never())->method('update');
        $this->featureRepository->expects(self::never())->method('removeByPresetId');
        $this->persistenceManager->expects(self::never())->method('persistAll');
        $this->cache->expects(self::never())->method('flush');

        $result = $this->subject->syncPreset(8, SyncMode::Reset);

        self::assertTrue($result->success);
        self::assertTrue($result->skipped);
        self::assertSame('custom,toolbar', $preset->getToolbarItems());
        self::assertStringContainsString('Custom database-only preset', $result->message);
    }

    #[Test]
    public function syncModeFromInputAcceptsAliases(): void
    {
        self::assertSame(SyncMode::Additive, SyncMode::fromInput('sync'));
        self::assertSame(SyncMode::Ordered, SyncMode::fromInput('ordered'));
        self::assertSame(SyncMode::Ordered, SyncMode::fromInput('position-aware'));
        self::assertSame(SyncMode::Strict, SyncMode::fromInput('yaml'));
        self::assertSame(SyncMode::Reset, SyncMode::fromInput('RESET'));
    }

    #[Test]
    public function syncAllAggregatesResults(): void
    {
        $presetA = $this->createPreset(1, 'a', '');
        $presetB = $this->createPreset(2, 'b', '');
        $this->presetRepository->method('findAll')->willReturn([$presetA, $presetB]);
        $this->presetRepository->method('findByUid')->willReturnCallback(
            static fn(int $uid): Preset => $uid === 1 ? $presetA : $presetB
        );

        $this->yamlLoader->method('hasRegisteredPreset')->willReturn(true);
        $this->yamlLoader->method('loadYamlConfiguration')->willReturn([
            'editor' => ['config' => ['toolbar' => ['items' => ['bold']]]],
        ]);
        $this->mergeUtility->method('syncToolBar')->willReturn('bold');
        $this->featureRepository->method('findByPresetUid')->willReturn([]);

        $batch = $this->subject->syncAll(SyncMode::Additive);

        self::assertTrue($batch['success']);
        self::assertSame(2, $batch['synced']);
        self::assertSame(0, $batch['skipped']);
        self::assertSame(0, $batch['failed']);
        self::assertCount(2, $batch['results']);
    }

    #[Test]
    public function syncAllSkipsDatabasePresetWithoutRegisteredYamlSource(): void
    {
        $registered = $this->createPreset(1, 'default', '');
        $orphan = $this->createPreset(9, 'orphan_preset', '');
        $this->presetRepository->method('findAll')->willReturn([$registered, $orphan]);
        $this->presetRepository->method('findByUid')->willReturnCallback(
            static fn(int $uid): ?Preset => match ($uid) {
                1 => $registered,
                9 => $orphan,
                default => null,
            }
        );

        $this->yamlLoader->method('hasRegisteredPreset')->willReturnMap([
            ['default', true],
            ['orphan_preset', false],
        ]);
        $this->yamlLoader->method('loadYamlConfiguration')->with('default')->willReturn([
            'editor' => ['config' => ['toolbar' => ['items' => ['bold']]]],
        ]);
        $this->mergeUtility->method('syncToolBar')->willReturn('bold');
        $this->featureRepository->method('findByPresetUid')->with(1)->willReturn([]);

        $batch = $this->subject->syncAll(SyncMode::Additive);

        self::assertTrue($batch['success']);
        self::assertSame(1, $batch['synced']);
        self::assertSame(1, $batch['skipped']);
        self::assertSame(0, $batch['failed']);
        self::assertTrue($batch['results'][1]->skipped);
        self::assertSame('orphan_preset', $batch['results'][1]->presetKey);
    }

    #[Test]
    public function syncAllSkipsCustomDatabaseOnlyPresets(): void
    {
        $registered = $this->createPreset(1, 'default', '');
        $custom = $this->createPreset(8, 'nst3ai', 'custom,toolbar');
        $custom->setIsCustom(true);
        $this->presetRepository->method('findAll')->willReturn([$registered, $custom]);
        $this->presetRepository->method('findByUid')->willReturnCallback(
            static fn(int $uid): ?Preset => match ($uid) {
                1 => $registered,
                8 => $custom,
                default => null,
            }
        );

        $this->yamlLoader->method('hasRegisteredPreset')->with('default')->willReturn(true);
        $this->yamlLoader->method('loadYamlConfiguration')->with('default')->willReturn([
            'editor' => ['config' => ['toolbar' => ['items' => ['bold']]]],
        ]);
        $this->mergeUtility->method('syncToolBar')->willReturn('bold');
        $this->featureRepository->method('findByPresetUid')->with(1)->willReturn([]);

        $batch = $this->subject->syncAll(SyncMode::Additive);

        self::assertTrue($batch['success']);
        self::assertSame(1, $batch['synced']);
        self::assertSame(1, $batch['skipped']);
        self::assertTrue($batch['results'][1]->skipped);
        self::assertSame('nst3ai', $batch['results'][1]->presetKey);
        self::assertSame('custom,toolbar', $custom->getToolbarItems());
    }

    private function createPreset(int $uid, string $key, string $toolbar): Preset
    {
        $preset = new Preset();
        $preset->_setProperty('uid', $uid);
        $preset->setPresetKey($key);
        $preset->setToolbarItems($toolbar);
        return $preset;
    }

    private function createFeature(string $configKey, string $fields): Feature
    {
        $feature = new Feature();
        $feature->setConfigKey($configKey);
        $feature->setFields($fields);
        return $feature;
    }
}
