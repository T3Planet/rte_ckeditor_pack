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
use T3Planet\RteCkeditorPack\Domain\Repository\RevisionHistoryRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RevisionHistory implements MiddlewareInterface
{
    /** @var ResponseFactoryInterface */
    private $responseFactory;

    private $currentUser;

    protected RevisionHistoryRepository $revisionHistoryRepository;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->revisionHistoryRepository = GeneralUtility::makeInstance(RevisionHistoryRepository::class);

        //Get Current Backend user..
        $context = GeneralUtility::makeInstance(Context::class);
        $this->currentUser = $context->getPropertyFromAspect('backend.user', 'id');

    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $default = 1;
        $response = '';
        if (str_contains($request->getRequestTarget(), '/ckeditor-premium/revisions/update/')) {
            $default = 0;
            $response = $this->addUpdateSuggestion($request);
        }
        if ($default) {
            return $handler->handle($request);
        }
        return $response;
    }

    private function addUpdateSuggestion($request)
    {
        $paramsJson = $request->getParsedBody();
        $revisionsData = json_decode($paramsJson['revisionsData'], true);
        $contentId = $paramsJson['contentId'];
        $createdAt = time();
        $authors = [(string)$this->currentUser];
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        if ($revisionsData) {
            foreach ($revisionsData as $params) {
                $name = $params['name'] ?: 'Save Content';
                $exist = $this->revisionHistoryRepository->checkExisting($params['id']);
                if ($exist) {
                    // Don't update existing revisions - they should be immutable
                    // Only update if name changed (metadata update)
                    if ($exist['name'] !== $name) {
                        $data = [
                            'id' => $exist['id'],
                            'name' => $name,
                            'created_at' => $exist['created_at'],
                            'attributes' => null,
                            'diff_data' => $exist['diff_data'],
                            'content_id' => $exist['content_id'],
                            'authors' => $exist['authors'],
                            'previous_version' => $exist['previous_version'],
                            'current_version' => $exist['current_version'],
                        ];
                        $this->revisionHistoryRepository->updateRevisions($params['id'], $data);
                    }
                } else {
                    // Encode diffData for database storage
                    $diffData = $params['diffData'] ?? null;
                    if (is_string($diffData)) {
                        $decoded = json_decode($diffData, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $diffData = $decoded;
                        } else {
                            $diffData = null;
                        }
                    }

                    // Only save non-empty diffData, otherwise save null
                    if ($diffData !== null && ((is_array($diffData) && count($diffData) > 0) || is_object($diffData))) {
                        $encodedDiffData = json_encode($diffData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if ($encodedDiffData === false) {
                            $encodedDiffData = null;
                        }
                    } else {
                        $encodedDiffData = null;
                    }

                    $data = [
                        'id' => $params['id'],
                        'name' => $name,
                        'created_at' => $createdAt,
                        'attributes' => null,
                        'diff_data' => $encodedDiffData,
                        'content_id' => $contentId,
                        'authors' => serialize($authors),
                        'previous_version' => $params['fromVersion'],
                        'current_version' => $params['toVersion'],
                    ];
                    $this->revisionHistoryRepository->saveRevision($data);
                }
            }
        }
        if (!$revisionsData) {
            $response->getBody()->write(json_encode([]));
        } else {
            $response->getBody()->write(json_encode($revisionsData));
        }
        return $response;
    }
}
