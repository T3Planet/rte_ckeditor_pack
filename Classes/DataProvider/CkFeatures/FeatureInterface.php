<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

/**
 * Interface for CKEditor feature configuration classes
 */
interface FeatureInterface
{
    /**
     * Get feature configuration array
     *
     * @return array Feature configuration array
     */
    public function getConfiguration(): array;
}
