<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Service;

use GuzzleHttp\Exception\ClientException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TokenUrlValidator
{
    protected RequestFactory $requestFactory;

    public function __construct()
    {
        $this->requestFactory =  GeneralUtility::makeInstance(RequestFactory::class);
    }

    /**
     * Check URL response status
     *
     * @return bool  True if status 200, false otherwise
     */
    public function validateUrl(string $url): bool
    {
        try {
            $response = $this->requestFactory->request($url, 'GET');
            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (ClientException $e) {
            return false;
        }
        return false;
    }

}
