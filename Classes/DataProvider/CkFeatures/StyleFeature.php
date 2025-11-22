<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class StyleFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'style' => [
                (new Field())
                    ->setName('Definitions')
                    ->setKey('definitions')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue([
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('Lead'),

                            (new Field())
                                ->setName('Element')
                                ->setKey('element')
                                ->setType(FieldType::INPUT)
                                ->setValue('p'),

                            (new Field())
                                ->setName('Class List')
                                ->setKey('classes')
                                ->setType(FieldType::VALUE_LIST)
                                ->setValue(['lead']),
                        ],
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('Small'),

                            (new Field())
                                ->setName('Element')
                                ->setKey('element')
                                ->setType(FieldType::INPUT)
                                ->setValue('small'),

                            (new Field())
                                ->setName('Class List')
                                ->setKey('classes')
                                ->setType(FieldType::VALUE_LIST)
                                ->setValue([]),
                        ],
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('Muted'),

                            (new Field())
                                ->setName('Element')
                                ->setKey('element')
                                ->setType(FieldType::INPUT)
                                ->setValue('span'),

                            (new Field())
                                ->setName('Class List')
                                ->setKey('classes')
                                ->setType(FieldType::VALUE_LIST)
                                ->setValue(['text-muted']),
                        ],
                    ]),
            ],
        ];
    }
}
