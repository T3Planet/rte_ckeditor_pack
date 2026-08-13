<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use T3Planet\RteCkeditorPack\Middleware\VisualEditorStylesMiddleware;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Visual Editor host-page styles (notification.css) — TYPO3 v14 only.
 *
 * Excluded on v12/v13 via PHPUnit group typo3-v14 in runTests.sh.
 */
#[Group('typo3-v14')]
final class VisualEditorStylesMiddlewareTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if ((new Typo3Version())->getMajorVersion() < 14) {
            self::markTestSkipped('VisualEditorStylesMiddlewareTest runs on TYPO3 v14 only.');
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    #[Test]
    public function passesThroughWithoutInjectingWhenEditModeMissing(): void
    {
        $collector = $this->createMock(AssetCollector::class);
        $collector->expects(self::never())->method('addStyleSheet');

        $middleware = new VisualEditorStylesMiddleware($collector);
        $request = new ServerRequest('https://example.com/');

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function doesNotInjectWithoutBackendUserEvenWithEditMode(): void
    {
        unset($GLOBALS['BE_USER']);

        $collector = $this->createMock(AssetCollector::class);
        $collector->expects(self::never())->method('addStyleSheet');

        $middleware = new VisualEditorStylesMiddleware($collector);
        $request = (new ServerRequest('https://example.com/'))
            ->withQueryParams(['editMode' => '1']);

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function injectsPackStylesWhenVisualEditorEditModeAndBackendUserPresent(): void
    {
        if (!ExtensionManagementUtility::isLoaded('visual_editor')) {
            self::markTestSkipped('visual_editor extension is not loaded');
        }

        $GLOBALS['BE_USER'] = $this->createMock(BackendUserAuthentication::class);

        $collector = $this->createMock(AssetCollector::class);
        $collector->expects(self::exactly(3))
            ->method('addStyleSheet')
            ->willReturnCallback(static function (string $identifier, string $source): void {
                self::assertContains($identifier, [
                    'rte-ckeditor-pack-revision-viewer',
                    'rte-ckeditor-pack-notification',
                    'rte-ckeditor-pack-mathtype',
                ]);
                self::assertStringContainsString('EXT:rte_ckeditor_pack/Resources/Public/Css/', $source);
            });

        $middleware = new VisualEditorStylesMiddleware($collector);
        $request = (new ServerRequest('https://example.com/'))
            ->withQueryParams(['editMode' => '1']);

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    private function passthroughHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }
}
