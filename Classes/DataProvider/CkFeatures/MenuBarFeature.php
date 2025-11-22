<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class MenuBarFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'menuBar' => [
                (new Field())
                    ->setName('Menubar Visibility')
                    ->setKey('isVisible')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),

            ],
        ];
    }
}
