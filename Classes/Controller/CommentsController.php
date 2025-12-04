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
use TYPO3\CMS\Core\Http\Response;

class CommentsController
{
    protected CommentsRepository $commentRepository;

    public function __construct(
        CommentsRepository $commentRepository,
        private readonly Context $context,
    ) {
        $this->commentRepository = $commentRepository;
    }

    public function saveCommentsAction(ServerRequestInterface $request): Response
    {
        $rteID = $request->getParsedBody()['rteId'];
        $threadData = json_decode($request->getParsedBody()['commentsData'], true);
        $userId = $this->context->getPropertyFromAspect('backend.user', 'id');
        if ($threadData) {
            foreach ($threadData as $thread) {
                // Handle resolved status at thread level
                $isResolved = isset($thread['resolvedAt']) || isset($thread['resolvedBy']);
                $resolvedAt = null;
                $resolvedBy = null;
                
                if ($isResolved) {
                    $resolvedAt = isset($thread['resolvedAt']) 
                        ? strtotime($thread['resolvedAt']) 
                        : time();
                    // Cast resolvedBy to int - it comes as string from JSON
                    $resolvedBy = isset($thread['resolvedBy']) 
                        ? (int)$thread['resolvedBy'] 
                        : $userId;
                }
                
                foreach ($thread['comments'] as $comment) {
                    if ($this->commentRepository->checkExisting($comment['commentId'])) {
                        // Update existing comment's resolved status
                        if ($isResolved) {
                            $this->commentRepository->markThreadAsResolved(
                                $thread['threadId'],
                                $resolvedAt,
                                $resolvedBy
                            );
                        } else {
                            // Comment was reopened from archive - mark as unresolved
                            $this->commentRepository->markThreadAsUnresolved(
                                $thread['threadId']
                            );
                        }
                        continue;
                    }
                    $data = [
                        'rte_id' => $rteID,
                        'user_id' => $userId,
                        'thread_id' => $thread['threadId'],
                        'id' => $comment['commentId'],
                        'content' => $comment['content'],
                        'created_at' => strtotime($comment['createdAt']),
                        'resolved_at' => $resolvedAt ? (int)$resolvedAt : null,
                        'resolved_by' => $resolvedBy ? (int)$resolvedBy : null,
                    ];
                    $this->commentRepository->saveComment($data);
                }
            }
        }
        $response = new Response();
        $response->getBody()->write(
            json_encode(['status' => 'OK'], JSON_THROW_ON_ERROR)
        );
        return $response;
    }

    /**
     * @throws \JsonException
     */
    public function fetchCommentsAction(ServerRequestInterface $request): Response
    {
        $response = new Response();
        $rteId = $request->getQueryParams()['threadId'];
        $comments = $this->commentRepository->fetchCommentsByThreatId($rteId);
        if ($comments) {
            $response->getBody()->write(
                json_encode($comments, JSON_THROW_ON_ERROR)
            );
        }
        return $response;
    }
}
