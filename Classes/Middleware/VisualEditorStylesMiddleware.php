<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Loads revision-history UI styles in Visual Editor edit mode.
 */
final readonly class VisualEditorStylesMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AssetCollector $assetCollector,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            ExtensionManagementUtility::isLoaded('visual_editor')
            && isset($request->getQueryParams()['editMode'])
            && ($GLOBALS['BE_USER'] ?? null) instanceof BackendUserAuthentication
        ) {
            $this->assetCollector->addStyleSheet(
                'rte-ckeditor-pack-revision-viewer',
                'EXT:rte_ckeditor_pack/Resources/Public/Css/revision-viewer.css'
            );
        }

        return $handler->handle($request);
    }
}
