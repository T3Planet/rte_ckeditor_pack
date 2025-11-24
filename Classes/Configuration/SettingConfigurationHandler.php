<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Configuration;

use T3Planet\RteCkeditorPack\Utility\ExtensionConfigurationUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SettingConfigurationHandler implements SingletonInterface
{
    public function __construct()
    {
        // No need to load config in constructor, use ExtensionConfigurationUtility directly
    }

    public function getTokenUrl(): string
    {
        $authType = ExtensionConfigurationUtility::get('authType', 'none');

        if ($authType === 'dev_token') {
            $tokenUrl = $this->getDevelopmentTokenUrl();
            if ($tokenUrl) {
                return $tokenUrl;
            }
        }

        if ($authType === 'key') {
            $accessKey = $this->getAccessKey();
            $environmentId = $this->getEnvironmentId();
            if ($accessKey && $environmentId) {
                $baseUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
                // Check if the base URL ends with a slash and remove it
                if (substr($baseUrl, -1) === '/') {
                    $baseUrl = substr($baseUrl, 0, -1);
                }
                // Concatenate with '/ckeditor5-premium/token'
                return $baseUrl . '/ckeditor5-premium/token';
            }
        }

        // The empty string allows to use the evaluation version note.
        return '';
    }

    public function getAccessKey(): ?string
    {
        $accessKey = ExtensionConfigurationUtility::get('accessKey', '');
        return $accessKey ?: null;
    }

    public function getEnvironmentId(): ?string
    {
        $environmentId = ExtensionConfigurationUtility::get('environmentId', '');
        return $environmentId ?: null;
    }

    public function getDevelopmentTokenUrl(): ?string
    {
        $tokenUrl = ExtensionConfigurationUtility::get('tokenUrl', '');
        return $tokenUrl ?: null;
    }

    public function getBaseUrl(): ?string
    {
        $baseUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        // Check if the base URL ends with a slash and remove it
        if (substr($baseUrl, -1) === '/') {
            $baseUrl = substr($baseUrl, 0, -1);
        }
        // Concatenate with '/ckeditor5-premium/token'
        return $baseUrl;
    }
}
