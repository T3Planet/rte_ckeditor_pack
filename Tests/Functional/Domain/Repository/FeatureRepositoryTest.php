<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional test for domain repository FeatureRepository
 */
class FeatureRepositoryTest extends FunctionalTestCase
{
    protected FeatureRepository $featureRepository;

    protected array $testExtensionsToLoad = ['typo3conf/ext/rte_ckeditor_pack'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $this->featureRepository = $this->getContainer()->get(FeatureRepository::class);

        $this->importCSVDataSet(__DIR__ . '/FeatureRepositoryTest/Fixtures/tx_rte_ckeditor_pack_domain_model_feature.csv');
    }

    #[Test]
    public function findByPresetUidReturnsCorrectFeatures(): void
    {
        $features = $this->featureRepository->findByPresetUid(1);

        self::assertCount(2, $features);
        self::assertInstanceOf(Feature::class, $features[0]);
        self::assertEquals(1, $features[0]->getPresetUid());
        self::assertEquals(1, $features[1]->getPresetUid());
    }

    #[Test]
    public function findByPresetUidReturnsEmptyArrayForNonExistentPreset(): void
    {
        $features = $this->featureRepository->findByPresetUid(999);

        self::assertIsArray($features);
        self::assertCount(0, $features);
    }

    #[Test]
    public function findEnabledByPresetUidReturnsOnlyEnabledFeatures(): void
    {
        $features = $this->featureRepository->findEnabledByPresetUid(1);

        self::assertCount(1, $features);
        self::assertInstanceOf(Feature::class, $features[0]);
        self::assertTrue($features[0]->isEnable());
        self::assertEquals(1, $features[0]->getPresetUid());
    }

    #[Test]
    public function findByPresetUidAndConfigKeyReturnsSpecificFeature(): void
    {
        $feature = $this->featureRepository->findByPresetUidAndConfigKey(1, 'test_feature_1');

        self::assertInstanceOf(Feature::class, $feature);
        self::assertEquals(1, $feature->getPresetUid());
        self::assertEquals('test_feature_1', $feature->getConfigKey());
    }

    #[Test]
    public function findByPresetUidAndConfigKeyReturnsNullWhenNotFound(): void
    {
        $feature = $this->featureRepository->findByPresetUidAndConfigKey(999, 'non_existent');

        self::assertNull($feature);
    }

    #[Test]
    public function findByConfigKeyReturnsFeaturesAcrossAllPresets(): void
    {
        $features = $this->featureRepository->findByConfigKey('common_feature');

        self::assertGreaterThanOrEqual(1, count($features));
        foreach ($features as $feature) {
            self::assertEquals('common_feature', $feature->getConfigKey());
        }
    }

    #[Test]
    public function findEnabledReturnsOnlyEnabledFeatures(): void
    {
        $features = $this->featureRepository->findEnabled();

        self::assertGreaterThanOrEqual(1, count($features));
        foreach ($features as $feature) {
            self::assertTrue($feature->isEnable());
        }
    }

    #[Test]
    public function removeByPresetIdRemovesAllFeaturesForPreset(): void
    {
        $presetUid = 2;
        $featuresBefore = $this->featureRepository->findByPresetUid($presetUid);
        $countBefore = count($featuresBefore);

        self::assertGreaterThan(0, $countBefore);

        $result = $this->featureRepository->removeByPresetId($presetUid);
        self::assertTrue($result);

        $featuresAfter = $this->featureRepository->findByPresetUid($presetUid);
        self::assertCount(0, $featuresAfter);
    }
}

