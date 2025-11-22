<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\ViewHelpers;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class UriActionViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('route', 'string', 'return url for given route', true);
        $this->registerArgument('arguments', 'array', 'argument for given route', false);
    }

    public function render(): string
    {
        $url = '';
        if (empty($this->arguments['route'])) {
            return $url;
        }
        $arguments = $this->arguments['arguments'] ?? [];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $url =  $uriBuilder->buildUriFromRoute($this->arguments['route'], $arguments);
        return $url->__toString();
    }

}
