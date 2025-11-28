<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use T3Planet\RteCkeditorPack\Domain\Repository\CommentsRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CommentTread implements MiddlewareInterface
{
    /** @var ResponseFactoryInterface */
    private $responseFactory;

    private $currentUser;

    protected CommentsRepository $commentRepository;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->commentRepository = GeneralUtility::makeInstance(CommentsRepository::class);

        //Get Current Backend user..
        $context = GeneralUtility::makeInstance(Context::class);
        $this->currentUser = $context->getPropertyFromAspect('backend.user', 'id');

    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = null;
        if (str_contains($request->getRequestTarget(), '/comments/thread/')) {
            $response = $this->fetchAllComments($request);
        }
        if (str_contains($request->getRequestTarget(), '/comments/update/')) {
            $response = $this->updateComment($request);
        }
        if (str_contains($request->getRequestTarget(), '/comments/delete/')) {
            $response = $this->deleteComment($request);
        }
        if ($request->getRequestTarget() === '/comments') {
            $response = $this->addComment($request);
        }
        if ($response instanceof ResponseInterface) {
            return $response;
        }
        return $handler->handle($request);
    }

    /**
     * @param $request
     * @return \Psr\Http\Message\MessageInterface|ResponseInterface
     */
    private function fetchAllComments($request)
    {
        $threadId = $request->getQueryParams()['threadId'];
        $data = $this->commentRepository->fetchCommentsByThreatId($threadId);
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($data));
        return $response;
    }

    /**
     * @throws AspectNotFoundException
     * @throws \JsonException
     */
    private function updateComment($request)
    {
        $commentId = $request->getParsedBody()['commentId'] ?? null;
        $threadId = $request->getParsedBody()['threadId'] ?? null;
        $content = $request->getParsedBody()['content'] ?? null;
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $comment = $this->commentRepository->getComment($commentId, $threadId);
        if (empty($comment)) {
            $response->getBody()->write(json_encode(
                [
                    'status' => 'error',
                    'message' => 'Comment not found',
                ],
                JSON_THROW_ON_ERROR
            ));
            return $response;
        }
        if ($comment['user_id'] !== $this->currentUser) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Could not update comment - comment can be updated only by its author',
            ], JSON_THROW_ON_ERROR));
            return $response;
        }
        $this->commentRepository->updateComment($commentId, $threadId, $content);
        $response->getBody()->write(json_encode(
            [
                'status' => 'success',
                'message' => 'Comment Updated',
            ],
            JSON_THROW_ON_ERROR
        ));
        return $response;
    }

    /**
     * @param $request
     * @return \Psr\Http\Message\MessageInterface|ResponseInterface
     * @throws \JsonException
     */
    private function deleteComment($request)
    {
        $commentId = $request->getQueryParams()['comment_id'] ?? null;
        $threadId = $request->getQueryParams()['thread_id'] ?? null;
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $comment = $this->commentRepository->getComment($commentId, $threadId);
        if (empty($comment)) {
            $response->getBody()->write(json_encode(
                [
                    'status' => 'error',
                    'message' => 'Comment not found',
                ],
                JSON_THROW_ON_ERROR
            ));
            return $response;
        }
        if ($comment['user_id'] !== $this->currentUser) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Could not update comment - comment can be updated only by its author',
            ], JSON_THROW_ON_ERROR));
            return $response;
        }
        $this->commentRepository->deleteComment($commentId, $threadId);
        $response->getBody()->write(json_encode(
            [
                'status' => 'success',
                'message' => 'Comment deleted',
            ],
            JSON_THROW_ON_ERROR
        ));
        return $response;
    }

    private function addComment($request)
    {
        try {
            $parsedBody = $request->getParsedBody();

            // Handle multipart/form-data (FormData from JavaScript)
            $contentType = $request->getHeaderLine('Content-Type');
            if (str_contains($contentType, 'multipart/form-data') && empty($parsedBody)) {
                // For multipart/form-data, TYPO3 should parse it, but if not, we'll handle it
                // The parsed body should be available, but let's check uploads too
                $uploads = $request->getUploadedFiles();
                $parsedBody = array_merge($parsedBody, $request->getQueryParams());
            }

            // If still empty, try to parse manually (fallback)
            if (empty($parsedBody) || !isset($parsedBody['rteId'])) {
                // This shouldn't happen in TYPO3, but as a fallback
                $body = $request->getBody()->getContents();
                if (!empty($body)) {
                    parse_str($body, $manualParsed);
                    $parsedBody = array_merge($parsedBody ?? [], $manualParsed);
                }
            }

            if (empty($parsedBody['rteId'])) {
                $response = $this->responseFactory->createResponse(400)
                    ->withHeader('Content-Type', 'application/json; charset=utf-8');
                $response->getBody()->write(json_encode(
                    [
                        'error' => true,
                        'message' => 'rteId is required',
                        'received' => array_keys($parsedBody ?? []),
                    ],
                    JSON_THROW_ON_ERROR
                ));
                return $response;
            }

            $createdAt = time();
            $rteID = $parsedBody['rteId'];
            $commentId = $parsedBody['id'] ?? null;
            $threadId = $parsedBody['thread_id'] ?? null;
            $content = $parsedBody['content'] ?? '';

            // Try to extract content_id from rteId, but don't fail if it doesn't match
            $contentId = null;
            if (preg_match('/data\[tt_content\]\[(\d+)\]\[bodytext\]/', $rteID, $matches)) {
                $contentId = $matches[1];
            } elseif (preg_match('/data\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]/', $rteID, $matches)) {
                // Try alternative format
                $contentId = $matches[1] ?? null;
            }

            $response = $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8');

            $data = [
                'content_id' => $contentId ?? 0,
                'rte_id' => $rteID,
                'user_id' => $this->currentUser,
                'thread_id' => $threadId,
                'id' => $commentId,
                'content' => $content,
                'created_at' => $createdAt,
            ];

            $this->commentRepository->saveComment($data);

            $response->getBody()->write(json_encode(
                [
                    'id' => $commentId,
                    'created_at' => $createdAt,
                ],
                JSON_THROW_ON_ERROR
            ));

            return $response;
        } catch (\Exception $e) {
            $response = $this->responseFactory->createResponse(500)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');

            $response->getBody()->write(json_encode(
                [
                    'error' => true,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                JSON_THROW_ON_ERROR
            ));

            return $response;
        }
    }

}
