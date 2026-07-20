<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Result as DBALResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use T3Planet\RteCkeditorPack\Domain\Repository\ToolbarGroupsRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for domain repository ToolbarGroupsRepository
 */
class ToolbarGroupsRepositoryTest extends BaseTestCase
{
    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_preset';

    /** @var ConnectionPool|MockObject */
    protected $mockedConnectionPool;

    /** @var Connection|MockObject */
    protected $mockedConnection;

    /** @var QueryBuilder|MockObject */
    protected $mockedQueryBuilder;

    /** @var ExpressionBuilder|MockObject */
    protected $mockedExpressionBuilder;

    protected function setUp(): void
    {
        $this->mockedConnectionPool = $this->createMock(ConnectionPool::class);
        $this->mockedConnection = $this->createMock(Connection::class);
        $this->mockedQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->mockedExpressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->mockedQueryBuilder->method('expr')->willReturn($this->mockedExpressionBuilder);

        $this->mockedConnectionPool->method('getQueryBuilderForTable')
            ->willReturn($this->mockedQueryBuilder);
        $this->mockedConnectionPool->method('getConnectionForTable')
            ->willReturn($this->mockedConnection);
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
    public function updateToolBarItemsReturnsFalseWhenInsertToolBarPresetThrowsDbalException(): void
    {
        // Doctrine\DBAL\Exception became an interface in DBAL 3.x; instantiate a class that implements it.
        $dbalException = new class ('boom') extends \RuntimeException implements DBALException {};

        $repository = $this->createRepositoryWithInsertMocked();
        $repository->method('insertToolBarPreset')
            ->willThrowException($dbalException);

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
    public function insertToolBarPresetUpdatesWhenRecordExists(): void
    {
        $activePreset = 'default';
        $fieldData = ['preset_key' => $activePreset, 'toolbar_items' => 'bold,italic'];

        GeneralUtility::addInstance(ConnectionPool::class, $this->mockedConnectionPool);

        $resultMock = $this->createMock(DBALResult::class);
        $resultMock->method('fetchOne')->willReturn(99);

        $this->mockedQueryBuilder->expects(self::once())
            ->method('select')
            ->with('uid')
            ->willReturnSelf();
        $this->mockedQueryBuilder->expects(self::once())
            ->method('from')
            ->with(self::TABLE_NAME)
            ->willReturnSelf();
        $this->mockedQueryBuilder->expects(self::once())
            ->method('createNamedParameter')
            ->with($activePreset)
            ->willReturn(':' . $activePreset);
        $this->mockedExpressionBuilder->expects(self::once())
            ->method('eq')
            ->with('preset_key', ':' . $activePreset)
            ->willReturn('eqExpr');
        $this->mockedQueryBuilder->expects(self::once())
            ->method('where')
            ->with('eqExpr')
            ->willReturnSelf();
        $this->mockedQueryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($resultMock);

        $this->mockedConnection->expects(self::once())
            ->method('update')
            ->with(self::TABLE_NAME, $fieldData, ['preset_key' => $activePreset]);
        $this->mockedConnection->expects(self::never())
            ->method('insert');

        $repository = $this->createRepositoryWithQueryBuilderOverride();
        $result = $repository->insertToolBarPreset($activePreset, $fieldData);
        self::assertTrue($result);
    }

    #[Test]
    public function insertToolBarPresetInsertsWhenRecordDoesNotExist(): void
    {
        $activePreset = 'fresh-preset';
        $fieldData = ['preset_key' => $activePreset, 'toolbar_items' => 'bold'];

        GeneralUtility::addInstance(ConnectionPool::class, $this->mockedConnectionPool);

        $resultMock = $this->createMock(DBALResult::class);
        $resultMock->method('fetchOne')->willReturn(false);

        $this->mockedQueryBuilder->method('select')->willReturnSelf();
        $this->mockedQueryBuilder->method('from')->willReturnSelf();
        $this->mockedQueryBuilder->method('where')->willReturnSelf();
        $this->mockedQueryBuilder->method('createNamedParameter')
            ->willReturn(':' . $activePreset);
        $this->mockedExpressionBuilder->method('eq')->willReturn('eqExpr');
        $this->mockedQueryBuilder->method('executeQuery')->willReturn($resultMock);

        $this->mockedConnection->expects(self::once())
            ->method('insert')
            ->with(self::TABLE_NAME, $fieldData);
        $this->mockedConnection->expects(self::never())
            ->method('update');

        $repository = $this->createRepositoryWithQueryBuilderOverride();
        $result = $repository->insertToolBarPreset($activePreset, $fieldData);
        self::assertTrue($result);
    }
}
