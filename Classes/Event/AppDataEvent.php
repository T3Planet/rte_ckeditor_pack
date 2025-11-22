<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Event;

use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Beuser\Domain\Repository\BackendUserRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

final class AppDataEvent
{
    public function __invoke(AfterFormEnginePageInitializedEvent $event): void
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        $currentUser = $context->getPropertyFromAspect('backend.user', 'id');
        $backendUsers = $backendUserRepository->findAll();
        $appData = [];
        if ($backendUsers) {
            foreach ($backendUsers as $backendUser) {
                $userData = [
                    'id' => (string)$backendUser->getUid(),
                    'name' => $backendUser->getRealName() ? $backendUser->getRealName() : $backendUser->getUsername(),
                ];

                // AVATAR: Add avatar URL to user data if available
                $account = BackendUtility::getRecord('be_users', $backendUser->getUid());
                if ($account && !empty($account['avatar'])) {
                    $fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
                    $fileObjects = $fileRepository->findByRelation('be_users', 'avatar', $backendUser->getUid());
                    if ($fileObjects && isset($fileObjects[0]) && $fileObjects[0]->getPublicUrl()) {
                        $publicUrl = $fileObjects[0]->getPublicUrl();
                        if (PathUtility::hasProtocolAndScheme($publicUrl)) {
                            $userData['avatar'] = $publicUrl;
                        } else {
                            $baseUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
                            if (str_ends_with($baseUrl, '/')) {
                                $baseUrl = rtrim($baseUrl, '/');
                            }
                            if (!str_starts_with($publicUrl, '/')) {
                                $publicUrl = '/' . $publicUrl;
                            }
                            $userData['avatar'] = $baseUrl . $publicUrl;
                        }
                    }
                }

                $appData['users'][] = $userData;
            }
        }
        $appData['userId'] = (string)$currentUser;
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineSetting('AppData', 'appData', $appData);
    }
}
