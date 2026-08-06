<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Configuration\Richtext;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
use TYPO3\CMS\Core\Configuration\Richtext as CoreRichtext;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for Configuration\Richtext (TYPO3 v12/v13 only).
 *
 * On TYPO3 v14, core Richtext is readonly — this subclass must not be autoloaded.
 * Use RichtextV14Test instead. Excluded via --exclude-group typo3-v12-v13 on v14 runs.
 */
#[Group('typo3-v12-v13')]
class RichtextTest extends BaseTestCase
{
    /** @var PresetRepository|MockObject */
    protected $mockedPresetRepository;

    protected function setUp(): void
    {
        $this->mockedPresetRepository = $this->createMock(PresetRepository::class);
        GeneralUtility::setSingletonInstance(PresetRepository::class, $this->mockedPresetRepository);

        $mockedFeatureRepository = $this->createMock(FeatureRepository::class);
        $mockedFeatureRepository->method('findByPresetUidAndConfigKey')->willReturn(null);
        $mockedFeatureRepository->method('findByConfigKey')->willReturn([]);
        GeneralUtility::setSingletonInstance(FeatureRepository::class, $mockedFeatureRepository);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function classExtendsCoreRichtext(): void
    {
        self::assertTrue(is_subclass_of(Richtext::class, CoreRichtext::class));
    }

    #[Test]
    public function getConfigurationInjectsCollaborationMarkersWhenNoPresetFound(): void
    {
        /** @var Richtext|AccessibleObjectInterface|MockObject $richtext */
        $richtext = $this->getAccessibleMock(
            Richtext::class,
            ['getRtePageTsConfigOfPid'],
            [],
            '',
            true
        );
        $richtext->method('getRtePageTsConfigOfPid')->willReturn([]);

        $this->mockedPresetRepository
            ->method('findByPresetKey')
            ->willReturn(null);
        $this->mockedPresetRepository
            ->method('findByUsage')
            ->willReturn(null);

        $result = $richtext->getConfiguration('tt_content', 'bodytext', 1, 'textmedia', []);

        self::assertIsArray($result);
        // No preset processing config, but applyAll always injects collaboration markers.
        self::assertSame(
            ['comment-start', 'comment-end', 'suggestion-start', 'suggestion-end', 'wbr'],
            $result['processing']['allowTags'] ?? null
        );
        self::assertSame(['name'], $result['processing']['allowAttributes'] ?? null);
    }

    #[Test]
    public function getConfigurationMergesProcessingConfigFromPreset(): void
    {
        /** @var Richtext|AccessibleObjectInterface|MockObject $richtext */
        $richtext = $this->getAccessibleMock(
            Richtext::class,
            ['getRtePageTsConfigOfPid'],
            [],
            '',
            true
        );
        $richtext->method('getRtePageTsConfigOfPid')->willReturn([]);

        $preset = new Preset();
        $preset->setProcessingConfig(json_encode([
            'allowTags' => 'p,div,span',
            'allowAttributes' => 'class,id',
        ]));

        $this->mockedPresetRepository
            ->method('findByPresetKey')
            ->willReturn($preset);

        $result = $richtext->getConfiguration('tt_content', 'bodytext', 1, 'textmedia', []);

        self::assertArrayHasKey('processing', $result);
        self::assertArrayHasKey('allowTags', $result['processing']);
        // Collaboration markers are prepended before preset allowTags.
        self::assertSame(
            ['comment-start', 'comment-end', 'suggestion-start', 'suggestion-end', 'wbr', 'p', 'div', 'span'],
            $result['processing']['allowTags']
        );
        self::assertSame(['name', 'class', 'id'], $result['processing']['allowAttributes']);
        self::assertArrayHasKey('proc.', $result);
        self::assertSame('default', $result['processing']['mode']);
    }
}
