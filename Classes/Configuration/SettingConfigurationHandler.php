<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Configuration;

use T3Planet\RteCkeditorPack\Domain\Repository\ConfigurationRepository;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SettingConfigurationHandler implements SingletonInterface
{
    /**
     * @var array
    */
    protected $config;

    public function __construct(
    ) {
        $configurationRepository = GeneralUtility::makeInstance(ConfigurationRepository::class);
        $this->config = $configurationRepository->fetchConfiguration('FeatureConfiguration');
    }

    public function getTokenUrl(): string
    {
        $type = $this->config['authType'] ?? '';

        if ($type === 'dev_token' && $token_url = $this->getDevelopmentTokenUrl()) {
            return $token_url;
        }

        if ($type === 'key' && $this->getAccessKey() && $this->getEnvironmentId()) {
            $baseUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
            // Check if the base URL ends with a slash and remove it
            if (substr($baseUrl, -1) === '/') {
                $baseUrl = substr($baseUrl, 0, -1);
            }
            // Concatenate with '/ckeditor5-premium/token'
            return $baseUrl . '/ckeditor5-premium/token';
        }

        // The empty string allows to use the evaluation version note.
        return '';
    }

    public function getAccessKey(): ?string
    {
        return $this->config['accessKey'];
    }

    public function getEnvironmentId(): ?string
    {
        return $this->config['environmentId'];
    }

    public function getDevelopmentTokenUrl(): ?string
    {
        return $this->config['tokenUrl'];
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
