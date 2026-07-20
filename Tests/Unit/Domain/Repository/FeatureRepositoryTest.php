<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use T3Planet\RteCkeditorPack\Domain\Repository\FeatureRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\Comparison;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\LogicalAnd;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\PropertyValue;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for domain repository FeatureRepository
 */
class FeatureRepositoryTest extends BaseTestCase
{
    /** @var FeatureRepository|AccessibleObjectInterface */
    protected $featureRepository;

    protected function setUp(): void
    {
        $this->featureRepository = $this->getAccessibleMock(
            FeatureRepository::class,
            ['createQuery'],
            [],
            '',
            false
        );

        $mockedQuerySettings = $this->createMock(QuerySettingsInterface::class);
        $mockedQuerySettings->expects(self::any())
            ->method('setRespectStoragePage')
            ->willReturnSelf();

        $this->featureRepository->_set('querySettings', $mockedQuerySettings);
    }

    #[Test]
    public function findByPresetUidCallsCorrectQueryMethods(): void
    {
        $presetUid = 123;
        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $propertyValue = new PropertyValue('presetUid', '');
        $comparison = new Comparison($propertyValue, QueryInterface::OPERATOR_EQUAL_TO, $presetUid);

        $mockedQuery->expects(self::once())
            ->method('equals')
            ->with('presetUid', $presetUid)
            ->willReturn($comparison);

        $mockedQuery->expects(self::once())
            ->method('matching')
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('setOrderings')
            ->with(['sorting' => QueryInterface::ORDER_ASCENDING])
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('execute')
            ->willReturn($mockedQueryResult);

        $mockedQueryResult->expects(self::once())
            ->method('toArray')
            ->willReturn([]);

        $this->featureRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->featureRepository->findByPresetUid($presetUid);
        self::assertIsArray($result);
    }

    #[Test]
    public function findEnabledByPresetUidUsesLogicalAnd(): void
    {
        $presetUid = 456;
        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $propertyValue1 = new PropertyValue('presetUid', '');
        $propertyValue2 = new PropertyValue('enable', '');
        $comparison1 = new Comparison($propertyValue1, QueryInterface::OPERATOR_EQUAL_TO, $presetUid);
        $comparison2 = new Comparison($propertyValue2, QueryInterface::OPERATOR_EQUAL_TO, 1);
        $logicalAnd = GeneralUtility::makeInstance(LogicalAnd::class, $comparison1, $comparison2);

        $mockedQuery->expects(self::exactly(2))
            ->method('equals')
            ->willReturnCallback(function ($property, $value) use ($comparison1, $comparison2, $presetUid) {
                if ($property === 'presetUid' && $value === $presetUid) {
                    return $comparison1;
                }
                if ($property === 'enable' && $value === 1) {
                    return $comparison2;
                }
                return new Comparison(new PropertyValue('', ''), QueryInterface::OPERATOR_EQUAL_TO, null);
            });

        $mockedQuery->expects(self::once())
            ->method('logicalAnd')
            ->willReturn($logicalAnd);

        $mockedQuery->expects(self::once())
            ->method('matching')
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('setOrderings')
            ->with(['sorting' => QueryInterface::ORDER_ASCENDING])
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('execute')
            ->willReturn($mockedQueryResult);

        $mockedQueryResult->expects(self::once())
            ->method('toArray')
            ->willReturn([]);

        $this->featureRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->featureRepository->findEnabledByPresetUid($presetUid);
        self::assertIsArray($result);
    }

    #[Test]
    public function findByPresetUidAndConfigKeySetsLimit(): void
    {
        $presetUid = 789;
        $configKey = 'test_key';
        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);
        $feature = new Feature();

        $propertyValue1 = new PropertyValue('presetUid', '');
        $propertyValue2 = new PropertyValue('configKey', '');
        $comparison1 = new Comparison($propertyValue1, QueryInterface::OPERATOR_EQUAL_TO, $presetUid);
        $comparison2 = new Comparison($propertyValue2, QueryInterface::OPERATOR_EQUAL_TO, $configKey);
        $logicalAnd = GeneralUtility::makeInstance(LogicalAnd::class, $comparison1, $comparison2);

        $mockedQuery->expects(self::exactly(2))
            ->method('equals')
            ->willReturnCallback(function ($property, $value) use ($comparison1, $comparison2, $presetUid, $configKey) {
                if ($property === 'presetUid' && $value === $presetUid) {
                    return $comparison1;
                }
                if ($property === 'configKey' && $value === $configKey) {
                    return $comparison2;
                }
                return new Comparison(new PropertyValue('', ''), QueryInterface::OPERATOR_EQUAL_TO, null);
            });

        $mockedQuery->expects(self::once())
            ->method('logicalAnd')
            ->willReturn($logicalAnd);

        $mockedQuery->expects(self::once())
            ->method('matching')
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('setLimit')
            ->with(1)
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('execute')
            ->willReturn($mockedQueryResult);

        $mockedQueryResult->expects(self::once())
            ->method('getFirst')
            ->willReturn($feature);

        $this->featureRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->featureRepository->findByPresetUidAndConfigKey($presetUid, $configKey);
        self::assertInstanceOf(Feature::class, $result);
    }

    #[Test]
    public function findByConfigKeyReturnsArray(): void
    {
        $configKey = 'test_config';
        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $propertyValue = new PropertyValue('configKey', '');
        $comparison = new Comparison($propertyValue, QueryInterface::OPERATOR_EQUAL_TO, $configKey);

        $mockedQuery->expects(self::once())
            ->method('equals')
            ->with('configKey', $configKey)
            ->willReturn($comparison);

        $mockedQuery->expects(self::once())
            ->method('matching')
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('setOrderings')
            ->with(['sorting' => QueryInterface::ORDER_ASCENDING])
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('execute')
            ->willReturn($mockedQueryResult);

        $mockedQueryResult->expects(self::once())
            ->method('toArray')
            ->willReturn([]);

        $this->featureRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->featureRepository->findByConfigKey($configKey);
        self::assertIsArray($result);
    }

    #[Test]
    public function findEnabledUsesConfigKeyOrdering(): void
    {
        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $propertyValue = new PropertyValue('enable', '');
        $comparison = new Comparison($propertyValue, QueryInterface::OPERATOR_EQUAL_TO, 1);

        $mockedQuery->expects(self::once())
            ->method('equals')
            ->with('enable', 1)
            ->willReturn($comparison);

        $mockedQuery->expects(self::once())
            ->method('matching')
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('setOrderings')
            ->with(['configKey' => QueryInterface::ORDER_ASCENDING])
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('execute')
            ->willReturn($mockedQueryResult);

        $mockedQueryResult->expects(self::once())
            ->method('toArray')
            ->willReturn([]);

        $this->featureRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->featureRepository->findEnabled();
        self::assertIsArray($result);
    }
}

