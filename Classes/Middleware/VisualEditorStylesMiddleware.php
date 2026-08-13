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
 * Loads Pack UI styles in Visual Editor edit mode (revision viewer, error notifications).
 *
 * Visual Editor runs on the frontend host (TYPO3 v13+). Backend BE stylesheets are not applied
 * there, so these assets must be injected explicitly. No-op on TYPO3 v12 (no visual_editor).
 */
final class VisualEditorStylesMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AssetCollector $assetCollector,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldInjectStyles($request)) {
            $this->assetCollector->addStyleSheet(
                'rte-ckeditor-pack-revision-viewer',
                'EXT:rte_ckeditor_pack/Resources/Public/Css/revision-viewer.css'
            );
            // BE stylesheets are not applied on the VE frontend host — load explicitly.
            $this->assetCollector->addStyleSheet(
                'rte-ckeditor-pack-notification',
                'EXT:rte_ckeditor_pack/Resources/Public/Css/notification.css'
            );
            $this->assetCollector->addStyleSheet(
                'rte-ckeditor-pack-mathtype',
                'EXT:rte_ckeditor_pack/Resources/Public/Css/mathtype.css'
            );
        }

        return $handler->handle($request);
    }

    private function shouldInjectStyles(ServerRequestInterface $request): bool
    {
        if (!ExtensionManagementUtility::isLoaded('visual_editor')) {
            return false;
        }

        if (!isset($request->getQueryParams()['editMode'])) {
            return false;
        }

        return ($GLOBALS['BE_USER'] ?? null) instanceof BackendUserAuthentication;
    }
}
