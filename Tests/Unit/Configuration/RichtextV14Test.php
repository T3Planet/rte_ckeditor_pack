<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use T3Planet\RteCkeditorPack\Configuration\RichtextV14;
use T3Planet\RteCkeditorPack\Utility\ProcessingConfigurationUtility;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\Event\AfterRichtextConfigurationPreparedEvent;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Configuration\Richtext as CoreRichtext;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Unit test for Configuration\RichtextV14 (TYPO3 v14+ only).
 *
 * Excluded on v12/v13 via --exclude-group typo3-v14.
 */
#[Group('typo3-v14')]
class RichtextV14Test extends BaseTestCase
{
    #[Test]
    public function classIsReadonlyAndExtendsCoreRichtext(): void
    {
        $reflection = new \ReflectionClass(RichtextV14::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue(is_subclass_of(RichtextV14::class, CoreRichtext::class));
    }

    #[Test]
    public function getConfigurationInjectsEditorContext(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(
            static fn(AfterRichtextConfigurationPreparedEvent $event): AfterRichtextConfigurationPreparedEvent => $event
        );

        $runtimeCache = $this->createMock(FrontendInterface::class);
        $runtimeCache->method('get')->willReturn(false);

        $yamlFileLoader = $this->createMock(YamlFileLoader::class);
        $typoScriptService = $this->createMock(TypoScriptService::class);

        /** @var RichtextV14|AccessibleObjectInterface|MockObject $richtext */
        $richtext = $this->getAccessibleMock(
            RichtextV14::class,
            ['getRtePageTsConfigOfPid'],
            [$eventDispatcher, $runtimeCache, $yamlFileLoader, $typoScriptService]
        );
        $richtext->method('getRtePageTsConfigOfPid')->willReturn([]);

        $result = $richtext->getConfiguration('tt_content', 'bodytext', 7, 'textmedia', []);

        $contextKey = ProcessingConfigurationUtility::RICH_TEXT_EDITOR_CONTEXT_KEY;
        self::assertArrayHasKey($contextKey, $result['editor']['config'] ?? []);
        self::assertSame(
            [
                'table' => 'tt_content',
                'field' => 'bodytext',
                'pid' => 7,
                'recordType' => 'textmedia',
            ],
            $result['editor']['config'][$contextKey]
        );
    }
}
