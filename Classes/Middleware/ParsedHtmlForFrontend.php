<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
            $newBody = (new StreamFactory())->createStream($this->parsedComment($content));
            $response = $response->withBody($newBody);
        }
        return $response;
    }

    private function parsedComment($content)
    {
        $content = preg_replace('/&lt;comment-start.*?&gt;&lt;\/comment-start&gt;/s', '', $content);
        $content = preg_replace('/&lt;comment-end.*?&gt;&lt;\/comment-end&gt;/s', '', $content);
        return $content;
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isTypeNumSet(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('routing')->getPageType() > 0;
    }

}
