<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Domain\Repository\PresetRepository;
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
 * Unit test for domain repository PresetRepository
 */
class PresetRepositoryTest extends BaseTestCase
{
    /** @var PresetRepository|AccessibleObjectInterface */
    protected $presetRepository;

    /** @var QuerySettingsInterface|MockObject */
    protected $mockedQuerySettings;

    protected function setUp(): void
    {
        $this->presetRepository = $this->getAccessibleMock(
            PresetRepository::class,
            ['createQuery', 'setDefaultQuerySettings'],
            [],
            '',
            false
        );

        $this->mockedQuerySettings = $this->createMock(QuerySettingsInterface::class);
        $this->mockedQuerySettings->method('setRespectStoragePage')->willReturnSelf();
        $this->mockedQuerySettings->method('setIgnoreEnableFields')->willReturnSelf();

        $this->presetRepository->_set('querySettings', $this->mockedQuerySettings);
    }

    #[Test]
    public function injectQuerySettingsAssignsTheGivenInstance(): void
    {
        $repository = $this->getAccessibleMock(
            PresetRepository::class,
            null,
            [],
            '',
            false
        );

        $querySettings = $this->createMock(QuerySettingsInterface::class);
        $repository->injectQuerySettings($querySettings);

        self::assertSame($querySettings, $repository->_get('querySettings'));
    }

    #[Test]
    public function initializeObjectAppliesDefaultQuerySettings(): void
    {
        $this->mockedQuerySettings->expects(self::once())
            ->method('setRespectStoragePage')
            ->with(false)
            ->willReturnSelf();
        $this->mockedQuerySettings->expects(self::once())
            ->method('setIgnoreEnableFields')
            ->with(true)
            ->willReturnSelf();

        $this->presetRepository->expects(self::exactly(2))
            ->method('setDefaultQuerySettings')
            ->with($this->mockedQuerySettings);

        $this->presetRepository->initializeObject();
    }

    #[Test]
    public function findByPresetKeyAppliesEqualsAndLimit(): void
    {
        $presetKey = 'default';
        $expectedPreset = new Preset();
        $expectedPreset->setPresetKey($presetKey);

        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $comparison = new Comparison(new PropertyValue('presetKey', ''), QueryInterface::OPERATOR_EQUAL_TO, $presetKey);

        $mockedQuery->expects(self::once())
            ->method('equals')
            ->with('presetKey', $presetKey)
            ->willReturn($comparison);
        $mockedQuery->expects(self::once())
            ->method('matching')
            ->with($comparison)
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
            ->willReturn($expectedPreset);

        $this->presetRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->presetRepository->findByPresetKey($presetKey);
        self::assertInstanceOf(Preset::class, $result);
        self::assertSame($expectedPreset, $result);
    }

    #[Test]
    public function findByPresetKeyReturnsNullWhenNoMatch(): void
    {
        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $mockedQuery->method('equals')
            ->willReturn(new Comparison(new PropertyValue('presetKey', ''), QueryInterface::OPERATOR_EQUAL_TO, 'missing'));
        $mockedQuery->method('matching')->willReturnSelf();
        $mockedQuery->method('setLimit')->willReturnSelf();
        $mockedQuery->method('execute')->willReturn($mockedQueryResult);

        $mockedQueryResult->expects(self::once())
            ->method('getFirst')
            ->willReturn(null);

        $this->presetRepository->method('createQuery')->willReturn($mockedQuery);

        $result = $this->presetRepository->findByPresetKey('missing');
        self::assertNull($result);
    }

    #[Test]
    public function findAllOrdersByPresetKeyAscending(): void
    {
        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $mockedQuery->expects(self::once())
            ->method('setOrderings')
            ->with(['presetKey' => QueryInterface::ORDER_ASCENDING])
            ->willReturnSelf();
        $mockedQuery->expects(self::once())
            ->method('execute')
            ->willReturn($mockedQueryResult);

        $mockedQueryResult->expects(self::once())
            ->method('toArray')
            ->willReturn([]);

        $this->presetRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->presetRepository->findAll();
        self::assertIsArray($result);
    }

    #[Test]
    public function findByUidAppliesEqualsAndLimit(): void
    {
        $uid = 42;
        $expectedPreset = new Preset();

        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);

        $comparison = new Comparison(new PropertyValue('uid', ''), QueryInterface::OPERATOR_EQUAL_TO, $uid);

        $mockedQuery->expects(self::once())
            ->method('equals')
            ->with('uid', $uid)
            ->willReturn($comparison);
        $mockedQuery->expects(self::once())
            ->method('matching')
            ->with($comparison)
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
            ->willReturn($expectedPreset);

        $this->presetRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->presetRepository->findByUid($uid);
        self::assertSame($expectedPreset, $result);
    }

    #[Test]
    public function findByUsageUsesLogicalAndAndRespectsEnableFields(): void
    {
        $presetKey = 'custom';
        $expectedPreset = new Preset();

        $mockedQuery = $this->createMock(Query::class);
        $mockedQueryResult = $this->createMock(QueryResult::class);
        $perQuerySettings = $this->createMock(QuerySettingsInterface::class);

        $perQuerySettings->expects(self::once())
            ->method('setIgnoreEnableFields')
            ->with(false)
            ->willReturnSelf();

        $mockedQuery->expects(self::once())
            ->method('getQuerySettings')
            ->willReturn($perQuerySettings);
        $mockedQuery->expects(self::once())
            ->method('setQuerySettings')
            ->with($perQuerySettings)
            ->willReturnSelf();

        $comparison1 = new Comparison(new PropertyValue('presetKey', ''), QueryInterface::OPERATOR_EQUAL_TO, $presetKey);
        $comparison2 = new Comparison(new PropertyValue('usageSource', ''), QueryInterface::OPERATOR_EQUAL_TO, 0);
        $logicalAnd = GeneralUtility::makeInstance(LogicalAnd::class, $comparison1, $comparison2);

        $mockedQuery->expects(self::exactly(2))
            ->method('equals')
            ->willReturnCallback(function ($property, $value) use ($comparison1, $comparison2, $presetKey) {
                if ($property === 'presetKey' && $value === $presetKey) {
                    return $comparison1;
                }
                if ($property === 'usageSource' && $value === 0) {
                    return $comparison2;
                }
                return new Comparison(new PropertyValue('', ''), QueryInterface::OPERATOR_EQUAL_TO, null);
            });

        $mockedQuery->expects(self::once())
            ->method('logicalAnd')
            ->willReturn($logicalAnd);

        $mockedQuery->expects(self::once())
            ->method('matching')
            ->with($logicalAnd)
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
            ->willReturn($expectedPreset);

        $this->presetRepository->expects(self::once())
            ->method('createQuery')
            ->willReturn($mockedQuery);

        $result = $this->presetRepository->findByUsage($presetKey);
        self::assertSame($expectedPreset, $result);
    }
}
