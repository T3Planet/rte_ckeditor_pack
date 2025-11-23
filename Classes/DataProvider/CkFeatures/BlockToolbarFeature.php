<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class BlockToolbarFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'blockToolbar' => [
                (new Field())
                    ->setName('Block Toolbar Items')
                    ->setKey('')
                    ->setType(FieldType::VALUE_LIST)
                    ->setValue(['style', 'heading', '|', 'bulletedList', 'numberedList', '|', 'blockQuote']),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-ui',
                'exports' => 'BlockToolbar',
            ],
        ];
    }
}
