<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Middleware;

use T3Planet\RteCkeditorPack\Utility\RteMarkupTransformationUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\StreamFactory;

class ParsedHtmlForFrontend implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($this->isTypeNumSet($request) === false) {
            $stream = $response->getBody();
            $stream->rewind();
            $content = $stream->getContents();
            // Visual Editor editMode embeds raw field HTML in ve-editable-rich-text.
            // Stripping comment markers there removes highlights for FormEngine parity.
            // Only skip strip for authenticated backend users in edit mode (TYPO3 12–14).
            $stripCollaborationMarkup = !$this->isVisualEditorEditMode($request);
            $newBody = (new StreamFactory())->createStream(
                RteMarkupTransformationUtility::transform($content, $stripCollaborationMarkup)
            );
            $response = $response->withBody($newBody);
        }
        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isTypeNumSet(ServerRequestInterface $request): bool
    {
        $routing = $request->getAttribute('routing');
        if ($routing === null || !is_object($routing) || !method_exists($routing, 'getPageType')) {
            return false;
        }

        return (int)$routing->getPageType() > 0;
    }

    private function isVisualEditorEditMode(ServerRequestInterface $request): bool
    {
        if (!isset($request->getQueryParams()['editMode'])) {
            return false;
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication
            && (int)($backendUser->user['uid'] ?? 0) > 0;
    }
}
