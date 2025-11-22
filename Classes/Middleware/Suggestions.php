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
use T3Planet\RteCkeditorPack\Domain\Repository\SuggestionRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Suggestions implements MiddlewareInterface
{
    /** @var ResponseFactoryInterface */
    private $responseFactory;

    private $currentUser;

    protected SuggestionRepository $suggestionRepository;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->suggestionRepository = GeneralUtility::makeInstance(SuggestionRepository::class);

        //Get Current Backend user..
        $context = GeneralUtility::makeInstance(Context::class);
        $this->currentUser = $context->getPropertyFromAspect('backend.user', 'id');

    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $default = 1;
        $response = '';
        if (str_contains($request->getRequestTarget(), 'ckeditor_premium/suggestions/get/')) {
            $default = 0;
            $response = $this->fetchAllSuggestions($request);
        }
        if (str_contains($request->getRequestTarget(), 'ckeditor_premium/suggestions/update/')) {
            $default = 0;
            $response = $this->updateSuggestion($request);
        }

        if ($request->getRequestTarget() === '/ckeditor_premium/suggestions/' || $request->getRequestTarget() === '/ckeditor_premium/suggestions') {
            $default = 0;
            $response = $this->addSuggestion($request);
        }
        if ($default) {
            return $handler->handle($request);
        }
        return $response;
    }

    /**
     * @param $request
     * @return \Psr\Http\Message\MessageInterface|ResponseInterface
     */
    private function fetchAllSuggestions($request)
    {
        $suggestionId = $request->getQueryParams()['suggestionId'];
        $data = $this->suggestionRepository->fetchSuggestionsById($suggestionId);
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($data));
        return $response;
    }

    /**
     * @throws AspectNotFoundException
     * @throws \JsonException
     */
    private function updateSuggestion($request)
    {
        $suggestionId = $request->getQueryParams()['suggestionId'];
        $reqArguments = $request->getParsedBody();
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        if (!$suggestionId) {
            $response->getBody()->write(json_encode(
                [
                    'status' => 'success',
                    'message' => 'Could not update suggestion - invalid request.',
                ],
                JSON_THROW_ON_ERROR
            ));
            return $response;
        }

        $suggestion = $this->suggestionRepository->getSuggestion($suggestionId);

        if (empty($suggestion)) {
            $response->getBody()->write(json_encode(
                [
                    'status' => 'error',
                    'message' => 'Suggestion not found',
                ],
                JSON_THROW_ON_ERROR
            ));
            return $response;
        }

        $hasComments = isset($reqArguments['has_comments']) && $reqArguments['has_comments'] === 'true';

        $this->suggestionRepository->updateSuggestion($suggestionId, $hasComments);
        $response->getBody()->write(json_encode(
            [
                'status' => 'success',
                'message' => 'Suggestion Updated',
            ],
            JSON_THROW_ON_ERROR
        ));

        return $response;
    }

    private function addSuggestion($request)
    {
        $createdAt = time();
        $suggestionId = $request->getParsedBody()['id'] ?? null;

        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        $authorId = $this->currentUser;
        $type = $request->getParsedBody()['type'] ?? null;
        $data = $request->getParsedBody()['data'] ?? null;

        /**
         * If the `original_suggestion_id` field is set, it means that this suggestion has been
         * created as a result of editing other existing suggestion contents (e.g. the existing
         * suggestion could be split to two separate suggestions).
         * In this case, the current application user may be not the original author of this
         * suggestion, so it is required to fetch the original suggestion and assign the
         * id of the original author.
         */
        if (!empty($request->getParsedBody()['original_suggestion_id'])) {
            $originalSuggestion = $this->getSuggestion($request->getParsedBody()['original_suggestion_id']);

            if (!empty($originalSuggestion)) {
                $authorId = $originalSuggestion['user_id'];
                $createdAt = $originalSuggestion['created_at'];
                $type = $originalSuggestion['type'];
                $data = $originalSuggestion['data'];
            }
        }

        $suggestionData = [
            'id' => $suggestionId,
            'type' => $type,
            'user_id' => $authorId,
            'created_at' => $createdAt,
            'data' => $data,
        ];

        $this->suggestionRepository->saveSuggestion($suggestionData);
        $response->getBody()->write(json_encode(
            [
                'id' => $suggestionId,
                'created_at' => $createdAt,
            ],
            JSON_THROW_ON_ERROR
        ));
        return $response;
    }

    private function getSuggestion(string $suggestionId): ?array
    {
        $suggestion = $this->suggestionRepository->checkExisting($suggestionId);
        if (!empty($suggestion['data'])) {
            $suggestion['data'] = \json_decode($suggestion['data']);
        }

        return $suggestion;
    }

}
