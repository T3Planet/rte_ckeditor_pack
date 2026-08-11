<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result as DBALResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use T3Planet\RteCkeditorPack\Service\PackRecordPersister;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for domain repository ToolbarGroupsRepository
 */
class ToolbarGroupsRepositoryTest extends BaseTestCase
{
    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_preset';

    /** @var QueryBuilder|MockObject */
    protected $mockedQueryBuilder;

    /** @var ExpressionBuilder|MockObject */
    protected $mockedExpressionBuilder;

    /** @var QueryRestrictionContainerInterface|MockObject */
    protected $mockedRestrictions;

    protected function setUp(): void
    {
        $this->mockedQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->mockedExpressionBuilder = $this->createMock(ExpressionBuilder::class);
        $this->mockedRestrictions = $this->createMock(QueryRestrictionContainerInterface::class);

        $this->mockedQueryBuilder->method('expr')->willReturn($this->mockedExpressionBuilder);
        $this->mockedQueryBuilder->method('getRestrictions')->willReturn($this->mockedRestrictions);
        $this->mockedRestrictions->method('add')->willReturnSelf();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @return ToolbarGroupsRepository|AccessibleObjectInterface|MockObject
     */
    private function createRepositoryWithInsertMocked()
    {
        $repository = $this->getAccessibleMock(
            ToolbarGroupsRepository::class,
            ['insertToolBarPreset', 'getQueryBuilder'],
            [],
            '',
            false
        );
        $repository->method('getQueryBuilder')->willReturn($this->mockedQueryBuilder);

        return $repository;
    }

    /**
     * @return ToolbarGroupsRepository|AccessibleObjectInterface|MockObject
     */
    private function createRepositoryWithQueryBuilderOverride()
    {
        $repository = $this->getAccessibleMock(
            ToolbarGroupsRepository::class,
            ['getQueryBuilder'],
            [],
            '',
            false
        );
        $repository->method('getQueryBuilder')->willReturn($this->mockedQueryBuilder);

        return $repository;
    }

    #[Test]
    public function updateToolBarItemsNormalizesDuplicatesAndPreservesSeparators(): void
    {
        $items = 'bold, italic , bold , | , italic, - , underline';
        $activePreset = 'default';

        $repository = $this->createRepositoryWithInsertMocked();
        $repository->expects(self::once())
            ->method('insertToolBarPreset')
            ->with(
                $activePreset,
                self::callback(function (array $data) use ($activePreset) {
                    return $data['preset_key'] === $activePreset
                        && $data['toolbar_items'] === 'bold,italic,|,-,underline';
                })
            )
            ->willReturn(true);

        $result = $repository->updateToolBarItems($items, $activePreset);
        self::assertTrue($result);
    }

    #[Test]
    public function updateToolBarItemsReturnsFalseWhenInsertToolBarPresetFails(): void
    {
        $repository = $this->createRepositoryWithInsertMocked();
        $repository->method('insertToolBarPreset')->willReturn(false);

        $result = $repository->updateToolBarItems('bold,italic', 'default');
        self::assertFalse($result);
    }

    #[Test]
    public function findPresetsBuildsSelectWithoutConstraintsWhenNoItems(): void
    {
        $expectedResult = [
            ['preset_key' => 'default', 'toolbar_items' => 'bold,italic'],
        ];

        $resultMock = $this->createMock(DBALResult::class);
        $resultMock->method('fetchAllAssociative')->willReturn($expectedResult);

        $this->mockedQueryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();
        $this->mockedQueryBuilder->expects(self::once())
            ->method('from')
            ->with(self::TABLE_NAME)
            ->willReturnSelf();
        $this->mockedQueryBuilder->expects(self::never())
            ->method('where');
        $this->mockedQueryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($resultMock);

        $repository = $this->createRepositoryWithQueryBuilderOverride();
        $result = $repository->findPresets();
        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function findPresetsAppliesInSetConstraintsForGivenItems(): void
    {
        $items = ['bold', 'italic'];
        $compositeOr = $this->createMock(CompositeExpression::class);

        $this->mockedExpressionBuilder->expects(self::exactly(2))
            ->method('inSet')
            ->willReturnCallback(static function (string $field, string $param): string {
                return $field . ' INSET ' . $param;
            });
        $this->mockedExpressionBuilder->expects(self::once())
            ->method('or')
            ->willReturn($compositeOr);

        $this->mockedQueryBuilder->expects(self::exactly(2))
            ->method('createNamedParameter')
            ->willReturnCallback(static fn(string $value): string => ':' . $value);

        $this->mockedQueryBuilder->expects(self::once())
            ->method('select')
            ->with('preset_key,toolbar_items')
            ->willReturnSelf();
        $this->mockedQueryBuilder->expects(self::once())
            ->method('from')
            ->with(self::TABLE_NAME)
            ->willReturnSelf();
        $this->mockedQueryBuilder->expects(self::once())
            ->method('where')
            ->with($compositeOr)
            ->willReturnSelf();

        $resultMock = $this->createMock(DBALResult::class);
        $resultMock->method('fetchAllAssociative')->willReturn([]);

        $this->mockedQueryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($resultMock);

        $repository = $this->createRepositoryWithQueryBuilderOverride();
        $result = $repository->findPresets($items, 'preset_key,toolbar_items');
        self::assertSame([], $result);
    }

    #[Test]
    public function insertToolBarPresetDelegatesToPackRecordPersister(): void
    {
        $activePreset = 'default';
        $fieldData = ['preset_key' => $activePreset, 'toolbar_items' => 'bold,italic'];

        $persister = $this->createMock(PackRecordPersister::class);
        $persister->expects(self::once())
            ->method('upsertPresetByKey')
            ->with($activePreset, $fieldData)
            ->willReturn(42);

        $repository = new ToolbarGroupsRepository();
        $repository->injectPackRecordPersister($persister);

        self::assertTrue($repository->insertToolBarPreset($activePreset, $fieldData));
    }

    #[Test]
    public function insertToolBarPresetReturnsFalseWhenPersisterReturnsZero(): void
    {
        $persister = $this->createMock(PackRecordPersister::class);
        $persister->method('upsertPresetByKey')->willReturn(0);

        $repository = new ToolbarGroupsRepository();
        $repository->injectPackRecordPersister($persister);

        self::assertFalse($repository->insertToolBarPreset('default', ['preset_key' => 'default']));
    }
}
