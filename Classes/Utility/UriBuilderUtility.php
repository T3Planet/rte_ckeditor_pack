<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\Utility;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UriBuilderUtility
{
    protected UriBuilder $backendUriBuilder;

    public function __construct(
    ) {
        $this->backendUriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
    }

    public function generateBackendUrl(string $route, array $parameters = [], string $referenceType = 'absolute'): string
    {
        return (string)$this->backendUriBuilder->buildUriFromRoute($route, $parameters, $referenceType);
    }

}
