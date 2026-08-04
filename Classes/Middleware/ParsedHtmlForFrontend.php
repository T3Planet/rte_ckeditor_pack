<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Middleware;

use T3Planet\RteCkeditorPack\Utility\MathMlFrontendRenderer;
use T3Planet\RteCkeditorPack\Utility\RteMarkupTransformationUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

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
            $content = RteMarkupTransformationUtility::transform($content, $stripCollaborationMarkup);
            $content = $this->normalizeAndBootstrapMathMl($content);
            $newBody = (new StreamFactory())->createStream($content);
            $response = $response->withBody($newBody);
        }
        return $response;
    }

    /**
     * Unescape/normalize MathML and ensure frontend MathJax bootstrap is present.
     */
    private function normalizeAndBootstrapMathMl(string $content): string
    {
        if ($content === '' || stripos($content, 'math') === false) {
            return $content;
        }

        $renderer = GeneralUtility::makeInstance(MathMlFrontendRenderer::class);
        $content = $renderer->process($content);

        if (!preg_match('/<math\b/i', $content)) {
            return $content;
        }

        if (str_contains($content, 'mathml-viewer.js') || str_contains($content, 'data-rte-ckeditor-pack-mathjax')) {
            return $content;
        }

        $scriptPath = GeneralUtility::getFileAbsFileName(
            'EXT:rte_ckeditor_pack/Resources/Public/JavaScript/Frontend/mathml-viewer.js'
        );
        if ($scriptPath === '') {
            return $content;
        }

        $scriptUrl = PathUtility::getAbsoluteWebPath($scriptPath);
        $scriptTag = '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES | ENT_HTML5)
            . '" async data-rte-ckeditor-pack-mathjax-loader="1"></script>';

        if (stripos($content, '</body>') !== false) {
            return str_ireplace('</body>', $scriptTag . '</body>', $content);
        }

        return $content . $scriptTag;
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
