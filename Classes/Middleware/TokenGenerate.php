<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Middleware;

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use T3Planet\RteCkeditorPack\Configuration\SettingConfigurationHandler;
use T3Planet\RteCkeditorPack\Domain\Repository\CommentsRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TokenGenerate implements MiddlewareInterface
{
    public const ALGORITHM = 'HS512';

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    private $currentUser;

    /** @var SettingConfigurationHandler */
    private $settingsConfigHandler;

    protected CommentsRepository $commentRepository;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (str_contains($request->getRequestTarget(), '/ckeditor5-premium/token')) {
            //Get Current Backend user..
            $context = GeneralUtility::makeInstance(Context::class);
            $this->currentUser = $context->getPropertyFromAspect('backend.user', 'id');
            $this->settingsConfigHandler = GeneralUtility::makeInstance(SettingConfigurationHandler::class);
            $token = (string)$this->getToken();
            $response = $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/text; charset=utf-8');
            $response->getBody()->write($token);
            return $response;
        }
        return $handler->handle($request);
    }

    private function getToken(): string
    {
        $payload = [
            'aud' => $this->settingsConfigHandler->getEnvironmentId(),
            'iat' => time(),
            'sub' => (string)$this->currentUser,
            'auth' => [
                'collaboration' => [
                    '*' => [
                        'role' => 'writer',
                    ],
                ],
            ],
        ];

        $userData = $this->getUserData();
        $payload['user'] = $userData;
        return JWT::encode($payload, $this->settingsConfigHandler->getAccessKey(), static::ALGORITHM);
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getUserData(): array
    {

        $account = $this->getBackendUser()->user;
        $data = [
            'name' => $account['realName'] ? $account['realName'] : $account['username'],
        ];

        if ($account['email']) {
            $data['email'] = $account['email'];
        }

        if ($account['avatar']) {
            $fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
            $fileObjects = $fileRepository->findByRelation('be_users', 'avatar', $account['uid']);
            if ($fileObjects && $fileObjects[0]->getPublicUrl()) {
                $data['avatar'] = $this->settingsConfigHandler->getBaseUrl() . '/' . $fileObjects[0]->getPublicUrl();
            }
        }
        return $data;
    }
}
