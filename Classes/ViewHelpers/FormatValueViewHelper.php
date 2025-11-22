<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3Planet\RteCkeditorPack\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class FormatValueViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'input',
            'mix',
            'return string',
            true
        );

    }

    public function render(): string
    {
        $result = '';
        if ($this->arguments['input']) {
            $input = $this->arguments['input'];

            if (!is_array($input)) {
                return $input;
            }
            foreach ($input as $value) {
                $result .= $value . ',';
            }

        }
        return $result;

    }

}
