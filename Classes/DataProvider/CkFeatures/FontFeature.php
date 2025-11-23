<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class FontFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            (new Field())
                ->setName('')
                ->setKey('')
                ->setType(FieldType::MULTIFIELD)
                ->setValue(
                    [
                        'fontSize' => [
                            (new Field())
                                ->setName('Font Size Options')
                                ->setKey('options')
                                ->setType(FieldType::VALUE_LIST)
                                ->setValue([8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22]),
                        ],
                        'fontFamily' => [
                            (new Field())
                                ->setName('Font Family Options')
                                ->setKey('options')
                                ->setType(FieldType::VALUE_LIST)
                                ->setValue(
                                    [
                                        'default',
                                        'Ubuntu, Arial, sans-serif',
                                        'Ubuntu Mono, Courier New, Courier, monospace',
                                    ]
                                ),
                        ],
                    ]
                ),
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-font',
                'exports' => 'Font',
            ],
        ];
    }
}
