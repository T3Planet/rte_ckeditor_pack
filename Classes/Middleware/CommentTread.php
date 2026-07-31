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
        $this->currentUser = 0;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Resolve BE user per request — never in the constructor.
        // FE Visual Editor authenticates after middleware construction; shared DI
        // would otherwise keep currentUser=0 and reject all /comments writes.
        $this->currentUser = $this->resolveBackendUserId();

        $target = $request->getRequestTarget();
        $path = parse_url($target, PHP_URL_PATH) ?: $target;
        $isCommentsRoute = str_contains($target, '/comments/thread/')
            || str_contains($target, '/comments/update/')
            || str_contains($target, '/comments/delete/')
            || str_contains($target, '/comments/archive/')
            || $path === '/comments'
            || $path === '/comments/';

        if (!$isCommentsRoute) {
            return $handler->handle($request);
        }

        // Non-RTC Comments are editorial data — require a logged-in backend user (FormEngine + VE).
        if (!$this->hasBackendUser()) {
            return $this->jsonErrorResponse('Backend authentication required', 401);
        }

        if (str_contains($target, '/comments/thread/')) {
            return $this->fetchAllComments($request);
        }
        if (str_contains($target, '/comments/update/')) {
            return $this->updateComment($request);
        }
        if (str_contains($target, '/comments/delete/')) {
            return $this->deleteComment($request);
        }
        if (str_contains($target, '/comments/archive/')) {
            return $this->archiveResolvedComments($request);
        }
        if ($path === '/comments' || $path === '/comments/') {
            return $this->addComment($request);
        }

        return $handler->handle($request);
    }

    private function resolveBackendUserId(): int
    {
        try {
            $context = GeneralUtility::makeInstance(Context::class);
            $userId = (int)$context->getPropertyFromAspect('backend.user', 'id');
            if ($userId > 0) {
                return $userId;
            }
        } catch (AspectNotFoundException) {
            // Fall through to $GLOBALS['BE_USER']
        }

        return (int)($GLOBALS['BE_USER']->user['uid'] ?? 0);
    }

    private function hasBackendUser(): bool
    {
        return (int)$this->currentUser > 0;
    }

    private function jsonErrorResponse(string $message, int $status = 400): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode([
            'error' => true,
            'status' => 'error',
            'message' => $message,
        ], JSON_THROW_ON_ERROR));

        return $response;
    }

    /**
     * @param $request
     * @return \Psr\Http\Message\MessageInterface|ResponseInterface
     */
    private function fetchAllComments($request)
    {
        $threadId = (string)($request->getQueryParams()['threadId'] ?? '');
        if ($threadId === '') {
            return $this->jsonErrorResponse('threadId is required', 400);
        }

        $data = $this->commentRepository->fetchCommentsByThreatId($threadId) ?: [];
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
        if ((int)$comment['user_id'] !== (int)$this->currentUser) {
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
        if ((int)$comment['user_id'] !== (int)$this->currentUser) {
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
                $parsedBody = array_merge(
                    is_array($parsedBody) ? $parsedBody : [],
                    $request->getQueryParams()
                );
            }

            // If still empty, try to parse manually (fallback)
            if (empty($parsedBody) || !isset($parsedBody['rteId'])) {
                $body = $request->getBody()->getContents();
                if (!empty($body)) {
                    parse_str($body, $manualParsed);
                    $parsedBody = array_merge(is_array($parsedBody) ? $parsedBody : [], $manualParsed);
                }
            }

            if (!is_array($parsedBody) || empty($parsedBody['rteId'])) {
                return $this->jsonErrorResponse('rteId is required', 400);
            }

            if (!str_starts_with((string)$parsedBody['rteId'], 'data[')) {
                return $this->jsonErrorResponse('Invalid rteId format', 400);
            }

            $createdAt = time();
            $rteID = $parsedBody['rteId'];
            $commentId = $parsedBody['id'] ?? null;
            $threadId = $parsedBody['thread_id'] ?? null;
            $content = $parsedBody['content'] ?? '';

            // Try to extract content_id from rteId, but don't fail if it doesn't match
            $contentId = null;
            if (preg_match('/data\[tt_content\]\[(\d+)\]\[bodytext\]/', $rteID, $matches)) {
                $contentId = (int)$matches[1];
            } elseif (preg_match('/data\[([^\]]+)\]\[(\d+)\]\[([^\]]+)\]/', $rteID, $matches)) {
                $contentId = (int)$matches[2];
            }

            $response = $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8');

            $data = [
                'content_id' => $contentId ?? 0,
                'rte_id' => $rteID,
                'user_id' => (int)$this->currentUser,
                'thread_id' => $threadId,
                'id' => $commentId,
                'content' => $content,
                'created_at' => $createdAt,
            ];

            // Idempotent for FormEngine + Visual Editor double-writes of the same comment.
            if ($commentId && $this->commentRepository->checkExisting($commentId)) {
                $this->commentRepository->updateComment((string)$commentId, (string)$threadId, (string)$content);
            } else {
                $this->commentRepository->saveComment($data);
            }

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

    /**
     * Archive resolved comments
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function archiveResolvedComments(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = $request->getParsedBody();
            $rteId = $parsedBody['rteId'] ?? null;
            $resolvedDataJson = $parsedBody['resolvedData'] ?? null;

            $response = $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8');

            if (!$rteId || !$resolvedDataJson) {
                $response->getBody()->write(json_encode([
                    'status' => 'error',
                    'message' => 'Missing required parameters'
                ], JSON_THROW_ON_ERROR));
                return $response;
            }

            $resolvedData = json_decode($resolvedDataJson, true);
            
            if (!is_array($resolvedData)) {
                $response->getBody()->write(json_encode([
                    'status' => 'error',
                    'message' => 'Invalid resolved data format'
                ], JSON_THROW_ON_ERROR));
                return $response;
            }

            $archivedCount = 0;
            foreach ($resolvedData as $threadData) {
                $threadId = $threadData['threadId'] ?? null;
                $resolvedAt = $threadData['resolvedAt'] ?? time();
                $resolvedBy = $threadData['resolvedBy'] ?? $this->currentUser;

                if ($threadId) {
                    $this->commentRepository->markThreadAsResolved(
                        $threadId,
                        (int)$resolvedAt,
                        (int)$resolvedBy
                    );
                    $archivedCount++;
                }
            }

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => "Successfully archived {$archivedCount} comment thread(s)",
                'archived' => $archivedCount
            ], JSON_THROW_ON_ERROR));

            return $response;
        } catch (\Exception $e) {
            $response = $this->responseFactory->createResponse(500)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');

            $response->getBody()->write(json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ], JSON_THROW_ON_ERROR));

            return $response;
        }
    }

}
