<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class FlashUtility
{
    protected PageRenderer $pageRenderer;

    public function __construct(
    ) {
        $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
    }

    public function addFlashNotification(array $response = [])
    {
        if ($response && (isset($response['title']))) {
            $title = LocalizationUtility::translate($response['title'], 'RteCkeditorPack');
            $message = $response['message'] ? LocalizationUtility::translate($response['message'], 'RteCkeditorPack') : '';
            $severity = isset($response['severity']) ? $response['severity'] : 0;
            $notificationInstruction = JavaScriptModuleInstruction::create('@typo3/backend/notification.js');
            $notificationInstruction->invoke('showMessage', $title, $message, (int)$severity);
            $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction($notificationInstruction);
        }
    }
}
