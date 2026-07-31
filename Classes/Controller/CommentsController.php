<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Controller;

use Psr\Http\Message\ServerRequestInterface;
use T3Planet\RteCkeditorPack\Domain\Repository\CommentsRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\JsonResponse;

class CommentsController
{
    protected CommentsRepository $commentRepository;

    public function __construct(
        CommentsRepository $commentRepository,
        private readonly Context $context,
    ) {
        $this->commentRepository = $commentRepository;
    }

    public function saveCommentsAction(ServerRequestInterface $request): JsonResponse
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return new JsonResponse(['status' => 'ERROR', 'message' => 'Invalid request body'], 400);
        }

        $rteID = (string)($body['rteId'] ?? '');
        $rawComments = $body['commentsData'] ?? '[]';
        $threadData = is_string($rawComments) ? json_decode($rawComments, true) : $rawComments;
        if (!is_array($threadData)) {
            return new JsonResponse(['status' => 'ERROR', 'message' => 'Invalid comments payload'], 400);
        }

        $userId = (int)$this->context->getPropertyFromAspect('backend.user', 'id');

        foreach ($threadData as $thread) {
            if (!is_array($thread) || empty($thread['threadId']) || empty($thread['comments']) || !is_array($thread['comments'])) {
                continue;
            }

            $isResolved = isset($thread['resolvedAt']) || isset($thread['resolvedBy']);
            $resolvedAt = null;
            $resolvedBy = null;

            if ($isResolved) {
                $resolvedAt = $this->normalizeTimestamp($thread['resolvedAt'] ?? null) ?? time();
                $resolvedBy = isset($thread['resolvedBy'])
                    ? (int)$thread['resolvedBy']
                    : $userId;
            }

            foreach ($thread['comments'] as $comment) {
                if (!is_array($comment) || empty($comment['commentId'])) {
                    continue;
                }

                if ($this->commentRepository->checkExisting($comment['commentId'])) {
                    if ($isResolved) {
                        $this->commentRepository->markThreadAsResolved(
                            (string)$thread['threadId'],
                            $resolvedAt,
                            $resolvedBy
                        );
                    } else {
                        $this->commentRepository->markThreadAsUnresolved(
                            (string)$thread['threadId']
                        );
                    }
                    continue;
                }

                $contentId = 0;
                if (preg_match('/data\[[^\]]+\]\[(\d+)\]\[[^\]]+\]/', $rteID, $matches)) {
                    $contentId = (int)$matches[1];
                }

                $data = [
                    'content_id' => $contentId,
                    'rte_id' => $rteID,
                    'user_id' => $userId,
                    'thread_id' => (string)$thread['threadId'],
                    'id' => (string)$comment['commentId'],
                    'content' => (string)($comment['content'] ?? ''),
                    'created_at' => $this->normalizeTimestamp($comment['createdAt'] ?? null) ?? time(),
                    'resolved_at' => $resolvedAt,
                    'resolved_by' => $resolvedBy,
                ];
                $this->commentRepository->saveComment($data);
            }
        }

        return new JsonResponse(['status' => 'OK']);
    }

    /**
     * @throws \JsonException
     */
    public function fetchCommentsAction(ServerRequestInterface $request): JsonResponse
    {
        $rteId = (string)($request->getQueryParams()['threadId'] ?? '');
        if ($rteId === '') {
            return new JsonResponse([]);
        }

        $comments = $this->commentRepository->fetchCommentsByThreatId($rteId) ?: [];

        return new JsonResponse($comments);
    }

    private function normalizeTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $asInt = (int)$value;
            // CKEditor sometimes sends ms
            return $asInt > 9999999999 ? (int)floor($asInt / 1000) : $asInt;
        }
        if (is_string($value)) {
            $parsed = strtotime($value);
            return $parsed !== false ? $parsed : null;
        }

        return null;
    }
}
